<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        
        if ($user['role'] != 'admin') {
            $stmt = $pdo->prepare("SELECT b.* FROM user_batch_mapping ubm 
                                 JOIN batches b ON ubm.batch_id = b.batch_id 
                                 WHERE ubm.user_id = ?");
            $stmt->execute([$user['user_id']]);
            $batch = $stmt->fetch();
            $_SESSION['batch_id'] = $batch['batch_id'] ?? null;
            $_SESSION['batch_name'] = $batch['name'] ?? null;
        }

        switch ($user['role']) {
            case 'admin':
                header('Location: admin_dashboard.php');
                break;
            case 'faculty':
            case 'student':
                header('Location: main.php');
                break;
            default:
                header('Location: login.php?error=1');
        }
        exit();
    } else {
        header('Location: login.php?error=1');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Portal - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex flex-col">

    <!-- Header -->
    <header class="fixed w-full bg-white shadow z-50">
  <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
    <h1 class="text-2xl font-bold flex items-center gap-2 text-blue-600">
      🎓 ALUMINI
    </h1>
    <span class="text-sm text-gray-600">Welcome back!</span>
  </div>
</header>

    <!-- Login Form Container -->
    <main class="flex-grow flex items-center justify-center">
        <div class="bg-white shadow-lg rounded-lg w-full max-w-md p-8">
            <h2 class="text-2xl font-semibold text-blue-800 text-center mb-6">Login to your account</h2>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 text-red-700 border border-red-400 p-3 rounded mb-4">
                    Invalid email or password.
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="text" id="email" name="email" required
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <input type="submit" value="Login"
                           class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition duration-200">
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-blue-800 text-white text-center py-3 mt-8">
        © <?php echo date('Y'); ?> Alumni Portal. All rights reserved.
    </footer>

</body>
</html>
