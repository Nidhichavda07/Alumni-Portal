<?php
require 'config.php';

// Function to check for banned words
function containsBannedWords($content) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT word FROM banned_words");
    $stmt->execute();
    $bannedWords = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($bannedWords as $word) {
        if (stripos($content, $word) !== false) {
            return true;
        }
    }
    return false;
}

// Function to process content for mentions and hashtags
function processContent($content) {
    // Process user mentions
    $content = preg_replace_callback('/@\[([^\]]+)\]\(user_(\d+)\)/', function($matches) {
        return '<a href="user_profile.php?id='.$matches[2].'" class="mention">@'.$matches[1].'</a>';
    }, $content);
    
    // Process hashtags
    $content = preg_replace_callback('/#(\w+)/', function($matches) {
        return '<a href="hashtag.php?tag='.$matches[1].'" class="hashtag">#'.$matches[1].'</a>';
    }, $content);
    
    return $content;
}

// Function to report content
function reportContent($contentType, $contentId, $reporterId, $reason) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO reported_content 
                          (content_type, content_id, reported_by, reason, status, created_at) 
                          VALUES (?, ?, ?, ?, 'pending', NOW())");
    return $stmt->execute([$contentType, $contentId, $reporterId, $reason]);
}
?>