<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: regenerate session ID
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'sanmarcos_mybank');
define('DB_PASS', 'Aj@nt246Aj');
define('DB_NAME', 'sanmarcos_tobi');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// CSRF Token
function generateToken() {
    return bin2hex(random_bytes(16));
}

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = generateToken();
}

// ───────────────────────────────────────────────
//          PROFESSIONAL EMAIL SENDER
// ───────────────────────────────────────────────

/**
 * Sends a professional HTML email with plain-text fallback
 */
function sendEmail($to, $subject, $htmlBody, $fromEmail = null, $fromName = 'MyBank') {
    // Use a better default From address
    if ($fromEmail === null) {
        $fromEmail = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'mybank.com');
    }

    // Create plain text version
    $plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
    $plainBody = html_entity_decode($plainBody);
    $plainBody = preg_replace('/\n\s+\n/', "\n\n", $plainBody);
    $plainBody = trim($plainBody);

    $boundary = md5(uniqid(time()));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plainBody . "\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--{$boundary}--";

    $sent = mail($to, $subject, $message, $headers);

    if (!$sent) {
        error_log("FAILED to send email → To: $to | Subject: $subject");
    }

    return $sent;
}

/**
 * Generate a secure 6-digit code
 */
function generateCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ───────────────────────────────────────────────
//          PUSH NOTIFICATION
// ───────────────────────────────────────────────

function sendPushNotification($userId, $title, $message) {
    $app_id  = 'YOUR_ONESIGNAL_APP_ID';
    $api_key = 'YOUR_ONESIGNAL_REST_API_KEY';

    $fields = json_encode([
        'app_id' => $app_id,
        'include_external_user_ids' => [$userId],
        'contents' => ["en" => $message],
        'headings' => ["en" => $title]
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}