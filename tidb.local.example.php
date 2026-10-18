<?php
/**
 * Copy this file to tidb.local.php and fill in the 5 values from your
 * TiDB Cloud "Connect" dialog, then run:  php tools/setup_database.php
 *
 * tidb.local.php is gitignored - it holds your database password.
 */

return [
    'host' => 'gateway-xxxx.ap-southeast-1.prod.aws.tidbcloud.com',
    'port' => 4000,
    'user' => 'your_tidb_username',
    'pass' => 'your_tidb_password',
    'db'   => 'chorehive',

    // Usually leave this out. Only add it if setup says the certificate/TLS
    // check failed: download https://crt.sh/?d=9314791 into certs/isrg-root-x1.pem
    // 'ca' => __DIR__ . '/certs/isrg-root-x1.pem',

    // Leave this out for TiDB Cloud, which always needs TLS.
    // 'ssl' => false,
];