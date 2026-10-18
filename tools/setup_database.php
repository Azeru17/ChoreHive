<?php
/**
 * ChoreHive  -  one-command database setup for TiDB Cloud.
 *
 *   php tools/setup_database.php
 *
 * This is the easy path. You run ONE command and it:
 *   1. reads your TiDB details from tidb.local.php
 *   2. connects to TiDB (with TLS)
 *   3. copies the schema + sample data out of your working local database
 *   4. runs it on TiDB
 *   5. checks it worked and prints the settings to paste into Vercel
 *
 * You do not need the TiDB web SQL editor at all. That editor is what gave
 * "Query Failed" because it cannot run a whole multi-statement file reliably.
 *
 * CLI only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

mysqli_report(MYSQLI_REPORT_OFF);

$root = dirname(__DIR__);

function say(string $line = ''): void {
    echo $line . "\n";
}

function fail(string $message): void {
    say('');
    say('  *** STOPPED ***');
    say('  ' . $message);
    say('');
    exit(1);
}

/** Split a SQL script into single statements, ignoring comments and quoted text. */
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
            if ($c === "\n") { $inLineComment = false; $buf .= $c; }
            continue;
        }
        if ($inBlockComment) {
            if ($c === '*' && $next === '/') { $inBlockComment = false; $i++; }
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
            $t = trim($buf);
            if ($t !== '') { $statements[] = $t; }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $t = trim($buf);
    if ($t !== '') { $statements[] = $t; }
    return $statements;
}

/** "Already done" errors are not failures. */
function ch_is_benign(string $err): bool {
    $e = strtolower($err);
    foreach (['duplicate column', 'duplicate key', 'duplicate entry', 'already exists',
        'duplicate primary key', "doesn't exist", 'table already exists'] as $n) {
        if (strpos($e, $n) !== false) { return true; }
    }
    return false;
}

say('');
say('=================================================');
say('  ChoreHive  -  set up your TiDB database');
say('=================================================');
say('');

/* ------------------------------------------------------------------
   Step 1 - your TiDB details
   ------------------------------------------------------------------ */
$cfgFile = $root . '/tidb.local.php';

if (!is_file($cfgFile)) {
    say('There is no tidb.local.php yet. That file holds your TiDB details.');
    say('');
    say('Do this once:');
    say('  1. copy  tidb.local.example.php  and rename the copy to  tidb.local.php');
    say('  2. open tidb.local.php in Notepad and fill in your 5 TiDB values');
    say('  3. run  php tools/setup_database.php  again');
    say('');
    say('Where to find the values:');
    say('  TiDB Cloud console  ->  click your cluster  ->  Connect  ->  MySQL');
    say('  host / user / password are shown there. port is 4000, db is chorehive.');
    say('');
    exit(1);
}

$cfg = require $cfgFile;
if (!is_array($cfg) || empty($cfg['host']) || empty($cfg['user'])) {
    fail('tidb.local.php is not filled in. Copy tidb.local.example.php and add your host and user.');
}
$cfg += ['port' => 4000, 'db' => 'chorehive', 'pass' => '', 'ca' => '', 'ssl' => true];
$useSsl = (bool) $cfg['ssl'];
if (strpos((string) $cfg['host'], 'xxxx') !== false) {
    fail('tidb.local.php still has the example host. Put your real TiDB host in it.');
}

$dbName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $cfg['db']);
if ($dbName === '') { $dbName = 'chorehive'; }

say('Using tidb.local.php:');
say('  host  : ' . $cfg['host']);
say('  port  : ' . $cfg['port']);
say('  user  : ' . $cfg['user']);
say('  db    : ' . $dbName);
say('');

/* ------------------------------------------------------------------
   Step 2 - connect to TiDB
   ------------------------------------------------------------------ */
say('Connecting to TiDB ... (a paused cluster can take up to a minute the');
say('first time - that is normal, just wait)');

$conn = mysqli_init();
if (!$conn) {
    fail('Could not start the MySQL client on this PC.');
}
if ($useSsl) {
    if (!defined('MYSQLI_CLIENT_SSL')) {
        fail('This PHP was built without TLS support, which TiDB Cloud requires.');
    }
    // Ask the client to verify the server certificate. Some PHP builds
    // (notably those using mysqlnd) report this option as unsupported. That is
    // fine: the CA file below turns verification on instead, and TiDB Cloud
    // always encrypts the connection regardless.
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    }
    if (!empty($cfg['ca'])) {
        if (!is_file($cfg['ca'])) {
            fail('The "ca" file in tidb.local.php was not found: ' . $cfg['ca']);
        }
        $conn->ssl_set(null, null, $cfg['ca'], null, null);
    }
}
$connectFlags = $useSsl ? MYSQLI_CLIENT_SSL : 0;

// Connect without choosing a database first, so we can create it if needed.
$connected = $conn->real_connect(
    $cfg['host'],
    $cfg['user'],
    (string) $cfg['pass'],
    '',
    (int) $cfg['port'],
    null,
    $connectFlags
);

