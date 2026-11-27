<?php
require 'config.php';

// Force Africa/Lagos timezone (West Africa Time - WAT)
date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId    = $_SESSION['user_id'];
$username  = $_SESSION['username']  ?? 'User';
$fullName  = $_SESSION['full_name'] ?? $username;

// Fetch initial unread notification count for the bell icon
$stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND read_at IS NULL");
$stmtNotif->execute([$userId]);
$unreadCount = $stmtNotif->fetchColumn();

// Fetch internal bank accounts
$stmt = $pdo->prepare("
    SELECT name, account_number, routing_number, balance 
    FROM accounts 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare internal account data
$hasChecking   = false;
$hasSavings    = false;
$checkingBal   = 0;
$savingsBal    = 0;
$checkingAcc   = '';
$checkingRout  = '';
$savingsAcc    = '';
$savingsRout   = '';

foreach ($accounts as $acc) {
    $name = trim($acc['name']);
    if (strcasecmp($name, 'Checking') === 0) {
        $hasChecking   = true;
        $checkingBal   = (float)$acc['balance'];
        $checkingAcc   = $acc['account_number'] ?: '—';
        $checkingRout  = $acc['routing_number'] ?: '—';
    }
    if (strcasecmp($name, 'Savings') === 0) {
        $hasSavings    = true;
        $savingsBal    = (float)$acc['balance'];
        $savingsAcc    = $acc['account_number'] ?: '—';
        $savingsRout   = $acc['routing_number'] ?: '—';
    }
}

// Extract last 4 digits
$checkingLast4 = strlen($checkingAcc) >= 4 ? substr($checkingAcc, -4) : '—';
$savingsLast4  = strlen($savingsAcc) >= 4 ? substr($savingsAcc, -4) : '—';

// Fetch VERIFIED linked external ACH accounts
$stmt = $pdo->prepare("
    SELECT id, account_holder, account_number, routing_number, account_type 
    FROM external_accounts 
    WHERE user_id = ? AND verification_status = 'verified'
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$external_linked = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle unlink request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink_external') {
    $unlink_id = (int)($_POST['external_id'] ?? 0);
    if ($unlink_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM external_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$unlink_id, $userId]);
        $success = "External account unlinked successfully.";
    }
}

