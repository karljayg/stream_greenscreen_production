<?php
/**
 * Mathison DB connection settings.
 * Override by creating config.local.php (see config.local.example.php).
 */
$mathisonDefaults = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'mathison',
    'user' => 'root',
    'pass' => 'password',
    'charset' => 'utf8mb4',
    // Token for mathison/api/v1.php (external tools). Empty = API disabled.
    // Override in config.local.php — never commit a real token.
    'api_token' => '',
];

$localFile = __DIR__ . '/config.local.php';
if (is_readable($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $mathisonDefaults = array_merge($mathisonDefaults, $local);
    }
}

return $mathisonDefaults;
