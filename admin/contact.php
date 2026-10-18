<?php
require '../config.php';
$u = require_role('admin');
$messageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$search = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_read'])) {
    verify_csrf();
    $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT) ?: 0;
    if ($messageId < 1 || !in_array($_POST['set_read'], ['0', '1'], true)) {
        flash('That message action is invalid.', 'error');
        header('Location: contact.php');
        exit;
    }
    $isRead = (int)$_POST['set_read'];
    $stmt = $conn->prepare('UPDATE contact_messages SET is_read=?, read_at=? WHERE id=?');
    $readAt = $isRead ? date('Y-m-d H:i:s') : null;
    $stmt->bind_param('isi', $isRead, $readAt, $messageId);
    $stmt->execute();
    $stmt->close();
    audit_log('contact_message_status_changed', "Admin {$u['name']} marked contact message #$messageId as " . ($isRead ? 'read' : 'unread'));
    flash($isRead ? 'Message marked as read.' : 'Message marked as unread.');
    header('Location: contact.php?id=' . $messageId);
    exit;
}

$selectedMessage = null;
if ($messageId > 0) {
    $stmt = $conn->prepare('SELECT id, name, email, message, is_read, created_at, read_at FROM contact_messages WHERE id=?');
    $stmt->bind_param('i', $messageId);
    $stmt->execute();
    $selectedMessage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$selectedMessage) {
        flash('That contact message could not be found.', 'error');
        header('Location: contact.php');
        exit;
    }
    audit_log('contact_message_viewed', "Admin {$u['name']} viewed contact message #$messageId");
}

$unreadCount = (int)$conn->query('SELECT COUNT(*) c FROM contact_messages WHERE is_read=0')->fetch_assoc()['c'];
$totalCount = (int)$conn->query('SELECT COUNT(*) c FROM contact_messages')->fetch_assoc()['c'];
$listSql = 'SELECT id, name, email, message, is_read, created_at FROM contact_messages';
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare($listSql . ' WHERE name LIKE ? OR email LIKE ? OR message LIKE ? ORDER BY created_at DESC, id DESC LIMIT 100');
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $messages = $stmt->get_result();
} else {
    $messages = $conn->query($listSql . ' ORDER BY created_at DESC, id DESC LIMIT 100');
}

$title = 'Contact Messages';
require '../layout.php';
?>
<div class="page-intro"><span class="eyebrow">CUSTOMER SUPPORT</span><h1>Contact messages<span class="heading-period">.</span></h1><p>Messages submitted through the public homepage contact form. Only administrators can access this inbox.</p></div>
<div class="market-admin-stats">
  <div class="market-admin-stat"><span class="market-admin-stat-icon">✉</span><div><b><?= $totalCount ?></b><small>Total messages</small></div></div>
  <div class="market-admin-stat"><span class="market-admin-stat-icon">◷</span><div><b><?= $unreadCount ?></b><small>Unread</small></div></div>
</div>

<?php if ($selectedMessage): ?>
<section class="card" style="margin-bottom:18px;">
  <div class="section-heading compact"><div><a class="market-text-link" href="contact.php">← Back to inbox</a><h2 style="margin-top:10px;">Message #<?= (int)$selectedMessage['id'] ?></h2></div><span class="badge <?= $selectedMessage['is_read'] ? 'ok' : 'warn' ?>"><?= $selectedMessage['is_read'] ? 'Read' : 'Unread' ?></span></div>
  <div class="form-grid">
    <div><small class="muted">From</small><p><b><?= e($selectedMessage['name']) ?></b></p></div>
    <div><small class="muted">Email</small><p><a href="mailto:<?= e($selectedMessage['email']) ?>"><?= e($selectedMessage['email']) ?></a></p></div>
    <div><small class="muted">Received</small><p><?= date('M j, Y g:i A', strtotime($selectedMessage['created_at'])) ?></p></div>
  </div>
  <div style="padding:16px;background:var(--bg);border-radius:12px;white-space:pre-wrap;overflow-wrap:anywhere;"><?= e($selectedMessage['message']) ?></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
    <a class="btn btn-sm" href="mailto:<?= e($selectedMessage['email']) ?>?subject=<?= rawurlencode('Re: ChoreHive contact message') ?>">Reply by email</a>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="message_id" value="<?= (int)$selectedMessage['id'] ?>">
      <input type="hidden" name="set_read" value="<?= $selectedMessage['is_read'] ? '0' : '1' ?>">
      <button class="btn btn-sm btn-sec"><?= $selectedMessage['is_read'] ? 'Mark as unread' : 'Mark as read' ?></button>
    </form>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="section-heading compact"><div><h2>Inbox</h2><p>Newest 100 messages. Search by sender, email, or message text.</p></div></div>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0;">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search messages" aria-label="Search messages">
    <button class="btn btn-sm">Search</button>
    <?php if ($search !== ''): ?><a class="btn btn-sec btn-sm" href="contact.php">Clear</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table><thead><tr><th>Status</th><th>Sender</th><th>Email</th><th>Message</th><th>Received</th><th></th></tr></thead><tbody>
  <?php if (!$messages->num_rows): ?><tr><td colspan="6" class="muted">No contact messages<?= $search !== '' ? ' match your search' : ' have been received yet' ?>.</td></tr><?php else: while ($message = $messages->fetch_assoc()): ?>
    <tr>
      <td><span class="badge <?= $message['is_read'] ? 'ok' : 'warn' ?>"><?= $message['is_read'] ? 'Read' : 'Unread' ?></span></td>
      <td><?= e($message['name']) ?></td>
      <td><?= e($message['email']) ?></td>
      <td><?= e(mb_strimwidth($message['message'], 0, 90, '…')) ?></td>
      <td style="white-space:nowrap;"><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></td>
      <td><a class="btn btn-sm btn-sec" href="contact.php?id=<?= (int)$message['id'] ?>">Open</a></td>
    </tr>
  <?php endwhile; endif; ?>
  </tbody></table></div>
</section>
<?php require '../footer.php'; ?>
