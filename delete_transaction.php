<?php
session_start();
require 'config.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo 'error';
    exit;
}

$user_id = $_SESSION['user_id'];
$tx_id   = (int)($_POST['id'] ?? 0);

if ($tx_id <= 0) {
    echo 'error';
    exit;
}

try {
    $stmt = $pdo->prepare("
        DELETE FROM transactions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$tx_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo 'success';
    } else {
        echo 'error'; // Transaction not found or doesn't belong to user
    }

} catch (Exception $e) {
    error_log("Delete transaction error: " . $e->getMessage());
    echo 'error';
}