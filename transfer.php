<?php
session_start();
require 'config.php';

// Force Africa/Lagos timezone
date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

// Helper: always generate UTC timestamp for storage
function getUtcNow(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->format('Y-m-d H:i:s');
}

// =============================================================================
// Fetch user's internal accounts
// =============================================================================
$stmt = $pdo->prepare("SELECT id, name, account_number, balance FROM accounts WHERE user_id = ?");
$stmt->execute([$user_id]);
$internal_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Credit cards — available = credit_limit - balance
$credit_cards = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, card_number, name_on_card, card_type, credit_limit, balance,
               (credit_limit - balance) AS available
        FROM credit_cards
        WHERE user_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $credit_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $credit_cards = [];
}

// =============================================================================
// Fetch verified external accounts (for ACH)
// =============================================================================
$stmt = $pdo->prepare("
    SELECT id, account_holder, account_number, routing_number, account_type,
           verification_status, verified_at
    FROM external_accounts
    WHERE user_id = ? AND verification_status = 'verified'
    ORDER BY verified_at DESC
");
$stmt->execute([$user_id]);
$verified_external = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================================================
// Pending manual verification (if any)
// =============================================================================
$stmt = $pdo->prepare("
    SELECT id, account_number, micro_deposit_1, micro_deposit_2
    FROM external_accounts 
    WHERE user_id = ? AND verification_status = 'pending'
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$user_id]);
$pending_manual = $stmt->fetch(PDO::FETCH_ASSOC);

$show_verification_form = (bool)$pending_manual;
$pending_external_id    = $pending_manual['id'] ?? null;
$linked_last4           = substr($pending_manual['account_number'] ?? '', -4);

