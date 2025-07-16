<?php
require 'auth_check.php';
require 'config.php';
require 'forum_utils.php';

// Fetch all questions with their best answers
$questionsStmt = $pdo->prepare("
    SELECT q.*, u.name as author_name, u.profile_pic,
    (
        SELECT COUNT(*) 
        FROM answers a 
        WHERE a.question_id = q.question_id
    ) as answer_count,
    (
        SELECT a.answer_id
        FROM answers a
        WHERE a.question_id = q.question_id AND a.is_best = 1
        LIMIT 1
    ) as best_answer_id,
    (
        SELECT COUNT(*) 
        FROM answer_votes av 
        JOIN answers a ON av.answer_id = a.answer_id
        WHERE a.question_id = q.question_id AND av.vote_type = 'up'
    ) - (
        SELECT COUNT(*) 
        FROM answer_votes av 
        JOIN answers a ON av.answer_id = a.answer_id
        WHERE a.question_id = q.question_id AND av.vote_type = 'down'
    ) as total_votes
    FROM questions q
    JOIN users u ON q.user_id = u.user_id
    ORDER BY q.created_at DESC
");
$questionsStmt->execute();
$questions = $questionsStmt->fetchAll();

// Fetch best answers for each question
$bestAnswers = [];
foreach ($questions as $question) {
    if ($question['best_answer_id']) {
        $answerStmt = $pdo->prepare("
            SELECT a.*, u.name as author_name, u.profile_pic,
            (
                SELECT COUNT(*) 
                FROM answer_votes 
                WHERE answer_id = a.answer_id AND vote_type = 'up'
            ) as upvotes,
            (
                SELECT COUNT(*) 
                FROM answer_votes 
                WHERE answer_id = a.answer_id AND vote_type = 'down'
            ) as downvotes
            FROM answers a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.answer_id = ?
        ");
        $answerStmt->execute([$question['best_answer_id']]);
        $bestAnswers[$question['question_id']] = $answerStmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Q&A Forum | Alumni Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

</head>
<body class="bg-gray-50 text-gray-800">

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

  <div class="max-w-4xl mx-auto p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Q&A Forum</h1>
      <a href="ask_question.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Ask Question</a>
    </div>

    <!-- Questions -->
    <?php foreach ($questions as $question): ?>
      <div class="bg-white p-5 rounded-lg shadow mb-6">
        <!-- Header -->
        <div class="flex items-start gap-4 mb-3">
          <img src="<?= htmlspecialchars($question['profile_pic'] ?: 'https://via.placeholder.com/40') ?>" alt="Author" class="w-10 h-10 rounded-full object-cover">
          <div>
            <h3 class="text-lg font-semibold">
              <a href="question_detail.php?id=<?= $question['question_id'] ?>" class="hover:underline text-blue-700">
                <?= htmlspecialchars($question['title']) ?>
              </a>
            </h3>
            <div class="text-sm text-gray-500">
              Asked by <?= htmlspecialchars($question['author_name']) ?> • <?= date('M j, Y', strtotime($question['created_at'])) ?>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="mb-4 text-gray-700">
          <?= processContent($question['content']) ?>
        </div>

        <!-- Best Answer -->
        <?php if (isset($bestAnswers[$question['question_id']])): 
          $bestAnswer = $bestAnswers[$question['question_id']]; ?>
          <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded mb-3">
            <div class="flex justify-between items-center mb-1">
              <span class="text-sm font-semibold text-green-700">Best Answer</span>
              <span class="text-sm font-medium text-gray-600"><?= ($bestAnswer['upvotes'] - $bestAnswer['downvotes']) ?> votes</span>
            </div>
            <div class="text-gray-800 mb-2">
              <?= processContent($bestAnswer['content']) ?>
            </div>
            <div class="text-xs text-gray-500">— <?= htmlspecialchars($bestAnswer['author_name']) ?></div>
          </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex justify-between items-center text-sm text-gray-600">
          <div>
            <span><?= $question['answer_count'] ?> answers</span>
            <span class="mx-1">•</span>
            <span><?= $question['total_votes'] ?> votes</span>
          </div>
          <button class="flex items-center gap-1 text-red-600 hover:underline report-btn" data-type="question" data-id="<?= $question['question_id'] ?>">
            <i class="fas fa-flag"></i> Report
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Report Modal -->
  <div id="report-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-md p-6 rounded-lg shadow-lg">
      <h3 class="text-lg font-bold mb-4">Report Content</h3>
      <input type="hidden" id="report-content-type">
      <input type="hidden" id="report-content-id">
      <div class="mb-4">
        <label for="report-reason" class="block mb-1 font-medium">Reason:</label>
        <textarea id="report-reason" rows="4" class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:ring focus:ring-blue-300"></textarea>
      </div>
      <div class="flex justify-end gap-2">
        <button id="cancel-report" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
        <button id="submit-report" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Submit Report</button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function () {
      $('.report-btn').on('click', function () {
        $('#report-content-type').val($(this).data('type'));
        $('#report-content-id').val($(this).data('id'));
        $('#report-reason').val('');
        $('#report-modal').removeClass('hidden');
      });

      $('#cancel-report').click(function () {
        $('#report-modal').addClass('hidden');
      });

      $('#submit-report').click(function () {
        const type = $('#report-content-type').val();
        const id = $('#report-content-id').val();
        const reason = $('#report-reason').val().trim();

        if (!reason) {
          alert('Please enter a reason for reporting');
          return;
        }

        $.post('report_content.php', {
          content_type: type,
          content_id: id,
          reason: reason
        }, function (response) {
          if (response === 'success') {
            alert('Content reported successfully');
            $('#report-modal').addClass('hidden');
          } else {
            alert('Error: ' + response);
          }
        }).fail(function () {
          alert('Error reporting content. Please try again.');
        });
      });
    });
  </script>

</body>
</html>
