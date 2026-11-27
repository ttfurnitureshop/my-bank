<?php
$host = getenv('MYSQLHOST') ?: 'nozomi.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: '34085';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD'); // Set this in Vercel Environment Variables!

$conn = new mysqli($host, $user, $pass, $db, (int)$port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
