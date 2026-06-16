<?php
require '../config.php';

$quizFilter = intval($_GET['quiz_id'] ?? 0);
$quizzes = $pdo->query("SELECT * FROM quiz_sets ORDER BY title")->fetchAll();

$sql = "
    SELECT a.*, u.name as user_name, u.email, q.title as quiz_title 
    FROM attempts a 
    JOIN users u ON a.user_id = u.id 
    JOIN quiz_sets q ON a.quiz_id = q.id 
    WHERE a.status != 'in_progress'
";
if ($quizFilter) $sql .= " AND a.quiz_id = $quizFilter";
$sql .= " ORDER BY a.submitted_at DESC LIMIT 100";

$results = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Live Results</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<script>
    function refreshData() {
        fetch('../api/live-results.php?quiz_id=<?= $quizFilter ?>')
            .then(r => r.text())
            .then(html => {
                document.querySelector('#results-table tbody').innerHTML = html;
            });
    }
    setInterval(refreshData, 5000);
</script>
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
            <a href="assign.php">Assignments</a>
            <a href="results.php" class="active">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Results & Analytics</h1>
        
        <div class="card">
            <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Filter by Quiz</label>
                    <select name="quiz_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Quizzes</option>
                        <?php foreach ($quizzes as $q): ?>
                        <option value="<?= $q['id'] ?>" <?= $quizFilter==$q['id']?'selected':'' ?>><?= sanitize($q['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Submissions (Auto-refresh every 5s)</div>
            <div id="results-table" class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Quiz</th>
                            <th>Score</th>
                            <th>Tab Switches</th>
                            <th>Copy/Paste</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                        <tr>
                            <td><?= sanitize($r['user_name']) ?><br><small><?= sanitize($r['email']) ?></small></td>
                            <td><?= sanitize($r['quiz_title']) ?></td>
                            <td><strong><?= $r['score'] ?>/<?= $r['total_points'] ?></strong></td>
                            <td><?= $r['tab_switch_count'] ?></td>
                            <td><?= $r['copy_paste_count'] ?></td>
                            <td><span class="badge badge-<?= $r['status']==='submitted'?'green':'yellow' ?>"><?= $r['status'] ?></span></td>
                            <td><?= $r['submitted_at'] ? date('H:i:s', strtotime($r['submitted_at'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>