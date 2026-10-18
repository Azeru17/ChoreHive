<?php
require 'config.php';
$token  = $_GET['token'] ?? $_POST['token'] ?? '';
$email  = $_GET['email'] ?? $_POST['email'] ?? '';
$error = '';
$valid = false;
$user = null;

if ($token && $email) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT id FROM password_resets WHERE user_id=? AND token_hash=? AND used=0 AND expires_at > NOW()");
        $stmt->bind_param('is', $user['id'], $hash);
        $stmt->execute();
        $valid = (bool)$stmt->get_result()->fetch_assoc();
        if (!$valid) $error = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'Missing reset information. Please use the link from your email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    verify_csrf();
    if (strlen($_POST['password'] ?? '') < 8) fail('Password must be at least 8 characters.');
    if ($_POST['password'] !== ($_POST['confirm'] ?? '')) fail('Passwords do not match.');
    $hash_pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param('si', $hash_pw, $user['id']);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND token_hash=?");
    $hash = hash('sha256', $token);
    $stmt->bind_param('is', $user['id'], $hash);
    $stmt->execute();
    // invalidate other sessions' attempts and notify
    notify_and_email($user['id'], 'Your ChoreHive password was just changed. If this wasn\'t you, contact support immediately.', 'Password changed');
    audit_log('password_reset', "Password reset completed for {$user['email']}");
    flash('Password updated! You can now log in with your new password.');
    header('Location: login.php'); exit;
}
$title = 'Reset Password';
require 'layout.php';
?>
<div class="card auth-card">
  <h1>Reset Password</h1>
  <?php if ($error): ?>
    <p class="flash error"><?= e($error) ?></p>
    <p class="muted"><a href="forgot_password.php">Request a new reset link</a></p>
  <?php elseif ($valid): ?>
    <p class="muted">Choose a new password for <b><?= e($email) ?></b>.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <input type="hidden" name="email" value="<?= e($email) ?>">
      <label for="resetNewPassword">New password (min 8 characters)</label><div class="password-field"><input id="resetNewPassword" type="password" name="password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="resetNewPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div>
      <label for="resetConfirmPassword">Confirm new password</label><div class="password-field"><input id="resetConfirmPassword" type="password" name="confirm" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="resetConfirmPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div>
      <button>Update password</button>
    </form>
  <?php endif; ?>
</div>
<?php require 'footer.php'; ?>
