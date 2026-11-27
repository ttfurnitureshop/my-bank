<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT id, email, password, is_verified, job_title, 
                       address_street1, address_street2, address_state, address_country, address_postal,
                       address_status, identity_status, identity_file_path
                       FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ───────────────────────────────────────────────
// Change Password
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = "All password fields are required.";
    } elseif (!password_verify($currentPass, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($newPass) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($newPass !== $confirmPass) {
        $error = "New passwords do not match.";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        $success = "Password changed successfully.";
    }
}

// ───────────────────────────────────────────────
// Email Verification
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verification_code'])) {
    if ($user['is_verified'] == 1) {
        $error = "Your email is already verified.";
    } else {
        $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_code_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
        $stmt->execute([$code, $userId]);

        $subject = "MyBank - Email Verification Code";
        $message = "Your verification code is: $code\n\nThis code expires in 15 minutes.";
        $headers = "From: no-reply@mybank.com";

        if (mail($user['email'], $subject, $message, $headers)) {
            $success = "Verification code sent to " . htmlspecialchars($user['email']);
        } else {
            $error = "Failed to send email. Please try again.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $enteredCode = $_POST['verification_code'] ?? '';
    $stmt = $pdo->prepare("SELECT verification_code FROM users WHERE id = ? AND verification_code_expires > NOW()");
    $stmt->execute([$userId]);
    $stored = $stmt->fetch();

    if ($stored && $stored['verification_code'] === $enteredCode) {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        $success = "Email verified successfully!";
        $user['is_verified'] = 1;
    } else {
        $error = "Invalid or expired code.";
    }
}

// ───────────────────────────────────────────────
// Update Full Home Address + Job Title
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $job_title   = trim($_POST['job_title'] ?? '');
    $street1     = trim($_POST['street1'] ?? '');
    $street2     = trim($_POST['street2'] ?? '');
    $state       = trim($_POST['state'] ?? '');
    $country     = trim($_POST['country'] ?? '');
    $postal      = trim($_POST['postal_code'] ?? '');

    $address_status = (!empty($street1) && !empty($country)) ? 'pending' : 'not_submitted';

    $stmt = $pdo->prepare("UPDATE users SET 
        job_title = ?, 
        address_street1 = ?, 
        address_street2 = ?, 
        address_state = ?, 
        address_country = ?, 
        address_postal = ?,
        address_status = ?
        WHERE id = ?");

    $stmt->execute([$job_title, $street1, $street2, $state, $country, $postal, $address_status, $userId]);
    $success = "Address submitted successfully and is now pending admin verification.";
}

// Refresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// ───────────────────────────────────────────────
// Identity Upload
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_identity'])) {
    if (isset($_FILES['identity_file']) && $_FILES['identity_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['identity_file'];
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "File is too large. Maximum size is 5MB.";
        } else {
            $uploadDir = "uploads/identity/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newName = "identity_" . $userId . "_" . time() . "." . $ext;
            $uploadPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                chmod($uploadPath, 0644);

                $stmt = $pdo->prepare("UPDATE users SET identity_file_path = ?, identity_status = 'pending' WHERE id = ?");
                $stmt->execute([$uploadPath, $userId]);

                $success = "Identity document uploaded successfully. It is now pending admin review.";
            } else {
                $error = "Failed to save the file. Check folder permissions.";
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// ───────────────────────────────────────────────
// Sign out a specific device (Trusted Devices)
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_out_device'])) {
    $deviceId = (int)($_POST['device_id'] ?? 0);

    if ($deviceId > 0) {
        $stmt = $pdo->prepare("SELECT id, ip_address, user_agent FROM login_history WHERE id = ? AND user_id = ?");
        $stmt->execute([$deviceId, $userId]);
        $dev = $stmt->fetch();

        if ($dev) {
            // Remove the device record
            $stmt = $pdo->prepare("DELETE FROM login_history WHERE id = ? AND user_id = ?");
            $stmt->execute([$deviceId, $userId]);

            $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';

            // If this is the current device → terminate the session completely
            if ($dev['ip_address'] === $current_ip) {
                session_unset();
                session_destroy();
                header("Location: login.php");
                exit;
            }

            $success = "Device signed out successfully.";
        } else {
            $error = "Device not found.";
        }
    }
}

// Log current device IP address into login_history (Trusted Devices)
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$stmt = $pdo->prepare("SELECT ip_address, login_time FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 1");
$stmt->execute([$userId]);
$last = $stmt->fetch();

$shouldLog = true;
if ($last) {
    $lastTime = strtotime($last['login_time']);
    if ($last['ip_address'] === $current_ip && (time() - $lastTime) < 3600) {
        $shouldLog = false;
    }
}

if ($shouldLog) {
    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, login_time) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $current_ip, $current_ua]);
}

