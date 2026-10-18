<?php
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

function chorehive_env($name, $default = '') {
    $value = getenv($name);
    return $value === false ? $default : $value;
}

if (!defined('DB_HOST')) define('DB_HOST', chorehive_env('DB_HOST', 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', (int)chorehive_env('DB_PORT', 3306));
if (!defined('DB_USER')) define('DB_USER', chorehive_env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', chorehive_env('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', chorehive_env('DB_NAME', 'chorehive'));
if (!defined('DB_SSL')) define('DB_SSL', filter_var(chorehive_env('DB_SSL', chorehive_env('VERCEL') !== ''), FILTER_VALIDATE_BOOLEAN));
if (!defined('DB_SSL_CA')) define('DB_SSL_CA', chorehive_env('DB_SSL_CA', ''));
if (!defined('SESSION_DRIVER')) define('SESSION_DRIVER', chorehive_env('SESSION_DRIVER', chorehive_env('VERCEL') !== '' ? 'database' : 'file'));
if (!defined('MEDIA_STORAGE')) define('MEDIA_STORAGE', chorehive_env('MEDIA_STORAGE', chorehive_env('VERCEL') !== '' ? 'database' : 'local'));
if (!defined('APP_URL')) define('APP_URL', chorehive_env('APP_URL', ''));
if (!defined('PAYMONGO_SECRET_KEY')) define('PAYMONGO_SECRET_KEY', chorehive_env('PAYMONGO_SECRET_KEY', ''));
if (!defined('PAYMONGO_PUBLIC_KEY')) define('PAYMONGO_PUBLIC_KEY', chorehive_env('PAYMONGO_PUBLIC_KEY', ''));
if (!defined('PAYMONGO_WEBHOOK_SECRET')) define('PAYMONGO_WEBHOOK_SECRET', chorehive_env('PAYMONGO_WEBHOOK_SECRET', ''));
if (!defined('PAYMENT_DEMO_MODE')) define('PAYMENT_DEMO_MODE', filter_var(chorehive_env('PAYMENT_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN));
if (!defined('SMTP_HOST')) define('SMTP_HOST', chorehive_env('SMTP_HOST', ''));
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)chorehive_env('SMTP_PORT', 465));
if (!defined('SMTP_USER')) define('SMTP_USER', chorehive_env('SMTP_USER', ''));
if (!defined('SMTP_PASS')) define('SMTP_PASS', chorehive_env('SMTP_PASS', ''));
if (!defined('SMTP_FROM')) define('SMTP_FROM', chorehive_env('SMTP_FROM', ''));
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', chorehive_env('SMTP_FROM_NAME', 'ChoreHive'));
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', chorehive_env('ADMIN_EMAIL', ''));

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024); // 3 MB per photo
if (!in_array(SESSION_DRIVER, ['file', 'database'], true)) {
    die('Invalid SESSION_DRIVER. Use "file" or "database".');
}
if (!in_array(MEDIA_STORAGE, ['local', 'database'], true)) {
    die('Invalid MEDIA_STORAGE. Use "local" or "database".');
}

if (DB_SSL) {
    $conn = mysqli_init();
    if (!$conn) {
        die('DB connection failed: could not initialize MySQL client.');
    }
    if (!defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')
        || !defined('MYSQLI_CLIENT_SSL')
        || !$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true)) {
        die('DB connection failed: this PHP MySQL client cannot verify TLS certificates.');
    }
    if (DB_SSL_CA !== '') {
        if (!is_file(DB_SSL_CA) || !is_readable(DB_SSL_CA)) {
            die('DB connection failed: DB_SSL_CA must point to a readable CA certificate file.');
        }
        $conn->ssl_set(null, null, DB_SSL_CA, null, null);
    }
    $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, null, MYSQLI_CLIENT_SSL);
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
}
if ($conn->connect_error) die('DB connection failed: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');

if (SESSION_DRIVER === 'database') {
    require_once __DIR__ . '/lib/DatabaseSessionHandler.php';
    session_set_save_handler(new ChoreHiveDatabaseSessionHandler($conn), true);
}
$requestIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $requestIsHttps,
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

/* ---------- Portable base path ----------
 * Derives the app's web-root-relative base (e.g. "/chorehive") from the
 * running script, so the project is not tied to a hardcoded folder name and
 * deploys cleanly to any host or subfolder (localhost, 000webhost, etc.).
 */
function ch_base() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = preg_replace('~/(client|worker|admin)$~', '', $dir); // strip role subfolder
    return rtrim($dir, '/');
}

