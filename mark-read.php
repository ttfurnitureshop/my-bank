<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Option 1: Mark ALL notifications as read
if (isset($_POST['mark_all']) && $_POST['mark_all'] == 'true') {
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET read_at = NOW() 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $success = $stmt->execute([$user_id]);
    
    echo json_encode(['success' => $success]);
    exit;
}

// Option 2: Mark a specific notification as read (by ID)
if (isset($_POST['transaction_id'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET read_at = NOW() 
        WHERE id = ? AND user_id = ? AND read_at IS NULL
    ");
    $success = $stmt->execute([$transaction_id, $user_id]);
    
    echo json_encode(['success' => $success]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'No action specified']);