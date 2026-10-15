<?php
require 'config.php';

$user = current_user();
if (!$user) {
    http_response_code(404);
    exit;
}

$photoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conn->prepare('SELECT p.filepath,b.client_id,b.worker_id FROM booking_photos p JOIN bookings b ON b.id=p.booking_id WHERE p.id=?');
$stmt->bind_param('i', $photoId);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();
if (!$photo) {
    http_response_code(404);
    exit;
}

$allowed = $user['role'] === 'admin'
    || ($user['role'] === 'client' && (int)$user['id'] === (int)$photo['client_id'])
    || ($user['role'] === 'worker' && (int)$user['id'] === (int)$photo['worker_id']);
if (!$allowed) {
    http_response_code(404);
    exit;
}

$filename = basename((string)$photo['filepath']);
$path = UPLOAD_DIR . '/' . $filename;
$image = is_file($path) ? @getimagesize($path) : false;
$mimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if ($image === false || !in_array($image['mime'], $mimeTypes, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
