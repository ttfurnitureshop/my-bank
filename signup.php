<?php
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password2      = $_POST['password2'] ?? '';
    $preferred_type = $_POST['preferred_account'] ?? '';
    $address        = trim($_POST['address'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $zip            = trim($_POST['zip'] ?? '');
    $country        = trim($_POST['country'] ?? '');

    if (empty($full_name)) {
        $error = "Full name is required.";
    } elseif (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($phone) < 8 || !preg_match('/^\+?[1-9]\d{6,14}$/', $phone)) {
        $error = "Please enter a valid international phone number (e.g. +2348012345678).";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $password2) {
        $error = "Passwords do not match.";
    } elseif (empty($preferred_type)) {
        $error = "Please select a preferred account type.";
    } elseif (!in_array($preferred_type, ['Checking', 'Savings'])) {
        $error = "Invalid account type selected.";
    } elseif (strlen($address) < 3) {
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ?");
        $stmt->execute([$username, $email, $phone]);
        if ($stmt->fetch()) {
            $error = "Username, email or phone number is already taken.";
        } else {
            foreach ([
                "ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL",
                "ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL",
                "ALTER TABLE users ADD COLUMN state VARCHAR(100) NULL",
                "ALTER TABLE users ADD COLUMN zip VARCHAR(20) NULL",
                "ALTER TABLE users ADD COLUMN country VARCHAR(100) NULL",
            ] as $alterSql) {
                try { $pdo->exec($alterSql); } catch (Exception $e) {}
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, phone, password, full_name, address, city, state, zip, country)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $phone, $hash, $full_name, $address, $city, $state, $zip, $country]);
            $userId = $pdo->lastInsertId();

            $account_number = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $routing_number = "021000021";

            $stmt = $pdo->prepare("
                INSERT INTO accounts (user_id, name, balance, account_number, routing_number, preferred)
                VALUES (?, ?, 0.00, ?, ?, 1)
            ");
            $stmt->execute([$userId, $preferred_type, $account_number, $routing_number]);

            $other_type = ($preferred_type === 'Checking') ? 'Savings' : 'Checking';
            $other_account = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $pdo->prepare("
                INSERT INTO accounts (user_id, name, balance, account_number, routing_number)
                VALUES (?, ?, 0.00, ?, ?)
            ")->execute([$userId, $other_type, $other_account, $routing_number]);

            $success = "Account created successfully!<br>
                        Your $preferred_type account number: <strong>$account_number</strong><br>
                        Routing number: <strong>$routing_number</strong><br>
                        You can now log in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center text-white py-8 px-4">
    <div class="bg-slate-900 p-5 sm:p-6 rounded-2xl w-full max-w-md border border-slate-700 shadow-2xl">
        <h1 class="text-2xl font-bold text-center mb-5 text-violet-400">Create Account</h1>

        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-700 text-red-200 p-3 rounded-xl mb-4 text-center text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-200 p-4 rounded-xl mb-4 text-center text-sm">
                <?= $success ?><br>
                <a href="login.php" class="underline font-medium mt-3 inline-block">Go to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" id="signupForm" class="space-y-3">
                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Full Name *</label>
                    <input type="text" name="full_name" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Username *</label>
                    <input type="text" name="username" required minlength="4"
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Email *</label>
                    <input type="email" name="email" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Phone * <span class="text-slate-500">(e.g. +12024566534)</span></label>
                    <input type="tel" name="phone" required placeholder="+1xxxxxxxxxx"
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Password *</label>
                    <input type="password" name="password" id="password" required minlength="6"
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Confirm Password *</label>
                    <input type="password" name="password2" id="password2" required
                           class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                </div>

                <div class="pt-2 border-t border-slate-700">
                    <p class="text-slate-300 text-xs font-medium mb-2">Billing address *</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-slate-400 mb-1 text-xs font-medium">Street address *</label>
                            <input type="text" name="address" required placeholder="123 Main Street"
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                   class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 mb-1 text-xs font-medium">City *</label>
                                <input type="text" name="city" required placeholder="City"
                                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                            </div>
                            <div>
                                <label class="block text-slate-400 mb-1 text-xs font-medium">State *</label>
                                <input type="text" name="state" required placeholder="State"
                                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-400 mb-1 text-xs font-medium">ZIP *</label>
                                <input type="text" name="zip" required placeholder="10001"
                                       value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                            </div>
                            <div>
                                <label class="block text-slate-400 mb-1 text-xs font-medium">Country *</label>
                                <input type="text" name="country" required placeholder="Country"
                                       value="<?= htmlspecialchars($_POST['country'] ?? '') ?>"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 mb-1 text-xs font-medium">Preferred Account Type *</label>
                    <select name="preferred_account" required
                            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-500 transition text-white">
                        <option value="" disabled selected>Select account type</option>
                        <option value="Checking">Checking Account</option>
                        <option value="Savings">Savings Account</option>
                    </select>
                </div>

                <button type="submit"
                        class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 rounded-lg text-sm transition duration-200 shadow-md mt-1">
                    Sign Up
                </button>
            </form>

            <div class="text-center mt-4 text-slate-400 text-sm">
                Already have an account?
                <a href="login.php" class="text-violet-400 hover:text-violet-300 font-medium underline">Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('signupForm')?.addEventListener('submit', function(e) {
            const pass1 = document.getElementById('password').value;
            const pass2 = document.getElementById('password2').value;
            if (pass1 !== pass2) {
                e.preventDefault();
                alert("Passwords do not match!");
            }
        });
    </script>
</body>
</html>
