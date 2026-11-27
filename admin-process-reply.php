<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: Ensure only logged-in staff can process replies
if (!isset($_SESSION['is_staff']) || $_SESSION['is_staff'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);
$admin_id = $_SESSION['user_id']; // The ID of the logged-in staff member
$target_user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$message = isset($data['message']) ? trim($data['message']) : "";

if ($target_user_id > 0 && $message !== "") {
    try {
        // 3. Insert the Admin's message into the chats table
        // We use 'official' as the sender_type so it styled correctly on the user side
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, sender_type, created_at) VALUES (?, ?, 'official', NOW())");
        $stmt->execute([$target_user_id, $message]);

        // 4. CRITICAL: Reset the Admin's typing status to 0
        // This ensures the "Agent is typing" indicator disappears for the user
        $upd = $pdo->prepare("UPDATE users SET is_typing = 0 WHERE id = ?");
        $upd->execute([$admin_id]);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
