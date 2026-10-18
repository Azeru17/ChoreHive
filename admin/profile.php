<?php
require '../config.php';
$u = require_role('admin');

/* ---------- Actions (before output so redirects work) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verify_csrf();
    $name    = v_str('name', 100, true, 'Full name');
    $phone   = v_str('phone', 30, false, 'Phone');
    $address = v_str('address', 255, false, 'Address');
    $city    = v_str('city', 100, false, 'City / Municipality');
    update_profile($u['id'], $name, $phone, $address, $city, $u['bio']);
    $new = handle_avatar_upload('avatar', $u['id'], $conn);
    if ($new) {
        $old = avatar_file($u);
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param('si', $new, $u['id']);
        $stmt->execute();
        if ($old) media_delete($old);
    }
    audit_log('profile_updated', $name . ' updated their admin profile');
    flash('Profile updated.');
    header('Location: profile.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    verify_csrf();
    delete_avatar($conn, $u['id']);
    flash('Profile picture removed.');
    header('Location: profile.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf();
    change_password($u, $_POST['current_password'] ?? '', $_POST['new_password'] ?? '', $_POST['confirm_password'] ?? '');
    flash('Password updated successfully.');
    header('Location: profile.php'); exit;
}

$u = refresh_session_user($conn) ?: $u;
$av = avatar_url($u, ch_base());
$title = 'My Profile';
require '../layout.php';
?>
<h1>My Profile</h1>

<div class="card">
  <div class="profile-head">
    <?php if ($av): ?><img class="avatar" src="<?= e($av) ?>" alt="Profile picture">
    <?php else: ?><div class="avatar"><?= e(initials($u['name'])) ?></div><?php endif; ?>
    <div class="profile-meta">
      <h1 style="margin-bottom:2px;"><?= e($u['name']) ?></h1>
      <p class="muted" style="margin:0;"><?= e($u['email']) ?></p>
      <p style="margin:8px 0 0;"><span class="badge err">Administrator</span></p>
    </div>
  </div>
</div>

<div class="card">
  <h2>Profile details</h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Full name</label><input name="name" maxlength="100" value="<?= e($u['name']) ?>" required></div>
      <div><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
      <div><label>Phone</label><input name="phone" maxlength="30" value="<?= e($u['phone'] ?? '') ?>" placeholder="09XX XXX XXXX"></div>
      <div><label>Office address</label><input name="address" maxlength="255" value="<?= e($u['address'] ?? '') ?>"></div>
      <div><label>City / Municipality</label><input name="city" maxlength="100" value="<?= e($u['city'] ?? '') ?>"></div>
    </div>
    <label>Profile picture <span class="muted" style="font-weight:400;">(JPG, PNG or WebP · max 2&nbsp;MB)</span></label>
    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
    <p class="actions" style="margin-top:6px;">
      <button name="save_profile" value="1">💾 Save changes</button>
      <?php if ($av): ?><button name="remove_avatar" value="1" class="btn-sec" data-confirm="Remove your profile picture?" data-confirm-title="Remove picture" data-confirm-yes="Yes, remove">Remove picture</button><?php endif; ?>
    </p>
  </form>
</div>

<div class="card">
  <h2>Change password</h2>
  <p class="muted" style="margin-bottom:12px;">Use at least 8 characters. You'll stay signed in on this device.</p>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label for="profileCurrentPassword">Current password</label><div class="password-field"><input id="profileCurrentPassword" type="password" name="current_password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="profileCurrentPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div></div>
      <div><label for="profileNewPassword">New password</label><div class="password-field"><input id="profileNewPassword" type="password" name="new_password" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="profileNewPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div></div>
      <div><label for="profileConfirmPassword">Confirm new password</label><div class="password-field"><input id="profileConfirmPassword" type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="profileConfirmPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i><span>Show</span></button></div></div>
    </div>
    <button name="change_password" value="1" class="btn-sec">🔒 Update password</button>
  </form>
</div>
<?php require '../footer.php'; ?>