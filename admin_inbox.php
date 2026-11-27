<?php
require 'config.php';
// Fetch all unique users who have sent messages, ordered by the latest message
$stmt = $pdo->query("SELECT DISTINCT user_id, MAX(created_at) as last_msg 
                     FROM chats 
                     GROUP BY user_id 
                     ORDER BY last_msg DESC");
$active_chats = $stmt->fetchAll();
?>

<div class="p-6 bg-slate-900 min-h-screen text-white">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Active Support Tickets</h1>
        <a href="logout.php" class="bg-red-600 hover:bg-red-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition uppercase tracking-wider">
            Logout
        </a>
    </div>

    <div class="grid gap-4">
        <?php foreach ($active_chats as $chat): ?>
            <a href="admin_chat.php?user_id=<?= $chat['user_id'] ?>" 
               class="block p-4 bg-slate-800 rounded-2xl hover:bg-slate-700 border border-slate-700 transition">
                <div class="flex justify-between items-center">
                    <span>Customer #<?= $chat['user_id'] ?></span>
                    <span class="text-xs text-slate-500"><?= $chat['last_msg'] ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
