<?php
/**
 * Development helper: apply a migration_*.sql file to the configured database.
 *
 *   /_run_migration.php                       -> migration_new_features.sql
 *   /_run_migration.php?f=migration_user_city.sql
 *
 * Blocked from public routing in vercel.json and listed in .vercelignore.
 * Only files matching /^migration_[a-z0-9_]+\.sql$/i are accepted.
 */
require 'config.php';

$requested = isset($_GET['f']) ? (string)$_GET['f'] : 'migration_new_features.sql';

if (!preg_match('/\Amigration_[a-z0-9_]+\.sql\z/i', $requested)) {
    http_response_code(400);
    exit("Refusing to run '" . htmlspecialchars($requested) . "'. Only migration_*.sql files are allowed.\n");
}

$path = __DIR__ . '/' . $requested;
if (!is_file($path)) {
    http_response_code(404);
    exit("Migration file not found: " . htmlspecialchars($requested) . "\n");
}

$sql = file_get_contents($path);
if ($sql === false) {
    http_response_code(500);
    exit("Could not read the migration file.\n");
}

echo "Applying {$requested}\n";

if ($conn->multi_query($sql)) {
    do { /* drain all result sets */ } while ($conn->more_results() && $conn->next_result());
    if ($conn->errno) {
        http_response_code(500);
        exit("ERROR: " . $conn->error . "\n");
    }
    echo "Migration applied OK\n";
} else {
    http_response_code(500);
    exit('ERROR: ' . $conn->error . "\n");
}

// Report on the tables this application depends on.
$checks = [
    'saved_addresses'   => "SHOW TABLES LIKE 'saved_addresses'",
    'favorite_workers'  => "SHOW TABLES LIKE 'favorite_workers'",
    'worker_portfolio'  => "SHOW TABLES LIKE 'worker_portfolio'",
    'role_id_counters'  => "SHOW TABLES LIKE 'role_id_counters'",
    'role_id_freed'     => "SHOW TABLES LIKE 'role_id_freed'",
    'app_sessions'      => "SHOW TABLES LIKE 'app_sessions'",
    'media_files'       => "SHOW TABLES LIKE 'media_files'",
    'contact_messages'  => "SHOW TABLES LIKE 'contact_messages'",
];
foreach ($checks as $label => $sqlCheck) {
    $r = $conn->query($sqlCheck);
    echo "  {$label}: " . ($r && $r->num_rows ? 'present' : 'MISSING') . "\n";
}
$col = $conn->query("SHOW COLUMNS FROM users LIKE 'city'");
echo "  users.city: " . ($col && $col->num_rows ? 'present' : 'MISSING') . "\n";