<?php
require 'config.php';
require 'auth_check.php';

if (isset($_POST['comment_id'])) {
    $commentId = $_POST['comment_id'];
    $userId = $_SESSION['user_id'];
    
    // Check if already liked
    $stmt = $pdo->prepare("SELECT * FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$commentId, $userId]);
    $existingLike = $stmt->fetch();
    
    if ($existingLike) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $userId]);
        echo "unliked";
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$commentId, $userId]);
        echo "liked";
    }
}
?>