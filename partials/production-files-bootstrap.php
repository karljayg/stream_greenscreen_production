<?php
/**
 * Server-side bootstrap for production_files URLs and optional mood music base.
 * Call after auth + session (uses $_SESSION['username']).
 *
 * Optional defines in config.local.php (before this file is loaded from index):
 *   define('PRODUCTION_FILES_MODE', 'remote'); // or 'local'
 *   define('PRODUCTION_FILES_REMOTE_BASE_URL', 'https://psistorm.com/stream_production/production_files/');
 *   define('MUSIC_FILES_BASE_URL', 'https://example.com/your-app/music'); // optional; overrides auto .../music/ when remote production_files
 *   define('SCENE_ASSETS_BASE_URL', 'https://example.com/stream_production'); // optional; overrides auto app root when remote production_files
 *   define('REMOTE_SITE_ORIGIN', 'https://psistorm.com'); // optional; same host for /fsl/, /stream_production/, etc. (parsed from remoteBaseUrl if omitted)
 *   define('FSL_WEB_BASE_PATH', 'fsl'); // optional URL segment before spider scripts (default: fsl → /fsl/view_spider_chart_…)
 */
$streamPfBootstrap = [
    'mode'            => 'local',
    'remoteBaseUrl'   => 'https://psistorm.com/stream_production/production_files/',
];

$__spfUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['username'] ?? '');
$__spfFile = __DIR__ . '/../data/settings_' . $__spfUser . '.json';
$__spfFb   = __DIR__ . '/../data/stream_production_settings.json';
$__spfPath = ($__spfUser && is_file($__spfFile)) ? $__spfFile : $__spfFb;
if (is_readable($__spfPath)) {
    $__spfRaw = @file_get_contents($__spfPath);
    if ($__spfRaw) {
        $__spfJ = json_decode($__spfRaw, true);
        if (is_array($__spfJ) && isset($__spfJ['productionFiles']) && is_array($__spfJ['productionFiles'])) {
            $streamPfBootstrap = array_merge($streamPfBootstrap, $__spfJ['productionFiles']);
        }
    }
}

if (defined('PRODUCTION_FILES_MODE')) {
    $streamPfBootstrap['mode'] = (PRODUCTION_FILES_MODE === 'remote') ? 'remote' : 'local';
}
if (defined('PRODUCTION_FILES_REMOTE_BASE_URL') && is_string(PRODUCTION_FILES_REMOTE_BASE_URL) && PRODUCTION_FILES_REMOTE_BASE_URL !== '') {
    $streamPfBootstrap['remoteBaseUrl'] = PRODUCTION_FILES_REMOTE_BASE_URL;
}

$pfIsRemote = (($streamPfBootstrap['mode'] ?? '') === 'remote');
$pfRb       = isset($streamPfBootstrap['remoteBaseUrl']) ? rtrim((string) $streamPfBootstrap['remoteBaseUrl'], "/\\ \t") : '';
$pfRoot     = ($pfIsRemote && $pfRb !== '') ? ($pfRb . '/') : '';

// Parent of production_files/ (e.g. https://psistorm.com/stream_production/production_files -> https://psistorm.com/stream_production)
$streamProductionAppRoot = '';
if ($pfIsRemote && $pfRb !== '') {
    $rbNorm = rtrim((string) $pfRb, "/\\ \t");
    if ($rbNorm !== '' && preg_match('#/production_files$#i', $rbNorm)) {
        $parent = preg_replace('#/production_files$#i', '', $rbNorm);
        if ($parent !== '') {
            $streamProductionAppRoot = $parent . '/';
        }
    }
}

$streamPfIconHref = $pfRoot ? ($pfRoot . 'images/favicon.ico') : 'production_files/images/favicon.ico';
$streamPfGifHref  = $pfRoot ? ($pfRoot . 'images/transparent_greenscreen.gif') : 'production_files/images/transparent_greenscreen.gif';

$streamMxMusicPath = 'music/';
if (defined('MUSIC_FILES_BASE_URL') && is_string(MUSIC_FILES_BASE_URL) && MUSIC_FILES_BASE_URL !== '') {
    $streamMxMusicPath = rtrim(MUSIC_FILES_BASE_URL, "/\\ \t") . '/';
} elseif ($streamProductionAppRoot !== '') {
    $streamMxMusicPath = $streamProductionAppRoot . 'music/';
}

// App root for 2026/ overlay videos (POG, ST, schedule mp4s, logos): mirrors local paths like 2026/POG.mp4 -> {app}/2026/POG.mp4
$streamSceneAssetsBase = '';
if (defined('SCENE_ASSETS_BASE_URL') && is_string(SCENE_ASSETS_BASE_URL) && SCENE_ASSETS_BASE_URL !== '') {
    $streamSceneAssetsBase = rtrim(SCENE_ASSETS_BASE_URL, "/\\ \t") . '/';
} elseif ($streamProductionAppRoot !== '') {
    $streamSceneAssetsBase = $streamProductionAppRoot;
}

$streamMxMusicPathLocked = defined('MUSIC_FILES_BASE_URL') && is_string(MUSIC_FILES_BASE_URL) && MUSIC_FILES_BASE_URL !== '';
$streamSceneAssetsBaseLocked = defined('SCENE_ASSETS_BASE_URL') && is_string(SCENE_ASSETS_BASE_URL) && SCENE_ASSETS_BASE_URL !== '';

// One origin for tools on the same machine: /fsl/ spider charts, images, etc. (derive from production_files URL when possible)
$streamRemoteSiteOrigin = '';
if (defined('REMOTE_SITE_ORIGIN') && is_string(REMOTE_SITE_ORIGIN) && REMOTE_SITE_ORIGIN !== '') {
    $streamRemoteSiteOrigin = rtrim((string) REMOTE_SITE_ORIGIN, "/\\ \t");
} elseif ($pfRb !== '') {
    $pu = @parse_url($pfRb);
    if (is_array($pu) && !empty($pu['scheme']) && !empty($pu['host'])) {
        $streamRemoteSiteOrigin = $pu['scheme'] . '://' . $pu['host'] . (isset($pu['port']) ? ':' . (string) $pu['port'] : '');
    }
}
if ($streamRemoteSiteOrigin === '') {
    $streamRemoteSiteOrigin = 'https://psistorm.com';
}

$fslPathSeg = 'fsl';
if (defined('FSL_WEB_BASE_PATH') && is_string(FSL_WEB_BASE_PATH) && FSL_WEB_BASE_PATH !== '') {
    $fslPathSeg = trim((string) FSL_WEB_BASE_PATH, "/\\ \t");
}
$streamFslBaseUrl = $streamRemoteSiteOrigin . '/' . $fslPathSeg;

$streamFslSpiderPlayerUrl   = $streamFslBaseUrl . '/view_spider_chart_player.php';
$streamFslSpiderMatchupUrl  = $streamFslBaseUrl . '/view_spider_chart_player_matchup.php';
$streamFslImagesBase        = $streamFslBaseUrl . '/images/';
