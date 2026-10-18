<?php
require '../config.php';
$u = require_role('admin');
$id = (int)($_GET['id'] ?? 0);
$usr = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
if (!$usr) {
    flash('User not found.', 'error');
    header('Location: users.php');
    exit;
}
$title = 'User Details - ' . $usr['name'];
$usrAvatar = avatar_url($usr, ch_base());
require '../layout.php';
?>
<h1>User Details</h1>
<div class="card">
  <h2><?= e($usr['name']) ?></h2>
  <p><b>ID:</b> #<?= $usr['id'] ?></p>
  <p><b>Email:</b> <?= e($usr['email']) ?></p>
  <p><b>Role:</b> <?= e($usr['role']) ?></p>
  <p><b>Phone:</b> <?= e($usr['phone'] ?? 'N/A') ?></p>
  <p><b>Address:</b> <?= e($usr['address'] ?? 'N/A') ?></p>
  <p><b>City / Municipality:</b> <?= e($usr['city'] ?? 'N/A') ?></p>
  <p><b>Bio:</b> <?= e($usr['bio'] ?? 'N/A') ?></p>
  <p><b>Worker Approved:</b> <?= $usr['role'] === 'worker' ? ($usr['worker_approved'] ? 'Yes' : 'No') : 'N/A' ?></p>
  <p><b>Member Since:</b> <?= date('M j, Y g:i A', strtotime($usr['created_at'])) ?></p>
  <?php if ($usrAvatar): ?>
    <p><b>Profile Picture:</b><br><img src="<?= e($usrAvatar) ?>" alt="<?= e($usr['name']) ?> profile picture" style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:3px solid var(--line);"></p>
  <?php else: ?><p class="muted">No profile picture uploaded.</p><?php endif; ?>
</div>
<p class="actions"><a class="btn btn-sec" href="users.php">← Back to Users</a></p>
<?php require '../footer.php'; ?>