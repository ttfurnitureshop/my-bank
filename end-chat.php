<?php
require 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Delete messages for this user
    $stmt = $pdo->prepare("DELETE FROM chats WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Also reset admin typing status just in case
    $resetTyping = $pdo->prepare("UPDATE users SET is_typing = 0 WHERE role = 'admin'");
    $resetTyping->execute();
}
