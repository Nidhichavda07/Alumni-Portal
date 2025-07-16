<?php
require 'auth_check.php';
require 'config.php';
require 'forum_utils.php';

if (!isset($_GET['id'])) {
    header('Location: questions.php');
    exit();
}

$questionId = $_GET['id'];

// Fetch question details
$questionStmt = $pdo->prepare("
    SELECT q.*, u.name as author_name, u.profile_pic, u.role
    FROM questions q
    JOIN users u ON q.user_id = u.user_id
    WHERE q.question_id = ?
");
$questionStmt->execute([$questionId]);
$question = $questionStmt->fetch();

if (!$question) {
    header('Location: questions.php');
    exit();
}

// Fetch answers with vote counts
$answersStmt = $pdo->prepare("
    SELECT a.*, u.name as author_name, u.profile_pic, u.role,
    (
        SELECT COUNT(*) 
        FROM answer_votes 
        WHERE answer_id = a.answer_id AND vote_type = 'up'
    ) as upvotes,
    (
        SELECT COUNT(*) 
        FROM answer_votes 
        WHERE answer_id = a.answer_id AND vote_type = 'down'
    ) as downvotes,
    EXISTS(
        SELECT 1 FROM answer_votes 
        WHERE answer_id = a.answer_id AND user_id = ? AND vote_type = 'up'
    ) as has_upvoted,
    EXISTS(
        SELECT 1 FROM answer_votes 
        WHERE answer_id = a.answer_id AND user_id = ? AND vote_type = 'down'
    ) as has_downvoted
    FROM answers a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.question_id = ?
    ORDER BY a.is_best DESC, (upvotes - downvotes) DESC, a.created_at ASC
");
$answersStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $questionId]);
$answers = $answersStmt->fetchAll();

