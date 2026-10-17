<?php
/**
 * Mathison DB connection settings.
 * Override by creating config.local.php (see config.local.example.php).
 *
 * Paths below are relative to the stream_production app root unless absolute.
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
    // Open Matchups queue/session data (relative to app root)
    'open_matchups_data_dir' => 'data/open_matchups',
    // local | remote | auto (localhost → public remote API, else local files)
    'open_matchups_data_mode' => 'auto',
    // Live server board API (public JSON, same idea as /status — no token)
    'open_matchups_remote_base' => 'https://psistorm.com/stream_production/mathison/open-matchups',
    // Open Matchups: resolve FSL players case-insensitively (same api-server as the bot)
    'fsl_api_base' => 'https://psistorm.com/api-server/public',
    'fsl_api_key' => '', // set in config.local.php (= bot DB_API_KEY)
    'fsl_public_base' => 'https://psistorm.com/fsl',
    // Optional override; empty = auto-find ../fsl or ../../fsl next to the app
    'fsl_rankings_json' => '',
];

$localFile = __DIR__ . '/config.local.php';
if (is_readable($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $mathisonDefaults = array_merge($mathisonDefaults, $local);
    }
}

return $mathisonDefaults;
