<?php
require '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $text = trim($_POST['question_text'] ?? '');
        $type = $_POST['question_type'] ?? 'mcq';
        $points = intval($_POST['points'] ?? 1);
        $category = trim($_POST['category'] ?? 'General');
        $options = null;
        $correct = trim($_POST['correct_answer'] ?? '');
        
        if ($type === 'mcq') {
            $opts = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')));
            $options = json_encode($opts);
        }
        
        if ($text) {
            $stmt = $pdo->prepare("INSERT INTO questions (question_text, question_type, options, correct_answer, points, category, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$text, $type, $options, $correct, $points, $category, $_SESSION['user_id']]);
            $message = 'Question added.';
        }
    } elseif ($action === 'delete' && !empty($_POST['question_id'])) {
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([intval($_POST['question_id'])]);
        $message = 'Question deleted.';
    }
}

$questions = $pdo->query("SELECT * FROM questions ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Questions</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function toggleFields() {
            const type = document.getElementById('qtype').value;
            document.getElementById('mcq-fields').style.display = type === 'mcq' ? 'block' : 'none';
            document.getElementById('correct-field').style.display = type === 'points' ? 'none' : 'block';
        }
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Quiz Admin</h2></div>
        <div class="sidebar-menu">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="groups.php">Groups</a>
            <a href="questions.php" class="active">Questions</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="assign.php">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Manage Questions</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-header">Add Question</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Question Type</label>
                    <select name="question_type" id="qtype" class="form-control" onchange="toggleFields()">
                        <option value="mcq">Multiple Choice (MCQ)</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="points">Points / Rating</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" required class="form-control"></textarea>
                </div>
                
                <div class="form-group" id="mcq-fields">
                    <label>Options (one per line)</label>
                    <textarea name="options" class="form-control" placeholder="Option A&#10;Option B&#10;Option C&#10;Option D"></textarea>
                </div>
                
                <div class="form-group" id="correct-field">
                    <label>Correct Answer / Model Answer</label>
                    <input type="text" name="correct_answer" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Points</label>
                    <input type="number" name="points" value="1" min="1" class="form-control" style="width:100px">
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="General" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Add Question</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Question Bank</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Type</th><th>Question</th><th>Category</th><th>Points</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?= $q['id'] ?></td>
                            <td><span class="badge badge-blue"><?= strtoupper($q['question_type']) ?></span></td>
                            <td><?= sanitize(substr($q['question_text'], 0, 80)) ?>...</td>
                            <td><?= sanitize($q['category']) ?></td>
                            <td><?= $q['points'] ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>toggleFields();</script>
</body>
</html>