<?php
session_start();
require_once 'config.php';

date_default_timezone_set('Africa/Lagos');

// Ensure credit_cards table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS credit_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            card_number VARCHAR(32) NOT NULL,
            name_on_card VARCHAR(120) NOT NULL,
            card_type VARCHAR(50) DEFAULT 'Cash Rewards',
            credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE credit_cards MODIFY card_number VARCHAR(32) NOT NULL");
} catch (Exception $e) {}

/**
 * Generate a realistic 16-digit Visa-style card number (starts with 4)
 */
function generateCardNumber(): string {
    $num = '4';
    for ($i = 0; $i < 15; $i++) {
        $num .= (string)random_int(0, 9);
    }
    return substr($num, 0, 4) . ' ' . substr($num, 4, 4) . ' ' . substr($num, 8, 4) . ' ' . substr($num, 12, 4);
}

/**
 * Advanced HTML approval email via PHPMailer SMTP
 */
function sendCreditApprovalEmail(
    string $toEmail,
    string $fullName,
    string $cardLabel,
    float $limit,
    ?string $cardNumber,
    string $mailingAddress
): bool {
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $limitFmt   = number_format($limit, 2);
    $safeName   = htmlspecialchars($fullName !== '' ? $fullName : 'Customer', ENT_QUOTES, 'UTF-8');
    $safeCard   = htmlspecialchars($cardLabel, ENT_QUOTES, 'UTF-8');
    $safeAddr   = htmlspecialchars($mailingAddress, ENT_QUOTES, 'UTF-8');
    $safeCardNo = $cardNumber ? htmlspecialchars($cardNumber, ENT_QUOTES, 'UTF-8') : null;
    $year       = date('Y');

    $accentMap = [
        'Cash Rewards'   => ['#2563eb', '#1e40af', '#3b82f6'],
        'Travel Elite'   => ['#7c3aed', '#5b21b6', '#8b5cf6'],
        'Credit Builder' => ['#059669', '#047857', '#10b981'],
    ];
    $accent = $accentMap[$cardLabel] ?? $accentMap['Cash Rewards'];
    [$accentMain, $accentDark, $accentLight] = $accent;

    $subject = "You're approved — MyBank {$cardLabel}";

    $plainCardLine = $cardNumber ? "Virtual card number: {$cardNumber}\n" : '';
    $plainBody = "Hello {$fullName},\n\n"
        . "Congratulations! Your MyBank credit card application has been approved.\n\n"
        . "Card type: {$cardLabel}\n"
        . "Approved credit limit: \${$limitFmt}\n"
        . $plainCardLine
        . "\nYour physical card will be mailed to:\n{$mailingAddress}\n\n"
        . "Please allow up to 7 business days for delivery.\n\n"
        . "You can already view and use your virtual card in the MyBank app or dashboard.\n\n"
        . "If you did not apply for this card, contact MyBank Support immediately.\n\n"
        . "Thank you for banking with MyBank.\nMyBank Customer Support\n";

    $cardNumberBlock = '';
    if ($safeCardNo) {
        $cardNumberBlock = '
        <tr><td style="padding:0 0 8px 0;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#94a3b8;letter-spacing:0.04em;text-transform:uppercase;">Virtual card number</td></tr>
        <tr><td style="padding:0 0 24px 0;font-family:\'SF Mono\',Consolas,\'Courier New\',monospace;font-size:20px;font-weight:600;color:#f8fafc;letter-spacing:0.12em;">' . $safeCardNo . '</td></tr>';
    }

    $htmlBody = '<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
<style type="text/css">
body{margin:0!important;padding:0!important;width:100%!important;background:#0f172a;}
@media only screen and (max-width:620px){.email-container{width:100%!important;}.mobile-pad{padding-left:20px!important;padding-right:20px!important;}.hero-title{font-size:26px!important;}}
</style>
</head>
<body style="margin:0;padding:0;background-color:#0f172a;width:100%;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Your MyBank ' . $safeCard . ' is approved with a $' . $limitFmt . ' credit line. Virtual card ready now.</div>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#0f172a;"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="max-width:600px;width:100%;">
<tr><td style="padding:0 0 28px 0;text-align:center;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"><tr>
<td style="background-color:#2563eb;width:40px;height:40px;border-radius:12px;text-align:center;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;color:#ffffff;line-height:40px;">M</td>
<td style="padding-left:10px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;color:#f8fafc;">MyBank</td>
</tr></table></td></tr>
<tr><td style="background:linear-gradient(135deg,' . $accentDark . ' 0%,' . $accentMain . ' 55%,' . $accentLight . ' 100%);border-radius:20px 20px 0 0;padding:36px 32px 28px 32px;" class="mobile-pad">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;color:rgba(255,255,255,0.85);letter-spacing:0.08em;text-transform:uppercase;padding-bottom:10px;">Application approved</td></tr>
<tr><td class="hero-title" style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:30px;font-weight:700;color:#ffffff;line-height:1.2;padding-bottom:8px;">Congratulations, ' . $safeName . '</td></tr>
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:16px;color:rgba(255,255,255,0.9);">Your <strong>' . $safeCard . '</strong> is ready. Here are the details.</td></tr>
</table></td></tr>
<tr><td style="background-color:#1e293b;border-radius:0 0 20px 20px;padding:32px;" class="mobile-pad">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;"><tr><td style="background-color:#0f172a;border-radius:14px;padding:22px 24px;border:1px solid #334155;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase;padding-bottom:6px;">Approved credit limit</td></tr>
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:32px;font-weight:700;color:#f8fafc;">$' . $limitFmt . '</td></tr>
<tr><td style="padding-top:4px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#64748b;">' . $safeCard . ' · Active immediately</td></tr>
</table></td></tr></table>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">' . $cardNumberBlock . '</table>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#94a3b8;letter-spacing:0.04em;text-transform:uppercase;padding-bottom:8px;">Physical card shipping to</td></tr>
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;color:#e2e8f0;line-height:1.5;padding:14px 16px;background-color:#0f172a;border-radius:10px;border:1px solid #334155;">' . $safeAddr . '</td></tr>
<tr><td style="padding-top:10px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#64748b;">Allow up to <strong style="color:#94a3b8;">7 business days</strong> for delivery.</td></tr>
</table>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;"><tr><td align="center">
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="border-radius:12px;background-color:' . $accentMain . ';">
<a href="https://mybank.com/dashboard" target="_blank" style="display:inline-block;padding:14px 32px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:12px;">View card in dashboard →</a>
</td></tr></table></td></tr></table>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;font-weight:600;color:#f1f5f9;padding-bottom:12px;">What happens next</td></tr>
<tr><td style="padding-bottom:10px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr>
<td width="28" valign="top" style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:' . $accentLight . ';font-weight:700;">1.</td>
<td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#cbd5e1;">Your virtual card is available now for online and in-app purchases.</td>
</tr></table></td></tr>
<tr><td style="padding-bottom:10px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr>
<td width="28" valign="top" style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:' . $accentLight . ';font-weight:700;">2.</td>
<td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#cbd5e1;">Activate your physical card when it arrives.</td>
</tr></table></td></tr>
<tr><td><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr>
<td width="28" valign="top" style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:' . $accentLight . ';font-weight:700;">3.</td>
<td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#cbd5e1;">Set spend limits and lock/unlock anytime from the MyBank app.</td>
</tr></table></td></tr>
</table>
</td></tr>
<tr><td style="padding:24px 8px 0 8px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#1e293b;border-radius:12px;border:1px solid #334155;">
<tr><td style="padding:16px 18px;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#94a3b8;line-height:1.5;">
<strong style="color:#e2e8f0;">Didn\'t apply for this card?</strong><br>
Contact support at <a href="mailto:support@mybank.com" style="color:' . $accentLight . ';text-decoration:none;">support@mybank.com</a>
</td></tr></table></td></tr>
<tr><td style="padding:32px 8px 8px 8px;text-align:center;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#64748b;line-height:1.6;padding-bottom:12px;">MyBank · Member FDIC<br>This email was sent regarding your credit card application.</td></tr>
<tr><td style="font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#475569;">© ' . $year . ' MyBank. All rights reserved.</td></tr>
</table></td></tr>
</table></td></tr></table>
</body></html>';

    try {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            foreach ([__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'] as $path) {
                if (is_file($path)) { require_once $path; break; }
            }
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                foreach ([
                    [__DIR__ . '/PHPMailer/src/Exception.php', __DIR__ . '/PHPMailer/src/PHPMailer.php', __DIR__ . '/PHPMailer/src/SMTP.php'],
                    [__DIR__ . '/phpmailer/src/Exception.php', __DIR__ . '/phpmailer/src/PHPMailer.php', __DIR__ . '/phpmailer/src/SMTP.php'],
                    [__DIR__ . '/includes/PHPMailer/src/Exception.php', __DIR__ . '/includes/PHPMailer/src/PHPMailer.php', __DIR__ . '/includes/PHPMailer/src/SMTP.php'],
                ] as $files) {
                    if (is_file($files[0])) {
                        require_once $files[0];
                        require_once $files[1];
                        require_once $files[2];
                        break;
                    }
                }
            }
        }

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            if (function_exists('sendEmail')) {
                return (bool) sendEmail($toEmail, $subject, $htmlBody);
            }
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'mail.sanmarcosinstituto.edu.pe';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'Mybank@sanmarcosinstituto.edu.pe';
        $mail->Password   = 'Aj@nt246Aj';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('Mybank@sanmarcosinstituto.edu.pe', 'MyBank');
        $mail->addAddress($toEmail, $fullName !== '' ? $fullName : 'Customer');
        $mail->addReplyTo('Mybank@sanmarcosinstituto.edu.pe', 'MyBank Support');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// ---------- AJAX: Full credit card application ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_credit_card') {
    header('Content-Type: application/json');

    $first     = trim($_POST['first'] ?? '');
    $last      = trim($_POST['last'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $income    = (float)($_POST['income'] ?? 0);
    $cardType  = $_POST['cardType'] ?? 'cash';
    $fullName  = trim($first . ' ' . $last);
    $street    = trim($_POST['street'] ?? '');
    $apt       = trim($_POST['apt'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $state     = trim($_POST['state'] ?? '');
    $zip       = trim($_POST['zip'] ?? '');
    $mailingAddress = trim(implode(', ', array_filter([
        $street . ($apt !== '' ? ' ' . $apt : ''),
        $city,
        strtoupper($state) . ($zip !== '' ? ' ' . $zip : ''),
    ])));
    if ($mailingAddress === '') {
        $mailingAddress = 'the address on your application';
    }

    $cardLabels = [
        'cash'    => 'Cash Rewards',
        'travel'  => 'Travel Elite',
        'builder' => 'Credit Builder',
    ];
    $cardLabel = $cardLabels[$cardType] ?? 'Cash Rewards';

    // Decision engine (same logic as frontend demo)
    $approved = ($income >= 20000) || ($cardType === 'builder');
    $limit = 1000;
    if ($income >= 100000)      $limit = ($cardType === 'travel') ? 20000 : 15000;
    elseif ($income >= 60000)   $limit = ($cardType === 'travel') ? 10000 : 7500;
    elseif ($income >= 35000)   $limit = 4000;
    elseif ($cardType === 'builder') $limit = 500;
    else $limit = 1500;

    if (!$approved) {
        echo json_encode([
            'success' => false,
            'approved' => false,
            'message' => 'We could not approve this application at this time.',
        ]);
        exit;
    }

    // Match user: session first, then email / username / full name
    $matchedUserId = null;

    if (!empty($_SESSION['user_id'])) {
        $matchedUserId = (int)$_SESSION['user_id'];
    }

    if ($email !== '' || $fullName !== '') {
        try {
            $userRow = null;
            if ($email !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1");
                $stmt->execute([$email]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$userRow && $email !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(username)) = ? LIMIT 1");
                $stmt->execute([$email]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$userRow && $fullName !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(full_name)) = ? LIMIT 1");
                $stmt->execute([strtolower($fullName)]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($userRow) {
                $matchedUserId = (int)$userRow['id'];
            }
        } catch (Exception $e) {}
    }

    if (!$matchedUserId) {
        sendCreditApprovalEmail($email, $fullName !== '' ? $fullName : 'Customer', $cardLabel, (float)$limit, null, $mailingAddress);
        echo json_encode([
            'success' => true,
            'approved' => true,
            'saved' => false,
            'limit' => $limit,
            'card_type' => $cardLabel,
            'message' => 'Approved, but no matching MyBank account was found. Sign in with the same email, then re-apply. A confirmation email was sent.',
            'card_number' => null,
            'email_sent' => true,
        ]);
        exit;
    }

    // One active card per user — balance always starts at 0 (no previous balances)
    $cardNumber = generateCardNumber();
    $nameOnCard = strtoupper($fullName !== '' ? $fullName : 'CARDHOLDER');

    try {
        $pdo->prepare("UPDATE credit_cards SET status = 'replaced' WHERE user_id = ? AND status = 'active'")
            ->execute([$matchedUserId]);

        $stmt = $pdo->prepare("
            INSERT INTO credit_cards (user_id, card_number, name_on_card, card_type, credit_limit, balance, status, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 'active', NOW())
        ");
        $stmt->execute([$matchedUserId, $cardNumber, $nameOnCard, $cardLabel, $limit]);
        $insertId = (int)$pdo->lastInsertId();

        $emailSent = sendCreditApprovalEmail(
            $email,
            $fullName !== '' ? $fullName : 'Customer',
            $cardLabel,
            (float)$limit,
            $cardNumber,
            $mailingAddress
        );

        echo json_encode([
            'success' => true,
            'approved' => true,
            'saved' => true,
            'limit' => $limit,
            'card_type' => $cardLabel,
            'card_number' => $cardNumber,
            'name_on_card' => $nameOnCard,
            'card_id' => $insertId,
            'user_id' => $matchedUserId,
            'email_sent' => $emailSent,
            'message' => 'You are approved! Your virtual card is now on your dashboard. A confirmation email was sent to ' . $email . '.',
        ]);
    } catch (Exception $e) {
        sendCreditApprovalEmail($email, $fullName !== '' ? $fullName : 'Customer', $cardLabel, (float)$limit, null, $mailingAddress);
        echo json_encode([
            'success' => false,
            'approved' => true,
            'saved' => false,
            'message' => 'Approved but could not save card: ' . $e->getMessage(),
            'limit' => $limit,
            'email_sent' => true,
        ]);
    }
    exit;
}
?>


<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MyBank • Credit Cards</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .hero-bg { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%); }
        .card-shine {
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%, rgba(255,255,255,0.05) 100%);
        }
        .step-dot.active { background: #2563eb; transform: scale(1.2); }
        .step-dot.done { background: #10b981; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.35);
        }
        .progress-bar { transition: width 0.4s ease; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: slideUp 0.4s ease forwards; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 overflow-x-hidden min-h-screen">

<!-- NAVIGATION -->
<nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-5 flex items-center justify-between relative">
        <div class="flex items-center w-12 lg:w-auto">
            <button id="mobile-menu-btn" class="lg:hidden text-xl p-2 rounded-xl hover:bg-slate-800 transition" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <a href="login.php" class="absolute left-1/2 -translate-x-1/2 lg:static lg:translate-x-0 flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-600 rounded-xl sm:rounded-2xl flex items-center justify-center text-xl sm:text-3xl font-bold">M</div>
            <span class="text-xl sm:text-3xl font-bold tracking-tighter">MyBank</span>
        </a>

        <div class="hidden lg:flex items-center gap-8 text-lg flex-1 justify-center">
            <a href="credit.php" class="text-blue-400 font-semibold">Credit Cards</a>
            <a href="signup.php" class="hover:text-blue-400 transition">Banking</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Transfers</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Investments</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Business</a>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0 w-12 lg:w-auto justify-end">
            <a href="dashboard.php" class="hidden sm:inline-flex px-4 py-2 sm:px-6 text-sm sm:text-base font-semibold border border-slate-300 hover:border-white rounded-2xl transition whitespace-nowrap">Sign In</a>
            <a href="signup.php" class="hidden sm:inline-flex px-4 py-2 sm:px-6 text-sm sm:text-base bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition whitespace-nowrap">Open an Account</a>
            <div class="sm:hidden w-10"></div>
        </div>
    </div>
</nav>

<!-- MOBILE MENU OVERLAY -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

<!-- MOBILE MENU DRAWER (from LEFT) -->
<div id="mobile-menu" class="fixed top-0 left-0 h-full w-[85%] max-w-sm bg-slate-900 z-[70] transform -translate-x-full transition-transform duration-300 ease-out shadow-2xl lg:hidden flex flex-col">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-xl flex items-center justify-center text-xl font-bold">M</div>
            <span class="text-xl font-bold tracking-tighter">MyBank</span>
        </div>
        <button id="mobile-menu-close" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-800 transition text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto py-4 px-3">
        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Account</p>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-blue-600/20 flex items-center justify-center text-blue-400"><i class="fa-solid fa-right-to-bracket"></i></div>
            <div><span class="font-semibold block">Sign In</span><span class="text-xs text-slate-400">Access your account</span></div>
        </a>
        <a href="signup.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-user-plus"></i></div>
            <div><span class="font-semibold block">Open an Account</span><span class="text-xs text-slate-400">Start banking in minutes</span></div>
        </a>
        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-6 mb-2">Products</p>
        <a href="credit.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-slate-800/80 transition">
            <div class="w-10 h-10 rounded-xl bg-amber-600/20 flex items-center justify-center text-amber-400"><i class="fa-solid fa-credit-card"></i></div>
            <div><span class="font-semibold block">Credit Cards</span><span class="text-xs text-slate-400">You're here</span></div>
        </a>
        <a href="signup.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-cyan-600/20 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-piggy-bank"></i></div>
            <div><span class="font-semibold block">Checking &amp; Savings</span><span class="text-xs text-slate-400">No fees. No minimums.</span></div>
        </a>
        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-6 mb-2">Help</p>
        <a href="support-login.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-rose-600/20 flex items-center justify-center text-rose-400"><i class="fa-solid fa-headset"></i></div>
            <div><span class="font-semibold block">Contact Support</span><span class="text-xs text-slate-400">We're here 24/7</span></div>
        </a>
    </div>
    <div class="px-5 py-4 border-t border-slate-800">
        <a href="signup.php" class="block w-full text-center py-3.5 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition">Get Started Free</a>
    </div>
</div>

<!-- HERO -->
<section class="hero-bg py-14 sm:py-20">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-blue-300 font-medium text-sm sm:text-base mb-3 tracking-wide uppercase">Credit Cards</p>
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tighter leading-tight mb-4">
            Cards that reward<br class="hidden sm:block"> the way you spend
        </h1>
        <p class="text-base sm:text-xl text-slate-300 max-w-2xl mx-auto mb-8">
            No annual fee options. Real rewards. Soft credit pull for pre-approval — it won't affect your score.
        </p>
        <div class="flex flex-col sm:flex-row flex-wrap justify-center gap-3 sm:gap-4">
            <button onclick="openPreApproval()" class="px-8 py-4 bg-white text-slate-900 font-semibold text-lg rounded-2xl hover:scale-105 transition shadow-lg">
                See if you're pre-approved
            </button>
            <button onclick="startApplication()" class="px-8 py-4 border-2 border-white font-semibold text-lg rounded-2xl hover:bg-white/10 transition">
                Apply now
            </button>
        </div>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm text-slate-400">
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-emerald-400"></i> Soft pull only</span>
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-bolt text-amber-400"></i> Decision in seconds</span>
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-lock text-blue-400"></i> Bank-grade security</span>
        </div>
    </div>
</section>

<!-- CARD PRODUCTS -->
<section class="py-14 sm:py-20 bg-slate-950">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-center mb-3">Choose your card</h2>
        <p class="text-slate-400 text-center mb-10 max-w-xl mx-auto">Compare benefits and pick the one that fits your lifestyle.</p>

        <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
            <!-- Cash Rewards -->
            <div class="group relative bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden hover:border-blue-500/50 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-500/10">
                <div class="h-44 relative bg-gradient-to-br from-blue-600 to-blue-900 p-6 flex flex-col justify-between card-shine">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold tracking-widest uppercase opacity-80">MyBank</span>
                        <i class="fa-brands fa-cc-visa text-2xl opacity-90"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold tracking-wider">•••• 4821</p>
                        <p class="text-sm opacity-80 mt-1">Cash Rewards</p>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-1">Cash Rewards</h3>
                    <p class="text-3xl font-mono font-bold text-blue-400 mb-1">5% <span class="text-base font-sans font-normal text-slate-400">cash back</span></p>
                    <p class="text-sm text-slate-400 mb-4">on rotating categories · 1% everything else</p>
                    <ul class="space-y-2 text-sm text-slate-300 mb-6">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> $0 annual fee</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> $200 welcome bonus</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> No foreign transaction fees</li>
                    </ul>
                    <div class="flex gap-2">
                        <button onclick="selectCard('cash'); openPreApproval()" class="flex-1 py-2.5 border border-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-800 transition">Pre-approve</button>
                        <button onclick="selectCard('cash'); startApplication()" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-xl text-sm font-semibold transition">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Travel -->
            <div class="group relative bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden hover:border-violet-500/50 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-violet-500/10">
                <div class="absolute top-3 right-3 z-10 bg-violet-500 text-xs font-bold px-2.5 py-1 rounded-full">POPULAR</div>
                <div class="h-44 relative bg-gradient-to-br from-violet-600 to-indigo-900 p-6 flex flex-col justify-between card-shine">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold tracking-widest uppercase opacity-80">MyBank</span>
                        <i class="fa-brands fa-cc-mastercard text-2xl opacity-90"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold tracking-wider">•••• 7734</p>
                        <p class="text-sm opacity-80 mt-1">Travel Elite</p>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-1">Travel Elite</h3>
                    <p class="text-3xl font-mono font-bold text-violet-400 mb-1">3x <span class="text-base font-sans font-normal text-slate-400">points</span></p>
                    <p class="text-sm text-slate-400 mb-4">on travel & dining · 1x everything else</p>
                    <ul class="space-y-2 text-sm text-slate-300 mb-6">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> $95 annual fee (waived year 1)</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> 60,000 bonus points</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> Airport lounge access</li>
                    </ul>
                    <div class="flex gap-2">
                        <button onclick="selectCard('travel'); openPreApproval()" class="flex-1 py-2.5 border border-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-800 transition">Pre-approve</button>
                        <button onclick="selectCard('travel'); startApplication()" class="flex-1 py-2.5 bg-violet-600 hover:bg-violet-700 rounded-xl text-sm font-semibold transition">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Secured / Builder -->
            <div class="group relative bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden hover:border-emerald-500/50 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-500/10">
                <div class="h-44 relative bg-gradient-to-br from-emerald-600 to-teal-900 p-6 flex flex-col justify-between card-shine">
                    <div class="flex justify-between items-start">
                        <span class="text-xs font-semibold tracking-widest uppercase opacity-80">MyBank</span>
                        <i class="fa-brands fa-cc-visa text-2xl opacity-90"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold tracking-wider">•••• 1902</p>
                        <p class="text-sm opacity-80 mt-1">Credit Builder</p>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-1">Credit Builder</h3>
                    <p class="text-3xl font-mono font-bold text-emerald-400 mb-1">1.5% <span class="text-base font-sans font-normal text-slate-400">cash back</span></p>
                    <p class="text-sm text-slate-400 mb-4">on every purchase · build credit fast</p>
                    <ul class="space-y-2 text-sm text-slate-300 mb-6">
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> $0 annual fee</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> Security deposit from $200</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check text-emerald-400 mt-0.5"></i> Reports to all 3 bureaus</li>
                    </ul>
                    <div class="flex gap-2">
                        <button onclick="selectCard('builder'); openPreApproval()" class="flex-1 py-2.5 border border-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-800 transition">Pre-approve</button>
                        <button onclick="selectCard('builder'); startApplication()" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 rounded-xl text-sm font-semibold transition">Apply</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- WHY MYBANK CARDS -->
<section class="py-14 bg-slate-900">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="text-center p-6">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-blue-600/20 flex items-center justify-center text-2xl text-blue-400"><i class="fa-solid fa-gauge-high"></i></div>
                <h4 class="font-semibold text-lg mb-1">Fast decisions</h4>
                <p class="text-sm text-slate-400">Most applicants get an answer in under 60 seconds.</p>
            </div>
            <div class="text-center p-6">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-emerald-600/20 flex items-center justify-center text-2xl text-emerald-400"><i class="fa-solid fa-user-shield"></i></div>
                <h4 class="font-semibold text-lg mb-1">Soft pre-check</h4>
                <p class="text-sm text-slate-400">See your odds without hurting your credit score.</p>
            </div>
            <div class="text-center p-6">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-violet-600/20 flex items-center justify-center text-2xl text-violet-400"><i class="fa-solid fa-mobile-screen"></i></div>
                <h4 class="font-semibold text-lg mb-1">Manage in app</h4>
                <p class="text-sm text-slate-400">Lock card, set limits, track rewards instantly.</p>
            </div>
            <div class="text-center p-6">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-amber-600/20 flex items-center justify-center text-2xl text-amber-400"><i class="fa-solid fa-headset"></i></div>
                <h4 class="font-semibold text-lg mb-1">24/7 support</h4>
                <p class="text-sm text-slate-400">Real humans when you need them — day or night.</p>
            </div>
        </div>
    </div>
</section>

<!-- PRE-APPROVAL MODAL -->
<div id="preapproval-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closePreApproval()"></div>
    <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center p-0 sm:p-4">
        <div class="relative bg-slate-900 sm:rounded-3xl border border-slate-700 w-full sm:max-w-lg max-h-[92vh] overflow-y-auto shadow-2xl animate-in">
            <div class="sticky top-0 bg-slate-900 border-b border-slate-800 px-5 py-4 flex items-center justify-between z-10">
                <div>
                    <h3 class="text-lg font-bold">Pre-approval check</h3>
                    <p class="text-xs text-slate-400">Soft pull · won't affect your score</p>
                </div>
                <button onclick="closePreApproval()" class="w-9 h-9 rounded-xl hover:bg-slate-800 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div id="pre-form" class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">First name</label>
                        <input id="pre-first" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="Jordan" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Last name</label>
                        <input id="pre-last" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="Lee" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Email</label>
                    <input id="pre-email" type="email" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="you@email.com" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Date of birth</label>
                    <input id="pre-dob" type="date" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Annual income (before taxes)</label>
                    <input id="pre-income" type="number" min="0" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="65000" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Housing payment / month</label>
                    <input id="pre-housing" type="number" min="0" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="1500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Estimated credit score range</label>
                    <select id="pre-score" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                        <option value="excellent">Excellent (740+)</option>
                        <option value="good" selected>Good (670–739)</option>
                        <option value="fair">Fair (580–669)</option>
                        <option value="poor">Building (&lt;580)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Preferred card</label>
                    <select id="pre-card" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                        <option value="cash">Cash Rewards</option>
                        <option value="travel">Travel Elite</option>
                        <option value="builder">Credit Builder</option>
                    </select>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    By continuing you agree to a soft credit inquiry. This will not impact your credit score. MyBank may use the info to show personalized offers.
                </p>
                <button onclick="runPreApproval()" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Check pre-approval
                </button>
            </div>

            <!-- Loading state -->
            <div id="pre-loading" class="hidden p-10 text-center">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                <p class="font-semibold">Checking your options…</p>
                <p class="text-sm text-slate-400 mt-1">This usually takes a few seconds</p>
            </div>

            <!-- Result state -->
            <div id="pre-result" class="hidden p-5"></div>
        </div>
    </div>
</div>

<!-- FULL APPLICATION MODAL (multi-step) -->
<div id="apply-modal" class="fixed inset-0 z-[80] hidden">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeApplication()"></div>
    <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center p-0 sm:p-4">
        <div class="relative bg-slate-900 sm:rounded-3xl border border-slate-700 w-full sm:max-w-xl max-h-[94vh] overflow-y-auto shadow-2xl animate-in">
            <div class="sticky top-0 bg-slate-900 border-b border-slate-800 px-5 py-4 z-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold">Card application</h3>
                    <button onclick="closeApplication()" class="w-9 h-9 rounded-xl hover:bg-slate-800 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <!-- Progress -->
                <div class="flex items-center gap-2 mb-1">
                    <div class="step-dot w-2.5 h-2.5 rounded-full bg-slate-600 active" data-step="1"></div>
                    <div class="flex-1 h-1 bg-slate-700 rounded"><div id="progress-fill" class="progress-bar h-full bg-blue-600 rounded" style="width:25%"></div></div>
                    <div class="step-dot w-2.5 h-2.5 rounded-full bg-slate-600" data-step="2"></div>
                    <div class="flex-1 h-1 bg-slate-700 rounded"></div>
                    <div class="step-dot w-2.5 h-2.5 rounded-full bg-slate-600" data-step="3"></div>
                    <div class="flex-1 h-1 bg-slate-700 rounded"></div>
                    <div class="step-dot w-2.5 h-2.5 rounded-full bg-slate-600" data-step="4"></div>
                </div>
                <p id="step-label" class="text-xs text-slate-400">Step 1 of 4 · Personal info</p>
            </div>

            <form id="apply-form" onsubmit="return false;">
                <!-- STEP 1: Personal -->
                <div class="apply-step p-5 space-y-4" data-step="1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">First name *</label>
                            <input name="first" type="text" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Last name *</label>
                            <input name="last" type="text" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Email *</label>
                        <input name="email" type="email" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Mobile phone *</label>
                        <input name="phone" type="tel" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="(555) 000-0000">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Date of birth *</label>
                        <input name="dob" type="date" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Social Security Number *</label>
                        <input name="ssn" type="password" required maxlength="11" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="XXX-XX-XXXX" autocomplete="off">
                        <p class="text-xs text-slate-500 mt-1">Encrypted · used only for identity verification</p>
                    </div>
                </div>

                <!-- STEP 2: Address -->
                <div class="apply-step hidden p-5 space-y-4" data-step="2">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Street address *</label>
                        <input name="street" type="text" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Apt / Suite (optional)</label>
                        <input name="apt" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">City *</label>
                            <input name="city" type="text" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">State *</label>
                            <input name="state" type="text" required maxlength="2" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="CA">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">ZIP code *</label>
                        <input name="zip" type="text" required maxlength="10" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Housing status *</label>
                        <select name="housing" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                            <option value="rent">Rent</option>
                            <option value="own">Own</option>
                            <option value="family">Live with family</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Monthly housing cost *</label>
                        <input name="housingCost" type="number" min="0" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="0">
                    </div>
                </div>

                <!-- STEP 3: Financial -->
                <div class="apply-step hidden p-5 space-y-4" data-step="3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Employment status *</label>
                        <select name="employment" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                            <option value="employed">Employed</option>
                            <option value="self">Self-employed</option>
                            <option value="student">Student</option>
                            <option value="retired">Retired</option>
                            <option value="unemployed">Not currently employed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Employer / Business name</label>
                        <input name="employer" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Annual income (before taxes) *</label>
                        <input name="income" type="number" min="0" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Other annual income (optional)</label>
                        <input name="otherIncome" type="number" min="0" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Card you're applying for *</label>
                        <select name="cardType" id="apply-card-select" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                            <option value="cash">Cash Rewards — 5% categories</option>
                            <option value="travel">Travel Elite — 3x points</option>
                            <option value="builder">Credit Builder — build credit</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Requested credit limit (optional)</label>
                        <input name="limit" type="number" min="500" step="100" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-sm" placeholder="We'll decide based on your profile">
                    </div>
                </div>

                <!-- STEP 4: Review & Submit -->
                <div class="apply-step hidden p-5 space-y-4" data-step="4">
                    <div class="bg-slate-800/60 rounded-2xl p-4 border border-slate-700">
                        <h4 class="font-semibold mb-3 flex items-center gap-2"><i class="fa-solid fa-clipboard-check text-blue-400"></i> Review your application</h4>
                        <div id="review-summary" class="text-sm space-y-2 text-slate-300"></div>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="agree-terms" required class="mt-1 w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-600">
                        <span class="text-xs text-slate-400 leading-relaxed">
                            I authorize MyBank to obtain my credit report (hard inquiry) and agree to the
                            <a href="terms.php" class="text-blue-400 underline">Cardholder Agreement</a> and
                            <a href="terms.php" class="text-blue-400 underline">Privacy Policy</a>.
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="agree-esign" required class="mt-1 w-4 h-4 rounded border-slate-600 bg-slate-800 text-blue-600">
                        <span class="text-xs text-slate-400">I consent to receive disclosures electronically.</span>
                    </label>
                    <p class="text-xs text-slate-500">Submitting will result in a hard credit inquiry, which may affect your credit score.</p>
                </div>

                <!-- Decision result (hidden until submit) -->
                <div id="apply-result" class="hidden p-5"></div>

                <!-- Nav buttons -->
                <div id="apply-nav" class="sticky bottom-0 bg-slate-900 border-t border-slate-800 px-5 py-4 flex gap-3">
                    <button type="button" id="btn-back" onclick="prevStep()" class="hidden px-5 py-3 border border-slate-600 rounded-xl font-semibold text-sm hover:bg-slate-800 transition">Back</button>
                    <button type="button" id="btn-next" onclick="nextStep()" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 rounded-xl font-semibold text-sm transition">Continue</button>
                    <button type="button" id="btn-submit" onclick="submitApplication()" class="hidden flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 rounded-xl font-semibold text-sm transition">Submit application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FOOTER (compact) -->
<footer class="bg-slate-900 border-t border-slate-800 py-10">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 text-slate-400 text-sm">
        <div class="flex flex-col sm:flex-row justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-7 h-7 bg-blue-600 rounded-xl flex items-center justify-center text-lg font-bold text-white">M</div>
                    <span class="text-lg font-bold text-white tracking-tighter">MyBank</span>
                </div>
                <p class="text-xs">© 2026 MyBank. All rights reserved. FDIC Insured.</p>
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <a href="login.php" class="hover:text-white">Home</a>
                <a href="credit.php" class="hover:text-white">Credit Cards</a>
                <a href="signup.php" class="hover:text-white">Open Account</a>
                <a href="support-login.php" class="hover:text-white">Support</a>
                <a href="terms.php" class="hover:text-white">Terms</a>
            </div>
        </div>
        <p class="mt-6 text-xs text-slate-500 leading-relaxed max-w-3xl">
            Credit cards are issued by MyBank, Member FDIC. Pre-approval results are estimates based on the information you provide and do not guarantee approval. A hard credit inquiry will occur when you submit a full application. Terms, rewards, and APR subject to change.
        </p>
    </div>
</footer>

<script>
// ---------- State ----------
let selectedCard = 'cash';
let currentStep = 1;
const totalSteps = 4;
const stepLabels = [
    'Step 1 of 4 · Personal info',
    'Step 2 of 4 · Address',
    'Step 3 of 4 · Income & card',
    'Step 4 of 4 · Review & submit'
];

const cardNames = {
    cash: 'Cash Rewards',
    travel: 'Travel Elite',
    builder: 'Credit Builder'
};

function selectCard(id) {
    selectedCard = id;
    const pre = document.getElementById('pre-card');
    const app = document.getElementById('apply-card-select');
    if (pre) pre.value = id;
    if (app) app.value = id;
}

// ---------- Mobile menu ----------
(function () {
    const btn = document.getElementById('mobile-menu-btn');
    const closeBtn = document.getElementById('mobile-menu-close');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    if (!btn || !menu || !overlay) return;

    function open() {
        menu.classList.remove('-translate-x-full');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        menu.classList.add('-translate-x-full');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
    }
    btn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
})();

// ---------- Pre-approval ----------
function openPreApproval() {
    document.getElementById('preapproval-modal').classList.remove('hidden');
    document.getElementById('pre-form').classList.remove('hidden');
    document.getElementById('pre-loading').classList.add('hidden');
    document.getElementById('pre-result').classList.add('hidden');
    document.getElementById('pre-card').value = selectedCard;
    document.body.style.overflow = 'hidden';
}
function closePreApproval() {
    document.getElementById('preapproval-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function runPreApproval() {
    const first = document.getElementById('pre-first').value.trim();
    const last = document.getElementById('pre-last').value.trim();
    const email = document.getElementById('pre-email').value.trim();
    const income = parseFloat(document.getElementById('pre-income').value) || 0;
    const housing = parseFloat(document.getElementById('pre-housing').value) || 0;
    const score = document.getElementById('pre-score').value;
    const card = document.getElementById('pre-card').value;

    if (!first || !last || !email || !income) {
        alert('Please fill in all required fields.');
        return;
    }

    document.getElementById('pre-form').classList.add('hidden');
    document.getElementById('pre-loading').classList.remove('hidden');

    // Simulated underwriting logic (demo)
    setTimeout(() => {
        document.getElementById('pre-loading').classList.add('hidden');
        const result = document.getElementById('pre-result');
        result.classList.remove('hidden');

        const dti = housing > 0 ? (housing * 12) / income : 0;
        let status = 'approved';
        let limit = 1500;
        let message = '';

        if (score === 'poor' || income < 18000) {
            status = card === 'builder' ? 'approved' : 'builder';
        } else if (score === 'fair' || dti > 0.45) {
            status = 'conditional';
        } else if (score === 'excellent' && income >= 80000) {
            status = 'approved';
            limit = card === 'travel' ? 15000 : 12000;
        } else if (score === 'good' && income >= 40000) {
            status = 'approved';
            limit = card === 'travel' ? 8000 : 5000;
        } else {
            status = 'approved';
            limit = 2500;
        }

        if (status === 'approved') {
            result.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-500/20 flex items-center justify-center text-3xl text-emerald-400">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h4 class="text-xl font-bold text-emerald-400 mb-1">You're pre-approved!</h4>
                    <p class="text-slate-300 mb-1">for the <strong>${cardNames[card]}</strong> card</p>
                    <p class="text-2xl font-mono font-bold text-white my-3">Up to $${limit.toLocaleString()}</p>
                    <p class="text-xs text-slate-400 mb-6">Estimated credit line · final amount determined at application</p>
                    <button onclick="closePreApproval(); selectCard('${card}'); startApplication();" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-2xl transition">
                        Continue to full application
                    </button>
                    <button onclick="closePreApproval()" class="w-full mt-2 py-2.5 text-sm text-slate-400 hover:text-white transition">Maybe later</button>
                </div>`;
        } else if (status === 'conditional') {
            result.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/20 flex items-center justify-center text-3xl text-amber-400">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <h4 class="text-xl font-bold text-amber-400 mb-1">You may qualify</h4>
                    <p class="text-slate-300 mb-4">Based on your info, we recommend applying for the <strong>Credit Builder</strong> or continuing for a full review of the ${cardNames[card]} card.</p>
                    <button onclick="closePreApproval(); selectCard('builder'); startApplication();" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-2xl transition mb-2">
                        Apply for Credit Builder
                    </button>
                    <button onclick="closePreApproval(); selectCard('${card}'); startApplication();" class="w-full py-3 border border-slate-600 rounded-2xl font-semibold text-sm hover:bg-slate-800 transition">
                        Still apply for ${cardNames[card]}
                    </button>
                </div>`;
        } else {
            result.innerHTML = `
                <div class="text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-500/20 flex items-center justify-center text-3xl text-emerald-400">
                        <i class="fa-solid fa-seedling"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-1">Great news — you can build credit</h4>
                    <p class="text-slate-300 mb-4">The <strong>Credit Builder</strong> card is designed for you. Deposit $200–$2,000 as your credit line and start building history.</p>
                    <button onclick="closePreApproval(); selectCard('builder'); startApplication();" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-2xl transition">
                        Apply for Credit Builder
                    </button>
                </div>`;
        }
    }, 1800);
}

// ---------- Full application multi-step ----------
function startApplication() {
    currentStep = 1;
    document.getElementById('apply-modal').classList.remove('hidden');
    document.getElementById('apply-result').classList.add('hidden');
    document.getElementById('apply-nav').classList.remove('hidden');
    document.querySelectorAll('.apply-step').forEach(s => s.classList.add('hidden'));
    document.querySelector('.apply-step[data-step="1"]').classList.remove('hidden');
    document.getElementById('apply-card-select').value = selectedCard;
    updateStepUI();
    document.body.style.overflow = 'hidden';
}
function closeApplication() {
    document.getElementById('apply-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function updateStepUI() {
    document.getElementById('step-label').textContent = stepLabels[currentStep - 1];
    document.getElementById('progress-fill').style.width = (currentStep / totalSteps * 100) + '%';
    document.querySelectorAll('.step-dot').forEach(dot => {
        const s = parseInt(dot.dataset.step);
        dot.classList.remove('active', 'done');
        if (s === currentStep) dot.classList.add('active');
        else if (s < currentStep) dot.classList.add('done');
    });
    document.getElementById('btn-back').classList.toggle('hidden', currentStep === 1);
    document.getElementById('btn-next').classList.toggle('hidden', currentStep === totalSteps);
    document.getElementById('btn-submit').classList.toggle('hidden', currentStep !== totalSteps);

    if (currentStep === 4) buildReview();
}

function validateStep(step) {
    const panel = document.querySelector(`.apply-step[data-step="${step}"]`);
    const inputs = panel.querySelectorAll('input[required], select[required]');
    for (const el of inputs) {
        if (!el.value.trim()) {
            el.focus();
            el.classList.add('border-rose-500');
            setTimeout(() => el.classList.remove('border-rose-500'), 2000);
            return false;
        }
    }
    return true;
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < totalSteps) {
        document.querySelector(`.apply-step[data-step="${currentStep}"]`).classList.add('hidden');
        currentStep++;
        document.querySelector(`.apply-step[data-step="${currentStep}"]`).classList.remove('hidden');
        updateStepUI();
    }
}
function prevStep() {
    if (currentStep > 1) {
        document.querySelector(`.apply-step[data-step="${currentStep}"]`).classList.add('hidden');
        currentStep--;
        document.querySelector(`.apply-step[data-step="${currentStep}"]`).classList.remove('hidden');
        updateStepUI();
    }
}

function buildReview() {
    const f = document.getElementById('apply-form');
    const fd = new FormData(f);
    const card = fd.get('cardType');
    document.getElementById('review-summary').innerHTML = `
        <p><span class="text-slate-500">Name:</span> ${fd.get('first')} ${fd.get('last')}</p>
        <p><span class="text-slate-500">Email:</span> ${fd.get('email')}</p>
        <p><span class="text-slate-500">Phone:</span> ${fd.get('phone')}</p>
        <p><span class="text-slate-500">Address:</span> ${fd.get('street')}, ${fd.get('city')}, ${fd.get('state')} ${fd.get('zip')}</p>
        <p><span class="text-slate-500">Income:</span> $${Number(fd.get('income')).toLocaleString()}/yr</p>
        <p><span class="text-slate-500">Card:</span> <strong>${cardNames[card] || card}</strong></p>
    `;
}

function submitApplication() {
    if (!document.getElementById('agree-terms').checked || !document.getElementById('agree-esign').checked) {
        alert('Please accept the terms and e-sign consent to continue.');
        return;
    }
    if (!validateStep(4)) return;

    document.querySelectorAll('.apply-step').forEach(s => s.classList.add('hidden'));
    document.getElementById('apply-nav').classList.add('hidden');
    const result = document.getElementById('apply-result');
    result.classList.remove('hidden');
    result.innerHTML = `
        <div class="text-center py-10">
            <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p class="font-semibold">Submitting your application…</p>
            <p class="text-sm text-slate-400 mt-1">Secure connection · matching your MyBank account</p>
        </div>`;

    const f = document.getElementById('apply-form');
    const fd = new FormData(f);
    fd.append('action', 'apply_credit_card');

    fetch('credit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const card = fd.get('cardType');
            if (data.approved) {
                const cardNum = data.card_number
                    ? `<p class="font-mono text-lg tracking-widest text-white my-2">${data.card_number}</p>`
                    : '';
                const savedNote = data.saved
                    ? `<p class="text-sm text-emerald-400 mb-4">Virtual card added to your dashboard.</p>`
                    : `<p class="text-sm text-amber-400 mb-4">${data.message || 'Sign in with the same email to see your card.'}</p>`;
                result.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-emerald-500/20 flex items-center justify-center text-4xl text-emerald-400">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-emerald-400 mb-1">You're approved!</h4>
                        <p class="text-slate-300 mb-1">${data.card_type || cardNames[card]} card</p>
                        ${cardNum}
                        <p class="text-3xl font-mono font-bold my-3">$${(data.limit || 0).toLocaleString()} <span class="text-base font-sans text-slate-400">credit line</span></p>
                        ${savedNote}
                        <a href="index.php" class="block w-full py-3.5 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition mb-2">Go to dashboard</a>
                        <a href="login.php" class="block w-full py-2.5 text-sm text-slate-400 hover:text-white transition">Back to home</a>
                    </div>`;
            } else {
                result.innerHTML = `
                    <div class="text-center py-6">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-700 flex items-center justify-center text-3xl text-slate-400">
                            <i class="fa-solid fa-file-circle-xmark"></i>
                        </div>
                        <h4 class="text-xl font-bold mb-2">We couldn't approve this application</h4>
                        <p class="text-slate-400 text-sm mb-6">${data.message || 'You can try the Credit Builder card or reapply later.'}</p>
                        <button onclick="closeApplication(); selectCard('builder'); startApplication();" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-2xl transition mb-2">
                            Try Credit Builder
                        </button>
                        <a href="support-login.php" class="block w-full py-2.5 text-sm text-slate-400 hover:text-white transition">Contact support</a>
                    </div>`;
            }
        })
        .catch(() => {
            result.innerHTML = `
                <div class="text-center py-6">
                    <h4 class="text-xl font-bold mb-2 text-rose-400">Connection error</h4>
                    <p class="text-slate-400 text-sm mb-4">Please try again in a moment.</p>
                    <button onclick="closeApplication(); startApplication();" class="w-full py-3 bg-blue-600 rounded-2xl font-semibold">Try again</button>
                </div>`;
        });
}
</script>
</body>
</html>

