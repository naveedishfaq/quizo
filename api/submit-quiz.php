<?php
require '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid request']);
}

$attemptId = intval($_POST['attempt_id'] ?? 0);
$auto = isset($_POST['auto']) && $_POST['auto'] === '1';
$violations = json_decode($_POST['violations'] ?? '{}', true);

if (!$attemptId) jsonResponse(['success' => false]);

// Get attempt info
$attempt = $pdo->prepare("SELECT a.*, q.allow_multiple_attempts, q.shuffle_questions FROM attempts a JOIN quiz_sets q ON a.quiz_id = q.id WHERE a.id = ? AND a.user_id = ? LIMIT 1");
$attempt->execute([$attemptId, $_SESSION['user_id']]);
$attemptData = $attempt->fetch();

if (!$attemptData) jsonResponse(['success' => false, 'message' => 'Attempt not found']);
if ($attemptData['status'] !== 'in_progress') jsonResponse(['success' => false, 'message' => 'Already submitted']);

// Get questions
$questions = $pdo->prepare("SELECT q.* FROM questions q JOIN quiz_questions qq ON q.id = qq.question_id WHERE qq.quiz_id = ?");
$questions->execute([$attemptData['quiz_id']]);
$questions = $questions->fetchAll();

$totalScore = 0;
$totalPoints = 0;

foreach ($questions as $q) {
    $answer = trim($_POST['q_' . $q['id']] ?? '');
    $isCorrect = null;
    $earned = 0;
    
    if ($q['question_type'] === 'mcq') {
        $isCorrect = strtolower(trim($answer)) === strtolower(trim($q['correct_answer']));
        $earned = $isCorrect ? $q['points'] : 0;
    } elseif ($q['question_type'] === 'points') {
        $val = intval($answer);
        $earned = min($val, $q['points']);
        $isCorrect = $earned > 0 ? 1 : 0;
    }
    // short_answer requires manual grading
    
    $totalPoints += $q['points'];
    $totalScore += $earned;
    
    $pdo->prepare("UPDATE answers SET answer_text = ?, is_correct = ?, points_earned = ? WHERE attempt_id = ? AND question_id = ?")
        ->execute([$answer, $isCorrect, $earned, $attemptId, $q['id']]);
}

$status = $auto ? 'auto_submitted' : 'submitted';
$pdo->prepare("UPDATE attempts SET status = ?, score = ?, total_points = ?, submitted_at = NOW(), tab_switch_count = ?, copy_paste_count = ? WHERE id = ?")
    ->execute([$status, $totalScore, $totalPoints, intval($violations['tab'] ?? 0), intval($violations['copy'] ?? 0), $attemptId]);

jsonResponse(['success' => true, 'score' => $totalScore, 'total' => $totalPoints]);
?>