<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = v_str('name', 100, true, 'Full name');
    $email = v_email();
    $phone = v_str('phone', 20, true, 'Phone number');
    $address = v_str('address', 255, true, 'Address');
    $city = v_str('city', 100, true, 'City / Municipality');
    // Validate phone number - must contain only digits
    if (!preg_match('/^[0-9+\-\s()]+$/', $_POST['phone']) || strlen(preg_replace('/[^0-9]/', '', $_POST['phone'])) < 7) {
        fail('Please enter a valid phone number with at least 7 digits.');
    }
    if (strlen($_POST['password'] ?? '') < 8) fail('Password must be at least 8 characters.');
    if (strlen($_POST['password']) > 72) fail('Password is too long.');
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = ($_POST['role'] ?? 'client') === 'worker' ? 'worker' : 'client';

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) fail('That email is already registered.');

    $stmt = $conn->prepare("INSERT INTO users (id,name,email,password,role,phone,address,city) VALUES (?,?,?,?,?,?,?,?)");
    $id = get_next_role_id($conn, $role);
    $stmt->bind_param('isssssss', $id, $name, $email, $pass, $role, $phone, $address, $city);
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
    <label>Phone number</label><input type="tel" name="phone" maxlength="20" pattern="[0-9+\-\s()]{7,20}" placeholder="e.g., 09171234567" required>
    <label>Address</label><input name="address" maxlength="255" placeholder="House number, street, barangay" required>
    <label>City / Municipality</label><input name="city" maxlength="100" placeholder="e.g., Cebu City" required>
    <p class="form-hint" style="margin:-6px 0 14px;">Your city helps us match you with cleaners (or clients) in the same area. You can change it later in your profile.</p>
    <label for="registerPassword">Password (min 8 characters)</label><div class="password-field"><input id="registerPassword" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="registerPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div>
    <label>I want to</label>
    <select name="role"><option value="client">Book cleaning services (Client)</option><option value="worker">Offer cleaning services (Worker)</option></select>
    <button>Create account</button>
  </form>
</div>
<?php require 'footer.php'; ?>
