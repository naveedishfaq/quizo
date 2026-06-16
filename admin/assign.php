<?php
require '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $quizId = intval($_POST['quiz_id'] ?? 0);
    $groupId = intval($_POST['group_id'] ?? 0);
    
    if ($quizId && $groupId) {
        try {
            $pdo->prepare("INSERT INTO quiz_assignments (quiz_id, group_id) VALUES (?, ?)")->execute([$quizId, $groupId]);
            $message = 'Quiz assigned to group.';
        } catch (PDOException $e) {
            $message = 'Already assigned to this group.';
        }
    }
}

if (!empty($_GET['delete'])) {
    $pdo->prepare("DELETE FROM quiz_assignments WHERE id = ?")->execute([intval($_GET['delete'])]);
    redirect('assign.php');
}

$assignments = $pdo->query("
    SELECT qa.id, q.title as quiz_title, g.name as group_name, qa.assigned_at 
    FROM quiz_assignments qa 
    JOIN quiz_sets q ON qa.quiz_id = q.id 
    JOIN groups_tbl g ON qa.group_id = g.id 
    ORDER BY qa.assigned_at DESC
")->fetchAll();

$quizzes = $pdo->query("SELECT * FROM quiz_sets WHERE status = 'published' ORDER BY title")->fetchAll();
$groups = $pdo->query("SELECT * FROM groups_tbl ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Assign Quizzes</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Quiz Admin</h2></div>
        <div class="sidebar-menu">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="groups.php">Groups</a>
            <a href="questions.php">Questions</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="assign.php" class="active">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Assign Quizzes to Groups</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-header">New Assignment</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-group">
                    <label>Select Quiz</label>
                    <select name="quiz_id" required class="form-control">
                        <option value="">-- Choose Quiz --</option>
                        <?php foreach ($quizzes as $q): ?>
                        <option value="<?= $q['id'] ?>"><?= sanitize($q['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Group</label>
                    <select name="group_id" required class="form-control">
                        <option value="">-- Choose Group --</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= sanitize($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Assign Quiz</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Active Assignments</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Quiz</th><th>Assigned To</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td><?= sanitize($a['quiz_title']) ?></td>
                            <td><?= sanitize($a['group_name']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($a['assigned_at'])) ?></td>
                            <td><a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove assignment?')">Remove</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>