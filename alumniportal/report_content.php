<?php
require 'auth_check.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_POST['content_type'];
    $contentId = $_POST['content_id'];
    $reason = $_POST['reason'];
    
    if (empty($contentType) || empty($contentId) || empty($reason)) {
        echo "missing_data";
        exit();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO reported_content 
        (content_type, content_id, reported_by, reason, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    
    if ($stmt->execute([$contentType, $contentId, $_SESSION['user_id'], $reason])) {
        echo "success";
    } else {
        echo "error";
    }
} else {
    echo "invalid_request";
}
?>