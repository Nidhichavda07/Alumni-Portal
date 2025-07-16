<!-- notifications.php -->
<?php
require 'auth_check.php';
require 'config.php';

// Get filter parameters from URL
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

// Prepare base query
$query = "SELECT n.*, 
          u.name as sender_name, u.profile_pic as sender_pic,
          CASE 
            WHEN n.type = 'connection_request' THEN 'sent you a connection request'
            WHEN n.type = 'connection_accepted' THEN 'accepted your connection request'
            WHEN n.type = 'group_invite' THEN 'invited you to join a group'
            WHEN n.type = 'meeting_invite' THEN 'invited you to a meeting'
            WHEN n.type = 'post_mention' THEN 'mentioned you in a post'
            WHEN n.type = 'comment_on_post' THEN 'commented on your post'
            WHEN n.type = 'answer_on_question' THEN 'answered your question'
            WHEN n.type = 'content_report' THEN 'Your content was reported'
            WHEN n.type = 'batch_change_status' THEN 'Your batch change request was updated'
            ELSE ''
          END as message_template
          FROM notifications n
          LEFT JOIN users u ON 
            (n.type = 'connection_request' OR n.type = 'connection_accepted' OR 
             n.type = 'group_invite' OR n.type = 'meeting_invite' OR 
             n.type = 'post_mention' OR n.type = 'comment_on_post' OR 
             n.type = 'answer_on_question') AND u.user_id = 
            CASE 
              WHEN n.type = 'connection_request' THEN (SELECT requester_id FROM connections WHERE connection_id = n.reference_id)
              WHEN n.type = 'connection_accepted' THEN (SELECT recipient_id FROM connections WHERE connection_id = n.reference_id)
              WHEN n.type = 'group_invite' THEN (SELECT inviter_id FROM group_invitations WHERE invitation_id = n.reference_id)
              WHEN n.type = 'meeting_invite' THEN (SELECT inviter_id FROM meeting_invitations WHERE invitation_id = n.reference_id)
              WHEN n.type = 'post_mention' THEN (SELECT user_id FROM posts WHERE post_id = n.reference_id)
              WHEN n.type = 'comment_on_post' THEN (SELECT user_id FROM comments WHERE comment_id = n.reference_id)
              WHEN n.type = 'answer_on_question' THEN (SELECT user_id FROM answers WHERE answer_id = n.reference_id)
              ELSE NULL
            END
          WHERE n.user_id = ?";

// Add filters
$params = [$_SESSION['user_id']];

if ($filter !== 'all') {
    $query .= " AND n.type = ?";
    $params[] = $filter;
}

if ($timeframe !== 'all') {
    switch ($timeframe) {
        case 'today':
            $query .= " AND DATE(n.created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND n.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND n.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$query .= " ORDER BY n.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Mark notifications as read
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
    ->execute([$_SESSION['user_id']]);

// Get unread count for each filter type
$filterCounts = [];
$types = ['all', 'connection_request', 'connection_accepted', 'group_invite', 'meeting_invite', 
          'post_mention', 'comment_on_post', 'answer_on_question', 'content_report', 'batch_change_status'];

foreach ($types as $type) {
    $query = "SELECT COUNT(*) FROM notifications WHERE user_id = ?";
    $params = [$_SESSION['user_id']];
    
    if ($type !== 'all') {
        $query .= " AND type = ?";
        $params[] = $type;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filterCounts[$type] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Notifications - Alumni Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 text-gray-800">

  <!-- Header -->
  <header class="bg-blue-800 text-white p-4 flex justify-between items-center shadow-md">
    <div class="text-2xl font-bold">Alumni Portal</div>
    <nav class="flex space-x-6">
         <a href="main.php" class="flex flex-col items-center hover:text-yellow-300">
      <i class="fas fa-home text-white text-xl"></i>
      <span class="text-sm">home</span>
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

  <!-- Page Title -->
  <div class="text-center mt-6">
    <h1 class="text-3xl font-semibold">Notifications</h1>
  </div>

  <!-- Notification Container -->
  <div class="max-w-4xl mx-auto mt-6 px-4">
    
    <!-- Filters -->
    <div class="flex flex-wrap justify-between items-center bg-white p-4 rounded-lg shadow mb-4">
      <div class="flex space-x-4 flex-wrap">
        <a href="?filter=all" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">All</a>
        <a href="?filter=connection_request" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Connections</a>
        <a href="?filter=group_invite" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Groups</a>
        <a href="?filter=meeting_invite" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Meetings</a>
        <a href="?filter=post_mention" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Mentions</a>
        <a href="?filter=comment_on_post" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Comments</a>
        <a href="?filter=answer_on_question" class="px-3 py-1 rounded bg-blue-100 hover:bg-blue-200 text-sm font-medium">Answers</a>
      </div>
      <div class="flex space-x-2 mt-2 md:mt-0">
        <a href="?timeframe=all" class="text-sm text-gray-600 hover:underline">All Time</a>
        <a href="?timeframe=today" class="text-sm text-gray-600 hover:underline">Today</a>
        <a href="?timeframe=week" class="text-sm text-gray-600 hover:underline">Last 7 Days</a>
        <a href="?timeframe=month" class="text-sm text-gray-600 hover:underline">Last 30 Days</a>
      </div>
    </div>

    <!-- Notification List -->
    <div class="space-y-4">
      <?php if (empty($notifications)): ?>
        <div class="bg-white p-4 rounded shadow text-center text-gray-600">
          No notifications found matching your criteria.
        </div>
      <?php else: ?>
        <?php foreach ($notifications as $n): ?>
          <div class="bg-white p-4 rounded shadow flex items-start space-x-4 <?= !$n['is_read'] ? 'border-l-4 border-blue-500' : '' ?>">
            <img src="<?= htmlspecialchars($n['sender_pic'] ?? 'https://via.placeholder.com/50') ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover">
            <div>
              <div class="text-sm">
                <strong><?= htmlspecialchars($n['sender_name'] ?? 'System') ?></strong> <?= htmlspecialchars($n['message_template']) ?>
              </div>
              <div class="text-xs text-gray-500 mt-1">
                <?= date('M j, Y g:i a', strtotime($n['created_at'])) ?> • <?= str_replace('_', ' ', $n['type']) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <footer class="text-center text-sm text-gray-500 mt-10 py-4 border-t">
    Alumni Portal - by Ekta and Nidhi &copy; <?= date('Y') ?>
  </footer>

</body>
</html>
