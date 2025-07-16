<?php
require 'auth_check.php';
require 'config.php';

// Fetch user profile data
$userStmt = $pdo->prepare("SELECT u.*, b.name as batch_name, c.name as course_name 
                          FROM users u
                          LEFT JOIN user_batch_mapping ubm ON u.user_id = ubm.user_id
                          LEFT JOIN batches b ON ubm.batch_id = b.batch_id
                          LEFT JOIN courses c ON b.course_id = c.course_id
                          WHERE u.user_id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

// Fetch connection counts
$connectionStmt = $pdo->prepare("SELECT 
                                SUM(CASE WHEN requester_id = ? AND status = 'accepted' THEN 1 ELSE 0 END) as following,
                                SUM(CASE WHEN recipient_id = ? AND status = 'accepted' THEN 1 ELSE 0 END) as followers
                                FROM connections");
$connectionStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$connections = $connectionStmt->fetch();

// Fetch recent notifications
$notificationStmt = $pdo->prepare("SELECT * FROM notifications 
                                  WHERE user_id = ? 
                                  ORDER BY created_at DESC 
                                  LIMIT 5");
$notificationStmt->execute([$_SESSION['user_id']]);
$notifications = $notificationStmt->fetchAll();

// Fetch posts (from connections or public)
$postStmt = $pdo->prepare("SELECT p.*, u.name as author_name, 
                          (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
                          (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
                          EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) as is_liked
                          FROM posts p
                          JOIN users u ON p.user_id = u.user_id
                          WHERE p.privacy = 'public' OR 
                                (p.privacy = 'connections' AND 
                                EXISTS (SELECT 1 FROM connections 
                                        WHERE ((requester_id = ? AND recipient_id = p.user_id) OR 
                                               (requester_id = p.user_id AND recipient_id = ?)) AND 
                                        status = 'accepted'))
                          ORDER BY p.created_at DESC");
$postStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$posts = $postStmt->fetchAll();

// Fetch media for posts
$mediaStmt = $pdo->prepare("SELECT * FROM post_media WHERE post_id = ?");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    
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


    <!-- Main Content -->
   <div class="flex h-screen overflow-hidden">
    
    <!-- Left Sidebar -->
    <aside class="w-64 bg-white shadow-md p-4 flex-shrink-0 overflow-y-auto">
      <div class="flex flex-col items-center">
        <img src="https://via.placeholder.com/80" class="rounded-full mb-2" alt="Profile Picture" />
        <a href="profile_edit.php"><h3 class="text-lg font-bold"><?php echo htmlspecialchars($user['name']); ?></a></h3>
        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['batch_name'] ?? 'No batch'); ?></p>
        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['course_name'] ?? 'No course'); ?></p>
      </div>

      <div class="flex justify-around my-4 text-center">
        <div>
          <h4 class="font-semibold"><?php echo htmlspecialchars($connections['followers'] ?? 0); ?></h4>
          <p class="text-xs text-gray-500">Followers</p>
        </div>
        <div>
          <h4 class="font-semibold"><?php echo htmlspecialchars($connections['following'] ?? 0); ?></h4>
          <p class="text-xs text-gray-500">Following</p>
        </div>
      </div>

      <div class="space-y-2">
        <button onclick="location.href='settings.php'" class="w-full flex items-center gap-2 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded">
          <i class="fas fa-cog"></i> Settings
        </button>
        <button onclick="location.href='create_post.php'" class="w-full flex items-center gap-2 px-3 py-2 bg-blue-100 hover:bg-blue-200 rounded">
          <i class="fas fa-plus"></i> Create Post
        </button>
        <button onclick="location.href='schedule_meeting.php'" class="w-full flex items-center gap-2 px-3 py-2 bg-green-100 hover:bg-green-200 rounded">
          <i class="fas fa-calendar-plus"></i> Schedule Meeting
        </button>
        <button onclick="location.href='create_group.php'" class="w-full flex items-center gap-2 px-3 py-2 bg-purple-100 hover:bg-purple-200 rounded">
          <i class="fas fa-users"></i> Create Group
        </button>
      </div>
    </aside>

    <!-- Center Content (Posts) -->
    <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
      <h3 class="text-xl font-bold mb-4">Recent Posts</h3>

      <?php if (empty($posts)): ?>
      <div class="bg-white p-4 rounded shadow">
        <p>No posts to display. Connect with others or create your first post!</p>
      </div>
      <?php else: ?>
      <?php foreach ($posts as $post): 
        $mediaStmt->execute([$post['post_id']]);
        $media = $mediaStmt->fetchAll();
      ?>
      <div class="bg-white rounded shadow mb-4 p-4 cursor-pointer hover:shadow-lg transition" onclick="location.href='post_detail.php?id=<?php echo $post['post_id']; ?>'">
        <div class="flex items-center mb-2">
          <img src="https://via.placeholder.com/40" class="w-10 h-10 rounded-full mr-3" alt="Author">
          <div>
            <h4 class="font-semibold"><?php echo htmlspecialchars($post['author_name']); ?></h4>
            <p class="text-xs text-gray-500"><?php echo date('M j, Y g:i a', strtotime($post['created_at'])); ?></p>
          </div>
        </div>
        <p class="mb-2"><?php echo htmlspecialchars($post['content']); ?></p>

        <?php if (!empty($media)): ?>
        <div class="grid grid-cols-2 gap-2 mb-2">
          <?php foreach ($media as $item): ?>
            <?php if ($item['media_type'] == 'image'): ?>
              <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="rounded" alt="Post image">
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex justify-between text-sm text-gray-600 mb-2">
          <span><?php echo $post['like_count']; ?> likes</span>
          <span><?php echo $post['comment_count']; ?> comments</span>
        </div>

        <div class="flex gap-6 text-lg text-gray-600">
          <div class="like-btn cursor-pointer" data-post-id="<?php echo $post['post_id']; ?>">
            <i class="<?php echo $post['is_liked'] ? 'fas' : 'far'; ?> fa-heart <?php echo $post['is_liked'] ? 'text-red-500' : ''; ?>"></i>
          </div>
          <div><i class="far fa-comment"></i></div>
          <div class="share-btn cursor-pointer" data-post-id="<?php echo $post['post_id']; ?>">
            <i class="fas fa-share"></i>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </main>

    <!-- Right Sidebar -->
    <aside class="w-64 bg-white shadow-md p-4 flex-shrink-0 overflow-y-auto">
      <h3 class="text-lg font-semibold mb-4">Recent Notifications</h3>
      <?php if (empty($notifications)): ?>
        <p class="text-sm text-gray-500">No recent notifications</p>
      <?php else: ?>
        <div class="space-y-3">
        <?php foreach ($notifications as $notification): ?>
          <div class="bg-gray-100 p-2 rounded hover:bg-gray-200 cursor-pointer" onclick="location.href='notification_detail.php?id=<?php echo $notification['notification_id']; ?>'">
            <p class="font-medium"><?php echo htmlspecialchars($notification['type']); ?></p>
            <p class="text-xs text-gray-500"><?php echo date('M j, g:i a', strtotime($notification['created_at'])); ?></p>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <a href="notifications.php" class="block text-blue-500 mt-4 text-right text-sm hover:underline">View All</a>
    </aside>
  </div>

  <!-- Footer -->
  <footer class="text-center text-sm text-gray-500 py-3 bg-white border-t">
    Alumni Portal - by Ekta and Nidhi &copy; <?php echo date('Y'); ?>
  </footer>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  $(document).ready(function () {
    $('.like-btn').click(function (e) {
      e.stopPropagation();
      const postId = $(this).data('post-id');
      const likeIcon = $(this).find('i');
      const isLiked = likeIcon.hasClass('fas');

      $.ajax({
        url: 'handle_like.php',
        method: 'POST',
        data: { post_id: postId },
        success: function (response) {
          if (response === 'liked') {
            likeIcon.removeClass('far').addClass('fas text-red-500');
          } else {
            likeIcon.removeClass('fas text-red-500').addClass('far');
          }
          const likeCount = likeIcon.closest('.post-card').find('.post-stats span:first');
          const currentCount = parseInt(likeCount.text());
          likeCount.text(response === 'liked' ? currentCount + 1 + ' likes' : currentCount - 1 + ' likes');
        }
      });
    });

    $('.share-btn').click(function (e) {
      e.stopPropagation();
      const postId = $(this).data('post-id');
      alert('Share functionality will open a modal to select connections to share with');
    });
  });
  </script>

</body>
</html>