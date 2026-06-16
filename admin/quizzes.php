<?php
require '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $time = intval($_POST['time_limit'] ?? 30);
        $start = $_POST['start_time'] ?: null;
        $end = $_POST['end_time'] ?: null;
        $shuffle = isset($_POST['shuffle']) ? 1 : 0;
        $multi = isset($_POST['allow_multiple']) ? 1 : 0;
        $maxTabs = intval($_POST['max_tab_switches'] ?? 3);
        
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO quiz_sets (title, description, time_limit, start_time, end_time, shuffle_questions, allow_multiple_attempts, max_tab_switches, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $time, $start, $end, $shuffle, $multi, $maxTabs, $_SESSION['user_id']]);
            $quizId = $pdo->lastInsertId();
            
            // Add selected questions
            if (!empty($_POST['question_ids']) && is_array($_POST['question_ids'])) {
                $order = 0;
                foreach ($_POST['question_ids'] as $qid) {
                    $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_id, question_order) VALUES (?, ?, ?)")->execute([$quizId, intval($qid), $order++]);
                }
            }
            $message = 'Quiz created successfully.';
        }
    } elseif ($action === 'status' && !empty($_POST['quiz_id'])) {
        $status = $_POST['status'] ?? 'draft';
        $pdo->prepare("UPDATE quiz_sets SET status = ? WHERE id = ?")->execute([$status, intval($_POST['quiz_id'])]);
        $message = 'Status updated.';
    }
}

$quizzes = $pdo->query("SELECT q.*, COUNT(qq.id) as q_count FROM quiz_sets q LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id GROUP BY q.id ORDER BY q.created_at DESC")->fetchAll();
$allQuestions = $pdo->query("SELECT * FROM questions WHERE status = 1 ORDER BY category, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Quizzes</title>
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
            <a href="quizzes.php" class="active">Quizzes</a>
            <a href="assign.php">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Manage Quizzes</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-header">Create New Quiz</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" value="30" min="1" class="form-control" style="width:120px">
                </div>
                <div class="form-group">
                    <label>Start Time (optional)</label>
                    <input type="datetime-local" name="start_time" class="form-control">
                </div>
                <div class="form-group">
                    <label>End Time (optional)</label>
                    <input type="datetime-local" name="end_time" class="form-control">
                </div>
                <div class="form-group">
                    <label>Max Tab Switches Allowed</label>
                    <input type="number" name="max_tab_switches" value="3" min="0" class="form-control" style="width:120px">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="shuffle" checked> Shuffle Questions</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="allow_multiple"> Allow Multiple Attempts</label>
                </div>
                
                <div class="form-group">
                    <label>Select Questions</label>
                    <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border);padding:1rem;border-radius:6px;">
                        <?php 
                        $currentCat = '';
                        foreach ($allQuestions as $q): 
                            if ($q['category'] !== $currentCat) {
                                $currentCat = $q['category'];
                                echo '<h4 style="margin:0.75rem 0 0.5rem;color:var(--primary);font-size:0.875rem;">'.sanitize($currentCat).'</h4>';
                            }
                        ?>
                        <label style="display:block;margin-bottom:0.5rem;cursor:pointer;">
                            <input type="checkbox" name="question_ids[]" value="<?= $q['id'] ?>">
                            <?= sanitize(substr($q['question_text'], 0, 100)) ?>... (<?= $q['points'] ?> pts)
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Quiz</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">All Quizzes</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Title</th><th>Questions</th><th>Time</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $q): ?>
                        <tr>
                            <td><?= sanitize($q['title']) ?></td>
                            <td><?= $q['q_count'] ?></td>
                            <td><?= $q['time_limit'] ?> min</td>
                            <td><span class="badge badge-<?= $q['status'] === 'published' ? 'green' : ($q['status'] === 'closed' ? 'red' : 'yellow') ?>"><?= $q['status'] ?></span></td>
                            <td><?= date('Y-m-d', strtotime($q['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline">
                                        <option value="draft" <?= $q['status']==='draft'?'selected':'' ?>>Draft</option>
                                        <option value="published" <?= $q['status']==='published'?'selected':'' ?>>Published</option>
                                        <option value="closed" <?= $q['status']==='closed'?'selected':'' ?>>Closed</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>