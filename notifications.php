<?php
require 'config.php';
$u = current_user();
if (!$u) { header('Location: login.php'); exit; }

function notif_payload($conn, $uid) {
    $stmt = $conn->prepare("SELECT id, message, action_url, created_at, is_read FROM notifications
                            WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $out = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'id'      => (int)$r['id'],
            'message' => $r['message'],
            'url'     => is_string($r['action_url']) && preg_match('~^(client|worker|admin)/[a-z0-9_-]+\.php(?:\?[a-z0-9_=&%-]*)?$|^chat\.php(?:\?[a-z0-9_=&%-]*)?$~i', $r['action_url'])
                ? ch_base() . '/' . $r['action_url']
                : null,
            'when'    => date('M j, g:i A', strtotime($r['created_at'])),
            'unread'  => !$r['is_read'],
        ];
    }
    return $out;
}

$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch')
    || isset($_GET['fetch']) || isset($_GET['poll'])
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET));

/* ---------- Actions (state-changing, so POST + CSRF) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        if ($is_ajax || !empty($_POST['action'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            exit;
        }
        fail('Invalid CSRF token.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $stmt->bind_param('i', $u['id']);
        $stmt->execute();
    } elseif ($action === 'follow') {
        $nid = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $nid, $u['id']);
        $stmt->execute();
        $urlStmt = $conn->prepare("SELECT action_url FROM notifications WHERE id=? AND user_id=?");
        $urlStmt->bind_param('ii', $nid, $u['id']);
        $urlStmt->execute();
        $target = $urlStmt->get_result()->fetch_assoc()['action_url'] ?? '';
        $redirect = is_string($target) && preg_match('~^(client|worker|admin)/[a-z0-9_-]+\.php(?:\?[a-z0-9_=&%-]*)?$|^chat\.php(?:\?[a-z0-9_=&%-]*)?$~i', $target)
            ? ch_base() . '/' . $target
            : 'notifications.php';
        if ($is_ajax || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'redirect' => $redirect, 'items' => notif_payload($conn, $u['id'])]);
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    } elseif ($action === 'read_all' || $action === 'mark_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $stmt->bind_param('i', $u['id']);
        $stmt->execute();
        audit_log('notifications_read', $u['name'] . ' marked notifications as read');
    } elseif ($action === 'delete_one') {
        $nid = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $nid, $u['id']);
        $stmt->execute();
    } elseif ($action === 'clear') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id=?");
        $stmt->bind_param('i', $u['id']);
        $stmt->execute();
        audit_log('notifications_cleared', $u['name'] . ' cleared their notifications');
    }
    if ($is_ajax || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'items' => notif_payload($conn, $u['id'])]);
        exit;
    }
    header('Location: notifications.php'); exit;
}

if (isset($_GET['fetch']) || isset($_GET['poll']) || $is_ajax) {
    header('Content-Type: application/json');
    $since = (int)($_GET['since'] ?? 0);
    $items = notif_payload($conn, $u['id']);
    $c = $conn->prepare("SELECT COUNT(*) c, COALESCE(MAX(id),0) m FROM notifications WHERE user_id=? AND is_read=0");
    $c->bind_param('i', $u['id']);
    $c->execute();
    $row = $c->get_result()->fetch_assoc();
    echo json_encode(['items' => $items, 'unread' => (int)$row['c'], 'max_id' => (int)$row['m']]);
    exit;
}

/* ---------- Full page (Bootstrap pro list) ---------- */
$title = 'Notifications';
require 'layout.php';
$items = notif_payload($conn, $u['id']);
$unread = count(array_filter($items, fn($n) => $n['unread']));
?>
<div class="page-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
  <div>
    <h1 class="mb-1"><i class="bi bi-bell-ring me-2"></i>Notifications</h1>
    <p class="mb-0">Every booking move, payment and job update — the moment it happens.</p>
  </div>
  <div class="d-flex gap-2">
    <form method="post" class="m-0"><?= csrf_field() ?><button class="btn btn-light btn-sm" name="action" value="read_all"><i class="bi bi-check2-all"></i> Mark all read</button></form>
    <form method="post" class="m-0" data-confirm="Clear all notifications?" data-confirm-title="Clear notifications" data-confirm-yes="Yes, clear all"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm" name="action" value="clear"><i class="bi bi-trash"></i> Clear all</button></form>
  </div>
</div>

<div class="card shadow-sm border-0 p-0 overflow-hidden">
  <div class="card-body p-2 p-md-3">
    <?php if (!$items): ?>
      <div class="empty-state"><span class="big">🔔</span><b>You're all caught up.</b><br><span class="muted">New booking updates, payments and job status changes will appear here instantly.</span></div>
    <?php else: ?>
      <div class="list-group list-group-flush notif-list">
        <?php foreach ($items as $n): ?>
          <div class="list-group-item d-flex gap-3 align-items-start <?= $n['unread'] ? 'notif-unread' : '' ?>">
            <span class="notif-ico"><i class="bi <?= $n['unread'] ? 'bi-bell-fill' : 'bi-bell' ?>"></i></span>
            <div class="flex-grow-1">
              <?php if ($n['url']): ?><form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="follow"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="notif-follow" type="submit"><?= e($n['message']) ?></button></form><?php else: ?><div class="notif-msg"><?= e($n['message']) ?></div><?php endif; ?>
              <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($n['when']) ?></small>
            </div>
            <?php if ($n['unread']): ?><span class="badge rounded-pill text-bg-warning">new</span><?php endif; ?>
            <form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="icon-btn" name="action" value="delete_one" title="Delete">×</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<p class="mt-3"><a class="btn btn-sec btn-sm" href="<?= $base ?>/<?= $u['role'] ?>/dashboard.php">← Back to dashboard</a></p>
<?php require 'footer.php'; ?>
