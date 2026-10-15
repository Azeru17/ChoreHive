<?php
require 'config.php';

$filename = basename((string)($_GET['file'] ?? ''));
if (!preg_match('/^(?:av|pf)[0-9]+_[a-f0-9]+\.(?:jpg|png|webp)$/', $filename)) {
    http_response_code(404);
    exit;
}

$media = media_read($filename);
$mimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!$media || !in_array($media['mime_type'], $mimeTypes, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $media['mime_type']);
header('Content-Length: ' . (string)strlen($media['file_data']));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo $media['file_data'];
