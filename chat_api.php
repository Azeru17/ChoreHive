<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

function chat_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$u = current_user();
if (!$u || !in_array($u['role'], ['client', 'worker'], true)) {
    chat_json(401, ['ok' => false, 'error' => 'Please sign in to use messages.']);
}
require_marketplace_schema();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$partnerId = $isPost
    ? filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$partnerId || $partnerId < 1) {
    chat_json(400, ['ok' => false, 'error' => 'A conversation partner is required.']);
}
if ($isPost && !hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
    chat_json(403, ['ok' => false, 'error' => 'Security token expired. Refresh the page and try again.']);
}

$expectedRole = $u['role'] === 'client' ? 'worker' : 'client';
$partnerStmt = $conn->prepare('SELECT id,name,role,worker_approved FROM users WHERE id=? AND role=?');
$partnerStmt->bind_param('is', $partnerId, $expectedRole);
$partnerStmt->execute();
$partner = $partnerStmt->get_result()->fetch_assoc();
if (!$partner) {
    chat_json(404, ['ok' => false, 'error' => 'Conversation partner not found.']);
}
if ($u['role'] === 'client' && (int)$partner['worker_approved'] !== 1) {
    chat_json(404, ['ok' => false, 'error' => 'This worker profile is not available in the marketplace.']);
}

$clientId = $u['role'] === 'client' ? (int)$u['id'] : (int)$partnerId;
$workerId = $u['role'] === 'worker' ? (int)$u['id'] : (int)$partnerId;

// A single client/worker pair has one persistent conversation thread.
$threadStmt = $conn->prepare('INSERT INTO chat_threads (client_id,worker_id) VALUES (?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
$threadStmt->bind_param('ii', $clientId, $workerId);
if (!$threadStmt->execute()) {
    chat_json(500, ['ok' => false, 'error' => 'Could not open conversation.']);
}
$threadId = (int)$conn->insert_id;
if (!$threadId) {
    $lookup = $conn->prepare('SELECT id FROM chat_threads WHERE client_id=? AND worker_id=?');
    $lookup->bind_param('ii', $clientId, $workerId);
    $lookup->execute();
    $threadId = (int)$lookup->get_result()->fetch_assoc()['id'];
}

if ($u['role'] === 'worker') {
    $presence = $conn->prepare('INSERT INTO worker_presence (worker_id,last_seen) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW()');
    $presence->bind_param('i', $u['id']);
    $presence->execute();
}

if ($isPost) {
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 2000) {
        chat_json(422, ['ok' => false, 'error' => 'Messages must be between 1 and 2,000 characters.']);
    }
    $insert = $conn->prepare('INSERT INTO chat_messages (thread_id,sender_id,message) VALUES (?,?,?)');
    $insert->bind_param('iis', $threadId, $u['id'], $message);
    if (!$insert->execute()) {
        chat_json(500, ['ok' => false, 'error' => 'Message could not be saved.']);
    }
    $update = $conn->prepare('UPDATE chat_threads SET updated_at=NOW() WHERE id=?');
    $update->bind_param('i', $threadId);
    $update->execute();

    $short = mb_strimwidth($message, 0, 120, '…');
    $notice = 'New message from ' . $u['name'] . ': ' . $short;
    $actionUrl = 'chat.php?user_id=' . (int)$u['id'];
    $notify = $conn->prepare('INSERT INTO notifications (user_id,message,action_url) VALUES (?,?,?)');
    $notify->bind_param('iss', $partnerId, $notice, $actionUrl);
    $notify->execute();
}

$after = max(0, (int)($_GET['after'] ?? 0));
$messageStmt = $conn->prepare('SELECT m.id,m.sender_id,m.message,m.created_at,s.name sender FROM chat_messages m JOIN users s ON s.id=m.sender_id WHERE m.thread_id=? AND m.id>? ORDER BY m.id ASC LIMIT 100');
$messageStmt->bind_param('ii', $threadId, $after);
$messageStmt->execute();
$messageRows = $messageStmt->get_result();
$messages = [];
while ($row = $messageRows->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'sender' => $row['sender'],
        'message' => $row['message'],
        'time' => date('M j, g:i A', strtotime($row['created_at'])),
        'mine' => (int)$row['sender_id'] === (int)$u['id'],
    ];
}

$presenceStmt = $conn->prepare('SELECT last_seen FROM worker_presence WHERE worker_id=?');
$presenceStmt->bind_param('i', $workerId);
$presenceStmt->execute();
$presenceRow = $presenceStmt->get_result()->fetch_assoc();
$partnerOnline = $presenceRow && strtotime($presenceRow['last_seen']) >= time() - 300;

chat_json(200, ['ok' => true, 'messages' => $messages, 'partner_online' => (bool)$partnerOnline]);
