<?php
require '../config.php';
$u = require_role('worker');

/* Self-service account deletion: preserve transaction history while anonymizing personal details. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_my_account'])) {
    verify_csrf();
    $pw = (string)($_POST['delete_password'] ?? '');
    $roleForDelete = 'worker';
    $check = $conn->prepare("SELECT password, avatar FROM users WHERE id=? AND role=?");
    $check->bind_param('is', $u['id'], $roleForDelete);
    $check->execute();
    $account = $check->get_result()->fetch_assoc();
    if (!$account || !password_verify($pw, $account['password'])) fail('Password did not match. Your account was not deleted.');
    if (!empty($account['avatar'])) media_delete($account['avatar']);
    $newName = 'Deleted account #' . (int)$u['id'];
    $newEmail = 'deleted-worker-' . (int)$u['id'] . '-' . bin2hex(random_bytes(4)) . '@deleted.invalid';
    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $st = $conn->prepare("UPDATE users SET name=?, email=?, password=?, phone=NULL, address=NULL, city=NULL, bio=NULL, avatar=NULL, worker_approved=0 WHERE id=?");
    $st->bind_param('sssi', $newName, $newEmail, $randomPassword, $u['id']);
    $st->execute();
    if ('worker' === 'worker') {
      $del = $conn->prepare("DELETE FROM worker_portfolio WHERE worker_id=?");
      if ($del) { $del->bind_param('i',$u['id']); $del->execute(); }
    }
    audit_log('account_self_deleted', "User #".(int)$u['id']." requested account deletion; personal profile fields anonymized and historical records retained");
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $params=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']); }
    session_destroy();
    header('Location: ../login.php?account_deleted=1'); exit;
}


/* ---------- Actions (before output so redirects work) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    verify_csrf();
    $name    = v_str('name', 100, true, 'Full name');
    $phone   = v_str('phone', 30, false, 'Phone');
    $address = v_str('address', 255, false, 'Address');
    $city    = 'Polomolok'; // Worker service area is restricted to Polomolok.
    $bio     = v_str('bio', 500, false, 'Bio');
    update_profile($u['id'], $name, $phone, $address, $city, $bio);
    $new = handle_avatar_upload('avatar', $u['id'], $conn);
    if ($new) {
        $old = avatar_file($u);
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param('si', $new, $u['id']);
        $stmt->execute();
        if ($old) media_delete($old);
    }
    audit_log('profile_updated', $name . ' updated their worker profile');
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

/* ---------- Portfolio ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_portfolio'])) {
    verify_csrf();
    $caption = v_str('caption', 255, false, 'Caption');
    $img = handle_portfolio_upload('portfolio_image', $u['id']);
    if (!$img) fail('Please choose a valid JPG, PNG or WebP image (max 3 MB).');
    $st = $conn->prepare("INSERT INTO worker_portfolio (worker_id, image, caption) VALUES (?,?,?)");
    $st->bind_param('iss', $u['id'], $img, $caption);
    $st->execute();
    audit_log('portfolio_added', $u['name'] . ' added a portfolio photo');
    flash('Portfolio photo added.');
    header('Location: profile.php#portfolio'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_portfolio'])) {
    verify_csrf();
    $id = v_int('photo_id', 1, PHP_INT_MAX, 'Photo');
    $st = $conn->prepare("SELECT image FROM worker_portfolio WHERE id=? AND worker_id=?");
    $st->bind_param('ii', $id, $u['id']);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if ($row) {
        media_delete($row['image']);
        $del = $conn->prepare("DELETE FROM worker_portfolio WHERE id=? AND worker_id=?");
        $del->bind_param('ii', $id, $u['id']);
        $del->execute();
        flash('Portfolio photo removed.');
    }
    header('Location: profile.php#portfolio'); exit;
}

$uid = (int)$u['id'];
$u = refresh_session_user($conn) ?: $u;
$av = avatar_url($u, ch_base());

/* Only recorded money counts in payment-value totals; demo jobs remain excluded. */
$earn  = $conn->query("SELECT COUNT(*) jobs, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) total FROM bookings WHERE worker_id=$uid AND status='completed'")->fetch_assoc();
$mEarn = $conn->query("SELECT COUNT(*) jobs, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) total FROM bookings WHERE worker_id=$uid AND status='completed' AND schedule >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetch_assoc();
$dEarn = $conn->query("SELECT COUNT(*) jobs, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) total FROM bookings WHERE worker_id=$uid AND status='completed' AND DATE(schedule)=CURDATE()")->fetch_assoc();
$wEarn = $conn->query("SELECT COUNT(*) jobs, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) total FROM bookings WHERE worker_id=$uid AND status='completed' AND schedule >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc();
$byDay = $conn->query("SELECT DATE(schedule) d, COUNT(*) jobs, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_price ELSE 0 END),0) total FROM bookings WHERE worker_id=$uid AND status='completed' GROUP BY d ORDER BY d DESC LIMIT 7");
$avg = $conn->query("SELECT AVG(f.rating) avg_r, COUNT(f.id) cnt FROM feedback f
  JOIN bookings b ON b.id=f.booking_id WHERE b.worker_id=$uid")->fetch_assoc();
$badges = $conn->query("SELECT * FROM badges WHERE worker_id=$uid ORDER BY earned_at DESC");
$portfolio = $conn->query("SELECT id, image, caption, created_at FROM worker_portfolio WHERE worker_id=$uid ORDER BY created_at DESC");

$title = 'My Profile';
require '../layout.php';
?>
<h1>My Profile</h1>

<div class="card">
  <div class="profile-head">
    <?php if ($av): ?><img class="avatar profile-zoom-trigger" src="<?= e($av) ?>" alt="Profile picture" title="Click to enlarge" tabindex="0" role="button" aria-label="Enlarge profile picture">
    <?php else: ?><div class="avatar"><?= e(initials($u['name'])) ?></div><?php endif; ?>
    <div class="profile-meta">
      <h1 style="margin-bottom:2px;"><?= e($u['name']) ?></h1>
      <p class="muted" style="margin:0 0 8px;"><?= e($u['email']) ?></p>
      <p style="margin:0;">
        <span class="badge info">Worker account</span>
        <?php if ((int)$avg['cnt']): ?><span class="badge warn">⭐ <?= number_format($avg['avg_r'], 1) ?> (<?= (int)$avg['cnt'] ?> reviews)</span><?php endif; ?>
        <span class="badge ok"><?= (int)$earn['jobs'] ?> jobs done</span>
      </p>
      <?php if (!empty($u['bio'])): ?><p class="muted" style="margin:10px 0 0;"><?= nl2br(e($u['bio'])) ?></p><?php endif; ?>
    </div>
  </div>
</div>

<h2 style="margin-bottom:10px;">Recorded paid booking value</h2>
<p class="muted">Simulation-only jobs are excluded. These figures are not confirmed payouts or take-home earnings.</p>
<div class="grid" style="margin-bottom:18px;">
  <div class="stat"><b class="money">₱<?= number_format($earn['total'], 2) ?></b>All-time paid booking value</div>
  <div class="stat"><b class="money">₱<?= number_format($mEarn['total'], 2) ?></b>This month</div>
  <div class="stat"><b class="money">₱<?= number_format($wEarn['total'], 2) ?></b>Last 7 days</div>
  <div class="stat"><b class="money">₱<?= number_format($dEarn['total'], 2) ?></b>Today</div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;align-items:start;margin-bottom:18px;">
  <div class="card">
    <h2>Recorded paid booking value by day</h2>
    <?php $rows = []; $maxDay = 1; while ($r = $byDay->fetch_assoc()) { $rows[] = $r; $maxDay = max($maxDay, (float)$r['total']); } ?>
    <?php if (!$rows): ?>
      <div class="empty-state"><span class="big">💸</span>No completed jobs yet.<br><span class="muted">Completed booking totals will appear here.</span></div>
    <?php endif; ?>
    <?php foreach ($rows as $r): $pct = $maxDay > 0 ? (int)round($r['total'] / $maxDay * 100) : 0; ?>
      <div class="bar-row"><span><?= date('D, M j', strtotime($r['d'])) ?> <span class="muted">(<?= (int)$r['jobs'] ?>)</span></span><b class="money">₱<?= number_format($r['total'], 2) ?></b></div>
      <div class="bar-track"><div class="bar-fill" style="width:<?= max($pct, 3) ?>%;"></div></div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>🏅 Skill badges</h2>
    <?php if (!$badges->num_rows): ?><p class="muted">No badges yet. Administrators manage the badges displayed on worker profiles.</p><?php endif; ?>
    <?php while ($bd = $badges->fetch_assoc()): ?>
      <div class="kv"><span><b><?= e($bd['name']) ?></b><br><span class="muted" style="font-weight:400;"><?= e($bd['description']) ?></span></span>
        <span class="muted" style="white-space:nowrap;font-weight:400;"><?= date('M j, Y', strtotime($bd['earned_at'])) ?></span></div>
    <?php endwhile; ?>
    <p style="margin-top:12px;"><a class="btn btn-sm btn-sec" href="reviews.php">📖 See my reviews</a></p>
  </div>
</div>
<div class="card">
  <h2>Profile details</h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Full name</label><input name="name" maxlength="100" value="<?= e($u['name']) ?>" required></div>
      <div><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
      <div><label>Phone</label><input type="tel" name="phone" maxlength="30" value="<?= e($u['phone'] ?? '') ?>" placeholder="09XX XXX XXXX" pattern="[0-9+\-\s()]{7,30}" required></div>
      <div><label>Address / service area</label><input name="address" maxlength="255" value="<?= e($u['address'] ?? '') ?>" placeholder="Street, barangay"></div>
      <div><label>City / Municipality you serve</label><input name="city" maxlength="100" value="<?= e($u['city'] ?? '') ?>" placeholder="e.g. Cebu City"></div>
    </div>
    <label>Bio <span class="muted" style="font-weight:400;">(max 500 characters — shown to clients)</span></label>
    <textarea name="bio" rows="4" maxlength="500" placeholder="Tell clients about your experience and the services you offer…"><?= e($u['bio'] ?? '') ?></textarea>
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

<div class="card" id="portfolio" style="margin-top:18px;">
  <h2>🖼️ My portfolio</h2>
  <p class="muted" style="margin-bottom:14px;">Show off your best work. These photos appear on your public cleaner profile.</p>
  <form method="post" enctype="multipart/form-data" style="margin-bottom:18px;padding:14px;background:#f5f8ff;border:1px solid #e3e9f2;border-radius:10px;">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Photo <span class="muted" style="font-weight:400;">(JPG/PNG/WebP · max 3 MB)</span></label><input type="file" name="portfolio_image" accept="image/jpeg,image/png,image/webp" required></div>
      <div><label>Caption <span class="muted" style="font-weight:400;">(optional)</span></label><input name="caption" maxlength="255" placeholder="e.g. Deep clean of a 2-bedroom condo"></div>
    </div>
    <button name="add_portfolio" value="1" class="btn-sec" style="margin-top:8px;">＋ Add to portfolio</button>
  </form>

  <?php if (!$portfolio->num_rows): ?>
    <p class="muted">No portfolio photos yet. Add your first one above to impress potential clients.</p>
  <?php else: ?>
    <div class="portfolio-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
      <?php while ($p = $portfolio->fetch_assoc()): ?>
        <figure class="portfolio-item" style="margin:0;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff;">
          <img src="<?= e(media_url($p['image'], ch_base())) ?>" alt="<?= e($p['caption'] ?: 'Portfolio photo') ?>" style="width:100%;height:160px;object-fit:cover;display:block;">
          <?php if ($p['caption']): ?><figcaption style="padding:8px 10px;font-size:.82rem;"><?= e($p['caption']) ?></figcaption><?php endif; ?>
          <form method="post" style="padding:0 10px 10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
            <button name="delete_portfolio" value="1" class="btn btn-sm" style="background:#fdecea;color:#c0392b;border-color:#f5c6c0;font-size:.78rem;" data-confirm="Delete this portfolio photo?" data-confirm-title="Delete photo" data-confirm-yes="Yes, delete">Delete</button>
          </form>
        </figure>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:18px;border:1px solid #f1b7b7;">
  <h2 style="color:#b42318;">Delete my account</h2>
  <p class="muted">No administrator approval is required. To preserve booking, payment, and audit records, your personal profile details will be anonymized and you will be signed out. This action cannot be undone.</p>
  <form method="post" data-confirm="Delete your account and anonymize your profile? You will be signed out and cannot log in again." data-confirm-title="Delete my account" data-confirm-yes="Delete account">
    <?= csrf_field() ?>
    <label for="delete_password">Confirm your current password</label>
    <input id="delete_password" type="password" name="delete_password" required autocomplete="current-password">
    <button class="btn" style="background:#b42318;border-color:#b42318;margin-top:10px;" name="delete_my_account" value="1">Delete my account</button>
  </form>
</div>

<?php require '../footer.php'; ?>
