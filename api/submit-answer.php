<?php
require '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false]);

$action = $_POST['action'] ?? '';
$attemptId = intval($_POST['attempt_id'] ?? 0);

if (!$attemptId) jsonResponse(['success' => false, 'message' => 'Invalid attempt']);

if ($action === 'violation') {
    $type = $_POST['type'] ?? '';
    $field = $type === 'tab' ? 'tab_switch_count = tab_switch_count + 1' : 'copy_paste_count = copy_paste_count + 1';
    $pdo->prepare("UPDATE attempts SET $field WHERE id = ?")->execute([$attemptId]);
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false]);
?>