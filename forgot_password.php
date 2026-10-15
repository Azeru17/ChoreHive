<?php
require 'config.php';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = v_email();
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        $uid = $user['id'];
        $stmt->bind_param('is', $uid, $hash);
        $stmt->execute();

        $reset_link = app_url('reset_password.php?token=' . $token . '&email=' . urlencode($email));
        $mailBody = "Hello {$user['name']},\n\nReset your ChoreHive password using this link (valid for 30 minutes):\n\n$reset_link\n\nIf you did not request this, you can ignore this email.";
        send_app_email($email, 'Reset your ChoreHive password', $mailBody);
        audit_log('password_reset_requested', "Reset requested for $email");
    }
    $sent = true;
}
$title = 'Forgot Password';
require 'layout.php';
?>
<div class="card auth-card">
  <h1>Forgot Password</h1>
  <?php if ($sent): ?>
    <p class="flash success">If that email is registered, a reset link has been sent (valid for 30 minutes).</p>
    <?php if (!smtp_configured()): ?>
      <p class="muted">Email delivery is not configured on this server yet (SMTP is empty in <code>config.local.php</code>). Ask an administrator to complete setup, or check the local mail log if you are developing on XAMPP.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">Enter your account email and we'll send you a reset link.</p>
    <form method="post">
      <?= csrf_field() ?>
      <label>Email</label><input type="email" name="email" required>
      <button>Send reset link</button>
    </form>
  <?php endif; ?>
  <p class="muted"><a href="login.php">← Back to login</a></p>
</div>
<?php require 'footer.php'; ?>
