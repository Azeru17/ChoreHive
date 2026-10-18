<?php
require '../config.php';
$u = require_role('worker');
$id = (int)($_GET['id'] ?? 0);
$b = $conn->query("SELECT b.*, 
  s.name AS service, s.description AS service_description, s.base_price AS service_price,
  u2.id AS client_id, u2.name AS client, u2.email AS client_email, u2.phone AS client_phone, u2.avatar AS client_avatar
  FROM bookings b
  JOIN services s ON s.id=b.service_id 
  JOIN users u2 ON u2.id=b.client_id
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
$client_avatar_url = avatar_url(['avatar' => $b['client_avatar']], ch_base());
?>
<h1>Job #<?= $id ?> — <?= e($b['service']) ?></h1>

<div class="card">
  <h2 style="margin-top: 0;">Customer information</h2>
  <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
  <div style="flex: 0 0 auto;">
    <?php if ($client_avatar_url): ?>
      <img src="<?= e($client_avatar_url) ?>" alt="<?= e($b['client']) ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--line, #e2e8f0);">
    <?php else: ?>
      <div style="width: 80px; height: 80px; border-radius: 50%; background: #17427a; color: #fff; display: grid; place-items: center; font-size: 1.8rem; font-weight: bold;">
        <?= e(initials($b['client'])) ?>
      </div>
    <?php endif; ?>
  </div>
  <div style="flex: 1 1 300px;">
    <h2 style="margin: 0 0 8px 0;"><?= e($b['client']) ?></h2>
    <p style="margin: 4px 0;"><b>Phone number:</b> <?= !empty($b['client_phone']) ? '<a href="tel:' . e($b['client_phone']) . '">' . e($b['client_phone']) . '</a>' : '<span class="muted">Not provided</span>' ?></p>
    <p style="margin: 4px 0;"><b>Email:</b> <?= e($b['client_email']) ?></p>
    <p style="margin: 8px 0 0 0;"><a class="btn btn-sm btn-sec" href="../chat.php?user_id=<?= (int)$b['client_id'] ?>">💬 Chat with client</a></p>
  </div>
  </div>
</div>

<div class="card">
  <h2>Service &amp; Schedule Details</h2>
  <p><b>Service requested:</b> <?= e($b['service']) ?> (₱<?= number_format((float)$b['total_price'], 2) ?>)</p>
  <?php if (!empty($b['service_description'])): ?>
    <p class="muted" style="margin-top: -4px; margin-bottom: 12px;"><?= e($b['service_description']) ?></p>
  <?php endif; ?>
  <p><b>Date &amp; Schedule Time:</b> <?= date('F j, Y', strtotime($b['schedule'])) ?> at <b><?= date('g:i A', strtotime($b['schedule'])) ?></b></p>
  <p><b>Current Status:</b> <span class="badge <?= status_class($b['status']) ?>"><?= str_replace('_',' ',$b['status']) ?></span></p>
  <?php if (!empty($b['notes'])): ?>
    <div style="background: var(--bg-subtle, #f8fafc); padding: 10px 14px; border-left: 4px solid #17427a; border-radius: 4px; margin-top: 10px;">
      <b>Special instructions / Client notes:</b>
      <p style="margin: 4px 0 0 0; white-space: pre-line;"><?= e($b['notes']) ?></p>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Service Location &amp; Map Pin</h2>
  <p><b>Complete Address:</b> <?= e($b['address']) ?></p>
  <?php if ($b['address_lat'] !== null && $b['address_lng'] !== null): ?>
    <p class="job-map-actions" style="margin-top: 10px;">
      <b>Customer's Map Pin:</b> 
      <a class="btn btn-sm" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?= e((string)$b['address_lat']) ?>&amp;mlon=<?= e((string)$b['address_lng']) ?>#map=17/<?= e((string)$b['address_lat']) ?>/<?= e((string)$b['address_lng']) ?>">🗺 Open pinned map</a>
      <a class="btn btn-sm btn-sec" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= rawurlencode((string)$b['address_lat'] . ',' . (string)$b['address_lng']) ?>">🧭 Get directions</a>
    </p>
    <p class="muted" style="font-size: 0.85rem; margin-top: 4px;">Coordinates: <?= e((string)$b['address_lat']) ?>, <?= e((string)$b['address_lng']) ?></p>
  <?php else: ?>
    <p class="muted">No map pin was set by the client. Please follow the written address above.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Payment &amp; Online Transaction Receipt</h2>
  <p><b>Payment Method:</b> <?= e(ucfirst($b['payment_method'])) ?></p>
  <p><b>Payment Status:</b> <span class="badge <?= $b['payment_status'] === 'paid' ? 'status-completed' : ($b['payment_status'] === 'demo_paid' ? 'status-in_progress' : 'status-pending') ?>"><?= e($b['payment_status'] === 'demo_paid' ? 'Demo Paid (Simulation)' : ucfirst($b['payment_status'])) ?></span></p>
  <p><b>Total Amount:</b> ₱<?= number_format((float)$b['total_price'], 2) ?></p>

  <?php if (in_array($b['payment_status'], ['paid', 'demo_paid'], true) || !empty($b['payment_reference'])): ?>
    <div style="background: #f0f7ff; border: 1px solid #cce3ff; padding: 14px 18px; border-radius: 8px; margin-top: 14px;">
      <h3 style="margin: 0 0 8px 0; font-size: 1.05rem; color: #17427a;">🧾 Online Payment Receipt Information</h3>
      <p style="margin: 4px 0;"><b>Payment Reference Code:</b> <code style="background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #d0d7de; font-weight: bold;"><?= e($b['payment_reference'] ?: 'N/A') ?></code></p>
      <p style="margin: 4px 0;"><b>Date Paid:</b> <?= $b['paid_at'] ? date('M j, Y g:i A', strtotime($b['paid_at'])) : 'Recorded upon booking/completion' ?></p>
      <?php if ($b['payment_status'] === 'demo_paid'): ?>
        <p class="muted" style="margin: 6px 0 0 0; font-size: 0.85rem;">Note: This was a simulated payment transaction for test/demo purposes.</p>
      <?php else: ?>
        <p class="muted" style="margin: 6px 0 0 0; font-size: 0.85rem;">This online payment was successfully processed via PayMongo / Online Transaction.</p>
      <?php endif; ?>
    </div>
  <?php elseif ($b['payment_method'] === 'cash'): ?>
    <p class="muted" style="margin-top: 8px;">Cash payment to be collected from the client upon job completion.</p>
  <?php endif; ?>
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
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$p['id'] ?>" alt="Before photo" loading="lazy" tabindex="0" role="button" aria-label="Enlarge before photo"><figcaption>before</figcaption></figure>
    <?php endwhile; ?>
  </div>
  <form method="post" enctype="multipart/form-data" class="worker-photo-upload">
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="before">
    <div class="worker-photo-file"><label>Upload before photo (JPG/PNG/WebP, max 3&nbsp;MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <button class="btn btn-sm">Upload</button>
  </form>
  <p style="margin-top:12px;"><b>After:</b></p>
  <div class="photo-grid">
    <?php while ($p = $after->fetch_assoc()): ?>
      <figure><img class="lightbox-img" src="../photo.php?id=<?= (int)$p['id'] ?>" alt="After photo" loading="lazy" tabindex="0" role="button" aria-label="Enlarge after photo"><figcaption>after</figcaption></figure>
    <?php endwhile; ?>
  </div>
  <form method="post" enctype="multipart/form-data" class="worker-photo-upload">
    <?= csrf_field() ?>
    <input type="hidden" name="stage" value="after">
    <div class="worker-photo-file"><label>Upload after photo (JPG/PNG/WebP, max 3&nbsp;MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <button class="btn btn-sm">Upload</button>
  </form>
</div>
<p class="actions"><a class="btn btn-sec" href="jobs.php">← Back to jobs</a></p>
<?php if (booking_is_finished($b['status'])): ?>
<div class="card danger-zone">
  <h2>Clean up</h2>
  <p class="muted" style="margin-bottom:10px;">This job is finished. Delete it to clear it from your work list and history.</p>
  <form method="post" data-confirm="Delete job #<?= $id ?> permanently?\n\nThe checklist, photos and the client’s feedback will be removed too." data-confirm-title="Delete job" data-confirm-yes="Yes, delete">
    <?= csrf_field() ?>
    <button name="delete_job" value="1" class="btn-danger">🗑 Delete this job</button>
  </form>
</div>
<?php endif; ?>
<div id="lightbox" role="dialog" aria-modal="true" aria-label="Enlarged booking photo" aria-hidden="true"><img id="lightbox-img" src="" alt="Enlarged photo" tabindex="-1"></div>
<?php require '../footer.php'; ?>
