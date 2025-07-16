<?php
require 'auth_check.php';
require 'config.php';

// Fetch user's connections
$connectionsStmt = $pdo->prepare("SELECT u.user_id, u.name, u.profile_pic 
                                 FROM connections c
                                 JOIN users u ON (c.requester_id = u.user_id OR c.recipient_id = u.user_id) AND u.user_id != ?
                                 WHERE (c.requester_id = ? OR c.recipient_id = ?) AND c.status = 'accepted'");
$connectionsStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$connections = $connectionsStmt->fetchAll();

// Fetch user's groups
$groupsStmt = $pdo->prepare("SELECT g.group_id, g.name 
                             FROM group_members gm
                             JOIN groups g ON gm.group_id = g.group_id
                             WHERE gm.user_id = ?");
$groupsStmt->execute([$_SESSION['user_id']]);
$groups = $groupsStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $meetingLink = $_POST['meeting_link'];
    $inviteType = $_POST['invite_type'];
    $invitees = $_POST['invitees'] ?? [];
    $groupId = $_POST['group_id'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Insert meeting
        $meetingStmt = $pdo->prepare("INSERT INTO meetings 
                                     (title, description, organizer_id, start_time, end_time, meeting_link, status, group_id)
                                     VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $meetingStmt->execute([$title, $description, $_SESSION['user_id'], $startTime, $endTime, $meetingLink, $groupId]);
        $meetingId = $pdo->lastInsertId();
        
        // Insert invitations based on type
        if ($inviteType === 'individual') {
            foreach ($invitees as $inviteeId) {
                $inviteStmt = $pdo->prepare("INSERT INTO meeting_invitations 
                                             (meeting_id, inviter_id, invitee_id, status)
                                             VALUES (?, ?, ?, 'pending')");
                $inviteStmt->execute([$meetingId, $_SESSION['user_id'], $inviteeId]);
                
                // Create notification
                $notifStmt = $pdo->prepare("INSERT INTO notifications 
                                            (user_id, type, reference_id)
                                            VALUES (?, 'meeting_invite', ?)");
                $notifStmt->execute([$inviteeId, $pdo->lastInsertId()]);
            }
        } elseif ($inviteType === 'group' && $groupId) {
            // Get all group members except organizer
            $membersStmt = $pdo->prepare("SELECT user_id FROM group_members 
                                         WHERE group_id = ? AND user_id != ?");
            $membersStmt->execute([$groupId, $_SESSION['user_id']]);
            $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($members as $memberId) {
                $inviteStmt = $pdo->prepare("INSERT INTO meeting_invitations 
                                             (meeting_id, inviter_id, invitee_id, status)
                                             VALUES (?, ?, ?, 'pending')");
                $inviteStmt->execute([$meetingId, $_SESSION['user_id'], $memberId]);
                
                // Create notification
                $notifStmt = $pdo->prepare("INSERT INTO notifications 
                                            (user_id, type, reference_id)
                                            VALUES (?, 'meeting_invite', ?)");
                $notifStmt->execute([$memberId, $pdo->lastInsertId()]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Meeting scheduled successfully! Invitations have been sent.";
        header("Location: meetings.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error scheduling meeting: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Schedule Meeting - Alumni Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans">

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

  <!-- Main Container -->
  <div class="max-w-4xl mx-auto mt-10 bg-white shadow-md rounded-lg p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Schedule a New Meeting</h1>

    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="text-red-600 mb-4"><?= $_SESSION['error_message'] ?></div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form action="schedule_meeting.php" method="POST" class="space-y-6">
      <div>
        <label for="title" class="block text-gray-700 font-medium mb-1">Meeting Title</label>
        <input type="text" id="title" name="title" required class="w-full p-2 border border-gray-300 rounded">
      </div>

      <div>
        <label for="description" class="block text-gray-700 font-medium mb-1">Description</label>
        <textarea id="description" name="description" rows="4" class="w-full p-2 border border-gray-300 rounded"></textarea>
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-1">Meeting Time</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="datetime-local" id="start_time" name="start_time" required class="p-2 border border-gray-300 rounded">
          <input type="datetime-local" id="end_time" name="end_time" required class="p-2 border border-gray-300 rounded">
        </div>
      </div>

      <div>
        <label for="meeting_link" class="block text-gray-700 font-medium mb-1">Meeting Link</label>
        <input type="url" id="meeting_link" name="meeting_link" required class="w-full p-2 border border-gray-300 rounded">
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-2">Invite Type</label>
        <div class="flex gap-6 mb-4">
          <label class="flex items-center gap-2">
            <input type="radio" name="invite_type" value="individual" id="invite_individual" class="form-radio" checked>
            <i class="fas fa-user-friends"></i> Invite Individuals
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="invite_type" value="group" id="invite_group" class="form-radio">
            <i class="fas fa-users"></i> Invite a Group
          </label>
        </div>

        <!-- Individual Section -->
        <div id="individual-invites" class="space-y-2">
          <p class="text-gray-600 font-medium">Select Connections</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <?php if (empty($connections)): ?>
              <p class="text-gray-500">No connections found.</p>
            <?php else: ?>
              <?php foreach ($connections as $connection): ?>
                <label class="flex items-center gap-2 p-2 border rounded shadow-sm">
                  <input type="checkbox" name="invitees[]" value="<?= $connection['user_id'] ?>" class="form-checkbox">
                  <img src="<?= htmlspecialchars($connection['profile_pic'] ?? 'https://via.placeholder.com/40') ?>" alt="Profile" class="w-10 h-10 rounded-full">
                  <span><?= htmlspecialchars($connection['name']) ?></span>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Group Section -->
        <div id="group-invites" class="hidden">
          <label for="group_id" class="block text-gray-600 font-medium mt-4">Select Group</label>
          <select id="group_id" name="group_id" class="w-full p-2 border border-gray-300 rounded">
            <?php if (empty($groups)): ?>
              <option value="">No groups available</option>
            <?php else: ?>
              <option value="">-- Select a group --</option>
              <?php foreach ($groups as $group): ?>
                <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white py-2 px-4 rounded flex items-center gap-2">
        <i class="fas fa-calendar-plus"></i> Schedule Meeting
      </button>
    </form>
  </div>

  <!-- Footer -->
  <footer class="text-center mt-10 p-4 text-gray-500">
    Alumni Portal - by Ekta and Nidhi &copy; <?= date('Y') ?>
  </footer>

  <!-- Script to handle toggle -->
  <script>
    const individualRadio = document.getElementById('invite_individual');
    const groupRadio = document.getElementById('invite_group');
    const individualSection = document.getElementById('individual-invites');
    const groupSection = document.getElementById('group-invites');

    individualRadio.addEventListener('change', () => {
      individualSection.classList.remove('hidden');
      groupSection.classList.add('hidden');
    });

    groupRadio.addEventListener('change', () => {
      individualSection.classList.add('hidden');
      groupSection.classList.remove('hidden');
    });

    // Set default datetime values
    const now = new Date();
    const startTime = new Date(now.getTime() + 30 * 60000);
    const endTime = new Date(startTime.getTime() + 60 * 60000);

    document.getElementById('start_time').value = formatDateTime(startTime);
    document.getElementById('end_time').value = formatDateTime(endTime);

    function formatDateTime(date) {
      return date.toISOString().slice(0, 16);
    }
  </script>
</body>
</html>
