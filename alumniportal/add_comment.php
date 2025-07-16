<?php
require 'config.php';
require 'auth_check.php';

if (isset($_POST['post_id']) && isset($_POST['content'])) {
    $postId = $_POST['post_id'];
    $content = $_POST['content'];
    $parentCommentId = isset($_POST['parent_comment_id']) ? $_POST['parent_comment_id'] : null;
    
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, parent_comment_id, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$postId, $_SESSION['user_id'], $content, $parentCommentId]);
    
    echo "success";
}
?>