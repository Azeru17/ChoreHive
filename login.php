<?php
require 'config.php';
$error = '';

/* ---------- Rate limiting: max 5 failed attempts per email+IP per 15 min ---------- */
function login_attempts_left($email) {
    global $conn;
    $key = strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE attempt_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return 5;
    // reset window after 15 minutes
    if (time() - strtotime($row['last_attempt']) > 900) return 5;
    return max(0, 5 - (int)$row['attempts']);
}
function login_record_fail($email) {
    global $conn;
    $key = strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $conn->query("INSERT INTO login_attempts (attempt_key, attempts) VALUES ('" . $conn->real_escape_string($key) . "', 1)
                  ON DUPLICATE KEY UPDATE attempts = IF(TIME_TO_SEC(TIMEDIFF(NOW(), last_attempt)) > 900, 1, attempts + 1), last_attempt = NOW()");
}
function login_clear($email) {
    global $conn;
    $key = strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = v_email();
    $left = login_attempts_left($email);
    if ($left <= 0) {
        $error = 'Too many failed attempts. Please wait 15 minutes or reset your password below.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password'])) {
            login_clear($email);
            // Detect brand-new users: freshly registered OR never completed a booking
            $done = $conn->query("SELECT COUNT(*) c FROM bookings WHERE client_id={$user['id']} AND status='completed'")->fetch_assoc()['c'];
            $_SESSION['is_new_user'] = ((int)$user['is_new'] === 1) && ((int)$done === 0);
            if ($_SESSION['is_new_user']) {
                $conn->query("UPDATE users SET is_new=0 WHERE id={$user['id']}");
            }
            $_SESSION['user'] = $user;
            audit_log('login', "{$user['name']} logged in");
            header('Location: ' . ($user['role']==='admin' ? 'admin/dashboard.php' : ($user['role']==='worker' ? 'worker/dashboard.php' : 'client/dashboard.php')));
            exit;
        }
        login_record_fail($email);
        $left = login_attempts_left($email);
        $error = 'Invalid email or password.' . ($left <= 2 ? " ($left attempt" . ($left == 1 ? '' : 's') . " remaining)" : '');
    }
}
$title = 'Login';
require 'layout.php';
?>
<div class="card auth-card">
  <h1>Login</h1>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label><input type="email" name="email" required>
    <label for="loginPassword">Password</label><div class="password-field"><input id="loginPassword" type="password" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle="loginPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div>
    <button>Login</button>
  </form>
  <p class="muted"><a href="forgot_password.php">Forgot your password?</a></p>
  <p class="muted">No account? <a href="register.php">Register here</a></p>
</div>
<?php require 'footer.php'; ?>
