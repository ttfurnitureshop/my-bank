<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ────────────────────────────────────────────────
// Get Total Fiat Balance from Checking + Savings (same as index.php)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(balance), 0) AS total_balance 
    FROM accounts 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$fullname     = $user['full_name'] ?? 'Valued Customer';  // kept for compatibility
$fiat_balance = (float)($user['total_balance'] ?? 0.00);

// Credit cards
$credit_cards = [];
$credit_available = 0.00;
$cc_check = @$conn->query("SHOW TABLES LIKE 'credit_cards'");
if ($cc_check && $cc_check->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT id, card_type, card_number, credit_limit, balance,
               (credit_limit - balance) AS available
        FROM credit_cards
        WHERE user_id = ? AND status = 'active'
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $r['available'] = max(0, (float)$r['available']);
            $credit_cards[] = $r;
            $credit_available += $r['available'];
        }
    }
}
// ────────────────────────────────────────────────

$message      = "";

// Tokens & Prices
$default_tokens = ['BTC','ETH','USDT','SOL','ADA','XRP','BNB','DOGE','TRX','LINK','MATIC','DOT'];

$prices = [
    'BTC'=>62000, 'ETH'=>2800, 'USDT'=>1, 'SOL'=>140,
    'ADA'=>0.45, 'XRP'=>0.55, 'BNB'=>520, 'DOGE'=>0.14,
    'TRX'=>0.12, 'LINK'=>14.50, 'MATIC'=>0.68, 'DOT'=>7.20
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

// Initialize missing tokens
foreach ($default_tokens as $t) {
    if (!isset($holdings[$t])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO crypto_balances (user_id, token, balance) VALUES (?, ?, 0.00)");
        $stmt->bind_param("is", $user_id, $t);
        $stmt->execute();
        $holdings[$t] = 0.00;
    }
}

// SELL LOGIC (proceeds to fiat OR pay toward credit card balance)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["sell"])) {
    $token        = trim($_POST["token"] ?? "");
    $amount_token = floatval($_POST["amount_token"] ?? 0);
    $dest         = trim($_POST["dest"] ?? "fiat"); // "fiat" or "cc_{id}"

    if (!isset($prices[$token])) {
        $message = "❌ Invalid token selected.";
    } elseif ($amount_token <= 0) {
        $message = "❌ Amount must be greater than 0.";
    } elseif ($amount_token > ($holdings[$token] ?? 0)) {
        $message = "❌ Insufficient $token balance.";
    } else {
        $usd_received = $amount_token * $prices[$token];
        $is_credit = (strpos($dest, 'cc_') === 0);
        $cc_id = $is_credit ? (int)substr($dest, 3) : 0;

        $conn->begin_transaction();
        try {
            if ($is_credit) {
                // Pay toward credit card balance (reduce amount owed)
                $stmt = $conn->prepare("SELECT id, balance FROM credit_cards WHERE id = ? AND user_id = ? AND status = 'active'");
                $stmt->bind_param("ii", $cc_id, $user_id);
                $stmt->execute();
                $cc = $stmt->get_result()->fetch_assoc();
                if (!$cc) throw new Exception("Credit card not found");

                $owed = (float)$cc['balance'];
                $apply = min($usd_received, $owed);
                $leftover = $usd_received - $apply;

                if ($apply > 0) {
                    $stmt = $conn->prepare("UPDATE credit_cards SET balance = balance - ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("dii", $apply, $cc_id, $user_id);
                    $stmt->execute();
                }
                // Any leftover over payment goes to fiat
                if ($leftover > 0.009) {
                    $stmt = $conn->prepare("
                        UPDATE accounts SET balance = balance + ?
                        WHERE user_id = ? AND LOWER(TRIM(name)) IN ('checking', 'savings')
                        ORDER BY balance DESC LIMIT 1
                    ");
                    $stmt->bind_param("di", $leftover, $user_id);
                    $stmt->execute();
                    $fiat_balance += $leftover;
                }
                $desc = "Sold " . number_format($amount_token, 6) . " " . $token . " — $" . number_format($apply, 2) . " applied to credit card";
            } else {
                // Add fiat to Checking or Savings
                $stmt = $conn->prepare("
                    UPDATE accounts 
                    SET balance = balance + ? 
                    WHERE user_id = ? 
                      AND LOWER(TRIM(name)) IN ('checking', 'savings')
                    ORDER BY balance DESC 
                    LIMIT 1
                ");
                $stmt->bind_param("di", $usd_received, $user_id);
                $stmt->execute();

                if ($stmt->affected_rows === 0) {
                    throw new Exception("No account found to credit");
                }
                $fiat_balance += $usd_received;
                $desc = "Sold " . number_format($amount_token, 6) . " " . $token . " for $" . number_format($usd_received, 2);
            }

            // Deduct crypto
            $stmt = $conn->prepare("UPDATE crypto_balances SET balance = balance - ? WHERE user_id = ? AND token = ?");
            $stmt->bind_param("dis", $amount_token, $user_id, $token);
            $stmt->execute();

            $now = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, created_at, description, type, amount) VALUES (?, ?, ?, 'credit', ?)");
            $stmt->bind_param("issd", $user_id, $now, $desc, $usd_received);
            $stmt->execute();

            $conn->commit();

            $holdings[$token] -= $amount_token;

            // Refresh credit display
            if ($is_credit) {
                $credit_available = 0;
                foreach ($credit_cards as &$c) {
                    if ((int)$c['id'] === $cc_id) {
                        $c['balance'] = max(0, (float)$c['balance'] - min($usd_received, (float)$c['balance']));
                        $c['available'] = max(0, (float)$c['credit_limit'] - (float)$c['balance']);
                    }
                    $credit_available += max(0, (float)$c['available']);
                }
                unset($c);
            }

            $message = "✅ Successfully sold " . number_format($amount_token, 6) . " " . $token . " for $" . number_format($usd_received, 2);

        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Sell failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Crypto • MyBank</title>
    <style>
        body {margin:0;font-family:Arial,sans-serif;background:#eef2f7;color:#333;}
        .header {background:#0a2a66;color:white;padding:18px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;}
        .container {max-width:480px;margin:0 auto;padding:20px;}
        .card {background:white;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
        h2 {color:#0a2a66;}
        button {background:#0a2a66;color:white;border:none;padding:14px;border-radius:12px;font-size:16px;cursor:pointer;width:100%;margin-top:10px;}
        select,input {width:100%;padding:14px;margin:10px 0;border:1px solid #ccc;border-radius:12px;font-size:16px;}
        .notice {background:#e6f0ff;padding:12px;border-radius:10px;margin-bottom:16px;text-align:center;}
        .balance-box {background:#0a2a66;color:white;padding:16px;border-radius:12px;text-align:center;margin-bottom:20px;font-weight:bold;}
        .holdings-list {margin:1rem 0;padding:1rem;background:#f8f9fa;border-radius:10px;}
        .holdings-item {padding:0.5rem 0;border-bottom:1px solid #eee;}
        .holdings-item:last-child {border-bottom:none;}
        .max-btn {
            color: #0a2a66;
            font-weight: 600;
            text-align: right;
            margin: -8px 0 10px;
            cursor: pointer;
            display: inline-block;
        }
        .max-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <strong>MyBank</strong><br>
        <small>Sell Crypto</small>
    </div>
    <a href="crypto.php" style="color:white;text-decoration:none;">← Back</a>
</div>

<div class="container">
    <div class="card">
        <h2>Sell Crypto • MyBank</h2>
        <?php if ($message): ?>
            <div class="notice"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="balance-box">
            Fiat Balance: $<?= number_format($fiat_balance, 2) ?><br>
            <span style="font-size:14px;font-weight:normal;opacity:0.9;">Available Credit: $<?= number_format($credit_available, 2) ?></span>
        </div>

        <div class="holdings-list">
            <strong>Your Crypto Holdings:</strong><br>
            <?php foreach ($holdings as $token => $amt): ?>
                <?php if ($amt > 0): ?>
                <div class="holdings-item">
                    <?= $token ?>: <?= number_format($amt, 6) ?> (~$<?= number_format($amt * $prices[$token], 2) ?>)
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <form method="post" id="sell-form">
            <label>Select Token to Sell:</label>
            <select name="token" required id="sell-token">
                <option value="">-- Choose Token --</option>
                <?php foreach ($holdings as $token => $amt): ?>
                    <?php if ($amt > 0): ?>
                        <option value="<?= $token ?>">
                            <?= $token ?> - You own <?= number_format($amt, 6) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <label>Deposit proceeds to:</label>
            <select name="dest" required id="sell-dest">
                <option value="fiat">Fiat (Checking / Savings)</option>
                <?php foreach ($credit_cards as $cc):
                    $last4 = substr(preg_replace('/\s+/', '', $cc['card_number']), -4);
                    $owed = (float)$cc['balance'];
                ?>
                <option value="cc_<?= (int)$cc['id'] ?>">
                    💳 Pay toward <?= htmlspecialchars($cc['card_type'] ?? 'Credit Card') ?> •••• <?= $last4 ?> (owed $<?= number_format($owed, 2) ?>)
                </option>
                <?php endforeach; ?>
            </select>

            <label>Amount to Sell:</label>
            <input type="number" name="amount_token" step="any" min="0.00000001" required placeholder="e.g. 0.01" id="sell-amount">
            <span class="max-btn" id="max-btn">Max: 0</span>

            <button type="submit" name="sell">Sell Token</button>
        </form>

        <p style="text-align:center; margin-top:20px; color:#666;">
            
        </p>
    </div>
</div>

<script>
document.getElementById('sell-token').addEventListener('change', function() {
    const selected = this.value;
    const holdings = <?= json_encode($holdings) ?>;
    const max = parseFloat(holdings[selected]) || 0;
    
    const amountInput = document.getElementById('sell-amount');
    const maxBtn = document.getElementById('max-btn');
    
    amountInput.value = '';
    amountInput.max = max;
    maxBtn.textContent = 'Max: ' + max.toFixed(8);
    
    // Click on "Max" to fill the field
    maxBtn.onclick = function() {
        amountInput.value = max;   // Use the exact number (no extra formatting)
        amountInput.focus();
    };
});
</script>

</body>
</html>