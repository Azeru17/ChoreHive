<?php

$projectRoot = realpath(dirname(__DIR__));
$requestedPath = $_GET['__chorehive_path'] ?? '';
unset($_GET['__chorehive_path']);

if (!is_string($requestedPath)) {
    http_response_code(404);
    exit('Not Found');
}

$requestedPath = rawurldecode(str_replace('\\', '/', $requestedPath));
$requestedPath = ltrim($requestedPath, '/');

if (
    $projectRoot === false
    || !preg_match('~\A[a-zA-Z0-9_/-]+\.php\z~D', $requestedPath)
    || preg_match('~\A(?:api|lib|uploads|tools|\.git)(?:/|\z)~i', $requestedPath)
    || in_array(strtolower($requestedPath), [
        'config.php',
        'config.local.php',
        'config.local.example.php',
        'layout.php',
        'footer.php',
        'worker_data.php',
        'paymongo.php',
    ], true)
) {
    http_response_code(404);
    exit('Not Found');
}

$entrypoint = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedPath));
$rootPrefix = $projectRoot . DIRECTORY_SEPARATOR;

if ($entrypoint === false || !is_file($entrypoint) || !str_starts_with($entrypoint, $rootPrefix)) {
    http_response_code(404);
    exit('Not Found');
}

$scriptName = '/' . $requestedPath;
$_SERVER['SCRIPT_NAME'] = $scriptName;
$_SERVER['PHP_SELF'] = $scriptName;
$_SERVER['SCRIPT_FILENAME'] = $entrypoint;

if (!chdir(dirname($entrypoint))) {
    http_response_code(500);
    exit('Unable to initialize the requested page.');
}

require $entrypoint;
