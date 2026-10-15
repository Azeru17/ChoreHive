<?php
require '../config.php';
$u = require_role('worker');
$id = (int)($_GET['id'] ?? 0);
$b = $conn->query("SELECT b.*, s.name service, u2.name client FROM bookings b
  JOIN services s ON s.id=b.service_id JOIN users u2 ON u2.id=b.client_id
  WHERE b.id=$id AND b.worker_id={$u['id']}")->fetch_assoc();
if (!$b) { die('Job not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['toggle'])) {
        $cid = v_int('toggle', 1, PHP_INT_MAX, 'Checklist item');
        $stmt = $conn->prepare("UPDATE checklist_items SET done = 1 - done WHERE id=? AND booking_id=?");
        $stmt->bind_param('ii', $cid, $id);
        $stmt->execute();
        header('Location: job.php?id=' . $id); exit;
    }
    $stage = $_POST['stage'] ?? '';
    if (in_array($stage, ['before','after'], true)) {
        handle_photo_upload('photo', $id, $stage, $conn);
        notify_and_email($b['client_id'], "{$u['name']} uploaded new $stage photo(s) for your booking #$id ({$b['service']}). Tap to view.", 'New job photos', 'client/booking.php?id=' . $id);
        flash('Photo uploaded.');
        header('Location: job.php?id=' . $id); exit;
    }
    if (isset($_POST['delete_job'])) {
        if (!booking_is_finished($b['status'])) fail('Only completed or cancelled jobs can be deleted.');
        [$n, $files] = delete_booking_record($id);
        notify_and_email($b['client_id'], "Job #$id ({$b['service']}) was removed from your worker's history.", 'Job record deleted', 'client/bookings.php');
        audit_log('booking_deleted', "Worker {$u['name']} deleted job #$id ({$b['status']}); $files photo file(s) removed");
        flash('Job deleted from your history.');
        header('Location: jobs.php'); exit;
    }
    fail('Unknown action.');
}

$title = 'Job #' . $id;
require '../layout.php';
$items = $conn->query("SELECT * FROM checklist_items WHERE booking_id=$id");
$before = $conn->query("SELECT * FROM booking_photos WHERE booking_id=$id AND stage='before'");
$after  = $conn->query("SELECT * FROM booking_photos WHERE booking_id=$id AND stage='after'");
?>
<h1>Job #<?= $id ?> — <?= e($b['service']) ?></h1>
<div class="card">
  <p><b>Client:</b> <?= e($b['client']) ?> · <b>Schedule:</b> <?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></p>
  <p><b>Address:</b> <?= e($b['address']) ?></p>
  <?php if ($b['address_lat'] !== null && $b['address_lng'] !== null): ?>
    <p class="job-map-actions"><b>Customer's map pin:</b> <a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string)$b['address_lat']) ?>&amp;mlon=<?= e((string)$b['address_lng']) ?>#map=17/<?= e((string)$b['address_lat']) ?>/<?= e((string)$b['address_lng']) ?>">Open pinned location</a> · <a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= rawurlencode((string)$b['address_lat'] . ',' . (string)$b['address_lng']) ?>">Get directions</a></p>
  <?php else: ?><p class="muted">No map pin was provided. Use the customer's manually entered address.</p><?php endif; ?>
  <p><b>Payment:</b> <?= e(ucfirst($b['payment_method'])) ?> · <?= e($b['payment_status'] === 'demo_paid' ? 'demo-paid · no money transferred' : $b['payment_status']) ?></p>
  <p><b>Status:</b> <span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span></p>
  <?php if ($b['notes']): ?><p class="muted">Notes: <?= e($b['notes']) ?></p><?php endif; ?>
</div>

<div class="card">
  <h2>Checklist — tick off as you go</h2>
  <?php while ($it = $items->fetch_assoc()): ?>
    <form method="post" style="margin:4px 0;">
      <?= csrf_field() ?>
      <button name="toggle" value="<?= $it['id'] ?>" class="btn btn-sm <?= $it['done'] ? '' : 'btn-sec' ?>">
        <?= $it['done'] ? '✅' : '⬜' ?> <?= e($it['task']) ?>
      </button>
    </form>
  <?php endwhile; ?>
</div>

<div class="card">
  <h2>Photos <span class="muted" style="font-weight:400">(click to enlarge)</span></h2>
  <p><b>Before:</b></p>
  <div class="photo-grid">
    <?php while ($p = $before->fetch_assoc()): ?>
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$p['id'] ?>" alt="Before photo" loading="lazy"><figcaption>before</figcaption></figure>
    <?php endwhile; ?>
  </div>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="before">
    <div style="flex:1"><label>Upload before photo (JPG/PNG/WebP, max 3&nbsp;MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <button class="btn-sm">Upload</button>
  </form>
  <p style="margin-top:12px;"><b>After:</b></p>
  <div class="photo-grid">
    <?php while ($p = $after->fetch_assoc()): ?>
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$p['id'] ?>" alt="After photo" loading="lazy"><figcaption>after</figcaption></figure>
    <?php endwhile; ?>
  </div>
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="after">
    <div style="flex:1"><label>Upload after photo (JPG/PNG/WebP, max 3&nbsp;MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <button class="btn-sm">Upload</button>
  </form>
</div>
<p class="actions"><a class="btn btn-sec" href="jobs.php">← Back to jobs</a></p>
<?php if (booking_is_finished($b['status'])): ?>
<div class="card danger-zone">
  <h2>Clean up</h2>
  <p class="muted" style="margin-bottom:10px;">This job is finished. Delete it to clear it from your work list and history.</p>
  <form method="post" onsubmit="return confirm('Delete job #<?= $id ?> permanently?\n\nThe checklist, photos and the client’s feedback will be removed too.');">
    <?= csrf_field() ?>
    <button name="delete_job" value="1" class="btn-danger">🗑 Delete this job</button>
  </form>
</div>
<?php endif; ?>
<div id="lightbox"><img id="lightbox-img" src="" alt="Enlarged photo"></div>
<?php require '../footer.php'; ?>
