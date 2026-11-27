<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['pending_user_id'];
$error = '';
$success_msg = '';

function sendVerificationEmail($email, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.sanmarcosinstituto.edu.pe'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'Mybank@sanmarcosinstituto.edu.pe';
        $mail->Password   = 'Aj@nt246Aj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('Mybank@sanmarcosinstituto.edu.pe', 'MyBank Security');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your MyBank Verification Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; background: #0f172a; color: white; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #8b5cf6;'>Identity Verification</h2>
                <p>Use the code below to access your account:</p>
                <div style='background: #1e293b; padding: 15px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #a78bfa; border: 1px solid #4c1d95;'>
                    $code
                </div>
                <p style='font-size: 12px; color: #94a3b8; margin-top: 20px;'>This code expires in 15 minutes.</p>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- HANDLE RESEND REQUEST ---
if (isset($_GET['resend'])) {
    // Generate as STRING to preserve leading zeros
    $newCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry  = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();

    if ($userEmail) {
        $update = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
        $update->execute([$newCode, $expiry, $userId]);
        
        if (sendVerificationEmail($userEmail, $newCode)) {
            header("Location: email-confirmation.php?sent=1");
            exit;
        } else {
            $error = "Failed to send email. Please try again later.";
        }
    }
}

if (isset($_GET['sent'])) {
    $success_msg = "A new code has been sent to your inbox.";
}

// --- HANDLE VERIFICATION SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codeInput = trim($_POST['code'] ?? '');

    if (strlen($codeInput) !== 6 || !ctype_digit($codeInput)) {
        $error = "Please enter a valid 6-digit code.";
    } else {
        try {
            // Select code specifically as a string
            $stmt = $pdo->prepare("SELECT id, username, full_name, verification_code, verification_expires 
                                   FROM users 
                                   WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                // Precise string comparison to avoid integer conversion issues
                if ($user['verification_code'] !== $codeInput) {
                    $error = "The code you entered is incorrect.";
                } elseif (strtotime($user['verification_expires']) < time()) {
                    $error = "Code expired. Click 'Resend' below.";
                } else {
                    // Success: Verify user
                    $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                    $update->execute([$user['id']]);

                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['is_staff']  = (isset($_SESSION['pending_role']) && $_SESSION['pending_role'] === 'admin');

                    unset($_SESSION['pending_user_id']);
                    unset($_SESSION['pending_role']);

                    if ($_SESSION['is_staff']) {
                        header("Location: admin_chat.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            } else {
                $error = "User session expired. Please login again.";
            }
        } catch (PDOException $e) {
            $error = "System error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center text-white p-4">
    <div class="bg-slate-900 p-8 rounded-2xl w-full max-w-md border border-slate-700 shadow-2xl">
        <h1 class="text-3xl font-bold text-center mb-4 text-violet-400">Confirm Email</h1>
        <p class="text-center text-slate-400 mb-8">We sent a 6-digit code to your inbox. Enter it below to continue.</p>

        <?php if ($error): ?>
            <div class="bg-red-900/40 border border-red-500 text-red-200 p-3 rounded-lg mb-6 text-sm text-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="bg-emerald-900/40 border border-emerald-500 text-emerald-200 p-3 rounded-lg mb-6 text-sm text-center">
                <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-6">
                <input type="text" name="code" required maxlength="6" autofocus placeholder="000000"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 text-center text-3xl tracking-[10px] focus:outline-none focus:border-violet-500 transition shadow-inner">
            </div>
            <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-4 rounded-xl transition shadow-lg active:scale-[0.98]">
                Verify & Access Account
            </button>
        </form>

        <div class="mt-8 text-center space-y-4">
            <p class="text-sm text-slate-500">
                Didn't get the code? 
                <a href="?resend=1" class="text-violet-400 hover:underline">Resend Email</a>
            </p>
            <a href="login.php" class="block text-slate-500 hover:text-white text-sm transition">
                &larr; Back to Login
            </a>
        </div>
    </div>
</body>
</html>
