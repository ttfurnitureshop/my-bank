<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- EMAIL ATTACHMENT HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);
    $html_content = $_POST['check_content'];
    
    $subject = "Digital Check Attachment - MyBank";
    $from = "MyBank Notifications <no-reply@yourdomain.com>";
    $boundary = md5(time()); 

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: " . $from . "\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";

    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "Please find your digital check attached.\r\n\r\n";

    $filename = "MyBank_Check.html";
    $attachment = chunk_split(base64_encode($html_content));

    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; name=\"" . $filename . "\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"" . $filename . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $attachment . "\r\n";
    $body .= "--" . $boundary . "--";

    if (mail($to, $subject, $body, $headers)) {
        $msg = "Check successfully sent to $to";
    }
}

// Database Fetch
$stmtUser = $pdo->prepare("SELECT full_name, billing_address, address_postal FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$stmtAcc = $pdo->prepare("SELECT account_number, routing_number FROM accounts WHERE user_id = ? AND name = 'Checking' LIMIT 1");
$stmtAcc->execute([$userId]);
$account = $stmtAcc->fetch(PDO::FETCH_ASSOC);

$fullName       = !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : "USER";
$billingAddress  = !empty($user['billing_address']) ? htmlspecialchars($user['billing_address']) : "";
$postalCode     = !empty($user['address_postal']) ? htmlspecialchars($user['address_postal']) : "";
$accNumber      = !empty($account['account_number']) ? $account['account_number'] : "00000000";
$routNumber     = !empty($account['routing_number']) ? $account['routing_number'] : "00000000";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyBank • Disbursement Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rochester&display=swap');
        body { background-color: #0f172a; font-family: sans-serif; margin: 0; padding: 0; }
        
        /* The container ensures front and back never collide */
        .check-container {
            display: block;
            width: 850px;
            margin: 0 auto;
            padding: 40px 0;
        }

        .check-base { 
            width: 800px; 
            height: 380px; /* Slightly taller to accommodate MICR footer */
            padding: 30px; 
            margin: 0 auto 100px auto; /* 100px gap between checks */
            border: 2px solid #3b82f6; 
            box-sizing: border-box; 
            background-color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .check-front { background-color: #f0f7ff; color: #1e3a8a; }
        .check-back { background-color: #e2e8f0; color: #475569; }

        /* Dedicated MICR row at the absolute bottom */
        .micr-footer {
            margin-top: auto; /* Pushes to bottom of the flex container */
            padding-top: 20px;
            font-family: 'Courier New', Courier, monospace !important;
            font-size: 22px !important;
            font-weight: bold !important;
            letter-spacing: 5px !important;
            text-align: center;
            width: 100%;
        }

        .signature-font { font-family: 'Rochester', cursive; font-size: 30px; line-height: 1; }
        [contenteditable="true"] { outline: none; border-bottom: 1.5px solid #1e3a8a; min-width: 40px; display: inline-block; }
        
        @media print { 
            .no-print { display: none !important; } 
            body { background: white !important; }
            .check-base { border: 1px solid #000; page-break-after: always; margin-bottom: 50px; } 
        }
    </style>
</head>
<body class="py-10">

    <div class="no-print flex flex-wrap gap-4 justify-center mb-10">
        <button onclick="window.print()" class="bg-slate-700 text-white px-6 py-3 rounded-xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-print"></i> PRINT
        </button>
        <button onclick="downloadPDF()" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold flex items-center gap-2 shadow-lg">
            <i class="fa-solid fa-download"></i> DOWNLOAD PDF
        </button>
        <button onclick="document.getElementById('emailModal').classList.remove('hidden')" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold flex items-center gap-2 shadow-lg">
            <i class="fa-solid fa-envelope"></i> SEND EMAIL
        </button>
    </div>

    <div id="capture-area" class="check-container">
        
        <div class="check-base check-front">
            <div style="display:flex; justify-content:space-between; align-items: flex-start;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <div style="background:#1e40af; color:white; padding:4px 10px; font-weight:bold; border-radius:4px;">M</div>
                        <span style="font-size:20px; font-weight:900; font-style:italic;">MYBANK</span>
                    </div>
                    <div style="font-size:11px; font-weight:bold; text-transform:uppercase; line-height:1.3;">
                        <div><?= $fullName ?></div>
                        <div><?= $billingAddress ?></div>
                        <div><?= $postalCode ?></div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:bold; font-size:18px; font-family: monospace;">1042</div>
                    <div style="margin-top:15px; font-size:11px; font-weight:bold;">DATE <span contenteditable="true" style="margin-left:5px; font-family: monospace; font-size:14px;"><?= date('m/d/Y') ?></span></div>
                </div>
            </div>

            <div style="margin-top:30px; display:flex; align-items:flex-end;">
                <div style="flex-grow:1; border-bottom:1.5px solid #1e3a8a; position:relative; padding-bottom:3px;">
                    <span style="font-size:9px; position:absolute; top:-14px; font-weight: bold;">PAY TO THE ORDER OF</span>
                    <span contenteditable="true" style="font-size:18px; font-weight:bold;">Recipient Name</span>
                </div>
                <div style="border: 2.5px solid #1e3a8a; width: 140px; height: 40px; background: white; display: flex; align-items: center; padding: 0 10px; margin-left: 15px;">
                    <span style="font-weight:bold; margin-right:5px;">$</span>
                    <span contenteditable="true" style="flex-grow:1; text-align:right; font-family: monospace; font-weight:bold; font-size:17px;">0.00</span>
                </div>
            </div>

            <div style="margin-top:25px; border-bottom:1.5px solid #1e3a8a; font-style:italic; position:relative; padding-bottom:3px;">
                <span contenteditable="true" style="font-size:15px;">Zero dollars and 00/100</span>
                <span style="font-size:10px; position:absolute; right:-65px; bottom:3px; font-weight: bold; font-style: normal;">DOLLARS</span>
            </div>

            <div style="margin-top:30px; display:flex; justify-content:space-between; align-items:flex-end;">
                <div style="width:38%; border-bottom:1.5px solid #1e3a8a; position:relative; padding-bottom:3px;">
                    <span style="font-size:9px; position:absolute; top:-14px; font-weight: bold;">MEMO</span>
                    <span contenteditable="true" style="font-size:13px;">Digital Settlement</span>
                </div>
                <div style="width:45%; border-bottom:1.5px solid #1e3a8a; text-align:center; padding-bottom:2px;" class="signature-font">
                    <?= $fullName ?>
                </div>
            </div>

            <div class="micr-footer">
               |: <?= $routNumber ?> |:  <?= $accNumber ?> ||' 1042
            </div>
        </div>

        <div class="check-base check-back" style="justify-content: space-between; display: flex; flex-direction: column;">
            <div style="border-bottom:2px solid #94a3b8; height:45%; position:relative;">
                <div style="position:absolute; top:20px; right:40px; border-bottom:1px dashed #64748b; width:220px; height:40px; text-align:center; color:#94a3b8; font-weight:bold;">Endorse Here</div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:10px; font-weight:bold; letter-spacing:1px;">ORIGINAL DOCUMENT SECURITY FEATURES</div>
                <div style="font-size:35px; color:#94a3b8;"><i class="fa-solid fa-barcode"></i></div>
            </div>
        </div>

    </div>

    <div id="emailModal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50">
        <div class="bg-slate-900 p-8 rounded-2xl w-full max-w-md border border-slate-700">
            <h2 class="text-white text-xl font-bold mb-4">Email Attachment</h2>
            <form method="POST" id="emailForm">
                <input type="email" name="recipient_email" required placeholder="Recipient Email" class="w-full p-3 rounded-lg mb-4 bg-slate-800 text-white outline-none border border-slate-600 focus:border-blue-500">
                <input type="hidden" name="check_content" id="check_content_input">
                <input type="hidden" name="send_email" value="1">
                <div class="flex gap-2">
                    <button type="button" onclick="prepareAndSend()" class="flex-grow bg-blue-600 text-white p-3 rounded-lg font-bold">SEND NOW</button>
                    <button type="button" onclick="document.getElementById('emailModal').classList.add('hidden')" class="bg-slate-700 text-white p-3 rounded-lg font-bold">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('capture-area');
            const opt = {
                margin:       0.2, 
                filename:     'MyBank_Check_Disbursement.pdf',
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { 
                    scale: 3, 
                    useCORS: true,
                    letterRendering: true,
                    windowWidth: 1000 // Forces a wider view for the capture engine
                },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }

        function prepareAndSend() {
            const checkData = document.getElementById('capture-area').innerHTML;
            const styles = document.getElementsByTagName('style')[0].innerHTML;
            const finalAttachment = `<html><head><style>${styles}</style></head><body style="background:#f8fafc; padding:20px;">${checkData}</body></html>`;
            document.getElementById('check_content_input').value = finalAttachment;
            document.getElementById('emailForm').submit();
        }
    </script>
</body>
</html>



