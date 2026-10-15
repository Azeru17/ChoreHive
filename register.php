<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = v_str('name', 100, true, 'Full name');
    $email = v_email();
    if (strlen($_POST['password'] ?? '') < 8) fail('Password must be at least 8 characters.');
    if (strlen($_POST['password']) > 72) fail('Password is too long.');
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = ($_POST['role'] ?? 'client') === 'worker' ? 'worker' : 'client';

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) fail('That email is already registered.');

    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $name, $email, $pass, $role);
    $stmt->execute();
    $welcome = $role === 'worker'
        ? "Welcome to ChoreHive, $name! Your worker profile is pending administrator review and will not appear in the marketplace until approved."
        : "Welcome to ChoreHive, $name! Your client account is ready.";
    notify_and_email($conn->insert_id, $welcome, 'Welcome to ChoreHive');
    audit_log('user_registered', "$role account created for $name <$email>");
    $_SESSION['just_registered'] = true;
    flash('Registration successful! Please login.');
    header('Location: login.php'); exit;
}
$title = 'Register';
require 'layout.php';
?>
<div class="card auth-card">
  <h1>Register</h1>
  <form method="post">
    <?= csrf_field() ?>
    <label>Full name</label><input name="name" maxlength="100" required>
    <label>Email</label><input type="email" name="email" maxlength="120" required>
    <label for="registerPassword">Password (min 8 characters)</label><div class="password-field"><input id="registerPassword" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="registerPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div>
    <label>I want to</label>
    <select name="role"><option value="client">Book cleaning services (Client)</option><option value="worker">Offer cleaning services (Worker)</option></select>
    <p class="muted">Worker profiles are reviewed by an administrator before they are listed or available for booking. This review is not an identity or background check.</p>
    <button>Create account</button>
  </form>
</div>
<?php require 'footer.php'; ?>
