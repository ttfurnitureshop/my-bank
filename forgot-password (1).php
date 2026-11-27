<?php
require 'config.php';

$error = '';
$success = '';
$email_for_resend = '';

// Pre-fill email if coming from login page
$prefilled_email = '';
if (isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    $prefilled_email = trim($_GET['email']);
}

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

            // Let MySQL set the expiry time (fixes timezone issues)
            $stmt = $pdo->prepare("UPDATE users SET reset_code = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email = ?");
            $stmt->execute([$code, $email]);

            // Professional HTML Email
            $body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin:0; padding:0; background-color:#f4f4f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 40px 0;">
                    <tr>
                        <td align="center">
                            <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                                
                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(135deg, #7c3aed, #6d28d9); padding: 32px 40px; text-align: center;">
                                        <h1 style="margin:0; color:#ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">MyBank</h1>
                                    </td>
                                </tr>

                                <!-- Body -->
                                <tr>
                                    <td style="padding: 40px 40px 32px;">
                                        <h2 style="margin:0 0 12px; color:#111827; font-size: 20px; font-weight: 600;">Password Reset Code</h2>
                                        <p style="margin:0 0 28px; color:#6b7280; font-size: 15px; line-height: 1.5;">
                                            Use the code below to reset your password. This code will expire in <strong>15 minutes</strong>.
                                        </p>

                                        <!-- Code Box -->
                                        <div style="background: #f5f3ff; border: 2px dashed #8b5cf6; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 28px;">
                                            <span style="font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #5b21b6; font-family: monospace;">
                                                ' . $code . '
                                            </span>
                                        </div>

                                        <p style="margin:0; color:#9ca3af; font-size: 13px; line-height: 1.5;">
                                            If you didn\'t request a password reset, you can safely ignore this email.
                                        </p>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background: #f9fafb; padding: 20px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                                        <p style="margin:0; color:#9ca3af; font-size: 12px;">
                                            © ' . date('Y') . ' MyBank. All rights reserved.
                                        </p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';

            if (sendEmail($email, "MyBank - Password Reset Code", $body)) {
                $_SESSION['reset_email'] = $email;
                $success = "A reset code has been sent to your email.";
                $email_for_resend = $email;
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
                <?= htmlspecialchars($success) ?><br><br>
                <a href="reset-password.php" class="underline font-medium text-emerald-300 hover:text-emerald-200">
                    Enter Reset Code →
                </a>
            </div>

            <!-- Resend Code Section -->
            <div class="text-center">
                <p class="text-slate-400 text-sm mb-3">Didn't receive the code?</p>
                
                <form method="POST" id="resendForm">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email_for_resend) ?>">
                    <button type="submit" id="resendBtn"
                            class="w-full bg-slate-700 hover:bg-slate-600 disabled:bg-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed text-white font-medium py-3 rounded-xl transition">
                        Resend Code
                    </button>
                </form>
                
                <p id="countdownText" class="text-slate-500 text-sm mt-3 hidden">
                    You can resend in <span id="countdown">20</span> seconds
                </p>
            </div>

            <script>
                (function() {
                    const resendBtn = document.getElementById('resendBtn');
                    const countdownText = document.getElementById('countdownText');
                    const countdownEl = document.getElementById('countdown');
                    let seconds = 20;

                    resendBtn.disabled = true;
                    countdownText.classList.remove('hidden');

                    const timer = setInterval(() => {
                        seconds--;
                        countdownEl.textContent = seconds;

                        if (seconds <= 0) {
                            clearInterval(timer);
                            resendBtn.disabled = false;
                            countdownText.classList.add('hidden');
                        }
                    }, 1000);
                })();
            </script>

        <?php else: ?>
            <form method="POST">
                <div class="mb-6">
                    <label class="block text-slate-400 mb-2 font-medium">Email Address</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($prefilled_email) ?>"
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