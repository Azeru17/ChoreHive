<?php
require '../config.php';
$u = require_role('admin');
$title = 'Marketplace Conversations';
$threadId = filter_input(INPUT_GET, 'thread', FILTER_VALIDATE_INT) ?: 0;
$search = trim((string)($_GET['q'] ?? ''));
$selectedThread = null;
$messages = [];
$relatedBookings = [];

if ($threadId > 0) {
    $detail = $conn->prepare("SELECT t.id,t.client_id,t.worker_id,t.created_at,t.updated_at,c.name client,c.email client_email,w.name worker,w.email worker_email FROM chat_threads t JOIN users c ON c.id=t.client_id JOIN users w ON w.id=t.worker_id WHERE t.id=?");
    $detail->bind_param('i', $threadId);
    $detail->execute();
    $selectedThread = $detail->get_result()->fetch_assoc();
    if ($selectedThread) {
        // Viewing private conversation content is intentionally read-only and audited.
        audit_log('admin_chat_viewed', "Admin {$u['name']} opened conversation #$threadId between client #{$selectedThread['client_id']} and worker #{$selectedThread['worker_id']}");
        $msgStmt = $conn->prepare('SELECT m.id,m.sender_id,m.message,m.created_at,s.name sender,s.role sender_role FROM chat_messages m JOIN users s ON s.id=m.sender_id WHERE m.thread_id=? ORDER BY m.id ASC LIMIT 500');
        $msgStmt->bind_param('i', $threadId);
        $msgStmt->execute();
        $messages = $msgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $bookStmt = $conn->prepare('SELECT b.id,b.schedule,b.status,b.payment_status,b.total_price,s.name service FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.client_id=? AND b.worker_id=? ORDER BY b.created_at DESC LIMIT 8');
        $bookStmt->bind_param('ii', $selectedThread['client_id'], $selectedThread['worker_id']);
        $bookStmt->execute();
        $relatedBookings = $bookStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        flash('That conversation could not be found.', 'error');
        header('Location: chats.php');
        exit;
    }
}

