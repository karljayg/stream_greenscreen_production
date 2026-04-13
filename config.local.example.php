<?php
/**
 * Copy to config.local.php (gitignored) and uncomment as needed.
 *
 * StreamElements JWT and other secrets stay in your real config.local.php.
 */

// define('SE_JWT', 'paste-token-here');

// When this checkout has no production_files/ media, pull intros from the live tree:
// define('PRODUCTION_FILES_MODE', 'remote');
// define('PRODUCTION_FILES_REMOTE_BASE_URL', 'https://psistorm.com/stream_production/production_files/');

// When music/ is not present locally, point mood playback at a host that mirrors music/.
// If you omit this while PRODUCTION_FILES_REMOTE_BASE_URL ends in .../production_files, the app
// derives .../music/ from the same parent URL automatically.
// define('MUSIC_FILES_BASE_URL', 'https://psistorm.com/stream_greenscreen_production/music');

// Optional: app root for 2026/ (POG, ST, schedule mp4s, logos). If omitted while remote production_files
// URL ends in .../production_files, the app uses the same parent as https://psistorm.com/stream_production/
// so 2026/ mirrors https://psistorm.com/stream_production/2026/ (not production_files/).
// define('SCENE_ASSETS_BASE_URL', 'https://psistorm.com/stream_production');

// Same host for FSL spider charts + scoreboard race icons (/fsl/...). If omitted, origin is parsed from
// PRODUCTION_FILES_REMOTE_BASE_URL / saved productionFiles.remoteBaseUrl, else https://psistorm.com
// define('REMOTE_SITE_ORIGIN', 'https://psistorm.com');
// define('FSL_WEB_BASE_PATH', 'fsl'); // URL path segment (default fsl → /fsl/view_spider_chart_player_matchup.php)
