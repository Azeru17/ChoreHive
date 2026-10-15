<?php
require '../config.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    verify_csrf();
    $n = (int)$conn->query("DELETE FROM audit_log")->affected_rows;
    audit_log('audit_cleared', "Admin {$u['name']} cleared the audit log ($n entries removed)");
    flash("Audit log cleared — $n entr" . ($n === 1 ? 'y' : 'ies') . ' removed.');
    header('Location: audit.php'); exit;
}

$title = 'Audit Log';
require '../layout.php';

$page = max(1, (int)($_GET['p'] ?? 1));
$per = 30;
$total = $conn->query("SELECT COUNT(*) c FROM audit_log")->fetch_assoc()['c'];
$pages = max(1, ceil($total / $per));
$off = ($page - 1) * $per;
$res = $conn->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT $per OFFSET $off");
?>
<h1>Audit Log</h1>
<p class="muted">Who did what, and when — every booking mutation, assignment, payment, and badge change.</p>
<?php if ((int)$total > 0): ?>
<div class="card danger-zone" style="padding:16px 20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
    <div>
      <b>Clear the audit log</b>
      <p class="muted" style="margin:3px 0 0;">Removes all <?= (int)$total ?> entries to keep the log tidy. The clearing action itself is recorded.</p>
    </div>
    <form method="post" onsubmit="return confirm('Clear the entire audit log?\n\nAll <?= (int)$total ?> entries will be permanently removed.');">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-danger" name="clear_log" value="1">🧹 Clear audit log</button>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="card">
  <div class="table-wrap">
  <table>
    <tr><th>When</th><th>Actor</th><th>Action</th><th>Details</th></tr>
    <?php while ($r = $res->fetch_assoc()): ?>
      <tr>
        <td style="white-space:nowrap;"><?= date('M j, Y g:i:s A', strtotime($r['created_at'])) ?></td>
        <td><b><?= e($r['actor_name']) ?></b><?= $r['user_id'] ? ' <span class="muted">#' . $r['user_id'] . '</span>' : '' ?></td>
        <td><span class="badge info"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
        <td><?= e($r['details']) ?></td>
      </tr>
    <?php endwhile; ?>
    <?php if (!$total): ?><tr><td colspan="4" class="muted">No audit entries yet.</td></tr><?php endif; ?>
  </table>
  </div>
  <?php if ($pages > 1): ?>
    <p class="muted" style="margin-top:10px;">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i == $page): ?><b><?= $i ?></b><?php else: ?><a href="?p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </p>
  <?php endif; ?>
</div>
<?php require '../footer.php'; ?>
