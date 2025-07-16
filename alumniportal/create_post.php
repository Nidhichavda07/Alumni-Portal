<?php
require 'auth_check.php';
require 'config.php';

// Fetch user connections for mention suggestions
$connectionsStmt = $pdo->prepare("SELECT u.user_id, u.name, u.eno, u.profile_pic 
                                FROM connections c
                                JOIN users u ON (c.requester_id = u.user_id OR c.recipient_id = u.user_id) 
                                WHERE u.user_id != ? 
                                AND (c.requester_id = ? OR c.recipient_id = ?) 
                                AND c.status = 'accepted'");
$connectionsStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$connections = $connectionsStmt->fetchAll();

// Fetch all batches and courses for mention suggestions
$batchesStmt = $pdo->prepare("SELECT b.batch_id, b.name, c.course_id, c.name as course_name 
                             FROM batches b
                             JOIN courses c ON b.course_id = c.course_id");
$batchesStmt->execute();
$allBatches = $batchesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $content = $_POST['content'] ?? '';
        $privacy = $_POST['privacy'] ?? 'public';
        
        // Validate content
        if (empty(trim($content))) {
            throw new Exception("Post content cannot be empty");
        }
        
        // Insert post
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, privacy, created_at, updated_at) 
                              VALUES (?, ?, ?, NOW(), NOW())");
        if (!$stmt->execute([$_SESSION['user_id'], $content, $privacy])) {
            throw new Exception("Failed to create post");
        }
        $postId = $pdo->lastInsertId();
        
        // Process mentions
        preg_match_all('/@\[([^\]]+)\]\(([^\)]+)\)/', $content, $matches);
        foreach ($matches[2] as $i => $mentionId) {
            $mentionName = $matches[1][$i];
            if (str_starts_with($mentionId, 'user_')) {
                $userId = substr($mentionId, 5);
                $stmt = $pdo->prepare("INSERT INTO post_mentions (post_id, mentioned_user_id) VALUES (?, ?)");
                $stmt->execute([$postId, $userId]);
            } elseif (str_starts_with($mentionId, 'batch_')) {
                $batchId = substr($mentionId, 6);
                $stmt = $pdo->prepare("INSERT INTO post_mentions (post_id, mentioned_batch_id) VALUES (?, ?)");
                $stmt->execute([$postId, $batchId]);
            } elseif (str_starts_with($mentionId, 'course_')) {
                $courseId = substr($mentionId, 7);
                $stmt = $pdo->prepare("INSERT INTO post_mentions (post_id, mentioned_course_id) VALUES (?, ?)");
                $stmt->execute([$postId, $courseId]);
            }
        }
        
        // // Process hashtags
        // preg_match_all('/#(\w+)/', $content, $hashtags);
        // foreach (array_unique($hashtags[1]) as $tag) {
        //     // Check if hashtag exists
        //     $tagStmt = $pdo->prepare("SELECT tag_id FROM hashtags WHERE tag_name = ?");
        //     $tagStmt->execute([$tag]);
        //     $tagId = $tagStmt->fetchColumn();
            
        //     if (!$tagId) {
        //         // Create new hashtag
        //         $tagStmt = $pdo->prepare("INSERT INTO hashtags (tag_name) VALUES (?)");
        //         $tagStmt->execute([$tag]);
        //         $tagId = $pdo->lastInsertId();
        //     }
            
        //     // Link hashtag to post
        //     $linkStmt = $pdo->prepare("INSERT INTO post_hashtags (post_id, tag_id) VALUES (?, ?)");
        //     $linkStmt->execute([$postId, $tagId]);
        // }

        // Process hashtags
preg_match_all('/#(\w+)/', $content, $hashtags);
foreach (array_unique($hashtags[1]) as $tag) {
    // Check if hashtag exists
    $tagStmt = $pdo->prepare("SELECT tag_id FROM hashtags WHERE tag_name = ?");
    $tagStmt->execute([strtolower($tag)]);
    $tagId = $tagStmt->fetchColumn();
    
    if (!$tagId) {
        // Create new hashtag
        $tagStmt = $pdo->prepare("INSERT INTO hashtags (tag_name) VALUES (?)");
        $tagStmt->execute([strtolower($tag)]);
        $tagId = $pdo->lastInsertId();
    }
    
    // Link hashtag to post
    $linkStmt = $pdo->prepare("INSERT INTO post_hashtags (post_id, tag_id) VALUES (?, ?)");
    $linkStmt->execute([$postId, $tagId]);
}
        
        // Handle file uploads
        if (!empty($_FILES['media']['name'][0])) {
            $uploadDir = 'uploads/posts/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 
                'video/mp4', 'video/quicktime',
                'application/pdf', 
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            
            foreach ($_FILES['media']['tmp_name'] as $key => $tmpName) {
                // Validate file type and size
                $fileType = mime_content_type($tmpName);
                $fileSize = $_FILES['media']['size'][$key];
                
                if (!in_array($fileType, $allowedTypes) || $fileSize > 10 * 1024 * 1024) {
                    continue;
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['media']['name'][$key]);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $type = str_starts_with($fileType, 'image/') ? 'image' : 
                           (str_starts_with($fileType, 'video/') ? 'video' : 'document');
                    
                    $stmt = $pdo->prepare("INSERT INTO post_media (post_id, media_url, media_type) VALUES (?, ?, ?)");
                    $stmt->execute([$postId, $targetPath, $type]);
                }
            }
        }
        
        header("Location: main.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post | Alumni Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
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
        
        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .nav-links i {
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
        }
        
        .create-post-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .create-post-container h2 {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .post-form {
            display: flex;
            flex-direction: column;
        }
        
        .post-editor {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        #post-content {
            width: 100%;
            min-height: 150px;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .highlighted-mention {
            color: #3498db;
            font-weight: 500;
            cursor: pointer;
        }
        
        .highlighted-hashtag {
            color: #9b59b6;
            font-weight: 500;
            cursor: pointer;
        }
        
        .mention-dropdown {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            width: 300px;
        }
        
        .mention-type-header {
            padding: 8px 12px;
            background-color: #f5f5f5;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        
        .mention-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mention-item:hover {
            background-color: #f5f5f5;
        }
        
        .mention-item img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .mention-item .user-info,
        .mention-item .batch-info,
        .mention-item .course-info {
            flex: 1;
        }
        
        .mention-item .user-name,
        .mention-item .batch-name {
            font-weight: 500;
        }
        
        .mention-item .user-eno,
        .mention-item .course-name {
            font-size: 0.8rem;
            color: #666;
        }
        
        .media-upload {
            margin-bottom: 1.5rem;
        }
        
        .media-upload label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.2rem;
            background-color: #f5f5f5;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .media-upload label:hover {
            background-color: #eaeaea;
        }
        
        .file-count {
            font-size: 0.8rem;
            color: #666;
            margin-left: 0.5rem;
        }
        
        .file-upload-status {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .media-preview {
            margin-top: 1rem;
            position: relative;
        }
        
        .swiper {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
        }
        
        .swiper-slide img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .swiper-slide video {
            max-width: 100%;
            max-height: 100%;
        }
        
        .swiper-slide .document-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .swiper-slide .document-preview i {
            font-size: 3rem;
            color: #666;
        }
        
        .swiper-button-next, 
        .swiper-button-prev {
            color: white;
            background: rgba(0,0,0,0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .swiper-button-next::after, 
        .swiper-button-prev::after {
            font-size: 1.2rem;
        }
        
        .remove-media {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
        }
        
        .post-options {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .privacy-options {
            display: flex;
            gap: 1rem;
        }
        
        .privacy-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .submit-btn {
            padding: 0.8rem 1.5rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            align-self: flex-end;
        }
        
        .submit-btn:hover {
            background-color: #2980b9;
        }
        
        .error-message {
            color: #e74c3c;
            background-color: #fdecea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 16px;
            border-left: 4px solid #e74c3c;
        }
        
        .highlight-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: inherit;
            font-size: inherit;
            font-family: inherit;
            line-height: inherit;
            white-space: pre-wrap;
            overflow: hidden;
            pointer-events: none;
            z-index: 2;
            background: transparent;
            color: transparent;
        }
        
        footer {
            background-color: #2c3e50;
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
            margin-top: auto;
        }
        
        @media (max-width: 768px) {
            .create-post-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            .swiper {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Alumni Portal</div>
        <div class="nav-links">
            <a href="notifications.php">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="connections.php">
                <i class="fas fa-hands-helping"></i>
                <span>Connections</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            <a href="questions.php">
                <i class="fas fa-question-circle"></i>
                <span>Q&A</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="create-post-container">
        <h2>Create New Post</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form class="post-form" method="POST" enctype="multipart/form-data">
            <div class="post-editor">
                <textarea id="post-content" name="content" placeholder="What's on your mind? Mention people with @ or batches/courses with @batch or @course. Add hashtags with #"></textarea>
                <div class="mention-dropdown" id="mention-dropdown"></div>
            </div>
            
            <div class="post-options">
                <div class="privacy-options">
                    <div class="privacy-option">
                        <input type="radio" id="privacy-public" name="privacy" value="public" checked>
                        <label for="privacy-public"><i class="fas fa-globe"></i> Public</label>
                    </div>
                    <div class="privacy-option">
                        <input type="radio" id="privacy-connections" name="privacy" value="connections">
                        <label for="privacy-connections"><i class="fas fa-user-friends"></i> Connections Only</label>
                    </div>
                </div>
            </div>
            
            <div class="media-upload">
                <label for="media-files">
                    <i class="fas fa-camera"></i> 
                    <span>Add Photos/Videos/Documents</span>
                    <span class="file-count" id="file-count"></span>
                </label>
                <input type="file" id="media-files" name="media[]" multiple style="display: none;" 
                       accept="image/*,video/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                <div class="file-upload-status" id="upload-status"></div>
                <div class="media-preview" id="media-preview"></div>
            </div>
            
            <button type="submit" class="submit-btn">Post</button>
        </form>
    </div>
    
    <footer>
        <p>Alumni Portal - by Ekta and Nidhi &copy; <?php echo date('Y'); ?></p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        const textarea = $('#post-content');
        const mentionDropdown = $('#mention-dropdown');
        let mentionStartPos = -1;
        let currentMentionType = '';
        let swiper = null;
        
        // Mention data
        const userMentions = [
            <?php foreach ($connections as $connection): ?>
            {
                id: 'user_<?php echo $connection['user_id']; ?>',
                name: '<?php echo addslashes($connection['name']); ?>',
                eno: '<?php echo addslashes($connection['eno']); ?>',
                avatar: '<?php echo $connection['profile_pic'] ? htmlspecialchars($connection['profile_pic']) : 'https://via.placeholder.com/30'; ?>'
            },
            <?php endforeach; ?>
        ];
        
        const batchMentions = [
            <?php foreach ($allBatches as $batch): ?>
            {
                id: 'batch_<?php echo $batch['batch_id']; ?>',
                name: '<?php echo addslashes($batch['name']); ?>',
                course: '<?php echo addslashes($batch['course_name']); ?>',
                course_id: 'course_<?php echo $batch['course_id']; ?>'
            },
            <?php endforeach; ?>
        ];

        // Initialize Swiper
        function initSwiper() {
            if (swiper) {
                swiper.destroy();
            }
            swiper = new Swiper('.swiper', {
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        }

        // Handle @ key for mentions
        textarea.on('keyup', function(e) {
            const val = textarea.val();
            const caretPos = textarea[0].selectionStart;
            
            // Find the last @ before caret with no whitespace
            let lastAtPos = val.lastIndexOf('@', caretPos - 1);
            if (lastAtPos >= 0) {
                const textAfterAt = val.substring(lastAtPos, caretPos);
                
                if (!/\s/.test(textAfterAt)) {
                    mentionStartPos = lastAtPos;
                    
                    // Check for special mentions
                    if (val.substring(caretPos - 6, caretPos) === '@batch') {
                        currentMentionType = 'batch';
                        showMentionDropdown('batch');
                    } else if (val.substring(caretPos - 7, caretPos) === '@course') {
                        currentMentionType = 'course';
                        showMentionDropdown('course');
                    } else {
                        currentMentionType = 'user';
                        showMentionDropdown('user');
                    }
                    return;
                }
            }
            
            // Hide dropdown if needed
            if (mentionStartPos !== -1 && (e.key === ' ' || caretPos < mentionStartPos)) {
                mentionDropdown.hide();
                mentionStartPos = -1;
                currentMentionType = '';
            }
            
            // Filter mentions
            if (mentionStartPos !== -1 && currentMentionType) {
                const prefixLength = currentMentionType === 'batch' ? 6 : 
                                    (currentMentionType === 'course' ? 7 : 1);
                const mentionText = val.substring(mentionStartPos + prefixLength, caretPos).toLowerCase();
                filterMentions(mentionText);
            }
        });

        // Show mention dropdown
        function showMentionDropdown(type) {
            mentionDropdown.empty();
            currentMentionType = type;
            
            let mentions = [];
            if (type === 'user') {
                mentions = userMentions;
                mentionDropdown.append('<div class="mention-type-header">People</div>');
            } else if (type === 'batch') {
                mentions = batchMentions;
                mentionDropdown.append('<div class="mention-type-header">Batches</div>');
            } else if (type === 'course') {
                // Group by course
                const courses = {};
                batchMentions.forEach(batch => {
                    if (!courses[batch.course_id]) {
                        courses[batch.course_id] = {
                            id: batch.course_id,
                            name: batch.course
                        };
                    }
                });
                mentions = Object.values(courses);
                mentionDropdown.append('<div class="mention-type-header">Courses</div>');
            }
            
            if (mentions.length === 0) {
                mentionDropdown.append('<div class="mention-item">No ' + type + 's found</div>');
            } else {
                mentions.forEach(item => {
                    const itemElement = $('<div class="mention-item" data-id="' + item.id + '"></div>');
                    
                    if (type === 'user') {
                        itemElement.append(
                            '<img src="' + item.avatar + '" alt="' + item.name + '">' +
                            '<div class="user-info">' +
                            '   <div class="user-name">' + item.name + '</div>' +
                            '   <div class="user-eno">' + item.eno + '</div>' +
                            '</div>'
                        );
                    } else if (type === 'batch') {
                        itemElement.append(
                            '<i class="fas fa-users"></i>' +
                            '<div class="batch-info">' +
                            '   <div class="batch-name">' + item.name + '</div>' +
                            '   <div class="course-name">' + item.course + '</div>' +
                            '</div>'
                        );
                    } else if (type === 'course') {
                        itemElement.append(
                            '<i class="fas fa-book"></i>' +
                            '<div class="course-info">' +
                            '   <div class="course-name">' + item.name + '</div>' +
                            '</div>'
                        );
                    }
                    
                    mentionDropdown.append(itemElement);
                });
            }
            
            positionDropdownAtCursor();
            mentionDropdown.show();
        }

        // Position dropdown at cursor
        function positionDropdownAtCursor() {
            const textareaRect = textarea[0].getBoundingClientRect();
            const caretPos = textarea[0].selectionStart;
            const cursorTop = getCaretCoordinates(textarea[0], caretPos).top;
            
            mentionDropdown.css({
                top: textareaRect.top + cursorTop + 20 + window.scrollY,
                left: textareaRect.left + getCaretCoordinates(textarea[0], caretPos).left
            });
        }

        // Filter mentions based on input
        function filterMentions(text) {
            let mentions = [];
            if (currentMentionType === 'user') {
                mentions = userMentions.filter(item => 
                    item.name.toLowerCase().includes(text) || 
                    item.eno.toLowerCase().includes(text)
                );
            } else if (currentMentionType === 'batch') {
                mentions = batchMentions.filter(item => 
                    item.name.toLowerCase().includes(text) ||
                    item.course.toLowerCase().includes(text)
                );
            } else if (currentMentionType === 'course') {
                const courses = {};
                batchMentions.forEach(batch => {
                    if (!courses[batch.course_id] && batch.course.toLowerCase().includes(text)) {
                        courses[batch.course_id] = {
                            id: batch.course_id,
                            name: batch.course
                        };
                    }
                });
                mentions = Object.values(courses);
            }
            
            mentionDropdown.empty();
            
            if (currentMentionType === 'user') {
                mentionDropdown.append('<div class="mention-type-header">People</div>');
            } else if (currentMentionType === 'batch') {
                mentionDropdown.append('<div class="mention-type-header">Batches</div>');
            } else if (currentMentionType === 'course') {
                mentionDropdown.append('<div class="mention-type-header">Courses</div>');
            }
            
            if (mentions.length === 0) {
                mentionDropdown.append('<div class="mention-item">No matches found</div>');
            } else {
                mentions.forEach(item => {
                    const itemElement = $('<div class="mention-item" data-id="' + item.id + '"></div>');
                    
                    if (currentMentionType === 'user') {
                        itemElement.append(
                            '<img src="' + item.avatar + '" alt="' + item.name + '">' +
                            '<div class="user-info">' +
                            '   <div class="user-name">' + highlightMatch(item.name, text) + '</div>' +
                            '   <div class="user-eno">' + highlightMatch(item.eno, text) + '</div>' +
                            '</div>'
                        );
                    } else if (currentMentionType === 'batch') {
                        itemElement.append(
                            '<i class="fas fa-users"></i>' +
                            '<div class="batch-info">' +
                            '   <div class="batch-name">' + highlightMatch(item.name, text) + '</div>' +
                            '   <div class="course-name">' + highlightMatch(item.course, text) + '</div>' +
                            '</div>'
                        );
                    } else if (currentMentionType === 'course') {
                        itemElement.append(
                            '<i class="fas fa-book"></i>' +
                            '<div class="course-info">' +
                            '   <div class="course-name">' + highlightMatch(item.name, text) + '</div>' +
                            '</div>'
                        );
                    }
                    
                    mentionDropdown.append(itemElement);
                });
            }
            
            positionDropdownAtCursor();
        }

        // Highlight matching text
        function highlightMatch(str, match) {
            if (!str || !match) return str;
            const regex = new RegExp(match, 'gi');
            return str.replace(regex, m => `<span style="background-color: #ffff00">${m}</span>`);
        }

        // Handle mention selection
        mentionDropdown.on('click', '.mention-item', function() {
            const mentionId = $(this).data('id');
            let mentionName = '';
            let prefix = '';
            
            if (currentMentionType === 'user') {
                mentionName = userMentions.find(u => u.id === mentionId).name;
            } else if (currentMentionType === 'batch') {
                mentionName = batchMentions.find(b => b.id === mentionId).name;
                prefix = '@batch';
            } else if (currentMentionType === 'course') {
                mentionName = batchMentions.find(b => b.course_id === mentionId).course;
                prefix = '@course';
            }
            
            const currentValue = textarea.val();
            const newValue = currentValue.substring(0, mentionStartPos) + 
                           `@[${mentionName}](${mentionId})` + 
                           currentValue.substring(textarea[0].selectionStart);
            
            textarea.val(newValue).focus();
            mentionDropdown.hide();
            
            // Position cursor after the mention
            const newCaretPos = mentionStartPos + mentionName.length + mentionId.length + 4;
            textarea[0].setSelectionRange(newCaretPos, newCaretPos);
            
            updateHighlights();
        });

        // Update highlights
        function updateHighlights() {
            const content = textarea.val();
            
            // Replace mentions with highlighted spans
            const withMentions = content.replace(/@\[([^\]]+)\]\(([^\)]+)\)/g, 
                '<span class="highlighted-mention" data-id="$2">@$1</span>');
            
            // Replace hashtags with highlighted spans
            const withHighlights = withMentions.replace(/#(\w+)/g, 
                '<span class="highlighted-hashtag">#$1</span>');
            
            // Create a temporary div to hold our HTML
            const temp = $('<div>').html(withHighlights);
            
            // Handle clicks on mentions
            temp.find('.highlighted-mention').on('click', function(e) {
                e.preventDefault();
                const mentionId = $(this).data('id');
                if (mentionId.startsWith('user_')) {
                    const userId = mentionId.split('_')[1];
                    window.open('user_profile.php?id=' + userId, '_blank');
                } else if (mentionId.startsWith('batch_')) {
                    const batchId = mentionId.split('_')[1];
                    window.open('batch.php?id=' + batchId, '_blank');
                } else if (mentionId.startsWith('course_')) {
                    const courseId = mentionId.split('_')[1];
                    window.open('course.php?id=' + courseId, '_blank');
                }
            });
            
            // Handle clicks on hashtags
            temp.find('.highlighted-hashtag').on('click', function(e) {
                e.preventDefault();
                const tag = $(this).text().substring(1);
                window.open('hashtag.php?tag=' + tag, '_blank');
            });
            
            // Create a mirror element to get the text content with HTML
            const mirror = $('<div>').css({
                position: 'absolute',
                left: '-9999px',
                top: '0',
                width: textarea.width() + 'px',
                height: textarea.height() + 'px',
                padding: textarea.css('padding'),
                'font-size': textarea.css('font-size'),
                'font-family': textarea.css('font-family'),
                'line-height': textarea.css('line-height'),
                'white-space': 'pre-wrap',
                'overflow': 'hidden'
            }).html(temp.html());
            
            $('body').append(mirror);
            
            // Get the plain text version (without HTML tags)
            const plainText = mirror.text();
            mirror.remove();
            
            
            // Only update if the content hasn't changed (to avoid cursor jumps)
            if (plainText === content) {
                // Save cursor position
                const start = textarea[0].selectionStart;
                const end = textarea[0].selectionEnd;
                
                // Update the textarea with highlighted HTML
                textarea.next('.highlight-container').remove();
                const highlightContainer = $('<div class="highlight-container">').html(temp.html());
                
                textarea.after(highlightContainer);
                
                // Restore cursor position
                textarea[0].setSelectionRange(start, end);
            }
        }

        // Handle media upload preview
        $('#media-files').on('change', function() {
            const files = this.files;
            const preview = $('#media-preview');
            preview.empty();
            $('#upload-status').empty();
            
            $('#file-count').text(files.length > 0 ? `(${files.length} file${files.length > 1 ? 's' : ''})` : '');
            
            if (files.length === 0) return;
            
            const swiperContainer = $('<div class="swiper">');
            const swiperWrapper = $('<div class="swiper-wrapper">');
            let loadedCount = 0;
            
            Array.from(files).forEach((file, i) => {
                if (!file.type.match('image.*|video.*') && !file.name.match(/\.(pdf|docx?|pptx?|xlsx?)$/i)) {
                    $('#upload-status').append(`<div>Unsupported file: ${file.name}</div>`);
                    return;
                }
                
                if (file.size > 10 * 1024 * 1024) {
                    $('#upload-status').append(`<div>File too large (max 10MB): ${file.name}</div>`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const slide = $('<div class="swiper-slide">');
                    
                    if (file.type.match('image.*')) {
                        slide.append($('<img>').attr('src', e.target.result));
                    } else if (file.type.match('video.*')) {
                        slide.append($('<video controls>').attr('src', e.target.result));
                    } else {
                        slide.append(
                            $('<div class="document-preview">')
                                .append($('<i class="fas fa-file-alt">'))
                                .append($('<div>').text(file.name))
                        );
                    }
                    
                    slide.append($('<button class="remove-media" data-index="' + i + '">').html('&times;'));
                    swiperWrapper.append(slide);
                    
                    if (++loadedCount === files.length) {
                        swiperContainer.append(swiperWrapper, 
                            '<div class="swiper-button-next">',
                            '<div class="swiper-button-prev">');
                        preview.append(swiperContainer);
                        initSwiper();
                    }
                };
                reader.readAsDataURL(file);
            });
        });

        // Handle media removal
        $('#media-preview').on('click', '.remove-media', function(e) {
            e.stopPropagation();
            const index = $(this).data('index');
            const files = $('#media-files')[0].files;
            const newFiles = Array.from(files).filter((_, i) => i !== index);
            
            // Create new DataTransfer to update files
            const dataTransfer = new DataTransfer();
            newFiles.forEach(file => dataTransfer.items.add(file));
            $('#media-files')[0].files = dataTransfer.files;
            
            // Trigger change event to update preview
            $('#media-files').trigger('change');
        });

        // Update highlights when content changes
        textarea.on('input', updateHighlights);
        
        // Initialize highlights
        updateHighlights();

        // Get caret position coordinates
        function getCaretCoordinates(element, position) {
            const div = document.createElement('div');
            div.style.position = 'absolute';
            div.style.visibility = 'hidden';
            div.style.whiteSpace = 'pre-wrap';
            div.style.font = window.getComputedStyle(element).font;
            div.style.padding = window.getComputedStyle(element).padding;
            div.style.border = window.getComputedStyle(element).border;
            div.style.width = element.clientWidth + 'px';
            div.textContent = element.value.substring(0, position);
            
            document.body.appendChild(div);
            const span = document.createElement('span');
            span.textContent = element.value.substring(position) || '.';
            div.appendChild(span);
            
            const rect = span.getBoundingClientRect();
            document.body.removeChild(div);
            
            return {
                top: rect.top - element.getBoundingClientRect().top + element.scrollTop,
                left: rect.left - element.getBoundingClientRect().left + element.scrollLeft
            };
        }
    });
    </script>
</body>
</html>