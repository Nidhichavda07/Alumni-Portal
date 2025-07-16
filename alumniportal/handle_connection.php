<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'auth_functions.php';
$db = new PDO('mysql:host=localhost;dbname=alumni_portal', 'root', '');

$action = $_POST['action'] ?? '';
$recipient_id = $_POST['recipient_id'] ?? 0;

try {
    switch ($action) {
        case 'send_request':
            // Check if connection already exists
            $check_stmt = $db->prepare("
                SELECT status FROM connections 
                WHERE (requester_id = ? AND recipient_id = ?)
                   OR (requester_id = ? AND recipient_id = ?)
            ");
            $check_stmt->execute([$_SESSION['user_id'], $recipient_id, $recipient_id, $_SESSION['user_id']]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Connection already exists']);
                exit;
            }
            
            // Create new connection request
            $insert_stmt = $db->prepare("
                INSERT INTO connections (requester_id, recipient_id, status)
                VALUES (?, ?, 'pending')
            ");
            $insert_stmt->execute([$_SESSION['user_id'], $recipient_id]);
            
            // Create notification
            $notif_sql = "INSERT INTO notifications (user_id, type, reference_id) 
                          VALUES (?, 'connection_request', ?)";
            $db->prepare($notif_sql)->execute([$recipient_id, $db->lastInsertId()]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}