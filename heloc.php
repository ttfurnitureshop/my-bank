<?php
require 'config.php';

date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId  = $_SESSION['user_id'];
$message = '';
$error   = '';

// Check if a HELOC account already exists for this user
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? AND LOWER(TRIM(name)) = 'heloc' LIMIT 1");
$stmt->execute([$userId]);
$helocAccount = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Application Request with User-Submitted Financial Information
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_heloc') {
    if ($helocAccount) {
        $error = "You already have an active HELOC account. Multiple applications are not permitted.";
    } else {
        $propertyValue  = (float)($_POST['property_value'] ?? 0);
        $mortgageBalance = (float)($_POST['mortgage_balance'] ?? 0);
        $annualIncome   = (float)($_POST['annual_income'] ?? 0);
        $employment     = trim($_POST['employment_status'] ?? '');
        $requestedCredit = (float)($_POST['requested_credit'] ?? 0);

        // Validation logic
        if ($propertyValue <= 0 || $annualIncome <= 0 || $requestedCredit <= 0) {
            $error = "Please enter valid monetary values for property, income, and requested credit line.";
        } elseif (empty($employment)) {
            $error = "Please select your employment status.";
        } elseif ($mortgageBalance >= $propertyValue) {
            $error = "Your current mortgage balance cannot exceed or equal the property value.";
        } else {
            // Calculate Maximum Credit Limit (e.g., up to 80% LTV minus existing mortgage)
            $equity = $propertyValue - $mortgageBalance;
            $maxBorrowable = max(0, ($propertyValue * 0.80) - $mortgageBalance);

            if ($requestedCredit > $maxBorrowable) {
                $error = "Based on your property equity, the maximum line of credit you qualify for is $" . number_format($maxBorrowable, 2) . ".";
            } else {
                $helocAccNum = '99' . str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
                $defaultRoutingNum = '021000021';
                $approvedCredit = $requestedCredit;

                try {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO accounts (user_id, name, account_number, routing_number, balance) 
                        VALUES (?, 'HELOC', ?, ?, ?)
                    ");
                    $stmtInsert->execute([$userId, $helocAccNum, $defaultRoutingNum, $approvedCredit]);
                    
                    // Re-fetch created account
                    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? AND LOWER(TRIM(name)) = 'heloc' LIMIT 1");
                    $stmt->execute([$userId]);
                    $helocAccount = $stmt->fetch(PDO::FETCH_ASSOC);

                    $message = "Congratulations! Your Home Equity Line of Credit application for $" . number_format($approvedCredit, 2) . " has been approved and activated.";
                } catch (Exception $e) {
                    $error = "Failed to activate HELOC account. Please try again.";
                }
            }
        }
    }
}

// Handle Transfer of HELOC Balance to Dashboard Accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_heloc') {
    if (!$helocAccount) {
        $error = "No active HELOC account found.";
    } else {
        $transferAmount  = (float)($_POST['transfer_amount'] ?? 0);
        $targetAccountId = (int)($_POST['target_account_id'] ?? 0);

        if ($transferAmount <= 0) {
            $error = "Please enter a valid transfer amount.";
        } elseif ($transferAmount > (float)$helocAccount['balance']) {
            $error = "Transfer amount exceeds your available HELOC balance ($" . number_format($helocAccount['balance'], 2) . ").";
        } else {
            $stmtTarget = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
            $stmtTarget->execute([$targetAccountId, $userId]);
            $targetAccount = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetAccount) {
                $error = "Target account not found.";
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtDeduct = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                    $stmtDeduct->execute([$transferAmount, $helocAccount['id']]);

                    $stmtAdd = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                    $stmtAdd->execute([$transferAmount, $targetAccountId]);

                    $stmtTx1 = $pdo->prepare("
                        INSERT INTO transactions (user_id, description, amount, type, created_at) 
                        VALUES (?, ?, ?, 'withdraw', NOW())
                    ");
                    $stmtTx1->execute([$userId, "HELOC Draw to " . $targetAccount['name'], -$transferAmount]);

                    $stmtTx2 = $pdo->prepare("
                        INSERT INTO transactions (user_id, description, amount, type, created_at) 
                        VALUES (?, ?, ?, 'deposit', NOW())
                    ");
                    $stmtTx2->execute([$userId, "HELOC Advance Credit", $transferAmount]);

                    $pdo->commit();

                    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? AND LOWER(TRIM(name)) = 'heloc' LIMIT 1");
                    $stmt->execute([$userId]);
                    $helocAccount = $stmt->fetch(PDO::FETCH_ASSOC);

                    $message = "Successfully transferred $" . number_format($transferAmount, 2) . " to your " . htmlspecialchars($targetAccount['name']) . " account!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Transfer failed. Please try again.";
                }
            }
        }
    }
}

