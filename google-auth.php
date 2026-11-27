<?php
require 'config.php';

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;

    // Use cURL instead of file_get_contents to bypass allow_url_fopen restrictions
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Ensure secure connection
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $payload = json_decode($response, true);

        if ($payload && isset($payload['email'])) {
            $email = $payload['email'];

            // Prepare and execute database check
            $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                // User not found in database, redirect to signup
                header("Location: signup.php?msg=please_register");
                exit;
            }
        }
    }
}

// Default redirect if authentication fails or access is direct
header("Location: login.php");
exit;
