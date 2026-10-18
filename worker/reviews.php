<?php
require '../config.php';
$u = require_role('worker');

/* ---------- Actions (before output so redirects work) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    verify_csrf();
    $fid = v_int('delete_review', 1, PHP_INT_MAX, 'Review');
    $stmt = $conn->prepare("SELECT f.id FROM feedback f JOIN bookings b ON b.id=f.booking_id
                            WHERE f.id=? AND b.worker_id=?");
    $stmt->bind_param('ii', $fid, $u['id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) fail('Review not found.');
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id=?");
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    audit_log('review_deleted', "Worker {$u['name']} deleted a client review (#$fid)");
    flash('Review deleted.');
    header('Location: reviews.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_reviews'])) {
    verify_csrf();
    $ids = [];
    $r = $conn->query("SELECT f.id FROM feedback f JOIN bookings b ON b.id=f.booking_id
                       WHERE b.worker_id=" . (int)$u['id']);
    while ($x = $r->fetch_assoc()) $ids[] = (int)$x['id'];
    $n = 0;
    foreach ($ids as $id) {
        $s = $conn->prepare("DELETE FROM feedback WHERE id=?");
        $s->bind_param('i', $id);
        $s->execute();
        $n += $s->affected_rows;
    }
    audit_log('reviews_cleared', "Worker {$u['name']} cleared $n review(s)");
    flash($n ? "Deleted $n review(s)." : 'No reviews to delete yet.', $n ? 'success' : 'error');
    header('Location: reviews.php'); exit;
}

$uid = (int)$u['id'];
$summary = $conn->query("SELECT AVG(f.rating) avg_r, COUNT(f.id) cnt
                         FROM feedback f JOIN bookings b ON b.id=f.booking_id WHERE b.worker_id=$uid")->fetch_assoc();
$dist = [];
for ($i = 5; $i >= 1; $i--) $dist[$i] = 0;
$dr = $conn->query("SELECT f.rating r, COUNT(*) c FROM feedback f JOIN bookings b ON b.id=f.booking_id
                    WHERE b.worker_id=$uid GROUP BY f.rating");
while ($x = $dr->fetch_assoc()) $dist[(int)$x['r']] = (int)$x['c'];
$reviews = $conn->query("SELECT f.*, s.name service, u.name client, b.schedule
                         FROM feedback f JOIN bookings b ON b.id=f.booking_id
                         JOIN services s ON s.id=b.service_id JOIN users u ON u.id=f.client_id
                         WHERE b.worker_id=$uid ORDER BY f.created_at DESC");

$title = 'My Reviews';
require '../layout.php';
?>
<h1>📖 My Reviews</h1>
<p class="muted" style="margin-bottom:18px;">Every client review from your completed jobs. You can remove a review if you want it gone.</p>

<div class="grid" style="margin-bottom:18px;">
  <div class="stat"><b><?= (int)$summary['cnt'] ?></b>Total reviews</div>
  <div class="stat"><b><?= (int)$summary['cnt'] ? number_format($summary['avg_r'], 1) . ' ⭐' : '—' ?></b>Average rating</div>
  <?php foreach ($dist as $star => $c): if ($c > 0): ?>
    <div class="stat"><b><?= $c ?></b><?= str_repeat('⭐', $star) ?></div>
  <?php endif; endforeach; ?>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
    <h2 style="margin-bottom:0;">All reviews</h2>
    <?php if ((int)$summary['cnt'] > 0): ?>
      <form method="post" data-confirm="Delete all <?= (int)$summary['cnt'] ?> review(s)?\n\nThis cannot be undone." data-confirm-title="Delete reviews" data-confirm-yes="Yes, delete all">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline" name="clear_reviews" value="1">🧹 Delete all reviews</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$reviews->num_rows): ?>
    <div class="empty-state"><span class="big">⭐</span>No reviews yet.<br><span class="muted">Clients can rate you once they verify a completed job.</span></div>
  <?php else: ?>
    <div class="table-wrap">
    <table>
      <tr><th>Client</th><th>Service</th><th>Job date</th><th>Rating</th><th>Comment</th><th></th></tr>
      <?php while ($r = $reviews->fetch_assoc()): ?>
      <tr>
        <td><b><?= e($r['client']) ?></b></td>
        <td><?= e($r['service']) ?></td>
        <td class="muted" style="white-space:nowrap;"><?= date('M j, Y', strtotime($r['schedule'])) ?></td>
        <td><span class="star-row"><?= str_repeat('⭐', (int)$r['rating']) ?></span> <span class="muted">(<?= (int)$r['rating'] ?>/5)</span></td>
        <td><?= $r['comment'] ? e($r['comment']) : '<span class="muted">No comment</span>' ?></td>
        <td>
          <form method="post" data-confirm="Delete this review from <?= e($r['client']) ?>?\n\nThis cannot be undone." data-confirm-title="Delete review" data-confirm-yes="Yes, delete">
            <?= csrf_field() ?>
            <button class="icon-btn danger" name="delete_review" value="<?= (int)$r['id'] ?>" title="Delete review" aria-label="Delete review">🗑</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require '../footer.php'; ?>