// Fetch checking/savings accounts for transfers
$stmtOther = $pdo->prepare("SELECT id, name, account_number, balance FROM accounts WHERE user_id = ? AND LOWER(TRIM(name)) != 'heloc'");
$stmtOther->execute([$userId]);
$dashboardAccounts = $stmtOther->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MyBank • HELOC Application & Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        tailwind.config = { darkMode: 'class' }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen">

<main class="max-w-2xl mx-auto p-5 sm:p-8">

    <div class="flex items-center justify-between mb-8">
        <a href="index.php" class="flex items-center gap-2 text-violet-600 dark:text-violet-400 hover:underline font-medium text-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-house-chimney-window text-emerald-500"></i> HELOC Center
        </h1>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-sm font-medium flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-lg"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 text-sm font-medium flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-lg"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($helocAccount): ?>
        <!-- ACTIVE HELOC CARD -->
        <div class="bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-900 text-white rounded-3xl p-6 shadow-xl mb-6 border border-white/10">
            <div class="flex justify-between items-center mb-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500/20 text-emerald-200 rounded-full text-xs font-semibold border border-emerald-400/30">
                    <i class="fa-solid fa-circle-check"></i> Approved & Active
                </span>
                <span class="text-xs font-mono opacity-80">Acc: *<?= htmlspecialchars(substr((string)$helocAccount['account_number'], -4)) ?></span>
            </div>

            <div class="mb-6">
                <span class="text-xs uppercase tracking-wider text-emerald-200 font-medium">Available Credit Line</span>
                <div class="text-4xl sm:text-5xl font-extrabold font-mono mt-1">
                    $<?= number_format((float)$helocAccount['balance'], 2) ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/10 text-xs">
                <div>
                    <span class="text-emerald-200 block mb-0.5">Account Status</span>
                    <span class="font-semibold text-emerald-300">Active</span>
                </div>
                <div>
                    <span class="text-emerald-200 block mb-0.5">Routing Number</span>
                    <span class="font-mono font-semibold"><?= htmlspecialchars($helocAccount['routing_number'] ?: '021000021') ?></span>
                </div>
            </div>
        </div>

        <!-- HELOC TRANSFER SECTION -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h3 class="text-lg font-semibold mb-1 text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-arrow-right-arrow-left text-emerald-500"></i> Draw Funds to Dashboard Account
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Transfer money from your HELOC into your Checking or Savings account.</p>

            <?php if (!empty($dashboardAccounts)): ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="transfer_heloc">

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Transfer To Account</label>
                        <select name="target_account_id" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                            <?php foreach ($dashboardAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['name']) ?> (*<?= htmlspecialchars(substr((string)$acc['account_number'], -4)) ?>) — Bal: $<?= number_format((float)$acc['balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Amount to Draw ($)</label>
                        <input type="number" step="0.01" name="transfer_amount" min="1" max="<?= (float)$helocAccount['balance'] ?>" placeholder="Enter amount" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-slate-800 dark:text-slate-100">
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition shadow-lg shadow-emerald-600/20 text-sm flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Draw Funds Now
                    </button>
                </form>
            <?php else: ?>
                <div class="text-sm text-slate-500 dark:text-slate-400 py-4 text-center">
                    No active Checking or Savings accounts found to transfer funds into.
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- DETAILED APPLICATION FORM -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">
                    <i class="fa-solid fa-house-chimney-user"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Apply for a HELOC Account</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Please fill in your property and financial details below to request approval.</p>
            </div>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="apply_heloc">

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Estimated Home Value ($)</label>
                        <input type="number" step="1000" name="property_value" placeholder="e.g. 400000" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Mortgage Balance ($)</label>
                        <input type="number" step="1000" name="mortgage_balance" placeholder="e.g. 150000" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Annual Household Income ($)</label>
                        <input type="number" step="1000" name="annual_income" placeholder="e.g. 85000" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Employment Status</label>
                        <select name="employment_status" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-slate-800 dark:text-slate-100">
                            <option value="">Select status</option>
                            <option value="Employed">Employed Full-Time</option>
                            <option value="Self-Employed">Self-Employed</option>
                            <option value="Part-Time">Part-Time</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Requested Line of Credit ($)</label>
                    <input type="number" step="1000" name="requested_credit" placeholder="e.g. 50000" required class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-slate-800 dark:text-slate-100">
                </div>

                <button type="submit" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl transition shadow-lg shadow-emerald-600/30 text-base">
                    Submit HELOC Application
                </button>
            </form>
        </div>
    <?php endif; ?>

</main>

</body>
</html>
