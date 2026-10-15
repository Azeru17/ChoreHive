<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/paymongo.php';

$payload = file_get_contents('php://input') ?: '';
$header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (!paymongo_verify_webhook_signature($payload, $header)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
$type = (string)($event['data']['attributes']['type'] ?? '');
$resource = $event['data']['attributes']['data'] ?? [];

$bookingId = 0;
$checkoutId = '';
$paymentId = '';
$paid = false;

if ($type === 'checkout_session.payment.paid' || isset($resource['id']) && str_starts_with((string)($resource['id'] ?? ''), 'cs_')) {
    $checkoutId = (string)($resource['id'] ?? '');
    $meta = $resource['attributes']['metadata']['booking_id'] ?? '';
    $bookingId = (int)$meta;
    $info = paymongo_checkout_payment_info(['data' => $resource]);
    $paid = $info['paid'] || $type === 'checkout_session.payment.paid';
    $paymentId = $info['id'];
}

if ($type === 'payment.paid') {
    $paymentId = (string)($resource['id'] ?? '');
    $meta = $resource['attributes']['metadata']['booking_id'] ?? '';
    $bookingId = (int)$meta;
    $paid = true;
}

if (!$paid) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'ignored' => $type]);
    exit;
}

$booking = null;
if ($bookingId > 0) {
    $stmt = $conn->prepare('SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.id=?');
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
} elseif ($checkoutId !== '') {
    $stmt = $conn->prepare('SELECT b.*, s.name service FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.paymongo_checkout_id=?');
    $stmt->bind_param('s', $checkoutId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
}

if (!$booking || !in_array($booking['payment_method'], ['gcash', 'maya'], true)) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'unmatched' => true]);
    exit;
}

$reference = $paymentId !== '' ? $paymentId : ($checkoutId !== '' ? $checkoutId : ('CH-' . $booking['id']));
record_online_payment($booking, $reference, $paymentId, $checkoutId);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
