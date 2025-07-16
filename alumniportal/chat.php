<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get connections with unread indicators
$connectionsStmt = $pdo->prepare("
    SELECT u.user_id, u.name, u.profile_pic,
           EXISTS(
               SELECT 1 FROM messages m 
               WHERE ((m.sender_id = u.user_id AND m.recipient_id = ?) OR 
                     (m.sender_id = ? AND m.recipient_id = u.user_id))
               AND m.is_read = FALSE AND m.recipient_id = ?
           ) as has_unread
    FROM connections c
    JOIN users u ON (c.requester_id = u.user_id OR c.recipient_id = u.user_id) AND u.user_id != ?
    WHERE (c.requester_id = ? OR c.recipient_id = ?) AND c.status = 'accepted'
    ORDER BY u.name
");
$connectionsStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
$connections = $connectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get groups with unread indicators
$groupsStmt = $pdo->prepare("
    SELECT g.group_id, g.name, g.description,
           EXISTS(
               SELECT 1 FROM messages m
               JOIN group_members gm ON gm.group_id = m.group_id
               WHERE m.group_id = g.group_id AND gm.user_id = ?
               AND (gm.last_seen_message_id IS NULL OR m.message_id > gm.last_seen_message_id)
           ) as has_unread
    FROM group_members gm
    JOIN groups g ON gm.group_id = g.group_id
    WHERE gm.user_id = ?
    ORDER BY g.name
");
$groupsStmt->execute([$userId, $userId]);
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for selected chat
$selectedChat = null;
$messages = [];
$isGroupChat = false;
$chatMembers = [];

if (isset($_GET['user_id'])) {
    $selectedUserId = $_GET['user_id'];
    $isGroupChat = false;
    
    // Verify connection
    $validConnection = false;
    foreach ($connections as $conn) {
        if ($conn['user_id'] == $selectedUserId) {
            $validConnection = true;
            $selectedChat = $conn;
            break;
        }
    }
    
    if ($validConnection) {
        // Get messages
        $messagesStmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.profile_pic as sender_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)
            ORDER BY m.created_at
        ");
        $messagesStmt->execute([$userId, $selectedUserId, $selectedUserId, $userId]);
        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE sender_id = ? AND recipient_id = ? AND is_read = FALSE
        ")->execute([$selectedUserId, $userId]);
    }
} elseif (isset($_GET['group_id'])) {
    $selectedGroupId = $_GET['group_id'];
    $isGroupChat = true;
    
    // Verify group membership
    $validGroup = false;
    foreach ($groups as $group) {
        if ($group['group_id'] == $selectedGroupId) {
            $validGroup = true;
            $selectedChat = $group;
            break;
        }
    }
    
    if ($validGroup) {
        // Get messages with reactions and status info
        $messagesStmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.profile_pic as sender_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.group_id = ?
            ORDER BY m.created_at
        ");
        $messagesStmt->execute([$selectedGroupId]);
        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get group members
        $membersStmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.profile_pic
            FROM group_members gm
            JOIN users u ON gm.user_id = u.user_id
            WHERE gm.group_id = ?
        ");
        $membersStmt->execute([$selectedGroupId]);
        $chatMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update last seen message
        $latestMessageId = !empty($messages) ? end($messages)['message_id'] : 0;
        $pdo->prepare("
            UPDATE group_members 
            SET last_seen_message_id = ?
            WHERE group_id = ? AND user_id = ?
        ")->execute([$latestMessageId, $selectedGroupId, $userId]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    
    <style>
        /* Previous styles remain, adding new ones below */
        
       
    </style>
</head>
<body>
    <!-- Header -->
<header class="bg-blue-800 text-white p-4 flex justify-between items-center shadow-md">
  <div class="text-2xl font-bold">Alumni Portal</div>
  <nav class="flex space-x-6">
     <a href="main.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-home text-white text-xl"></i>
      <span class="text-sm">home</span>
    </a>
    <a href="notifications.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-bell text-white text-xl"></i>
      <span class="text-sm">Notifications</span>
    </a>
    <a href="connections.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-hands-helping text-white text-xl"></i>
      <span class="text-sm">Connections</span>
    </a>
    <a href="chat.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-envelope text-white text-xl"></i>
      <span class="text-sm">Messages</span>
    </a>
    <a href="questions.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-question-circle text-white text-xl"></i>
      <span class="text-sm">Q&A</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-user-circle text-white text-xl"></i>
      <span class="text-sm">Profile</span>
    </a>
    <a href="logout.php" class="flex flex-col items-center hover:text-red-400">
      <i class="fas fa-sign-out-alt text-white text-xl"></i>
      <span class="text-sm">Logout</span>
    </a>
  </nav>
</header>

<!-- Sidebar -->
<div class="w-full md:w-80 bg-white border-r h-full shadow-sm overflow-y-auto">
  <div class="flex items-center justify-between p-4 border-b">
    <h2 class="text-lg font-semibold text-gray-700">Chats</h2>
    <a href="create_group.php" class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">New Group</a>
  </div>

  <div class="p-2 space-y-1">

    <!-- Connections -->
    <?php foreach ($connections as $connection): ?>
      <a href="?user_id=<?= $connection['user_id'] ?>"
         class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-50 transition-all <?= isset($selectedChat) && !$isGroupChat && $selectedChat['user_id'] == $connection['user_id'] ? 'bg-blue-100' : '' ?>">
        <div class="relative">
          <img src="<?= htmlspecialchars($connection['profile_pic']) ?>" alt="<?= htmlspecialchars($connection['name']) ?>" class="w-10 h-10 rounded-full object-cover">
          <?php if ($connection['has_unread']): ?>
            <span class="absolute top-0 right-0 w-3 h-3 bg-blue-600 border-2 border-white rounded-full"></span>
          <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium truncate text-gray-800"><?= htmlspecialchars($connection['name']) ?></div>
          <div class="text-xs text-gray-500 truncate">Last message preview...</div>
        </div>
      </a>
    <?php endforeach; ?>

    <!-- Groups -->
    <?php foreach ($groups as $group): ?>
      <a href="?group_id=<?= $group['group_id'] ?>"
         class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-blue-50 transition-all <?= isset($selectedChat) && $isGroupChat && $selectedChat['group_id'] == $group['group_id'] ? 'bg-blue-100' : '' ?>">
        <div class="relative">
          <img src="group-default-icon.png" alt="<?= htmlspecialchars($group['name']) ?>" class="w-10 h-10 rounded-full object-cover">
          <?php if ($group['has_unread']): ?>
            <span class="absolute top-0 right-0 w-3 h-3 bg-blue-600 border-2 border-white rounded-full"></span>
          <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium truncate text-gray-800"><?= htmlspecialchars($group['name']) ?></div>
          <div class="text-xs text-gray-500 truncate">Last message preview...</div>
        </div>
      </a>
    <?php endforeach; ?>

  </div>
</div>

    
    <div class="chat-area flex flex-col h-full bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (isset($selectedChat)): ?>
        <!-- Chat Header -->
        <div class="chat-header flex items-center p-4 border-b border-gray-200">
            <img class="w-10 h-10 rounded-full object-cover mr-3" src="<?= $isGroupChat ? 'group-default-icon.png' : htmlspecialchars($selectedChat['profile_pic']) ?>" alt="<?= htmlspecialchars($selectedChat['name']) ?>">
            <div class="chat-header-info">
                <h3 class="text-lg font-semibold"><?= htmlspecialchars($selectedChat['name']) ?></h3>
                <?php if ($isGroupChat): ?>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($selectedChat['description']) ?></p>
                <?php else: ?>
                    <p id="typing-indicator" class="text-sm text-blue-500" style="display: none;">typing...</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="messages-container" class="messages-container flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
            <?php foreach ($messages as $message): 
                $reactions = $message['reactions'] ? json_decode($message['reactions'], true) : [];
                $statusInfo = $message['status_info'] ? json_decode($message['status_info'], true) : [];
            ?>
                <div class="message relative <?= $message['sender_id'] == $userId ? 'text-right' : 'text-left' ?>" data-message-id="<?= $message['message_id'] ?>">
                    <!-- Sender Info in Group Chat -->
                    <?php if ($isGroupChat && $message['sender_id'] != $userId): ?>
                        <div class="message-info flex items-center mb-1">
                            <img class="w-6 h-6 rounded-full mr-2" src="<?= htmlspecialchars($message['sender_pic']) ?>" alt="<?= htmlspecialchars($message['sender_name']) ?>">
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($message['sender_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Message Bubble -->
                    <div class="inline-block bg-white px-4 py-2 rounded-xl shadow-sm max-w-xs <?= $message['sender_id'] == $userId ? 'ml-auto bg-blue-100' : 'bg-gray-100' ?>">
                        <?= nl2br(htmlspecialchars($message['content'])) ?>
                        <div class="text-xs text-gray-400 mt-1"><?= date('h:i A', strtotime($message['created_at'])) ?></div>

                        <!-- Reactions -->
                        <?php if (!empty($reactions)): ?>
                            <div class="flex gap-1 mt-1">
                                <?php foreach ($reactions as $type => $userIds): 
                                    $emoji = [
                                        'like' => '👍', 'love' => '❤️', 'laugh' => '😂',
                                        'wow' => '😮', 'sad' => '😢', 'angry' => '😠', 'thumbs_up' => '👍'
                                    ][$type] ?? $type;
                                ?>
                                    <div class="text-xs flex items-center bg-white px-2 py-1 rounded-full shadow-sm">
                                        <span class="mr-1"><?= $emoji ?></span>
                                        <span><?= count($userIds) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Read Receipt (Group) -->
                        <?php if ($isGroupChat && $message['sender_id'] == $userId && !empty($statusInfo)): ?>
                            <div class="flex items-center gap-1 mt-1">
                                <?php 
                                $readBy = array_filter($statusInfo, fn($s) => $s === 'read');
                                $deliveredTo = array_diff_key($statusInfo, $readBy);
                                foreach ($readBy as $memberId => $status): 
                                    $member = array_filter($chatMembers, fn($m) => $m['user_id'] == $memberId);
                                    $member = reset($member);
                                    if ($member): ?>
                                        <img title="Read by <?= htmlspecialchars($member['name']) ?>" class="w-4 h-4 rounded-full" src="<?= htmlspecialchars($member['profile_pic']) ?>">
                                <?php endif; endforeach; ?>
                                <?php foreach ($deliveredTo as $memberId => $status): 
                                    $member = array_filter($chatMembers, fn($m) => $m['user_id'] == $memberId);
                                    $member = reset($member);
                                    if ($member): ?>
                                        <img title="Delivered to <?= htmlspecialchars($member['name']) ?>" class="w-4 h-4 rounded-full opacity-60" src="<?= htmlspecialchars($member['profile_pic']) ?>">
                                <?php endif; endforeach; ?>
                            </div>
                        <?php elseif (!$isGroupChat && $message['sender_id'] == $userId): ?>
                            <div class="text-xs text-green-600 mt-1"><?= $message['is_read'] ? 'Seen' : 'Delivered' ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Reaction Options (Hidden initially) -->
                    <div class="reaction-options absolute right-0 mt-1 hidden bg-white rounded-lg shadow-md p-2 flex-wrap gap-2">
                        <?php foreach (['like'=>'👍','love'=>'❤️','laugh'=>'😂','wow'=>'😮','sad'=>'😢','angry'=>'😠','thumbs_up'=>'👍'] as $reaction => $emoji): ?>
                            <button class="reaction-btn text-xl hover:scale-110 transition" data-reaction="<?= $reaction ?>"><?= $emoji ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Message Input -->
        <form id="message-form" class="flex items-center gap-2 p-4 border-t bg-white">
            <textarea id="message-input" name="message" rows="1" placeholder="Type a message..." required class="flex-1 resize-none p-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"></textarea>
            <?php if ($isGroupChat): ?>
                <input type="hidden" name="group_id" value="<?= $selectedChat['group_id'] ?>">
            <?php else: ?>
                <input type="hidden" name="recipient_id" value="<?= $selectedChat['user_id'] ?>">
            <?php endif; ?>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Send</button>
        </form>

            <script>
                // WebSocket connection
                const ws = new WebSocket('ws://localhost:8080');
                const userId = <?= $userId ?>;
                const isGroupChat = <?= $isGroupChat ? 'true' : 'false' ?>;
                const chatId = <?= isset($selectedChat) ? ($isGroupChat ? $selectedChat['group_id'] : $selectedChat['user_id']) : 0 ?>;
                
                // Register user with WebSocket server
                ws.onopen = function() {
                    ws.send(JSON.stringify({
                        type: 'register',
                        user_id: userId
                    }));
                };
                
                // Handle incoming messages
                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'new_message') {
                        addNewMessage(data);
                        scrollToBottom();
                    } 
                    else if (data.type === 'typing') {
                        const typingIndicator = document.getElementById('typing-indicator');
                        if (typingIndicator) {
                            typingIndicator.style.display = data.is_typing ? 'block' : 'none';
                        }
                    }
                    else if (data.type === 'read_receipt') {
                        updateReadReceipt(data);
                    }
                    else if (data.type === 'reaction') {
                        updateMessageReaction(data);
                    }
                };
                
                // Send message via WebSocket
                document.getElementById('message-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const messageInput = document.getElementById('message-input');
                    const message = messageInput.value.trim();
                    
                    if (message) {
                        const formData = new FormData(this);
                        ws.send(JSON.stringify({
                            type: 'message',
                            sender_id: userId,
                            recipient_id: formData.get('recipient_id') || null,
                            group_id: formData.get('group_id') || null,
                            content: message
                        }));
                        messageInput.value = '';
                    }
                });
                
                // Typing indicator
                let typingTimeout;
                document.getElementById('message-input').addEventListener('input', function() {
                    ws.send(JSON.stringify({
                        type: 'typing',
                        sender_id: userId,
                        recipient_id: isGroupChat ? null : chatId,
                        group_id: isGroupChat ? chatId : null,
                        is_typing: true
                    }));
                    
                    if (typingTimeout) clearTimeout(typingTimeout);
                    typingTimeout = setTimeout(() => {
                        ws.send(JSON.stringify({
                            type: 'typing',
                            sender_id: userId,
                            recipient_id: isGroupChat ? null : chatId,
                            group_id: isGroupChat ? chatId : null,
                            is_typing: false
                        }));
                    }, 2000);
                });
                
                // Add new message to UI
                function addNewMessage(data) {
                    const container = document.getElementById('messages-container');
                    const isSent = data.sender_id == userId;
                    
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
                    messageDiv.dataset.messageId = data.message_id;
                    
                    if (isGroupChat && !isSent) {
                        // For group messages from others, we'd need to fetch sender info
                        // For simplicity, we'll just show the message without sender info
                        messageDiv.innerHTML = `
                            <div class="message-content">
                                ${escapeHtml(data.content).replace(/\n/g, '<br>')}
                                <div class="message-time">
                                    ${new Date(data.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                </div>
                            </div>
                        `;
                    } else {
                        messageDiv.innerHTML = `
                            <div class="message-content">
                                ${escapeHtml(data.content).replace(/\n/g, '<br>')}
                                <div class="message-time">
                                    ${new Date(data.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                </div>
                            </div>
                        `;
                    }
                    
                    container.appendChild(messageDiv);
                    
                    // Send read receipt if this is a private chat
                    if (!isGroupChat && !isSent) {
                        ws.send(JSON.stringify({
                            type: 'read_receipt',
                            message_id: data.message_id,
                            user_id: userId,
                            sender_id: data.sender_id
                        }));
                    }
                }
                
                // Update read receipt in UI
                function updateReadReceipt(data) {
                    const messageDiv = document.querySelector(`.message[data-message-id="${data.message_id}"]`);
                    if (messageDiv) {
                        if (data.group_id) {
                            // For groups, we'd update the status_info display
                            console.log(`Group message ${data.message_id} was read by user ${data.reader_id}`);
                        } else {
                            // For private chats, update the seen status
                            const statusDiv = messageDiv.querySelector('.read-status');
                            if (statusDiv) {
                                statusDiv.textContent = 'Seen';
                            }
                        }
                    }
                }
                
                // Update message reaction in UI
                function updateMessageReaction(data) {
                    const messageDiv = document.querySelector(`.message[data-message-id="${data.message_id}"]`);
                    if (messageDiv) {
                        // Clear existing reactions
                        const reactionsDiv = messageDiv.querySelector('.message-reactions');
                        if (reactionsDiv) reactionsDiv.innerHTML = '';
                        
                        // Add updated reactions
                        if (data.reactions && Object.keys(data.reactions).length > 0) {
                            let reactionsHtml = '<div class="message-reactions">';
                            for (const [type, userIds] of Object.entries(data.reactions)) {
                                const emoji = {
                                    'like': '👍', 'love': '❤️', 'laugh': '😂', 
                                    'wow': '😮', 'sad': '😢', 'angry': '😠', 'thumbs_up': '👍'
                                }[type] || type;
                                
                                reactionsHtml += `
                                    <div class="reaction" title="${type} (${userIds.length})">
                                        <span class="reaction-emoji">${emoji}</span>
                                        <span>${userIds.length}</span>
                                    </div>
                                `;
                            }
                            reactionsHtml += '</div>';
                            
                            const contentDiv = messageDiv.querySelector('.message-content');
                            if (contentDiv) {
                                // Remove existing reactions if any
                                const existingReactions = contentDiv.querySelector('.message-reactions');
                                if (existingReactions) existingReactions.remove();
                                
                                // Insert before read receipts/status
                                const timeDiv = contentDiv.querySelector('.message-time');
                                if (timeDiv) {
                                    timeDiv.insertAdjacentHTML('afterend', reactionsHtml);
                                }
                            }
                        }
                    }
                }
                
                // Helper to escape HTML
                function escapeHtml(unsafe) {
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }
                
                // Scroll to bottom
                function scrollToBottom() {
                    const container = document.getElementById('messages-container');
                    container.scrollTop = container.scrollHeight;
                }
                
                // Reaction handling
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('message-content')) {
                        document.querySelectorAll('.reaction-options').forEach(el => {
                            el.style.display = 'none';
                        });
                        
                        const messageDiv = e.target.closest('.message');
                        const optionsDiv = messageDiv.querySelector('.reaction-options');
                        optionsDiv.style.display = 'flex';
                        optionsDiv.style.position = 'absolute';
                        optionsDiv.style.bottom = '0';
                        optionsDiv.style.right = '0';
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('reaction-btn')) {
                        const reactionType = e.target.dataset.reaction;
                        const messageDiv = e.target.closest('.message');
                        const messageId = messageDiv.dataset.messageId;
                        
                        ws.send(JSON.stringify({
                            type: 'reaction',
                            message_id: messageId,
                            user_id: userId,
                            reaction_type: reactionType,
                            group_id: isGroupChat ? chatId : null,
                            recipient_id: isGroupChat ? null : chatId
                        }));
                        
                        messageDiv.querySelector('.reaction-options').style.display = 'none';
                    }
                });
                
                // Scroll to bottom on load
                window.onload = scrollToBottom;
            </script>
       <?php else: ?>
        <!-- No Chat Selected UI -->
        <div class="flex flex-col items-center justify-center flex-1 p-10 text-center text-gray-500">
            <h2 class="text-xl font-semibold">Select a chat to start messaging</h2>
            <p>Choose from your connections or groups on the left</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>