function payment_demo_enabled() {
    if (!PAYMENT_DEMO_MODE) return false;
    $host = strtolower((string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $remote = strtolower((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $localHost = $host === 'localhost' || $host === '::1' || (bool)preg_match('/^127(?:\.\d{1,3}){3}$/', $host)
        || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
    $localConnection = $remote === '::1' || (bool)preg_match('/^127(?:\.\d{1,3}){3}$/', $remote);
    return $localHost && $localConnection;
}

/** Absolute public URL for emails, PayMongo return URLs, and Hostinger HTTPS. */
function app_url($path = '') {
    $path = ltrim((string)$path, '/');
    $configured = rtrim((string)APP_URL, '/');
    if ($configured !== '') {
        return $configured . ($path !== '' ? '/' . $path : '');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = ch_base();
    return $scheme . '://' . $host . $base . ($path !== '' ? '/' . $path : '');
}

function smtp_configured() {
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '' && SMTP_FROM !== '';
}

function send_app_email($to, $subject, $body, $replyTo = null) {
    if (!filter_var((string)$to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $subject = (string)$subject;
    $body = (string)$body;
    if (!smtp_configured()) {
        $line = sprintf("[%s] TO %s | SUBJECT: %s | %s\n", date('Y-m-d H:i:s'), $to, $subject, $body);
        @file_put_contents(__DIR__ . '/email_outbox.log', $line, FILE_APPEND);
        error_log('ChoreHive mail (SMTP not configured): ' . $subject . ' -> ' . $to);
        return false;
    }
    require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $port = (int)SMTP_PORT ?: 465;
        $mail->Port = $port;
        $mail->SMTPSecure = $port === 587
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (Throwable $ex) {
        error_log('ChoreHive SMTP send failed: ' . $ex->getMessage());
        return false;
    }
}


function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function current_user() { return $_SESSION['user'] ?? null; }
function require_marketplace_schema() {
    global $conn;
    static $ready = null;
    if ($ready === null) {
        $check = $conn->query("SELECT
                SUM(COLUMN_NAME='worker_approved') AS worker_ok,
                SUM(COLUMN_NAME='city') AS city_ok
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'");
        $cols = $check ? $check->fetch_assoc() : ['worker_ok' => 0, 'city_ok' => 0];
        $ready = (int)$cols['worker_ok'] === 1 && (int)$cols['city_ok'] === 1;
    }
    if ($ready) return;

    http_response_code(503);
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'chat_api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'The site database needs an upgrade before marketplace features can be used.']);
        exit;
    }

    $base = ch_base();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup required — ChoreHive</title><style>
      *{box-sizing:border-box}body{margin:0;padding:24px;background:#f4f7fc;color:#1f2c44;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
      main{max-width:680px;margin:8vh auto;padding:clamp(22px,5vw,40px);background:#fff;border:1px solid #e3e9f2;border-radius:18px;box-shadow:0 12px 38px rgba(23,66,122,.1)}
      h1{margin:0 0 12px;color:#17427a;font-size:clamp(1.5rem,5vw,2rem)}p{margin:12px 0}
      .notice{padding:14px 16px;background:#fff7e3;border:1px solid #f0d48a;border-radius:12px}
      ol{padding-left:22px}li{margin:7px 0}code{overflow-wrap:anywhere;background:#f2f5fa;padding:2px 5px;border-radius:5px}
      a{color:#17427a;font-weight:700}small{color:#687992}
    </style></head><body><main><h1>ChoreHive needs a database upgrade</h1>
      <p class="notice">The current database is missing the worker marketplace-approval schema, so ChoreHive cannot safely load worker listings or booking workflows. No database changes were made automatically.</p>
      <p>For an existing installation, back up the database, then check <code>README.md</code> and apply only the migrations your database has not already received, in the listed order:</p>
      <ol><li><code>migration_marketplace_chat.sql</code></li><li><code>migration_chat_message_delete.sql</code></li><li><code>migration_payments_maps.sql</code></li><li><code>migration_paymongo.sql</code></li><li><code>migration_new_features.sql</code></li><li><code>migration_user_city.sql</code></li></ol>
      <p>For a fresh installation, import the current <code>db.sql</code> instead. Do not import it over an existing database.</p>
      <p><small>After upgrading, an administrator must explicitly review and approve worker listings.</small></p>
      <p><a href="' . e($base) . '/">Return to ChoreHive</a></p></main></body></html>';
    exit;
}
function require_role($role) {
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        // App-root-relative redirect works from any subfolder
        $root = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        // strip the role subfolder (e.g. /client, /worker, /admin)
        $root = preg_replace('~/?(client|worker|admin)$~', '', $root);
        header('Location: ' . $root . '/login.php');
        exit;
    }
    require_marketplace_schema();
    // Authenticated worker page views refresh the marketplace presence heartbeat.
    // This keeps the availability indicator useful outside the chat screen too.
    if ($role === 'worker' && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $presence = $GLOBALS['conn']->prepare('INSERT INTO worker_presence (worker_id,last_seen) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE last_seen=NOW()');
        if ($presence) {
            $presence->bind_param('i', $u['id']);
            $presence->execute();
            $presence->close();
        }
    }
    return $u;
}
function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return; }
    if (!empty($_SESSION['flash'])) { [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']);
        echo "<div class='flash $t'>" . e($m) . "</div>"; }
}
function status_class($s) {
    return ['pending'=>'warn','assigned'=>'info','in_progress'=>'info',
            'awaiting_verification'=>'warn','completed'=>'ok','cancelled'=>'err'][$s] ?? '';
}

/* ---------- Cleanup / deletion helpers ---------- */
// A booking is "finished" once it is either completed or cancelled.
function booking_is_finished($status) {
    return in_array($status, ['completed', 'cancelled'], true);
}
// The checklist is frozen once the client is verifying/paying, so the record stays trustworthy.
function checklist_is_editable($status) {
    return !in_array($status, ['awaiting_verification', 'completed'], true);
}
/**
 * Permanently remove a booking. MySQL cascades to checklist_items,
 * booking_photos and feedback; we also unlink the photo files from disk
 * so uploads/ doesn't fill up with orphans.
 */
function delete_booking_record($booking_id) {
    global $conn;
    $booking_id = (int)$booking_id;
    $res = $conn->query("SELECT filepath FROM booking_photos WHERE booking_id=$booking_id");
    $freed = 0;
    while ($p = $res->fetch_assoc()) {
        if (media_delete($p['filepath'])) $freed++;
    }
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id=?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    return [$stmt->affected_rows, $freed];
}
/** Delete every finished (completed/cancelled) booking belonging to a user. */
function purge_user_history($user_id, $column) {
    global $conn;
    if (!in_array($column, ['client_id', 'worker_id'], true)) return [0, 0];
    $ids = [];
    $res = $conn->query("SELECT id FROM bookings WHERE $column=" . (int)$user_id .
                         " AND status IN ('completed','cancelled')");
    while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
    $rows = $freed = 0;
    foreach ($ids as $bid) {
        [$n, $f] = delete_booking_record($bid);
        $rows += $n; $freed += $f;
    }
    return [$rows, $freed];
}

/* ---------- Profile / account helpers ---------- */
/**
 * Get the next available users.id for a specific role (reusing freed IDs when
 * one is available).
 *
 * IMPORTANT: users.id is ONE shared primary key covering every role, while
 * role_id_counters is per role. Two roles can therefore point at the same
 * "next" number, so each candidate is verified against the whole users table
 * before being handed out. Without that check, registering a client can fail
 * with "Duplicate entry" simply because a worker already owns that id.
 */
function get_next_role_id($conn, $role) {
    global $conn;
    if (!in_array($role, ['client', 'worker', 'admin'], true)) {
        return null;
    }

    $isTaken = static function (int $id) use ($conn): bool {
        $s = $conn->prepare('SELECT 1 FROM users WHERE id=? LIMIT 1');
        $s->bind_param('i', $id);
        $s->execute();
        $taken = (bool)$s->get_result()->fetch_row();
        $s->close();
        return $taken;
    };

    $dropFreed = static function (string $role, int $id) use ($conn): void {
        $d = $conn->prepare('DELETE FROM role_id_freed WHERE role=? AND freed_id=?');
        $d->bind_param('si', $role, $id);
        $d->execute();
        $d->close();
    };

    // 1. Reuse an ID freed by deleting an account of this role.
    $stmt = $conn->prepare("SELECT freed_id FROM role_id_freed WHERE role=? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $freed_id = (int)$row['freed_id'];
        $dropFreed($role, $freed_id);
        if (!$isTaken($freed_id)) {
            return $freed_id;
        }
        // stale entry - already used by another role, fall through to the counter
    }

    // 2. Take the next counter value.
    $stmt = $conn->prepare("SELECT next_id FROM role_id_counters WHERE role=?");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $next_id = (int)$row['next_id'];
    } else {
        // First use of this role: start above the highest ID already used.
        $safeRole = $conn->real_escape_string($role);
        $maxRow = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM users WHERE role = '$safeRole'")->fetch_assoc();
        $next_id = (int)($maxRow['next_id'] ?? 1);
    }

    // 3. Skip any ID already used by ANY role.
    $guard = 0;
    while ($isTaken($next_id) && $guard++ < 100000) {
        $next_id++;
    }

    // 4. Persist the following free number.
    $next_free = $next_id + 1;
    $stmt = $conn->prepare("INSERT INTO role_id_counters (role, next_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE next_id = GREATEST(next_id, VALUES(next_id))");
    $stmt->bind_param('si', $role, $next_free);
    $stmt->execute();
    $stmt->close();

    return $next_id;
}

/** Re-read the signed-in user and refresh the session snapshot after an update. */
function refresh_session_user($conn) {
    $id = (int)($_SESSION['user']['id'] ?? 0);
    if (!$id) return null;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if ($u) $_SESSION['user'] = $u;
    return $u;
}
function avatar_file($user) {
    $f = $user['avatar'] ?? '';
    return (!empty($f) && media_exists($f)) ? basename($f) : '';
}
function avatar_url($user, $base) {
    $f = avatar_file($user);
    return $f ? media_url($f, $base) : '';
}
function media_url($filename, $base) {
    $filename = basename((string)$filename);
    if ($filename === '') return '';
    if (MEDIA_STORAGE === 'database') {
        return rtrim($base, '/') . '/media.php?file=' . rawurlencode($filename);
    }
    return rtrim($base, '/') . '/uploads/' . rawurlencode($filename);
}
function media_exists($filename) {
    global $conn;
    $filename = basename((string)$filename);
    if ($filename === '') return false;
    if (MEDIA_STORAGE === 'database') {
        $stmt = $conn->prepare('SELECT 1 FROM media_files WHERE filename=?');
        $stmt->bind_param('s', $filename);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }
    return is_file(UPLOAD_DIR . '/' . $filename);
}
function media_store($filename, $temporaryPath, $mimeType, $visibility) {
    global $conn;
    $filename = basename((string)$filename);
    if (!in_array($visibility, ['public', 'private'], true)) {
        throw new InvalidArgumentException('Invalid media visibility.');
    }
    if (MEDIA_STORAGE === 'database') {
        $data = file_get_contents($temporaryPath);
        if ($data === false) fail('Could not read the uploaded picture.');
        $stmt = $conn->prepare('INSERT INTO media_files (filename, mime_type, file_data, visibility) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $filename, $mimeType, $data, $visibility);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) fail('Could not save the uploaded picture.');
        return true;
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        fail('Could not prepare image storage.');
    }
    if (!move_uploaded_file($temporaryPath, UPLOAD_DIR . '/' . $filename)) {
        fail('Could not save the uploaded picture.');
    }
    return true;
}
function media_read($filename, $allowPrivate = false) {
    global $conn;
    $filename = basename((string)$filename);
    if ($filename === '') return null;
    if (MEDIA_STORAGE === 'database') {
        $stmt = $conn->prepare(
            'SELECT mime_type, file_data FROM media_files WHERE filename=?' .
            ($allowPrivate ? '' : " AND visibility='public'")
        );
        $stmt->bind_param('s', $filename);
        $stmt->execute();
        $media = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $media ?: null;
    }
    if (!$allowPrivate) {
        $stmt = $conn->prepare(
            'SELECT 1 FROM users WHERE avatar=? UNION ALL SELECT 1 FROM worker_portfolio WHERE image=? LIMIT 1'
        );
        $stmt->bind_param('ss', $filename, $filename);
        $stmt->execute();
        $public = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$public) return null;
    }
    $path = UPLOAD_DIR . '/' . $filename;
    if (!is_file($path)) return null;
    $data = file_get_contents($path);
    $mimeType = $data === false ? false : (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    if ($data === false || !is_string($mimeType)) return null;
    return ['mime_type' => $mimeType, 'file_data' => $data];
}
function media_delete($filename) {
    global $conn;
    $filename = basename((string)$filename);
    if ($filename === '') return false;
    if (MEDIA_STORAGE === 'database') {
        $stmt = $conn->prepare('DELETE FROM media_files WHERE filename=?');
        $stmt->bind_param('s', $filename);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();
        return $deleted;
    }
    $path = UPLOAD_DIR . '/' . $filename;
    return is_file($path) && @unlink($path);
}
function initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out !== '' ? $out : '?';
}
/** Store a new profile picture; returns the stored filename or null when nothing was uploaded. */
function handle_avatar_upload($field, $user_id, $conn) {
    // error === 0 means SUCCESS (UPLOAD_ERR_OK); empty() would be true and break it.
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload failed (error code ' . $f['error'] . ').');
    if ($f['size'] === 0) fail('The selected file is empty.');
    if ($f['size'] > 2 * 1024 * 1024) fail('Profile picture is too large (max 2 MB).');
    $info = @getimagesize($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($allowed[$info['mime']])) fail('Only JPG, PNG or WebP images are allowed.');
    if ($info[0] > 4000 || $info[1] > 4000) fail('That image is too large (max 4000x4000).');
    $name = 'av' . (int)$user_id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$info['mime']];
    media_store($name, $f['tmp_name'], $info['mime'], 'public');
    return $name;
}
function handle_portfolio_upload($field, $worker_id) {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload failed (error code ' . $f['error'] . ').');
    if ($f['size'] === 0 || $f['size'] > UPLOAD_MAX_BYTES) fail('Portfolio photo must be between 1 byte and 3 MB.');
    $info = @getimagesize($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($allowed[$info['mime']])) fail('Only JPG, PNG or WebP images are allowed.');
    $name = 'pf' . (int)$worker_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
    media_store($name, $f['tmp_name'], $info['mime'], 'public');
    return $name;
}
function delete_avatar($conn, $user_id) {
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && !empty($r['avatar'])) {
        media_delete($r['avatar']);
    }
    $stmt = $conn->prepare("UPDATE users SET avatar=NULL WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
function update_profile($user_id, $name, $phone, $address, $city, $bio) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, address=?, city=?, bio=? WHERE id=?");
    $stmt->bind_param('sssssi', $name, $phone, $address, $city, $bio, $user_id);
    $stmt->execute();
}

/* ---------- Location matching & marketplace ranking ---------- */
/** Normalise a city / municipality name for case- and space-insensitive comparison. */
function normalize_city($s) {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)));
}
/** True when two people share the same city; blank values never match. */
function same_city($a, $b) {
    $a = normalize_city($a);
    $b = normalize_city($b);
    return $a !== '' && $a === $b;
}
/**
 * Marketplace ranking score for a worker row (higher = better match).
 * Blends customer rating, experience, earned badges and how often they are
 * hired, so the dashboard can showcase the strongest cleaners first.
 */
function worker_score(array $w) {
    $rating  = (float)($w['rating'] ?? 0);
    $jobs    = (int)($w['completed_jobs'] ?? 0);
    $badges  = (int)($w['badge_count'] ?? 0);
    $reviews = (int)($w['review_count'] ?? 0);
    return round($rating * 2 + min($jobs, 100) * 0.15 + min($badges, 10) * 0.4 + min($reviews, 50) * 0.05, 3);
}
function change_password($user, $current, $new, $confirm) {
    global $conn;
    if (!password_verify((string)$current, $user['password'])) fail('Your current password is incorrect.');
    if (strlen((string)$new) < 8) fail('New password must be at least 8 characters.');
    if (strlen((string)$new) > 72) fail('New password is too long.');
    if ($new !== $confirm) fail('The new passwords do not match.');
    if ($current === $new) fail('The new password must be different from your current one.');
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param('si', $hash, $user['id']);
    $stmt->execute();
    audit_log('password_changed', $user['name'] . ' changed their password');
    notify_and_email($user['id'], 'Your ChoreHive password was just changed. If this wasn\'t you, contact support immediately.', 'Password changed');
}

/* ---------- Notifications & email ---------- */
function notify_and_email($user_id, $message, $subject = null, $actionUrl = null) {
    global $conn;
    $actionUrl = is_string($actionUrl) && preg_match('~^(client|worker|admin)/[a-z0-9_-]+\.php(?:\?[a-z0-9_=&%-]*)?$|^chat\.php(?:\?[a-z0-9_=&%-]*)?$~i', $actionUrl)
        ? $actionUrl
        : null;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id,message,action_url) VALUES (?,?,?)");
    $stmt->bind_param('iss', $user_id, $message, $actionUrl);
    $stmt->execute();
    $emailStmt = $conn->prepare('SELECT email FROM users WHERE id=?');
    $emailStmt->bind_param('i', $user_id);
    $emailStmt->execute();
    $row = $emailStmt->get_result()->fetch_assoc();
    if (!empty($row['email'])) {
        send_app_email($row['email'], $subject ?? 'ChoreHive Notification', $message);
    }
}

/* ---------- Audit log ---------- */
function audit_log($action, $details = '') {
    global $conn;
    $u = current_user();
    $uid = $u['id'] ?? null;
    $who = $u['name'] ?? 'guest';
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, actor_name, action, details) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $uid, $who, $action, $details);
    $stmt->execute();
}

/* ---------- CSRF protection ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}
function verify_csrf() {
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        die('Invalid CSRF token. Go back and try again.');
    }
}

/* ---------- Server-side input validation ---------- */
function fail($msg) {
    flash($msg, 'error');
    $back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $back);
    exit;
}
function v_str($key, $max = 255, $required = true, $label = 'Field') {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($required && $v === '') fail("$label is required.");
    if (mb_strlen($v) > $max) fail("$label must be at most $max characters.");
    return $v;
}
function v_email($key = 'email') {
    $v = trim((string)($_POST[$key] ?? ''));
    if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 120) fail('Please enter a valid email address.');
    return strtolower($v);
}
function v_datetime($key = 'schedule') {
    $v = trim((string)($_POST[$key] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('Y-m-d H:i:s', $v);
    if (!$d) fail('Please choose a valid schedule.');
    if ($d < new DateTime('today 00:00:00')) fail('Schedule cannot be in the past — please pick today or a future date.');
    return $d->format('Y-m-d H:i:s');
}
/** Server-rendered values for HTML min attributes, so past dates are blocked even without JS. */
function min_datetime_attr() { return date('Y-m-d\TH:i'); }
function min_date_attr() { return date('Y-m-d'); }
function v_money($key, $label = 'Amount') {
    $v = filter_var($_POST[$key] ?? '', FILTER_VALIDATE_FLOAT);
    if ($v === false || $v < 0 || $v > 1000000) fail("$label must be a valid amount.");
    return round($v, 2);
}
function v_int($key, $min = 1, $max = PHP_INT_MAX, $label = 'Value') {
    $v = filter_var($_POST[$key] ?? '', FILTER_VALIDATE_INT);
    if ($v === false || $v < $min || $v > $max) fail("$label is invalid.");
    return $v;
}

/* ---------- Hardened image upload ---------- */
function handle_photo_upload($field, $booking_id, $stage, $conn) {
    // NOTE: don't use empty() here — a successful upload has error === 0 (UPLOAD_ERR_OK),
    // and empty(0) is true, which would silently discard every valid upload.
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return;
    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload failed (error code ' . $f['error'] . ').');
    if ($f['size'] > UPLOAD_MAX_BYTES) fail('Photo is too large. Maximum size is 3 MB.');
    if ($f['size'] === 0) fail('Uploaded file is empty.');

    // Real MIME check from file content, not the client-supplied name/type
    $info = @getimagesize($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($info === false || !isset($allowed[$info['mime']])) fail('Only JPG, PNG, or WebP images are allowed.');

    $ext  = $allowed[$info['mime']];
    $name = 'b' . (int)$booking_id . '_' . preg_replace('/[^a-z]/', '', $stage) . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    media_store($name, $f['tmp_name'], $info['mime'], 'private');

    $stmt = $conn->prepare("INSERT INTO booking_photos (booking_id,stage,filepath) VALUES (?,?,?)");
    $bid = (int)$booking_id;
    $stmt->bind_param('iss', $bid, $stage, $name);
    $stmt->execute();
}