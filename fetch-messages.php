<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$target_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : ($_SESSION['user_id'] ?? 0);

// 1. Get Messages
$stmt = $pdo->prepare("SELECT * FROM chats WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$target_id]);
$messages = $stmt->fetchAll();

$html = "";
foreach ($messages as $msg) {
    $isUser = ($msg['sender_type'] === 'user');
    $bg = $isUser ? 'bg-violet-600 text-white' : 'bg-slate-800 text-slate-200';
    $align = $isUser ? 'items-end' : 'items-start';
    
    // Logic to detect if the message is an image tag or raw text
    $messageContent = $msg['message'];
    // We only allow <img> tags through for security; everything else is escaped
    if (strpos($messageContent, '<img') === false) {
        $messageContent = htmlspecialchars($messageContent);
    }

    $html .= '
    <div class="flex flex-col '.$align.' mb-4 w-full px-2">
        <div class="p-3 rounded-2xl text-sm '.$bg.' shadow-sm max-w-[85%] break-words">
            '.$messageContent.'
        </div>
    </div>';
}

// 2. Typing Logic: Check for the Admin specifically
$tStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_typing = 1");
$tStmt->execute();
$admin_typing = ($tStmt->fetchColumn() > 0) ? 1 : 0;

header('Content-Type: application/json');
echo json_encode([
    'html' => $html, 
    'is_typing' => $admin_typing // This will trigger the indicator on the user's page
]);
