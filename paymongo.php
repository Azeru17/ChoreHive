<?php
/**
 * PayMongo Checkout Sessions (GCash / Maya) for ChoreHive.
 */

function paymongo_configured() {
    return defined('PAYMONGO_SECRET_KEY') && PAYMONGO_SECRET_KEY !== '';
}

function paymongo_method_types($payment_method) {
    if ($payment_method === 'maya') {
        return ['paymaya'];
    }
    return ['gcash'];
}

function paymongo_request($method, $path, $body = null) {
    if (!paymongo_configured()) {
        throw new RuntimeException('PayMongo is not configured. Add PAYMONGO_SECRET_KEY to config.local.php.');
    }
    $url = 'https://api.paymongo.com/v1/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        throw new RuntimeException('Could not reach PayMongo. Check your server internet connection.');
    }
    $decoded = json_decode((string)$raw, true);
    if ($http >= 400) {
        $detail = $decoded['errors'][0]['detail'] ?? ('PayMongo HTTP ' . $http);
        throw new RuntimeException($detail);
    }
    return is_array($decoded) ? $decoded : [];
}

function paymongo_create_checkout(array $booking, $serviceName) {
    $amount = (int)round(((float)$booking['total_price']) * 100);
    if ($amount < 100) {
        throw new RuntimeException('PayMongo requires a minimum of ₱1.00.');
    }
    $id = (int)$booking['id'];
    $body = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'description' => 'ChoreHive booking #' . $id,
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => $amount,
                    'name' => mb_substr((string)$serviceName, 0, 50) ?: 'Cleaning service',
                    'quantity' => 1,
                ]],
                'payment_method_types' => paymongo_method_types($booking['payment_method'] ?? 'gcash'),
                'success_url' => app_url('client/pay.php?id=' . $id . '&paymongo=success'),
                'cancel_url' => app_url('client/pay.php?id=' . $id . '&paymongo=cancel'),
                'metadata' => [
                    'booking_id' => (string)$id,
                ],
            ],
        ],
    ];
    return paymongo_request('POST', 'checkout_sessions', $body);
}

function paymongo_get_checkout($checkoutId) {
    $checkoutId = rawurlencode((string)$checkoutId);
    return paymongo_request('GET', 'checkout_sessions/' . $checkoutId);
}

function paymongo_checkout_payment_info(array $session) {
    $attrs = $session['data']['attributes'] ?? [];
    $payments = $attrs['payments'] ?? [];
    if (is_array($payments)) {
        foreach ($payments as $p) {
            $st = strtolower((string)($p['attributes']['status'] ?? ''));
            if (in_array($st, ['paid', 'succeeded'], true)) {
                return ['paid' => true, 'id' => (string)($p['id'] ?? ''), 'status' => $st];
            }
        }
    }
    $pi = $attrs['payment_intent'] ?? [];
    if (is_array($pi)) {
        $piStatus = strtolower((string)($pi['attributes']['status'] ?? $pi['status'] ?? ''));
        if (in_array($piStatus, ['succeeded', 'paid'], true)) {
            return ['paid' => true, 'id' => (string)($pi['id'] ?? ''), 'status' => $piStatus];
        }
    }
    $status = strtolower((string)($attrs['status'] ?? ''));
    if ($status === 'paid') {
        return ['paid' => true, 'id' => '', 'status' => $status];
    }
    return ['paid' => false, 'id' => '', 'status' => $status];
}

function paymongo_session_expired(array $session) {
    $status = strtolower((string)($session['data']['attributes']['status'] ?? ''));
    return $status === 'expired';
}

function paymongo_checkout_url(array $session) {
    return (string)($session['data']['attributes']['checkout_url'] ?? '');
}

function paymongo_verify_webhook_signature($payload, $header) {
    if (PAYMONGO_WEBHOOK_SECRET === '') {
        return false;
    }
    $timestamp = '';
    $testSig = '';
    $liveSig = '';
    foreach (explode(',', (string)$header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't') $timestamp = $kv[1];
        if ($kv[0] === 'te') $testSig = $kv[1];
        if ($kv[0] === 'li') $liveSig = $kv[1];
    }
    if ($timestamp === '') {
        return false;
    }
    $computed = hash_hmac('sha256', $timestamp . '.' . $payload, PAYMONGO_WEBHOOK_SECRET);
    $match = ($testSig !== '' && hash_equals($testSig, $computed))
        || ($liveSig !== '' && hash_equals($liveSig, $computed));
    if (!$match) {
        return false;
    }
    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }
    return true;
}

