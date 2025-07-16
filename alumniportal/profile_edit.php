<?php
// session_start();
require_once 'auth_check.php';
require_once 'config.php';

// Only allow users to edit their own profile
// Only allow users to edit their own profile
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
try {
    // Prepare the SQL statement
    $stmt = $pdo->prepare("
        SELECT u.*, 
               b.name AS batch_name, b.batch_id,
               c.name AS course_name, c.course_id,
               s.show_email, s.show_phone, s.messaging_preference
        FROM users u
        LEFT JOIN user_batch_mapping ub ON u.user_id = ub.user_id
        LEFT JOIN batches b ON ub.batch_id = b.batch_id
        LEFT JOIN courses c ON b.course_id = c.course_id
        LEFT JOIN user_settings s ON u.user_id = s.user_id
        WHERE u.user_id = ?
    ");
    
    if ($stmt === false) {
        throw new Exception('Failed to prepare SQL statement');
    }
    
    // Execute the statement with parameters
    $success = $stmt->execute([$user_id]);
    if ($success === false) {
        throw new Exception('Failed to execute SQL statement');
    }
    
    // Fetch the user data
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user === false) {
        throw new Exception('User not found');
    }
    
} catch (Exception $e) {
    // Log the error and show a user-friendly message
    error_log('Database error: ' . $e->getMessage());
    $_SESSION['error'] = "Unable to load profile data. Please try again later.";
    header('Location: index.php');
    exit;
}
/// Check if user has department-controlled education
try {
    $stmt = $pdo->prepare("
        SELECT 1 FROM education_details 
        WHERE user_id = ? AND is_our_department = 1
    ");
    
    if ($stmt === false) {
        throw new Exception('Failed to prepare department check query');
    }
    
    $executed = $stmt->execute([$user_id]);
    if ($executed === false) {
        throw new Exception('Failed to execute department check query');
    }
    
    $is_department_student = $stmt->fetchColumn();
    if ($is_department_student === false) {
        $is_department_student = false; // No matching record found
    }
    
} catch (Exception $e) {
    error_log('Department check error: ' . $e->getMessage());
    $is_department_student = false; // Default to false on error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'profile_update_handler.php';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | Alumni Portal</title>
    <?php include 'head.php'; ?>
</head>
<body>
    
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


    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-6">Edit Profile</h1>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <?php include 'edit_profile_picture.php'; ?>
                <?php include 'edit_basic_info.php'; ?>
                <?php include 'edit_location.php'; ?>
                <?php include 'edit_about.php'; ?>
                <?php include 'edit_privacy.php'; ?>
                
                <?php if (!$is_department_student): ?>
                    <?php include 'edit_academic_info.php'; ?>
                <?php else: ?>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Your academic program is managed by the department. Contact admin for changes.
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="flex justify-end space-x-4 pt-4">
                    <a href="profile_view.php?id=<?= $user_id ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="text-center text-sm text-gray-500 py-3 bg-white border-t">
    Alumni Portal - by Ekta and Nidhi &copy; <?php echo date('Y'); ?>
  </footer>
    <script src="assets/js/profile_edit.js"></script>
</body>
</html>