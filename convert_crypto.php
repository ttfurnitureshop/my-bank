<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user name + current fiat balance - MATCHING index.php style
$stmt = $conn->prepare("SELECT full_name, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$fullname     = $user['full_name'] ?? 'Valued Customer';
$fiat_balance = (float)($user['balance'] ?? 0);
$message      = "";

// Tokens & Initials
$default_tokens = ['BTC','ETH','USDT','SOL','ADA','XRP','BNB','DOGE','TRX','LINK','MATIC','DOT'];

$initials = [
    'BTC'=>0.0125, 'ETH'=>1.8,   'USDT'=>450.00, 'SOL'=>85.0,
    'ADA'=>1200.0, 'XRP'=>2500.0,'BNB'=>12.50,  'DOGE'=>4500.0,
    'TRX'=>3800.0, 'LINK'=>95.00,'MATIC'=>1400.0,'DOT'=>320.00
];

// Load holdings
$holdings = [];
$stmt = $conn->prepare("SELECT token, balance FROM crypto_balances WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $holdings[$row['token']] = (float)$row['balance'];
}

// Initialize missing
foreach ($default_tokens as $t) {
    if (!isset($holdings[$t])) {
        $initial = $initials[$t] ?? 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO crypto_balances (user_id, token, balance) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $user_id, $t, $initial);
        $stmt->execute();
        $holdings[$t] = $initial;
    }
}

// Prices
$prices = [
    'BTC'=>62000, 'ETH'=>2800, 'USDT'=>1,   'SOL'=>140,
    'ADA'=>0.45,  'XRP'=>0.55, 'BNB'=>520,  'DOGE'=>0.14,
    'TRX'=>0.12,  'LINK'=>14.50,'MATIC'=>0.68,'DOT'=>7.20
];

// Convert Logic
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["convert"])) {
    $from_token = trim($_POST["from_token"] ?? "");
    $to_token   = trim($_POST["to_token"] ?? "");
    $amount     = floatval($_POST["amount"] ?? 0);

    if (!isset($prices[$from_token]) || !isset($prices[$to_token])) {
        $message = "❌ Invalid token selected.";
    } elseif ($from_token === $to_token) {
        $message = "❌ Cannot convert to the same token.";
    } elseif ($amount <= 0) {
        $message = "❌ Amount must be greater than 0.";
    } elseif ($amount > ($holdings[$from_token] ?? 0)) {
        $message = "❌ Insufficient $from_token balance.";
    } else {
        $usd_value = $amount * $prices[$from_token];
        $received  = $usd_value / $prices[$to_token];

        $conn->begin_transaction();
        try {
            // Deduct from source
            $stmt = $conn->prepare("UPDATE crypto_balances SET balance = balance - ? WHERE user_id = ? AND token = ?");
            $stmt->bind_param("dis", $amount, $user_id, $from_token);
            $stmt->execute();

            // Add to destination
            $stmt = $conn->prepare("UPDATE crypto_balances SET balance = balance + ? WHERE user_id = ? AND token = ?");
            $stmt->bind_param("dis", $received, $user_id, $to_token);
            $stmt->execute();

            // Log
            $now = date("Y-m-d H:i:s");
            $desc = "Converted " . number_format($amount, 6) . " $from_token to " . number_format($received, 6) . " $to_token";

            $stmt = $conn->prepare("INSERT INTO transactions (user_id, created_at, description, type, amount) VALUES (?, ?, ?, 'debit', ?)");
            $stmt->bind_param("issd", $user_id, $now, $desc, $usd_value);
            $stmt->execute();

            $conn->commit();

            $holdings[$from_token] -= $amount;
            $holdings[$to_token]   += $received;

            $message = "✅ Converted " . number_format($amount, 6) . " $from_token → " . number_format($received, 6) . " $to_token";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Conversion failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Crypto • MyBank</title>
    <style>
        body {margin:0;font-family:Arial,sans-serif;background:#eef2f7;color:#333;}
        .header {background:#0a2a66;color:white;padding:18px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;}
        .container {max-width:480px;margin:0 auto;padding:20px;}
        .card {background:white;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
        h2 {color:#0a2a66;}
        button {background:#0a2a66;color:white;border:none;padding:14px;border-radius:12px;font-size:16px;cursor:pointer;width:100%;margin-top:10px;}
        select,input {width:100%;padding:14px;margin:10px 0;border:1px solid #ccc;border-radius:12px;font-size:16px;}
        .notice {background:#e6f0ff;padding:12px;border-radius:10px;margin-bottom:16px;text-align:center;}
        .holdings-list {margin:1rem 0;padding:1rem;background:#f8f9fa;border-radius:10px;}
        .holdings-item {padding:0.5rem 0;border-bottom:1px solid #eee;}
        .holdings-item:last-child {border-bottom:none;}
        .max-note {color:#666;font-size:0.9rem;margin:-8px 0 10px;text-align:right;}
    </style>
</head>
<body>

<div class="header">
    <div>
        <strong>MyBank</strong><br>
        <small>Convert Crypto</small>
    </div>
    <a href="crypto.php" style="color:white;text-decoration:none;">← Back</a>
</div>

<div class="container">
    <div class="card">
        <h2>Convert Crypto • MyBank</h2>
        <?php if ($message): ?>
            <div class="notice"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="holdings-list">
            <strong>Your Crypto Holdings:</strong><br>
            <?php foreach ($holdings as $token => $amt): ?>
                <div class="holdings-item">
                    <?= $token ?>: <?= number_format($amt, 6) ?> (~$<?= number_format($amt * $prices[$token], 2) ?>)
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post">
            <label>From Token:</label>
            <select name="from_token" required id="convert-from">
                <option value="">-- Select --</option>
                <?php foreach ($holdings as $token => $amt): ?>
                    <?php if ($amt > 0): ?>
                        <option value="<?= $token ?>">
                            <?= $token ?> (You own <?= number_format($amt, 6) ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <label>To Token:</label>
            <select name="to_token" required>
                <option value="">-- Select --</option>
                <?php foreach ($prices as $token => $price): ?>
                    <option value="<?= $token ?>"><?= $token ?> ($<?= number_format($price, 2) ?>)</option>
                <?php endforeach; ?>
            </select>

            <label>Amount to Convert:</label>
            <input type="number" name="amount" step="0.0001" min="0.0001" required placeholder="e.g. 0.01" id="convert-amount">
            <p class="max-note" id="max-convert">Max: 0</p>

            <button type="submit" name="convert">Convert Now</button>
        </form>

        <p style="text-align:center; margin-top:20px; color:#666;">
            Note: Conversion is simulated — no real tokens are moved.
        </p>
    </div>
</div>

<script>
document.getElementById('convert-from').addEventListener('change', function() {
    const selected = this.value;
    const max = <?= json_encode($holdings) ?>[selected] || 0;
    document.getElementById('convert-amount').max = max;
    document.getElementById('max-convert').textContent = 'Max: ' + max.toFixed(6);
});
</script>

</body>
</html>