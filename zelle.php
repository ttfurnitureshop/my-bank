<?php
/**
 * ZELLE TRANSFER PAGE - FULL UPDATE
 * Features: Professional Debit/Credit Email Alerts, Transaction Logging, and Security
 */

// 1. CONFIGURATION & ERROR HANDLING
require_once 'config.php'; 

// Force Africa/Lagos timezone
date_default_timezone_set('Africa/Lagos');

// Helper: always generate UTC timestamp for storage
function getUtcNow(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->format('Y-m-d H:i:s');
}

// Manual PHPMailer Load - This fixes the "failed to open stream" error
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1a. PHPMailer Function - Fixed with existence check to prevent redeclaration error
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'mail.sanmarcosinstituto.edu.pe'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'Mybank@sanmarcosinstituto.edu.pe'; 
            $mail->Password   = 'Aj@nt246Aj'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Recipients
            $mail->setFrom('Mybank@sanmarcosinstituto.edu.pe', 'MyBank Support');
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Prevents "White Page" by displaying errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$successMessage = "";
$errorMessage   = "";

// 3. FETCH SENDER DATA
try {
    $stmt = $pdo->prepare("
        SELECT u.email, u.full_name, COALESCE(SUM(a.balance), 0) AS total_balance
        FROM users u
        LEFT JOIN accounts a ON u.id = a.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $senderName    = $user['full_name'] ?? 'Valued Customer';
    $senderEmail   = $user['email'] ?? '';
    $senderBalance = (float)($user['total_balance'] ?? 0);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 4. TRANSACTION PROCESSING
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $recipientEmail = trim($_POST['recipient'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $memo           = trim($_POST['memo'] ?? 'Zelle Payment');

    if ($amount <= 0) {
        $errorMessage = "Please enter a valid amount.";
    } elseif ($amount > $senderBalance) {
        $errorMessage = "Insufficient funds.";
    } elseif (empty($recipientEmail)) {
        $errorMessage = "Recipient email is required.";
    } else {
        $createdAt = getUtcNow();          // ← FIXED
        $date_now  = $createdAt;
        $txId      = strtoupper(uniqid('ZEL-')); 

        try {
            $pdo->beginTransaction();

            // Find Receiver in local DB
            $stmt_r = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email = ? LIMIT 1");
            $stmt_r->execute([$recipientEmail]);
            $receiver = $stmt_r->fetch();

            // Deduct from Sender
            $deduct = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE user_id = ? AND balance >= ? LIMIT 1");
            $deduct->execute([$amount, $user_id, $amount]);

            if ($deduct->rowCount() === 0) throw new Exception("Transaction failed: Check balance.");

            // Credit Receiver (If internal user)
            if ($receiver) {
                $credit = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE user_id = ? LIMIT 1");
                $credit->execute([$amount, $receiver['id']]);
            }

            // Log Transaction for History (Sender - Debit)
            $log = $pdo->prepare("
                INSERT INTO transactions (user_id, amount, description, type, created_at) 
                VALUES (?, ?, ?, 'debit', ?)
            ");
            $desc = "Zelle Transfer to $recipientEmail";
            $log->execute([$user_id, -$amount, $desc, $createdAt]);

            // Log credit for receiver if they are an internal user
            if ($receiver) {
                $log2 = $pdo->prepare("
                    INSERT INTO transactions (user_id, amount, description, type, created_at) 
                    VALUES (?, ?, ?, 'credit', ?)
                ");
                $desc2 = "Zelle Transfer from $senderName";
                $log2->execute([$receiver['id'], $amount, $desc2, $createdAt]);
            }

            $pdo->commit();

            // 5. SEND NOTIFICATION EMAILS
            if (function_exists('sendEmail')) {
                
                // --- SENDER ALERT (DEBIT) ---
                $s_body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden;'>
                    <div style='background:#6a1b9a;color:white;padding:25px;text-align:center;'><h2 style='margin:0;'>Zelle&reg; Payment Sent</h2></div>
                    <div style='padding:30px;background:#fff;'>
                        <p>Hello <strong>$senderName</strong>,</p>
                        <div style='background:#fdf2f2;padding:25px;border-radius:10px;text-align:center;margin:25px 0;border:1px solid #dcfce7;'>
                            <h1 style='color:#dc2626;margin:0;font-size:36px;'>-$" . number_format($amount, 2) . "</h1>
                            <p style='color:#991b1b;margin:5px 0 0;font-weight:bold;'>DEBIT</p>
                        </div>
                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;'>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>To:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$recipientEmail</td></tr>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Description:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>Zelle Transfer</td></tr>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Transaction ID:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$txId</td></tr>
                            <tr><td style='padding:12px 0;'><strong>Date:</strong></td><td style='padding:12px 0;text-align:right;'>$date_now</td></tr>
                        </table>
                    </div>
                </div>";
                sendEmail($senderEmail, "Debit Alert: Zelle Payment Sent", $s_body);

                // --- RECEIVER ALERT (CREDIT) ---
                if ($receiver) {
                    $r_name = htmlspecialchars($receiver['full_name']);
                    $r_body = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden;'>
                        <div style='background:#6a1b9a;color:white;padding:25px;text-align:center;'><h2 style='margin:0;'>Zelle&reg; Payment Received</h2></div>
                        <div style='padding:30px;background:#fff;'>
                            <p>Hello <strong>$r_name</strong>,</p>
                            <div style='background:#f0fdf4;padding:25px;border-radius:10px;text-align:center;margin:25px 0;border:1px solid #dcfce7;'>
                                <h1 style='color:#16a34a;margin:0;font-size:36px;'>+$" . number_format($amount, 2) . "</h1>
                                <p style='color:#166534;margin:5px 0 0;font-weight:bold;'>CREDIT</p>
                            </div>
                            <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;'>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>From:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$senderName</td></tr>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Description:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>Zelle Transfer Received</td></tr>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Transaction ID:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$txId</td></tr>
                                <tr><td style='padding:12px 0;'><strong>Memo:</strong></td><td style='padding:12px 0;text-align:right;'>" . ($memo ?: 'N/A') . "</td></tr>
                            </table>
                        </div>
                    </div>";
                    sendEmail($receiver['email'], "Credit Alert: Zelle Payment Received", $r_body);
                }
            }

            $successMessage = "Success! $" . number_format($amount, 2) . " sent to $recipientEmail";
            $senderBalance -= $amount;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMessage = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zelle&reg; Transfer - MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    
    <style>
        /* Loading Overlay Styles */
        #loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        #loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .spinner {
            width: 70px;
            height: 70px;
            border: 6px solid rgba(255,255,255,0.2);
            border-top-color: #8b5cf6;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .success-check {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: white;
            transform: scale(0);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .success-check.show {
            transform: scale(1);
        }
    </style>
</head>
<body class="bg-slate-950 text-white min-h-screen">

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="text-center">
            <div id="spinner" class="spinner mx-auto mb-4"></div>
            <div id="success-check" class="success-check mx-auto mb-4 hidden">
                <i class="fa-solid fa-check"></i>
            </div>
            <p id="loading-text" class="text-white text-lg font-medium">Processing...</p>
        </div>
    </div>

    <div class="bg-violet-700 p-6 text-center shadow-lg">
        <h1 class="text-2xl font-bold italic">Zelle<span class="text-sm align-top">&reg;</span></h1>
    </div>

    <div class="max-w-md mx-auto px-4 py-10">
        
        <?php if ($successMessage): ?>
            <div class="bg-green-900/40 border border-green-600 text-green-200 p-4 rounded-2xl mb-6 text-center shadow-lg">
                <i class="fa-solid fa-circle-check mr-2"></i> <?= $successMessage ?>
            </div>
            <script>
                // Show success animation then redirect
                document.addEventListener('DOMContentLoaded', function() {
                    const overlay = document.getElementById('loading-overlay');
                    const spinner = document.getElementById('spinner');
                    const successCheck = document.getElementById('success-check');
                    const loadingText = document.getElementById('loading-text');

                    // Show overlay
                    overlay.classList.add('show');
                    spinner.classList.remove('hidden');
                    successCheck.classList.add('hidden');
                    loadingText.textContent = "Processing...";

                    // After 2.5 seconds show success
                    setTimeout(() => {
                        spinner.classList.add('hidden');
                        successCheck.classList.remove('hidden');
                        successCheck.classList.add('show');
                        loadingText.textContent = "Transfer Successful!";
                        loadingText.classList.add('text-emerald-400');
                    }, 2500);

                    // After another 1.5 seconds redirect
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 4000);
                });
            </script>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="bg-red-900/40 border border-red-600 text-red-200 p-4 rounded-2xl mb-6 text-center shadow-lg">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $errorMessage ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-900 p-8 rounded-3xl border border-slate-800 shadow-2xl">
            <div class="text-center mb-10">
                <p class="text-slate-400 text-xs uppercase tracking-widest mb-1">Available Balance</p>
                <h2 class="text-4xl font-bold text-violet-400">$<?= number_format($senderBalance, 2) ?></h2>
            </div>

            <form method="POST" id="zelle-form" class="space-y-6">
                <div>
                    <label class="block text-slate-400 text-sm mb-2 font-medium">Recipient Email</label>
                    <input type="email" name="recipient" required placeholder="name@example.com"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:border-violet-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-slate-400 text-sm mb-2 font-medium">Amount</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-2xl text-slate-500 font-bold">$</span>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 pl-10 text-2xl font-bold focus:border-violet-500 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 text-sm mb-2 font-medium">Memo (Optional)</label>
                    <input type="text" name="memo" placeholder="What's this for?"
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:border-violet-500 outline-none transition">
                </div>

                <button type="submit" id="send-btn" class="w-full bg-violet-600 hover:bg-violet-700 py-5 rounded-2xl font-bold text-xl shadow-xl shadow-violet-900/20 transition transform active:scale-95">
                    Send Money
                </button>
            </form>
        </div>

        <div class="mt-8 text-center">
            <a href="index.php" class="text-slate-500 hover:text-violet-400 transition text-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i> Return to Dashboard
            </a>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black/75 flex items-center justify-center z-[9999] opacity-0 invisible transition-all duration-300">
        <div class="text-center">
            <div id="spinner" class="w-16 h-16 border-4 border-white/20 border-t-violet-500 rounded-full animate-spin mx-auto mb-4"></div>
            <div id="success-check" class="w-20 h-20 rounded-full bg-emerald-500 flex items-center justify-center text-4xl text-white mx-auto mb-4 scale-0 transition-transform duration-500 hidden">
                <i class="fa-solid fa-check"></i>
            </div>
            <p id="loading-text" class="text-white text-lg font-medium">Processing...</p>
        </div>
    </div>

<script>
// Show loading animation on form submit
document.querySelector('form').addEventListener('submit', function(e) {
    const overlay = document.getElementById('loading-overlay');
    const spinner = document.getElementById('spinner');
    const successCheck = document.getElementById('success-check');
    const loadingText = document.getElementById('loading-text');

    // Show overlay with spinner
    overlay.classList.remove('opacity-0', 'invisible');
    overlay.classList.add('opacity-100', 'visible');
    spinner.classList.remove('hidden');
    successCheck.classList.add('hidden');
    loadingText.textContent = "Processing...";
    loadingText.classList.remove('text-emerald-400');
});
</script>

</body>
</html>