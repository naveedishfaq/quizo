<?php
require '../config.php';

$results = $pdo->prepare("
    SELECT a.*, q.title as quiz_title, q.time_limit 
    FROM attempts a 
    JOIN quiz_sets q ON a.quiz_id = q.id 
    WHERE a.user_id = ? AND a.status != 'in_progress'
    ORDER BY a.submitted_at DESC
");
$results->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Results</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Employee Portal</h2>
            <small><?= sanitize($_SESSION['name']) ?></small>
        </div>
        <div class="sidebar-menu">
            <a href="index.php">My Quizzes</a>
            <a href="results.php" class="active">My Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>My Results</h1>
        
        <?php if (!$results->rowCount()): ?>
        <div class="card"><p>No completed quizzes yet.</p></div>
        <?php endif; ?>
        
        <?php foreach ($results as $r): 
            $pct = $r['total_points'] > 0 ? round(($r['score'] / $r['total_points']) * 100) : 0;
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h3><?= sanitize($r['quiz_title']) ?></h3>
                    <p style="color:#64748b;font-size:0.875rem;">
                        Submitted: <?= date('M d, Y H:i', strtotime($r['submitted_at'])) ?> | 
                        Time Limit: <?= $r['time_limit'] ?> min
                    </p>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:2rem;font-weight:700;color:<?= $pct>=70?'var(--success)':($pct>=40?'var(--warning)':'var(--danger)') ?>">
                        <?= $r['score'] ?>/<?= $r['total_points'] ?>
                    </div>
                    <div style="font-size:0.875rem;color:#64748b;"><?= $pct ?>%</div>
                </div>
            </div>
            <?php if ($r['tab_switch_count'] > 0 || $r['copy_paste_count'] > 0): ?>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);font-size:0.875rem;color:#dc2626;">
                ⚠️ Violations: <?= $r['tab_switch_count'] ?> tab switches, <?= $r['copy_paste_count'] ?> copy/paste attempts
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>