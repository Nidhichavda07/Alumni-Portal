<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user's connections
$connectionsStmt = $pdo->prepare("
    SELECT u.user_id, u.name, u.profile_pic 
    FROM connections c
    JOIN users u ON (c.requester_id = u.user_id OR c.recipient_id = u.user_id) AND u.user_id != ?
    WHERE (c.requester_id = ? OR c.recipient_id = ?) AND c.status = 'accepted'
");
$connectionsStmt->execute([$userId, $userId, $userId]);
$connections = $connectionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['group_name'];
    $description = $_POST['description'];
    $joinPolicy = $_POST['join_policy'];
    $invitees = isset($_POST['invitees']) ? $_POST['invitees'] : [];

    try {
        $pdo->beginTransaction();

        $groupStmt = $pdo->prepare("
            INSERT INTO groups (name, description, type, creator_id, join_policy)
            VALUES (?, ?, 'custom', ?, ?)
        ");
        $groupStmt->execute([$name, $description, $userId, $joinPolicy]);
        $groupId = $pdo->lastInsertId();

        $memberStmt = $pdo->prepare("
            INSERT INTO group_members (group_id, user_id, is_admin)
            VALUES (?, ?, 1)
        ");
        $memberStmt->execute([$groupId, $userId]);

        if (!empty($invitees)) {
            $inviteStmt = $pdo->prepare("
                INSERT INTO group_invitations (group_id, inviter_id, invitee_id, status)
                VALUES (?, ?, ?, 'pending')
            ");
            foreach ($invitees as $inviteeId) {
                $inviteStmt->execute([$groupId, $userId, $inviteeId]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Group created successfully!";
        header('Location: chat.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating group: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Group - Alumni Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="bg-gray-50 min-h-screen font-sans">

<!-- Header -->
<header class="bg-blue-800 text-white p-4 flex justify-between items-center shadow-md">
    <div class="text-2xl font-bold">Alumni Portal</div>
    <nav class="flex space-x-6">
        <a href="main.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-home text-xl"></i>
            <span class="text-sm">Home</span>
        </a>
        <a href="notifications.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-bell text-xl"></i>
            <span class="text-sm">Notifications</span>
        </a>
        <a href="connections.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-hands-helping text-xl"></i>
            <span class="text-sm">Connections</span>
        </a>
        <a href="chat.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-envelope text-xl"></i>
            <span class="text-sm">Messages</span>
        </a>
        <a href="questions.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-question-circle text-xl"></i>
            <span class="text-sm">Q&A</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center hover:text-yellow-300">
            <i class="fas fa-user-circle text-xl"></i>
            <span class="text-sm">Profile</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center hover:text-red-400">
            <i class="fas fa-sign-out-alt text-xl"></i>
            <span class="text-sm">Logout</span>
        </a>
    </nav>
</header>

<!-- Main Content -->
<div class="max-w-4xl mx-auto bg-white mt-8 p-6 rounded shadow">
    <h1 class="text-2xl font-semibold mb-4">Create New Group</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label for="group_name" class="block font-medium mb-1">Group Name</label>
            <input type="text" name="group_name" id="group_name" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div>
            <label for="description" class="block font-medium mb-1">Description</label>
            <textarea name="description" id="description" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4"></textarea>
        </div>

        <div>
            <label for="join_policy" class="block font-medium mb-1">Join Policy</label>
            <select name="join_policy" id="join_policy" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="open">Anyone can join</option>
                <option value="approval">Requires approval</option>
                <option value="invite">Invite only</option>
            </select>
        </div>

        <div>
            <label class="block font-medium mb-2">Invite Connections</label>
            <div class="space-y-3 max-h-60 overflow-y-auto border border-gray-200 p-3 rounded">
                <?php foreach ($connections as $connection): ?>
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="invitees[]" value="<?= $connection['user_id'] ?>" id="invitee_<?= $connection['user_id'] ?>" class="text-blue-500 focus:ring-blue-500">
                        <img src="<?= htmlspecialchars($connection['profile_pic']) ?>" alt="avatar" class="w-10 h-10 rounded-full object-cover">
                        <label for="invitee_<?= $connection['user_id'] ?>" class="font-medium"><?= htmlspecialchars($connection['name']) ?></label>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($connections)): ?>
                    <p class="text-gray-500">No connections available to invite.</p>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded shadow flex items-center space-x-2">
            <i class="fas fa-users"></i>
            <span>Create Group</span>
        </button>
    </form>
</div>

</body>
</html>