$conversationCount = (int)$conn->query('SELECT COUNT(*) c FROM chat_threads')->fetch_assoc()['c'];
$messageCount = (int)$conn->query('SELECT COUNT(*) c FROM chat_messages')->fetch_assoc()['c'];
$activeToday = (int)$conn->query("SELECT COUNT(*) c FROM chat_threads WHERE updated_at >= NOW() - INTERVAL 1 DAY")->fetch_assoc()['c'];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT t.id,t.created_at,t.updated_at,c.name client,w.name worker,(SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id=t.id) message_count,(SELECT m.message FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message,(SELECT m.created_at FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message_at FROM chat_threads t JOIN users c ON c.id=t.client_id JOIN users w ON w.id=t.worker_id WHERE c.name LIKE ? OR w.name LIKE ? ORDER BY t.updated_at DESC");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("SELECT t.id,t.created_at,t.updated_at,c.name client,w.name worker,(SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id=t.id) message_count,(SELECT m.message FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message,(SELECT m.created_at FROM chat_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message_at FROM chat_threads t JOIN users c ON c.id=t.client_id JOIN users w ON w.id=t.worker_id ORDER BY t.updated_at DESC");
}
require '../layout.php';
?>
<div class="page-intro"><span class="eyebrow">MARKETPLACE OPERATIONS</span><h1>Customer conversations<span class="heading-period">.</span></h1><p>Review conversation activity when helping resolve a service issue, and follow up using the related booking records.</p></div>
<div class="market-admin-stats">
  <div class="market-admin-stat"><span class="market-admin-stat-icon">☏</span><div><b><?= $conversationCount ?></b><small>Total conversations</small></div></div>
  <div class="market-admin-stat"><span class="market-admin-stat-icon">✉</span><div><b><?= $messageCount ?></b><small>Total messages</small></div></div>
  <div class="market-admin-stat"><span class="market-admin-stat-icon">◷</span><div><b><?= $activeToday ?></b><small>Active in last 24 hours</small></div></div>
</div>
<?php if ($selectedThread): ?>
  <div class="card admin-conversation-detail">
    <div class="admin-conversation-title"><div><a class="market-text-link" href="chats.php">← Back to all conversations</a><h2>Conversation #<?= (int)$selectedThread['id'] ?></h2><p><?= e($selectedThread['client']) ?> ↔ <?= e($selectedThread['worker']) ?></p></div><span class="admin-readonly-pill">Read only · Access audited</span></div>
    <div class="admin-conversation-participants"><div><small>Customer</small><b><?= e($selectedThread['client']) ?></b><span><?= e($selectedThread['client_email']) ?></span></div><div><small>Worker</small><b><?= e($selectedThread['worker']) ?></b><span><?= e($selectedThread['worker_email']) ?></span></div></div>
    <div class="admin-transcript" aria-label="Conversation transcript">
      <?php if (!$messages): ?><p class="muted">No messages have been sent in this conversation.</p><?php else: foreach ($messages as $m): ?><article class="admin-transcript-message <?= $m['sender_role']==='worker'?'worker-message':'client-message' ?>"><div class="admin-transcript-meta"><b><?= e($m['sender']) ?></b><span><?= date('M j, Y g:i A', strtotime($m['created_at'])) ?></span></div><p><?= nl2br(e($m['message'])) ?></p></article><?php endforeach; endif; ?>
    </div>
    <p class="admin-privacy-note">For support use only. This view does not allow administrators to impersonate either participant or send messages. Opening a transcript is recorded in the audit log.</p>
  </div>
  <div class="card"><h2>Related bookings</h2><?php if (!$relatedBookings): ?><p class="muted">No bookings between this customer and worker yet. They may still be discussing a potential hire.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Booking</th><th>Service</th><th>Schedule</th><th>Status</th><th>Payment</th><th>Total</th></tr></thead><tbody><?php foreach ($relatedBookings as $b): ?><tr><td><a href="bookings.php">#<?= (int)$b['id'] ?></a></td><td><?= e($b['service']) ?></td><td><?= date('M j, Y g:i A', strtotime($b['schedule'])) ?></td><td><span class="badge <?= status_class($b['status']) ?>"><?= e(str_replace('_',' ',$b['status'])) ?></span></td><td><?= e($b['payment_status']) ?></td><td>₱<?= number_format((float)$b['total_price'],2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php endif; ?>
<div class="card admin-conversations-list">
  <div class="section-heading compact"><div><h2>Conversation activity</h2><p>Search by customer or worker name. Open a transcript only when needed for support.</p></div></div>
  <form class="admin-chat-search" method="get"><label for="chatSearch">Find a conversation</label><div><input id="chatSearch" name="q" value="<?= e($search) ?>" placeholder="Search customer or worker name"><button class="btn btn-sm">Search</button><?php if ($search !== ''): ?><a class="btn btn-sec btn-sm" href="chats.php">Clear</a><?php endif; ?></div></form>
  <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Worker</th><th>Last message</th><th>Messages</th><th>Last activity</th><th></th></tr></thead><tbody>
  <?php if (!$res->num_rows): ?><tr><td colspan="6" class="muted">No conversations match your search.</td></tr><?php else: while ($t=$res->fetch_assoc()): ?><tr><td><?= e($t['client']) ?></td><td><?= e($t['worker']) ?></td><td><?= e(mb_strimwidth($t['last_message']?:'No messages yet',0,78,'…')) ?></td><td><?= (int)$t['message_count'] ?></td><td><?= $t['last_message_at']?date('M j, Y g:i A',strtotime($t['last_message_at'])):'—' ?></td><td><a class="btn btn-sm btn-sec" href="chats.php?thread=<?= (int)$t['id'] ?>">Review</a></td></tr><?php endwhile; endif; ?>
  </tbody></table></div>
</div>
<?php require '../footer.php'; ?>
