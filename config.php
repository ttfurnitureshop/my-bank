<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Security: regenerate session ID on login
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'sanmarcos_mybank');          // ← change if needed
define('DB_PASS', 'Aj@nt246Aj');                 // ← change if needed
define('DB_NAME', 'sanmarcos_tobi');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Simple CSRF-like token (per request)
function generateToken() {
    return bin2hex(random_bytes(16));
}

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = generateToken();
}

// ───────────────────────────────────────────────
//          EMAIL SENDING (using plain mail())
// ───────────────────────────────────────────────

/**
 * Sends an HTML email with a plain-text fallback using PHP mail()
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML content of the email
 * @param string $fromEmail Sender email (should match your domain if possible)
 * @param string $fromName Sender name
 * @return bool Success
 */
function sendEmail($to, $subject, $htmlBody, $fromEmail = 'no-reply@yourdomain.com', $fromName = 'MyBank') {
    // Create plain text version from HTML
    $plainBody = strip_tags($htmlBody);
    $plainBody = str_replace(['<br>', '<br/>', '<br />'], "\n", $plainBody);
    $plainBody = html_entity_decode($plainBody);

    // Boundary for multipart/alternative
    $boundary = md5(uniqid(time()));

    // Email headers
    $headers   = "MIME-Version: 1.0\r\n";
    $headers  .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers  .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers  .= "Reply-To: {$fromEmail}\r\n";
    $headers  .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // Build message body (multipart)
    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plainBody . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--{$boundary}--\r\n";

    // Attempt to send
    $sent = @mail($to, $subject, $message, $headers);

    if (!$sent) {
        error_log("mail() failed → To: $to | Subject: $subject");
    }

    return $sent;
}

/**
 * Generate a 6-digit verification / reset code
 * @return string 6-digit number as string
 */
function generateCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}



function sendPushNotification($userId, $title, $message) {
    $app_id = 'YOUR_ONESIGNAL_APP_ID';
    $api_key = 'YOUR_ONESIGNAL_REST_API_KEY';   // from OneSignal dashboard

    $content = array("en" => $message);
    $fields = array(
        'app_id' => $app_id,
        'include_external_user_ids' => array($userId),
        'contents' => $content,
        'headings' => array("en" => $title)
    );

    $fields = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}