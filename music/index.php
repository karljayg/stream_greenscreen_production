<?php
// Standalone music player — public, no login required.
// Safe to PHP-include from any page on the same server.
//
// The including page may set $mxBase before including this file:
//   $mxBase = '/tools/stream_greenscreen_production/';  // absolute from web root
//   include '/path/to/music/index.php';
//
// When accessed directly (music/index.php), $mxBase is auto-detected.

// Only start a new session if one isn't already active
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../asset_version.php';         // defines $v
require_once __DIR__ . '/../partials/music-config.php'; // defines $moodSongs, $sceneMoodMap, $musicFiles

$currentUser = !empty($_SESSION['username'])
    ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8')
    : null;

// Auto-detect base URL when not provided by the including page.
if (empty($mxBase)) {
    if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        // Direct access (music/index.php is the entry point).
        // SCRIPT_NAME = /some/path/music/index.php  →  base = /some/path/
        $mxBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
    } else {
        // Included from another page; fall back to filesystem-based detection.
        $docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
        $toolRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__))),           '/');
        $mxBase   = rtrim(substr($toolRoot, strlen($docRoot)), '/') . '/';
    }
}
$mxBase = rtrim($mxBase, '/') . '/';  // ensure single trailing slash

if (!defined('MX_PLAYER_INCLUDED')) {
    define('MX_PLAYER_INCLUDED', true);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSL Music Player</title>
    <link rel="icon" href="<?= $mxBase ?>production_files/images/favicon.ico?v=<?= $v ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="<?= $mxBase ?>styles/app.css?v=<?= $v ?>">
    <style>
        body {
            background: #111827;
            margin: 0;
            padding: 20px 12px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .sp-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 340px;
            max-width: 100%;
            margin-bottom: 10px;
        }
        .sp-user {
            font-size: 0.75rem;
            color: #94a3b8;
            flex: 1;
        }
        .sp-logout {
            font-size: 0.72rem;
            padding: 3px 9px;
            background: #1e293b;
            color: #94a3b8;
            border: 1px solid #334155;
            border-radius: 3px;
            cursor: pointer;
        }
        .sp-logout:hover { background: #334155; color: #e2e8f0; }
        .sp-wrap {
            width: 340px;
            max-width: 100%;
        }
        /* Override: player starts expanded in standalone mode */
        .lp-music-grid, .lp-mx-song-row { display: block !important; }
        .lp-mx-toggle-icon { display: none; }
    </style>
</head>
<body>

    <?php if ($currentUser): ?>
    <div class="sp-bar">
        <span class="sp-user">&#9836; <?= $currentUser ?></span>
        <button class="sp-logout" id="sp-logout-btn">Logout</button>
    </div>
    <?php endif; ?>

    <div class="sp-wrap">
        <?php include __DIR__ . '/../partials/music-player-widget.php'; ?>
    </div>

    <script>
        window.MX_MUSIC_PATH  = '<?= $mxBase ?>music/';
        window.MX_STATS_URL   = '<?= $mxBase ?>save_music_stats.php';
        window.MX_HELP_URL    = '<?= $mxBase ?>docs/music-help.php';
        window.MX_TRACKS      = <?= json_encode($moodSongs,    JSON_UNESCAPED_SLASHES) ?>;
        window.MX_SCENE_MAP   = <?= json_encode($sceneMoodMap, JSON_UNESCAPED_SLASHES) ?>;
        window.MX_MUSIC_FILES = <?= json_encode($musicFiles,   JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>
    <script src="<?= $mxBase ?>js/music-player.js?v=<?= $v ?>"></script>

    <?php if ($currentUser): ?>
    <script>
        document.getElementById('sp-logout-btn').addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'logout');
            fetch('<?= $mxBase ?>auth.php', { method: 'POST', body: fd })
                .then(function () { window.location.reload(); })
                .catch(function () { window.location.reload(); });
        });
    </script>
    <?php endif; ?>

</body>
</html>
