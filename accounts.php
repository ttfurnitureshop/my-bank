<?php
require 'config.php';

date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? $username;

// Internal accounts - fetches unique accounts per user using GROUP BY to prevent duplicate displays
$stmt = $pdo->prepare("
    SELECT id, name, account_number, routing_number, balance
    FROM accounts
    WHERE user_id = ?
    GROUP BY LOWER(TRIM(name))
    ORDER BY name ASC
");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Billing / profile address from users table
$billing = [
    'full_name' => $fullName,
    'email'     => '',
    'phone'     => '',
    'address'   => '',
    'city'      => '',
    'state'     => '',
    'zip'       => '',
    'country'   => '',
];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $map = [
        'full_name' => ['full_name', 'fullname', 'name'],
        'email'     => ['email', 'email_address'],
        'phone'     => ['phone', 'phone_number', 'mobile'],
        'address'   => ['address', 'street_address', 'billing_address', 'home_address'],
        'city'      => ['city', 'billing_city'],
        'state'     => ['state', 'billing_state', 'region'],
        'zip'       => ['zip', 'zip_code', 'postal_code', 'billing_zip'],
        'country'   => ['country', 'billing_country'],
    ];
    foreach ($map as $key => $candidates) {
        foreach ($candidates as $col) {
            if (!empty($userRow[$col])) {
                $billing[$key] = $userRow[$col];
                break;
            }
        }
    }
    if (!empty($billing['full_name'])) {
        $fullName = $billing['full_name'];
    }
} catch (Exception $e) {}
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

    <div class="p-5 sm:p-8 max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-violet-600 dark:text-violet-400">Account information</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Account numbers, routing numbers, and billing details</p>
        </div>

        <!-- Bank Accounts -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            <h2 class="text-lg font-semibold flex items-center gap-3">
                <i class="fa-solid fa-building-columns text-blue-500"></i>
                Your accounts
            </h2>

            <?php if (empty($accounts)): ?>
                <p class="text-slate-500 text-sm py-6 text-center">No accounts found.</p>
            <?php else: ?>
                <?php foreach ($accounts as $acc): ?>
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 p-5 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($acc['name']) ?></h3>
                        <span class="font-mono text-sm text-emerald-600 dark:text-emerald-400">$<?= number_format((float)$acc['balance'], 2) ?></span>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Account number</p>
                            <p class="font-mono text-slate-900 dark:text-slate-100 break-all"><?= htmlspecialchars($acc['account_number'] ?: '—') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Routing number</p>
                            <p class="font-mono text-slate-900 dark:text-slate-100"><?= htmlspecialchars($acc['routing_number'] ?: '021000021') ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Billing Address -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-lg font-semibold flex items-center gap-3 mb-5">
                <i class="fa-solid fa-location-dot text-violet-500"></i>
                Billing address
            </h2>
            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Name</p>
                    <p class="font-medium"><?= htmlspecialchars($billing['full_name'] ?: '—') ?></p>
                </div>
                <?php if ($billing['email']): ?>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Email</p>
                    <p class="font-medium break-all"><?= htmlspecialchars($billing['email']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($billing['phone']): ?>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Phone</p>
                    <p class="font-medium"><?= htmlspecialchars($billing['phone']) ?></p>
                </div>
                <?php endif; ?>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Street address</p>
                    <p class="font-medium"><?= htmlspecialchars($billing['address'] ?: '—') ?></p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">City</p>
                        <p class="font-medium"><?= htmlspecialchars($billing['city'] ?: '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">State</p>
                        <p class="font-medium"><?= htmlspecialchars($billing['state'] ?: '—') ?></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">ZIP / Postal</p>
                        <p class="font-medium"><?= htmlspecialchars($billing['zip'] ?: '—') ?></p>
                    </div>
                </div>
                <?php if ($billing['country']): ?>
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Country</p>
                    <p class="font-medium"><?= htmlspecialchars($billing['country']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 mt-6">
                Update your address anytime in <a href="profile.php" class="text-violet-600 dark:text-violet-400 hover:underline">Profile</a>.
            </p>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <footer class="fixed bottom-0 left-0 right-0 lg:ml-72 bg-white/95 dark:bg-slate-900/95 border-t border-slate-200 dark:border-slate-800 px-5 py-4 flex justify-around z-40 backdrop-blur-md">
        <a href="index.php" class="flex flex-col items-center text-slate-500 hover:text-violet-600">
            <i class="fa-solid fa-house text-2xl"></i>
            <span class="text-xs mt-1">Main</span>
        </a>
        <a href="accounts.php" class="flex flex-col items-center text-blue-600 dark:text-blue-400">
            <i class="fa-solid fa-wallet text-2xl"></i>
            <span class="text-xs mt-1">Accounts</span>
        </a>
        <a href="transfer.php" class="flex flex-col items-center text-slate-500 hover:text-cyan-600">
            <i class="fa-solid fa-arrow-right-arrow-left text-2xl"></i>
            <span class="text-xs mt-1">Transfer</span>
        </a>
        <a href="crypto.php" class="flex flex-col items-center text-slate-500 hover:text-amber-600">
            <i class="fa-solid fa-bitcoin-sign text-2xl"></i>
            <span class="text-xs mt-1">Bitcoin</span>
        </a>
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
</script>

<script>
(function(){
  const btn = document.getElementById('theme-toggle');
  const darkIcon = document.getElementById('theme-toggle-dark-icon');
  const lightIcon = document.getElementById('theme-toggle-light-icon');
  if (!btn) return;
  if (document.documentElement.classList.contains('dark')) {
    if (lightIcon) lightIcon.classList.remove('hidden');
  } else {
    if (darkIcon) darkIcon.classList.remove('hidden');
  }
  btn.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    if (darkIcon) darkIcon.classList.toggle('hidden');
    if (lightIcon) lightIcon.classList.toggle('hidden');
    localStorage.setItem('color-theme', isDark ? 'dark' : 'light');
  });
})();
</script>

</body>
</html>