function record_online_payment(array $booking, $reference, $paymentId = '', $checkoutId = '') {
    global $conn;
    $id = (int)$booking['id'];
    if (($booking['payment_status'] ?? '') === 'paid') {
        return true;
    }
    $reference = $reference !== '' ? $reference : ('CH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))));
    $paymentId = (string)$paymentId;
    $checkoutId = (string)$checkoutId;
    $stmt = $conn->prepare("UPDATE bookings SET payment_status='paid', payment_reference=?, paid_at=NOW(), paymongo_payment_id=NULLIF(?, ''), paymongo_checkout_id=COALESCE(NULLIF(?, ''), paymongo_checkout_id) WHERE id=? AND payment_status='unpaid' AND payment_method IN ('gcash','maya') AND status<>'completed'");
    $stmt->bind_param('sssi', $reference, $paymentId, $checkoutId, $id);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $check = $conn->prepare("SELECT payment_status FROM bookings WHERE id=?");
        $check->bind_param('i', $id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        return ($row['payment_status'] ?? '') === 'paid';
    }

    $statusStmt = $conn->prepare('SELECT status FROM bookings WHERE id=?');
    $statusStmt->bind_param('i', $id);
    $statusStmt->execute();
    $bookingStatus = (string)($statusStmt->get_result()->fetch_assoc()['status'] ?? '');
    $provider = (($booking['payment_method'] ?? '') === 'maya') ? 'Maya' : 'GCash';
    $amount = number_format((float)$booking['total_price'], 2);
    if ($bookingStatus === 'cancelled') {
        if (!empty($booking['worker_id'])) {
            notify_and_email((int)$booking['worker_id'], "Payment for booking #$id arrived after the client cancelled it. Do not proceed with the job; the client has been told to contact ChoreHive support about a refund.", 'Payment received after cancellation', 'worker/job.php?id=' . $id);
        }
        if (!empty($booking['client_id'])) {
            notify_and_email((int)$booking['client_id'], "Your $provider payment for cancelled booking #$id was received. The booking remains cancelled and the payment is not automatically refunded; contact ChoreHive support to request a refund.", 'Payment received after cancellation', 'client/booking.php?id=' . $id);
        }
    } elseif (!empty($booking['worker_id'])) {
        notify_and_email((int)$booking['worker_id'], "Prepayment received for booking #$id — ₱$amount via $provider. You may proceed with the job.", 'Prepayment received', 'worker/job.php?id=' . $id);
        if (!empty($booking['client_id'])) {
            $service = $booking['service'] ?? 'cleaning service';
            notify_and_email((int)$booking['client_id'], "Your $provider payment for booking #$id ($service) was received. Receipt reference: $reference.", 'Payment receipt available', 'client/receipt.php?id=' . $id);
        }
    } elseif (!empty($booking['client_id'])) {
        $service = $booking['service'] ?? 'cleaning service';
        notify_and_email((int)$booking['client_id'], "Your $provider payment for booking #$id ($service) was received. Receipt reference: $reference.", 'Payment receipt available', 'client/receipt.php?id=' . $id);
    }
    $who = current_user()['name'] ?? 'PayMongo';
    audit_log('payment_completed', "$who recorded ₱{$booking['total_price']} for booking #$id via $provider (PayMongo reference $reference)");
    return true;
}

function paymongo_fulfill_checkout(array $booking, array $session) {
    $info = paymongo_checkout_payment_info($session);
    if (!$info['paid']) {
        return false;
    }
    $checkoutId = (string)($session['data']['id'] ?? ($booking['paymongo_checkout_id'] ?? ''));
    $paymentId = $info['id'];
    $reference = $paymentId !== '' ? $paymentId : $checkoutId;
    return record_online_payment($booking, $reference, $paymentId, $checkoutId);
}
/**
 * Re-check a booking's PayMongo checkout and record the payment if PayMongo
 * confirms it was paid. This "self-heals" bookings where the customer paid but
 * never returned to the success URL (closed the tab, dropped the connection),
 * so a paid booking is never stuck as "unpaid" just because the redirect failed.
 *
 * Safe to call repeatedly: record_online_payment() is idempotent. Only performs
 * a network call when the booking is an unpaid GCash/Maya booking that already
 * has a stored PayMongo checkout id. Network/API errors are swallowed so a
 * transient failure never breaks the calling page.
 *
 * @return bool True if the booking is paid (already, or just recorded now).
 */
function paymongo_sync_booking(array $booking): bool {
    $isPaid = ($booking['payment_status'] ?? '') === 'paid';
    if (!paymongo_configured() || $isPaid) return $isPaid;
    if (!in_array($booking['payment_method'] ?? '', ['gcash', 'maya'], true)) return $isPaid;
    if (empty($booking['paymongo_checkout_id'])) return $isPaid;
    try {
        $session = paymongo_get_checkout($booking['paymongo_checkout_id']);
        if (paymongo_fulfill_checkout($booking, $session)) {
            return true;
        }
    } catch (Throwable $ex) {
        error_log('PayMongo sync failed for booking #' . (int)($booking['id'] ?? 0) . ' - ' . (string)$ex);
    }
    return ($booking['payment_status'] ?? '') === 'paid';
}