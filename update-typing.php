<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$data = json_decode(file_get_contents('php://input'), true);
$status = isset($data['typing']) ? intval($data['typing']) : 0;
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    // Updates the is_typing column shown in your database screenshot
    $stmt = $pdo->prepare("UPDATE users SET is_typing = ? WHERE id = ?");
    $stmt->execute([$status, $user_id]);
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
