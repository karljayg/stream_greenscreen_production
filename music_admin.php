<?php
// Standalone Music Admin window.
// Opened in a separate browser window from the stream tool so the editor modal
// never overlays / interferes with the live production view. Reuses the same
// js/music-admin.js editor and save_music_config.php / upload_music.php backends.
$pathPrefix = '';
require_once __DIR__ . '/partials/auth-gate.php'; // session_start, login gate, $currentUser
require_once __DIR__ . '/asset_version.php';       // $v

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/partials/music-config.php'; // $safeUser, $moodSongs, $sceneMoodMap, $sceneStages, $musicFiles
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Music Admin</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/smoothness/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>
    <style>
        html, body { height: 100%; margin: 0; }
        body { background: #f2f4f8; font-family: 'Segoe UI', system-ui, sans-serif; }
        /* The editor renders inside its own overlay; in this dedicated window we
           drop the centered-modal look and let it fill the whole window. */
        #mx-admin-overlay {
            position: static !important;
            background: #f2f4f8 !important;
            padding: 0 !important;
            display: block !important;
            width: 100% !important;
            height: 100vh !important;
            overflow: hidden !important;
        }
        #mx-admin-modal {
            width: 100% !important;
            max-width: 100% !important;
            height: 100vh !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        /* Let the scrollable body grow to fill the window instead of 62vh. */
        .mx-body { max-height: none !important; flex: 1 1 auto !important; }
    </style>
</head>

<body>
    <script>
        window.CURRENT_USER    = <?php echo json_encode($_SESSION['username']); ?>;
        window.MX_TRACKS       = <?php echo json_encode($moodSongs,    JSON_UNESCAPED_SLASHES); ?>;
        window.MX_SCENE_MAP    = <?php echo json_encode($sceneMoodMap, JSON_UNESCAPED_SLASHES); ?>;
        window.MX_SCENE_STAGES = <?php echo json_encode($sceneStages,  JSON_UNESCAPED_SLASHES); ?>;
        window.MX_MUSIC_FILES  = <?php echo json_encode($musicFiles,   JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="js/music-admin.js?v=<?php echo $v; ?>"></script>
    <script>
        (function () {
            function start() {
                if (typeof window.mxAdminOpen !== 'function') { setTimeout(start, 30); return; }
                window.mxAdminOpen();

                var overlay = document.getElementById('mx-admin-overlay');
                if (!overlay) return;

                // This is a dedicated window: clicking the X (which hides the
                // overlay) should close the window instead of leaving a blank page.
                var obs = new MutationObserver(function () {
                    if (overlay.style.display === 'none') { try { window.close(); } catch (e) {} }
                });
                obs.observe(overlay, { attributes: true, attributeFilter: ['style'] });

                // "Apply" only updates the live in-memory deck, which doesn't exist
                // in this standalone window — hide it so it isn't misleading. Use
                // "Save to Server" / "Promote to Global" to persist changes.
                var applyBtn = document.getElementById('mx-admin-apply');
                if (applyBtn) applyBtn.style.display = 'none';
            }
            if (document.readyState === 'complete' || document.readyState === 'interactive') start();
            else document.addEventListener('DOMContentLoaded', start);
        })();
    </script>
</body>

</html>