// Get recent devices (now includes id for sign-out)
$stmt = $pdo->prepare("SELECT id, ip_address, user_agent, login_time FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
$stmt->execute([$userId]);
$devices = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- OneSignal Push Notifications -->
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async=""></script>
    <script>
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(function(OneSignal) {
            OneSignal.init({
                appId: "17013813-7039-4c43-aac1-c5582929b974",   // Replace with your actual OneSignal App ID
                allowLocalhostAsSecureOrigin: true
            });
        });
    </script>
    
    <style>
        .card { transition: all 0.3s ease; }
        .card:hover { transform: translateY(-4px); }
        .status-not { background-color: #374151; color: #9ca3af; }
        .status-pending { background-color: #78350f; color: #fcd34d; }
        .status-approved { background-color: #14532d; color: #86efac; }
        .status-rejected { background-color: #7f1d1d; color: #fda4af; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen text-white">

    <main class="max-w-4xl mx-auto mt-10 px-4 pb-12">
        <div class="bg-slate-900 rounded-3xl border border-slate-700 shadow-2xl p-8">

            <div class="flex justify-between items-center mb-10">
                <h1 class="text-4xl font-bold text-violet-400">Settings</h1>
                <a href="index.php" 
                   class="bg-violet-600 hover:bg-violet-700 px-6 py-3 rounded-2xl font-medium flex items-center gap-2 transition">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-900/70 border border-red-700 text-red-200 p-5 rounded-2xl mb-8"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-emerald-900/70 border border-emerald-700 text-emerald-200 p-5 rounded-2xl mb-8"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-8">

                <!-- Change Password -->
                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-7 card">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3">
                        <i class="fas fa-key text-violet-400"></i> Change Password
                    </h2>
                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="block text-slate-400 mb-2">Current Password</label>
                            <input type="password" name="current_password" required class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-2">New Password</label>
                            <input type="password" name="new_password" required minlength="6" class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                        </div>
                        <button type="submit" name="change_password" class="w-full bg-violet-600 hover:bg-violet-700 py-4 rounded-2xl font-bold transition">Update Password</button>
                    </form>
                </div>

                <!-- Email Verification -->
                <div class="bg-slate-800 border border-slate-700 rounded-2xl p-7 card">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3">
                        <i class="fas fa-envelope text-violet-400"></i> Email Verification
                    </h2>
                    <p class="text-slate-400 mb-4">Registered Email: <span class="font-medium text-white"><?= htmlspecialchars($user['email']) ?></span></p>

                    <?php if ($user['is_verified'] == 1): ?>
                        <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-300 p-6 rounded-2xl flex items-center gap-3">
                            <i class="fas fa-check-circle text-3xl"></i>
                            <p class="font-semibold">Email is Verified</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="mb-6">
                            <button type="submit" name="send_verification_code" class="w-full bg-amber-600 hover:bg-amber-700 py-4 rounded-2xl font-medium transition">Send Verification Code</button>
                        </form>
                        <form method="POST">
                            <label class="block text-slate-400 mb-2">Enter 6-digit code</label>
                            <input type="text" name="verification_code" maxlength="6" required 
                                   class="w-full bg-slate-900 border border-slate-600 rounded-xl p-5 text-center text-3xl tracking-widest focus:outline-none focus:border-violet-500">
                            <button type="submit" name="verify_code" class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700 py-4 rounded-2xl font-bold">Verify Email</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Trusted Devices -->
                <div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-2xl p-7 card">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center gap-3">
                        <i class="fas fa-shield-alt text-violet-400"></i> Trusted Devices
                    </h2>
                    <?php if (empty($devices)): ?>
                        <p class="text-slate-400 py-10 text-center">No login history yet.</p>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($devices as $device): ?>
                                <div class="bg-slate-900 rounded-xl p-5 border border-slate-700 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars(substr($device['user_agent'], 0, 60)) ?>...</p>
                                        <p class="text-xs text-slate-500">IP: <?= htmlspecialchars($device['ip_address']) ?></p>
                                        <p class="text-xs text-slate-400 mt-1"><?= date('M d, Y • H:i', strtotime($device['login_time'])) ?></p>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Sign out this device? This will terminate the session on that device.');">
                                        <input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>">
                                        <button type="submit" name="sign_out_device"
                                                class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-xl transition flex items-center gap-2">
                                            <i class="fas fa-sign-out-alt"></i> Sign out device
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Full Home Address -->
                <div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-2xl p-7 card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold flex items-center gap-3">
                            <i class="fas fa-home text-violet-400"></i> Full Home Address
                        </h2>
                        <?php
                        $addr_status = $user['address_status'] ?? 'not_submitted';
                        $status_class = $addr_status === 'approved' ? 'status-approved' : 
                                       ($addr_status === 'pending' ? 'status-pending' : 'status-not');
                        $status_text = ucfirst(str_replace('_', ' ', $addr_status));
                        ?>
                        <span class="px-5 py-2 rounded-2xl text-sm font-medium <?= $status_class ?>">
                            <?= $status_text ?>
                        </span>
                    </div>

                    <?php if ($addr_status === 'approved'): ?>
                        <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-300 p-6 rounded-2xl text-center">
                            <i class="fas fa-check-circle text-4xl mb-3"></i>
                            <p class="font-semibold text-lg">Address Approved</p>
                            <p class="text-sm">Your home address has been verified and cannot be changed.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="grid md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-slate-400 mb-2">Job Title</label>
                                <input type="text" name="job_title" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>"
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-slate-400 mb-2">Street Address 1 <span class="text-red-400">*</span></label>
                                <input type="text" name="street1" value="<?= htmlspecialchars($user['address_street1'] ?? '') ?>" required
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-slate-400 mb-2">Street Address 2 (Optional)</label>
                                <input type="text" name="street2" value="<?= htmlspecialchars($user['address_street2'] ?? '') ?>"
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-2">State / Province</label>
                                <input type="text" name="state" value="<?= htmlspecialchars($user['address_state'] ?? '') ?>"
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-2">Country <span class="text-red-400">*</span></label>
                                <input type="text" name="country" value="<?= htmlspecialchars($user['address_country'] ?? 'Nigeria') ?>" required
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div>
                                <label class="block text-slate-400 mb-2">Postal Code</label>
                                <input type="text" name="postal_code" value="<?= htmlspecialchars($user['address_postal'] ?? '') ?>"
                                       class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 focus:outline-none focus:border-violet-500">
                            </div>

                            <div class="md:col-span-2 pt-6">
                                <button type="submit" name="update_profile" 
                                        class="w-full bg-violet-600 hover:bg-violet-700 py-4 rounded-2xl font-bold transition">
                                    Submit Address for Verification
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Identity Verification - With Camera Integration -->
                <div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-2xl p-7 card">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold flex items-center gap-3">
                            <i class="fas fa-id-card text-violet-400"></i> Identity Verification
                        </h2>
                        <?php
                        $id_status = $user['identity_status'] ?? 'not_submitted';
                        $id_class = $id_status === 'approved' ? 'status-approved' : 
                                   ($id_status === 'pending' ? 'status-pending' : 'status-not');
                        $id_text = ucfirst(str_replace('_', ' ', $id_status));
                        ?>
                        <span class="px-5 py-2 rounded-2xl text-sm font-medium <?= $id_class ?>">
                            <?= $id_text ?>
                        </span>
                    </div>

                    <?php if ($id_status === 'approved'): ?>
                        <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-300 p-8 rounded-2xl text-center">
                            <i class="fas fa-check-circle text-5xl mb-4"></i>
                            <p class="font-semibold text-lg">Identity Verified</p>
                            <p class="text-sm">Your identity document has been approved and cannot be changed.</p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($user['identity_file_path'])): ?>
                            <p class="text-emerald-400 mb-4 text-sm">Uploaded: <?= basename($user['identity_file_path']) ?></p>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="border-2 border-dashed border-slate-600 hover:border-violet-500 transition rounded-2xl p-12 text-center">
                                <i class="fas fa-camera text-6xl text-slate-500 mb-4"></i>
                                <p class="text-slate-300">Take Photo or Upload ID</p>
                                <p class="text-xs text-slate-500 mt-2">JPG or PNG • Max 5MB</p>
                                
                                <input type="file" 
                                       name="identity_file" 
                                       accept="image/jpeg,image/png" 
                                       capture="environment"
                                       required
                                       class="mt-8 block w-full text-sm text-slate-400 
                                              file:mr-4 file:py-3 file:px-8 file:rounded-2xl 
                                              file:border-0 file:bg-violet-600 file:text-white 
                                              hover:file:bg-violet-700 cursor-pointer">
                            </div>

                            <button type="submit" name="upload_identity" 
                                    class="mt-8 w-full bg-violet-600 hover:bg-violet-700 py-4 rounded-2xl font-bold transition">
                                Upload & Submit for Verification
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- Service Worker Registration -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('Service Worker registered!', reg))
            .catch(err => console.log('Service Worker registration failed:', err));
    }
    </script>
</body>
</html>