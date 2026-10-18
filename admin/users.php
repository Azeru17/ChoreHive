<?php
require '../config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve_worker']) || isset($_POST['revoke_worker']))) {
    verify_csrf();
    $field = isset($_POST['approve_worker']) ? 'approve_worker' : 'revoke_worker';
    $wid = v_int($field, 1, PHP_INT_MAX, 'Worker');
    $approved = $field === 'approve_worker' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET worker_approved=? WHERE id=? AND role='worker'");
    $stmt->bind_param('ii', $approved, $wid);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) fail('Worker profile not found or its marketplace status is already up to date.');
    $nameStmt = $conn->prepare("SELECT name FROM users WHERE id=? AND role='worker'");
    $nameStmt->bind_param('i', $wid);
    $nameStmt->execute();
    $workerName = $nameStmt->get_result()->fetch_assoc()['name'] ?? 'Worker';
    $verb = $approved ? 'approved for marketplace listing' : 'removed from the marketplace';
    notify_and_email($wid, "Your ChoreHive profile was $verb by an administrator.", 'Worker marketplace status updated');
    audit_log($approved ? 'worker_marketplace_approved' : 'worker_marketplace_unapproved', "Admin {$u['name']} $verb: worker #$wid ($workerName)");
    flash($approved ? 'Worker profile approved for marketplace listing.' : 'Worker profile removed from marketplace listings.');
    header('Location: users.php'); exit;
}

/* ---------- CREATE ---------- */
// Admin creation disabled - users register themselves
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    verify_csrf();
    flash('User creation is disabled. Users can register through the registration page.', 'error');
    header('Location: users.php');
    exit;
}

