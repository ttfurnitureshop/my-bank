<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ────────────────────────────────────────────────
// Get Total Fiat Balance (Checking + Savings) - SAME AS index.php
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(balance), 0) AS total_balance 
    FROM accounts 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$fiat_balance = (float)($row['total_balance'] ?? 0.00);

// Available credit card balance (limit - used)
$credit_available = 0.00;
$credit_cards_list = [];
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
            $credit_cards_list[] = $r;
            $credit_available += max(0, (float)$r['available']);
        }
    }
}
$total_spendable = $fiat_balance + $credit_available;
// ────────────────────────────────────────────────

// Fallback prices (used if API fails)
$fallback_prices = [
    'bitcoin'      => ['usd' => 62000, 'usd_24h_change' => 0],
    'ethereum'     => ['usd' => 2800,  'usd_24h_change' => 0],
    'tether'       => ['usd' => 1,     'usd_24h_change' => 0],
    'binancecoin'  => ['usd' => 520,   'usd_24h_change' => 0],
    'solana'       => ['usd' => 140,   'usd_24h_change' => 0],
    'ripple'       => ['usd' => 0.55,  'usd_24h_change' => 0],
    'cardano'      => ['usd' => 0.45,  'usd_24h_change' => 0],
    'dogecoin'     => ['usd' => 0.14,  'usd_24h_change' => 0],
    'tron'         => ['usd' => 0.12,  'usd_24h_change' => 0],
    'avalanche-2'  => ['usd' => 25,    'usd_24h_change' => 0],
    'polkadot'     => ['usd' => 7.20,  'usd_24h_change' => 0],
    'chainlink'    => ['usd' => 14.50, 'usd_24h_change' => 0]
];

// Live price integration via CoinGecko
$ids = implode(',', array_keys($fallback_prices));
$api_url = "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies=usd&include_24hr_change=true";

$live_prices = $fallback_prices; // start with fallback

$context = stream_context_create([
    'http' => [
        'timeout' => 4,
        'header'  => "User-Agent: MyBankCryptoWallet/1.0\r\n"
    ]
]);

$json = @file_get_contents($api_url, false, $context);
if ($json !== false) {
    $data = json_decode($json, true);
    if (is_array($data)) {
        foreach ($data as $id => $info) {
            if (isset($info['usd'])) {
                $live_prices[$id] = [
                    'usd' => (float)$info['usd'],
                    'usd_24h_change' => (float)($info['usd_24h_change'] ?? 0)
                ];
            }
        }
    }
}

// Crypto mapping
$crypto_map = [
    'BTC'  => ['id' => 'bitcoin',      'name' => 'Bitcoin'],
    'ETH'  => ['id' => 'ethereum',     'name' => 'Ethereum'],
    'USDT' => ['id' => 'tether',       'name' => 'Tether USD'],
    'BNB'  => ['id' => 'binancecoin',  'name' => 'BNB'],
    'SOL'  => ['id' => 'solana',       'name' => 'Solana'],
    'XRP'  => ['id' => 'ripple',       'name' => 'XRP'],
    'ADA'  => ['id' => 'cardano',      'name' => 'Cardano'],
    'DOGE' => ['id' => 'dogecoin',     'name' => 'Dogecoin'],
    'TRX'  => ['id' => 'tron',         'name' => 'TRON'],
    'AVAX' => ['id' => 'avalanche-2',  'name' => 'Avalanche'],
    'DOT'  => ['id' => 'polkadot',     'name' => 'Polkadot'],
    'LINK' => ['id' => 'chainlink',    'name' => 'Chainlink']
];

