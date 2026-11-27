<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['is_staff']) || $_SESSION['is_staff'] != 1) { 
    header("Location: login.php"); 
    exit; 
}

$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

$uStmt = $pdo->prepare("SELECT u.full_name, a.balance, a.account_number FROM users u LEFT JOIN accounts a ON u.id = a.user_id WHERE u.id = ?");
$uStmt->execute([$target_user_id]);
$user = $uStmt->fetch();

$tStmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$tStmt->execute([$target_user_id]);
$transactions = $tStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Support Console | <?= htmlspecialchars($user['full_name'] ?? 'Admin') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        html, body { height: 100dvh; background: #020617; overflow: hidden; margin: 0; }
        #chat-display::-webkit-scrollbar { width: 4px; }
        #chat-display::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        .audit-panel { transition: transform 0.3s ease-in-out; }
        .audit-hidden { transform: translateX(100%); }
        .dot { animation: blink 1.4s infinite both; }
        @keyframes blink { 0% { opacity: .2; } 20% { opacity: 1; } 100% { opacity: .2; } }
        #endModal { display: none; position: fixed; inset: 0; background: rgba(2, 6, 23, 0.95); backdrop-filter: blur(10px); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
    </style>
</head>
<body class="text-slate-100 flex overflow-hidden">

    <div id="endModal">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-3xl p-8 text-center shadow-2xl">
            <div class="w-16 h-16 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-circle-exclamation text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold mb-2 text-white">End Session?</h2>
            <p class="text-slate-500 text-sm mb-8 leading-relaxed">This will terminate the session and clear chat history for the user.</p>
            <div class="flex flex-col gap-3">
                <button onclick="confirmEndChat()" class="bg-red-600 hover:bg-red-500 text-white font-bold py-4 rounded-2xl transition active:scale-95 shadow-lg shadow-red-600/20">End Chat</button>
                <button onclick="hideEndModal()" class="bg-slate-800 text-slate-300 font-bold py-4 rounded-2xl transition active:scale-95">Cancel</button>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col border-r border-slate-800 relative">
        <header class="p-4 bg-slate-900 border-b border-slate-800 flex justify-between items-center z-10">
            <div class="flex items-center gap-3">
                <a href="admin_inbox.php" class="text-slate-500 hover:text-white"><i class="fa-solid fa-arrow-left"></i></a>
                <div>
                    <h1 class="font-bold text-[10px] tracking-widest uppercase text-violet-400">Support Console</h1>
                    <p class="text-[9px] text-slate-200 font-bold"><?= htmlspecialchars($user['full_name'] ?? 'Unknown User') ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="text-right mr-2">
                    <p class="text-[10px] text-emerald-500 font-mono font-bold">$<?= number_format($user['balance'] ?? 0, 2) ?></p>
                    <p class="text-[8px] text-slate-500 font-mono italic">#<?= htmlspecialchars($user['account_number'] ?? 'N/A') ?></p>
                </div>
                <button onclick="showEndModal()" class="bg-red-900/20 hover:bg-red-900/40 text-red-500 text-[9px] font-black px-4 py-2.5 rounded-xl border border-red-500/30 uppercase tracking-widest transition">
                    End Chat
                </button>
                <button onclick="toggleAudit()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-[9px] font-black px-4 py-2.5 rounded-xl border border-slate-700 uppercase tracking-widest transition">
                    <i class="fa-solid fa-list-check mr-1"></i> Audit
                </button>
            </div>
        </header>

        <main id="chat-display" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-950 bg-[url('https://www.transparenttextures.com/patterns/dark-matter.png')]"></main>
        
        <div id="typing-box" class="hidden px-6 py-2 bg-slate-900/50 border-t border-slate-800">
            <p class="text-[9px] font-black text-emerald-500 uppercase italic">User is typing<span class="dot">.</span><span class="dot">.</span><span class="dot">.</span></p>
        </div>

        <footer class="p-4 bg-slate-900 border-t border-slate-800">
            <form id="replyForm" class="flex gap-2 max-w-5xl mx-auto">
                <input type="text" id="replyInput" autocomplete="off" placeholder="Write official response..." 
                       class="flex-1 bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 text-sm text-white focus:outline-none focus:border-violet-500 shadow-inner transition-all">
                <button type="submit" class="bg-violet-600 text-white w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg active:scale-95 transition flex-none">
                    <i class="fa-solid fa-paper-plane text-lg"></i>
                </button>
            </form>
        </footer>
    </div>

    <div id="auditPanel" class="audit-panel w-80 bg-slate-900 flex flex-col border-l border-slate-800 fixed lg:relative right-0 top-0 h-full z-20 audit-hidden lg:audit-visible">
        <header class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
            <h2 class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Account Audit</h2>
            <button onclick="toggleAudit()" class="lg:hidden text-slate-500 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </header>
        
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <?php if (empty($transactions)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-slate-600 italic text-[10px] uppercase font-bold">No Records</div>
            <?php else: foreach($transactions as $t): ?>
                <div class="bg-slate-800/40 p-3 rounded-xl border border-slate-700 hover:border-violet-500/50 transition-all">
                    <div class="flex justify-between text-[10px] font-bold mb-1">
                        <span class="text-slate-300 truncate w-36"><?= htmlspecialchars($t['description']) ?></span>
                        <span class="<?= $t['amount'] < 0 ? 'text-red-400' : 'text-emerald-400' ?>">
                            <?= $t['amount'] < 0 ? '-' : '+' ?>$<?= number_format(abs($t['amount']), 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-[8px] font-bold">
                        <span class="text-slate-500 uppercase"><?= date('M d, H:i', strtotime($t['created_at'])) ?></span>
                        <span class="text-slate-400 px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800 uppercase"><?= htmlspecialchars($t['status'] ?? 'DONE') ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <script>
        const chatDisplay = document.getElementById('chat-display');
        const typingBox = document.getElementById('typing-box');
        const replyInput = document.getElementById('replyInput');
        const endModal = document.getElementById('endModal');
        let typingTimer;

        function toggleAudit() { document.getElementById('auditPanel').classList.toggle('audit-hidden'); }
        function showEndModal() { endModal.style.display = 'flex'; }
        function hideEndModal() { endModal.style.display = 'none'; }
        
        async function confirmEndChat() {
            await fetch('end-chat.php?user_id=<?= $target_user_id ?>', { method: 'POST' });
            window.location.href = 'admin_inbox.php';
        }

        async function fetchMessages() {
            try {
                const res = await fetch('fetch-messages.php?user_id=<?= $target_user_id ?>');
                const data = await res.json();
                if (chatDisplay.innerHTML !== data.html) {
                    chatDisplay.innerHTML = data.html;
                    chatDisplay.scrollTop = chatDisplay.scrollHeight;
                }
                typingBox.style.display = (data.is_typing == 1) ? 'block' : 'none';
            } catch (e) { }
        }

        replyInput.addEventListener('input', () => {
            fetch('update-typing.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ typing: 1 })
            });

            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                fetch('update-typing.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ typing: 0 })
                });
            }, 3000);
        });

        document.getElementById('replyForm').onsubmit = async (e) => {
            e.preventDefault();
            const msg = replyInput.value.trim();
            if(!msg) return;
            replyInput.value = '';
            
            clearTimeout(typingTimer);
            fetch('update-typing.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ typing: 0 })
            });

            await fetch('admin-process-reply.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ message: msg, user_id: <?= $target_user_id ?> })
            });
            fetchMessages();
        };

        setInterval(fetchMessages, 3000);
        fetchMessages();
    </script>
</body>
</html>
