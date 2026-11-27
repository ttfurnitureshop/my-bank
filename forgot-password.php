<?php
require 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            $error = "No account found with that email.";
        } else {
            $code = generateCode();
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $stmt = $pdo->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE email = ?");
            $stmt->execute([$code, $expires, $email]);

            $body = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #6b46c1;'>Reset Your MyBank Password</h2>
                <p>Your password reset code is:</p>
                <h1 style='font-size: 36px; letter-spacing: 8px; color: #000;'><strong>$code</strong></h1>
                <p>This code expires in 15 minutes.</p>
                <p style='color: #666; font-size: 14px;'>If you did not request a password reset, please ignore this email.</p>
            </body>
            </html>";

            if (sendEmail($email, "MyBank - Password Reset Code", $body)) {
                $_SESSION['reset_email'] = $email;
                $success = "A reset code has been sent to your email.";
            } else {
                $error = "Failed to send reset email. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center text-white">
    <div class="bg-slate-900 p-8 rounded-2xl w-full max-w-md border border-slate-700 shadow-2xl">
        <h1 class="text-3xl font-bold text-center mb-6 text-violet-400">Forgot Password?</h1>
        <p class="text-center text-slate-300 mb-8">Enter your email to receive a reset code.</p>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-700 text-red-200 p-4 rounded-xl mb-6 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-200 p-6 rounded-xl mb-6 text-center">
                <?= $success ?><br><br>
                <a href="reset-password.php" class="underline font-medium text-emerald-300 hover:text-emerald-200">
                    Enter Reset Code →
                </a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-slate-400 mb-2 font-medium">Email Address</label>
                    <input type="email" name="email" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:outline-none focus:border-violet-500 transition">
                </div>
                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-4 rounded-xl transition">
                    Send Reset Code
                </button>
            </form>
        <?php endif; ?>

        <p class="text-center mt-6 text-slate-400">
            <a href="login.php" class="text-violet-400 hover:text-violet-300">Back to Login</a>
        </p>
    </div>
</body>
</html>