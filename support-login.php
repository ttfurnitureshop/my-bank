<?php
require 'config.php';

// No session check - anyone can contact support from login page

$messageSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['full_name'] ?? '');
    $userEmail = trim($_POST['email'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    if (empty($name) || empty($userEmail) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $to      = "ajayitobijoshua@gmail.com";
        $subject = "MyBank Support Request (from Login Page) - " . htmlspecialchars($name);
        
        $body = "New Support Request from Login Page\n\n";
        $body .= "Name: " . htmlspecialchars($name) . "\n";
        $body .= "Email: " . htmlspecialchars($userEmail) . "\n";
        $body .= "Message:\n" . htmlspecialchars($message) . "\n\n";
        $body .= "Sent from: " . $_SERVER['HTTP_HOST'] . "\n";

        $headers = "From: " . $userEmail . "\r\n";
        $headers .= "Reply-To: " . $userEmail . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8";

        if (mail($to, $subject, $body, $headers)) {
            $messageSent = true;
        } else {
            $error = "Failed to send your message. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Contact Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #0f172a;
        }
        .support-container {
            background-color: #1e2937;
        }
    </style>
</head>
<body class="min-h-screen text-slate-100">

<div class="max-w-2xl mx-auto px-5 py-10">
    <div class="flex items-center gap-3 mb-10">
        <div class="w-12 h-12 bg-violet-600 rounded-2xl flex items-center justify-center text-4xl font-bold">M</div>
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Contact Support</h1>
            <p class="text-slate-400">We're here to help — even before you log in</p>
        </div>
    </div>

    <?php if ($messageSent): ?>
        <div class="support-container rounded-3xl p-10 text-center border border-emerald-500/30">
            <i class="fa-solid fa-check-circle text-6xl text-emerald-400 mb-6"></i>
            <h2 class="text-2xl font-semibold text-emerald-400 mb-3">Message Sent Successfully!</h2>
            <p class="text-slate-300 mb-8">Our support team will reply to you as soon as possible.</p>
            <a href="login.php" 
               class="inline-block px-10 py-4 bg-violet-600 hover:bg-violet-500 rounded-2xl font-medium transition">
                ← Back to Login
            </a>
        </div>
    <?php else: ?>
        <div class="support-container rounded-3xl p-8 border border-slate-700">
            <?php if ($error): ?>
                <div class="bg-red-900/60 border border-red-500 text-red-200 p-4 rounded-2xl mb-6 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Your Full Name</label>
                    <input type="text" name="full_name" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500 text-white">
                </div>

                <div>
                    <label class="block text-sm text-slate-400 mb-2">Email Address</label>
                    <input type="email" name="email" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500 text-white">
                </div>

                <div>
                    <label class="block text-sm text-slate-400 mb-2">How can we help you?</label>
                    <textarea name="message" rows="7" required
                              class="w-full bg-slate-800 border border-slate-700 rounded-3xl px-5 py-4 focus:outline-none focus:border-violet-500 text-white resize-y"
                              placeholder="Describe your issue or question..."></textarea>
                </div>

                <button type="submit"
                        class="w-full bg-violet-600 hover:bg-violet-500 py-4 rounded-2xl font-semibold text-lg transition text-white">
                    Send Message
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-800 py-4 text-center">
    <a href="login.php" class="text-violet-400 hover:text-white text-sm flex items-center justify-center gap-2">
        <i class="fa-solid fa-arrow-left"></i> Back to Login
    </a>
</div>

</body>
</html>