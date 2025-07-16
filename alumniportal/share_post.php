<?php
require 'config.php';
require 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['post_id']) && isset($_POST['recipient_id'])) {
        $postId = $_POST['post_id'];
        $recipientId = $_POST['recipient_id'];
        
        // Verify connection exists
        $stmt = $pdo->prepare("SELECT * FROM connections 
                              WHERE ((requester_id = ? AND recipient_id = ?) 
                              OR (requester_id = ? AND recipient_id = ?)) 
                              AND status = 'accepted'");
        $stmt->execute([$_SESSION['user_id'], $recipientId, $recipientId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Create notification for the recipient
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, reference_id) 
                                  VALUES (?, 'shared_post', ?)");
            $stmt->execute([$recipientId, $postId]);
            
            echo 'success';
        } else {
            echo 'not_connected';
        }
    } else {
        echo 'missing_data';
    }
}
?>