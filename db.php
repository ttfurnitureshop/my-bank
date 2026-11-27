<?php
$host = getenv('MYSQLHOST') ?: 'mainline.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: '52296';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD')?:'PTYDmdhVYIDfzCgGBUEXXrILbQuotXzW'; // Set this in Vercel Environment Variables!

$conn = new mysqli($host, $user, $pass, $db, (int)$port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
