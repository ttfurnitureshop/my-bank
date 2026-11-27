<?php
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$fullname = $user['full_name'] ?? 'Valued Customer';

$message = "";

// Load sender holdings
$holdings = [];
$stmt = $conn->prepare("SELECT token, balance FROM crypto_balances WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $holdings[$row['token']] = (float)$row['balance'];
}

// Initialize missing holdings
$default_tokens = ['BTC','ETH','USDT','SOL','ADA','XRP','BNB','DOGE','TRX','LINK','MATIC','DOT'];
$initials = ['BTC'=>0.0125,'ETH'=>1.8,'USDT'=>450,'SOL'=>85,'ADA'=>1200,'XRP'=>2500,'BNB'=>12.5,'DOGE'=>4500,'TRX'=>3800,'LINK'=>95,'MATIC'=>1400,'DOT'=>320];

foreach ($default_tokens as $t) {
    if (!isset($holdings[$t])) {
        $initial = $initials[$t] ?? 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO crypto_balances (user_id, token, balance) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $user_id, $t, $initial);
        $stmt->execute();
        $holdings[$t] = $initial;
    }
}

// Token info
$token_info = [
    'BTC' => ['min' => 0.0005, 'fee' => 0.0001, 'network' => 'Bitcoin'],
    'ETH' => ['min' => 0.005,  'fee' => 0.001,  'network' => 'Ethereum (ERC-20)'],
    'USDT'=> ['min' => 10.00,  'fee' => 1.00,   'network' => 'TRC-20 / ERC-20'],
    'SOL' => ['min' => 0.5,    'fee' => 0.01,   'network' => 'Solana'],
    'ADA' => ['min' => 2.0,    'fee' => 0.17,   'network' => 'Cardano'],
    'XRP' => ['min' => 20.0,   'fee' => 0.25,   'network' => 'Ripple'],
    'BNB' => ['min' => 0.05,   'fee' => 0.005,  'network' => 'BEP-20'],
    'DOGE'=> ['min' => 50.0,   'fee' => 1.0,    'network' => 'Dogecoin'],
    'TRX' => ['min' => 100.0,  'fee' => 1.0,    'network' => 'TRON (TRC-20)'],
    'LINK'=> ['min' => 1.0,    'fee' => 0.2,    'network' => 'Ethereum (ERC-20)'],
    'MATIC'=>['min' => 10.0,   'fee' => 0.5,    'network' => 'Polygon'],
    'DOT' => ['min' => 5.0,    'fee' => 0.1,    'network' => 'Polkadot']
];

$prices = ['BTC'=>62000,'ETH'=>2800,'USDT'=>1,'SOL'=>140,'ADA'=>0.45,'XRP'=>0.55,'BNB'=>520,'DOGE'=>0.14,'TRX'=>0.12,'LINK'=>14.50,'MATIC'=>0.68,'DOT'=>7.20];