// Fetch replies for each answer
$replies = [];
foreach ($answers as $answer) {
    $repliesStmt = $pdo->prepare("
        SELECT ar.*, u.name as author_name, u.profile_pic
        FROM answer_replies ar
        JOIN users u ON ar.user_id = u.user_id
        WHERE ar.answer_id = ?
        ORDER BY ar.created_at ASC
    ");
    $repliesStmt->execute([$answer['answer_id']]);
    $replies[$answer['answer_id']] = $repliesStmt->fetchAll();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Post answer
    if (isset($_POST['answer_content'])) {
        $content = $_POST['answer_content'];
        
        if (containsBannedWords($content)) {
            $answerError = "Your answer contains inappropriate language and cannot be posted.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO answers (question_id, user_id, content, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            if ($stmt->execute([$questionId, $_SESSION['user_id'], $content])) {
                header("Location: question_detail.php?id=$questionId");
                exit();
            } else {
                $answerError = "Failed to post answer";
            }
        }
    }
    
    // Post reply
    elseif (isset($_POST['reply_content']) && isset($_POST['answer_id'])) {
        $content = $_POST['reply_content'];
        $answerId = $_POST['answer_id'];
        
        if (containsBannedWords($content)) {
            $replyError = "Your reply contains inappropriate language and cannot be posted.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO answer_replies (answer_id, user_id, content, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            if ($stmt->execute([$answerId, $_SESSION['user_id'], $content])) {
                header("Location: question_detail.php?id=$questionId");
                exit();
            } else {
                $replyError = "Failed to post reply";
            }
        }
    }
    
    // Mark as best answer
    elseif (isset($_POST['mark_best']) && isset($_POST['answer_id'])) {
        if ($question['user_id'] == $_SESSION['user_id'] || $_SESSION['role'] == 'faculty') {
            $answerId = $_POST['answer_id'];
            
            // First unmark any existing best answer
            $pdo->prepare("UPDATE answers SET is_best = 0 WHERE question_id = ?")->execute([$questionId]);
            
            // Mark new best answer
            $stmt = $pdo->prepare("
                UPDATE answers 
                SET is_best = 1, best_marked_by = ?, best_marked_at = NOW()
                WHERE answer_id = ?
            ");
            if ($stmt->execute([$_SESSION['user_id'], $answerId])) {
                header("Location: question_detail.php?id=$questionId");
                exit();
            }
        }
    }
    
    // Vote on answer
    elseif (isset($_POST['vote_type']) && isset($_POST['answer_id'])) {
        $answerId = $_POST['answer_id'];
        $voteType = $_POST['vote_type'];
        
        // Check if user already voted
        $checkStmt = $pdo->prepare("
            SELECT vote_type FROM answer_votes 
            WHERE answer_id = ? AND user_id = ?
        ");
        $checkStmt->execute([$answerId, $_SESSION['user_id']]);
        $existingVote = $checkStmt->fetch();
        
        if ($existingVote) {
            if ($existingVote['vote_type'] == $voteType) {
                // Remove vote if clicking same button
                $pdo->prepare("
                    DELETE FROM answer_votes 
                    WHERE answer_id = ? AND user_id = ?
                ")->execute([$answerId, $_SESSION['user_id']]);
            } else {
                // Update vote if changing vote type
                $pdo->prepare("
                    UPDATE answer_votes 
                    SET vote_type = ?
                    WHERE answer_id = ? AND user_id = ?
                ")->execute([$voteType, $answerId, $_SESSION['user_id']]);
            }
        } else {
            // Add new vote
            $pdo->prepare("
                INSERT INTO answer_votes (answer_id, user_id, vote_type, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([$answerId, $_SESSION['user_id'], $voteType]);
        }
        
        header("Location: question_detail.php?id=$questionId#answer-$answerId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($question['title']); ?> | Alumni Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800">
  <div class="max-w-4xl mx-auto py-8 px-4">
    <!-- Question -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <div class="flex items-center space-x-4 mb-4">
        <img src="<?php echo htmlspecialchars($question['profile_pic'] ?: 'https://via.placeholder.com/40'); ?>" class="w-10 h-10 rounded-full" alt="Author">
        <div>
          <h1 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($question['title']); ?></h1>
          <p class="text-sm text-gray-500">Asked by <?php echo htmlspecialchars($question['author_name']); ?> on <?php echo date('M j, Y g:i a', strtotime($question['created_at'])); ?></p>
        </div>
      </div>
      <div class="prose max-w-none">
        <?php echo processContent($question['content']); ?>
      </div>
    </div>

    <!-- Answer Form -->
    <div class="bg-white mt-8 p-6 rounded-lg shadow-md">
      <h2 class="text-lg font-semibold mb-4">Your Answer</h2>
      <form method="POST">
        <textarea name="answer_content" class="w-full p-3 border rounded-md focus:outline-none focus:ring" rows="6" required></textarea>
        <div class="mention-dropdown mt-2 text-sm text-gray-500" id="answer-mention-dropdown"></div>
        <button type="submit" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Post Answer</button>
        <?php if (isset($answerError)): ?>
        <p class="mt-2 text-red-600"><?php echo htmlspecialchars($answerError); ?></p>
        <?php endif; ?>
      </form>
    </div>

    <!-- Answers List -->
    <div class="mt-10 space-y-6">
      <?php foreach ($answers as $answer): ?>
      <div class="bg-white p-6 rounded-lg shadow-md <?php echo $answer['is_best'] ? 'border-2 border-green-500' : ''; ?>" id="answer-<?php echo $answer['answer_id']; ?>">
        <?php if ($answer['is_best']): ?>
        <span class="inline-block mb-2 text-green-700 font-semibold">Best Answer</span>
        <?php endif; ?>

        <div class="flex items-start space-x-4">
          <img src="<?php echo htmlspecialchars($answer['profile_pic'] ?: 'https://via.placeholder.com/40'); ?>" class="w-10 h-10 rounded-full" alt="Author">
          <div class="flex-1">
            <div class="flex justify-between items-center">
              <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($answer['author_name']); ?></h4>
              <small class="text-gray-500"><?php echo date('M j, Y g:i a', strtotime($answer['created_at'])); ?></small>
            </div>
            <div class="mt-2 prose max-w-none">
              <?php echo processContent($answer['content']); ?>
            </div>
            <div class="mt-4 flex items-center space-x-4">
              <form method="POST" class="flex items-center space-x-2">
                <input type="hidden" name="vote_type" value="up">
                <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
                <button type="submit" class="text-blue-600 hover:text-blue-800 <?php echo $answer['has_upvoted'] ? 'font-bold' : ''; ?>">
                  <i class="fas fa-arrow-up"></i>
                </button>
              </form>
              <span class="text-gray-700 font-semibold"><?php echo ($answer['upvotes'] - $answer['downvotes']); ?></span>
              <form method="POST" class="flex items-center space-x-2">
                <input type="hidden" name="vote_type" value="down">
                <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
                <button type="submit" class="text-red-600 hover:text-red-800 <?php echo $answer['has_downvoted'] ? 'font-bold' : ''; ?>">
                  <i class="fas fa-arrow-down"></i>
                </button>
              </form>

              <?php if (($question['user_id'] == $_SESSION['user_id'] || $_SESSION['role'] == 'faculty') && !$answer['is_best']): ?>
              <form method="POST">
                <input type="hidden" name="mark_best" value="1">
                <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
                <button type="submit" class="ml-4 px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">Mark as Best</button>
              </form>
              <?php endif; ?>

              <button type="button" class="ml-4 text-sm text-blue-600 hover:underline reply-btn" data-answer-id="<?php echo $answer['answer_id']; ?>">Reply</button>
            </div>

            <!-- Replies -->
            <?php if (!empty($replies[$answer['answer_id']])): ?>
            <div class="mt-4 space-y-3">
              <?php foreach ($replies[$answer['answer_id']] as $reply): ?>
              <div class="bg-gray-100 p-3 rounded-md">
                <div class="flex items-center space-x-3">
                  <img src="<?php echo htmlspecialchars($reply['profile_pic'] ?: 'https://via.placeholder.com/30'); ?>" class="w-7 h-7 rounded-full" alt="Author">
                  <strong class="text-sm"><?php echo htmlspecialchars($reply['author_name']); ?></strong>
                  <small class="text-gray-500"><?php echo date('M j, g:i a', strtotime($reply['created_at'])); ?></small>
                </div>
                <div class="mt-1 text-sm">
                  <?php echo processContent($reply['content']); ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Reply form -->
            <form method="POST" class="reply-form hidden mt-4" id="reply-form-<?php echo $answer['answer_id']; ?>">
              <input type="hidden" name="answer_id" value="<?php echo $answer['answer_id']; ?>">
              <textarea name="reply_content" rows="3" class="w-full p-2 mt-2 border rounded-md" required></textarea>
              <div class="mention-dropdown mt-1 text-sm text-gray-500 reply-mention-dropdown"></div>
              <button type="submit" class="mt-2 px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">Post Reply</button>
              <?php if (isset($replyError)): ?>
              <p class="text-red-600 mt-1"><?php echo htmlspecialchars($replyError); ?></p>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- JS logic for dynamic reply display etc. would go here -->



    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Handle reply button click
        $('.reply-btn').click(function() {
            const answerId = $(this).data('answer-id');
            $('.reply-form').removeClass('active');
            $('#reply-form-' + answerId).addClass('active');
            $('#reply-form-' + answerId + ' textarea').focus();
        });
        
        // Handle vote buttons
        $('.vote-btn').click(function() {
            const answerId = $(this).data('answer-id');
            const voteType = $(this).hasClass('upvote') ? 'up' : 'down';
            
            $.ajax({
                url: 'question_detail.php?id=<?php echo $questionId; ?>',
                method: 'POST',
                data: {
                    vote_type: voteType,
                    answer_id: answerId
                },
                success: function() {
                    location.reload();
                }
            });
        });
        
        // Mention functionality
        const connections = <?php echo json_encode($connections); ?>;
        let mentionStartPos = -1;
        
        // Handle mentions in answer textarea
        $('textarea[name="answer_content"]').on('input', function() {
            handleMentions($(this), $('#answer-mention-dropdown'));
        });
        
        // Handle mentions in reply textareas
        $(document).on('input', 'textarea[name="reply_content"]', function() {
            handleMentions($(this), $(this).siblings('.mention-dropdown'));
        });
        
        function handleMentions(input, dropdown) {
            const val = input.val();
            const cursorPos = input[0].selectionStart;
            const textBeforeCursor = val.substring(0, cursorPos);
            
            mentionStartPos = textBeforeCursor.lastIndexOf('@');
            
            if (mentionStartPos >= 0 && (mentionStartPos === 0 || textBeforeCursor[mentionStartPos - 1].match(/\s/))) {
                const searchTerm = textBeforeCursor.substring(mentionStartPos + 1).toLowerCase();
                const filteredUsers = connections.filter(user => 
                    user.name.toLowerCase().includes(searchTerm)
                );
                
                if (filteredUsers.length > 0) {
                    dropdown.empty();
                    
                    filteredUsers.forEach(user => {
                        dropdown.append(`
                            <div class="mention-item" data-user-id="${user.user_id}" data-user-name="${user.name}">
                                <img src="${user.profile_pic || 'https://via.placeholder.com/40'}" width="20" height="20" style="border-radius:50%;margin-right:5px;">
                                ${user.name}
                            </div>
                        `);
                    });
                    
                    dropdown.show();
                } else {
                    dropdown.hide();
                }
            } else {
                dropdown.hide();
            }
        }
        
        // Handle mention selection in answer textarea
        $(document).on('click', '#answer-mention-dropdown .mention-item', function() {
            selectMention($(this), $('textarea[name="answer_content"]'), $('#answer-mention-dropdown'));
        });
        
        // Handle mention selection in reply textareas
        $(document).on('click', '.reply-mention-dropdown .mention-item', function() {
            selectMention($(this), $(this).parent().siblings('textarea'), $(this).parent());
        });
        
        function selectMention(item, input, dropdown) {
            const userId = item.data('user-id');
            const userName = item.data('user-name');
            const val = input.val();
            const cursorPos = input[0].selectionStart;
            
            // Replace the @mention with the formatted mention
            const beforeText = val.substring(0, mentionStartPos);
            const afterText = val.substring(cursorPos);
            const newVal = beforeText + `@[${userName}](user_${userId})` + afterText;
            
            input.val(newVal);
            dropdown.hide();
            
            // Set cursor position after the inserted mention
            const newCursorPos = mentionStartPos + `@[${userName}](user_${userId})`.length;
            setTimeout(() => {
                input[0].setSelectionRange(newCursorPos, newCursorPos);
                input.focus();
            }, 0);
        }
        
        // Hide dropdown when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('.mention-dropdown').length && !$(e.target).is('textarea')) {
                $('.mention-dropdown').hide();
            }
        });
    });
    </script>
</body>
</html>