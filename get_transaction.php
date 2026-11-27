<?php
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['user_id'];
$txId   = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT id, description, amount, type, created_at, notes 
    FROM transactions 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$txId, $userId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    echo json_encode(['error' => 'Transaction not found or access denied']);
    exit;
}

echo json_encode($tx);
?>