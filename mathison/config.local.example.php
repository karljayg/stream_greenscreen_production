<?php
/**
 * Copy to config.local.php and adjust if needed.
 * Defaults in config.php match the local mathison-mysql Docker container.
 *
 * Paths are relative to the stream_production app root unless absolute.
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

    // Default is already data/open_matchups under the app — only set this
    // if the Twitch bot shares a different folder.
    // 'open_matchups_data_dir' => 'data/open_matchups',

    // Open Matchups on localhost uses the live server API (public, like /status).
    //   auto   = localhost → remote; production host → local files (default)
    //   local  = always local data/open_matchups
    //   remote = always proxy to open_matchups_remote_base
    // 'open_matchups_data_mode' => 'auto',
    // 'open_matchups_remote_base' => 'https://psistorm.com/stream_production/mathison/open-matchups',

    // Same credentials the Twitch bot uses for DB_API_* / FSL lookups
    'fsl_api_base' => 'https://psistorm.com/api-server/public',
    'fsl_api_key' => 'change-me-to-bot-DB_API_KEY',
];
