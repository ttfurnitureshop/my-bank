<?php
require 'config.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code      = trim($_POST['code'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = "Please enter a valid 6-digit code.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $password2) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users 
                               WHERE email = ? AND reset_code = ? AND reset_expires > NOW()");
        $stmt->execute([$_SESSION['reset_email'], $code]);
        $row = $stmt->fetch();

        if ($row) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$hash, $row['id']]);

            unset($_SESSION['reset_email']);
            $success = "Your password has been reset successfully.";
        } else {
            $error = "Invalid or expired reset code.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center text-white">
    <div class="bg-slate-900 p-8 rounded-2xl w-full max-w-md border border-slate-700 shadow-2xl">
        <h1 class="text-3xl font-bold text-center mb-6 text-violet-400">Reset Password</h1>

        <?php if ($success): ?>
            <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-200 p-6 rounded-xl mb-6 text-center">
                <?= $success ?><br><br>
                <a href="login.php" class="underline font-medium text-emerald-300 hover:text-emerald-200">
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-900/50 border border-red-700 text-red-200 p-4 rounded-xl mb-6 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-6">
                    <label class="block text-slate-400 mb-2 font-medium">Reset Code (6 digits)</label>
                    <input type="text" name="code" required maxlength="6" pattern="\d{6}"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-center tracking-widest focus:outline-none focus:border-violet-500 transition">
                </div>
                <div class="mb-6">
                    <label class="block text-slate-400 mb-2 font-medium">New Password</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:outline-none focus:border-violet-500 transition">
                </div>
                <div class="mb-8">
                    <label class="block text-slate-400 mb-2 font-medium">Confirm New Password</label>
                    <input type="password" name="password2" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:outline-none focus:border-violet-500 transition">
                </div>
                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-4 rounded-xl transition">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>

        <p class="text-center mt-6 text-slate-400">
            <a href="login.php" class="text-violet-400 hover:text-violet-300">Back to Login</a>
        </p>
    </div>
</body>
</html>