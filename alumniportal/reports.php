<?php
require 'config.php';

// Fetch reports with reporter and reviewer names
$stmt = $pdo->prepare("
    SELECT 
        rc.*,
        reporter.name AS reporter_name,
        reviewer.name AS reviewer_name
    FROM reported_content rc
    JOIN users reporter ON rc.reported_by = reporter.user_id
    LEFT JOIN users reviewer ON rc.reviewed_by = reviewer.user_id
    ORDER BY rc.created_at DESC
");
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reported Content | Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-7xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-3xl font-bold text-center text-red-700 mb-6">Reported Content</h1>

    <?php if (empty($reports)): ?>
      <p class="text-center text-gray-600">No reports found.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-300 text-sm">
          <thead class="bg-red-100 text-red-800 font-semibold">
            <tr>
              <th class="px-4 py-2 border">#</th>
              <th class="px-4 py-2 border">Reported By</th>
              <th class="px-4 py-2 border">Content Type</th>
              <th class="px-4 py-2 border">Content ID</th>
              <th class="px-4 py-2 border">Reason</th>
              <th class="px-4 py-2 border">Status</th>
              <th class="px-4 py-2 border">Admin Remarks</th>
              <th class="px-4 py-2 border">Reviewed By</th>
              <th class="px-4 py-2 border">Reviewed At</th>
              <th class="px-4 py-2 border">Reported At</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <?php foreach ($reports as $index => $report): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border"><?= $index + 1 ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($report['reporter_name']) ?></td>
                <td class="px-4 py-2 border capitalize"><?= htmlspecialchars($report['content_type']) ?></td>
                <td class="px-4 py-2 border"><?= $report['content_id'] ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($report['reason']) ?></td>
                <td class="px-4 py-2 border text-blue-700 font-medium"><?= htmlspecialchars($report['status']) ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($report['admin_remarks'] ?? '-') ?></td>
                <td class="px-4 py-2 border"><?= htmlspecialchars($report['reviewer_name'] ?? '-') ?></td>
                <td class="px-4 py-2 border">
                  <?= $report['reviewed_at'] ? date('d M Y, h:i A', strtotime($report['reviewed_at'])) : '-' ?>
                </td>
                <td class="px-4 py-2 border"><?= date('d M Y, h:i A', strtotime($report['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
