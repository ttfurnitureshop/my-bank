<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Handle card linking
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Unlink card
    if (isset($_POST['unlink_card'])) {
        $card_id = (int)($_POST['card_id'] ?? 0);
        if ($card_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM linked_cards WHERE id = ? AND user_id = ?");
            $stmt->execute([$card_id, $userId]);
            $message = "✅ Card unlinked successfully!";
        } else {
            $message = "❌ Invalid card.";
        }
    }
    // Link new card
    else {
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $name_on_card = trim($_POST['name_on_card'] ?? '');
        $expiry_month = str_pad($_POST['expiry_month'] ?? '', 2, '0', STR_PAD_LEFT);
        $expiry_year = $_POST['expiry_year'] ?? '';
        $cvv = trim($_POST['cvv'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');

        $expiry = $expiry_month . '/' . substr($expiry_year, -2);

        // ── Input Validation ──
        $errors = [];

        // Card number: digits only, length 13–19
        if (!preg_match('/^\d{13,19}$/', $card_number)) {
            $errors[] = "Card number must be 13–19 digits.";
        }

        // Name on card
        if (strlen($name_on_card) < 2 || strlen($name_on_card) > 60) {
            $errors[] = "Name on card must be between 2 and 60 characters.";
        }

        // Expiry – must not be in the past
        $current_year  = (int)date('Y');
        $current_month = (int)date('m');
        $exp_year  = (int)$expiry_year;
        $exp_month = (int)$expiry_month;

        if ($exp_year < $current_year || ($exp_year === $current_year && $exp_month < $current_month)) {
            $errors[] = "Card has already expired.";
        }

        // CVV: 3 or 4 digits
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = "CVV must be 3 or 4 digits.";
        }

        // Address fields
        if (strlen($address) < 3) {
            $errors[] = "Street address is required.";
        }
        if (strlen($city) < 2) {
            $errors[] = "City is required.";
        }
        if (strlen($state) < 2) {
            $errors[] = "State is required.";
        }
        if (!preg_match('/^[A-Za-z0-9\-\s]{3,12}$/', $zip)) {
            $errors[] = "ZIP / Postal code is invalid.";
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO linked_cards (user_id, card_number, name_on_card, expiry, cvv, address, city, state, zip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $card_number, $name_on_card, $expiry, $cvv, $address, $city, $state, $zip]);
            
            $message = "✅ Card linked successfully!";
        } else {
            $message = "❌ " . implode(' ', $errors);
        }
    }
}