// =============================================================================
// Active recurring transfers
// =============================================================================
$stmt = $pdo->prepare("
    SELECT r.*, a1.name as from_name, a2.name as to_name 
    FROM recurring_transfers r
    JOIN accounts a1 ON r.from_account_id = a1.id
    JOIN accounts a2 ON r.to_account_id = a2.id
    WHERE r.user_id = ? AND r.active = 1
    ORDER BY r.next_execution_date ASC
");
$stmt->execute([$user_id]);
$recurring = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================================================
// POST handling + PRG (prevent resubmit on refresh)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        // ─── 1. Internal Transfer (bank account OR credit card) ─────────
        if ($action === 'internal_transfer') {
            $from_raw = trim($_POST['from_account'] ?? '');
            $to_id    = (int)($_POST['to_account'] ?? 0);
            $amount   = (float)str_replace([',', '$'], '', $_POST['amount'] ?? '0');
            $memo     = trim($_POST['memo'] ?? '');
            $createdAt = getUtcNow();

            if ($amount <= 0) throw new Exception("Amount must be greater than zero.");

            $isCredit = (strpos($from_raw, 'cc_') === 0);

            if ($isCredit) {
                // Fund from credit card available balance
                $cc_id = (int)substr($from_raw, 3);
                $stmt = $pdo->prepare("
                    SELECT id, card_type, credit_limit, balance, (credit_limit - balance) AS available
                    FROM credit_cards WHERE id = ? AND user_id = ? AND status = 'active'
                ");
                $stmt->execute([$cc_id, $user_id]);
                $cc = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$cc) throw new Exception("Credit card not found.");
                if ((float)$cc['available'] < $amount) {
                    throw new Exception("Insufficient available credit. Available: $" . number_format($cc['available'], 2));
                }

                // Increase card balance (amount used against limit)
                $pdo->prepare("UPDATE credit_cards SET balance = balance + ? WHERE id = ? AND user_id = ?")
                    ->execute([$amount, $cc_id, $user_id]);

                // Credit destination bank account
                $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$to_id, $user_id]);
                if (!$stmt->fetchColumn()) throw new Exception("Destination account not found.");

                $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")
                    ->execute([$amount, $to_id]);

                $desc = $memo
                    ? "Credit card transfer ({$cc['card_type']}) - $memo"
                    : "Credit card transfer ({$cc['card_type']})";

                $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at)
                               VALUES (?, ?, 'credit_spend', ?, ?, ?)")
                    ->execute([$user_id, $to_id, -$amount, $desc . ' (from card)', $createdAt]);

                $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at)
                               VALUES (?, ?, 'transfer_in', ?, ?, ?)")
                    ->execute([$user_id, $to_id, $amount, $desc, $createdAt]);

                $success = "Transferred $" . number_format($amount, 2) . " from your credit card to the selected account.";
            } else {
                $from_id = (int)$from_raw;
                if ($from_id === $to_id) throw new Exception("Cannot transfer to the same account.");

                $stmt = $pdo->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$from_id, $user_id]);
                $from_balance = $stmt->fetchColumn();

                if ($from_balance === false || $from_balance < $amount) {
                    throw new Exception("Insufficient funds in source account.");
                }

                $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?")
                    ->execute([$amount, $from_id]);

                $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")
                    ->execute([$amount, $to_id]);

                $desc = $memo ? "Internal transfer - $memo" : "Internal transfer";

                $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at)
                               VALUES (?, ?, 'transfer_out', ?, ?, ?)")
                    ->execute([$user_id, $from_id, -$amount, $desc, $createdAt]);

                $pdo->prepare("INSERT INTO transactions (user_id, account_id, type, amount, description, created_at)
                               VALUES (?, ?, 'transfer_in', ?, ?, ?)")
                    ->execute([$user_id, $to_id, $amount, $desc, $createdAt]);

                $success = "Internal transfer of $" . number_format($amount, 2) . " completed successfully.";
            }
        }

        // ─── 2. Create Recurring Internal Transfer ──────────────────────
        elseif ($action === 'create_recurring') {
            $from_id = (int)($_POST['from_account'] ?? 0);
            $to_id   = (int)($_POST['to_account']   ?? 0);
            $amount  = (float)($_POST['amount'] ?? 0);
            $freq    = $_POST['frequency'] ?? '';
            $memo    = trim($_POST['memo'] ?? '');

            if ($amount <= 0) throw new Exception("Amount must be greater than 0.");
            if ($from_id === $to_id) throw new Exception("Cannot transfer to the same account.");
            if (!in_array($freq, ['weekly', 'biweekly', 'monthly'])) {
                throw new Exception("Invalid frequency selected.");
            }

            if ($freq === 'weekly') {
                $interval = '7 days';
            } elseif ($freq === 'biweekly') {
                $interval = '14 days';
            } elseif ($freq === 'monthly') {
                $interval = '1 month';
            } else {
                $interval = '7 days';
            }

            $next_date = date('Y-m-d', strtotime("+ $interval"));

            $stmt = $pdo->prepare("
                INSERT INTO recurring_transfers 
                (user_id, from_account_id, to_account_id, amount, frequency, next_execution_date, memo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $from_id, $to_id, $amount, $freq, $next_date, $memo]);

            $success = "Recurring $freq transfer of $" . number_format($amount, 2) . " scheduled. Next run: $next_date";
        }

        // ─── 3. Manual external account linking ─────────────────────────
        elseif ($action === 'link_manual') {
            $routing   = trim($_POST['routing_number'] ?? '');
            $account   = trim($_POST['account_number'] ?? '');
            $holder    = trim($_POST['account_holder'] ?? '');
            $type      = $_POST['account_type'] ?? 'checking';

            if (strlen($routing) !== 9 || !ctype_digit($routing)) {
                throw new Exception("Routing number must be exactly 9 digits.");
            }
            if (strlen($account) < 6 || strlen($account) > 17) {
                throw new Exception("Account number should be 6–17 digits.");
            }
            if (empty($holder)) {
                throw new Exception("Account holder name is required.");
            }

            $stmt = $pdo->prepare("
                SELECT 1 FROM external_accounts 
                WHERE user_id = ? AND routing_number = ? AND account_number = ?
            ");
            $stmt->execute([$user_id, $routing, $account]);
            if ($stmt->fetchColumn()) {
                throw new Exception("This account is already linked.");
            }

            $dep1 = round(mt_rand(10, 98) / 100, 2);
            $dep2 = round(mt_rand(10, 98) / 100, 2);

            $stmt = $pdo->prepare("
                INSERT INTO external_accounts 
                (user_id, routing_number, account_number, account_holder, account_type,
                 verification_status, micro_deposit_1, micro_deposit_2)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$user_id, $routing, $account, $holder, $type, $dep1, $dep2]);

            $stmt_target = $pdo->prepare("
                SELECT id, user_id 
                FROM accounts 
                WHERE account_number = ? 
                LIMIT 1
            ");
            $stmt_target->execute([$account]);
            $target = $stmt_target->fetch(PDO::FETCH_ASSOC);

            if ($target) {
                $target_acc_id   = $target['id'];
                $target_user_id  = $target['user_id'];
                $createdAt = getUtcNow();   // ← FIXED

                $pdo->prepare("
                    INSERT INTO transactions 
                    (user_id, account_id, type, amount, description, created_at)
                    VALUES (?, ?, 'deposit', ?, 'Micro-deposit verification (test deposit #1)', ?)
                ")->execute([$target_user_id, $target_acc_id, $dep1, $createdAt]);

                $pdo->prepare("
                    INSERT INTO transactions 
                    (user_id, account_id, type, amount, description, created_at)
                    VALUES (?, ?, 'deposit', ?, 'Micro-deposit verification (test deposit #2)', ?)
                ")->execute([$target_user_id, $target_acc_id, $dep2, $createdAt]);

                $pdo->prepare("
                    UPDATE accounts 
                    SET balance = balance + ? 
                    WHERE id = ?
                ")->execute([$dep1 + $dep2, $target_acc_id]);
            }

            $success = "Manual account linked. Two small test deposits were sent. Verify them below.";
            $show_verification_form = true;
            $pending_external_id    = $pdo->lastInsertId();
            $linked_last4           = substr($account, -4);
        }

        // ─── 4. Verify micro-deposits ──────────────────────────────────
        elseif ($action === 'verify_micro') {
            $ext_id = (int)($_POST['external_account_id'] ?? 0);
            $d1     = (float)($_POST['deposit_1'] ?? 0);
            $d2     = (float)($_POST['deposit_2'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT micro_deposit_1, micro_deposit_2 
                FROM external_accounts 
                WHERE id = ? AND user_id = ? AND verification_status = 'pending'
            ");
            $stmt->execute([$ext_id, $user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception("No pending verification found.");

            if (abs($d1 - $row['micro_deposit_1']) <= 0.02 &&
                abs($d2 - $row['micro_deposit_2']) <= 0.02) {
                $pdo->prepare("
                    UPDATE external_accounts 
                    SET verification_status = 'verified', verified_at = NOW()
                    WHERE id = ? AND user_id = ?
                ")->execute([$ext_id, $user_id]);

                $success = "Micro-deposits verified successfully! Your linked account should now appear below.";
                $show_verification_form = false;

                // Refresh verified list
                $stmt = $pdo->prepare("
                    SELECT id, account_holder, account_number, routing_number, account_type,
                           verification_status, verified_at
                    FROM external_accounts
                    WHERE user_id = ? AND verification_status = 'verified'
                    ORDER BY verified_at DESC
                ");
                $stmt->execute([$user_id]);
                $verified_external = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = "Amounts do not match. Please check your bank statement and try again.";
            }
        }

        // ─── 5. Transfer to External (ACH) Account ──────────────────────
        elseif ($action === 'ach_external_transfer') {
            $from_raw  = trim($_POST['from_account'] ?? '');
            $to_ext_id = (int)($_POST['to_external'] ?? 0);
            $amount    = (float)str_replace([',', '$'], '', $_POST['amount'] ?? '0');
            $memo      = trim($_POST['memo'] ?? '');
            $isCredit  = (strpos($from_raw, 'cc_') === 0);
            $from_id   = $isCredit ? 0 : (int)$from_raw;
            $cc_id     = $isCredit ? (int)substr($from_raw, 3) : 0;

            if ($amount <= 0) throw new Exception("Amount must be greater than zero.");

            // Get sender user info
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $sender_user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sender_user) throw new Exception("User not found.");

            $sender_data = [
                'email'     => $sender_user['email'],
                'full_name' => $sender_user['full_name'],
                'balance'   => 0,
            ];

            if ($isCredit) {
                $stmt = $pdo->prepare("
                    SELECT id, card_type, credit_limit, balance, (credit_limit - balance) AS available
                    FROM credit_cards WHERE id = ? AND user_id = ? AND status = 'active'
                ");
                $stmt->execute([$cc_id, $user_id]);
                $cc = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$cc) throw new Exception("Credit card not found.");
                if ((float)$cc['available'] < $amount) {
                    throw new Exception("Insufficient available credit. Available: $" . number_format($cc['available'], 2));
                }
                $sender_data['balance'] = (float)$cc['available'];
            } else {
                $stmt = $pdo->prepare("SELECT u.email, u.full_name, a.balance FROM accounts a JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.user_id = ?");
                $stmt->execute([$from_id, $user_id]);
                $sender_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$sender_data || $sender_data['balance'] < $amount) {
                    throw new Exception("Insufficient funds in source account.");
                }
            }

            // Get external account details (including raw account_number)
            $stmt = $pdo->prepare("
                SELECT id, account_holder, account_number,
                       CONCAT('•••• ', RIGHT(account_number, 4)) AS masked
                FROM external_accounts 
                WHERE id = ? AND user_id = ? AND verification_status = 'verified'
            ");
            $stmt->execute([$to_ext_id, $user_id]);
            $external = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$external) {
                throw new Exception("Invalid or unverified external account selected.");
            }

            // Generate Tx ID
            $txId = strtoupper(uniqid('ACH-'));
            $createdAt = getUtcNow();   // ← FIXED
            $date_now  = $createdAt;

            // ── Deduct from sender (bank account OR credit card) ────────
            if ($isCredit) {
                $pdo->prepare("UPDATE credit_cards SET balance = balance + ? WHERE id = ? AND user_id = ?")
                    ->execute([$amount, $cc_id, $user_id]);
            } else {
                $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?")
                    ->execute([$amount, $from_id]);
            }

            $desc_out = $memo 
                ? "ACH to {$external['masked']} - $memo" 
                : "ACH to {$external['masked']}";
            if ($isCredit) {
                $desc_out = "Credit card ACH — " . $desc_out;
            }
            // Log against a real account id when possible (credit spends use first internal account for history)
            $log_account_id = $from_id;
            if ($isCredit || !$log_account_id) {
                $log_account_id = $internal_accounts[0]['id'] ?? null;
            }
            $pdo->prepare("
                INSERT INTO transactions 
                (user_id, account_id, type, amount, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $user_id,
                $log_account_id,
                $isCredit ? 'credit_spend' : 'ach_out',
                -$amount,
                $desc_out,
                $createdAt
            ]);

            // ── Credit receiver + log on receiver side (if internal account exists) ──
            $stmt_target = $pdo->prepare("
                SELECT a.id, a.user_id, u.email, u.full_name 
                FROM accounts a 
                JOIN users u ON a.user_id = u.id
                WHERE a.account_number = ? 
                LIMIT 1
            ");
            $stmt_target->execute([$external['account_number']]);
            $target = $stmt_target->fetch(PDO::FETCH_ASSOC);

            if ($target) {
                $target_acc_id  = $target['id'];
                $target_user_id = $target['user_id'];

                // Credit receiver balance
                $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")
                    ->execute([$amount, $target_acc_id]);

                // Log incoming on receiver's transaction history
                $desc_in = $memo 
                    ? "ACH from MyBank - $memo" 
                    : "ACH transfer received";
                $pdo->prepare("
                    INSERT INTO transactions 
                    (user_id, account_id, type, amount, description, created_at)
                    VALUES (?, ?, 'ach_in', ?, ?, ?)
                ")->execute([$target_user_id, $target_acc_id, $amount, $desc_in, $createdAt]);

                // --- RECEIVER ALERT (CREDIT) ---
                if (function_exists('sendEmail')) {
                    $r_body = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden;'>
                        <div style='background:#0f172a;color:white;padding:25px;text-align:center;'><h2 style='margin:0;'>ACH Payment Received</h2></div>
                        <div style='padding:30px;background:#fff;'>
                            <p>Hello <strong>".htmlspecialchars($target['full_name'])."</strong>,</p>
                            <div style='background:#f0fdf4;padding:25px;border-radius:10px;text-align:center;margin:25px 0;border:1px solid #dcfce7;'>
                                <h1 style='color:#16a34a;margin:0;font-size:36px;'>+$" . number_format($amount, 2) . "</h1>
                                <p style='color:#166534;margin:5px 0 0;font-weight:bold;'>CREDIT</p>
                            </div>
                            <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;'>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>From:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>{$sender_data['full_name']}</td></tr>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Description:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>ACH Transfer Received</td></tr>
                                <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Transaction ID:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$txId</td></tr>
                                <tr><td style='padding:12px 0;'><strong>Memo:</strong></td><td style='padding:12px 0;text-align:right;'>" . ($memo ?: 'N/A') . "</td></tr>
                            </table>
                        </div>
                    </div>";
                    sendEmail($target['email'], "Credit Alert: ACH Payment Received", $r_body);
                }
            }

            // --- SENDER ALERT (DEBIT) ---
            if (function_exists('sendEmail')) {
                $s_body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden;'>
                    <div style='background:#0f172a;color:white;padding:25px;text-align:center;'><h2 style='margin:0;'>ACH Payment Sent</h2></div>
                    <div style='padding:30px;background:#fff;'>
                        <p>Hello <strong>".htmlspecialchars($sender_data['full_name'])."</strong>,</p>
                        <div style='background:#fdf2f2;padding:25px;border-radius:10px;text-align:center;margin:25px 0;border:1px solid #dcfce7;'>
                            <h1 style='color:#dc2626;margin:0;font-size:36px;'>-$" . number_format($amount, 2) . "</h1>
                            <p style='color:#991b1b;margin:5px 0 0;font-weight:bold;'>DEBIT</p>
                        </div>
                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;'>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>To:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>{$external['account_holder']} ({$external['masked']})</td></tr>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Description:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>ACH External Transfer</td></tr>
                            <tr><td style='padding:12px 0;border-bottom:1px solid #eee;'><strong>Transaction ID:</strong></td><td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;'>$txId</td></tr>
                            <tr><td style='padding:12px 0;'><strong>Date:</strong></td><td style='padding:12px 0;text-align:right;'>$date_now</td></tr>
                        </table>
                    </div>
                </div>";
                sendEmail($sender_data['email'], "Debit Alert: ACH Payment Sent", $s_body);
            }

            $success = "ACH transfer of $" . number_format($amount, 2) . " completed. Funds deducted from sender and credited to receiver.";
        }

        $pdo->commit();

        // ── PRG: redirect to self with success/error in session ───────────────
        if ($success) {
            $_SESSION['transfer_success'] = $success;
        }
        if ($error) {
            $_SESSION['transfer_error'] = $error;
        }

        header("Location: transfer.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ── Show messages from session (after redirect) ─────────────────────────────
if (isset($_SESSION['transfer_success'])) {
    $success = $_SESSION['transfer_success'];
    unset($_SESSION['transfer_success']);
}
if (isset($_SESSION['transfer_error'])) {
    $error = $_SESSION['transfer_error'];
    unset($_SESSION['transfer_error']);
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Transfers • MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .nav-active { @apply bg-slate-800 text-white; }
        .toggle-content { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

<aside class="fixed inset-y-0 left-0 z-40 w-72 bg-slate-900 border-r border-slate-800 transform -translate-x-full lg:translate-x-0 transition-transform lg:relative">
    <div class="p-6 border-b border-slate-800 flex items-center gap-3">
        <div class="w-10 h-10 bg-violet-600 rounded-2xl flex items-center justify-center text-3xl font-bold">M</div>
        <span class="text-3xl font-bold tracking-tighter">MyBank</span>
    </div>
    <nav class="p-4 space-y-1">
    
        <a href="index.php" class="nav-item"><i class="fa-solid fa-house w-5"></i> Dashboard</a>
        <a href="transfer.php" class="nav-item nav-active"><i class="fa-solid fa-arrow-right-arrow-left w-5"></i> Transfers</a>
        <a href="#" class="nav-item"><i class="fa-solid fa-wallet w-5"></i> Accounts</a>
        <div class="border-t border-slate-700 my-5"></div>
        <a href="logout.php" class="nav-item text-red-400 hover:text-red-300"><i class="fa-solid fa-right-from-bracket w-5"></i> Logout</a>
    </nav>
</aside>

<main class="lg:ml-72 p-6 lg:p-10">

    <h1 class="text-3xl font-bold mb-2">Transfers & Payments</h1>
    <p class="text-slate-400 mb-10">Internal • External ACH • Recurring</p>

    <?php if ($success): ?>
    <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-200 px-6 py-4 rounded-2xl mb-8">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-900/50 border border-red-700 text-red-200 px-6 py-4 rounded-2xl mb-8">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <div class="bg-slate-900 rounded-3xl border border-slate-800">
            <button onclick="toggleSection('internal')" 
                    class="w-full px-7 py-7 text-left flex items-center justify-between hover:bg-slate-800/50 rounded-t-3xl transition">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-arrow-right-arrow-left text-indigo-400"></i>
                    <h2 class="text-xl font-semibold">Internal Transfer</h2>
                </div>
                <i id="internal-icon" class="fa-solid fa-chevron-down transition-transform"></i>
            </button>
            <div id="internal-content" class="toggle-content px-7 pb-7">
                <form method="post">
                    <input type="hidden" name="action" value="internal_transfer">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-slate-400 mb-2">From Account / Card</label>
                            <select name="from_account" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                                <?php foreach ($internal_accounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> ($<?= number_format($a['balance'],2) ?>)</option>
                                <?php endforeach; ?>
                                <?php foreach ($credit_cards as $cc):
                                    $avail = (float)$cc['available'];
                                    $last4 = substr(preg_replace('/\s+/', '', $cc['card_number']), -4);
                                ?>
                                <option value="cc_<?= (int)$cc['id'] ?>">
                                    💳 <?= htmlspecialchars($cc['card_type'] ?? 'Credit Card') ?> •••• <?= $last4 ?> — Available $<?= number_format($avail, 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-2">To Account</label>
                            <select name="to_account" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                                <?php foreach ($internal_accounts as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-2">Amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                <input type="text" name="amount" required pattern="[0-9]+([,\.][0-9]{1,2})?" class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-10 pr-4 py-3">
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-400 mb-2">Memo (optional)</label>
                            <input type="text" name="memo" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 py-3.5 rounded-xl font-medium">
                            Transfer Now
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl border border-slate-800">
            <button onclick="toggleSection('recurring')" 
                    class="w-full px-7 py-7 text-left flex items-center justify-between hover:bg-slate-800/50 rounded-t-3xl transition">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-clock-rotate-left text-amber-400"></i>
                    <h2 class="text-xl font-semibold">Schedule Recurring Payment</h2>
                </div>
                <i id="recurring-icon" class="fa-solid fa-chevron-down transition-transform"></i>
            </button>
            <div id="recurring-content" class="toggle-content px-7 pb-7">
                <form method="post" class="space-y-5">
                    <input type="hidden" name="action" value="create_recurring">

                    <div>
                        <label class="block text-slate-400 mb-2">From Account</label>
                        <select name="from_account" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                            <?php foreach ($internal_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-2">To Account</label>
                        <select name="to_account" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                            <?php foreach ($internal_accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-2">Amount</label>
                        <input type="number" step="0.01" name="amount" required min="0.01" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-2">Frequency</label>
                        <select name="frequency" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Every 2 weeks</option>
                            <option value="monthly" selected>Monthly</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 mb-2">Memo (optional)</label>
                        <input type="text" name="memo" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                    </div>

                    <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 py-3.5 rounded-xl font-medium">
                        Schedule Recurring Payment
                    </button>
                </form>

                <?php if ($recurring): ?>
                <h3 class="text-lg font-medium mt-10 mb-4">Active Recurring Payments</h3>
                <div class="space-y-4">
                    <?php foreach ($recurring as $r): ?>
                    <div class="bg-slate-800/60 p-4 rounded-xl flex justify-between items-center text-sm">
                        <div>
                            <div class="font-medium"><?= htmlspecialchars($r['from_name']) ?> → <?= htmlspecialchars($r['to_name']) ?></div>
                            <div class="text-slate-500">$<?= number_format($r['amount'], 2) ?> • <?= ucfirst($r['frequency']) ?> • Next: <?= $r['next_execution_date'] ?></div>
                        </div>
                        <span class="text-emerald-400 text-xs font-medium">Active</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-900 rounded-3xl border border-slate-800">
            <button onclick="toggleSection('link')" 
                    class="w-full px-7 py-7 text-left flex items-center justify-between hover:bg-slate-800/50 rounded-t-3xl transition">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-building-columns text-cyan-400"></i>
                    <h2 class="text-xl font-semibold">Link External Account (Manual)</h2>
                </div>
                <i id="link-icon" class="fa-solid fa-chevron-down transition-transform"></i>
            </button>
            <div id="link-content" class="toggle-content px-7 pb-7">
                <?php if (!$show_verification_form): ?>
                <form method="post" class="space-y-6">
                    <input type="hidden" name="action" value="link_manual">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-slate-300 mb-2">Routing Number (9 digits) <span class="text-red-400">*</span></label>
                            <input type="text" name="routing_number" required pattern="\d{9}" maxlength="9" inputmode="numeric"
                                   placeholder="021000021"
                                   class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3.5 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                        </div>

                        <div>
                            <label class="block text-slate-300 mb-2">Account Number <span class="text-red-400">*</span></label>
                            <input type="text" name="account_number" required pattern="\d{6,17}" inputmode="numeric"
                                   placeholder="123456789012"
                                   class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3.5 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-2">Account Holder Name <span class="text-red-400">*</span></label>
                        <input type="text" name="account_holder" required placeholder="John A. Smith"
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3.5 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-slate-300 mb-2">Account Type</label>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="account_type" value="checking" checked class="w-4 h-4 accent-cyan-500">
                                <span>Checking</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="account_type" value="savings" class="w-4 h-4 accent-cyan-500">
                                <span>Savings</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 py-3.5 rounded-xl font-medium mt-4">
                        Link & Verify Account
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($show_verification_form): ?>
                <div class="space-y-8">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-cyan-900/40 text-cyan-300 mb-4">
                            <i class="fa-solid fa-envelope-open-dollar text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Verify Your Account</h3>
                        <p class="text-slate-400">
                            Two small deposits were sent to account ending in 
                            <strong class="text-white">•••• <?= htmlspecialchars($linked_last4) ?></strong>.<br>
                            Enter the exact amounts below.
                        </p>
                    </div>

                    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="action" value="verify_micro">
                        <input type="hidden" name="external_account_id" value="<?= $pending_external_id ?>">

                        <div>
                            <label class="block text-slate-300 mb-2">First deposit amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                <input type="number" step="0.01" name="deposit_1" required min="0" max="0.99" placeholder="0.00"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-10 pr-4 py-3.5 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 mb-2">Second deposit amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                <input type="number" step="0.01" name="deposit_2" required min="0" max="0.99" placeholder="0.00"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-10 pr-4 py-3.5 text-white focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition">
                            </div>
                        </div>

                        <div class="md:col-span-2 pt-4">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3.5 rounded-xl transition">
                                Verify Deposits
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($verified_external)): ?>
    <div class="mt-12 bg-slate-900 rounded-3xl border border-slate-800">
        <button onclick="toggleSection('ach')" 
                class="w-full px-7 py-7 text-left flex items-center justify-between hover:bg-slate-800/50 rounded-t-3xl transition">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-arrow-right-from-bracket text-purple-400"></i>
                <h2 class="text-xl font-semibold">Transfer to External Account (ACH)</h2>
            </div>
            <i id="ach-icon" class="fa-solid fa-chevron-down transition-transform"></i>
        </button>
        <div id="ach-content" class="toggle-content px-7 pb-7">
            <form method="post" class="space-y-6">
                <input type="hidden" name="action" value="ach_external_transfer">

                <div>
                    <label class="block text-slate-400 mb-2">From Account / Card</label>
                    <select name="from_account" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                        <?php foreach ($internal_accounts as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (Bal: $<?= number_format($a['balance'],2) ?>)</option>
                        <?php endforeach; ?>
                        <?php foreach ($credit_cards as $cc):
                            $avail = (float)$cc['available'];
                            $last4 = substr(preg_replace('/\s+/', '', $cc['card_number']), -4);
                        ?>
                        <option value="cc_<?= (int)$cc['id'] ?>">
                            💳 <?= htmlspecialchars($cc['card_type'] ?? 'Credit Card') ?> •••• <?= $last4 ?> — Available $<?= number_format($avail, 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-400 mb-2">To External Account</label>
                    <select name="to_external" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                        <?php foreach ($verified_external as $ext): ?>
                        <option value="<?= $ext['id'] ?>">
                            <?= htmlspecialchars($ext['account_holder']) ?> 
                            •••• <?= substr($ext['account_number'], -4) ?> 
                            (<?= ucfirst($ext['account_type']) ?>)
                            – Verified <?= date('M d, Y', strtotime($ext['verified_at'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-400 mb-2">Amount</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                        <input type="text" name="amount" required pattern="[0-9]+([,\.][0-9]{1,2})?" 
                               class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-10 pr-4 py-3">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 mb-2">Memo (optional)</label>
                    <input type="text" name="memo" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3">
                </div>

                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 py-3.5 rounded-xl font-medium">
                    Send via ACH
                </button>
            </form>

            <p class="text-sm text-slate-500 mt-4 text-center">
                Note: This is a simulation. Real ACH transfers may take 1–3 business days.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-12 bg-slate-900 rounded-3xl p-7 border border-slate-800">
        <h2 class="text-xl font-semibold mb-6 flex items-center gap-3">
            <i class="fa-solid fa-building-columns text-cyan-400"></i>
            Linked External Accounts (ACH)
        </h2>

        <?php if (empty($verified_external)): ?>
            <div class="text-center py-10 text-slate-500">
                No external accounts linked yet.<br>
                Use the form above to link a checking or savings account.
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($verified_external as $ext): ?>
                <div class="bg-slate-800/70 p-5 rounded-2xl flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                    <div>
                        <div class="font-medium text-lg"><?= htmlspecialchars($ext['account_holder']) ?></div>
                        <div class="text-slate-400 mt-1">
                            <span class="font-mono">•••• <?= substr($ext['account_number'], -4) ?></span>
                            <span class="mx-2">•</span>
                            <?= ucfirst($ext['account_type']) ?>
                            <span class="mx-2">•</span>
                            Verified <?= date('M d, Y', strtotime($ext['verified_at'])) ?>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-900/60 text-emerald-300 border border-emerald-700/50">
                        Verified
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-10 flex justify-center">
            <a href="index.php" 
               class="inline-flex items-center gap-3 bg-slate-800 hover:bg-slate-700 transition-colors text-slate-300 hover:text-white px-8 py-4 rounded-2xl font-medium">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

</main>

<script>
function toggleSection(section) {
    const content = document.getElementById(section + '-content');
    const icon = document.getElementById(section + '-icon');
    if (content && icon) {
        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }
}
</script>

</body>
</html>