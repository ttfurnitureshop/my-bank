<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$message  = '';
$messageType = '';

date_default_timezone_set('Africa/Lagos');

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS investment_holdings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            name VARCHAR(120) NOT NULL,
            quantity DECIMAL(18,8) NOT NULL DEFAULT 0,
            avg_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_symbol (user_id, symbol),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {}

$market = [
    'AAPL' => ['name' => 'Apple Inc.',   'price' => 198.50],
    'TSLA' => ['name' => 'Tesla, Inc.',  'price' => 248.30],
    'MSFT' => ['name' => 'Microsoft',    'price' => 425.10],
    'AMZN' => ['name' => 'Amazon',       'price' => 185.40],
    'GOOGL'=> ['name' => 'Alphabet',     'price' => 175.20],
    'NVDA' => ['name' => 'NVIDIA',       'price' => 920.00],
    'BTC'  => ['name' => 'Bitcoin',      'price' => 68450.00],
    'ETH'  => ['name' => 'Ethereum',     'price' => 3450.00],
];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) FROM accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$fiatBalance = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT symbol, name, quantity, avg_cost FROM investment_holdings WHERE user_id = ? AND quantity > 0 ORDER BY (quantity * avg_cost) DESC");
$stmt->execute([$userId]);
$holdingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $symbol    = strtoupper(trim($_POST['symbol'] ?? ''));
    $amountUsd = (float)str_replace([',', '$'], '', $_POST['amount_usd'] ?? '0');

    try {
        if (!isset($market[$symbol])) {
            throw new Exception('Invalid asset selected.');
        }
        if ($amountUsd <= 0) {
            throw new Exception('Enter an amount greater than zero.');
        }

        $price = (float)$market[$symbol]['price'];
        $name  = $market[$symbol]['name'];
        $qty   = $amountUsd / $price;

        $pdo->beginTransaction();

        if ($action === 'buy') {
            $stmt = $pdo->prepare("
                SELECT id, balance FROM accounts
                WHERE user_id = ? AND LOWER(TRIM(name)) IN ('checking', 'savings')
                ORDER BY balance DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$acc || (float)$acc['balance'] < $amountUsd) {
                throw new Exception('Insufficient fiat balance. Available: $' . number_format($fiatBalance, 2));
            }

            $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ? AND user_id = ?")
                ->execute([$amountUsd, $acc['id'], $userId]);

            $stmt = $pdo->prepare("SELECT quantity, avg_cost FROM investment_holdings WHERE user_id = ? AND symbol = ?");
            $stmt->execute([$userId, $symbol]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $oldQty = (float)$existing['quantity'];
                $oldCost = (float)$existing['avg_cost'];
                $newQty = $oldQty + $qty;
                $newAvg = $newQty > 0 ? (($oldQty * $oldCost) + $amountUsd) / $newQty : $price;
                $pdo->prepare("UPDATE investment_holdings SET quantity = ?, avg_cost = ?, name = ? WHERE user_id = ? AND symbol = ?")
                    ->execute([$newQty, $newAvg, $name, $userId, $symbol]);
            } else {
                $pdo->prepare("INSERT INTO investment_holdings (user_id, symbol, name, quantity, avg_cost) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$userId, $symbol, $name, $qty, $price]);
            }

            $desc = "Bought {$symbol} · " . number_format($qty, 6) . " @ $" . number_format($price, 2);
            $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at) VALUES (?, ?, 'debit', ?, ?, UTC_TIMESTAMP())")
                ->execute([$userId, $acc['id'], -$amountUsd, $desc]);

            $fiatBalance -= $amountUsd;
            $message = 'Purchase successful: ' . $desc;
            $messageType = 'success';

        } elseif ($action === 'sell') {
            $stmt = $pdo->prepare("SELECT quantity, avg_cost FROM investment_holdings WHERE user_id = ? AND symbol = ?");
            $stmt->execute([$userId, $symbol]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing || (float)$existing['quantity'] <= 0) {
                throw new Exception('You do not hold this asset.');
            }

            $holdQty = (float)$existing['quantity'];
            $holdValue = $holdQty * $price;
            if ($amountUsd > $holdValue + 0.01) {
                throw new Exception('Sell amount exceeds your holding value ($' . number_format($holdValue, 2) . ').');
            }

            $sellQty = $amountUsd / $price;
            if ($sellQty > $holdQty) {
                $sellQty = $holdQty;
                $amountUsd = $sellQty * $price;
            }

            $newQty = $holdQty - $sellQty;
            if ($newQty < 0.00000001) {
                $pdo->prepare("DELETE FROM investment_holdings WHERE user_id = ? AND symbol = ?")
                    ->execute([$userId, $symbol]);
            } else {
                $pdo->prepare("UPDATE investment_holdings SET quantity = ? WHERE user_id = ? AND symbol = ?")
                    ->execute([$newQty, $userId, $symbol]);
            }

            $stmt = $pdo->prepare("
                SELECT id FROM accounts
                WHERE user_id = ? AND LOWER(TRIM(name)) IN ('checking', 'savings')
                ORDER BY balance DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $accId = $stmt->fetchColumn();
            if (!$accId) {
                throw new Exception('No checking or savings account to receive funds.');
            }

            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ? AND user_id = ?")
                ->execute([$amountUsd, $accId, $userId]);

            $desc = "Sold {$symbol} · " . number_format($sellQty, 6) . " @ $" . number_format($price, 2);
            $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at) VALUES (?, ?, 'credit', ?, ?, UTC_TIMESTAMP())")
                ->execute([$userId, $accId, $amountUsd, $desc]);

            $fiatBalance += $amountUsd;
            $message = 'Sale successful: ' . $desc;
            $messageType = 'success';
        } else {
            throw new Exception('Unknown action.');
        }

        $pdo->commit();

        $stmt = $pdo->prepare("SELECT symbol, name, quantity, avg_cost FROM investment_holdings WHERE user_id = ? AND quantity > 0 ORDER BY (quantity * avg_cost) DESC");
        $stmt->execute([$userId]);
        $holdingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$portfolioItems = [];
$portfolioValue = 0.0;
$costBasis = 0.0;
foreach ($holdingsRows as $row) {
    $sym = $row['symbol'];
    $price = isset($market[$sym]) ? (float)$market[$sym]['price'] : (float)$row['avg_cost'];
    $qty = (float)$row['quantity'];
    $value = $qty * $price;
    $cost = $qty * (float)$row['avg_cost'];
    $chg = $cost > 0 ? (($value - $cost) / $cost) * 100 : 0;
    $portfolioItems[] = [
        'symbol' => $sym,
        'name' => $row['name'],
        'quantity' => $qty,
        'price' => $price,
        'value' => $value,
        'avg_cost' => (float)$row['avg_cost'],
        'change_pct' => $chg,
    ];
    $portfolioValue += $value;
    $costBasis += $cost;
}
$totalGain = $portfolioValue - $costBasis;
$totalGainPct = $costBasis > 0 ? ($totalGain / $costBasis) * 100 : 0;

$chartLabels = ['Start'];
$chartData = [max(0, $costBasis * 0.85)];
$steps = 5;
for ($i = 1; $i <= $steps; $i++) {
    $chartLabels[] = 'T' . $i;
    $t = $i / $steps;
    $chartData[] = round($costBasis + ($portfolioValue - $costBasis) * $t, 2);
}
$chartLabels[count($chartLabels) - 1] = 'Now';
$chartData[count($chartData) - 1] = round($portfolioValue, 2);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Investments • MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .nav-item { @apply flex items-center gap-3 px-5 py-3.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors duration-200; }
        .nav-active { @apply bg-slate-800 text-white; }
        .sidebar-divider { @apply border-t border-slate-700 my-5; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen overflow-x-hidden">

<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-[45] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 border-r border-slate-800 -translate-x-full lg:translate-x-0 transition-transform duration-300 overflow-y-auto">
    <div class="p-6 border-b border-slate-800 flex items-center gap-3">
        <a href="index.php" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-violet-600 rounded-2xl flex items-center justify-center text-3xl font-bold">M</div>
            <span class="text-3xl font-bold tracking-tighter">MyBank</span>
        </a>
    </div>
    <nav class="p-4 space-y-1">
        <a href="index.php" class="nav-item"><i class="fa-solid fa-house w-5"></i> Dashboard</a><br></br>
        <a href="accounts.php" class="nav-item"><i class="fa-solid fa-wallet w-5"></i> Accounts</a> <br></br>
        <a href="credit.php" class="nav-item"><i class="fa-solid fa-credit-card w-5"></i> Cards</a> <br></br>
        <a href="transfer.php" class="nav-item"><i class="fa-solid fa-arrow-right-arrow-left w-5"></i> Transfers</a> <br></br>
        <a href="investments.php" class="nav-item nav-active"><i class="fa-solid fa-chart-line w-5"></i> Investments</a> <br></br>
        <a href="crypto.php" class="nav-item"><i class="fa-brands fa-bitcoin w-5"></i> Crypto</a> <br></br>
        <div class="sidebar-divider"></div>
        <a href="profile.php" class="nav-item"><i class="fa-solid fa-user w-5"></i> Profile</a> <br></br>
        <a href="settings.php" class="nav-item"><i class="fa-solid fa-gear w-5"></i> Settings</a> <br></br>
        <a href="support.php" class="nav-item"><i class="fa-solid fa-headset w-5"></i> Support</a> <br></br>
        <div class="sidebar-divider"></div>
        <a href="logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-900/30">
            <i class="fa-solid fa-right-from-bracket w-5"></i> Logout
        </a> <br></br>
    </nav>
</aside>

<main class="lg:ml-72 min-h-screen pb-24 lg:pb-8">
    <header class="sticky top-0 z-[60] bg-slate-950 border-b border-slate-800 px-4 sm:px-8 py-3 flex items-center justify-between gap-3">
        <button id="mobile-menu-btn" type="button"
                class="lg:hidden flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-800 text-white border border-slate-600 shadow-sm"
                aria-label="Open menu">
            <i id="menu-icon" class="fa-solid fa-bars text-xl"></i>
        </button>
        <div class="hidden lg:block w-11" aria-hidden="true"></div>

        <div class="flex items-center gap-2">
            <span class="text-sm text-slate-400 hidden sm:inline mr-1">Hi, <?= htmlspecialchars($username) ?></span>
            <a href="notifications.php"
               class="relative flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-800 text-white border border-slate-600"
               aria-label="Notifications">
                <i class="fa-solid fa-bell text-xl"></i>
            </a>
        </div>
    </header>

    <div class="p-4 sm:p-8 max-w-[1600px] mx-auto space-y-6">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-amber-400">Investments</h2>
            <p class="text-slate-400 text-sm mt-1">Buy &amp; sell with your MyBank fiat balance</p>
        </div>

        <?php if ($message): ?>
        <div class="rounded-2xl px-4 py-3 text-sm <?= $messageType === 'success' ? 'bg-emerald-900/50 border border-emerald-700 text-emerald-200' : 'bg-red-900/50 border border-red-700 text-red-200' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs text-slate-500 uppercase tracking-wider">Available fiat</p>
                <p class="text-2xl font-mono font-bold text-emerald-400">$<?= number_format($fiatBalance, 2) ?></p>
            </div>
            <a href="transfer.php" class="text-sm text-cyan-400 hover:underline">Move money →</a>
        </div>

        <div class="bg-gradient-to-br from-amber-950 via-yellow-950 to-amber-950 rounded-3xl p-6 sm:p-8 shadow-2xl border border-amber-900/30">
            <div class="text-slate-300 uppercase text-xs tracking-wider mb-1">Portfolio value</div>
            <div class="text-4xl sm:text-5xl font-bold font-mono tracking-tight">$<?= number_format($portfolioValue, 2) ?></div>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                <span class="<?= $totalGain >= 0 ? 'text-emerald-400' : 'text-red-400' ?> font-semibold">
                    <?= $totalGain >= 0 ? '+' : '' ?>$<?= number_format(abs($totalGain), 2) ?>
                    (<?= $totalGain >= 0 ? '+' : '' ?><?= number_format($totalGainPct, 2) ?>%)
                </span>
                <span class="text-slate-500">vs cost basis</span>
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden">
            <div class="flex border-b border-slate-800">
                <button type="button" id="tab-buy" class="flex-1 py-3.5 text-sm font-semibold text-amber-400 border-b-2 border-amber-400 bg-slate-800/40">Buy</button>
                <button type="button" id="tab-sell" class="flex-1 py-3.5 text-sm font-semibold text-slate-400 border-b-2 border-transparent">Sell</button>
            </div>

            <form method="post" id="form-buy" class="p-5 sm:p-6 space-y-4">
                <input type="hidden" name="action" value="buy">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Asset</label>
                    <select name="symbol" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-sm">
                        <?php foreach ($market as $sym => $info): ?>
                        <option value="<?= $sym ?>"><?= $sym ?> — <?= htmlspecialchars($info['name']) ?> ($<?= number_format($info['price'], 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Amount in USD</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                        <input type="number" name="amount_usd" id="buy-amount" step="0.01" min="1" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-4 py-3 text-sm" placeholder="100.00">
                    </div>
                    <button type="button" id="buy-max" class="text-xs text-amber-400 mt-2 font-medium">Max: $<?= number_format($fiatBalance, 2) ?></button>
                </div>
                <button type="submit" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-semibold rounded-2xl transition">Buy with fiat</button>
            </form>

            <form method="post" id="form-sell" class="p-5 sm:p-6 space-y-4 hidden">
                <input type="hidden" name="action" value="sell">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Asset to sell</label>
                    <select name="symbol" id="sell-symbol" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-sm">
                        <?php if (empty($portfolioItems)): ?>
                        <option value="">No holdings yet</option>
                        <?php else: ?>
                            <?php foreach ($portfolioItems as $item): ?>
                            <option value="<?= htmlspecialchars($item['symbol']) ?>" data-value="<?= htmlspecialchars($item['value']) ?>">
                                <?= htmlspecialchars($item['symbol']) ?> — $<?= number_format($item['value'], 2) ?> value
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">USD amount to sell</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                        <input type="number" name="amount_usd" id="sell-amount" step="0.01" min="0.01" required
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-4 py-3 text-sm" placeholder="50.00" <?= empty($portfolioItems) ? 'disabled' : '' ?>>
                    </div>
                    <button type="button" id="sell-max" class="text-xs text-amber-400 mt-2 font-medium">Max holding</button>
                </div>
                <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-2xl transition" <?= empty($portfolioItems) ? 'disabled' : '' ?>>Sell to fiat balance</button>
            </form>
        </div>

        <div class="bg-slate-900 rounded-3xl p-5 sm:p-7 border border-slate-800">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-area text-amber-400"></i> Portfolio performance</h3>
            <div class="h-52 sm:h-72"><canvas id="portfolioChart"></canvas></div>
        </div>

        <div class="bg-slate-900 rounded-3xl p-5 sm:p-7 border border-slate-800">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2"><i class="fa-solid fa-list text-cyan-400"></i> Your holdings</h3>
            <?php if (empty($portfolioItems)): ?>
                <p class="text-slate-500 text-center py-8 text-sm">No investments yet. Buy an asset above using your fiat balance.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($portfolioItems as $item): ?>
                    <div class="flex justify-between items-center py-3 border-b border-slate-800 last:border-0 gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold flex-shrink-0"><?= htmlspecialchars(substr($item['symbol'], 0, 2)) ?></div>
                            <div class="min-w-0">
                                <div class="font-medium truncate"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($item['symbol']) ?> · <?= number_format($item['quantity'], 6) ?> units</div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-medium font-mono">$<?= number_format($item['value'], 2) ?></div>
                            <div class="text-xs <?= $item['change_pct'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                                <?= $item['change_pct'] >= 0 ? '+' : '' ?><?= number_format($item['change_pct'], 1) ?>%
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <p class="text-center text-slate-600 text-xs pb-2">Prices are simulated for demo. Trades debit/credit your real Checking &amp; Savings balances.</p>
    </div>

    <footer class="fixed bottom-0 left-0 right-0 lg:ml-72 bg-slate-900/95 border-t border-slate-800 px-2 sm:px-6 py-3 flex justify-around items-center text-[10px] sm:text-xs backdrop-blur-md z-40">
        <a href="index.php" class="flex flex-col items-center text-violet-400 px-2 py-1"><i class="fa-solid fa-house text-xl mb-0.5"></i><span>Main</span></a>
        <a href="transfer.php" class="flex flex-col items-center text-cyan-400 px-2 py-1"><i class="fa-solid fa-arrow-right-arrow-left text-xl mb-0.5"></i><span>Transfer</span></a>
        <a href="investments.php" class="flex flex-col items-center text-amber-400 px-2 py-1"><i class="fa-solid fa-chart-line text-xl mb-0.5"></i><span>Invest</span></a>
        <a href="crypto.php" class="flex flex-col items-center text-orange-400 px-2 py-1"><i class="fa-brands fa-bitcoin text-xl mb-0.5"></i><span>Crypto</span></a>
    </footer>
</main>

<script>
(function () {
    const btn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const icon = document.getElementById('menu-icon');
    if (!btn || !sidebar) return;
    function open() {
        sidebar.classList.remove('-translate-x-full');
        if (overlay) { overlay.classList.remove('opacity-0', 'pointer-events-none'); overlay.classList.add('opacity-100'); }
        if (icon) { icon.classList.remove('fa-bars'); icon.classList.add('fa-xmark'); }
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar.classList.add('-translate-x-full');
        if (overlay) { overlay.classList.add('opacity-0', 'pointer-events-none'); overlay.classList.remove('opacity-100'); }
        if (icon) { icon.classList.remove('fa-xmark'); icon.classList.add('fa-bars'); }
        document.body.style.overflow = '';
    }
    function isOpen() { return !sidebar.classList.contains('-translate-x-full'); }
    btn.addEventListener('click', e => { e.stopPropagation(); isOpen() ? close() : open(); });
    if (overlay) overlay.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { if (window.innerWidth < 1024) close(); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
})();

const tabBuy = document.getElementById('tab-buy');
const tabSell = document.getElementById('tab-sell');
const formBuy = document.getElementById('form-buy');
const formSell = document.getElementById('form-sell');
tabBuy.addEventListener('click', () => {
    tabBuy.className = 'flex-1 py-3.5 text-sm font-semibold text-amber-400 border-b-2 border-amber-400 bg-slate-800/40';
    tabSell.className = 'flex-1 py-3.5 text-sm font-semibold text-slate-400 border-b-2 border-transparent';
    formBuy.classList.remove('hidden'); formSell.classList.add('hidden');
});
tabSell.addEventListener('click', () => {
    tabSell.className = 'flex-1 py-3.5 text-sm font-semibold text-emerald-400 border-b-2 border-emerald-400 bg-slate-800/40';
    tabBuy.className = 'flex-1 py-3.5 text-sm font-semibold text-slate-400 border-b-2 border-transparent';
    formSell.classList.remove('hidden'); formBuy.classList.add('hidden');
});
document.getElementById('buy-max')?.addEventListener('click', () => {
    document.getElementById('buy-amount').value = <?= json_encode(round($fiatBalance, 2)) ?>;
});
document.getElementById('sell-max')?.addEventListener('click', () => {
    const sel = document.getElementById('sell-symbol');
    const opt = sel?.options[sel.selectedIndex];
    const v = parseFloat(opt?.getAttribute('data-value') || '0');
    document.getElementById('sell-amount').value = v.toFixed(2);
});

const ctx = document.getElementById('portfolioChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Portfolio Value',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#fbbf24',
                backgroundColor: 'rgba(251, 191, 36, 0.12)',
                tension: 0.35, fill: true, pointBackgroundColor: '#fbbf24'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(100,116,139,0.15)' }, ticks: { color: '#94a3b8', maxTicksLimit: 6 } },
                y: { grid: { color: 'rgba(100,116,139,0.15)' }, ticks: { color: '#94a3b8' } }
            }
        }
    });
}
</script>
</body>
</html>
