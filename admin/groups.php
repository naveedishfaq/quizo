<?php
require '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO groups_tbl (name, description) VALUES (?, ?)")->execute([$name, $desc]);
            $message = 'Group created.';
        }
    } elseif ($action === 'delete' && !empty($_POST['group_id'])) {
        $pdo->prepare("DELETE FROM groups_tbl WHERE id = ?")->execute([intval($_POST['group_id'])]);
        $message = 'Group deleted.';
    }
}

$groups = $pdo->query("SELECT g.*, COUNT(u.id) as user_count FROM groups_tbl g LEFT JOIN users u ON g.id = u.group_id GROUP BY g.id ORDER BY g.name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Groups</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Quiz Admin</h2></div>
        <div class="sidebar-menu">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="groups.php" class="active">Groups</a>
            <a href="questions.php">Questions</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="assign.php">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Manage Groups</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-header">Create Group</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Group Name</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Group</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">All Groups</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Description</th><th>Members</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                        <tr>
                            <td><?= sanitize($g['name']) ?></td>
                            <td><?= sanitize($g['description'] ?? '-') ?></td>
                            <td><?= $g['user_count'] ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
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
</body>
</html>