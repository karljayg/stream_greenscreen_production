<?php
/**
 * Copy to config.local.php and adjust if needed.
 * Defaults in config.php match the local mathison-mysql Docker container.
 */
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'mathison',
    'user' => 'root',
    'pass' => 'password',
    'charset' => 'utf8mb4',

    // Required for mathison/api/v1.php (Bearer token / ?token=).
    // Generate something long and random, e.g. openssl rand -hex 24
    'api_token' => 'change-me-to-a-long-random-secret',
];
