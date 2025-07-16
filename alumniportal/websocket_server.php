<?php
require 'vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class ChatWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if ($data['type'] === 'register') {
            $userId = $data['user_id'];
            $this->userConnections[$userId] = $from;
            echo "User {$userId} registered\n";
            return;
        }
        
        if ($data['type'] === 'message') {
            $this->handleNewMessage($data);
        }
        
        if ($data['type'] === 'typing') {
            $this->handleTypingNotification($data);
        }
        
        if ($data['type'] === 'read_receipt') {
            $this->handleReadReceipt($data);
        }
        
        if ($data['type'] === 'reaction') {
            $this->handleReaction($data);
        }
    }

    protected function handleNewMessage($data) {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Initialize status_info for group messages
            $statusInfo = $data['group_id'] ? [$data['sender_id'] => 'delivered'] : null;
            
            // Save message to database
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, group_id, content, status_info)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['sender_id'],
                $data['recipient_id'] ?? null,
                $data['group_id'] ?? null,
                $data['content'],
                $statusInfo ? json_encode($statusInfo) : null
            ]);
            $messageId = $pdo->lastInsertId();
            
            // For private messages, set is_read for the sender
            if (!$data['group_id']) {
                $stmt = $pdo->prepare("
                    UPDATE messages SET is_read = TRUE 
                    WHERE message_id = ? AND sender_id = ?
                ");
                $stmt->execute([$messageId, $data['sender_id']]);
            }
            
            $pdo->commit();
            
            // Prepare response
            $response = [
                'type' => 'new_message',
                'message_id' => $messageId,
                'sender_id' => $data['sender_id'],
                'content' => $data['content'],
                'timestamp' => date('Y-m-d H:i:s'),
                'group_id' => $data['group_id'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null,
                'status_info' => $statusInfo
            ];
            
            // Broadcast to recipient(s)
            if ($data['group_id']) {
                // Group message - send to all online members except sender
                $stmt = $pdo->prepare("
                    SELECT user_id FROM group_members WHERE group_id = ?
                ");
                $stmt->execute([$data['group_id']]);
                $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($members as $memberId) {
                    if ($memberId != $data['sender_id'] && isset($this->userConnections[$memberId])) {
                        $this->userConnections[$memberId]->send(json_encode($response));
                    }
                }
            } else {
                // Private message - send to recipient if online
                if (isset($this->userConnections[$data['recipient_id']])) {
                    $this->userConnections[$data['recipient_id']]->send(json_encode($response));
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error handling message: " . $e->getMessage());
        }
    }

    protected function handleTypingNotification($data) {
        $response = [
            'type' => 'typing',
            'sender_id' => $data['sender_id'],
            'is_typing' => $data['is_typing']
        ];
        
        if ($data['group_id']) {
            // Group typing notification
            foreach ($this->userConnections as $userId => $conn) {
                if ($userId != $data['sender_id']) {
                    $conn->send(json_encode($response));
                }
            }
        } else {
            // Private chat typing notification
            if (isset($this->userConnections[$data['recipient_id']])) {
                $this->userConnections[$data['recipient_id']]->send(json_encode($response));
            }
        }
    }

    protected function handleReadReceipt($data) {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            if ($data['group_id']) {
                // For groups, update last_seen_message_id in group_members
                $stmt = $pdo->prepare("
                    UPDATE group_members 
                    SET last_seen_message_id = ?
                    WHERE group_id = ? AND user_id = ?
                ");
                $stmt->execute([$data['message_id'], $data['group_id'], $data['user_id']]);
                
                // Update status_info in messages
                $stmt = $pdo->prepare("
                    SELECT status_info FROM messages WHERE message_id = ?
                ");
                $stmt->execute([$data['message_id']]);
                $message = $stmt->fetch();
                
                $statusInfo = $message['status_info'] ? json_decode($message['status_info'], true) : [];
                $statusInfo[$data['user_id']] = 'read';
                
                $stmt = $pdo->prepare("
                    UPDATE messages SET status_info = ? WHERE message_id = ?
                ");
                $stmt->execute([json_encode($statusInfo), $data['message_id']]);
            } else {
                // For private chats, update is_read
                $stmt = $pdo->prepare("
                    UPDATE messages SET is_read = TRUE 
                    WHERE message_id = ? AND recipient_id = ?
                ");
                $stmt->execute([$data['message_id'], $data['user_id']]);
            }
            
            // Notify sender that message was read
            if (isset($this->userConnections[$data['sender_id']])) {
                $response = [
                    'type' => 'read_receipt',
                    'message_id' => $data['message_id'],
                    'reader_id' => $data['user_id'],
                    'group_id' => $data['group_id'] ?? null
                ];
                $this->userConnections[$data['sender_id']]->send(json_encode($response));
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error handling read receipt: " . $e->getMessage());
        }
    }

    protected function handleReaction($data) {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Get current reactions
            $stmt = $pdo->prepare("SELECT reactions FROM messages WHERE message_id = ?");
            $stmt->execute([$data['message_id']]);
            $message = $stmt->fetch();
            $reactions = $message['reactions'] ? json_decode($message['reactions'], true) : [];
            
            // Toggle reaction
            $reactionType = $data['reaction_type'];
            if (!isset($reactions[$reactionType])) {
                $reactions[$reactionType] = [];
            }
            
            $index = array_search($data['user_id'], $reactions[$reactionType]);
            if ($index !== false) {
                unset($reactions[$reactionType][$index]);
            } else {
                $reactions[$reactionType][] = $data['user_id'];
            }
            
            // Save updated reactions
            $stmt = $pdo->prepare("UPDATE messages SET reactions = ? WHERE message_id = ?");
            $stmt->execute([json_encode($reactions), $data['message_id']]);
            
            $pdo->commit();
            
            // Broadcast reaction to all participants
            $response = [
                'type' => 'reaction',
                'message_id' => $data['message_id'],
                'user_id' => $data['user_id'],
                'reaction_type' => $data['reaction_type'],
                'reactions' => $reactions,
                'group_id' => $data['group_id'] ?? null,
                'recipient_id' => $data['recipient_id'] ?? null
            ];
            
            if ($data['group_id']) {
                // Group message reaction - notify all members
                $stmt = $pdo->prepare("
                    SELECT user_id FROM group_members WHERE group_id = ?
                ");
                $stmt->execute([$data['group_id']]);
                $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($members as $memberId) {
                    if (isset($this->userConnections[$memberId])) {
                        $this->userConnections[$memberId]->send(json_encode($response));
                    }
                }
            } else {
                // Private message reaction - notify both parties
                $stmt = $pdo->prepare("
                    SELECT sender_id FROM messages WHERE message_id = ?
                ");
                $stmt->execute([$data['message_id']]);
                $senderId = $stmt->fetchColumn();
                
                if (isset($this->userConnections[$senderId])) {
                    $this->userConnections[$senderId]->send(json_encode($response));
                }
                
                if (isset($this->userConnections[$data['user_id']])) {
                    $this->userConnections[$data['user_id']]->send(json_encode($response));
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error handling reaction: " . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $userId = array_search($conn, $this->userConnections);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
        }
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Database connection
require 'db.php';

// Run the server
$server = IoServer::factory(
    new WsServer(new ChatWebSocket()),
    8080
);

$server->run();