// Fetch linked cards for this user
$stmt = $pdo->prepare("
    SELECT id, card_number, name_on_card, expiry 
    FROM linked_cards 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$linked_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank • Cards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 50;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show {
            display: flex;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

<div class="max-w-2xl mx-auto p-6">

    <div class="flex items-center gap-3 mb-8">
        <a href="index.php" class="text-violet-400 hover:text-violet-300">
            <i class="fa-solid fa-arrow-left text-2xl"></i>
        </a>
        <h1 class="text-3xl font-bold">Your Cards</h1>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-2xl <?= strpos($message, '✅') !== false ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Linked Cards (always visible when present) -->
    <div class="bg-slate-900 rounded-3xl p-8 border border-slate-800 mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold">Your Cards (<?= count($linked_cards) ?>)</h2>
            <?php if (!empty($linked_cards)): ?>
            <button type="button" id="show-add-card-btn"
                    class="px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-xl text-sm font-semibold transition">
                <i class="fa-solid fa-plus mr-1"></i> Add new card
            </button>
            <?php endif; ?>
        </div>
        
        <?php if (empty($linked_cards)): ?>
            <div class="text-center py-10">
                <i class="fa-solid fa-credit-card text-5xl text-slate-600 mb-4"></i>
                <p class="text-slate-400 mb-6">No cards linked yet.</p>
                <button type="button" id="show-add-card-btn-empty"
                        class="inline-flex items-center gap-2 px-6 py-3.5 bg-violet-600 hover:bg-violet-700 rounded-2xl font-semibold transition">
                    <i class="fa-solid fa-plus"></i> Add new card
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($linked_cards as $card): 
                    $last4 = substr($card['card_number'], -4);
                ?>
                <div class="flex justify-between items-center bg-slate-800/70 p-5 rounded-2xl">
                    <div class="flex items-center gap-4">
                        <i class="fa-solid fa-credit-card text-3xl text-violet-400"></i>
                        <div>
                            <div class="font-medium"><?= htmlspecialchars($card['name_on_card']) ?></div>
                            <div class="font-mono text-slate-400">•••• •••• •••• <?= $last4 ?> • Expires <?= htmlspecialchars($card['expiry']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-emerald-400 text-sm font-medium">Linked</span>
                        <button type="button" 
                                onclick="openUnlinkModal(<?= (int)$card['id'] ?>, '•••• <?= $last4 ?>')"
                                class="px-4 py-2 bg-red-600/20 hover:bg-red-600/40 text-red-400 border border-red-500/40 rounded-xl text-sm font-medium transition">
                            Unlink
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Link New Card Form (hidden until user clicks Add new card) -->
    <div id="add-card-section" class="bg-slate-900 rounded-3xl p-8 border border-slate-800 mb-10 hidden">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold flex items-center gap-3">
                <i class="fa-solid fa-credit-card text-violet-400"></i>
                Link New Card
            </h2>
            <button type="button" id="hide-add-card-btn"
                    class="text-slate-400 hover:text-white text-sm px-3 py-1.5 rounded-xl hover:bg-slate-800 transition">
                Cancel
            </button>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm text-slate-400 mb-2">Card Number</label>
                <input type="text" name="card_number" maxlength="19" 
                       class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 text-lg font-mono focus:outline-none focus:border-violet-500"
                       placeholder="4242 4242 4242 4242" required>
            </div>

            <div>
                <label class="block text-sm text-slate-400 mb-2">Name on Card</label>
                <input type="text" name="name_on_card" 
                       class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500"
                       placeholder="John Doe" required>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Expiry Month</label>
                    <select name="expiry_month" class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>"><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-2">Expiry Year</label>
                    <select name="expiry_year" class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                        <?php for($y = date('Y'); $y <= date('Y')+10; $y++): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm text-slate-400 mb-2">CVV</label>
                <input type="text" name="cvv" maxlength="4" 
                       class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 font-mono focus:outline-none focus:border-violet-500"
                       placeholder="123" required>
            </div>

            <div class="pt-4 border-t border-slate-700">
                <h3 class="text-lg font-medium mb-4">Billing Address</h3>
                <div class="space-y-4">
                    <input type="text" name="address" placeholder="Street Address" 
                           class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="city" placeholder="City" 
                               class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                        <input type="text" name="state" placeholder="State" 
                               class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                    </div>
                    
                    <input type="text" name="zip" placeholder="ZIP Code" maxlength="10"
                           class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 focus:outline-none focus:border-violet-500" required>
                </div>
            </div>

            <button type="submit" 
                    class="w-full bg-violet-600 hover:bg-violet-700 transition py-4 rounded-2xl font-semibold text-lg mt-6">
                Link Card
            </button>
        </form>
    </div>

<!-- Custom Unlink Confirmation Modal -->
<div id="unlinkModal" class="modal-overlay">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl p-8 max-w-sm w-full mx-4 text-center shadow-2xl">
        <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fa-solid fa-unlink text-3xl text-red-400"></i>
        </div>
        <h3 class="text-xl font-semibold mb-2">Unlink Card?</h3>
        <p class="text-slate-400 mb-2">Are you sure you want to unlink this card?</p>
        <p class="font-mono text-slate-300 mb-8" id="unlinkCardLabel"></p>
        
        <form method="POST" id="unlinkForm">
            <input type="hidden" name="card_id" id="unlinkCardId" value="">
            <div class="flex gap-3">
                <button type="button" onclick="closeUnlinkModal()"
                        class="flex-1 bg-slate-700 hover:bg-slate-600 py-3.5 rounded-2xl font-medium transition">
                    Cancel
                </button>
                <button type="submit" name="unlink_card"
                        class="flex-1 bg-red-600 hover:bg-red-700 py-3.5 rounded-2xl font-medium transition">
                    Yes, Unlink
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openUnlinkModal(cardId, label) {
    document.getElementById('unlinkCardId').value = cardId;
    document.getElementById('unlinkCardLabel').textContent = label;
    document.getElementById('unlinkModal').classList.add('show');
}

function closeUnlinkModal() {
    document.getElementById('unlinkModal').classList.remove('show');
}

document.getElementById('unlinkModal').addEventListener('click', function(e) {
    if (e.target === this) closeUnlinkModal();
});

// Show / hide Add Card form
(function () {
    const section = document.getElementById('add-card-section');
    const showBtn = document.getElementById('show-add-card-btn');
    const showBtnEmpty = document.getElementById('show-add-card-btn-empty');
    const hideBtn = document.getElementById('hide-add-card-btn');

    function showForm() {
        if (!section) return;
        section.classList.remove('hidden');
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function hideForm() {
        if (!section) return;
        section.classList.add('hidden');
    }

    if (showBtn) showBtn.addEventListener('click', showForm);
    if (showBtnEmpty) showBtnEmpty.addEventListener('click', showForm);
    if (hideBtn) hideBtn.addEventListener('click', hideForm);

    // If there was a validation error on POST, keep form open
    const msg = <?= json_encode($message) ?>;
    if (msg && msg.indexOf('❌') !== -1) {
        showForm();
    }
})();
</script>

</body>
</html>