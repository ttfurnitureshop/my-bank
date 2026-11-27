<?php
// db.php - Hostinger compatible version

$host     = 'localhost';
$dbname   = 'sanmarcos_tobi';
$dbuser   = 'sanmarcos_mybank';
$dbpass   = 'Aj@nt246Aj';   // ← MUST match the one in Hostinger

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
// Remove this line after testing
?>