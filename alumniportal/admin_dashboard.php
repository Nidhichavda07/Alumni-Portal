<?php
require 'auth_check.php';

if ($_SESSION['role'] != 'admin') {
    header('Location: main.php');
    exit();
}
?>

<!-- <!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>Welcome, Admin <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    
    <h2>Admin Controls</h2>
    <ul>
        <li><a href="user_management.php">User Management</a></li>
        <li><a href="content_moderation.php">Content Moderation</a></li>
        <li><a href="batch_management.php">Batch/Course Management</a></li>
        <li><a href="system_settings.php">System Settings</a></li>
        <li><a href="reports.php">Reports & Analytics</a></li>
    </ul>
    
    <a href="logout.php">Logout</a>
</body>
</html> -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen">

  <!-- Sidebar + Content -->
  <div class="flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-md h-screen p-5 hidden md:block">
      <h2 class="text-2xl font-bold text-blue-600 mb-6">Admin Panel</h2>
      <ul class="space-y-4 text-gray-700">
        <li class="flex items-center gap-3 hover:text-blue-600">
          <i data-lucide="users"></i>
          <a href="user_management.php">User Management</a>
        </li>
        <li class="flex items-center gap-3 hover:text-blue-600">
          <i data-lucide="shield-check"></i>
          <a href="content_moderation.php">Content Moderation</a>
        </li>
        <li class="flex items-center gap-3 hover:text-blue-600">
          <i data-lucide="book-open"></i>
          <a href="batch_management.php">Batch/Course Management</a>
        </li>
        <li class="flex items-center gap-3 hover:text-blue-600">
          <i data-lucide="settings"></i>
          <a href="system_settings.php">System Settings</a>
        </li>
        <li class="flex items-center gap-3 hover:text-blue-600">
          <i data-lucide="bar-chart-3"></i>
          <a href="reports.php">Reports & Analytics</a>
        </li>
      </ul>

      <div class="mt-10">
        <a href="logout.php" class="text-red-500 hover:text-red-700 flex items-center gap-2">
          <i data-lucide="log-out"></i> Logout
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6">
      <div class="bg-white shadow p-6 rounded-lg">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Welcome, Admin</h1>
        <p class="text-gray-500 mb-4">Hello <span class="text-blue-600 font-semibold"><?php echo htmlspecialchars($_SESSION['name']); ?></span>, manage your platform from here.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
          <!-- Cards -->
          <a href="user_management.php" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg shadow flex items-center gap-4">
            <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
            <div>
              <p class="font-semibold text-blue-700">User Management</p>
              <small class="text-sm text-blue-600">View & control users</small>
            </div>
          </a>
          <a href="content_moderation.php" class="bg-red-50 hover:bg-red-100 p-4 rounded-lg shadow flex items-center gap-4">
            <i data-lucide="shield-check" class="w-6 h-6 text-red-600"></i>
            <div>
              <p class="font-semibold text-red-700">Content Moderation</p>
              <small class="text-sm text-red-600">Manage posts & reports</small>
            </div>
          </a>
          <a href="batch_management.php" class="bg-green-50 hover:bg-green-100 p-4 rounded-lg shadow flex items-center gap-4">
            <i data-lucide="book-open" class="w-6 h-6 text-green-600"></i>
            <div>
              <p class="font-semibold text-green-700">Batch/Course Management</p>
              <small class="text-sm text-green-600">Handle courses & schedules</small>
            </div>
          </a>
          <a href="system_settings.php" class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg shadow flex items-center gap-4">
            <i data-lucide="settings" class="w-6 h-6 text-yellow-600"></i>
            <div>
              <p class="font-semibold text-yellow-700">System Settings</p>
              <small class="text-sm text-yellow-600">Adjust platform config</small>
            </div>
          </a>
          <a href="reports.php" class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg shadow flex items-center gap-4">
            <i data-lucide="bar-chart-3" class="w-6 h-6 text-purple-600"></i>
            <div>
              <p class="font-semibold text-purple-700">Reports & Analytics</p>
              <small class="text-sm text-purple-600">Track usage & stats</small>
            </div>
          </a>
        </div>
      </div>
    </main>

  </div>

  <!-- Lucide Icons -->
  <script>
    lucide.createIcons();
  </script>
</body>
</html>
