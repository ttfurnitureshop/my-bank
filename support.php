<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username  = $_SESSION['username']  ?? 'User';
$fullName  = $_SESSION['full_name'] ?? $username;
$email     = $_SESSION['email'] ?? '';

$messageSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['full_name'] ?? '');
    $userEmail = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($userEmail) || empty($message)) {
        $error = "All fields are required.";
    } else {
        $to      = "ajayitobijoshua@gmail.com";
        $subject = "Support Request - " . htmlspecialchars($name);
        $body = "Name: $name\nEmail: $userEmail\nMessage: $message";
        $headers = "From: $userEmail";

        if (mail($to, $subject, $body, $headers)) {
            $messageSent = true;
        } else {
            $error = "Failed to send message.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MyBank • Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen relative">

<div class="max-w-2xl mx-auto p-6 pt-12 pb-32">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-violet-600 rounded-2xl flex items-center justify-center text-3xl font-bold">M</div>
            <span class="text-3xl font-bold tracking-tighter">MyBank Support</span>
        </div>
        <a href="index.php" class="bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>

    <?php if ($messageSent): ?>
        <div class="bg-emerald-900/50 border border-emerald-500 rounded-3xl p-8 text-center">
            <i class="fa-solid fa-check-circle text-6xl text-emerald-400 mb-4"></i>
            <h2 class="text-2xl font-semibold text-emerald-400 mb-2">Sent!</h2>
            <p class="text-slate-300">We will email you shortly.</p>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded-2xl mb-6 text-center text-sm">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-900 rounded-3xl p-8 border border-slate-700 shadow-xl">
            <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3">
                <i class="fa-solid fa-envelope text-violet-400"></i> Contact Us
            </h2>
            <form method="POST" class="space-y-6">
                <input type="text" name="full_name" value="<?= htmlspecialchars($fullName) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:border-violet-500 outline-none" placeholder="Full Name">
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:border-violet-500 outline-none" placeholder="Email">
                <textarea name="message" rows="5" class="w-full bg-slate-800 border border-slate-700 rounded-3xl px-5 py-4 focus:border-violet-500 outline-none" placeholder="How can we help?"></textarea>
                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 py-4 rounded-2xl font-semibold transition">Send Message</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="fixed bottom-8 right-8 flex flex-col items-center gap-2">
    <a href="live-chat.php" class="group flex flex-col items-center gap-2">
        <div class="bg-violet-600 w-16 h-16 rounded-full flex items-center justify-center text-2xl shadow-2xl group-hover:scale-110 transition-transform border-2 border-violet-400 relative">
            <i class="fa-solid fa-comments text-white"></i>
            <span class="absolute top-0 right-0 w-4 h-4 bg-emerald-500 border-2 border-slate-950 rounded-full"></span>
        </div>
        <span class="bg-slate-800 text-[11px] px-4 py-1.5 rounded-full text-violet-300 font-bold shadow-lg border border-slate-700 tracking-wider">
            LIVE CHAT
        </span>
    </a>
</div>

</body>
</html>
