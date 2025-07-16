<?php
require 'auth_check.php';
require 'config.php';

if (!isset($_GET['id'])) {
    header('Location: main.php');
    exit();
}

$profileUserId = $_GET['id'];
$currentUserId = $_SESSION['user_id'];

// Fetch profile user data
$userStmt = $pdo->prepare("SELECT u.*, 
                          GROUP_CONCAT(DISTINCT b.name SEPARATOR ', ') as batch_names,
                          GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as course_names
                          FROM users u
                          LEFT JOIN user_batch_mapping ubm ON u.user_id = ubm.user_id
                          LEFT JOIN batches b ON ubm.batch_id = b.batch_id
                          LEFT JOIN courses c ON b.course_id = c.course_id
                          WHERE u.user_id = ?");
$userStmt->execute([$profileUserId]);
$profileUser = $userStmt->fetch();

if (!$profileUser) {
    header('Location: main.php');
    exit();
}

// Check connection status
$connectionStmt = $pdo->prepare("SELECT * FROM connections 
                                WHERE (requester_id = ? AND recipient_id = ?) OR 
                                      (requester_id = ? AND recipient_id = ?)");
$connectionStmt->execute([$currentUserId, $profileUserId, $profileUserId, $currentUserId]);
$connection = $connectionStmt->fetch();

// Handle connection request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'connect' && !$connection) {
        $stmt = $pdo->prepare("INSERT INTO connections (requester_id, recipient_id, status, created_at) 
                              VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$currentUserId, $profileUserId]);
        header("Location: user_profile.php?id=$profileUserId");
        exit();
    } elseif ($_POST['action'] == 'cancel' && $connection && $connection['status'] == 'pending') {
        $stmt = $pdo->prepare("DELETE FROM connections WHERE connection_id = ?");
        $stmt->execute([$connection['connection_id']]);
        header("Location: user_profile.php?id=$profileUserId");
        exit();
    } elseif ($_POST['action'] == 'accept' && $connection && $connection['status'] == 'pending' && $connection['recipient_id'] == $currentUserId) {
        $stmt = $pdo->prepare("UPDATE connections SET status = 'accepted', updated_at = NOW() WHERE connection_id = ?");
        $stmt->execute([$connection['connection_id']]);
        header("Location: user_profile.php?id=$profileUserId");
        exit();
    } elseif ($_POST['action'] == 'decline' && $connection && $connection['status'] == 'pending' && $connection['recipient_id'] == $currentUserId) {
        $stmt = $pdo->prepare("DELETE FROM connections WHERE connection_id = ?");
        $stmt->execute([$connection['connection_id']]);
        header("Location: user_profile.php?id=$profileUserId");
        exit();
    } elseif ($_POST['action'] == 'disconnect' && $connection && $connection['status'] == 'accepted') {
        $stmt = $pdo->prepare("DELETE FROM connections WHERE connection_id = ?");
        $stmt->execute([$connection['connection_id']]);
        header("Location: user_profile.php?id=$profileUserId");
        exit();
    }
}

// Fetch user's education details
$educationStmt = $pdo->prepare("SELECT * FROM education_details WHERE user_id = ? ORDER BY education_level");
$educationStmt->execute([$profileUserId]);
$education = $educationStmt->fetchAll();

// Fetch user's work experience
$workStmt = $pdo->prepare("SELECT * FROM work_experience WHERE user_id = ? ORDER BY start_date DESC");
$workStmt->execute([$profileUserId]);
$workExperience = $workStmt->fetchAll();

// Fetch user's social links
$socialStmt = $pdo->prepare("SELECT * FROM social_links WHERE user_id = ?");
$socialStmt->execute([$profileUserId]);
$socialLinks = $socialStmt->fetchAll();

