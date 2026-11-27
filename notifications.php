<?php
// notifications.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

function shortenEmailInText($text) {
    return preg_replace_callback(
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        function ($matches) {
            $email = $matches[0];

            if (strlen($email) <= 12) return $email;

            $first4 = substr($email, 0, 4);
            $last4  = substr($email, -4);

            return $first4 . '...' . $last4;
        },
        $text
    );
}

// ======================
// MARK AS READ when user opens this page
// ======================
$stmt = $pdo->prepare("
    UPDATE transactions 
    SET read_at = NOW() 
    WHERE user_id = ? AND read_at IS NULL
");
$stmt->execute([$user_id]);

// ======================
// Handle delete actions
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            echo 'success';
            exit;
        } 
        elseif ($_POST['action'] === 'delete_all') {
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo 'success';
            exit;
        } 
        elseif ($_POST['action'] === 'delete_selected' && isset($_POST['ids'])) {
            $ids = json_decode($_POST['ids'], true);
            if (is_array($ids) && !empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
                $stmt->execute(array_merge($ids, [$user_id]));
            }
            echo 'success';
            exit;
        }
    }
}

// Fetch ALL messages (including already read ones - they stay until deleted)
$stmt = $pdo->prepare("
    SELECT 
        id, 
        description, 
        amount, 
        type, 
        created_at,
        DATE_FORMAT(created_at, '%b %d, %Y • %h:%i %p') AS display_date,
        read_at
    FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 100
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications • MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .activity-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .activity-card:hover {
            transform: translateX(4px);
            background-color: #1f2937;
        }
        .amount-positive { color: #34d399; }
        .amount-negative { color: #f87171; }
        .message-line {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
            max-width: 100%;
        }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content {
            background: #1f2937;
            border-radius: 20px;
            max-width: 420px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .unread { border-left: 4px solid #3b82f6; }
        /* Extra safety for long crypto wallet / description text */
        .crypto-safe {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

<header class="sticky top-0 z-30 bg-slate-950/90 backdrop-blur-md border-b border-slate-800 px-5 py-4 flex items-center justify-between">
    <a href="index.php" class="text-slate-400 hover:text-white">
        <i class="fa-solid fa-arrow-left text-xl"></i>
    </a>
    <h1 class="text-2xl font-semibold">Activity</h1>
    <?php if (!empty($transactions)): ?>
    <div class="flex gap-3">
        <button onclick="deleteAll()" 
                class="text-sm bg-red-900/70 hover:bg-red-800 px-4 py-2 rounded-2xl flex items-center gap-2">
            <i class="fa-solid fa-trash"></i> Delete All
        </button>
        <button id="delete-selected-btn" onclick="deleteSelected()" 
                class="hidden text-sm bg-red-900/70 hover:bg-red-800 px-4 py-2 rounded-2xl flex items-center gap-2">
            <i class="fa-solid fa-trash"></i> Delete Selected
        </button>
    </div>
    <?php endif; ?>
</header>

<div class="p-5 max-w-3xl mx-auto">

    <?php if (empty($transactions)): ?>
        <div class="bg-slate-900 rounded-3xl p-16 text-center mt-10">
            <i class="fa-solid fa-bell-slash text-6xl text-slate-600 mb-6"></i>
            <h3 class="text-2xl font-medium text-slate-300">No notifications yet</h3>
            <p class="text-slate-500 mt-3">Your activity will appear here.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4" id="notifications-list">
            <?php foreach ($transactions as $tx): 
                $isPositive = ($tx['amount'] ?? 0) > 0;
                $sign = $isPositive ? '+' : '−';
                $amountClass = $isPositive ? 'amount-positive' : 'amount-negative';
                $iconClass = $isPositive ? 'fa-arrow-down text-emerald-400' : 'fa-arrow-up text-red-400';
                $isUnread = empty($tx['read_at']);

                // Clean long crypto descriptions + replace "user #" with wallet if present
                $rawDesc = $tx['description'] ?? 'Transaction completed';
                $displayDesc = $rawDesc;

                if (preg_match('/bitcoin\s+received\s+from\s+user\s*#?\d+/i', $rawDesc) ||
                    preg_match('/btc\s+received\s+from\s+user\s*#?\d+/i', $rawDesc) ||
                    preg_match('/received\s+from\s+user\s*#?\d+/i', $rawDesc)) {
                    if (preg_match('/\b(bc1[a-zA-Z0-9]{25,}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})\b/', $rawDesc, $m)) {
                        $displayDesc = 'Bitcoin received from ' . $m[1];
                    } else {
                        $displayDesc = 'Bitcoin received';
                    }
                }

                $displayDesc = htmlspecialchars(shortenEmailInText($displayDesc));
            ?>
            <div class="activity-card bg-slate-900 rounded-3xl p-6 border border-slate-700 flex gap-5 group <?= $isUnread ? 'unread' : '' ?>" 
                 data-id="<?= $tx['id'] ?>">
                
                <div class="flex items-center">
                    <input type="checkbox" class="select-checkbox w-5 h-5 accent-red-500" 
                           onchange="toggleDeleteButton()">
                </div>

                <div class="flex-1 flex gap-5" onclick='showDetails(<?= json_encode($tx) ?>)'>
                    <div class="w-12 h-12 bg-slate-800 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid <?= $iconClass ?> text-2xl"></i>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-base message-line <?= $isUnread ? 'text-white' : 'text-slate-300' ?>">
                          <?= $displayDesc ?>
                        </div>
                        <p class="text-xs text-slate-500 mt-3">
                            <?= $tx['display_date'] ?>
                        </p>
                    </div>
                </div>

                <div class="text-right flex-shrink-0">
                    <div class="font-mono text-xl font-semibold <?= $amountClass ?>">
                        <?= $sign ?>$<?= number_format(abs($tx['amount'] ?? 0), 2) ?>
                    </div>
                    <div class="text-xs uppercase tracking-widest text-slate-500 mt-1">
                        <?= strtoupper($tx['type'] ?? 'TX') ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mt-12 text-center">
        <a href="index.php" class="inline-flex items-center gap-2 text-violet-400 hover:text-violet-300">
            ← Back to Dashboard
        </a>
    </div>
</div>

<div id="detail-modal" class="modal">
    <div class="modal-content p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold" id="modal-title">Transaction Details</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white text-3xl leading-none">&times;</button>
        </div>

        <div id="modal-content-area" class="space-y-6 text-slate-300">
            </div>

        <div class="flex gap-3 mt-8">
            <button id="modal-delete-btn" 
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-2xl font-medium transition">
                Delete Message
            </button>
            <button onclick="closeModal()" 
                    class="flex-1 bg-slate-700 hover:bg-slate-600 text-white py-3 rounded-2xl font-medium transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function toggleDeleteButton() {
    const checked = document.querySelectorAll('.select-checkbox:checked').length;
    const btn = document.getElementById('delete-selected-btn');
    if (btn) {
        if (checked > 0) btn.classList.remove('hidden');
        else btn.classList.add('hidden');
    }
}

function deleteAll() {
    if (!confirm('Are you sure you want to delete all notifications?')) return;
    fetch('notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_all'
    }).then(() => location.reload());
}

function deleteSelected() {
    const checkboxes = document.querySelectorAll('.select-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.closest('.activity-card').dataset.id);
    if (ids.length === 0) return;
    if (!confirm('Delete selected notifications?')) return;

    fetch('notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_selected&ids=' + JSON.stringify(ids)
    }).then(() => location.reload());
}

function showDetails(tx) {
    const modal = document.getElementById('detail-modal');
    const area = document.getElementById('modal-content-area');
    const isPositive = tx.amount > 0;
    const color = isPositive ? 'text-emerald-400' : 'text-red-400';

    // Clean long crypto descriptions in the modal too
    let cleanDesc = tx.description || 'Transaction completed';
    if (/bitcoin\s+received\s+from\s+user\s*#?\d+/i.test(cleanDesc) ||
        /btc\s+received\s+from\s+user\s*#?\d+/i.test(cleanDesc) ||
        /received\s+from\s+user\s*#?\d+/i.test(cleanDesc)) {
        const walletMatch = cleanDesc.match(/\b(bc1[a-zA-Z0-9]{25,}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})\b/);
        if (walletMatch) {
            cleanDesc = 'Bitcoin received from ' + walletMatch[1];
        } else {
            cleanDesc = 'Bitcoin received';
        }
    }

    area.innerHTML = `
        <div class="text-center py-4">
            <div class="text-4xl font-mono font-bold ${color}">${isPositive ? '+' : '-'}$${Math.abs(tx.amount).toFixed(2)}</div>
            <div class="text-slate-400 mt-2">${tx.type.toUpperCase()}</div>
        </div>
        <div class="bg-slate-800/50 p-4 rounded-2xl border border-slate-700">
            <p class="text-white leading-relaxed text-lg crypto-safe">${cleanDesc}</p>
        </div>
        <div class="text-sm text-slate-500 text-center">${tx.display_date}</div>
    `;

    document.getElementById('modal-delete-btn').onclick = function() {
        deleteFromModal(tx.id);
    };

    modal.style.display = "flex";
}

function deleteFromModal(id) {
    if (!confirm('Delete this notification?')) return;
    fetch('notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&id=${id}`
    }).then(() => {
        closeModal();
        location.reload();
    });
}

function closeModal() {
    document.getElementById('detail-modal').style.display = "none";
}

// Ensure outside click closes modal
window.onclick = function(event) {
    const modal = document.getElementById('detail-modal');
    if (event.target == modal) closeModal();
}
</script>

</body>
</html>