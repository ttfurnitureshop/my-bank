<?php
/**
 * MYBANK LOGIN PAGE - UPDATED WITH GOOGLE INTEGRATION & CALLBACK REDIRECT
 * Functions are now pulled from config.php to prevent redeclaration errors.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// config.php now handles the DB connection AND the sendEmail/generateCode functions
require 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

// Check if redirected back from google-auth.php with an error
if (isset($_GET['error']) && $_GET['error'] === 'usernotfound') {
    $error = "No MyBank account found for this Google email. Please sign up below.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // UPDATED: Added 'role' to the SELECT statement
        $stmt = $pdo->prepare("SELECT id, password, role, suspended_until, suspension_reason FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // === SUSPENSION CHECK ===
            $now = date('Y-m-d H:i:s');
            if (!empty($user['suspended_until']) && $user['suspended_until'] > $now) {
                $reason = $user['suspension_reason'] ? ": " . htmlspecialchars($user['suspension_reason']) : ".";
                $error = "Account suspended until " . date('M j, g:i A', strtotime($user['suspended_until'])) . $reason;
            } 
            // === PASSWORD VERIFICATION ===
            elseif (password_verify($password, $user['password'])) {
                
                // These functions MUST exist in your config.php
                $code = generateCode(); 
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
                $stmt->execute([$code, $expires, $user['id']]);

                // Email Template
                $body = "
                <html>
                <body style='font-family: Arial, sans-serif; background-color: #0f172a; padding: 20px; color: #ffffff;'>
                    <div style='max-width: 400px; margin: auto; background-color: #1e293b; padding: 30px; border-radius: 15px; border: 1px solid #334155; text-align: center;'>
                        <h2 style='color: #a78bfa;'>Secure Login</h2>
                        <p style='color: #cbd5e1;'>Your 6-digit verification code is:</p>
                        <div style='background-color: #0f172a; padding: 20px; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #a78bfa; border-radius: 10px; margin: 20px 0;'>
                            $code
                        </div>
                        <p style='color: #94a3b8; font-size: 12px;'>Expires in 15 minutes.</p>
                    </div>
                </body>
                </html>";

                if (sendEmail($email, "Your Login Code", $body)) {
                    $_SESSION['pending_user_id'] = $user['id'];
                    // NEW: Store the role temporarily until they confirm the 2FA code
                    $_SESSION['pending_role'] = $user['role']; 
                    header("Location: email-confirmation.php");
                    exit;
                } else {
                    $error = "Mail delivery failed. Please check server configuration.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Secure Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center text-white relative px-4">

    <div class="bg-slate-900 p-8 sm:p-10 rounded-2xl w-full max-w-md border border-slate-700 shadow-2xl z-10">
        <h1 class="text-4xl font-bold text-center mb-8 text-violet-400">MyBank</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-700 text-red-200 p-4 rounded-xl mb-6 text-center text-sm">
                <i class="fa-solid fa-circle-exclamation mr-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="mb-6">
                <label class="block text-slate-400 mb-2 font-medium">Email Address</label>
                <input type="email" name="email" id="emailInput" required autocomplete="email"
                       placeholder="name@example.com"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:outline-none focus:border-violet-500 transition">
            </div>

            <div class="mb-6">
                <label class="block text-slate-400 mb-2 font-medium">Password</label>
                <input type="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••"
                       class="w-full bg-slate-800 border border-slate-700 rounded-xl p-4 focus:outline-none focus:border-violet-500 transition">
            </div>

            <button type="submit" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-4 rounded-xl transition duration-200 shadow-lg mb-6">
                Sign In
            </button>
        </form>

        <div class="relative flex py-5 items-center">
            <div class="flex-grow border-t border-slate-700"></div>
            <span class="flex-shrink mx-4 text-slate-500 text-xs uppercase tracking-widest">Or continue with</span>
            <div class="flex-grow border-t border-slate-700"></div>
        </div>

        <div class="flex justify-center mt-2">
            <div id="g_id_onload"
                 data-client_id="953569836924-s37ovvhbnkfbn2ae0q2bnusg3etdfjit.apps.googleusercontent.com"
                 data-context="signin"
                 data-ux_mode="POST"
                 data-login_uri="https://sanmarcosinstituto.edu.pe/google-auth.php"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="pill"
                 data-theme="filled_blue"
                 data-text="signin_with"
                 data-size="large"
                 data-logo_alignment="left"
                 data-width="320">
            </div>
        </div>

        <div class="text-center mt-8 text-slate-400 text-sm">
            <!-- This link now carries the email automatically -->
            <a href="forgot-password.php" id="forgotLink" class="text-violet-400 hover:text-violet-300 font-medium underline">
                Forgot password?
            </a>
        </div>

        <div class="text-center mt-4 text-slate-400 text-sm">
            New to MyBank? 
            <a href="signup.php" class="text-violet-400 hover:text-violet-300 font-medium underline">
                Create Account
            </a>
        </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-800 py-4 z-50">
        <div class="max-w-md mx-auto flex justify-around text-[10px] uppercase tracking-wider text-slate-500">
            <a href="#" class="flex flex-col items-center text-violet-400">
                <i class="fa-solid fa-right-to-bracket text-xl mb-1"></i>
                <span>Login</span>
            </a>
            <a href="support-login.php" class="flex flex-col items-center hover:text-white transition-colors">
                <i class="fa-solid fa-headset text-xl mb-1"></i>
                <span>Support</span>
            </a>
            <a href="terms.php" class="flex flex-col items-center hover:text-white transition-colors">
                <i class="fa-solid fa-file-shield text-xl mb-1"></i>
                <span>Privacy</span>
            </a>
        </div>
    </div>

    <script>
        // Automatically pass the typed email to the forgot-password page
        document.getElementById('forgotLink').addEventListener('click', function(e) {
            const email = document.getElementById('emailInput').value.trim();
            if (email) {
                e.preventDefault();
                window.location.href = 'forgot-password.php?email=' + encodeURIComponent(email);
            }
        });
    </script>

</body>
</html>