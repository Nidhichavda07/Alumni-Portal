<?php
require 'auth_check.php';
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: main.php');
    exit();
}

$postId = $_GET['id'];

// Fetch post details
$postStmt = $pdo->prepare("SELECT p.*, u.name as author_name, u.profile_pic, 
                          (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
                          EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as is_liked
                          FROM posts p
                          JOIN users u ON p.user_id = u.user_id
                          WHERE p.post_id = ?");
$postStmt->execute([$_SESSION['user_id'], $postId]);
$post = $postStmt->fetch();

if (!$post) {
    header('Location: main.php');
    exit();
}

// Process content for mentions and hashtags
function processContent($content) {
    // Process user mentions
    $content = preg_replace_callback('/@\[([^\]]+)\]\(user_(\d+)\)/', function($matches) {
        return '<a href="user_profile.php?id='.$matches[2].'" class="mention">@'.$matches[1].'</a>';
    }, $content);
    
    // Process batch mentions
    $content = preg_replace_callback('/@\[([^\]]+)\]\(batch_(\d+)\)/', function($matches) {
        return '<a href="batch.php?id='.$matches[2].'" class="mention">@'.$matches[1].'</a>';
    }, $content);
    
    // Process course mentions
    $content = preg_replace_callback('/@\[([^\]]+)\]\(course_(\d+)\)/', function($matches) {
        return '<a href="course.php?id='.$matches[2].'" class="mention">@'.$matches[1].'</a>';
    }, $content);
    
    // Process hashtags
    $content = preg_replace_callback('/#(\w+)/', function($matches) {
        return '<a href="hashtag.php?tag='.$matches[1].'" class="hashtag">#'.$matches[1].'</a>';
    }, $content);
    
    return $content;
}

$post['processed_content'] = processContent($post['content']);

// Fetch media for the post
$mediaStmt = $pdo->prepare("SELECT * FROM post_media WHERE post_id = ?");
$mediaStmt->execute([$postId]);
$media = $mediaStmt->fetchAll();

// Function to fetch comments with nested structure
function fetchNestedComments($pdo, $postId, $userId, $parentId = null, $depth = 0) {
    $stmt = $pdo->prepare("SELECT c.*, u.name as author_name, u.profile_pic,
                          (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.comment_id) as like_count,
                          EXISTS(SELECT 1 FROM comment_likes WHERE comment_id = c.comment_id AND user_id = ?) as is_liked
                          FROM comments c
                          JOIN users u ON c.user_id = u.user_id
                          WHERE c.post_id = ? AND c.parent_comment_id " . ($parentId ? "= ?" : "IS NULL") . "
                          ORDER BY c.created_at " . ($depth > 0 ? "ASC" : "DESC"));
    
    $params = [$userId, $postId];
    if ($parentId) {
        $params[] = $parentId;
    }
    
    $stmt->execute($params);
    $comments = $stmt->fetchAll();
    
    foreach ($comments as &$comment) {
        $comment['processed_content'] = processContent($comment['content']);
        $comment['depth'] = $depth;
        $comment['replies'] = fetchNestedComments($pdo, $postId, $userId, $comment['comment_id'], $depth + 1);
    }
    
    return $comments;
}

$comments = fetchNestedComments($pdo, $postId, $_SESSION['user_id']);

// Fetch connections for share functionality and mentions
$connectionsStmt = $pdo->prepare("SELECT u.user_id, u.name, u.profile_pic 
                                FROM connections c
                                JOIN users u ON (c.requester_id = u.user_id OR c.recipient_id = u.user_id) AND 
                                               u.user_id != ?
                                WHERE (c.requester_id = ? OR c.recipient_id = ?) AND c.status = 'accepted'
                                ORDER BY u.name");
$connectionsStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$connections = $connectionsStmt->fetchAll();

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['comment_content'])) {
        $content = $_POST['comment_content'];
        $parentId = isset($_POST['parent_comment_id']) && !empty($_POST['parent_comment_id']) ? $_POST['parent_comment_id'] : null;
        
        // Insert comment
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, parent_comment_id, created_at) 
                              VALUES (?, ?, ?, ?, NOW())");
        
        // Explicitly pass NULL for parent_comment_id if it's a top-level comment
        $params = [$postId, $_SESSION['user_id'], $content];
        $params[] = ($parentId !== null && $parentId !== '') ? $parentId : null;
        
        if ($stmt->execute($params)) {
            // Redirect to avoid form resubmission
            header("Location: post_detail.php?id=$postId");
            exit();
        } else {
            $commentError = "Failed to post comment";
            // For debugging, you can add:
            // $commentError .= ": " . implode(", ", $stmt->errorInfo());
        }
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Details | Alumni Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f5f5f5;
        }
        
        /* Header Styles */
        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.8rem;
        }
        
        /* Main Content */
        .post-detail-container {
            max-width: 100%;
            width: 100%;
            padding: 1rem;
            background-color: white;
            margin: 0 auto;
        }
        
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
        }
        
        .post-author-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 0.8rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .post-author-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .post-author-info h3 {
            font-size: 0.95rem;
            margin-bottom: 0.1rem;
        }
        
        .post-author-info p {
            color: #666;
            font-size: 0.8rem;
        }
        
        .post-content {
            margin: 0.8rem 0;
            font-size: 0.95rem;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .mention {
            color: #3498db;
            font-weight: 500;
            text-decoration: none;
        }
        
        .hashtag {
            color: #9b59b6;
            font-weight: 500;
            text-decoration: none;
        }
        
        /* Media Slider */
        .post-media {
            margin: 0.8rem 0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .swiper {
            width: 100%;
            height: 300px;
        }
        
        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
        }
        
        .swiper-slide img, .swiper-slide video {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Post actions */
        .post-actions {
            display: flex;
            justify-content: space-between;
            margin: 0.8rem 0;
            padding: 0.5rem 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        
        .post-action {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            cursor: pointer;
            padding: 0.3rem 0.5rem;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .post-action:hover {
            background-color: #f0f0f0;
        }
        
        .post-action.like-btn {
            color: <?php echo $post['is_liked'] ? '#e74c3c' : '#666'; ?>;
        }
        
        .post-action.like-btn i {
            color: <?php echo $post['is_liked'] ? '#e74c3c' : '#666'; ?>;
        }
        
        /* Comment input */
        .comment-input-container {
            flex: 1;
            margin: 0 0.5rem;
            position: relative;
        }
        
        .comment-input {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-size: 0.85rem;
        }
        
        .comment-submit {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .comment-submit:hover {
            background-color: #2980b9;
        }
        
        /* Comments section */
        .comments-section {
            margin-top: 1rem;
        }
        
        .comments-section h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid #eee;
        }
        
        .comment {
            margin-bottom: 0.8rem;
            padding: 0.3rem;
        }
        
        .comment-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.2rem;
        }
        
        .comment-author-pic {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            margin-right: 0.6rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .comment-author-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .comment-author-info h4 {
            font-size: 0.85rem;
            margin-bottom: 0.1rem;
        }
        
        .comment-time {
            font-size: 0.7rem;
            color: #666;
        }
        
        .comment-content {
            margin-left: 2.2rem;
            font-size: 0.85rem;
            line-height: 1.3;
            white-space: pre-wrap;
        }
        
        .comment-actions {
            display: flex;
            gap: 0.8rem;
            margin-left: 2.2rem;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }
        
        .comment-action {
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        
        .comment-action:hover {
            color: #3498db;
        }
        
        .comment-action.like-btn {
            color: <?php echo isset($comment['is_liked']) && $comment['is_liked'] ? '#e74c3c' : '#666'; ?>;
        }
        
        .comment-action.like-btn i {
            color: <?php echo isset($comment['is_liked']) && $comment['is_liked'] ? '#e74c3c' : '#666'; ?>;
        }
        
        /* Nested comments */
        .comment-replies {
            margin-left: 1rem;
            padding-left: 0.5rem;
            border-left: 1px solid #eee;
        }
        
        /* Reply form */
        .reply-form {
            margin-top: 0.5rem;
            display: none;
            padding-left: 2.2rem;
        }
        
        .reply-form.active {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* Mention dropdown */
        .mention-dropdown {
            position: absolute;
            bottom: 100%;
            left: 0;
            width: 100%;
            max-height: 150px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 100;
            display: none;
        }
        
        .mention-item {
            padding: 0.5rem;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .mention-item:hover {
            background-color: #f0f0f0;
        }
        
        /* Share modal */
        .share-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .share-modal-content {
            background-color: white;
            padding: 1.2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .share-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .share-modal-close {
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .share-connection-list {
            margin-top: 0.5rem;
        }
        
        .share-connection-item {
            display: flex;
            align-items: center;
            padding: 0.6rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .share-connection-item:hover {
            background-color: #f5f5f5;
        }
        
        .share-connection-item img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 0.8rem;
            object-fit: cover;
        }
        
        /* Error message */
        .error-message {
            color: #e74c3c;
            font-size: 0.8rem;
            margin-top: 0.3rem;
            text-align: center;
        }
        
        /* Responsive Styles */
        @media (min-width: 768px) {
            .post-detail-container {
                max-width: 600px;
                padding: 1.5rem;
                margin: 1rem auto;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            .swiper {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
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


    <!-- Post Detail Content -->
    <div class="post-detail-container">
        <div class="post-header">
            <div class="post-author-pic">
                <img src="<?php echo htmlspecialchars($post['profile_pic'] ?: 'https://via.placeholder.com/50'); ?>" alt="Author">
            </div>
            <div class="post-author-info">
                <h3><?php echo htmlspecialchars($post['author_name']); ?></h3>
                <p><?php echo date('M j, Y g:i a', strtotime($post['created_at'])); ?></p>
            </div>
        </div>
        
        <div class="post-content">
            <?php echo $post['processed_content']; ?>
        </div>
        
        <?php if (!empty($media)): ?>
            <div class="post-media">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($media as $item): ?>
                            <div class="swiper-slide">
                                <?php if ($item['media_type'] == 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($item['media_url']); ?>" alt="Post image">
                                <?php elseif ($item['media_type'] == 'video'): ?>
                                    <video controls>
                                        <source src="<?php echo htmlspecialchars($item['media_url']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="post-actions">
            <div class="post-action like-btn" data-post-id="<?php echo $post['post_id']; ?>">
                <i class="<?php echo $post['is_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                <span><?php echo $post['like_count']; ?></span>
            </div>
            
            <form method="POST" class="comment-input-container" id="comment-form">
                <input type="text" class="comment-input" name="comment_content" id="comment-text" placeholder="Write a comment..." autocomplete="off">
                <div class="mention-dropdown" id="mention-dropdown"></div>
                <input type="hidden" name="parent_comment_id" id="parent-comment-id" value="">
            </form>
            
            <button type="submit" form="comment-form" class="comment-submit">
                Post
            </button>
            
            <div class="post-action share-btn" data-post-id="<?php echo $post['post_id']; ?>">
                <i class="fas fa-share"></i>
                <span>Share</span>
            </div>
        </div>
        
        <?php if (isset($commentError)): ?>
            <div class="error-message"><?php echo htmlspecialchars($commentError); ?></div>
        <?php endif; ?>
        
        <!-- Comments Section -->
        <div class="comments-section">
            <h3>Comments</h3>
            
            <?php if (empty($comments)): ?>
                <p>No comments yet. Be the first to comment!</p>
            <?php else: ?>
                <?php 
                function displayComments($comments) {
                    foreach ($comments as $comment): 
                ?>
                <div class="comment" data-comment-id="<?php echo $comment['comment_id']; ?>">
                    <div class="comment-header">
                        <div class="comment-author-pic">
                            <img src="<?php echo htmlspecialchars($comment['profile_pic'] ?: 'https://via.placeholder.com/40'); ?>" alt="Author">
                        </div>
                        <div class="comment-author-info">
                            <h4><?php echo htmlspecialchars($comment['author_name']); ?></h4>
                            <p class="comment-time"><?php echo date('M j, g:i a', strtotime($comment['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="comment-content">
                        <?php echo $comment['processed_content']; ?>
                    </div>
                    
                    <div class="comment-actions">
                        <div class="comment-action like-btn" data-comment-id="<?php echo $comment['comment_id']; ?>">
                            <i class="<?php echo $comment['is_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span class="like-count"><?php echo $comment['like_count']; ?></span>
                        </div>
                        <div class="comment-action reply-btn" data-comment-id="<?php echo $comment['comment_id']; ?>">
                            <i class="fas fa-reply"></i>
                            <span>Reply</span>
                        </div>
                    </div>
                    
                    <!-- Reply Form -->
                    <form method="POST" class="reply-form" id="reply-form-<?php echo $comment['comment_id']; ?>">
                        <div class="comment-input-container">
                            <input type="text" class="comment-input reply-input" name="comment_content" placeholder="Write a reply..." autocomplete="off">
                            <div class="mention-dropdown reply-mention-dropdown"></div>
                            <input type="hidden" name="parent_comment_id" value="<?php echo $comment['comment_id']; ?>">
                        </div>
                        <button type="submit" class="comment-submit send-reply">Reply</button>
                    </form>
                    
                    <!-- Replies -->
                    <?php if (!empty($comment['replies'])): ?>
                    <div class="comment-replies">
                        <?php displayComments($comment['replies']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php 
                    endforeach;
                }
                displayComments($comments);
                ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Share Modal -->
    <div class="share-modal" id="share-modal">
        <div class="share-modal-content">
            <div class="share-modal-header">
                <h3>Share Post</h3>
                <span class="share-modal-close">&times;</span>
            </div>
            <p>Select a connection to share with:</p>
            <div class="share-connection-list">
                <?php foreach ($connections as $connection): ?>
                <div class="share-connection-item" data-user-id="<?php echo $connection['user_id']; ?>">
                    <img src="<?php echo htmlspecialchars($connection['profile_pic'] ?: 'https://via.placeholder.com/40'); ?>" alt="<?php echo htmlspecialchars($connection['name']); ?>">
                    <span><?php echo htmlspecialchars($connection['name']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize Swiper
        const swiper = new Swiper('.swiper', {
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
        
        // Handle post like
        $('.post-action.like-btn').click(function() {
            const postId = $(this).data('post-id');
            const likeBtn = $(this);
            const icon = likeBtn.find('i');
            const likeCount = likeBtn.find('span');
            const isLiked = icon.hasClass('fas');
            
            $.ajax({
                url: 'handle_like.php',
                method: 'POST',
                data: { 
                    post_id: postId,
                    type: 'post'
                },
                success: function(response) {
                    if (response === 'liked') {
                        icon.removeClass('far').addClass('fas');
                        likeBtn.css('color', '#e74c3c');
                        icon.css('color', '#e74c3c');
                        likeCount.text(parseInt(likeCount.text()) + 1);
                    } else if (response === 'unliked') {
                        icon.removeClass('fas').addClass('far');
                        likeBtn.css('color', '#666');
                        icon.css('color', '#666');
                        likeCount.text(Math.max(0, parseInt(likeCount.text()) - 1));
                    } else {
                        console.error('Error processing like:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error processing your like. Please try again.');
                }
            });
        });
        
        // Handle comment like
        $(document).on('click', '.comment-action.like-btn', function() {
            const commentId = $(this).data('comment-id');
            const likeBtn = $(this);
            const icon = likeBtn.find('i');
            const likeCount = likeBtn.find('.like-count');
            const currentCount = parseInt(likeCount.text());
            
            $.ajax({
                url: 'handle_like.php',
                method: 'POST',
                data: { 
                    comment_id: commentId,
                    type: 'comment'
                },
                success: function(response) {
                    if (response === 'liked') {
                        icon.removeClass('far').addClass('fas');
                        likeBtn.css('color', '#e74c3c');
                        icon.css('color', '#e74c3c');
                        likeCount.text(currentCount + 1);
                    } else if (response === 'unliked') {
                        icon.removeClass('fas').addClass('far');
                        likeBtn.css('color', '#666');
                        icon.css('color', '#666');
                        likeCount.text(Math.max(0, currentCount - 1));
                    } else {
                        console.error('Error processing like:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error processing your like. Please try again.');
                }
            });
        });
        
        // Handle reply button click
        $(document).on('click', '.comment-action.reply-btn', function() {
            const commentId = $(this).data('comment-id');
            $('.reply-form').removeClass('active');
            $('#reply-form-' + commentId).addClass('active');
            $('#reply-form-' + commentId + ' .reply-input').focus();
        });
        
        // Handle comment form submission
        $('#comment-form').on('submit', function(e) {
            const content = $('#comment-text').val().trim();
            if (!content) {
                e.preventDefault();
                alert('Please enter a comment');
            }
        });
        
        // Handle reply form submission
        $(document).on('submit', '.reply-form', function(e) {
            const content = $(this).find('.reply-input').val().trim();
            if (!content) {
                e.preventDefault();
                alert('Please enter a reply');
            }
        });
        
        // Share modal functionality
        $('.post-action.share-btn').click(function() {
            $('#share-modal').css('display', 'flex');
        });
        
        $('.share-modal-close').click(function() {
            $('#share-modal').hide();
        });
        
        $(window).click(function(e) {
            if (e.target == $('#share-modal')[0]) {
                $('#share-modal').hide();
            }
        });
        
        // Handle share with connection
        $('.share-connection-item').click(function() {
            const userId = $(this).data('user-id');
            const postId = <?php echo $postId; ?>;
            const shareBtn = $(this);
            
            shareBtn.css('opacity', '0.6').css('pointer-events', 'none');
            
            $.ajax({
                url: 'share_post.php',
                method: 'POST',
                data: { 
                    post_id: postId,
                    recipient_id: userId
                },
                success: function(response) {
                    if (response === 'success') {
                        $('#share-modal').hide();
                        alert('Post shared successfully!');
                    } else {
                        alert('Error sharing post: ' + response);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error sharing post. Please try again.');
                    console.error('AJAX Error:', status, error);
                },
                complete: function() {
                    shareBtn.css('opacity', '1').css('pointer-events', 'auto');
                }
            });
        });
        
        // Mention functionality
        const connections = <?php echo json_encode($connections); ?>;
        let mentionStartPos = -1;
        
        // Handle mentions in main comment input
        $('#comment-text').on('input', function() {
            handleMentions($(this), $('#mention-dropdown'));
        });
        
        // Handle mentions in reply inputs
        $(document).on('input', '.reply-input', function() {
            handleMentions($(this), $(this).siblings('.mention-dropdown'));
        });
        
        function handleMentions(input, dropdown) {
            const val = input.val();
            const cursorPos = input[0].selectionStart;
            const textBeforeCursor = val.substring(0, cursorPos);
            
            mentionStartPos = textBeforeCursor.lastIndexOf('@');
            
            if (mentionStartPos >= 0 && (mentionStartPos === 0 || textBeforeCursor[mentionStartPos - 1].match(/\s/))) {
                const searchTerm = textBeforeCursor.substring(mentionStartPos + 1).toLowerCase();
                const filteredUsers = connections.filter(user => 
                    user.name.toLowerCase().includes(searchTerm)
                );
                
                if (filteredUsers.length > 0) {
                    dropdown.empty();
                    
                    filteredUsers.forEach(user => {
                        dropdown.append(`
                            <div class="mention-item" data-user-id="${user.user_id}" data-user-name="${user.name}">
                                <img src="${user.profile_pic || 'https://via.placeholder.com/40'}" width="20" height="20" style="border-radius:50%;margin-right:5px;">
                                ${user.name}
                            </div>
                        `);
                    });
                    
                    dropdown.show();
                } else {
                    dropdown.hide();
                }
            } else {
                dropdown.hide();
            }
        }
        
        // Handle mention selection in main comment
        $(document).on('click', '#mention-dropdown .mention-item', function() {
            selectMention($(this), $('#comment-text'), $('#mention-dropdown'));
        });
        
        // Handle mention selection in replies
        $(document).on('click', '.reply-mention-dropdown .mention-item', function() {
            selectMention($(this), $(this).parent().siblings('.reply-input'), $(this).parent());
        });
        
        function selectMention(item, input, dropdown) {
            const userId = item.data('user-id');
            const userName = item.data('user-name');
            const val = input.val();
            const cursorPos = input[0].selectionStart;
            
            // Replace the @mention with the formatted mention
            const beforeText = val.substring(0, mentionStartPos);
            const afterText = val.substring(cursorPos);
            const newVal = beforeText + `@[${userName}](user_${userId})` + afterText;
            
            input.val(newVal);
            dropdown.hide();
            
            // Set cursor position after the inserted mention
            const newCursorPos = mentionStartPos + `@[${userName}](user_${userId})`.length;
            setTimeout(() => {
                input[0].setSelectionRange(newCursorPos, newCursorPos);
                input.focus();
            }, 0);
        }
        
        // Hide dropdown when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('.mention-dropdown').length && !$(e.target).is('.comment-input, .reply-input')) {
                $('.mention-dropdown').hide();
            }
        });
        
        // Handle hashtags - they will be processed server-side when submitted
        // Highlight hashtags while typing
        $('.comment-input, .reply-input').on('input', function() {
            const val = $(this).val();
            // Simple hashtag detection for UI (actual processing happens server-side)
            const hashtagRegex = /#(\w+)/g;
            if (hashtagRegex.test(val)) {
                // Just visual feedback that hashtags are recognized
                $(this).css('color', '#333');
            }
        });
    });
    </script>
</body>
</html>