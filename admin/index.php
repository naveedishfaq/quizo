<?php
require '../config.php';

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$totalQuizzes = $pdo->query("SELECT COUNT(*) FROM quiz_sets")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalAttempts = $pdo->query("SELECT COUNT(*) FROM attempts WHERE status != 'in_progress'")->fetchColumn();

$recentAttempts = $pdo->query("
    SELECT a.*, u.name as user_name, q.title as quiz_title 
    FROM attempts a 
    JOIN users u ON a.user_id = u.id 
    JOIN quiz_sets q ON a.quiz_id = q.id 
    WHERE a.status != 'in_progress'
    ORDER BY a.submitted_at DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Quiz Admin</h2>
            <small><?= sanitize($_SESSION['email']) ?></small>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="active">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="groups.php">Groups</a>
            <a href="questions.php">Questions</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="assign.php">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $totalUsers ?></h3>
                <p>Employees</p>
            </div>
            <div class="stat-card">
                <h3><?= $totalQuizzes ?></h3>
                <p>Quizzes</p>
            </div>
            <div class="stat-card">
                <h3><?= $totalQuestions ?></h3>
                <p>Questions</p>
            </div>
            <div class="stat-card">
                <h3><?= $totalAttempts ?></h3>
                <p>Completed</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Recent Submissions</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Employee</th><th>Quiz</th><th>Score</th><th>Status</th><th>Submitted</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAttempts as $row): ?>
                        <tr>
                            <td><?= sanitize($row['user_name']) ?></td>
                            <td><?= sanitize($row['quiz_title']) ?></td>
                            <td><?= $row['score'] ?>/<?= $row['total_points'] ?></td>
                            <td><span class="badge badge-<?= $row['status'] === 'submitted' ? 'green' : 'yellow' ?>"><?= $row['status'] ?></span></td>
                            <td><?= $row['submitted_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>