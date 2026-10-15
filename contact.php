<?php
require 'config.php';
$base = ch_base();
$ok = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = v_str('name', 100, true, 'Name');
    $email = v_email();
    $message = v_str('message', 2000, true, 'Message');
    $to = ADMIN_EMAIL !== '' ? ADMIN_EMAIL : SMTP_FROM;
    $body = "Name: $name\nEmail: $email\n\n$message\n";
    if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $ok = send_app_email($to, 'ChoreHive contact form', $body, $email);
        if (!$ok && !smtp_configured()) {
            $ok = true;
        }
    } else {
        $line = sprintf("[%s] CONTACT FROM %s <%s> | %s\n", date('Y-m-d H:i:s'), $name, $email, $message);
        @file_put_contents(__DIR__ . '/email_outbox.log', $line, FILE_APPEND);
        $ok = true;
    }
    if (!$ok) {
        $error = 'We could not send your message right now. Please try again later.';
    }
} else {
    $error = 'Please submit the contact form from the homepage.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $ok ? 'Message sent' : 'Contact' ?> — ChoreHive</title>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
<style>body{background:#f4f7fc;font-family:Poppins,sans-serif}</style>
</head>
<body>
<div class="w3-container" style="max-width:560px;margin:80px auto;text-align:center;">
  <div class="w3-card w3-white w3-round w3-padding-32">
    <img src="<?= $base ?>/assets/logo.png" alt="ChoreHive logo" style="width:74px;height:74px;border-radius:50%;box-shadow:0 0 0 3px rgba(224,170,51,.55);margin-bottom:8px;">
    <?php if ($ok): ?>
      <h2>Thanks, <?= e($name ?? 'friend') ?>!</h2>
      <p>Your message has been sent. We'll get back to you at <b><?= e($email ?? '') ?></b> soon.</p>
    <?php else: ?>
      <h2>Could not send</h2>
      <p><?= e($error) ?></p>
    <?php endif; ?>
    <a class="w3-button w3-padding-large" style="background:#e0aa33;color:#241d12;border-radius:10px;font-weight:700;" href="<?= $base ?>/index.php">← Back to home</a>
  </div>
</div>
</body>
</html>
