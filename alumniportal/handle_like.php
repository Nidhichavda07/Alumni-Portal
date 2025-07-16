<?php
require 'config.php';
require 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['post_id']) {
        // Handle post like
        $postId = $_POST['post_id'];
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT * FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Unlike
            $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $_SESSION['user_id']]);
            echo 'unliked';
        } else {
            // Like
            $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$postId, $_SESSION['user_id']]);
            echo 'liked';
        }
    } elseif (isset($_POST['comment_id'])) {
        // Handle comment like
        $commentId = $_POST['comment_id'];
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT * FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Unlike
            $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            echo 'unliked';
        } else {
            // Like
            $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commentId, $_SESSION['user_id']]);
            echo 'liked';
        }
    }
}
?>