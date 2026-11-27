<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Support Session</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        html, body { height: 100dvh; margin: 0; padding: 0; overflow: hidden; background: #020617; width: 100%; position: fixed; }
        .chat-wrapper { display: flex; flex-direction: column; height: 100%; }
        #chat-container { flex: 1; overflow-y: auto; padding: 15px; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; }
        .chat-footer { flex-shrink: 0; background: #0f172a; border-top: 1px solid #1e293b; padding: 12px; padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
        #typing-indicator { display: none; padding: 8px 12px; color: #10b981; font-size: 10px; font-weight: 900; font-family: monospace; letter-spacing: 1px; }
        .dot { animation: blink 1.4s infinite both; }
        @keyframes blink { 0% { opacity: .2; } 20% { opacity: 1; } 100% { opacity: .2; } }
        #endModal { display: none; position: fixed; inset: 0; background: rgba(2, 6, 23, 0.95); backdrop-filter: blur(10px); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
    </style>
</head>
<body class="text-slate-100">
    <div id="endModal">
        <div class="bg-slate-900 border border-slate-800 w-full max-sm rounded-3xl p-8 text-center shadow-2xl">
            <div class="w-16 h-16 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-circle-exclamation text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold mb-2">End Support?</h2>
            <p class="text-slate-500 text-sm mb-8 leading-relaxed">This will terminate the session and clear history.</p>
            <div class="flex flex-col gap-3">
                <button onclick="confirmEndChat()" class="bg-red-600 hover:bg-red-500 text-white font-bold py-4 rounded-2xl transition active:scale-95">End Chat</button>
                <button onclick="hideEndModal()" class="bg-slate-800 text-slate-300 font-bold py-4 rounded-2xl transition active:scale-95">Cancel</button>
            </div>
        </div>
    </div>

    <div class="chat-wrapper">
        <header class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-900/90 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="text-slate-400 hover:text-white"><i class="fa-solid fa-arrow-left"></i></a>
                <h1 class="font-bold text-[10px] tracking-widest uppercase text-slate-300">Live Support</h1>
            </div>
            <div class="flex gap-2">
                <a href="dashboard.php" class="text-[9px] font-black bg-slate-800 text-slate-300 px-4 py-2.5 rounded-xl border border-slate-700 uppercase">Dashboard</a>
                <button onclick="showEndModal()" class="text-[9px] font-black bg-red-900/20 text-red-500 px-4 py-2.5 rounded-xl border border-red-500/30 uppercase">End Chat</button>
            </div>
        </header>

        <main id="chat-container">
            <div id="message-wrapper" class="flex flex-col gap-4 max-w-2xl mx-auto pb-4"></div>
            <div id="typing-indicator" class="max-w-2xl mx-auto uppercase">AGENT IS TYPING<span class="dot">.</span><span class="dot">.</span><span class="dot">.</span></div>
        </main>

        <footer class="chat-footer">
            <form id="chatForm" class="flex gap-2 max-w-2xl mx-auto items-center">
                <button type="button" onclick="document.getElementById('fileInput').click()" class="text-slate-500 hover:text-violet-400 p-2">
                    <i class="fa-solid fa-camera text-xl"></i>
                </button>
                <input type="file" id="fileInput" class="hidden" accept="image/*" onchange="uploadFile(this)">
                <input type="text" id="userInput" autocomplete="off" placeholder="Message..." class="flex-1 bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 text-sm text-white focus:outline-none">
                <button type="submit" class="bg-violet-600 text-white w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </footer>
    </div>

    <script>
        const container = document.getElementById('chat-container'), wrapper = document.getElementById('message-wrapper'), typingBox = document.getElementById('typing-indicator'), userInput = document.getElementById('userInput'), endModal = document.getElementById('endModal');
        let lastCount = 0;
        let typingTimer;

        function showEndModal() { endModal.style.display = 'flex'; }
        function hideEndModal() { endModal.style.display = 'none'; }
        async function confirmEndChat() { await fetch('end-chat.php', { method: 'POST' }); window.location.href = 'dashboard.php'; }

        async function uploadFile(input) {
            if (input.files && input.files[0]) {
                const formData = new FormData();
                formData.append('chat_file', input.files[0]);
                
                await fetch('chat-process.php', { 
                    method: 'POST', 
                    body: formData 
                });
                
                input.value = '';
                fetchMessages();
            }
        }

        async function fetchMessages() {
            try {
                const res = await fetch('fetch-messages.php');
                const data = await res.json();
                
                // Update chat window
                if (wrapper.innerHTML !== data.html) { 
                    wrapper.innerHTML = data.html; 
                    container.scrollTop = container.scrollHeight; 
                }

                // FIXED: Toggle indicator based on AGENT typing status
                // Assumes your fetch-messages.php returns admin_is_typing or similar
                if (data.admin_is_typing == 1 || data.is_typing == 1) {
                    typingBox.style.display = 'block';
                } else {
                    typingBox.style.display = 'none';
                }
            } catch (err) {}
        }

        // Send User typing status to Admin
        userInput.addEventListener('input', () => {
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

        document.getElementById('chatForm').onsubmit = async (e) => {
            e.preventDefault();
            const msg = userInput.value;
            if(!msg) return;
            userInput.value = '';
            
            await fetch('chat-process.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ message: msg }) 
            });
            
            // Immediately set typing to 0 after sending
            fetch('update-typing.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ typing: 0 }) 
            });
            
            fetchMessages();
        };

        setInterval(fetchMessages, 3000);
        fetchMessages();
    </script>
</body>
</html>
