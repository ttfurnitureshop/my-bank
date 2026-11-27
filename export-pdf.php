<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

require_once __DIR__ . '/lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

// Build your statement HTML (query transactions, format nicely)
$html = '
<!DOCTYPE html>
<html>
<head><title>Statement</title></head>
<body style="font-family: Arial;">
    <h1>MyBank Statement – ' . htmlspecialchars($_SESSION['full_name']) . '</h1>
    <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
        <tr><th>Date</th><th>Description</th><th>Amount</th></tr>';

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
foreach ($stmt->fetchAll() as $row) {
    $html .= "<tr><td>{$row['created_at']}</td><td>" . htmlspecialchars($row['description']) . "</td><td>$" . number_format($row['amount'], 2) . "</td></tr>";
}

$html .= '</table></body></html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("statement-" . date('Y-m-d') . ".pdf", ["Attachment" => true]);
exit;