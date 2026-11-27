<?php
// withdrawal_confirmation.php
// Variables available: $amount, $token, $network, $external_wallet, $fee, $txid, $now, $fullname
?>
<div style='font-family:Arial;background:#f2f4f8;padding:25px'>
  <div style='max-width:600px;margin:auto;background:#fff;border-radius:6px'>
    <div style='background:#0b2c63;color:#fff;padding:22px;text-align:center'>
      <h2>MyBank</h2>
      <p>Crypto Withdrawal Confirmation</p>
    </div>
    <div style='padding:25px'>
      <h3>Withdrawal Sent</h3>
      <p>Dear <?= htmlspecialchars($fullname ?? 'Valued Customer') ?>,</p>
      <p>Your withdrawal has been successfully processed.</p>
      <div style='background:#eef3fb;padding:18px;font-size:22px;color:#c62828;font-weight:bold'>
        -<?= number_format($amount, 6) ?> <?= $token ?> (~$<?= number_format($amount * 62000, 2) ?>)
      </div>
      <p><strong>Token:</strong> <?= $token ?></p>
      <p><strong>Network:</strong> <?= $network ?></p>
      <p><strong>To Address:</strong> <?= htmlspecialchars($external_wallet) ?></p>
      <p><strong>Fee:</strong> <?= number_format($fee, 6) ?> <?= $token ?></p>
      <p><strong>TXID:</strong> <?= $txid ?></p>
      <p><strong>Date:</strong> <?= $now ?></p>
      <p><strong>Status:</strong> Sent</p>
      <p>If you did not initiate this withdrawal, please contact support immediately.</p>
    </div>
    <div style='background:#f1f1f1;text-align:center;padding:12px;font-size:12px'>
      © 2026 MyBank<br>
      This is an automated message – please do not reply.
    </div>
  </div>
</div>