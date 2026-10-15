<?php
require '../config.php';
$u = require_role('client');

/* ---------- Actions (before output so redirects work) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verify_csrf();
    $name    = v_str('name', 100, true, 'Full name');
    $phone   = v_str('phone', 30, false, 'Phone');
    $address = v_str('address', 255, false, 'Address');
    update_profile($u['id'], $name, $phone, $address, $u['bio']);
    $new = handle_avatar_upload('avatar', $u['id'], $conn); // may be null
    if ($new) {
        $old = avatar_file($u);
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param('si', $new, $u['id']);
        $stmt->execute();
        if ($old && is_file(UPLOAD_DIR . '/' . $old)) @unlink(UPLOAD_DIR . '/' . $old);
    }
    audit_log('profile_updated', $name . ' updated their client profile');
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

/* ---------- Address book actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    verify_csrf();
    $label = v_str('label', 60, true, 'Address label');
    $address = v_str('address', 255, true, 'Address');
    $lat = null; $lng = null;
    if (!empty($_POST['address_lat']) && !empty($_POST['address_lng'])) {
        $lt = (float)$_POST['address_lat']; $ln = (float)$_POST['address_lng'];
        if ($lt >= -90 && $lt <= 90 && $ln >= -180 && $ln <= 180) {
            $lat = number_format($lt, 7, '.', ''); $lng = number_format($ln, 7, '.', '');
        }
    }
    $id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
    if ($id) {
        $st = $conn->prepare("UPDATE saved_addresses SET label=?, address=?, address_lat=?, address_lng=? WHERE id=? AND client_id=?");
        $st->bind_param('ssssii', $label, $address, $lat, $lng, $id, $u['id']);
        $st->execute();
        flash('Address updated.');
    } else {
        $st = $conn->prepare("INSERT INTO saved_addresses (client_id, label, address, address_lat, address_lng) VALUES (?,?,?,?,?)");
        $st->bind_param('issss', $u['id'], $label, $address, $lat, $lng);
        $st->execute();
        flash('Address saved.');
    }
    header('Location: profile.php#addressbook'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_address'])) {
    verify_csrf();
    $id = v_int('address_id', 1, PHP_INT_MAX, 'Address');
    $st = $conn->prepare("DELETE FROM saved_addresses WHERE id=? AND client_id=?");
    $st->bind_param('ii', $id, $u['id']);
    $st->execute();
    flash('Address removed.');
    header('Location: profile.php#addressbook'); exit;
}

$savedAddresses = [];
{
    $st = $conn->prepare("SELECT id, label, address, address_lat, address_lng FROM saved_addresses WHERE client_id=? ORDER BY created_at DESC");
    $st->bind_param('i', $u['id']);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) $savedAddresses[] = $r;
}

$u = refresh_session_user($conn) ?: $u;
$av = avatar_url($u, '/chorehive');
$stats = $conn->query("SELECT COUNT(*) total,
  SUM(status='completed') completed,
  COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price END),0) spent
  FROM bookings WHERE client_id=" . (int)$u['id'])->fetch_assoc();

$title = 'My Profile';
require '../layout.php';
?>
<h1>My Profile</h1>

<div class="card">
  <div class="profile-head">
    <?php if ($av): ?>
      <img class="avatar" src="<?= e($av) ?>" alt="Profile picture">
    <?php else: ?>
      <div class="avatar"><?= e(initials($u['name'])) ?></div>
    <?php endif; ?>
    <div class="profile-meta">
      <h1 style="margin-bottom:2px;"><?= e($u['name']) ?></h1>
      <p class="muted" style="margin:0;"><?= e($u['email']) ?></p>
      <p style="margin:8px 0 0;"><span class="badge info">Client account</span>
        <span class="badge warn">Member since <?= date('M Y', strtotime($u['created_at'])) ?></span></p>
    </div>
  </div>
</div>

<div class="grid" style="margin-bottom:18px;">
  <div class="stat"><b><?= (int)$stats['total'] ?></b>Total bookings</div>
  <div class="stat"><b><?= (int)$stats['completed'] ?></b>Completed jobs</div>
  <div class="stat"><b class="money">₱<?= number_format($stats['spent'], 2) ?></b>Total paid</div>
</div>

<div class="card">
  <h2>Profile details</h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Full name</label><input name="name" maxlength="100" value="<?= e($u['name']) ?>" required></div>
      <div><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
      <div><label>Phone</label><input name="phone" maxlength="30" value="<?= e($u['phone'] ?? '') ?>" placeholder="09XX XXX XXXX"></div>
      <div><label>Address</label><input name="address" maxlength="255" value="<?= e($u['address'] ?? '') ?>" placeholder="Street, city"></div>
    </div>
    <label>Profile picture <span class="muted" style="font-weight:400;">(JPG, PNG or WebP · max 2&nbsp;MB)</span></label>
    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
    <p class="actions" style="margin-top:6px;">
      <button name="save_profile" value="1">💾 Save changes</button>
      <?php if ($av): ?>
      <button name="remove_avatar" value="1" class="btn-sec" onclick="return confirm('Remove your profile picture?')">Remove picture</button>
      <?php endif; ?>
    </p>
  </form>
</div>

<div class="card">
  <h2>Change password</h2>
  <p class="muted" style="margin-bottom:12px;">Use at least 8 characters. You'll stay signed in on this device.</p>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
      <div><label>New password</label><input type="password" name="new_password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
      <div><label>Confirm new password</label><input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
    </div>
    <button name="change_password" value="1" class="btn-sec">🔒 Update password</button>
  </form>
</div>

<div class="card" id="addressbook" style="margin-top:18px;">
  <h2>📍 Address book</h2>
  <p class="muted" style="margin-bottom:14px;">Save your home, office, or other locations so booking is faster next time.</p>

  <?php if ($savedAddresses): ?>
    <div style="display:grid;gap:10px;margin-bottom:18px;">
      <?php foreach ($savedAddresses as $sa): ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#fafcff;">
          <div style="min-width:0;">
            <b><?= e($sa['label']) ?></b>
            <div class="muted" style="font-size:.9rem;"><?= e($sa['address']) ?></div>
            <?php if ($sa['address_lat'] && $sa['address_lng']): ?>
              <a class="muted" style="font-size:.82rem;" href="https://www.openstreetmap.org/?mlat=<?= e($sa['address_lat']) ?>&mlon=<?= e($sa['address_lng']) ?>#map=16/<?= e($sa['address_lat']) ?>/<?= e($sa['address_lng']) ?>" target="_blank" rel="noopener">View pinned location ↗</a>
            <?php endif; ?>
          </div>
          <form method="post" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="address_id" value="<?= (int)$sa['id'] ?>">
            <button type="button" class="btn btn-sm btn-sec" onclick="editAddr(<?= (int)$sa['id'] ?>, <?= json_encode($sa['label']) ?>, <?= json_encode($sa['address']) ?>, <?= json_encode($sa['address_lat'] ?? '') ?>, <?= json_encode($sa['address_lng'] ?? '') ?>)">Edit</button>
            <button name="delete_address" value="1" class="btn btn-sm" style="background:#fdecea;color:#c0392b;border-color:#f5c6c0;" onclick="return confirm('Delete this saved address?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted" style="margin-bottom:14px;">You haven't saved any addresses yet.</p>
  <?php endif; ?>

  <details <?= isset($_POST['save_address']) ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:600;margin-bottom:10px;">+ Add a new address</summary>
    <form method="post" id="addrForm" style="margin-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="address_id" id="addr_id" value="">
      <div class="form-grid">
        <div><label>Label</label><input name="label" id="addr_label" maxlength="60" required placeholder="Home, Office, Condo…"></div>
        <div><label>Full address</label><input name="address" id="addr_address" maxlength="255" required placeholder="House number, street, barangay, city"></div>
      </div>
      <p class="form-hint" style="margin-top:8px;">Optional: you can also save a map pin when booking — the address will be stored with its coordinates.</p>
      <input type="hidden" name="address_lat" id="addr_lat" value="">
      <input type="hidden" name="address_lng" id="addr_lng" value="">
      <button name="save_address" value="1" class="btn-sec" style="margin-top:10px;">💾 Save address</button>
    </form>
  </details>
</div>

<script>
function editAddr(id, label, address, lat, lng) {
  document.getElementById('addr_id').value = id;
  document.getElementById('addr_label').value = label;
  document.getElementById('addr_address').value = address;
  document.getElementById('addr_lat').value = lat || '';
  document.getElementById('addr_lng').value = lng || '';
  document.querySelector('#addressbook details').open = true;
  document.getElementById('addr_label').focus();
  window.scrollTo({top: document.getElementById('addressbook').offsetTop - 20, behavior: 'smooth'});
}
</script>
<?php require '../footer.php'; ?>