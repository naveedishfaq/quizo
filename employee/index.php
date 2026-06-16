<?php
require '../config.php';

$groupId = $_SESSION['group_id'] ?? 0;

$assignedQuizzes = $pdo->prepare("
    SELECT q.*, qa.assigned_at,
    (SELECT status FROM attempts WHERE quiz_id = q.id AND user_id = ? LIMIT 1) as attempt_status,
    (SELECT score FROM attempts WHERE quiz_id = q.id AND user_id = ? AND status != 'in_progress' LIMIT 1) as my_score,
    (SELECT total_points FROM attempts WHERE quiz_id = q.id AND user_id = ? AND status != 'in_progress' LIMIT 1) as my_total
    FROM quiz_sets q
    JOIN quiz_assignments qa ON q.id = qa.quiz_id
    WHERE qa.group_id = ? AND q.status = 'published'
    AND (q.start_time IS NULL OR q.start_time <= NOW())
    AND (q.end_time IS NULL OR q.end_time >= NOW())
    ORDER BY qa.assigned_at DESC
");
$assignedQuizzes->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $groupId]);
$quizzes = $assignedQuizzes->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Employee Portal</h2>
            <small><?= sanitize($_SESSION['name']) ?></small>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="active">My Quizzes</a>
            <a href="results.php">My Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>My Assigned Quizzes</h1>
        
        <?php if (empty($quizzes)): ?>
        <div class="card">
            <p>No quizzes assigned to you at this time.</p>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <?php foreach ($quizzes as $q): 
                $hasAttempt = !empty($q['attempt_status']) && $q['attempt_status'] !== 'in_progress';
                $canRetake = !$hasAttempt || $q['allow_multiple_attempts'];
            ?>
            <div class="card">
                <h3><?= sanitize($q['title']) ?></h3>
                <p><?= sanitize($q['description'] ?? 'No description') ?></p>
                <div style="margin:1rem 0;">
                    <span class="badge badge-blue"><?= $q['time_limit'] ?> min</span>
                    <?php if ($hasAttempt): ?>
                    <span class="badge badge-green">Completed: <?= $q['my_score'] ?>/<?= $q['my_total'] ?></span>
                    <?php else: ?>
                    <span class="badge badge-yellow">Pending</span>
                    <?php endif; ?>
                </div>
                <?php if (!$hasAttempt || $canRetake): ?>
                <a href="quiz.php?id=<?= $q['id'] ?>" class="btn btn-primary">Start Quiz</a>
                <?php else: ?>
                <button class="btn btn-success" disabled>Already Submitted</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>