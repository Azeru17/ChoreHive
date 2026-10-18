<?php
/**
 * Example TiDB Cloud connection settings for tools/setup_database.php.
 * Copy to tidb.local.php and replace the placeholders. Never commit live credentials.
 */
return [
    'host' => 'your-cluster-host.example.com',
    'port' => 4000,
    'user' => 'your_tidb_user',
    'pass' => 'replace_with_your_password',
    'db'   => 'chorehive',
    'ssl'  => true,
    // Optional absolute path to a CA bundle if PHP cannot use the system trust store.
    'ca'   => '',
];
