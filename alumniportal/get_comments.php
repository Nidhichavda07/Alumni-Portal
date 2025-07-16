<?php
require 'config.php';
require 'auth_check.php';

if (isset($_POST['post_id'])) {
    $postId = $_POST['post_id'];
    
    $stmt = $pdo->prepare("SELECT c.*, u.name as author_name 
                          FROM comments c
                          JOIN users u ON c.user_id = u.user_id
                          WHERE c.post_id = ? AND c.parent_comment_id IS NULL
                          ORDER BY c.created_at DESC");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();
    
    foreach ($comments as $comment) {
        echo '<div class="comment">';
        echo '<div class="comment-author">' . htmlspecialchars($comment['author_name']) . '</div>';
        echo '<div class="comment-content">' . htmlspecialchars($comment['content']) . '</div>';
        echo '<div class="comment-time">' . date('M j, g:i a', strtotime($comment['created_at'])) . '</div>';
        
        // Add nested comments if any
        $stmt = $pdo->prepare("SELECT c.*, u.name as author_name 
                              FROM comments c
                              JOIN users u ON c.user_id = u.user_id
                              WHERE c.parent_comment_id = ?
                              ORDER BY c.created_at DESC");
        $stmt->execute([$comment['comment_id']]);
        $replies = $stmt->fetchAll();
        
        if (!empty($replies)) {
            echo '<div class="comment-replies">';
            foreach ($replies as $reply) {
                echo '<div class="comment reply">';
                echo '<div class="comment-author">' . htmlspecialchars($reply['author_name']) . '</div>';
                echo '<div class="comment-content">' . htmlspecialchars($reply['content']) . '</div>';
                echo '<div class="comment-time">' . date('M j, g:i a', strtotime($reply['created_at'])) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
?>