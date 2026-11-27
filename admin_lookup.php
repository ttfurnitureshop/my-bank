<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['is_staff'])) { exit("Denied"); }

$search = $_GET['account_search'] ?? '';
$account = null; $transactions = [];

if ($search) {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name FROM accounts a JOIN users u ON a.user_id = u.id WHERE a.account_number = ? LIMIT 1");
    $stmt->execute([$search]);
    $account = $stmt->fetch();
    if ($account) {
        $tStmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
        $tStmt->execute([$account['user_id']]);
        $transactions = $tStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ledger Audit • <?= htmlspecialchars($search) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-6">
    <div class="max-w-5xl mx-auto">
        <header class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold">Investigation: <?= htmlspecialchars($account['full_name'] ?? 'Account '.$search) ?></h1>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Full Transaction Ledger</p>
            </div>
            <button onclick="history.back()" class="bg-slate-900 text-white text-[10px] px-4 py-2 rounded-lg font-bold">RETURN TO CHAT</button>
        </header>

        <?php if ($account): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm h-fit">
                    <span class="text-[10px] font-black text-slate-400 uppercase">Available Balance</span>
                    <p class="text-2xl font-black text-violet-600">$<?= number_format($account['balance'] ?? 0, 2) ?></p>
                </div>
                <div class="md:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase">
                            <tr><th class="p-4">Date</th><th class="p-4">Description</th><th class="p-4">Amount</th><th class="p-4">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($transactions as $t): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="p-4 text-slate-400"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                                <td class="p-4 font-bold"><?= htmlspecialchars($t['description']) ?></td>
                                <td class="p-4 font-black <?= $t['amount'] < 0 ? 'text-red-500' : 'text-emerald-600' ?>">$<?= number_format(abs($t['amount']), 2) ?></td>
                                <td class="p-4">
                                    <?php 
                                        // Prevents "Undefined index" error
                                        $st = $t['status'] ?? $t['STATUS'] ?? 'Completed'; 
                                    ?>
                                    <span class="px-2 py-1 rounded text-[9px] font-black uppercase bg-slate-100"><?= $st ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white p-20 text-center rounded-3xl border-2 border-dashed border-slate-200 text-slate-400">Account not found.</div>
        <?php endif; ?>
    </div>
</body>
</html>
