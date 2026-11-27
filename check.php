<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $front = $_FILES['front'] ?? null;
    $back = $_FILES['back'] ?? null;

    if ($amount > 0 && $front && $back && $front['error'] === 0 && $back['error'] === 0) {
        
        $uploadDir = 'uploads/checks/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $frontName = $userId . '_front_' . time() . '.jpg';
        $backName  = $userId . '_back_' . time() . '.jpg';

        $frontPath = $uploadDir . $frontName;
        $backPath  = $uploadDir . $backName;

        // Move uploaded files (already compressed client-side)
        if (move_uploaded_file($front['tmp_name'], $frontPath) && move_uploaded_file($back['tmp_name'], $backPath)) {
            
            $stmt = $pdo->prepare("
                INSERT INTO check_deposits (user_id, amount, front_image, back_image, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $amount, $frontPath, $backPath]);

            $message = "✅ Check deposit submitted successfully! Status: Pending (awaiting verification).";
        } else {
            $message = "❌ Failed to save images.";
        }
    } else {
        $message = "❌ Please provide amount and both front & back photos.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank • Deposit Check</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .preview { max-height: 220px; border-radius: 12px; object-fit: contain; }
        #submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

<div class="max-w-xl mx-auto p-6">

    <div class="flex items-center gap-3 mb-8">
        <a href="index.php" class="text-violet-400 hover:text-violet-300">
            <i class="fa-solid fa-arrow-left text-2xl"></i>
        </a>
        <h1 class="text-3xl font-bold">Deposit Check</h1>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-2xl <?= strpos($message, '✅') !== false ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form id="check-form" method="POST" enctype="multipart/form-data" class="space-y-8">

        <!-- Amount -->
        <div>
            <label class="block text-sm text-slate-400 mb-2">Check Amount ($)</label>
            <input type="number" name="amount" id="amount" step="0.01" required
                   class="w-full bg-slate-800 border border-slate-700 rounded-2xl px-5 py-4 text-2xl font-mono focus:outline-none focus:border-violet-500"
                   placeholder="0.00">
        </div>

        <!-- Front of Check -->
        <div>
            <label class="block text-sm text-slate-400 mb-3">Front of Check</label>
            <input type="file" name="front" accept="image/*" capture="environment" required
                   class="hidden" id="front-input">
            <button type="button" onclick="document.getElementById('front-input').click()"
                    class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-3xl py-6 flex flex-col items-center gap-3">
                <i class="fa-solid fa-camera text-4xl text-violet-400"></i>
                <span class="font-medium">Take Photo of Front</span>
            </button>
            <img id="front-preview" class="preview mt-4 hidden mx-auto">
        </div>

        <!-- Back of Check -->
        <div>
            <label class="block text-sm text-slate-400 mb-3">Back of Check (Endorsed)</label>
            <input type="file" name="back" accept="image/*" capture="environment" required
                   class="hidden" id="back-input">
            <button type="button" onclick="document.getElementById('back-input').click()"
                    class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-3xl py-6 flex flex-col items-center gap-3">
                <i class="fa-solid fa-camera text-4xl text-violet-400"></i>
                <span class="font-medium">Take Photo of Back</span>
            </button>
            <img id="back-preview" class="preview mt-4 hidden mx-auto">
        </div>

        <button type="submit" id="submit-btn"
                class="w-full bg-emerald-600 hover:bg-emerald-700 transition py-5 rounded-3xl font-semibold text-xl mt-8 flex items-center justify-center gap-3">
            <span id="btn-text">Submit Check for Verification</span>
            <span id="loading-spinner" class="hidden">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </span>
        </button>
    </form>

    <p class="text-center text-slate-500 text-sm mt-10">
        Your check will stay in <span class="text-amber-400">Pending</span> status until check cleared 
    </p>
</div>

<script>
// Live preview + compression
function compressImage(file, callback) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            let width = img.width;
            let height = img.height;

            // Resize to max 1200px width while keeping aspect ratio
            if (width > 1200) {
                height = Math.round((height * 1200) / width);
                width = 1200;
            }

            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            // Compress to JPEG ~70% quality
            canvas.toBlob(function(blob) {
                callback(blob);
            }, 'image/jpeg', 0.70);
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// Handle front image
document.getElementById('front-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const preview = document.getElementById('front-preview');
    compressImage(file, function(compressedBlob) {
        // Replace original file with compressed version
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(new File([compressedBlob], 'front.jpg', { type: 'image/jpeg' }));
        e.target.files = dataTransfer.files;

        // Show preview
        preview.src = URL.createObjectURL(compressedBlob);
        preview.classList.remove('hidden');
    });
});

// Handle back image (same logic)
document.getElementById('back-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const preview = document.getElementById('back-preview');
    compressImage(file, function(compressedBlob) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(new File([compressedBlob], 'back.jpg', { type: 'image/jpeg' }));
        e.target.files = dataTransfer.files;

        preview.src = URL.createObjectURL(compressedBlob);
        preview.classList.remove('hidden');
    });
});

// Form submit handling with loading state
const form = document.getElementById('check-form');
const submitBtn = document.getElementById('submit-btn');
const btnText = document.getElementById('btn-text');
const spinner = document.getElementById('loading-spinner');

form.addEventListener('submit', function() {
    submitBtn.disabled = true;
    btnText.classList.add('hidden');
    spinner.classList.remove('hidden');
});
</script>

</body>
</html>