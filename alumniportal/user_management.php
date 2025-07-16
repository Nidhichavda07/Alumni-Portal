<?php
require 'config.php'; // your DB config file

// Fetch all users
$stmt = $pdo->prepare("SELECT user_id, name, email, city, state FROM users ORDER BY user_id DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8 font-sans">

  <div class="max-w-6xl mx-auto bg-white shadow-lg rounded-lg p-6">
    <h1 class="text-2xl font-bold mb-6 text-center">User Management</h1>

    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-300 divide-y divide-gray-200">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <!-- <th class="px-4 py-2 text-left">User ID</th> -->
            <th class="px-4 py-2 text-left">Name</th>
            <th class="px-4 py-2 text-left">Email</th>
            <th class="px-4 py-2 text-left">City</th>
            <th class="px-4 py-2 text-left">State</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <?php foreach ($users as $user): ?>
            <tr class="hover:bg-gray-50">
              <!-- <td class="px-4 py-2 text-gray-800 font-medium">#<?php echo htmlspecialchars($user['user_id']); ?></td> -->
              <td class="px-4 py-2"><?php echo htmlspecialchars($user['name']); ?></td>
              <td class="px-4 py-2"><?php echo htmlspecialchars($user['email']); ?></td>
              <td class="px-4 py-2"><?php echo htmlspecialchars($user['city']); ?></td>
              <td class="px-4 py-2"><?php echo htmlspecialchars($user['state']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
