<?php
require 'config.php';
$sql = file_get_contents('migration_new_features.sql');
if ($conn->multi_query($sql)) {
    do { } while ($conn->more_results() && $conn->next_result());
    echo "Migration applied OK\n";
    // Verify
    $r = $conn->query("SHOW TABLES LIKE 'saved_addresses'");
    echo "saved_addresses table: " . ($r && $r->num_rows ? "present" : "MISSING") . "\n";
    $r = $conn->query("SHOW TABLES LIKE 'favorite_workers'");
    echo "favorite_workers table: " . ($r && $r->num_rows ? "present" : "MISSING") . "\n";
    $r = $conn->query("SHOW TABLES LIKE 'worker_portfolio'");
    echo "worker_portfolio table: " . ($r && $r->num_rows ? "present" : "MISSING") . "\n";
    $r = $conn->query("SHOW COLUMNS FROM bookings LIKE 'cancel_reason'");
    echo "cancel_reason column: " . ($r && $r->num_rows ? "present" : "MISSING") . "\n";
} else {
    echo 'ERROR: ' . $conn->error . "\n";
}
