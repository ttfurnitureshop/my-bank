<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
$fiat_balance = (float)($user['total_balance'] ?? 0.00);

// Credit cards available (limit - balance)
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
$total_spendable = $fiat_balance + $credit_available;
// ────────────────────────────────────────────────

$message = "";

// Prices (you can later replace with live prices)
$prices = [
    'BTC'=>62000, 'ETH'=>2800, 'USDT'=>1, 'SOL'=>140,
    'ADA'=>0.45, 'XRP'=>0.55, 'BNB'=>520, 'DOGE'=>0.14,
    'TRX'=>0.12, 'LINK'=>14.50, 'MATIC'=>0.68, 'DOT'=>7.20
];

$default_tokens = array_keys($prices);

// Load / initialize holdings
$holdings = [];
$stmt = $conn->prepare("SELECT token, balance FROM crypto_balances WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $holdings[$row['token']] = (float)$row['balance'];
}
foreach ($default_tokens as $t) {
    if (!isset($holdings[$t])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO crypto_balances (user_id, token, balance) VALUES (?, ?, 0.00)");
        $stmt->bind_param("is", $user_id, $t);
        $stmt->execute();
        $holdings[$t] = 0.00;
    }
}

// BUY LOGIC (fiat account OR credit card)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["buy"])) {
    $token      = trim($_POST["token"] ?? "");
    $amount_usd = floatval($_POST["amount_usd"] ?? 0);
    $pay_source = trim($_POST["pay_source"] ?? "fiat"); // "fiat" or "cc_{id}"

    if (!isset($prices[$token])) {
        $message = "❌ Invalid token.";
    } elseif ($amount_usd <= 0) {
        $message = "❌ Enter a valid amount.";
    } else {
        $is_credit = (strpos($pay_source, 'cc_') === 0);
        $cc_id = $is_credit ? (int)substr($pay_source, 3) : 0;

        // Validate funds
        $can_pay = false;
        $cc_row = null;
        if ($is_credit) {
            foreach ($credit_cards as $c) {
                if ((int)$c['id'] === $cc_id) { $cc_row = $c; break; }
            }
            if ($cc_row && (float)$cc_row['available'] >= $amount_usd) {
                $can_pay = true;
            } else {
                $message = "❌ Insufficient credit available.";
            }
        } else {
            if ($amount_usd <= $fiat_balance) {
                $can_pay = true;
            } else {
                $message = "❌ Insufficient fiat balance.";
            }
        }

        if ($can_pay) {
            $tokens_received = $amount_usd / $prices[$token];

            $conn->begin_transaction();
            try {
                if ($is_credit) {
                    // Increase card balance (uses available credit)
                    $stmt = $conn->prepare("UPDATE credit_cards SET balance = balance + ? WHERE id = ? AND user_id = ? AND status = 'active'");
                    $stmt->bind_param("dii", $amount_usd, $cc_id, $user_id);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("Credit card update failed");
                    }
                    $desc = "Bought " . number_format($tokens_received, 6) . " " . $token . " with credit card";
                } else {
                    // Deduct fiat from Checking or Savings
                    $stmt = $conn->prepare("
                        UPDATE accounts 
                        SET balance = balance - ? 
                        WHERE user_id = ? 
                          AND LOWER(TRIM(name)) IN ('checking', 'savings')
                        ORDER BY balance DESC 
                        LIMIT 1
                    ");
                    $stmt->bind_param("di", $amount_usd, $user_id);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("No sufficient account found");
                    }
                    $desc = "Bought " . number_format($tokens_received, 6) . " " . $token;
                    $fiat_balance -= $amount_usd;
                }

                // Add crypto
                $stmt = $conn->prepare("UPDATE crypto_balances SET balance = balance + ? WHERE user_id = ? AND token = ?");
                $stmt->bind_param("dis", $tokens_received, $user_id, $token);
                $stmt->execute();

                // Log transaction
                $now = date("Y-m-d H:i:s");
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, created_at, description, type, amount) VALUES (?, ?, ?, 'debit', ?)");
                $stmt->bind_param("issd", $user_id, $now, $desc, $amount_usd);
                $stmt->execute();

                $conn->commit();

                $holdings[$token] += $tokens_received;

                // Refresh credit available display
                if ($is_credit) {
                    foreach ($credit_cards as &$c) {
                        if ((int)$c['id'] === $cc_id) {
                            $c['balance'] = (float)$c['balance'] + $amount_usd;
                            $c['available'] = max(0, (float)$c['credit_limit'] - (float)$c['balance']);
                        }
                    }
                    unset($c);
                    $credit_available = 0;
                    foreach ($credit_cards as $c) $credit_available += max(0, (float)$c['available']);
                    $total_spendable = $fiat_balance + $credit_available;
                }

                $message = "✅ Purchase successful!";

            } catch (Exception $e) {
                $conn->rollback();
                $message = "❌ Transaction failed: " . $e->getMessage();
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
    <title>Buy Crypto • MyBank</title>
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
        <small>Buy Crypto</small>
    </div>
    <a href="crypto.php" style="color:white;text-decoration:none;">← Back</a>
</div>

<div class="container">
    <div class="card">
        <h2>Buy Crypto • MyBank</h2>
        <?php if ($message): ?>
            <div class="notice"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="balance-box">
            Fiat Balance: $<?= number_format($fiat_balance, 2) ?><br>
            <span style="font-size:14px;font-weight:normal;opacity:0.9;">Available Credit: $<?= number_format($credit_available, 2) ?></span><br>
            <span style="font-size:13px;font-weight:normal;opacity:0.85;">Total spendable: $<?= number_format($total_spendable, 2) ?></span>
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

        <form method="post" id="buy-form">
            <label>Pay with:</label>
            <select name="pay_source" required id="pay-source">
                <option value="fiat">Fiat (Checking / Savings) — $<?= number_format($fiat_balance, 2) ?></option>
                <?php foreach ($credit_cards as $cc):
                    $last4 = substr(preg_replace('/\s+/', '', $cc['card_number']), -4);
                ?>
                <option value="cc_<?= (int)$cc['id'] ?>">
                    💳 <?= htmlspecialchars($cc['card_type'] ?? 'Credit Card') ?> •••• <?= $last4 ?> — Available $<?= number_format((float)$cc['available'], 2) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label>Select Token to Buy:</label>
            <select name="token" required id="buy-token">
                <option value="">-- Choose Token --</option>
                <?php foreach ($prices as $t => $p): ?>
                    <option value="<?= $t ?>">
                        <?= $t ?> (≈ $<?= number_format($p, 2) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Amount in USD:</label>
            <input type="number" name="amount_usd" step="0.01" min="0.01" required placeholder="e.g. 100.00" id="buy-amount">
            <span class="max-btn" id="max-btn">Max: $<?= number_format($fiat_balance, 2) ?></span>

            <button type="submit" name="buy">Buy Crypto</button>
        </form>

        <p style="text-align:center; margin-top:20px; color:#666;">
            
        </p>
    </div>
</div>

<script>
const paySource = document.getElementById('pay-source');
const maxBtn = document.getElementById('max-btn');
const amountInput = document.getElementById('buy-amount');
const limits = {
    fiat: <?= json_encode((float)$fiat_balance) ?>,
<?php foreach ($credit_cards as $cc): ?>
    "cc_<?= (int)$cc['id'] ?>": <?= json_encode((float)$cc['available']) ?>,
<?php endforeach; ?>
};

function currentMax() {
    const key = paySource ? paySource.value : 'fiat';
    return parseFloat(limits[key] || 0) || 0;
}

function updateMaxLabel() {
    const m = currentMax();
    maxBtn.textContent = 'Max: $' + m.toFixed(2);
}

if (paySource) {
    paySource.addEventListener('change', updateMaxLabel);
    updateMaxLabel();
}

maxBtn.addEventListener('click', function() {
    const m = currentMax();
    amountInput.value = m.toFixed(2);
    amountInput.focus();
});
</script>

</body>
</html>