// Recent transactions — force newest first regardless of type
$stmt = $pdo->prepare("
    SELECT * FROM (
        SELECT id, description, amount, type, 
               DATE_FORMAT(created_at, '%b %d, %Y') AS display_date,
               created_at, 'regular' AS source
        FROM transactions 
        WHERE user_id = ? 

        UNION ALL

        SELECT id, CONCAT('Check Deposit • Pending Verification') AS description, 
               amount, 'deposit' AS type,
               DATE_FORMAT(created_at, '%b %d, %Y') AS display_date,
               created_at, 'check' AS source
        FROM check_deposits 
        WHERE user_id = ? AND status = 'pending'
    ) AS combined
    ORDER BY created_at DESC, id DESC
    LIMIT 20
");
$stmt->execute([$userId, $userId]);
$txs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extra safety: re-sort in PHP so the newest transaction is ALWAYS first
usort($txs, function ($a, $b) {
    $timeA = strtotime($a['created_at']);
    $timeB = strtotime($b['created_at']);

    if ($timeA !== $timeB) {
        return $timeB - $timeA;
    }

    return (int)$b['id'] - (int)$a['id'];
});

// Keep only the latest 8
$txs = array_slice($txs, 0, 8);

// Crypto prices (with fallback)
$cryptoJson = @file_get_contents("https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true");
$crypto = json_decode($cryptoJson, true) ?? [];
$btcPrice  = $crypto['bitcoin']['usd']           ?? 68450;
$btcChange = $crypto['bitcoin']['usd_24h_change'] ?? 1.2;
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="your-icon-180x180.png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MyBank • Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Initialize Theme
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .nav-item { 
            @apply flex items-center gap-3 px-5 py-3.5 rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-all duration-200;
        }
        .nav-active {
            @apply bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white;
        }
        .section-title { 
            @apply text-xl sm:text-2xl font-semibold mb-6 text-slate-800 dark:text-slate-100; 
        }
        .sidebar-divider { 
            @apply border-t border-slate-200 dark:border-slate-700 my-6; 
        }

        /* Fixed Recent Transactions Box */
        .transaction-container {
            max-height: 380px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #64748b #1e2937;
        }
        .transaction-container::-webkit-scrollbar {
            width: 6px;
        }
        .transaction-container::-webkit-scrollbar-thumb {
            background-color: #64748b;
            border-radius: 20px;
        }
        .transaction-row {
            transition: all 0.2s ease;
        }
        .transaction-row:hover {
            transform: translateX(6px);
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen">

<!-- Mobile sidebar overlay (tap to close) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-[45] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

<aside id="sidebar" 
       class="fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 -translate-x-full lg:translate-x-0 transition-transform duration-300 overflow-y-auto">
    
    <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3">
        <a href="index.php" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-violet-600 rounded-2xl flex items-center justify-center text-3xl font-bold text-white">M</div>
            <span class="text-3xl font-bold tracking-tighter text-slate-900 dark:text-white">MyBank</span>
        </a>
    </div>

    <nav class="p-4 space-y-1">
        <a href="index.php" class="nav-item nav-active">
            <i class="fa-solid fa-house w-5"></i> Dashboard
        </a><br></br>
        <a href="accounts.php" class="nav-item">
            <i class="fa-solid fa-wallet w-5"></i> Accounts
        </a><br></br>
        <a href="cards.php" class="nav-item">
            <i class="fa-solid fa-credit-card w-5"></i> Cards
        </a><br></br>
        <a href="transfer.php" class="nav-item">
            <i class="fa-solid fa-arrow-right-arrow-left w-5"></i> Transfers
        </a><br></br>
        <a href="investments.php" class="nav-item">
            <i class="fa-solid fa-chart-line w-5"></i> Investments
        </a><br></br>
        <a href="check-paper.php" class="nav-item">
            <i class="fa-solid fa-money-check w-5 text-amber-500"></i> Order Checks
        </a><br></br>

        <div class="sidebar-divider"></div>

        <a href="profile.php" class="nav-item">
            <i class="fa-solid fa-user w-5"></i> Profile
        </a><br></br>
        <a href="settings.php" class="nav-item">
            <i class="fa-solid fa-gear w-5"></i> Settings
        </a><br></br>
        <a href="notifications.php" class="nav-item">
            <i class="fa-solid fa-bell w-5"></i> Notifications
        </a><br></br>
        <a href="support.php" class="nav-item">
            <i class="fa-solid fa-headset w-5"></i> Support
        </a><br></br>

        <div class="sidebar-divider"></div>

        <a href="logout.php" class="nav-item text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
            <i class="fa-solid fa-right-from-bracket w-5"></i> Logout
        </a><br></br>
    </nav>
</aside>

<main class="lg:ml-72 min-h-screen pb-20 lg:pb-8">

    <header class="sticky top-0 z-[60] bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-8 py-3 flex items-center justify-between gap-3">
        <!-- Left: hamburger -->
        <button id="mobile-menu-btn"
                type="button"
                class="lg:hidden flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-white border border-slate-300 dark:border-slate-600 shadow-sm"
                aria-label="Toggle menu">
            <i id="menu-icon" class="fa-solid fa-bars text-xl"></i>
        </button>
        <div class="hidden lg:block w-11" aria-hidden="true"></div>

        <!-- Right: theme + notifications on one line -->
        <div class="flex items-center gap-2">
            <button id="theme-toggle"
                    type="button"
                    class="flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-white border border-slate-300 dark:border-slate-600"
                    aria-label="Toggle theme">
                <i id="theme-toggle-dark-icon" class="hidden fa-solid fa-moon text-xl"></i>
                <i id="theme-toggle-light-icon" class="hidden fa-solid fa-sun text-xl"></i>
            </button>

            <a href="notifications.php"
               class="relative flex items-center justify-center w-11 h-11 rounded-2xl bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-white border border-slate-300 dark:border-slate-600"
               aria-label="Notifications">
                <i class="fa-solid fa-bell text-xl"></i>
                <span id="notif-count"
                      class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold min-w-[1.15rem] h-[1.15rem] px-1 flex items-center justify-center rounded-full <?= ($unreadCount > 0) ? '' : 'hidden' ?>">
                    <?= $unreadCount ?>
                </span>
            </a>
        </div>
    </header>

    <div class="p-5 sm:p-8 max-w-[1600px] mx-auto space-y-6">

        <div>
            <h2 class="text-xl sm:text-2xl font-semibold text-violet-600 dark:text-violet-400">Welcome back, <?= htmlspecialchars($username) ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Bank smarter. Move faster.</p>
        </div>

        <!-- Account Selection Text Dropdown Line -->
        <div class="relative inline-block">
            <select id="card-account-select" onchange="updateCardBalance()" 
                    class="appearance-none bg-transparent text-slate-800 dark:text-slate-200 text-base sm:text-lg font-semibold pr-6 focus:outline-none cursor-pointer">
                <?php if ($hasChecking): ?>
                <option value="checking" data-bal="<?= $checkingBal ?>" data-last4="<?= htmlspecialchars($checkingLast4) ?>" class="text-slate-900 bg-white dark:bg-slate-900 dark:text-white">
                    Checking Account *<?= htmlspecialchars($checkingLast4) ?>
                </option>
                <?php endif; ?>
                <?php if ($hasSavings): ?>
                <option value="savings" data-bal="<?= $savingsBal ?>" data-last4="<?= htmlspecialchars($savingsLast4) ?>" class="text-slate-900 bg-white dark:bg-slate-900 dark:text-white">
                    Savings Account *<?= htmlspecialchars($savingsLast4) ?>
                </option>
                <?php endif; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center text-slate-600 dark:text-slate-400">
                <i class="fa-solid fa-chevron-down text-xs"></i>
            </div>
        </div>

        <!-- Centered Available Balance Box -->
        <div class="bg-white dark:bg-slate-900/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm max-w-md flex flex-col items-center justify-center text-center">
            <div class="text-sm font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                Available Balance
            </div>
            <div id="card-total-balance" class="text-3xl sm:text-4xl font-bold font-mono text-slate-900 dark:text-white mt-2">
                $<?= number_format($hasChecking ? $checkingBal : ($hasSavings ? $savingsBal : 0), 2) ?>
            </div>
        </div>

        <!-- Quick Options Bar -->
        <div class="grid grid-cols-4 gap-3 max-w-md">
            <a href="investments.php" class="flex flex-col items-center justify-center p-3 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-sm text-center">
                <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400 flex items-center justify-center mb-1.5 text-base">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Invest</span>
            </a>
            <a href="check-paper.php" class="flex flex-col items-center justify-center p-3 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-sm text-center">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-1.5 text-base">
                    <i class="fa-solid fa-money-check"></i>
                </div>
                <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Checks</span>
            </a>
            <a href="cards.php" class="flex flex-col items-center justify-center p-3 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-sm text-center">
                <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-1.5 text-base">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Cards</span>
            </a>
            <a href="heloc.php" class="flex flex-col items-center justify-center p-3 bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800 transition shadow-sm text-center">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-1.5 text-base">
                    <i class="fa-solid fa-house-chimney-window"></i>
                </div>
                <span class="text-xs font-medium text-slate-700 dark:text-slate-200">HELOC</span>
            </a>
        </div>

        <div class="bg-white dark:bg-slate-900/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm max-w-sm">
            <?php
            // Newest active card only — available = this card's limit − balance (never previous cards)
            $cc = null;
            try {
                $stmt = $pdo->prepare("
                    SELECT card_number, name_on_card, card_type, credit_limit, balance, status
                    FROM credit_cards
                    WHERE user_id = ? AND status = 'active'
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $cc = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Exception $e) {
                $cc = null;
            }

            $cardGradients = [
                'Cash Rewards'   => 'from-blue-600 to-blue-900',
                'Travel Elite'   => 'from-violet-600 to-indigo-900',
                'Credit Builder' => 'from-emerald-600 to-teal-900',
            ];
            $hasCreditCard = !empty($cc);
            ?>

            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold flex items-center gap-2 text-slate-800 dark:text-slate-100">
                    <i class="fa-solid fa-credit-card text-violet-500 dark:text-violet-400 text-sm"></i>
                    Your Credit Card
                </h3>
                <?php if (!$hasCreditCard): ?>
                <a href="credit.php" class="text-xs text-violet-600 dark:text-violet-400 hover:underline font-medium">Apply →</a>
                <?php endif; ?>
            </div>

            <?php if (!$hasCreditCard): ?>
                <div class="text-center py-5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                    <i class="fa-solid fa-credit-card text-2xl text-slate-300 dark:text-slate-600 mb-2"></i>
                    <p class="text-xs text-slate-500 mb-2">No credit card yet.</p>
                    <a href="credit.php" class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg transition">
                        See Pre-approval
                    </a>
                </div>
            <?php else:
                $grad = $cardGradients[$cc['card_type'] ?? ''] ?? 'from-slate-700 to-slate-900';
                $rawNum = preg_replace('/\s+/', '', $cc['card_number']);
                $formatted = strlen($rawNum) >= 16
                    ? substr($rawNum, 0, 4) . '  ' . substr($rawNum, 4, 4) . '  ' . substr($rawNum, 8, 4) . '  ' . substr($rawNum, 12, 4)
                    : htmlspecialchars($cc['card_number']);
                $limit     = (float)($cc['credit_limit'] ?? 0);
                $bal       = (float)($cc['balance'] ?? 0);
                $available = max(0, $limit - $bal);
            ?>
                <div class="max-w-xs mx-auto">
                    <div class="relative rounded-xl overflow-hidden shadow-md border border-white/10 text-white bg-gradient-to-br <?= $grad ?>">
                        <div class="absolute inset-0 opacity-20" style="background: linear-gradient(135deg, rgba(255,255,255,0.25) 0%, transparent 50%, rgba(255,255,255,0.05) 100%);"></div>
                        <div class="relative p-4">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <p class="text-[10px] font-semibold tracking-widest uppercase opacity-80">MyBank</p>
                                    <p class="text-xs opacity-90 mt-0.5"><?= htmlspecialchars($cc['card_type'] ?? 'Credit Card') ?></p>
                                </div>
                                <i class="fa-brands fa-cc-visa text-2xl opacity-90"></i>
                            </div>
                            <p class="font-mono text-sm sm:text-base tracking-widest mb-3"><?= $formatted ?></p>
                            <div class="flex justify-between items-end gap-2">
                                <div>
                                    <p class="text-[9px] uppercase tracking-wider opacity-70">Cardholder</p>
                                    <p class="font-semibold text-xs tracking-wide"><?= htmlspecialchars($cc['name_on_card']) ?></p>
                                </div>
                                <?php if ($limit > 0): ?>
                                <div class="text-right">
                                    <p class="text-[9px] uppercase tracking-wider opacity-70">Credit limit</p>
                                    <p class="font-mono font-bold text-sm">$<?= number_format($limit, 0) ?></p>
                                    <p class="text-[9px] opacity-80">Available $<?= number_format($available, 0) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <a href="zelle.php" class="block bg-gradient-to-br from-violet-500 via-purple-500 to-fuchsia-600 dark:from-violet-600 dark:via-purple-600 dark:to-fuchsia-600 rounded-3xl p-7 border border-white/20 hover:border-white/40 shadow-xl group text-white">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center text-3xl border border-white/30">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold">Send with Zelle</h3>
                        <span class="text-white/90 text-sm group-hover:translate-x-1 transition">Go →</span>
                    </div>
                    <p class="text-white/80 mt-1">Instant transfers to anyone with email or phone.</p>
                </div>
            </div>
        </a>

        <div class="bg-white dark:bg-slate-900/90 rounded-3xl p-7 border border-slate-200 dark:border-slate-800 shadow-sm">
            <button onclick="toggleExternalAccounts()" 
                    class="w-full flex items-center justify-between text-left focus:outline-none">
                <h3 class="text-lg font-semibold text-cyan-600 dark:text-cyan-300 flex items-center gap-3">
                    <i class="fa-solid fa-building-columns"></i>
                    Linked External Accounts (<?= count($external_linked) ?>)
                </h3>
                <i id="dropdown-icon" class="fa-solid fa-chevron-down text-cyan-500 dark:text-cyan-400 transition-transform"></i>
            </button>

            <div id="external-accounts-dropdown" class="hidden mt-6 space-y-4">
                <?php if (empty($external_linked)): ?>
                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-6 text-center text-slate-500">
                        No external accounts linked yet.<br>
                        <a href="transfer.php" class="text-cyan-600 dark:text-cyan-400 hover:underline">Link Account →</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($external_linked as $ext): ?>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4 py-4 border-b border-slate-100 dark:border-slate-700 last:border-b-0">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars($ext['account_holder']) ?></div>
                            <div class="text-slate-500 dark:text-slate-400 font-mono text-sm">
                                *<?= substr($ext['account_number'], -4) ?> • <?= ucfirst($ext['account_type']) ?>
                            </div>
                        </div>
                        <form method="post" onsubmit="return confirm('Unlink this account?');">
                            <input type="hidden" name="action" value="unlink_external">
                            <input type="hidden" name="external_id" value="<?= $ext['id'] ?>">
                            <button type="submit" class="px-5 py-2 bg-red-100 dark:bg-red-900/60 hover:bg-red-200 dark:hover:bg-red-800 text-red-700 dark:text-red-300 hover:text-red-800 dark:hover:text-red-100 text-sm rounded-lg transition">
                                Unlink
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-3xl p-7 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-xl font-semibold mb-6 flex items-center gap-3 text-slate-800 dark:text-slate-100">
                <i class="fa-solid fa-clock-rotate-left text-amber-500 dark:text-amber-400"></i>
                Recent Activity
            </h2>

            <?php if (empty($txs)): ?>
                <div class="text-center py-12 text-slate-500">No transactions yet.</div>
            <?php else: ?>
                <div class="transaction-container space-y-3">
                    <?php foreach ($txs as $tx): 
                        $is_credit = $tx['amount'] > 0;
                        $sign = $is_credit ? '+' : '−';
                        $color = $is_credit ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
                        $bg = $is_credit ? 'bg-emerald-50 dark:bg-emerald-900/30' : 'bg-red-50 dark:bg-red-900/30';

                        $desc = htmlspecialchars($tx['description']);

                        if (strpos($desc, '@') !== false && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $desc)) {
                            $desc = preg_replace_callback('/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', function($matches) {
                                $local = $matches[1];
                                $domain = $matches[2];
                                $masked_local = strlen($local) > 4 ? substr($local, 0, 4) . '...' : $local;
                                $masked_domain = strlen($domain) > 4 ? substr($domain, -4) : $domain;
                                return $masked_local . '@...' . $masked_domain;
                            }, $desc);
                        }

                        if ($tx['source'] === 'check') {
                            $desc = 'Check Deposit • <span class="text-amber-600 dark:text-amber-400">Pending Verification</span>';
                        }

                        // Database stores UTC → convert to Africa/Lagos for display
                        $dt = new DateTimeImmutable($tx['created_at'], new DateTimeZone('UTC'));
                        $dt = $dt->setTimezone(new DateTimeZone('Africa/Lagos'));
                        $displayTime = $dt->format('M d, Y • h:i A');
                    ?>
                    <div onclick="viewTransaction(<?= $tx['id'] ?>, '<?= addslashes($tx['description']) ?>', <?= $tx['amount'] ?>, '<?= $tx['created_at'] ?>', '<?= htmlspecialchars($tx['type'] ?? '') ?>')" 
                         class="transaction-row flex items-center justify-between p-4 <?= $bg ?> rounded-2xl cursor-pointer hover:bg-opacity-80 dark:hover:bg-slate-800 transition">
                        <div class="flex items-center gap-4 min-w-0 flex-1">
                            <div class="w-11 h-11 rounded-2xl <?= $bg ?> flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid <?= $is_credit ? 'fa-arrow-down' : 'fa-arrow-up' ?> <?= $color ?>"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-800 dark:text-slate-100"><?= $desc ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-500"><?= $displayTime ?></div>
                            </div>
                        </div>
                        <div class="text-right <?= $color ?> font-semibold text-lg flex-shrink-0 ml-3">
                            <?= $sign ?>$<?= number_format(abs($tx['amount']), 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-3xl p-7 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h3 class="section-title">Crypto Overview</h3>
            <a href="crypto.php" class="block cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors rounded-2xl overflow-hidden">
                <div class="bg-slate-50 dark:bg-slate-800/60 p-5">
                    <div class="flex justify-between items-center text-slate-800 dark:text-slate-100">
                        <span class="flex items-center gap-2 text-xl"><span class="text-3xl text-amber-500">₿</span> Bitcoin</span>
                        <span class="font-mono text-2xl">$<?= number_format($btcPrice) ?></span>
                    </div>
                    <div class="text-sm mt-2 <?= $btcChange >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
                        <?= $btcChange >= 0 ? '+' : '' ?><?= number_format($btcChange, 1) ?>% (24h)
                    </div>
                </div>
            </a>
        </div>

    </div>

    <footer class="fixed bottom-0 left-0 right-0 lg:ml-72 bg-white/95 dark:bg-slate-900/95 border-t border-slate-200 dark:border-slate-800 px-5 py-4 flex justify-around z-40 backdrop-blur-md">
        <a href="index.php" class="flex flex-col items-center text-violet-600 dark:text-violet-400">
            <i class="fa-solid fa-house text-2xl"></i>
            <span class="text-xs mt-1">Main</span>
        </a>
        <a href="transfer.php" class="flex flex-col items-center text-cyan-600 dark:text-cyan-400">
            <i class="fa-solid fa-arrow-right-arrow-left text-2xl"></i>
            <span class="text-xs mt-1">Transfer</span>
        </a>
        <a href="check.php" class="flex flex-col items-center text-amber-600 dark:text-amber-400">
            <i class="fa-solid fa-camera text-2xl"></i>
            <span class="text-xs mt-1">Deposit</span>
        </a>
        <a href="crypto.php" class="flex flex-col items-center text-amber-600 dark:text-amber-400">
            <i class="fa-solid fa-bitcoin-sign text-2xl"></i>
            <span class="text-xs mt-1">Bitcoin</span>
        </a>
    </footer>
</main>

<div id="transaction-modal" class="hidden fixed inset-0 bg-black/60 dark:bg-black/80 flex items-center justify-center z-[60] p-4">
    <div class="modal-box bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700">
        <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center text-slate-800 dark:text-slate-100">
            <h3 class="text-xl font-semibold">Transaction Details</h3>
            <button onclick="closeModal()" class="text-3xl leading-none text-slate-400 hover:text-slate-600 dark:hover:text-white">&times;</button>
        </div>
        <div id="modal-content" class="p-6">
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-slate-700 text-right">
            <button onclick="closeModal()" 
                    class="px-8 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-2xl transition">Close</button>
        </div>
    </div>
</div>

<script>
// Theme Toggle Logic
const themeToggleBtn = document.getElementById('theme-toggle');
const darkIcon = document.getElementById('theme-toggle-dark-icon');
const lightIcon = document.getElementById('theme-toggle-light-icon');

// Sync icons with initial state
if (document.documentElement.classList.contains('dark')) {
    lightIcon.classList.remove('hidden');
} else {
    darkIcon.classList.remove('hidden');
}

themeToggleBtn.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    
    // Toggle icons
    darkIcon.classList.toggle('hidden');
    lightIcon.classList.toggle('hidden');
    
    // Save preference
    localStorage.setItem('color-theme', isDark ? 'dark' : 'light');
});

// Mobile Sidebar Toggle
const mobileBtn = document.getElementById('mobile-menu-btn');
const sidebar = document.getElementById('sidebar');
const menuIcon = document.getElementById('menu-icon');
const sidebarOverlay = document.getElementById('sidebar-overlay');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    if (sidebarOverlay) {
        sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.add('opacity-100');
    }
    menuIcon.classList.remove('fa-bars');
    menuIcon.classList.add('fa-xmark');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    if (sidebarOverlay) {
        sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.remove('opacity-100');
    }
    menuIcon.classList.remove('fa-xmark');
    menuIcon.classList.add('fa-bars');
    document.body.style.overflow = '';
}

