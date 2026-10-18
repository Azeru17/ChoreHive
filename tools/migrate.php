<?php
/**
 * ChoreHive CLI migration runner.
 *
 *   php tools/migrate.php                          # apply every migration, in order
 *   php tools/migrate.php migration_user_city.sql
 *   php tools/migrate.php --list
 *   php tools/migrate.php --check                  # verify schema only, change nothing
 *
 * Statements are run one at a time and individually error-tolerant, so a
 * migration that is already applied reports "skipped" instead of aborting the
 * run. That matters on a fresh cloud database where several bundled migrations
 * overlap with the base schema.
 *
 * CLI only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../config.php';

// PHP 8.1+ defaults to MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT, which throws on
// recoverable conditions such as "Duplicate column". We want errors as values so
// each statement can be judged individually.
mysqli_report(MYSQLI_REPORT_OFF);

$root = dirname(__DIR__);
$all = glob($root . '/migration_*.sql') ?: [];
sort($all);

function short(string $path, string $root): string {
    return str_replace($root . DIRECTORY_SEPARATOR, '', $path);
}

/** Split a SQL script into individual statements, ignoring comments and quoted text. */
function ch_split_sql(string $sql): array {
    $statements = [];
    $buf = '';
    $len = strlen($sql);
    $inSingle = $inDouble = $inBacktick = false;
    $inLineComment = $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($c === "\n") {
                $inLineComment = false;
                $buf .= $c;
            }
            continue;
        }
        if ($inBlockComment) {
            if ($c === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($c === '-' && $next === '-') { $inLineComment = true; $i++; continue; }
            if ($c === '#') { $inLineComment = true; continue; }
            if ($c === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
        }

        if ($c === '\\' && ($inSingle || $inDouble)) { $buf .= $c . $next; $i++; continue; }
        if ($c === "'" && !$inDouble && !$inBacktick) { $inSingle = !$inSingle; }
        elseif ($c === '"' && !$inSingle && !$inBacktick) { $inDouble = !$inDouble; }
        elseif ($c === '`' && !$inSingle && !$inDouble) { $inBacktick = !$inBacktick; }

        if ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $trimmed = trim($buf);
            if ($trimmed !== '') { $statements[] = $trimmed; }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $trimmed = trim($buf);
    if ($trimmed !== '') { $statements[] = $trimmed; }
    return $statements;
}

/** Errors that mean "this was already done" rather than "this failed". */
function ch_is_benign(string $err): bool {
    $e = strtolower($err);
    foreach ([
        'duplicate column',
        'duplicate key',
        'duplicate entry',
        'already exists',
        'duplicate primary key',
        "doesn't exist",   // DROP IF EXISTS on a fresh DB
        'table already exists',
    ] as $needle) {
        if (strpos($e, $needle) !== false) { return true; }
    }
    return false;
}

$checkOnly = in_array('--check', $argv, true);

if (in_array('--list', $argv, true)) {
    echo "Available migrations:\n";
    foreach ($all as $f) { echo '  - ' . short($f, $root) . "\n"; }
    exit(0);
}

$targets = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '' || $arg[0] === '-') { continue; }
    $path = strpos($arg, '/') === false ? $root . '/' . $arg : $arg;
    if (!is_file($path)) {
        fwrite(STDERR, "Not found: {$arg}\n");
        exit(1);
    }
    $targets[] = $path;
}
if (!$targets) { $targets = $all; }

echo 'Database: ' . DB_NAME . ' @ ' . DB_HOST . "\n";
if ($checkOnly) { echo "Mode: CHECK ONLY (no changes)\n"; }
echo "\n";

$hardFailures = 0;
foreach ($targets as $file) {
    $name = short($file, $root);
    if ($checkOnly) {
        echo "[skip] {$name}\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "[{$name}] FAILED: could not read file\n");
        $hardFailures++;
        continue;
    }
    $okCount = 0;
    $skipped = 0;
    $errors = [];
    foreach (ch_split_sql($sql) as $stmt) {
        if (!$conn->query($stmt)) {
            $err = $conn->error ?: 'unknown error';
            if (ch_is_benign($err)) {
                $skipped++;
            } else {
                $errors[] = $err . '  [' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 90) . ']';
            }
            continue;
        }
        $okCount++;
        while ($conn->more_results() && $conn->next_result()) { /* drain */ }
    }
    if ($errors) {
        echo "[{$name}] {$okCount} applied, {$skipped} skipped, " . count($errors) . " ERROR(S)\n";
        foreach ($errors as $e) { echo "         ! {$e}\n"; }
        $hardFailures++;
    } else {
        echo "[{$name}] {$okCount} applied" . ($skipped ? ", {$skipped} already present" : '') . "\n";
    }
}

echo "\n--- schema check ---\n";
$tables = ['users', 'services', 'badges', 'bookings', 'checklist_items', 'booking_photos',
    'feedback', 'notifications', 'contact_messages', 'audit_log', 'saved_addresses',
    'favorite_workers', 'worker_portfolio', 'login_attempts', 'password_resets',
    'worker_presence', 'chat_threads', 'chat_messages', 'app_sessions', 'media_files',
    'role_id_counters', 'role_id_freed', 'worker_finance_entries'];
$missing = [];
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$r || !$r->num_rows) { $missing[] = $t; }
}
if ($missing) {
    echo "MISSING tables: " . implode(', ', $missing) . "\n";
    $hardFailures++;
} else {
    echo "OK  all " . count($tables) . " expected tables present\n";
}

$cols = [
    'users'    => ['city', 'phone', 'address', 'worker_approved'],
    'bookings' => ['cancel_reason', 'address_lat', 'address_lng', 'payment_status', 'payment_method', 'client_started_ack', 'total_price'],
    'feedback' => ['rating', 'comment'],
    'worker_finance_entries' => ['worker_id', 'booking_id', 'entry_type', 'amount', 'note', 'created_by'],
];
$missingCols = [];
foreach ($cols as $table => $names) {
    foreach ($names as $col) {
        $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$r || !$r->num_rows) { $missingCols[] = "{$table}.{$col}"; }
    }
}
if ($missingCols) {
    echo "MISSING columns: " . implode(', ', $missingCols) . "\n";
    $hardFailures++;
} else {
    echo "OK  all expected columns present\n";
}

$counters = $conn->query("SELECT role, next_id FROM role_id_counters ORDER BY role");
if ($counters) {
    $out = [];
    while ($row = $counters->fetch_assoc()) { $out[] = $row['role'] . '=' . $row['next_id']; }
    echo ($out ? 'OK  role_id_counters: ' . implode(', ', $out) : 'WARN role_id_counters is empty')
        . "\n";
}

echo "\n" . ($hardFailures ? "RESULT: {$hardFailures} problem(s) found\n" : "RESULT: ready\n");
exit($hardFailures ? 1 : 0);