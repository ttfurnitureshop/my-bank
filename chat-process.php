<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['is_staff']) && $_SESSION['is_staff'] == 1;
$sender_type = $is_admin ? 'admin' : 'user';

// HANDLE FILE UPLOAD
if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
    
    $fileExtension = pathinfo($_FILES['chat_file']['name'], PATHINFO_EXTENSION);
    $newFileName = bin2hex(random_bytes(8)) . '.' . $fileExtension;
    $targetFile = $uploadDir . $newFileName;

    if (move_uploaded_file($_FILES['chat_file']['tmp_name'], $targetFile)) {
        // We save the EXACT HTML string needed to render the image
        $imgTag = "<img src='uploads/".$newFileName."' class='max-w-xs rounded-lg shadow-md my-2' />";
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, sender_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $imgTag, $sender_type]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// HANDLE TEXT MESSAGE
$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['message'])) {
    $msg = trim($input['message']);
    if ($msg !== "") {
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, sender_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $msg, $sender_type]);
        echo json_encode(['status' => 'success']);
    }
}
