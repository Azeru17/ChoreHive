<?php
require '../config.php';
$u = require_role('admin');

/* ---------- Broadcast announcement ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast'])) {
    verify_csrf();
    $message = v_str('broadcast_message', 500, true, 'Announcement');
    $audience = $_POST['audience'] ?? 'all';
    if (!in_array($audience, ['all', 'client', 'worker'], true)) $audience = 'all';
    if ($audience === 'all') {
        $targets = $conn->query("SELECT id FROM users WHERE id <> {$u['id']}");
    } else {
        $st = $conn->prepare("SELECT id FROM users WHERE role = ? AND id <> ?");
        $st->bind_param('si', $audience, $u['id']);
        $st->execute();
        $targets = $st->get_result();
    }
    $ins = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)");
    $title = 'Announcement from ChoreHive';
    $sent = 0;
    while ($t = $targets->fetch_assoc()) {
        $ins->bind_param('iss', $t['id'], $title, $message);
        $ins->execute();
        $sent++;
    }
    audit_log('broadcast_sent', "Admin {$u['name']} sent an announcement to $audience ($sent recipients)");
    flash("Announcement sent to $sent recipient(s).");
    header('Location: dashboard.php');
    exit;
}

$s = $conn->query("SELECT
  COUNT(*) total,
  SUM(status='pending') pending,
  SUM(status IN ('assigned','in_progress','awaiting_verification')) active,
  SUM(status='completed') completed,
  SUM(status='completed' AND payment_status='paid') paid,
  COALESCE(SUM(CASE WHEN status='completed' AND payment_status='paid' THEN total_price ELSE 0 END),0) revenue
  FROM bookings")->fetch_assoc();
$users = $conn->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetch_all(MYSQLI_ASSOC);
$counts = ['client'=>0,'worker'=>0,'admin'=>0];
foreach ($users as $r) $counts[$r['role']] = (int)$r['c'];

$unreadNotifs = (int)$conn->query("SELECT COUNT(*) c FROM notifications WHERE is_read=0")->fetch_assoc()['c'];
$finishedTotal = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE status IN ('completed','cancelled')")->fetch_assoc()['c'];
$unassigned = (int)$conn->query("SELECT COUNT(*) c FROM bookings WHERE worker_id IS NULL AND status='pending'")->fetch_assoc()['c'];
$conversationCount = (int)$conn->query('SELECT COUNT(*) c FROM chat_threads')->fetch_assoc()['c'];
$messageCount = (int)$conn->query('SELECT COUNT(*) c FROM chat_messages')->fetch_assoc()['c'];
$onlineWorkers = (int)$conn->query("SELECT COUNT(*) c FROM worker_presence p JOIN users w ON w.id=p.worker_id WHERE w.role='worker' AND p.last_seen >= NOW() - INTERVAL 5 MINUTE")->fetch_assoc()['c'];
$workersWithActiveJobs = (int)$conn->query("SELECT COUNT(DISTINCT worker_id) c FROM bookings WHERE worker_id IS NOT NULL AND status IN ('assigned','in_progress','awaiting_verification')")->fetch_assoc()['c'];

$feeds = $conn->query("SELECT a.action, a.details, a.created_at, a.actor_name actor
  FROM audit_log a ORDER BY a.id DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

$perService = $conn->query("SELECT sv.name, COUNT(b.id) c FROM services sv
  LEFT JOIN bookings b ON b.service_id=sv.id GROUP BY sv.id ORDER BY c DESC")->fetch_all(MYSQLI_ASSOC);
$maxSvc = 1;
foreach ($perService as $r) $maxSvc = max($maxSvc, (int)$r['c']);

$feedIcons = [
  'booking_created'          => ['bi-calendar-plus', 'info'],
  'booking_rebooked'         => ['bi-arrow-repeat',          'info'],
  'booking_cancelled'        => ['bi-x-circle',  'err'],
  'booking_deleted'          => ['bi-trash',         'err'],
  'job_started'              => ['bi-play-circle',   'info'],
  'job_finished'             => ['bi-check-circle',  'ok'],
  'payment_completed'        => ['bi-cash-coin',         'ok'],
  'worker_assigned'          => ['bi-person-plus',     'info'],
  'admin_chat_viewed'        => ['bi-chat-dots',      'warn'],
  'user_registered'          => ['bi-person-plus',     'info'],
  'user_created'             => ['bi-person-plus',     'info'],
  'user_updated'             => ['bi-pencil',        'info'],
  'user_deleted'             => ['bi-person-x',    'err'],
  'badge_awarded'            => ['bi-trophy',        'ok'],
  'history_cleared'          => ['bi-stars',         'warn'],
  'notifications_cleared'    => ['bi-bell',        'warn'],
  'audit_cleared'            => ['bi-clock-history',       'warn'],
  'checklist_task_removed'   => ['bi-list-ol',       'warn'],
  'password_reset'           => ['bi-key',           'warn'],
  'password_reset_requested' => ['bi-key',           'warn'],
  'login'                    => ['bi-box-arrow-in-right',       'info'],
  'login_failed'             => ['bi-exclamation-triangle',       'err'],
];

$title = 'Admin Dashboard';
require '../layout.php';
?>
<h1><i class="bi bi-speedometer2"></i>&nbsp; Admin Dashboard</h1>

<!-- Stat cards -->
<div class="dash-stats">
  <div class="stat dark">
    <div class="top"><i class="bi bi-calendar3"></i><b><?= (int)$s['total'] ?></b></div>
    <h4>Total bookings</h4>
  </div>
  <div class="stat honey">
    <div class="top"><i class="bi bi-hourglass-split"></i><b><?= (int)$s['pending'] ?></b></div>
    <h4>Pending assignment</h4>
  </div>
  <div class="stat info">
    <div class="top"><i class="bi bi-arrow-repeat"></i><b><?= (int)$s['active'] ?></b></div>
    <h4>Active jobs</h4>
  </div>
  <div class="stat ok">
    <div class="top"><i class="bi bi-cash-coin"></i><b>&#8369;<?= number_format($s['revenue'], 0) ?></b></div>
    <h4>Paid booking value (excludes simulations)</h4>
  </div>
  <div class="stat">
    <div class="top"><i class="bi bi-people"></i><b><?= $counts['client'] + $counts['worker'] + $counts['admin'] ?></b></div>
    <h4><?= $counts['client'] ?> clients &middot; <?= $counts['worker'] ?> workers</h4>
  </div>
</div>

<!-- Marketplace overview: customer discovery, chat activity, worker availability and bookings -->
<section class="market-admin-overview">
  <div class="market-admin-overview-heading">
    <div><span class="eyebrow">CHOREHIVE MARKETPLACE</span><h2>Hiring &amp; conversation activity</h2><p>Track how customers discover cleaners and move from chat to a confirmed booking.</p></div>
    <a class="btn btn-sec" href="chats.php">Open conversations →</a>
  </div>
  <div class="market-admin-overview-grid">
    <a href="users.php" class="market-admin-overview-card"><span class="market-admin-overview-icon">♙</span><span class="market-admin-overview-value"><?= (int)$counts['worker'] ?></span><b>Cleaner profiles</b><small>Manage worker accounts and badges</small></a>
    <a href="chats.php" class="market-admin-overview-card"><span class="market-admin-overview-icon">☏</span><span class="market-admin-overview-value"><?= $conversationCount ?></span><b>Conversations</b><small><?= $messageCount ?> messages recorded</small></a>
    <a href="users.php" class="market-admin-overview-card"><span class="market-admin-overview-icon">●</span><span class="market-admin-overview-value"><?= $onlineWorkers ?></span><b>Recently active workers</b><small>Active in the last five minutes</small></a>
    <a href="bookings.php" class="market-admin-overview-card"><span class="market-admin-overview-icon">✓</span><span class="market-admin-overview-value"><?= $workersWithActiveJobs ?></span><b>Workers on active jobs</b><small>Assigned, in progress or awaiting verification</small></a>
  </div>
</section>

<!-- Charts & feed -->
<div class="card" style="margin-top:16px;">
  <div class="dash-cols">
    <div>
      <h2 class="card-title"><i class="bi bi-graph-up"></i> Bookings per service</h2>
      <?php if (!$perService): ?>
        <div class="empty-state"><span class="big">📊</span>No services have been added yet.</div>
      <?php endif; ?>
      <?php foreach ($perService as $i => $r): $pct = (int)round($r['c'] / $maxSvc * 100); ?>
        <div class="bar-row"><span><?= e($r['name']) ?></span><b><?= (int)$r['c'] ?></b></div>
        <div class="bar-track"><div class="bar-fill" style="width:<?= max($pct, 3) ?>%;animation-delay:<?= $i * 70 ?>ms"></div></div>
      <?php endforeach; ?>
    </div>
    <div>
      <h2 class="card-title"><i class="bi bi-activity"></i> Recent activity</h2>
      <?php if (!$feeds): ?>
        <div class="empty-state"><span class="big">🕓</span>No activity recorded yet.</div>
      <?php else: ?>
        <div class="feed">
          <?php foreach ($feeds as $i => $f):
            [$icon, $tone] = $feedIcons[$f['action']] ?? ['bi-circle-fill', 'info'];
            $actor = $f['actor'] ?: 'System';
            $details = $f['details'];
            if (mb_strpos($details, $actor . ' ') === 0) $details = trim(mb_substr($details, mb_strlen($actor) + 1));
          ?>
            <div class="feed-row" style="animation-delay:<?= $i * 55 ?>ms">
              <span class="feed-ico <?= $tone ?>"><i class="fa <?= $icon ?>"></i></span>
              <div>
                <div class="feed-text"><b><?= e($actor) ?></b> <?= e($details) ?></div>
                <div class="feed-when"><?= date('M j, g:i A', strtotime($f['created_at'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($unassigned || $unreadNotifs || $finishedTotal): ?>
<div class="card" style="border-color:#f0dcae;background:#fffdf8;">
  <h2 class="card-title"><i class="bi bi-bell" style="color:var(--warn)"></i> Needs attention</h2>
  <ul style="margin-left:18px;">
    <?php if ($unassigned): ?><li><b><?= $unassigned ?></b> booking(s) still waiting for a worker — <a href="bookings.php">assign a worker</a></li><?php endif; ?>
    <?php if ($unreadNotifs): ?><li><b><?= $unreadNotifs ?></b> unread notification(s) across all accounts</li><?php endif; ?>
    <?php if ($finishedTotal): ?><li><b><?= $finishedTotal ?></b> finished booking(s) can be purged — <a href="bookings.php">clean up</a></li><?php endif; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <h2 class="card-title"><i class="bi bi-lightning-charge"></i> Quick actions</h2>
  <div class="actions">
    <a class="btn" href="bookings.php"><i class="bi bi-calendar3"></i>&nbsp; Manage bookings</a>
    <a class="btn btn-sec" href="users.php"><i class="bi bi-people"></i>&nbsp; Users &amp; badges</a>
    <a class="btn btn-sec" href="services.php"><i class="bi bi-tags"></i>&nbsp; Services</a>
    <a class="btn btn-sec" href="reports.php"><i class="bi bi-bar-chart"></i>&nbsp; Reports</a>
    <a class="btn btn-sec" href="audit.php"><i class="bi bi-clock-history"></i>&nbsp; Audit log</a>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h2 class="card-title"><i class="bi bi-megaphone" style="color:var(--honey)"></i> Broadcast announcement</h2>
  <p class="muted" style="margin-bottom:12px;">Send an in-app notification to all users, or just clients or workers.</p>
  <form method="post">
    <?= csrf_field() ?>
    <label>Audience</label>
    <select name="audience" style="max-width:260px;">
      <option value="all">All users (clients + workers)</option>
      <option value="client">Clients only</option>
      <option value="worker">Workers only</option>
    </select>
    <label style="margin-top:10px;">Message</label>
    <textarea name="broadcast_message" rows="3" maxlength="500" required placeholder="e.g. We've added new cleaning services — book now and get 10% off this week!"></textarea>
    <button name="broadcast" value="1" class="btn" style="margin-top:10px;">📣 Send announcement</button>
  </form>
</div>
<?php require '../footer.php'; ?>
