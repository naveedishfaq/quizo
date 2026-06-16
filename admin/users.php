<?php
require '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'] ?? 'password123', PASSWORD_DEFAULT);
        $group_id = intval($_POST['group_id'] ?? 0) ?: null;
        $role = $_POST['role'] ?? 'employee';
        
        if ($name && $email) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, group_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $role, $group_id]);
                $message = 'User created successfully.';
            } catch (PDOException $e) {
                $message = 'Error: Email may already exist.';
            }
        }
    } elseif ($action === 'delete' && !empty($_POST['user_id'])) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([intval($_POST['user_id'])]);
        $message = 'User deleted.';
    }
}

$users = $pdo->query("SELECT u.*, g.name as group_name FROM users u LEFT JOIN groups_tbl g ON u.group_id = g.id WHERE u.role = 'employee' ORDER BY u.created_at DESC")->fetchAll();
$groups = $pdo->query("SELECT * FROM groups_tbl ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Quiz Admin</h2></div>
        <div class="sidebar-menu">
            <a href="index.php">Dashboard</a>
            <a href="users.php" class="active">Users</a>
            <a href="groups.php">Groups</a>
            <a href="questions.php">Questions</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="assign.php">Assignments</a>
            <a href="results.php">Live Results</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Manage Users</h1>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-header">Add New User</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Password (default: password123 if empty)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>Group</label>
                    <select name="group_id" class="form-control">
                        <option value="0">-- No Group --</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= sanitize($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">All Employees</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Group</th><th>Status</th><th>Created</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= sanitize($u['name']) ?></td>
                            <td><?= sanitize($u['email']) ?></td>
                            <td><?= sanitize($u['group_name'] ?? 'None') ?></td>
                            <td><span class="badge badge-<?= $u['status'] ? 'green' : 'red' ?>"><?= $u['status'] ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
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