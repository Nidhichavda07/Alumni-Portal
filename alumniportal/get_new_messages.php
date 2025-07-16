<?php
require 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['last_id']) || !isset($_GET['is_group']) || !isset($_GET['chat_id'])) {
    echo json_encode([]);
    exit;
}

$lastId = (int)$_GET['last_id'];
$isGroup = $_GET['is_group'] === 'true';
$chatId = (int)$_GET['chat_id'];

if ($isGroup) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.profile_pic as sender_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.group_id = ? AND m.message_id > ?
        ORDER BY m.created_at
    ");
    $stmt->execute([$chatId, $lastId]);
} else {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.profile_pic as sender_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
        AND m.message_id > ?
        ORDER BY m.created_at
    ");
    $stmt->execute([$userId, $chatId, $chatId, $userId, $lastId]);
}

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($messages);
?>