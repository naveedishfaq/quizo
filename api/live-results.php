<?php
require '../config.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Unauthorized');
}

$quizFilter = intval($_GET['quiz_id'] ?? 0);

$sql = "
    SELECT a.*, u.name as user_name, u.email, q.title as quiz_title 
    FROM attempts a 
    JOIN users u ON a.user_id = u.id 
    JOIN quiz_sets q ON a.quiz_id = q.id 
    WHERE a.status != 'in_progress'
";
if ($quizFilter) {
    $sql .= " AND a.quiz_id = " . intval($quizFilter);
}
$sql .= " ORDER BY a.submitted_at DESC LIMIT 100";

$results = $pdo->query($sql)->fetchAll();

// Return just the table body HTML for the JS refresh
if (empty($results)) {
    echo '<tr><td colspan="7" style="text-align:center;padding:2rem;">No submissions yet</td></tr>';
    exit;
}

foreach ($results as $r) {
    $badgeClass = $r['status'] === 'submitted' ? 'green' : ($r['status'] === 'auto_submitted' ? 'red' : 'yellow');
    $badgeText = $r['status'] === 'auto_submitted' ? 'Auto-Submitted' : $r['status'];
?>
<tr>
    <td>
        <?= htmlspecialchars($r['user_name']) ?><br>
        <small style="color:#64748b;"><?= htmlspecialchars($r['email']) ?></small>
    </td>
    <td><?= htmlspecialchars($r['quiz_title']) ?></td>
    <td><strong><?= $r['score'] ?>/<?= $r['total_points'] ?></strong></td>
    <td><?= $r['tab_switch_count'] ?></td>
    <td><?= $r['copy_paste_count'] ?></td>
    <td><span class="badge badge-<?= $badgeClass ?>"><?= $badgeText ?></span></td>
    <td><?= $r['submitted_at'] ? date('H:i:s', strtotime($r['submitted_at'])) : '-' ?></td>
</tr>
<?php
}
?>