// Fetch user's per-token wallet addresses from the database (matches the SQL we ran)
$stmt = $conn->prepare("SELECT 
    btc_address, eth_address, usdt_address, sol_address, ada_address, 
    xrp_address, bnb_address, doge_address, trx_address, 
    link_address, matic_address, dot_address 
FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_wallets = $stmt->get_result()->fetch_assoc();

// Map token symbols to their address columns
$wallet_map = [
    'BTC'  => $user_wallets['btc_address'] ?? '',
    'ETH'  => $user_wallets['eth_address'] ?? '',
    'USDT' => $user_wallets['usdt_address'] ?? '',
    'SOL'  => $user_wallets['sol_address'] ?? '',
    'ADA'  => $user_wallets['ada_address'] ?? '',
    'XRP'  => $user_wallets['xrp_address'] ?? '',
    'BNB'  => $user_wallets['bnb_address'] ?? '',
    'DOGE' => $user_wallets['doge_address'] ?? '',
    'TRX'  => $user_wallets['trx_address'] ?? '',
    'LINK' => $user_wallets['link_address'] ?? '',
    'MATIC'=> $user_wallets['matic_address'] ?? '',
    'DOT'  => $user_wallets['dot_address'] ?? ''
];

// Fallback wallet addresses so EVERY token always has an address + QR
$fallback_wallets = [
    'BTC'  => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
    'ETH'  => '0x71C7656EC7ab88b098defB751B7401B5f6d8976F',
    'USDT' => '0x71C7656EC7ab88b098defB751B7401B5f6d8976F',
    'BNB'  => '0x71C7656EC7ab88b098defB751B7401B5f6d8976F',
    'SOL'  => '7EqQdEULxWcraVx3mXKFjc84LhCkMGZCkRuDwvcMuZRh',
    'XRP'  => 'rEb8TK3gBgk5auZkwc6sHnwrGVJH8DuaLh',
    'ADA'  => 'addr1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
    'DOGE' => 'D7Y55gHhQ7JvQpKzVx2n0yrf2493p83kkf',
    'TRX'  => 'TXYZabcdefghijklmnopqrstuvwxyz123456',
    'AVAX' => 'X-avax1qxy2kgdygjrsqtzq2n0yrf2493p83kk',
    'DOT'  => '1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
    'LINK' => '0x71C7656EC7ab88b098defB751B7401B5f6d8976F'
];

// Ensure every token has a wallet address (DB value first, then fallback)
foreach ($crypto_map as $symbol => $info) {
    if (empty($wallet_map[$symbol])) {
        $wallet_map[$symbol] = $fallback_wallets[$symbol] ?? '';
    }
}

// Fetch ALL holdings for this user (including zero balances)
$stmt = $conn->prepare("SELECT token, balance FROM crypto_balances WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$holdings = [];
while ($row = $result->fetch_assoc()) {
    $symbol = strtoupper($row['token']);
    $holdings[$symbol] = (float)$row['balance'];
}

// Build complete list of ALL supported tokens (balance 0 if not held)
$user_crypto = [];
foreach ($crypto_map as $symbol => $info) {
    $user_crypto[$symbol] = $holdings[$symbol] ?? 0.00;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Wallet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .price-up { color: #22c55e; }
        .price-down { color: #ef4444; }
        .qr-modal { 
            display: none; 
            position: fixed; top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: rgba(0,0,0,0.9); 
            z-index: 50; 
            align-items: center; justify-content: center; 
        }
        .crypto-row {
            transition: background-color 0.2s ease;
        }
        .crypto-row:hover {
            background-color: rgba(30, 41, 59, 0.8);
        }
        .wallet-addr {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            letter-spacing: -0.01em;
            word-break: break-all;
        }
        /* Bybit / Binance style balance emphasis */
        .balance-main {
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .balance-sub {
            font-size: 0.8rem;
            color: #94a3b8;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen pb-24">

<div class="max-w-3xl mx-auto p-5 sm:p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <a href="index.php" class="flex items-center gap-2 text-orange-400 hover:text-orange-300">
            <i class="fa-solid fa-arrow-left text-2xl"></i>
            <span class="text-lg font-medium">Back</span>
        </a>
        <h1 class="text-3xl sm:text-4xl font-bold text-orange-400">Crypto Wallet</h1>
    </div>

    <!-- Fiat + Credit available -->
    <div class="space-y-4 mb-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6">
                <p class="text-sm text-slate-400">Available Fiat Balance</p>
                <p class="text-3xl font-semibold text-emerald-400">$<?= number_format($fiat_balance, 2) ?></p>
            </div>
            <div class="bg-slate-900 border border-violet-700/50 rounded-3xl p-6">
                <p class="text-sm text-slate-400">Available Credit</p>
                <p class="text-3xl font-semibold text-violet-400">$<?= number_format($credit_available, 2) ?></p>
                <p class="text-xs text-slate-500 mt-1">From your MyBank credit card limit</p>
            </div>
        </div>
        <div class="bg-slate-900/80 border border-slate-700 rounded-2xl px-5 py-3 flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm text-slate-400">Total spendable (fiat + credit)</span>
            <span class="text-xl font-semibold text-white">$<?= number_format($total_spendable, 2) ?></span>
        </div>
        <?php if (!empty($credit_cards_list)): ?>
        <div class="text-xs text-slate-500">
            Credit can be used when buying crypto — select your card on the Buy page.
            <?php foreach ($credit_cards_list as $cc): ?>
                <span class="inline-block mt-1 mr-2 px-2 py-0.5 rounded-lg bg-violet-900/40 text-violet-300">
                    <?= htmlspecialchars($cc['card_type'] ?? 'Card') ?> · avail $<?= number_format(max(0,(float)$cc['available']), 2) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <input type="text" id="searchInput" 
               class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-6 py-4 text-lg focus:outline-none focus:border-orange-500 placeholder-slate-500"
               placeholder="Search any crypto (Bitcoin, BNB, DOGE, Solana...)">
    </div>

    <!-- Crypto Holdings - Advanced List UI (Bybit / Binance style) -->
    <div class="bg-slate-900 border border-slate-700 rounded-3xl overflow-hidden" id="cryptoGrid">
        <!-- List Header -->
        <div class="hidden sm:grid grid-cols-12 gap-3 px-5 py-3 border-b border-slate-700 text-xs uppercase tracking-wider text-slate-500 font-medium">
            <div class="col-span-5">Asset</div>
            <div class="col-span-3 text-right">Balance</div>
            <div class="col-span-2 text-right">Value</div>
            <div class="col-span-2 text-right">Wallet / QR</div>
        </div>

        <?php foreach ($user_crypto as $symbol => $balance): 
            $info = $crypto_map[$symbol] ?? null;
            if (!$info) continue;

            $cg_id = $info['id'];
            $price_data = $live_prices[$cg_id] ?? ['usd' => 0, 'usd_24h_change' => 0];
            $price  = $price_data['usd'] ?? 0;
            $change = $price_data['usd_24h_change'] ?? 0;
            $dollar_value = $balance * $price;
            $wallet_addr = $wallet_map[$symbol] ?? '';
            $name_lower = strtolower($info['name']);
        ?>
            <div class="crypto-row border-b border-slate-800 last:border-b-0 px-5 py-5" 
                 data-symbol="<?= strtolower($symbol) ?>" 
                 data-name="<?= htmlspecialchars($name_lower) ?>">
                <!-- Mobile + Desktop unified row -->
                <div class="flex flex-col sm:grid sm:grid-cols-12 sm:gap-3 sm:items-center">
                    
                    <!-- Asset name + live price + change -->
                    <div class="col-span-5 flex items-center gap-3 mb-3 sm:mb-0">
                        <div class="w-11 h-11 rounded-2xl bg-slate-800 flex items-center justify-center text-orange-400 font-bold text-sm flex-shrink-0">
                            <?= substr($symbol, 0, 3) ?>
                        </div>
                        <div>
                            <div class="font-semibold text-lg leading-tight"><?= $info['name'] ?></div>
                            <div class="text-slate-400 text-sm flex items-center gap-2 flex-wrap">
                                <span><?= $symbol ?></span>
                                <span class="text-slate-300 font-mono text-xs">$<?= number_format($price, $price < 1 ? 4 : 2) ?></span>
                                <span class="<?= $change >= 0 ? 'price-up' : 'price-down' ?> text-xs font-medium">
                                    <?= $change >= 0 ? '↑' : '↓' ?> <?= number_format(abs($change), 2) ?>%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Balance (Bybit / Binance style - prominent in front) -->
                    <div class="col-span-3 sm:text-right mb-2 sm:mb-0">
                        <div class="balance-main <?= $balance > 0 ? 'text-emerald-400' : 'text-slate-500' ?> font-mono">
                            <?= number_format($balance, 8) ?>
                        </div>
                        <div class="balance-sub"><?= $symbol ?></div>
                    </div>

                    <!-- Dollar value -->
                    <div class="col-span-2 sm:text-right mb-3 sm:mb-0">
                        <div class="text-slate-200 font-medium text-lg">
                            $<?= number_format($dollar_value, 2) ?>
                        </div>
                        <div class="text-slate-500 text-xs sm:hidden">≈ Value</div>
                    </div>

                    <!-- Wallet address + QR button -->
                    <div class="col-span-2 flex flex-col sm:items-end gap-2">
                        <?php if ($wallet_addr): ?>
                            <div class="wallet-addr text-slate-400 bg-slate-800/70 px-3 py-1.5 rounded-xl max-w-full truncate" title="<?= htmlspecialchars($wallet_addr) ?>">
                                <?= htmlspecialchars(substr($wallet_addr, 0, 8) . '…' . substr($wallet_addr, -6)) ?>
                            </div>
                            <button onclick="showQR('<?= htmlspecialchars($wallet_addr) ?>', '<?= $symbol ?>')" 
                                    class="inline-flex items-center gap-2 bg-orange-500/15 hover:bg-orange-500/25 text-orange-400 border border-orange-500/30 px-3 py-1.5 rounded-xl text-sm font-medium transition">
                                <i class="fa-solid fa-qrcode"></i>
                                QR Code
                            </button>
                        <?php else: ?>
                            <span class="text-slate-600 text-sm">No address</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="qr-modal">
    <div class="bg-slate-900 rounded-3xl p-8 max-w-xs w-full text-center">
        <h3 class="text-2xl font-semibold mb-6" id="modalSymbol"></h3>
        <div id="qrcode" class="mx-auto mb-6"></div>
        <p class="text-xs text-slate-400 break-all mb-6 px-4" id="modalWallet"></p>
        
        <div class="flex flex-col gap-3">
            <button onclick="copyAddress()" 
                    id="copyBtn"
                    class="w-full bg-orange-500 hover:bg-orange-600 py-3.5 rounded-2xl font-medium text-lg flex items-center justify-center gap-2 transition">
                <i class="fa-solid fa-copy"></i>
                <span id="copyBtnText">Copy Address</span>
            </button>
            <button onclick="closeQR()" 
                    class="w-full bg-slate-700 hover:bg-slate-600 py-3.5 rounded-2xl font-medium text-lg">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Bottom Navigation Bar -->
<footer class="fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-700 py-4 grid grid-cols-4 text-center z-50">
    <a href="buy_crypto.php" class="flex flex-col items-center text-emerald-400 hover:text-emerald-300 active:scale-95 transition">
        <i class="fa-solid fa-cart-plus text-2xl mb-1"></i>
        <span class="text-xs">Buy</span>
    </a>
    <a href="sell_crypto.php" class="flex flex-col items-center text-red-400 hover:text-red-300 active:scale-95 transition">
        <i class="fa-solid fa-cart-arrow-down text-2xl mb-1"></i>
        <span class="text-xs">Sell</span>
    </a>
    <a href="withdraw_crypto.php" class="flex flex-col items-center text-purple-400 hover:text-purple-300 active:scale-95 transition">
        <i class="fa-solid fa-arrow-up-from-bracket text-2xl mb-1"></i>
        <span class="text-xs">Withdraw</span>
    </a>
    <a href="convert_crypto.php" class="flex flex-col items-center text-blue-400 hover:text-blue-300 active:scale-95 transition">
        <i class="fa-solid fa-exchange-alt text-2xl mb-1"></i>
        <span class="text-xs">Convert</span>
    </a>
</footer>

<script>
// Current wallet address for copy functionality
let currentWalletAddress = '';

// Dynamic QR Code generation
function showQR(wallet, symbol) {
    currentWalletAddress = wallet;
    document.getElementById('modalSymbol').textContent = symbol + ' Address';
    document.getElementById('modalWallet').textContent = wallet;
    
    // Reset copy button text
    const copyBtnText = document.getElementById('copyBtnText');
    copyBtnText.textContent = 'Copy Address';
    
    const qrDiv = document.getElementById('qrcode');
    qrDiv.innerHTML = '';
    new QRCode(qrDiv, {
        text: wallet,
        width: 220,
        height: 220,
        colorDark: "#f59e0b",
        colorLight: "#1e2937"
    });
    document.getElementById('qrModal').style.display = "flex";
}

function closeQR() {
    document.getElementById('qrModal').style.display = "none";
}

// Copy Address functionality
function copyAddress() {
    if (!currentWalletAddress) return;

    navigator.clipboard.writeText(currentWalletAddress).then(() => {
        const copyBtnText = document.getElementById('copyBtnText');
        copyBtnText.textContent = 'Copied!';
        
        // Reset text after 2 seconds
        setTimeout(() => {
            copyBtnText.textContent = 'Copy Address';
        }, 2000);
    }).catch(err => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = currentWalletAddress;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        const copyBtnText = document.getElementById('copyBtnText');
        copyBtnText.textContent = 'Copied!';
        setTimeout(() => {
            copyBtnText.textContent = 'Copy Address';
        }, 2000);
    });
}

// Search – works on symbol OR full name (Bitcoin, BNB, doge, solana, etc.)
document.getElementById('searchInput').addEventListener('keyup', function() {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('.crypto-row').forEach(row => {
        const symbol = row.getAttribute('data-symbol') || '';
        const name   = row.getAttribute('data-name') || '';
        const match  = !term || symbol.includes(term) || name.includes(term);
        row.style.display = match ? '' : 'none';
    });
});
</script>

</body>
</html>