function isSidebarOpen() {
    return !sidebar.classList.contains('-translate-x-full');
}

if (mobileBtn && sidebar) {
    mobileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (isSidebarOpen()) closeSidebar();
        else openSidebar();
    });

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) closeSidebar();
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isSidebarOpen()) closeSidebar();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            document.body.style.overflow = '';
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
                sidebarOverlay.classList.remove('opacity-100');
            }
            menuIcon.classList.remove('fa-xmark');
            menuIcon.classList.add('fa-bars');
        }
    });
}

// Balance Switcher
function updateCardBalance() {
    const select = document.getElementById('card-account-select');
    if (!select) return;
    const option = select.options[select.selectedIndex];
    if (!option) return;
    
    const bal = parseFloat(option.getAttribute('data-bal')) || 0;
    document.getElementById('card-total-balance').textContent = '$' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// External Accounts Dropdown
function toggleExternalAccounts() {
    const dropdown = document.getElementById('external-accounts-dropdown');
    const icon = document.getElementById('dropdown-icon');
    dropdown.classList.toggle('hidden');
    icon.style.transform = dropdown.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
}

// Transaction Modal
function viewTransaction(id, description, amount, created_at, type) {
    const modal = document.getElementById('transaction-modal');
    const content = document.getElementById('modal-content');
    
    const isCredit = parseFloat(amount) > 0;
    const sign = isCredit ? '+' : '−';
    const color = isCredit ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';

    let shortDesc = description.length > 48 ? description.substring(0, 45) + '...' : description;

    const created = new Date(created_at + 'Z');
    const now = new Date();
    const hoursDiff = (now - created) / (1000 * 60 * 60);
    let statusText = 'Pending';
    let statusClass = 'text-amber-600 dark:text-amber-400';
    if (hoursDiff >= 24) {
        statusText = 'Successful';
        statusClass = 'text-emerald-600 dark:text-emerald-400';
    }

    content.innerHTML = `
        <div class="text-center mb-8">
            <div class="${color} text-5xl font-bold mb-2">${sign}$${Math.abs(parseFloat(amount)).toFixed(2)}</div>
            <div class="text-slate-600 dark:text-slate-300 break-all">${shortDesc}</div>
        </div>
        <div class="grid grid-cols-2 gap-6 text-sm">
            <div><span class="text-slate-400">Date</span><br><span class="font-medium text-slate-800 dark:text-slate-100">${created.toLocaleString('en-NG', {timeZone: 'Africa/Lagos'})}</span></div>
            <div><span class="text-slate-400">Type</span><br><span class="capitalize font-medium text-slate-800 dark:text-slate-100">${type || '—'}</span></div>
            <div><span class="text-slate-400">Status</span><br><span class="${statusClass} font-medium">${statusText}</span></div>
            <div><span class="text-slate-400">ID</span><br><span class="font-mono text-slate-800 dark:text-slate-100">#${id}</span></div>
        </div>
    `;
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('transaction-modal').classList.add('hidden');
}

document.getElementById('transaction-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Notifications SSE - Real-time updates
function startNotifications() {
    const badge = document.getElementById('notif-count');

    const eventSource = new EventSource('notifications_sse.php');
    eventSource.onmessage = function(event) {
        const count = parseInt(event.data) || 0;
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };
}
startNotifications();
</script>

</body>
</html>
