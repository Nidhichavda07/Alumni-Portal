<?php
require 'config.php';

$selectedBatch = null;
$courseDetails = null;

// Fetch all batches
$batches = $pdo->query("SELECT batch_id, name FROM batches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// If a batch is selected
if (isset($_GET['batch_id'])) {
    $batch_id = $_GET['batch_id'];

    // Fetch batch with course info
    $stmt = $pdo->prepare("SELECT b.name AS batch_name, c.name AS course_name, c.description 
                           FROM batches b 
                           JOIN courses c ON b.course_id = c.course_id 
                           WHERE b.batch_id = ?");
    $stmt->execute([$batch_id]);
    $selectedBatch = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Batch Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8 font-sans">
  <div class="max-w-3xl mx-auto bg-white p-6 shadow-md rounded-lg">
    <h1 class="text-2xl font-bold mb-6 text-center">Batch Management</h1>

    <!-- Batch Selection Form -->
    <form method="GET" class="mb-6">
      <label for="batch_id" class="block mb-2 font-semibold">Select Batch:</label>
      <select name="batch_id" id="batch_id" class="w-full p-2 border border-gray-300 rounded">
        <option value="">-- Select Batch --</option>
        <?php foreach ($batches as $batch): ?>
          <option value="<?= $batch['batch_id'] ?>" <?= isset($_GET['batch_id']) && $_GET['batch_id'] == $batch['batch_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($batch['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        View Details
      </button>
    </form>

    <!-- Display Selected Batch & Course -->
    <?php if ($selectedBatch): ?>
    <div class="bg-gray-50 p-4 border border-gray-200 rounded">
      <h2 class="text-xl font-semibold mb-2 text-blue-700">Batch: <?= htmlspecialchars($selectedBatch['batch_name']) ?></h2>
      <p><strong>Course:</strong> <?= htmlspecialchars($selectedBatch['course_name']) ?></p>
      <p><strong>Description:</strong> <?= htmlspecialchars($selectedBatch['description']) ?></p>
    </div>
    <?php elseif (isset($_GET['batch_id'])): ?>
      <p class="text-red-500">Batch not found or has no course assigned.</p>
    <?php endif; ?>
  </div>
</body>
</html>