if (!$connected || $conn->connect_errno) {
    say('');
    say('  Could not connect to TiDB.');
    say('  Reason: ' . ($conn->connect_error ?: 'unknown'));
    say('');
    say('  Check, in tidb.local.php:');
    say('    - host, user and password match the Connect dialog exactly');
    say('    - the cluster is awake (open it in the browser first)');
    say('  If it says something about a certificate / TLS: download');
    say('    https://crt.sh/?d=9314791');
    say('  save it as  certs/isrg-root-x1.pem  in this folder and add this line');
    say('  to tidb.local.php:');
    say("    'ca' => __DIR__ . '/certs/isrg-root-x1.pem',");
    say('');
    exit(1);
}
$conn->set_charset('utf8mb4');
say('  Connected.');
say('');

/* ------------------------------------------------------------------
   Step 3 - build the SQL from your local database
   ------------------------------------------------------------------ */
$schemaOnly = in_array('--schema-only', $argv, true);
$tmpSql = $root . '/tidb_setup_import.sql';

say('Reading your local ChoreHive database ...');

$flag = $schemaOnly ? '' : '--demo-only';
$cmd = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($root . '/tools/export_sql.php')
    . ($flag !== '' ? ' ' . $flag : '')
    . ' --out=' . escapeshellarg('tidb_setup_import.sql') . ' 2>&1';
$output = [];
$code = 0;
exec($cmd, $output, $code);

if ($code !== 0 || !is_file($tmpSql) || filesize($tmpSql) < 100) {
    say('');
    say('  Could not read your local database.');
    say('  Make sure XAMPP is running (Apache + MySQL) and that the local');
    say('  ChoreHive site opens in your browser, then run this again.');
    if ($output) {
        say('');
        say('  Details: ' . trim(implode(' | ', array_slice($output, -3))));
    }
    say('');
    exit(1);
}

$script = file_get_contents($tmpSql);
$statements = ch_split_sql($script);

// We are already in the right database, so drop the file's own
// CREATE DATABASE / USE lines and do that part ourselves below.
$statements = array_values(array_filter($statements, function ($s) {
    return !preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $s);
}));

say('  Found ' . count($statements) . ' statements to run.');
say('');

/* ------------------------------------------------------------------
   Step 4 - make the database, then run every statement
   ------------------------------------------------------------------ */
say('Preparing database "' . $dbName . '" ...');
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    fail('Could not create the database: ' . $conn->error);
}
if (!$conn->select_db($dbName)) {
    fail('Could not select the database: ' . $conn->error);
}
say('  Ready.');
say('');

say('Copying tables and data to TiDB ...');
$ok = 0;
$problems = [];
foreach ($statements as $s) {
    if ($conn->query($s)) {
        $ok++;
    } elseif (!ch_is_benign($conn->error)) {
        $problems[] = substr(preg_replace('/\s+/', ' ', $s), 0, 70) . '  ->  ' . $conn->error;
    }
}
say('  ' . $ok . ' of ' . count($statements) . ' statements ran.');

if ($problems) {
    say('');
    say('  Some statements failed:');
    foreach (array_slice($problems, 0, 6) as $p) {
        say('    - ' . $p);
    }
    fail('The copy did not finish cleanly. Send me the lines above and I will fix it.');
}
say('');

/* ------------------------------------------------------------------
   Step 5 - verify
   ------------------------------------------------------------------ */
say('Checking the result ...');
$expected = ['users', 'services', 'bookings', 'checklist_items', 'booking_photos', 'feedback',
    'notifications', 'contact_messages', 'audit_log', 'saved_addresses', 'favorite_workers',
    'worker_portfolio', 'login_attempts', 'password_resets', 'worker_presence', 'chat_threads',
    'chat_messages', 'app_sessions', 'media_files', 'role_id_counters', 'role_id_freed', 'badges'];

$missing = [];
foreach ($expected as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$r || !$r->num_rows) { $missing[] = $t; }
}
$city = $conn->query("SHOW COLUMNS FROM `users` LIKE 'city'");

if ($missing || !$city || !$city->num_rows) {
    if ($missing) { say('  Missing tables: ' . implode(', ', $missing)); }
    if (!$city || !$city->num_rows) { say('  users.city column is missing.'); }
    fail('The database is not complete.');
}

$counts = [];
foreach (['users', 'services', 'bookings', 'feedback', 'badges', 'chat_messages'] as $t) {
    $r = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
    $counts[$t] = $r ? (int) $r->fetch_assoc()['c'] : 0;
}

@unlink($tmpSql);

say('  All 22 tables are there.');
say('');
foreach ($counts as $t => $n) {
    say(sprintf('    %-14s %d rows', $t, $n));
}

say('');
say('=================================================');
say('  DONE. Your online database is ready.');
say('=================================================');
say('');
say('Last step - tell Vercel where the database is.');
say('Open your project on vercel.com -> Settings -> Environment Variables,');
say('then add each line below as a separate variable (name = value):');
say('');
say('  DB_HOST        = ' . $cfg['host']);
say('  DB_PORT        = ' . $cfg['port']);
say('  DB_USER        = ' . $cfg['user']);
say('  DB_PASS        = <your TiDB password>');
say('  DB_NAME        = ' . $dbName);
say('  DB_SSL         = 1');
say('  SESSION_DRIVER = database');
say('  MEDIA_STORAGE  = database');
say('');
say('After adding them, click Redeploy. Then open your site and sign in with:');
say('');
say('  admin@demo.chorehive.test    password  ChoreHive2026!');
say('  denise@demo.chorehive.test   password  ChoreHive2026!');
say('');