/* ---------- UPDATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    verify_csrf();
    $uid = v_int('update', 1, PHP_INT_MAX, 'User');
    $name  = v_str('name', 100, true, 'Name');
    $email = v_email();
    $role  = in_array($_POST['role'] ?? '', ['client','worker','admin'], true) ? $_POST['role'] : fail('Invalid role.');
    if ($uid === $u['id'] && $role !== 'admin') fail('You cannot change your own role.');
    $current = $conn->prepare("SELECT role FROM users WHERE id=?");
    $current->bind_param('i', $uid);
    $current->execute();
    $currentRole = $current->get_result()->fetch_assoc()['role'] ?? null;
    if ($currentRole === null) fail('User not found.');
    $dup = $conn->query("SELECT id FROM users WHERE email='" . $conn->real_escape_string($email) . "' AND id<>$uid")->fetch_assoc();
    if ($dup) fail('Another account already uses that email.');
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < 8) fail('Password must be at least 8 characters.');
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
        $stmt->bind_param('ssssi', $name, $email, $role, $pass, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $email, $role, $uid);
    }
    $stmt->execute();
    if ($currentRole !== $role) {
        $revokeApproval = $conn->prepare("UPDATE users SET worker_approved=0 WHERE id=?");
        $revokeApproval->bind_param('i', $uid);
        $revokeApproval->execute();
    }
    audit_log('user_updated', "Admin {$u['name']} updated user #$uid ($name, role $role)");
    flash('User updated.');
    header('Location: users.php'); exit;
}

/* ---------- DELETE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    $uid = v_int('delete', 1, PHP_INT_MAX, 'User');
    if ($uid === $u['id']) fail('You cannot delete your own account.');
    $target = $conn->query("SELECT name, role FROM users WHERE id=$uid")->fetch_assoc();
    if (!$target) fail('User not found.');
    
    // Handle foreign key constraints by nullifying/reassigning related records
    // For bookings where worker is deleted, unassign them (set worker_id to NULL) if pending/assigned
    $conn->query("UPDATE bookings SET worker_id=NULL WHERE worker_id=" . (int)$uid);
    // For bookings where client is deleted, we need to be careful - but admin deleting is destructive
    // Check if user has client bookings
    $clientBookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE client_id=" . (int)$uid)->fetch_assoc()['c'];
    if ($clientBookings > 0) {
        // Delete bookings with photos/checklist/feedback (cascades) and notify? But easier: delete_booking_record for each
        $res = $conn->query("SELECT id FROM bookings WHERE client_id=" . (int)$uid);
        while ($b = $res->fetch_assoc()) {
            delete_booking_record($b['id']);
        }
    }
    
    // Clean up other related data
    $conn->query("DELETE FROM chat_threads WHERE client_id=" . (int)$uid . " OR worker_id=" . (int)$uid);
    $conn->query("DELETE FROM saved_addresses WHERE client_id=" . (int)$uid);
    $conn->query("DELETE FROM worker_portfolio WHERE worker_id=" . (int)$uid);
    $conn->query("DELETE FROM badges WHERE worker_id=" . (int)$uid);
    $conn->query("DELETE FROM notifications WHERE user_id=" . (int)$uid);
    $conn->query("DELETE FROM worker_presence WHERE worker_id=" . (int)$uid);
    $conn->query("DELETE FROM password_resets WHERE user_id=" . (int)$uid);
    // Skip favorites if table doesn't exist
    if ($conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='favorites'")->num_rows > 0) {
        $conn->query("DELETE FROM favorites WHERE client_id=" . (int)$uid . " OR worker_id=" . (int)$uid);
    }
    
    // Return ID to freed pool for reuse
    $role = $target['role'] ?? 'client';
    $conn->query("INSERT IGNORE INTO role_id_freed (role, freed_id) VALUES ('" . $conn->real_escape_string($role) . "', " . (int)$uid . ")");
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    audit_log('user_deleted', "deleted user #$uid ({$target['name']})");
    flash('User deleted successfully.');
    header('Location: users.php'); exit;
}

/* ---------- Badges (award / revoke) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award'])) {
    verify_csrf();
    $wid = v_int('worker_id', 1, PHP_INT_MAX, 'Worker');
    $name = v_str('badge_name', 80, true, 'Badge name');
    $chk = $conn->query("SELECT id FROM users WHERE id=$wid AND role='worker'")->fetch_assoc();
    if ($chk) {
        $stmt = $conn->prepare("INSERT INTO badges (worker_id,name,description) VALUES (?,?,'Awarded by admin.')");
        $stmt->bind_param('is', $wid, $name);
        $stmt->execute();
        notify_and_email($wid, "You earned a new badge: '$name'. Keep up the great work!", 'Badge earned');
        audit_log('badge_awarded', "Admin {$u['name']} awarded '$name' to worker #$wid");
        flash('Badge awarded!');
    } else {
        fail('Selected worker does not exist.');
    }
    header('Location: users.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke'])) {
    verify_csrf();
    $bid = v_int('revoke', 1, PHP_INT_MAX, 'Badge');
    $stmt = $conn->prepare("DELETE FROM badges WHERE id=?");
    $stmt->bind_param('i', $bid);
    $stmt->execute();
    flash('Badge revoked.', 'error');
    header('Location: users.php'); exit;
}

$title = 'Manage Users';
require '../layout.php';

$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM users WHERE id=$eid")->fetch_assoc();
}
$userSearch = trim((string)($_GET['q'] ?? ''));
$userRole = trim((string)($_GET['role'] ?? ''));
$userWhere = [];
if ($userSearch !== '') { $safeUserSearch=$conn->real_escape_string($userSearch); $userWhere[]="(u.name LIKE '%$safeUserSearch%' OR u.email LIKE '%$safeUserSearch%' OR CAST(u.id AS CHAR) LIKE '%$safeUserSearch%')"; }
if (in_array($userRole,['client','worker','admin'],true)) $userWhere[]="u.role='".$conn->real_escape_string($userRole)."'";
$userWhereSql = $userWhere ? ' WHERE '.implode(' AND ',$userWhere) : '';
$res = $conn->query("SELECT u.*,
  CASE WHEN u.role='worker' THEN (SELECT COUNT(*) FROM bookings b WHERE b.worker_id=u.id)
       ELSE (SELECT COUNT(*) FROM bookings b WHERE b.client_id=u.id) END jobs,
  (SELECT COUNT(*) FROM bookings ba WHERE ba.worker_id=u.id AND ba.status IN ('assigned','in_progress','awaiting_verification')) active_worker_jobs,
  (SELECT last_seen FROM worker_presence wp WHERE wp.worker_id=u.id) worker_last_seen
  FROM users u $userWhereSql ORDER BY u.role, u.name");
$workers = $conn->query("SELECT id,name FROM users WHERE role='worker'")->fetch_all(MYSQLI_ASSOC);
$badges = $conn->query("SELECT bg.*, u.name worker FROM badges bg JOIN users u ON u.id=bg.worker_id ORDER BY bg.worker_id");
?>
<h1>Manage Users</h1>

<div class="card" style="display:none;">
  <h2><?= $edit ? '✏️ Edit user #' . $edit['id'] : '➕ Create user' ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0 14px;">
    <?= csrf_field() ?>
    <?php if ($edit): ?><input type="hidden" name="update" value="<?= $edit['id'] ?>"><?php endif; ?>
    <div><label>Full name</label><input name="name" maxlength="100" value="<?= e($edit['name'] ?? '') ?>" required></div>
    <div><label>Email</label><input type="email" name="email" maxlength="120" value="<?= e($edit['email'] ?? '') ?>" required></div>
    <div><label>Role</label>
      <select name="role">
        <?php foreach (['client'=>'Client','worker'=>'Worker','admin'=>'Admin'] as $rv => $rl): ?>
          <option value="<?= $rv ?>" <?= ($edit['role'] ?? '') === $rv ? 'selected' : '' ?>><?= $rl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label><?= $edit ? 'New password (leave blank to keep)' : 'Password (min 8)' ?></label>
      <input type="password" name="password" minlength="8" <?= $edit ? '' : 'required' ?>></div>
    <div style="align-self:end;">
      <button name="<?= $edit ? 'update' : 'create' ?>" value="<?= $edit ? $edit['id'] : 1 ?>"><?= $edit ? '💾 Save changes' : '➕ Create user' ?></button>
      <?php if ($edit): ?> <a class="btn btn-sec" href="users.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2>All Users <span class="muted" style="font-weight:400;">(<?= (int)$res->num_rows ?> matching)</span></h2>
  <p class="muted">Users register themselves through the registration page. Admins can view and delete accounts.</p>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:14px;">
    <label style="flex:1;min-width:200px;">Search users<input name="q" value="<?= e($userSearch) ?>" placeholder="Name, email or ID"></label>
    <label>Role<select name="role"><option value="">All users</option><?php foreach(['client'=>'Clients','worker'=>'Workers','admin'=>'Admins'] as $rv=>$rl):?><option value="<?=$rv?>" <?=$userRole===$rv?'selected':''?>><?=$rl?></option><?php endforeach;?></select></label>
    <button class="btn btn-sm">Filter</button><a class="btn btn-sm btn-sec" href="users.php">Reset</a>
  </form>
  <div class="table-wrap">
  <table class="users-table">
    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Jobs</th><th>Marketplace status</th><th>Listing review</th><th>Actions</th></tr>
    <?php while ($usr = $res->fetch_assoc()): ?>
      <tr>
        <td class="user-id">#<?= (int)$usr['id'] ?></td>
        <td><b><?= e($usr['name']) ?></b><?= $usr['id'] == $u['id'] ? ' <span class="muted">(you)</span>' : '' ?></td>
        <td class="user-email"><?= e($usr['email']) ?></td>
        <td><span class="badge <?= $usr['role']==='admin' ? 'err' : ($usr['role']==='worker' ? 'info' : 'ok') ?>"><?= e($usr['role']) ?></span></td>
        <td><?= (int)$usr['jobs'] ?></td>
        <td><?php if ($usr['role'] === 'worker'): $isOnline = !empty($usr['worker_last_seen']) && strtotime($usr['worker_last_seen']) >= time()-300; $isBusy = (int)$usr['active_worker_jobs'] > 0; ?><span class="availability-pill <?= $isBusy ? 'busy' : 'available' ?>"><i></i><?= $isBusy ? 'Busy' : 'Available' ?></span><small class="admin-worker-presence <?= $isOnline ? 'is-online' : '' ?>"><?= $isOnline ? 'Recently active' : 'Offline' ?></small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><?php if ($usr['role'] === 'worker'): ?>
          <span class="badge <?= (int)$usr['worker_approved'] ? 'ok' : 'warn' ?>"><?= (int)$usr['worker_approved'] ? 'Listed' : 'Pending review' ?></span>
          <form method="post" style="margin-top:6px;" data-confirm="<?= (int)$usr['worker_approved'] ? 'Remove this profile from public worker listings?' : 'Approve this profile for marketplace listing?' ?>" data-confirm-title="Marketplace listing">
            <?= csrf_field() ?>
            <?php if ((int)$usr['worker_approved']): ?>
              <button name="revoke_worker" value="<?= (int)$usr['id'] ?>" class="btn btn-sm btn-outline">Revoke listing</button>
            <?php else: ?>
              <button name="approve_worker" value="<?= (int)$usr['id'] ?>" class="btn btn-sm">Approve listing</button>
            <?php endif; ?>
          </form>
        <?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td>
          <div class="actions">
            <a class="btn btn-sm btn-sec" href="user_view.php?id=<?= $usr['id'] ?>">View</a>
            <?php if ($usr['id'] != $u['id']): ?>
            <form method="post" data-confirm="Delete <?= e($usr['name']) ?>? This also removes their bookings, photos and feedback." data-confirm-title="Delete user" data-confirm-yes="Yes, delete">
              <?= csrf_field() ?><button name="delete" value="<?= $usr['id'] ?>" class="btn btn-sm btn-danger">Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
  </div>
  <p class="table-scroll-hint">Scroll the table sideways to reach the listing review and account actions.</p>
  <p class="muted" style="margin-top:10px;">Marketplace approval is an internal profile/listing review only. It does not mean identity or background checks have been completed.</p>
</div>

<div class="card">
  <h2>Award a Skill Badge</h2>
  <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div><label>Worker</label>
      <select name="worker_id"><?php foreach ($workers as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div style="flex:1;"><label>Badge name</label><input name="badge_name" maxlength="80" placeholder="e.g. Deep Cleaning Expert" required></div>
    <button name="award" value="1">🏅 Award</button>
  </form>
</div>

<div class="card">
  <h2>Workers &amp; Badges</h2>
  <div class="table-wrap">
  <table>
    <?php while ($b = $badges->fetch_assoc()): ?>
      <tr><td>🏅 <b><?= e($b['name']) ?></b></td><td><?= e($b['worker']) ?></td><td><?= e($b['description']) ?></td>
      <td><form method="post"><?= csrf_field() ?><button name="revoke" value="<?= $b['id'] ?>" class="btn btn-sm btn-sec">Revoke</button></form></td></tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
<?php require '../footer.php'; ?>
