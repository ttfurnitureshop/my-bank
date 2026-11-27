<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

$uploadDir = 'uploads/profile/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

foreach ([
    "ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL",
    "ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL",
    "ALTER TABLE users ADD COLUMN state VARCHAR(100) NULL",
    "ALTER TABLE users ADD COLUMN zip VARCHAR(20) NULL",
    "ALTER TABLE users ADD COLUMN country VARCHAR(100) NULL",
    "ALTER TABLE users ADD COLUMN billing_address TEXT NULL",
] as $alterSql) {
    try { $pdo->exec($alterSql); } catch (Exception $e) {}
}

$stmt = $pdo->prepare("SELECT username, email, phone, full_name, profile_pic,
                              address, city, state, zip, country, billing_address
                       FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php?error=User not found");
    exit;
}

if ($user && empty($user['address']) && !empty($user['billing_address'])) {
    $user['address'] = $user['billing_address'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        // ignore
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload error: " . $file['error'];
        if ($file['error'] === 1 || $file['error'] === 2) {
            $error = "File is too large (server limit reached). Try a smaller photo.";
        }
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024;

        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute([$filename, $userId]);
                $success = "Profile picture updated!";
                $user['profile_pic'] = $filename;
            } else {
                $error = "Failed to save file. Check folder permissions (uploads/profile/ must be writable).";
            }
        } else {
            $error = "Invalid file. Use jpg/png/gif, max 2MB.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_billing'])) {
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $state   = trim($_POST['state'] ?? '');
    $zip     = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if (strlen($address) < 3) {
        $error = "Please enter your street address.";
    } elseif (strlen($city) < 2) {
        $error = "Please enter your city.";
    } elseif (strlen($state) < 2) {
        $error = "Please enter your state.";
    } elseif (strlen($zip) < 3) {
        $error = "Please enter a valid ZIP / postal code.";
    } elseif (strlen($country) < 2) {
        $error = "Please enter your country.";
    } else {
        $combined = trim("$address, $city, $state $zip, $country");
        $stmt = $pdo->prepare("
            UPDATE users
            SET address = ?, city = ?, state = ?, zip = ?, country = ?, billing_address = ?
            WHERE id = ?
        ");
        $stmt->execute([$address, $city, $state, $zip, $country, $combined, $userId]);

        $success = "Billing address updated successfully.";
        $user['address'] = $address;
        $user['city'] = $city;
        $user['state'] = $state;
        $user['zip'] = $zip;
        $user['country'] = $country;
        $user['billing_address'] = $combined;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-slate-950 min-h-screen text-white">

    <nav class="bg-slate-900 border-b border-slate-700 p-4">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold text-violet-400">MyBank</a>
            <a href="index.php" class="text-sm text-slate-300 hover:text-violet-400 flex items-center gap-2 transition">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto mt-10 px-4 sm:px-6 pb-16">
        <div class="bg-slate-900 rounded-2xl border border-slate-700 shadow-2xl p-8">

            <h1 class="text-3xl font-bold text-center mb-10 text-violet-400">Your Profile</h1>

            <?php if ($error): ?>
                <div class="bg-red-900/50 border border-red-700 text-red-200 p-4 rounded-xl mb-6 text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-200 p-4 rounded-xl mb-6 text-center">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col items-center mb-10">
                <div class="relative w-32 h-32 mb-4 group">
                    <div class="w-full h-full rounded-full bg-slate-700 border-4 border-slate-600 overflow-hidden flex items-center justify-center">
                        <?php if (!empty($user['profile_pic']) && file_exists($uploadDir . $user['profile_pic'])): ?>
                            <img src="<?= $uploadDir . htmlspecialchars($user['profile_pic']) ?>"
                                 alt="Profile Picture"
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user text-6xl text-slate-400"></i>
                        <?php endif; ?>
                    </div>
                    <label for="profile_pic_input"
                           class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition cursor-pointer">
                        <i class="fas fa-camera text-white text-3xl"></i>
                    </label>
                </div>

                <form method="POST" enctype="multipart/form-data" class="text-center">
                    <input type="file" name="profile_pic" id="profile_pic_input" accept="image/jpeg,image/png,image/gif" class="hidden">
                    <button type="submit"
                            class="mt-2 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg text-sm transition">
                        Upload New Picture
                    </button>
                </form>

                <h2 class="text-2xl font-bold mt-4"><?= htmlspecialchars($user['username']) ?></h2>
                <p class="text-slate-400"><?= htmlspecialchars($user['full_name'] ?: 'No full name set') ?></p>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 rounded-xl p-5 border border-slate-700 flex items-center">
                    <i class="fas fa-envelope text-violet-400 mr-4 text-xl"></i>
                    <div>
                        <div class="text-slate-300 font-medium">Email</div>
                        <div class="text-lg break-all"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-xl p-5 border border-slate-700 flex items-center">
                    <i class="fas fa-phone-alt text-violet-400 mr-4 text-xl"></i>
                    <div>
                        <div class="text-slate-300 font-medium">Phone Number</div>
                        <div class="text-lg"><?= htmlspecialchars($user['phone']) ?></div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-xl p-5 border border-slate-700">
                    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-violet-400 mr-3 text-xl"></i>
                            <span class="text-slate-300 font-medium">Billing Address</span>
                        </div>
                        <button type="button" id="clear-address-btn"
                                class="text-sm px-3 py-1.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition">
                            Update address
                        </button>
                    </div>

                    <form method="POST" id="billing-form" class="space-y-3">
                        <div>
                            <label class="block text-slate-400 text-xs mb-1">Street address *</label>
                            <input type="text" name="address" id="addr-street" required
                                   placeholder="123 Main Street"
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                   class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">City *</label>
                                <input type="text" name="city" id="addr-city" required
                                       placeholder="City"
                                       value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">State *</label>
                                <input type="text" name="state" id="addr-state" required
                                       placeholder="State"
                                       value="<?= htmlspecialchars($user['state'] ?? '') ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">ZIP / Postal code *</label>
                                <input type="text" name="zip" id="addr-zip" required
                                       placeholder="10001"
                                       value="<?= htmlspecialchars($user['zip'] ?? '') ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-xs mb-1">Country *</label>
                                <input type="text" name="country" id="addr-country" required
                                       placeholder="Country"
                                       value="<?= htmlspecialchars($user['country'] ?? '') ?>"
                                       class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500">
                            </div>
                        </div>

                        <button type="submit" name="update_billing"
                                class="mt-2 w-full bg-violet-600 hover:bg-violet-700 text-white font-medium py-2.5 rounded-xl text-sm transition">
                            Save Billing Address
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

<!-- In-app confirm (not browser popup) -->
<div id="addr-confirm-modal" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-sm p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-white mb-2">Update address?</h3>
        <p class="text-sm text-slate-400 mb-6">This will clear your current billing address so you can enter a new one.</p>
        <div class="flex gap-3">
            <button type="button" id="addr-confirm-cancel"
                    class="flex-1 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-sm font-medium transition">
                Cancel
            </button>
            <button type="button" id="addr-confirm-ok"
                    class="flex-1 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-sm font-medium transition">
                Confirm
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('clear-address-btn');
    var modal = document.getElementById('addr-confirm-modal');
    var cancelBtn = document.getElementById('addr-confirm-cancel');
    var okBtn = document.getElementById('addr-confirm-ok');
    if (!btn || !modal) return;

    function openModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    function clearFields() {
        ['addr-street', 'addr-city', 'addr-state', 'addr-zip', 'addr-country'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var street = document.getElementById('addr-street');
        if (street) street.focus();
    }

    btn.addEventListener('click', openModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (okBtn) okBtn.addEventListener('click', function () {
        clearFields();
        closeModal();
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
})();
</script>
</body>
</html>
