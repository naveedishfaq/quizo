<?php
require '../config.php';

$quizId = intval($_GET['id'] ?? 0);
if (!$quizId) redirect('index.php');

$quiz = $pdo->prepare("SELECT * FROM quiz_sets WHERE id = ? AND status = 'published' LIMIT 1");
$quiz->execute([$quizId]);
$quiz = $quiz->fetch();
if (!$quiz) redirect('index.php');

// Check assignment
$assigned = $pdo->prepare("SELECT 1 FROM quiz_assignments WHERE quiz_id = ? AND group_id = ? LIMIT 1");
$assigned->execute([$quizId, $_SESSION['group_id'] ?? 0]);
if (!$assigned->fetch() && !isAdmin()) redirect('index.php');

// Check existing attempt
$attempt = $pdo->prepare("SELECT * FROM attempts WHERE quiz_id = ? AND user_id = ? LIMIT 1");
$attempt->execute([$quizId, $_SESSION['user_id']]);
$existing = $attempt->fetch();

if ($existing && $existing['status'] !== 'in_progress' && !$quiz['allow_multiple_attempts']) {
    redirect('results.php');
}

// Create or resume attempt
if (!$existing) {
    $pdo->prepare("INSERT INTO attempts (quiz_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)")
        ->execute([$quizId, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    $attemptId = $pdo->lastInsertId();
} else {
    $attemptId = $existing['id'];
    if ($existing['status'] !== 'in_progress') {
        $pdo->prepare("UPDATE attempts SET status='in_progress', started_at=NOW(), score=0 WHERE id=?")->execute([$attemptId]);
    }
}

// Get questions
$order = $quiz['shuffle_questions'] ? 'RAND()' : 'qq.question_order';
$questions = $pdo->prepare("
    SELECT q.*, qq.question_order 
    FROM questions q 
    JOIN quiz_questions qq ON q.id = qq.question_id 
    WHERE qq.quiz_id = ? AND q.status = 1
    ORDER BY $order
");
$questions->execute([$quizId]);
$questions = $questions->fetchAll();

// Pre-create answer rows
foreach ($questions as $q) {
    $check = $pdo->prepare("SELECT id FROM answers WHERE attempt_id = ? AND question_id = ? LIMIT 1");
    $check->execute([$attemptId, $q['id']]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO answers (attempt_id, question_id) VALUES (?, ?)")->execute([$attemptId, $q['id']]);
    }
}

$totalPoints = array_sum(array_column($questions, 'points'));
$pdo->prepare("UPDATE attempts SET total_points = ? WHERE id = ?")->execute([$totalPoints, $attemptId]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($quiz['title']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f8fafc; }
        .quiz-nav { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .quiz-nav button { width: 40px; height: 40px; border-radius: 6px; border: 1px solid var(--border); background: white; cursor: pointer; font-weight: 600; }
        .quiz-nav button.answered { background: var(--success); color: white; border-color: var(--success); }
        .quiz-nav button.current { border: 2px solid var(--primary); }
        .quiz-nav button:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="violation-warning" id="violationBanner">
        ⚠️ VIOLATION DETECTED! Tab switch or copy attempt recorded.
    </div>
    
    <div class="fullscreen-overlay" id="startOverlay">
        <h2><?= sanitize($quiz['title']) ?></h2>
        <p>Time Limit: <?= $quiz['time_limit'] ?> minutes</p>
        <p>Questions: <?= count($questions) ?></p>
        <p style="color:#fca5a5;margin:1rem 0;">⚠️ Anti-cheat is active. Do not switch tabs, copy, or paste.</p>
        <button class="btn btn-primary" onclick="enterFullscreen()" style="font-size:1.125rem;padding:1rem 2rem;">Start Quiz (Enter Fullscreen)</button>
    </div>
    
    <div class="quiz-container" id="quizContainer" style="display:none;">
        <div class="quiz-header">
            <div>
                <h2><?= sanitize($quiz['title']) ?></h2>
                <small>Question <span id="currentNum">1</span> of <?= count($questions) ?></small>
            </div>
            <div class="timer" id="timer"><?= $quiz['time_limit'] ?>:00</div>
        </div>
        
        <div class="quiz-nav" id="questionNav">
            <?php foreach ($questions as $i => $q): ?>
            <button type="button" class="<?= $i===0?'current':'' ?>" onclick="goToQuestion(<?= $i ?>)" id="nav-<?= $i ?>"><?= $i+1 ?></button>
            <?php endforeach; ?>
        </div>
        
        <form id="quizForm">
            <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            
            <?php foreach ($questions as $i => $q): 
                $opts = $q['options'] ? json_decode($q['options'], true) : [];
            ?>
            <div class="question-card" id="q-<?= $i ?>" style="<?= $i>0?'display:none':'' ?>">
                <div class="question-text">
                    <span class="badge badge-blue" style="margin-right:0.5rem;"><?= $q['points'] ?> pts</span>
                    <?= nl2br(sanitize($q['question_text'])) ?>
                </div>
                
                <?php if ($q['question_type'] === 'mcq'): ?>
                <div class="options">
                    <?php foreach ($opts as $opt): ?>
                    <label>
                        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= sanitize($opt) ?>" onchange="markAnswered(<?= $i ?>)">
                        <span><?= sanitize($opt) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                <div class="short-answer">
                    <textarea name="q_<?= $q['id'] ?>" class="form-control" oninput="markAnswered(<?= $i ?>)" placeholder="Type your answer here..."></textarea>
                </div>
                
                <?php elseif ($q['question_type'] === 'points'): ?>
                <div class="points-input">
                    <label>Rate (1-10):</label>
                    <input type="number" name="q_<?= $q['id'] ?>" min="1" max="10" class="form-control" onchange="markAnswered(<?= $i ?>)">
                </div>
                <?php endif; ?>
                
                <div style="display:flex;justify-content:space-between;margin-top:1.5rem;">
                    <?php if ($i > 0): ?>
                    <button type="button" class="btn" onclick="goToQuestion(<?= $i-1 ?>)">← Previous</button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    
                    <?php if ($i < count($questions)-1): ?>
                    <button type="button" class="btn btn-primary" onclick="goToQuestion(<?= $i+1 ?>)">Next →</button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success" onclick="submitQuiz()">Submit Quiz</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </form>
    </div>
    
    <script src="../assets/js/quiz.js"></script>
    <script>
        const QUIZ_CONFIG = {
            timeLimit: <?= $quiz['time_limit'] ?>,
            maxTabs: <?= $quiz['max_tab_switches'] ?>,
            attemptId: <?= $attemptId ?>,
            csrfToken: '<?= csrfToken() ?>'
        };
        initQuiz();
    </script>
</body>
</html>