// Fetch mutual connections
$mutualStmt = $pdo->prepare("SELECT u.user_id, u.name 
                            FROM connections c1
                            JOIN connections c2 ON 
                                (c1.requester_id = c2.requester_id OR c1.requester_id = c2.recipient_id OR 
                                 c1.recipient_id = c2.requester_id OR c1.recipient_id = c2.recipient_id) AND
                                c1.connection_id != c2.connection_id
                            JOIN users u ON (u.user_id = c2.requester_id OR u.user_id = c2.recipient_id) AND
                                           u.user_id != ? AND u.user_id != ?
                            WHERE (c1.requester_id = ? OR c1.recipient_id = ?) AND 
                                  c1.status = 'accepted' AND c2.status = 'accepted'
                            GROUP BY u.user_id
                            LIMIT 5");
$mutualStmt->execute([$currentUserId, $profileUserId, $currentUserId, $profileUserId]);
$mutualConnections = $mutualStmt->fetchAll();

// Fetch user posts
$postsStmt = $pdo->prepare("SELECT p.*, 
                           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
                           FROM posts p
                           WHERE p.user_id = ?
                           ORDER BY p.created_at DESC
                           LIMIT 3");
$postsStmt->execute([$profileUserId]);
$posts = $postsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo htmlspecialchars($profileUser['name']); ?> | Alumni Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-gray-800">

  <!-- Header -->
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
    <a href="profile.php" class="flex flex-col items-center hover:text-red-400">
      <i class="fas fa-sign-out-alt text-white text-xl"></i>
      <span class="text-sm">Logout</span>
    </a>
  </nav>
</header>

  <!-- Profile Header -->
  <div class="relative">
    <img class="w-full h-52 object-cover" src="https://via.placeholder.com/1000x200/3498db/ffffff" alt="Cover photo">
    <div class="absolute -bottom-16 left-8">
      <img class="w-32 h-32 rounded-full border-4 border-white" src="https://via.placeholder.com/160" alt="Profile Picture">
    </div>
  </div>

  <!-- Profile Info -->
  <div class="max-w-7xl mx-auto pt-20 px-4 md:px-8">
    <div class="bg-white rounded-lg shadow-md p-6 md:flex md:justify-between">
      <div>
        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($profileUser['name']); ?></h1>
        <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($profileUser['bio'] ?? ''); ?></p>
        <div class="mt-4 space-y-1 text-gray-600">
          <div><i class="fas fa-graduation-cap mr-2 text-blue-600"></i><?php echo htmlspecialchars($profileUser['batch_names'] ?? 'No batch'); ?></div>
          <div><i class="fas fa-book mr-2 text-blue-600"></i><?php echo htmlspecialchars($profileUser['course_names'] ?? 'No course'); ?></div>
          <div><i class="fas fa-map-marker-alt mr-2 text-blue-600"></i><?php echo htmlspecialchars($profileUser['city'] ?? '') . ($profileUser['city'] && $profileUser['state'] ? ', ' : '') . htmlspecialchars($profileUser['state'] ?? ''); ?></div>
        </div>
      </div>
      <div class="mt-4 md:mt-0">
        <?php if ($profileUserId == $currentUserId): ?>
          <a href="edit_profile.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-edit mr-2"></i>Edit Profile</a>
        <?php elseif ($connection): ?>
          <?php if ($connection['status'] == 'accepted'): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="disconnect">
              <button class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded mr-2 inline-flex items-center"><i class="fas fa-user-times mr-2"></i>Disconnect</button>
            </form>
            <a href="messages.php?user=<?php echo $profileUserId; ?>" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-envelope mr-2"></i>Message</a>
          <?php elseif ($connection['requester_id'] == $currentUserId): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="cancel">
              <button class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-times mr-2"></i>Cancel Request</button>
            </form>
          <?php else: ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="accept">
              <button class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-check mr-2"></i>Accept</button>
            </form>
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="decline">
              <button class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-times mr-2"></i>Decline</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="connect">
            <button class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded inline-flex items-center"><i class="fas fa-user-plus mr-2"></i>Connect</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Profile Sections -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
      <!-- Left Column -->
      <div class="md:col-span-2 space-y-6">
        <!-- Education -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Education</h2>
          <?php if (empty($education)): ?>
            <p class="text-gray-600">No education information available</p>
          <?php else: ?>
            <?php foreach ($education as $edu): ?>
              <div class="mb-4">
                <h3 class="font-semibold"><?php echo htmlspecialchars($edu['degree_name'] ?? ''); ?></h3>
                <p><?php echo htmlspecialchars($edu['university'] ?? ''); ?></p>
                <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($edu['year_of_passing'] ?? ''); ?><?php if ($edu['percentage']) echo " • " . htmlspecialchars($edu['percentage']) . "%"; ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Work Experience -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Work Experience</h2>
          <?php if (empty($workExperience)): ?>
            <p class="text-gray-600">No work experience information available</p>
          <?php else: ?>
            <?php foreach ($workExperience as $work): ?>
              <div class="mb-4">
                <h3 class="font-semibold"><?php echo htmlspecialchars($work['position']); ?></h3>
                <p><?php echo htmlspecialchars($work['company']); ?></p>
                <p class="text-gray-500 text-sm"><?php echo date('M Y', strtotime($work['start_date'])) . ' - ' . ($work['currently_working'] ? 'Present' : date('M Y', strtotime($work['end_date']))); ?></p>
                <?php if ($work['description']): ?><p class="text-sm text-gray-700 mt-1"><?php echo htmlspecialchars($work['description']); ?></p><?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Social Links -->
        <?php if (!empty($socialLinks)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Social Links</h2>
          <div class="flex space-x-4">
            <?php foreach ($socialLinks as $link):
              $platform = strtolower($link['platform']);
              $iconClass = match (true) {
                str_contains($platform, 'linkedin') => 'fab fa-linkedin',
                str_contains($platform, 'twitter') => 'fab fa-twitter',
                str_contains($platform, 'facebook') => 'fab fa-facebook',
                str_contains($platform, 'instagram') => 'fab fa-instagram',
                str_contains($platform, 'github') => 'fab fa-github',
                default => 'fas fa-globe',
              };
            ?>
              <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-xl"><i class="<?php echo $iconClass; ?>"></i></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Mutual Connections -->
        <?php if (!empty($mutualConnections) && $profileUserId != $currentUserId): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Mutual Connections</h2>
          <div class="grid grid-cols-3 gap-4">
            <?php foreach ($mutualConnections as $mutual): ?>
              <a href="user_profile.php?id=<?php echo $mutual['user_id']; ?>" class="text-center">
                <img src="https://via.placeholder.com/50" class="mx-auto rounded-full mb-2" alt="<?php echo htmlspecialchars($mutual['name']); ?>">
                <p class="text-sm"><?php echo htmlspecialchars($mutual['name']); ?></p>
              </a>
            <?php endforeach; ?>
          </div>
          <a href="mutual_connections.php?user=<?php echo $profileUserId; ?>" class="text-blue-600 hover:underline mt-3 inline-block">View All</a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Right Column (Recent Posts) -->
      <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold mb-4">Recent Posts</h2>
          <?php if (empty($posts)): ?>
            <p class="text-gray-600">No posts to display</p>
          <?php else: ?>
            <?php foreach ($posts as $post): ?>
              <div class="border-b pb-4 mb-4">
                <div class="flex items-center space-x-3">
                  <img src="https://via.placeholder.com/40" class="rounded-full" alt="<?php echo htmlspecialchars($profileUser['name']); ?>">
                  <div>
                    <p class="font-semibold"><?php echo htmlspecialchars($profileUser['name']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo date('M j, Y g:i a', strtotime($post['created_at'])); ?></p>
                  </div>
                </div>
                <p class="mt-3 text-gray-700"><?php echo htmlspecialchars($post['content']); ?></p>
                <div class="text-sm text-gray-500 mt-2"><?php echo $post['like_count']; ?> likes • <?php echo $post['comment_count']; ?> comments</div>
              </div>
            <?php endforeach; ?>
            <a href="user_posts.php?id=<?php echo $profileUserId; ?>" class="text-blue-600 hover:underline">View All Posts</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="mt-10 py-6 text-center text-gray-600">
    Alumni Portal - by Ekta and Nidhi &copy; <?php echo date('Y'); ?>
  </footer>
</body>
</html>
