<?php
require 'auth_check.php';
require 'config.php';
require 'forum_utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        $error = "Please fill in all fields";
    } elseif (containsBannedWords($title) || containsBannedWords($content)) {
        $error = "Your question contains inappropriate language and cannot be posted.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO questions (user_id, title, content, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        if ($stmt->execute([$_SESSION['user_id'], $title, $content])) {
            header("Location: questions.php");
            exit();
        } else {
            $error = "Failed to post question";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ask a Question | Alumni Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            padding: 1rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        h1 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .error-message {
            color: #e74c3c;
            margin-top: 1rem;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Ask a Question</h1>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="content">Details</label>
                <textarea id="content" name="content" class="form-control" required></textarea>
                <small>You can mention people using @ and add hashtags with #</small>
            </div>
            
            <button type="submit" class="btn">Post Question</button>
        </form>
    </div>
</body>
</html>