// ===================== TRANSFER LOGIC =====================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["withdraw"])) {
    $token            = trim($_POST["token"] ?? "");
    $network          = trim($_POST["network"] ?? "");
    $receiver_address = trim($_POST["receiver_address"] ?? "");
    $amount           = floatval($_POST["amount"] ?? 0);

    if (!isset($holdings[$token])) {
        $message = "❌ Invalid token selected.";
    } elseif (empty($network)) {
        $message = "❌ Please select network.";
    } elseif (empty($receiver_address)) {
        $message = "❌ Receiver wallet address required.";
    } elseif ($amount <= 0) {
        $message = "❌ Amount must be greater than 0.";
    } elseif ($amount < ($token_info[$token]['min'] ?? 0)) {
        $message = "❌ Amount below minimum withdrawal.";
    } elseif ($amount > ($holdings[$token] ?? 0)) {
        $message = "❌ Insufficient $token balance.";
    } else {
        $fee = $token_info[$token]['fee'] ?? 0;
        $amount_after_fee = $amount - $fee;

        if ($amount_after_fee <= 0) {
            $message = "❌ Amount too small after fee.";
        } else {
            // Find receiver
            $addr_column = strtolower($token) . "_address";
            $stmt = $conn->prepare("SELECT id FROM users WHERE `$addr_column` = ?");
            $stmt->bind_param("s", $receiver_address);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $message = "❌ No user found with this $token wallet address.";
            } else {
                $receiver = $result->fetch_assoc();
                $receiver_id = $receiver['id'];

                if ($receiver_id == $user_id) {
                    $message = "❌ You cannot send to your own wallet address.";
                } else {
                    $usd_value = $amount * ($prices[$token] ?? 1);   // Full USD value

                    $conn->begin_transaction();
                    try {
                        // 1. Deduct crypto from sender
                        $stmt = $conn->prepare("UPDATE crypto_balances SET balance = balance - ? WHERE user_id = ? AND token = ?");
                        $stmt->bind_param("dis", $amount, $user_id, $token);
                        $stmt->execute();

                        // 2. Add crypto to receiver
                        $stmt = $conn->prepare("INSERT INTO crypto_balances (user_id, token, balance) 
                                                VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?");
                        $stmt->bind_param("isdd", $receiver_id, $token, $amount_after_fee, $amount_after_fee);
                        $stmt->execute();

                        // 3. DEDUCT USD VALUE FROM SENDER'S FIAT BALANCE
                        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                        $stmt->bind_param("di", $usd_value, $user_id);
                        $stmt->execute();

                        // 4. ADD USD VALUE TO RECEIVER'S FIAT BALANCE
                        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->bind_param("di", $usd_value, $receiver_id);
                        $stmt->execute();

                        $txid = '0x' . bin2hex(random_bytes(16));
                        $now = date("Y-m-d H:i:s");

                        $wallet_short = substr($receiver_address, 0, 6) . "...." . substr($receiver_address, -6);

                        // Sender transaction
                        $desc_sender = "Sent $amount $token (fee $fee) via $network to $wallet_short | TXID: $txid";
                        $stmt = $conn->prepare("INSERT INTO transactions (user_id, created_at, description, type, amount) 
                                                VALUES (?, ?, ?, 'debit', ?)");
                        $stmt->bind_param("issd", $user_id, $now, $desc_sender, $usd_value);
                        $stmt->execute();

                        // Receiver transaction
                        $desc_receiver = "Received $amount_after_fee $token from User #$user_id | TXID: $txid";
                        $stmt = $conn->prepare("INSERT INTO transactions (user_id, created_at, description, type, amount) 
                                                VALUES (?, ?, ?, 'credit', ?)");
                        $stmt->bind_param("issd", $receiver_id, $now, $desc_receiver, $usd_value);
                        $stmt->execute();

                        $conn->commit();

                        $holdings[$token] -= $amount;

                        header("Location: withdraw_crypto.php?success=1&token=$token&amount=" . number_format($amount_after_fee,6));
                        exit;

                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "❌ Transfer failed: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $message = "✅ Successfully sent " . htmlspecialchars($_GET['amount'] ?? '') . " " . htmlspecialchars($_GET['token'] ?? '') . " to the receiver's wallet!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Crypto • MyBank</title>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        body {margin:0;font-family:Arial,sans-serif;background:#eef2f7;color:#333;}
        .header {background:#0a2a66;color:white;padding:18px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100;}
        .container {max-width:500px;margin:1.5rem auto;padding:0 1rem;}
        .card {background:white;border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
        h2 {color:#0a2a66;margin:0 0 1.5rem 0;}
        label {display:block;margin:1.2rem 0 0.5rem;font-weight:500;color:#555;}
        select, input[type="text"], input[type="number"] {width:100%;padding:0.9rem;border:1px solid #ccc;border-radius:10px;font-size:1rem;}
        button {background:#0a2a66;color:white;border:none;padding:1rem;border-radius:12px;font-size:1.1rem;font-weight:600;cursor:pointer;width:100%;margin-top:1.5rem;}
        .scan-btn {background:#f97316;color:white;padding:12px;border-radius:10px;text-align:center;margin:10px 0;cursor:pointer;}
        .notice {padding:1rem;border-radius:10px;margin-bottom:1rem;text-align:center;}
        .success {background:#d4edda;color:#155724;}
        .error   {background:#fee2e2;color:#c62828;}
        .holdings {background:#f8f9fa;border-radius:12px;padding:1rem;margin-bottom:1.5rem;}
        .holdings-item {display:flex;justify-content:space-between;padding:0.6rem 0;border-bottom:1px solid #eee;}
        .holdings-item:last-child {border-bottom:none;}
        #qr-video {width:100%; max-width:300px; margin:10px auto; display:none; border-radius:12px;}
    </style>
</head>
<body>

<div class="header">
    <div><strong>MyBank</strong><br><small>Demo Crypto Transfer</small></div>
    <a href="crypto.php" style="color:white;text-decoration:none;">← Back</a>
</div>

<div class="container">
    <div class="card">
        <h2>Send Crypto via Wallet Address</h2>

        <?php if ($message): ?>
            <div class="notice <?= strpos($message,'✅') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="holdings">
            <h4>Your Crypto Holdings</h4>
            <?php foreach ($holdings as $token => $amt): ?>
                <div class="holdings-item">
                    <span><?= $token ?></span>
                    <strong><?= number_format($amt, 6) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" onsubmit="return confirm('Are you sure you want to send this crypto?')">
            <label>Select Token</label>
            <select name="token" required>
                <option value="">Choose Token</option>
                <?php foreach ($holdings as $token => $amt): if ($amt > 0): ?>
                    <option value="<?= $token ?>">
                        <?= $token ?> (Own: <?= number_format($amt,6) ?>)
                    </option>
                <?php endif; endforeach; ?>
            </select>

            <label>Network</label>
            <select name="network" required>
                <option value="">Select Network</option>
                <?php foreach ($token_info as $t => $info): ?>
                    <option value="<?= $info['network'] ?>"><?= $info['network'] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Receiver Wallet Address</label>
            <input type="text" name="receiver_address" id="receiver_address" required 
                   placeholder="Paste receiver's wallet address here" style="font-family: monospace;">

            <div class="scan-btn" onclick="startQRScanner()">📷 Scan QR Code</div>
            <video id="qr-video" autoplay playsinline></video>
            <canvas id="qr-canvas" style="display:none;"></canvas>

            <label>Amount</label>
            <input type="number" name="amount" step="0.00000001" required placeholder="Enter amount" id="amount-input">
            <span onclick="setMaxAmount()" style="color:#0a2a66;font-weight:600;cursor:pointer;display:block;text-align:right;margin-top:4px;">Max</span>

            <button type="submit" name="withdraw">Send Crypto Now</button>
        </form>
    </div>
</div>

<script>
// QR Scanner (unchanged)
let video = document.getElementById('qr-video');
let canvas = document.getElementById('qr-canvas');
let ctx = canvas.getContext('2d');
let scanning = false;

async function startQRScanner() {
    if (scanning) return;
    scanning = true;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        video.srcObject = stream;
        video.style.display = 'block';
        video.play();
        scanQR();
    } catch (err) {
        alert("Camera access denied or not available.");
        scanning = false;
    }
}

function scanQR() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.height = video.videoHeight;
        canvas.width = video.videoWidth;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        if (code) {
            document.getElementById('receiver_address').value = code.data;
            stopScanner();
            return;
        }
    }
    setTimeout(scanQR, 100);
}

function stopScanner() {
    scanning = false;
    if (video.srcObject) {
        video.srcObject.getTracks().forEach(track => track.stop());
    }
    video.style.display = 'none';
}

// New Max Amount Function
function setMaxAmount() {
    const tokenSelect = document.querySelector('select[name="token"]');
    const amountInput = document.getElementById('amount-input');
    const holdings = <?= json_encode($holdings) ?>;
    
    if (tokenSelect && amountInput) {
        const selectedToken = tokenSelect.value;
        if (selectedToken && holdings[selectedToken] !== undefined) {
            amountInput.value = holdings[selectedToken];
        }
    }
}

window.addEventListener('beforeunload', stopScanner);
</script>

</body>
</html>