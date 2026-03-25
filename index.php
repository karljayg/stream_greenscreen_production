<?php
session_start();
require_once __DIR__ . '/asset_version.php';

// ── Music config save endpoint ───────────────────────────────────────────────
// Saves to data/{which}_{username}.json (user override), never touches global defaults.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['save_music_config'])) {
    if (empty($_SESSION['username'])) { http_response_code(403); echo json_encode(['error'=>'Not authenticated']); exit; }
    $which = $_GET['save_music_config'];
    $allowed = ['mood_songs', 'scene_mood_map'];
    if (!in_array($which, $allowed, true)) { http_response_code(400); echo json_encode(['error'=>'Unknown config']); exit; }
    $body = file_get_contents('php://input');
    $parsed = json_decode($body, true);
    if ($parsed === null) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
    $safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username']);
    $path = __DIR__ . '/data/' . $which . '_' . $safeUser . '.json';
    $written = file_put_contents($path, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo json_encode($written !== false ? ['ok'=>true] : ['error'=>'Write failed']);
    exit;
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// Gate: show login screen if not authenticated
if (empty($_SESSION['username'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Production Tool – Login</title>
    <link rel="icon" href="production_files/images/favicon.ico" type="image/x-icon">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #111;
            color: #eee;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            background: #1c1c1c;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 2.5rem 2rem;
            width: 320px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        }
        .login-box h1 {
            font-size: 1.3rem;
            text-align: center;
            margin-bottom: 1.8rem;
            color: #f0a500;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .login-box label {
            display: block;
            font-size: 0.78rem;
            color: #aaa;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 9px 10px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 4px;
            color: #eee;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            outline: none;
        }
        .login-box input:focus { border-color: #f0a500; }
        .login-box button {
            width: 100%;
            padding: 10px;
            background: #f0a500;
            color: #111;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.4rem;
        }
        .login-box button:hover { background: #ffc72c; }
        #login-error {
            color: #e44;
            font-size: 0.85rem;
            min-height: 1.2em;
            margin-top: 0.8rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Stream Production Tool</h1>
        <label for="l-user">Username</label>
        <input type="text" id="l-user" autocomplete="username" autofocus>
        <label for="l-pass">Password</label>
        <input type="password" id="l-pass" autocomplete="current-password">
        <button id="l-btn">Sign In</button>
        <div id="login-error"></div>
    </div>
    <script>
        function doLogin() {
            var user = document.getElementById('l-user').value.trim();
            var pass = document.getElementById('l-pass').value;
            var err  = document.getElementById('login-error');
            var btn  = document.getElementById('l-btn');
            if (!user || !pass) { err.textContent = 'Enter username and password.'; return; }
            btn.disabled = true;
            btn.textContent = 'Signing in…';
            var fd = new FormData();
            fd.append('action', 'login');
            fd.append('username', user);
            fd.append('password', pass);
            fetch('auth.php', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        err.textContent = res.error || 'Login failed.';
                        btn.disabled = false;
                        btn.textContent = 'Sign In';
                    }
                })
                .catch(function(){ err.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; });
        }
        document.getElementById('l-btn').addEventListener('click', doLogin);
        document.getElementById('l-pass').addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });
        document.getElementById('l-user').addEventListener('keydown', function(e){ if (e.key === 'Enter') document.getElementById('l-pass').focus(); });
    </script>
</body>
</html>
<?php
    exit;
}
$currentUser = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/config.local.php';

// Load mood→songs mapping — user override takes priority over global default
$safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username'] ?? '');
$moodSongsFile = __DIR__ . '/data/mood_songs.json';
$moodSongsUserFile = __DIR__ . '/data/mood_songs_' . $safeUser . '.json';
if ($safeUser && file_exists($moodSongsUserFile)) $moodSongsFile = $moodSongsUserFile;
$moodSongs = [];
if (file_exists($moodSongsFile)) {
    $raw = @file_get_contents($moodSongsFile);
    if ($raw) $moodSongs = json_decode($raw, true) ?: [];
}

// Load scene→moods mapping — user override takes priority over global default
$sceneMoodMapFile = __DIR__ . '/data/scene_mood_map.json';
$sceneMoodMapUserFile = __DIR__ . '/data/scene_mood_map_' . $safeUser . '.json';
if ($safeUser && file_exists($sceneMoodMapUserFile)) $sceneMoodMapFile = $sceneMoodMapUserFile;
$sceneMoodMap = [];
if (file_exists($sceneMoodMapFile)) {
    $raw = @file_get_contents($sceneMoodMapFile);
    if ($raw) $sceneMoodMap = json_decode($raw, true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Stream Production Tool</title>
    <link rel="icon" href="production_files/images/favicon.ico?v=<?php echo $v; ?>" type="image/x-icon">
    <link rel="shortcut icon" href="production_files/images/favicon.ico?v=<?php echo $v; ?>" type="image/x-icon">
    <link rel="stylesheet" href="styles/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="styles/main.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/smoothness/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@500;600;700;800;900&family=Teko:wght@700&family=Orbitron:wght@700;800;900&family=Exo+2:wght@400;600;700&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/jquery-ui.min.js?v=<?php echo $v; ?>"></script>
    <script src="js/popper.min.js?v=<?php echo $v; ?>"></script>
    <script src="js/chart.js?v=<?php echo $v; ?>"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>

    <style>
        /* ── User bar (left column only) ── */
        #user-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 6px;
            background: #1a1a1a;
            border-bottom: 1px solid #333;
        }
        #user-bar-name {
            background: none;
            border: 1px solid #555;
            color: #f0a500;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            cursor: pointer;
            letter-spacing: 0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 130px;
        }
        #user-bar-name:hover { border-color: #f0a500; background: rgba(240,165,0,0.1); }
        #user-bar-logout {
            background: none;
            border: 1px solid #444;
            color: #888;
            font-size: 0.72rem;
            padding: 2px 8px;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
        }
        #user-bar-logout:hover { border-color: #888; color: #ccc; }

        #chpw-error { color: #e44; font-size: 0.8rem; min-height: 1.1em; margin-top: 0.5rem; }
        #chpw-ok    { color: #4c4; font-size: 0.8rem; min-height: 1.1em; margin-top: 0.5rem; }
        #chpw-save  { background: #f0a500; color: #111; font-weight: 700; border-color: #f0a500; }
        #chpw-save:hover { background: #ffc72c; }

        .collapsible-content {
            display: none;
            margin-top: 10px;
        }

        .collapsible-btn {
            background-color: #f1f1f1;
            color: #333;
            cursor: pointer;
            padding: 10px;
            width: 100%;
            border: none;
            text-align: left;
        }
        .settings-group-heading {
            margin-top: 1rem;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        .settings-group-heading:first-child { margin-top: 0; }

        .scoreboard-overlay-wrap { background: transparent; }
        /* Centered horizontally; top edge 300px; bottom leaves room for small VDO */
        #scoreboard-content.scoreboard-content-wrap,
        #custom-scoreboard-content.scoreboard-content-wrap {
            position: absolute; left: 50%; top: 120px; bottom: 40px;
            width: calc(100% - 60px); max-width: calc(100vw - 60px);
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
            overflow: auto; box-sizing: border-box;
        }
        .scoreboard-panel { padding: 0.35rem 0.5rem; color: #fff; font-family: 'Exo 2', sans-serif; box-sizing: border-box; width: 100%; max-width: 100%; min-width: 0; }
        .scoreboard-panel-inner { width: 100%; max-width: 100%; min-width: 0; margin: 0 auto; }
        .scoreboard-header { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; padding-bottom: 0.35rem; border-bottom: 2px solid rgba(108, 92, 231, 0.3); flex-wrap: nowrap; }
        .scoreboard-team-block { min-width: 0; flex: 1; }
        .scoreboard-team-a { text-align: center; }
        .scoreboard-team-b { text-align: center; }
        .scoreboard-team-name { display: block; font-size: 2.6rem; font-weight: 600; line-height: 1.2; color: #e0e0e0; font-family: 'Exo 2', sans-serif; }
        .scoreboard-team-a .scoreboard-team-name { color: #e0e0e0; }
        .scoreboard-team-b .scoreboard-team-name { color: #e0e0e0; }
        .scoreboard-vs-block { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 0.5rem; flex-shrink: 0; }
        .scoreboard-score-main { font-size: 2.75rem; font-weight: 700; color: #99eeff; font-family: 'Rajdhani', sans-serif; }
        .scoreboard-score-label { font-size: 0.9rem; color: #00b894; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 0.08rem; font-weight: 600; font-family: 'Rajdhani', sans-serif; }
        .scoreboard-table-wrap { background: rgba(0, 0, 0, 0.25); border-radius: 8px; overflow: hidden; border: 1px solid rgba(108, 92, 231, 0.2); margin-top: 0.35rem; width: 100%; max-width: 100%; min-width: 0; }
        .scoreboard-table { width: 100%; max-width: 100%; min-width: 0; table-layout: auto; border-collapse: collapse; font-size: 1.15rem; font-family: 'Exo 2', sans-serif; }
        /* Narrow columns shrink to content; team/map columns autosize (no width = fit content) */
        .scoreboard-table col:nth-child(1), .scoreboard-table col:nth-child(6), .scoreboard-table col:nth-child(10) { width: 0.1%; }
        .scoreboard-table col:nth-child(2) { width: 1%; min-width: 2.8em; }
        .scoreboard-table col:nth-child(5), .scoreboard-table col:nth-child(9) { width: 1%; min-width: 2em; }
        .scoreboard-table thead { border-bottom: none; }
        .scoreboard-table th { padding: 0.3rem 0.4rem; text-align: center; color: #fff; font-weight: 600; font-size: 1.1rem; background: rgba(108, 92, 231, 0.25); border-bottom: 1px solid rgba(255,255,255,0.08); white-space: nowrap; }
        .scoreboard-table th.scoreboard-th-empty { padding-left: 4px; padding-right: 4px; background: rgba(108, 92, 231, 0.15); }
        .scoreboard-table th.scoreboard-th-map { color: #a29bfe; font-weight: 500; font-size: 0.88rem; }
        .scoreboard-table th.scoreboard-th-group { text-align: center; }
        .scoreboard-table td { padding: 0.28rem 0.4rem; border-bottom: 1px solid rgba(255,255,255,0.08); color: #e0e0e0; text-align: center; font-size: 1.12rem; }
        .scoreboard-table tr:last-child td { border-bottom: none; }
        .scoreboard-table tr:nth-child(even) td { background: rgba(255,255,255,0.03); }
        .scoreboard-table td.scoreboard-empty-cell { padding-left: 4px; padding-right: 4px; overflow: hidden; border-color: rgba(255,255,255,0.06); }
        .scoreboard-table td.scoreboard-type { font-size: 1.12rem; }
        .scoreboard-table td.scoreboard-num { color: #FFD700; font-size: 1.25rem; font-weight: 700; text-align: center; white-space: nowrap; }
        .scoreboard-table tbody tr:hover td { background: rgba(108, 92, 231, 0.12); }
        .scoreboard-type { color: #00b894; font-weight: 600; font-family: 'Rajdhani', sans-serif; text-align: center; white-space: nowrap; }
        .scoreboard-num { color: #FFD700; font-weight: 700; text-align: center; white-space: nowrap; }
        .scoreboard-table td.scoreboard-map { color: #a29bfe; font-size: 0.88rem; text-align: center; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .scoreboard-map { color: #a29bfe; text-align: center; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .scoreboard-cell { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }
        .scoreboard-table td.scoreboard-cell-team { padding-left: 0.4rem; padding-right: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .scoreboard-table td.scoreboard-cell-team-a { text-align: left; padding-right: 0.6rem; }
        .scoreboard-table td.scoreboard-cell-team-b { text-align: left; padding-left: 0.6rem; padding-right: 0.4rem; }
        .scoreboard-slot { font-size: 0.75em; color: #a29bfe; font-weight: 600; }
        .scoreboard-race-icon { width: 1em; height: 1em; vertical-align: middle; }
        .scoreboard-empty { color: #a29bfe; text-align: center; padding: 1rem; font-size: 1.075rem; }

        /* ═══════════════════════════════════
           LEFT PANEL — LIGHT PRODUCTION THEME
           ═══════════════════════════════════ */
        .left-column {
            background: #f2f4f8 !important;
            color: #1e293b;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 0.82rem;
        }

        /* Section headings — dark band, stands out on light panel */
        .left-column h2 {
            background: #2d3748;
            color: #f0f4ff;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .12em;
            padding: 7px 8px 7px 11px;
            margin: 6px 0 0 0;
            border-left: 3px solid #5a7fd4;
            border-bottom: none;
            line-height: 1;
        }
        /* Collapsible h2 sections */
        .left-column h2.collapsible-h2 {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .left-column h2.collapsible-h2::before {
            content: "−";
            font-size: 0.85em;
            font-weight: 400;
            opacity: 0.6;
            flex-shrink: 0;
            line-height: 1;
        }
        .left-column h2.collapsible-h2.collapsed::before {
            content: "+";
        }
        .left-column h2.collapsible-h2:hover {
            background: #3a4a5e;
        }
        .left-column h3.settings-group-heading {
            margin: 4px 0 0 0;
            padding: 5px 8px 5px 10px;
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #e2e8f0;
            background: #475569;
            border-left: 3px solid #94a3b8;
        }
        .left-column hr {
            border: none;
            border-top: 1px solid #cbd5e1;
            margin: 5px 0;
        }
        .left-column br { display: none; }

        /* Collapsible headers — light but clearly a drawer-pull */
        .collapsible-btn {
            background: #e2e8f0 !important;
            color: #374151 !important;
            border: none !important;
            border-top: 1px solid #cbd5e1 !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-radius: 0 !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .08em !important;
            padding: 8px 10px 8px 10px !important;
            transition: background .12s !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .collapsible-btn::before {
            content: "+";
            font-size: 0.9em;
            opacity: 0.55;
            flex-shrink: 0;
            margin-right: 5px;
            font-weight: 400;
            line-height: 1;
        }
        .collapsible-btn.open::before {
            content: "−";
        }
        .collapsible-btn:hover {
            background: #d1d9e8 !important;
            color: #111827 !important;
        }
        .collapsible-content {
            background: #f2f4f8;
        }

        /* Scene buttons — solid, clearly buttons */
        .scenes-buttons button {
            background: #e2e8f0;
            color: #1e293b;
            border: 1px solid #b8c4d4;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 7px 8px;
            cursor: pointer;
            text-decoration: none !important;
            transition: background .1s, border-color .1s;
        }
        .scenes-buttons button:hover {
            background: #d1d9e8;
            color: #111827;
            border-color: #8090b0;
        }
        .scenes-buttons button.active {
            background: #15803d !important;
            color: #ffffff !important;
            border-color: #16a34a !important;
            text-decoration: none !important;
            box-shadow: 0 0 0 2px rgba(22,163,74,0.2) !important;
        }
        .scene-btn-major {
            font-size: 0.92rem !important;
            padding: 10px 12px !important;
            font-weight: 800 !important;
        }

        /* VDO / panel utility buttons */
        .vdo-controls {
            display: flex;
            gap: 4px;
            margin-bottom: 6px;
        }
        .vdo-controls button {
            flex: 1;
            background: #e2e8f0;
            color: #1e293b;
            border: 1px solid #b8c4d4;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 4px 2px;
            cursor: pointer;
            text-decoration: none;
            transition: background .1s, border-color .1s;
            text-transform: uppercase;
            letter-spacing: .03em;
            white-space: nowrap;
        }
        .vdo-controls button:hover {
            background: #d1d9e8;
            border-color: #8090b0;
            color: #111827;
        }
        .vdo-controls button.active {
            background: #15803d;
            color: #ffffff;
            border-color: #16a34a;
            box-shadow: 0 0 0 2px rgba(22,163,74,0.2);
        }
        /* VDO hidden = amber warning — obviously something is off */
        #btn-hide-vdo.vdo-hidden {
            background: #b45309;
            color: #ffffff;
            border-color: #d97706;
            box-shadow: 0 0 0 2px rgba(180,83,9,0.3);
        }
        #btn-hide-vdo.vdo-hidden:hover {
            background: #92400e;
            border-color: #b45309;
        }

        /* Player intro forms */
        .media-form input[type="text"] {
            background: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #b8c4d4 !important;
            border-radius: 4px !important;
            font-size: 0.78rem !important;
            padding: 5px 8px !important;
        }
        .media-form input[type="text"]:focus {
            border-color: #5a7fd4 !important;
            outline: none !important;
        }
        .media-form button[type="submit"] {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            border: 1px solid #b8c4d4 !important;
            border-radius: 4px !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            padding: 5px 10px !important;
        }
        .media-form button[type="submit"]:hover {
            background: #d1d9e8 !important;
            color: #111827 !important;
        }

        /* Break timer inline inputs */
        #break-quick-min, #break-quick-sec {
            background: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #b8c4d4 !important;
            border-radius: 3px !important;
        }

        /* ── Settings button in user-bar ── */
        #user-bar #btn-settings {
            background: rgba(255,255,255,0.18) !important;
            border: 1px solid rgba(255,255,255,0.4) !important;
            border-bottom: 1px solid rgba(255,255,255,0.4) !important;
            border-top: none !important;
            color: #e0e8f0 !important;
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            padding: 2px 7px !important;
            border-radius: 4px !important;
            width: auto !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            white-space: nowrap !important;
            flex-shrink: 0;
        }
        #user-bar #btn-settings:hover {
            background: rgba(255,255,255,0.3) !important;
            color: #ffffff !important;
        }

        /* ── Music Player Widget ─────────────────── */
        .lp-music { border-bottom: 1px solid #1e2637; margin-top: 6px; }

        /* Bar: matches .left-column h2 section heading exactly */
        .lp-music-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 8px 6px 8px;
            background: #2d3748;
            color: #f0f4ff;
            border-left: 3px solid #5a7fd4;
            border-bottom: 1px solid #1e2637;
            cursor: pointer;
            user-select: none;
        }
        .lp-music-bar:hover { background: #3a4a5e; }
        .lp-music-label {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #f0f4ff;
            flex-shrink: 0;
        }
        /* Transport buttons on dark bar */
        .lp-music-transport {
            display: flex;
            gap: 3px;
            flex-shrink: 0;
        }
        .lp-music-transport button {
            width: 22px !important;
            height: 20px !important;
            background: rgba(255,255,255,0.1) !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            border-radius: 3px !important;
            color: #e2e8f0 !important;
            font-size: 10px !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            text-decoration: none !important;
            transition: background .1s !important;
        }
        .lp-music-transport button:hover {
            background: rgba(255,255,255,0.22) !important;
            color: #fff !important;
            border-color: rgba(255,255,255,0.38) !important;
        }
        .lp-music-transport button.lp-mx-dim {
            background: rgba(255,255,255,0.04) !important;
            color: rgba(255,255,255,0.28) !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        .lp-music-transport button.lp-mx-paused {
            background: rgba(251,191,36,0.18) !important;
            color: #fbbf24 !important;
            border-color: rgba(251,191,36,0.45) !important;
        }
        /* Knobs inline in bar */
        .lp-music-knobs {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .lp-mx-knob-wrap {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 2px;
            flex-shrink: 0;
            cursor: ns-resize;
        }
        .lp-mx-dial-lbl {
            font-size: 0.5rem;
            font-weight: 700;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .lp-mx-knob {
            display: block;
            width: 30px;
            height: 30px;
            cursor: ns-resize;
        }
        /* Toggle icon — left side of bar */
        .lp-mx-toggle-icon {
            font-size: 0.9em;
            font-weight: 400;
            color: rgba(255,255,255,0.4);
            line-height: 1;
            flex-shrink: 0;
            margin-right: 2px;
        }
        /* Filename row — compact, always visible */
        .lp-mx-song-row {
            padding: 3px 10px;
            background: #f2f4f8;
            border-bottom: 1px solid #cbd5e1;
        }
        .lp-mx-song {
            display: block;
            font-size: 0.58rem;
            color: #64748b;
            font-family: Consolas, monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-style: italic;
        }
        .lp-mx-song.playing { color: #15803d; font-style: normal; font-weight: 600; }
        .lp-mx-song.lp-mx-err { color: #b91c1c; font-style: normal; }
        .lp-mx-seek-wrap { display:flex; align-items:center; gap:4px; padding:3px 0 1px; }
        .lp-mx-time { font-size:0.52rem; color:#94a3b8; font-family:Consolas,monospace; flex-shrink:0; }
        .lp-mx-seek { flex:1; height:3px; accent-color:#3b82f6; cursor:pointer; margin:0; padding:0; }
        /* Mood grid */
        .lp-music-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3px;
            padding: 5px;
            background: #f2f4f8;
            border-bottom: 1px solid #b8c4d4;
        }
        .lp-music-grid button {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            border: 1px solid #b8c4d4 !important;
            border-radius: 4px !important;
            padding: 4px 2px !important;
            font-size: 0.59rem !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            text-align: center !important;
            text-transform: uppercase !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            text-decoration: none !important;
            transition: background .1s !important;
        }
        .lp-music-grid button:hover {
            background: #d1d9e8 !important;
            color: #111827 !important;
        }
        .lp-music-grid button.active {
            background: #15803d !important;
            color: #ffffff !important;
            border-color: #16a34a !important;
            box-shadow: 0 0 0 2px rgba(22,163,74,0.2) !important;
        }
    </style>
</head>

<body>
    <div class="container" data-layer-id="container">
        <div class="left-column">
            <!-- User bar -->
            <div id="user-bar">
                <button id="user-bar-name" title="Click to change password"><?php echo $currentUser; ?></button>
                <button class="collapsible-btn" id="btn-settings" onclick="toggleSettings(this)">&#9881; Settings</button>
                <button id="user-bar-logout">Logout</button>
            </div>
            <div class="collapsible-content" id="settings-section" style="display: none;">
                <h3 class="settings-group-heading">Onscreen Messages</h3>
                <button class="collapsible-btn" id="btn-status" onclick="toggleStatus(this)">Status Message</button>
                <div class="collapsible-content" id="status-section" style="display: none;">
                    <h2>Status</h2>
                    <table>
                        <tr>
                            <th>Title</th>
                            <th>Team A</th>
                            <th>Team B</th>
                        </tr>
                        <tr>
                            <td><input type="text" id="status-title" placeholder="Enter title"></td>
                            <td><input type="text" id="status-teamA" placeholder="Team A"></td>
                            <td><input type="text" id="status-teamB" placeholder="Team B"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td><input type="text" id="status-valueA" placeholder="Value A"></td>
                            <td><input type="text" id="status-valueB" placeholder="Value B"></td>
                        </tr>
                    </table>
                    <button id="btn-status-result" onclick="showFormattedResult(this)">Show Status Message</button>
                </div>
                <h3 class="settings-group-heading">Player</h3>
                <button class="collapsible-btn" id="btn-scoreboard-settings" onclick="toggleScoreboardSettings(this)">Scoreboard</button>
                <div class="collapsible-content" id="scoreboard-settings-section" style="display: none;">
                    <h2>Scoreboard</h2>
                    <div style="display:flex; gap:6px; align-items:center; margin-bottom:8px;">
                        <button type="button" id="scoreboard-load-btn" onclick="scoreboardEditorLoad()">Load Current Scores</button>
                        <span id="scoreboard-load-status" style="font-size:12px; color:#aaa;"></span>
                    </div>
                    <div id="scoreboard-editor-content" style="display:none;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; padding:8px; background:#1a1a1a; border-radius:4px;">
                            <span id="sb-team-a-name" style="flex:1; text-align:center; font-weight:bold; font-size:13px; color:#8f8; overflow:hidden; text-overflow:ellipsis;" title=""></span>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span id="sb-score-a" style="display:inline-block; width:52px; text-align:center; font-size:20px; font-weight:bold; color:#8f8; border:1px solid #484; border-radius:4px; padding:2px 4px; background:#1a2a1a; min-height:1.2em;">0</span>
                                <span style="font-size:16px; color:#888; padding:0 2px;">–</span>
                                <span id="sb-score-b" style="display:inline-block; width:52px; text-align:center; font-size:20px; font-weight:bold; color:#88f; border:1px solid #448; border-radius:4px; padding:2px 4px; background:#1a1a2a; min-height:1.2em;">0</span>
                            </div>
                            <span id="sb-team-b-name" style="flex:1; text-align:center; font-weight:bold; font-size:13px; color:#88f; overflow:hidden; text-overflow:ellipsis;" title=""></span>
                        </div>
                        <div id="scoreboard-matchup-rows"></div>
                        <div style="margin-top:8px; display:flex; gap:6px; align-items:center;">
                            <button type="button" id="scoreboard-score-save-btn" onclick="scoreboardEditorSave()">Save Scores</button>
                            <span id="scoreboard-save-status" style="font-size:12px; color:#aaa;"></span>
                        </div>
                    </div>
                    <details style="margin-top:10px;">
                        <summary style="cursor:pointer; font-size:11px; color:#888; user-select:none;">Raw CSV (advanced)</summary>
                        <div style="margin-top:6px;">
                            <p class="layer-order-hint">Paste CSV to overwrite <code>2026/scoreboard.csv</code> directly.</p>
                            <textarea id="scoreboard-csv-input" rows="10" style="width: 100%; font-family: monospace; font-size: 11px;" placeholder="Paste CSV data here..."></textarea>
                            <button type="button" id="scoreboard-csv-save-btn" style="margin-top: 6px;">Overwrite scoreboard.csv</button>
                        </div>
                    </details>
                </div>
                <button class="collapsible-btn" id="btn-custom-scoreboard-settings" onclick="toggleCustomScoreboardSettings(this)">Custom Scoreboard</button>
                <div class="collapsible-content" id="custom-scoreboard-settings-section" style="display: none;">
                    <h2>Custom Scoreboard</h2>
                    <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 6px; align-items:center; margin-bottom:10px;">
                        <label style="font-size:12px; color:#8f8;">Side A label:</label>
                        <input type="text" id="csb-label-a" placeholder="Side A" style="background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px; padding:3px 6px; font-size:12px;">
                        <label style="font-size:12px; color:#88f;">Side B label:</label>
                        <input type="text" id="csb-label-b" placeholder="Side B" style="background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px; padding:3px 6px; font-size:12px;">
                        <label style="font-size:12px; color:#aaa;">Matches:</label>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <input type="number" id="csb-num-matches" min="1" max="20" value="5" style="width:52px; text-align:center; padding:3px 4px;">
                            <button type="button" onclick="csbSetNumMatches(parseInt(document.getElementById('csb-num-matches').value,10)||1)">Set</button>
                        </div>
                    </div>
                    <div id="csb-rows-container"></div>
                    <div style="margin-top:8px; display:flex; gap:6px; align-items:center;">
                        <button type="button" id="csb-save-btn" onclick="csbSave()">Save Custom Scoreboard</button>
                        <span id="csb-save-status" style="font-size:12px; color:#aaa;"></span>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-player-intros" onclick="togglePlayerIntros(this)">Player Chroma</button>
                <div class="collapsible-content" id="player-intros-settings-section" style="display: none;">
                    <h2>Player Intros</h2>
                    <label><input type="checkbox" id="chroma-key-cb" checked> Chroma key (green transparent)</label>
                </div>

                <button class="collapsible-btn" id="btn-player-ratings" onclick="togglePlayerRatings(this)">Spider Ratings</button>
                <div class="collapsible-content" id="player-ratings-section" style="display: none;">
                    <h2>Spider Ratings</h2>
                    <p>External Spider Chart</p>
                    <input id="player-input" type="text" placeholder="Enter player name" value="littlereaper">
                    <input id="division-input" type="text" placeholder="Division" value="A" style="width: 5ch; margin-left: 5px;">
                    <button id="chart-toggle-btn" onclick="toggleExternalChart()">Load External Chart</button>
                    <div id="error-message" style="color: red;"></div>
                    <p class="layer-order-hint" style="margin-top: 8px;">FSL rankings (season/all-time W–L under player name):</p>
                    <button type="button" id="rankings-refresh-btn" style="margin-top: 4px;">Refresh rankings</button>
                    <span id="rankings-refresh-status" style="margin-left: 6px; font-size: 0.9rem;"></span>
                </div>

                <h3 class="settings-group-heading">Layouts and Layers</h3>
                <button class="collapsible-btn" id="btn-volume" onclick="toggleVolume(this)">Volume</button>
                <div class="collapsible-content" id="volume-section" style="display: none;">
                    <h2>Volume</h2>
                    <div>
                        <label for="volume-slider">Volume: </label>
                        <input type="range" id="volume-slider" min="5" max="100" value="50">
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-music" onclick="toggleMusic(this)">Music</button>
                <div class="collapsible-content" id="music-section" style="display: none;">
                    <h2>Music Mode</h2>
                    <p class="layer-order-hint">Controls how "Random Music" picks its clips.</p>
                    <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-top: 6px;">
                        <label><input type="radio" name="music-mode" value="sequence" checked> Sequence &mdash; plays in order, loops back to start</label>
                        <label><input type="radio" name="music-mode" value="random"> Random</label>
                    </div>
                    <h2 style="margin-top:10px;">Scene → Moods</h2>
                    <p class="layer-order-hint">Maps each scene to an ordered list of moods. When a scene activates, a random mood is picked; after all songs in that mood finish, the next mood in the list plays (cycling). Edit JSON, then Apply or Save.</p>
                    <textarea id="mx-scene-map-editor" rows="12" style="width:100%;font-family:monospace;font-size:10px;background:#1e293b;color:#e2e8f0;border:1px solid #475569;border-radius:4px;padding:6px;box-sizing:border-box;resize:vertical;"></textarea>
                    <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                        <button type="button" id="mx-scene-map-apply-btn">Apply</button>
                        <button type="button" id="mx-scene-map-save-btn">Save to Server</button>
                        <span id="mx-scene-map-status" style="font-size:0.78rem;color:#64748b;"></span>
                    </div>

                    <h2 style="margin-top:14px;">Mood → Songs</h2>
                    <p class="layer-order-hint">Maps each mood to its song files (in <code>music/</code>). Songs play in order; when the list ends the next mood in the scene begins. Edit JSON, then Apply or Save.</p>
                    <textarea id="mx-mood-songs-editor" rows="18" style="width:100%;font-family:monospace;font-size:10px;background:#1e293b;color:#e2e8f0;border:1px solid #475569;border-radius:4px;padding:6px;box-sizing:border-box;resize:vertical;"></textarea>
                    <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                        <button type="button" id="mx-mood-songs-apply-btn">Apply</button>
                        <button type="button" id="mx-mood-songs-save-btn">Save to Server</button>
                        <span id="mx-mood-songs-status" style="font-size:0.78rem;color:#64748b;"></span>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-layer-order" onclick="toggleLayerOrder(this)">Layer Order</button>
                <div class="collapsible-content" id="layer-order-section" style="display: none;">
                    <div id="layer-order-ui">
                        <h2 class="layer-order-heading">Layer order</h2>
                        <p class="layer-order-hint">Drag to reorder. Top = back, bottom = front. Order is saved automatically.</p>
                        <ul id="layer-list" aria-label="Layer order, drag to reorder"></ul>
                        <div class="layer-order-actions">
                            <button type="button" id="layer-reset-btn">Reset layer order to default</button>
                        </div>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-yt-video" onclick="toggleYtVideo(this)">YT Video</button>
                <div class="collapsible-content" id="yt-video-section" style="display: none;">
                    <h2 class="layer-order-heading">YT Video crop (pixels)</h2>
                    <p class="layer-order-hint">Crop applied when YT scene is on. Top, left, right, bottom cut from shared window.</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center; margin-top: 6px;">
                        <label>Top: <input type="number" id="yt-crop-top" min="0" step="1" value="150" style="width: 5ch;"></label>
                        <label>Left: <input type="number" id="yt-crop-left" min="0" step="1" value="10" style="width: 5ch;"></label>
                        <label>Right: <input type="number" id="yt-crop-right" min="0" step="1" value="20" style="width: 5ch;"></label>
                        <label>Bottom: <input type="number" id="yt-crop-bottom" min="0" step="1" value="100" style="width: 5ch;"></label>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-yt-videos-settings" onclick="toggleYtVideosSettings(this)">Video Buttons</button>
                <div class="collapsible-content" id="yt-videos-settings-section" style="display: none;">
                    <h2 class="layer-order-heading">Video Buttons</h2>
                    <p class="layer-order-hint">Paste any YouTube URL (youtube.com/watch, youtu.be, shorts, or embed). It is automatically converted to the embed+autoplay format needed. Changes take effect immediately.</p>
                    <div style="display: grid; grid-template-columns: auto auto 1fr; gap: 0.35rem 0.5rem; align-items: center; margin-top: 6px;">
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 1 label:</label>
                        <input type="text" id="yt-video-1-label" value="INTRO" style="width: 8ch;">
                        <span></span>
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 1 URL:</label>
                        <input type="text" id="yt-video-1-url" value="https://www.youtube.com/watch?v=vt04Xbq57Dk" style="grid-column: 2 / 4;" placeholder="Paste YouTube URL…">
                        <span></span>
                        <div id="yt-video-1-resolved" style="grid-column: 2 / 4; font-size: 0.75rem; font-family: monospace; word-break: break-all; min-height: 1em;"></div>
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 1 volume:</label>
                        <input type="number" id="yt-video-1-vol" min="5" max="100" value="100" style="width: 5ch; text-align: center;">
                        <span style="font-size: 0.85rem;">%</span>
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 2 label:</label>
                        <input type="text" id="yt-video-2-label" value="BREAK" style="width: 8ch;">
                        <span></span>
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 2 URL:</label>
                        <input type="text" id="yt-video-2-url" value="https://youtu.be/O9lNetcn9Y8?si=FaqwLX5I9KkoJecK" style="grid-column: 2 / 4;" placeholder="Paste YouTube URL…">
                        <span></span>
                        <div id="yt-video-2-resolved" style="grid-column: 2 / 4; font-size: 0.75rem; font-family: monospace; word-break: break-all; min-height: 1em;"></div>
                        <label style="font-size: 0.85rem; white-space: nowrap;">Button 2 volume:</label>
                        <input type="number" id="yt-video-2-vol" min="5" max="100" value="100" style="width: 5ch; text-align: center;">
                        <span style="font-size: 0.85rem;">%</span>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-break-settings" onclick="toggleBreakSettings(this)">Break</button>
                <div class="collapsible-content" id="break-settings-section" style="display: none;">
                    <h2 class="layer-order-heading">Break</h2>
                    <p class="layer-order-hint">Countdown and message shown on top of the BREAK video in the right panel.</p>
                    <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-top: 6px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="font-size: 0.85rem; white-space: nowrap;">Timer (min:sec):</label>
                            <input type="number" id="break-timer-min" min="0" max="99" value="5" style="width: 5ch; text-align: center;">
                            <span style="font-size: 0.85rem;">:</span>
                            <input type="number" id="break-timer-sec" min="0" max="59" value="0" style="width: 5ch; text-align: center;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label style="font-size: 0.85rem; white-space: nowrap;">Message:</label>
                            <input type="text" id="break-timer-msg" value="be right back..." style="flex: 1; min-width: 0;">
                        </div>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-logos-settings" onclick="toggleLogosSettings(this)">Positioning</button>
                <div class="collapsible-content" id="logos-settings-section" style="display: none;">
                    <h2>Positional settings</h2>
                    <div class="logos-checkboxes">
                        <label><input type="checkbox" id="logo-s10-cb" data-logo="s10"> FSL S10 logo</label><br>
                        <label><input type="checkbox" id="logo-fsl-small-cb" data-logo="fsl-small"> FSL SC2 logo small</label><br>
                        <label><input type="checkbox" id="logo-vdo-large-cb" data-vdo="large" checked> Large VDO</label><br>
                        <label><input type="checkbox" id="logo-sc2-cb" data-logo="sc2"> Small VDO</label>
                    </div>
                    <div class="logos-edit-actions" style="margin-top: 10px;">
                        <p class="layer-order-hint">Edit and Move: drag/resize logos and VDO panels. SC2 scene shows small VDO; non-SC2 shows large VDO. Save stores all positions.</p>
                        <button type="button" id="logos-edit-save-btn" onclick="toggleLogosEditMode()">Edit and Move</button>
                        <button type="button" id="logos-reset-btn" onclick="resetLogosPositions()">Reset</button>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-overlays" onclick="toggleOverlays(this)">Overlays</button>
                <div class="collapsible-content" id="overlays-section" style="display: none;">
                    <h2 class="layer-order-heading">Overlays</h2>
                    <p class="layer-order-hint">BG, VDO full, and Logos toggles (optional).</p>
                    <div class="scenes-buttons settings-overlays-row" style="margin-top: 6px;">
                        <button type="button" id="scene-btn-all-vdo" onclick="toggleSceneOverlay('all-vdo')">BG</button>
                        <button type="button" id="scene-btn-vdo-full" onclick="toggleSceneOverlay('vdo-full')">VDO full</button>
                        <button type="button" id="scene-btn-logos" onclick="toggleSceneOverlay('logos')">Logos</button>
                    </div>
                </div>

                <h3 class="settings-group-heading">Save / Load setup</h3>
                <button class="collapsible-btn" id="btn-save-load" onclick="toggleSaveLoad(this)">Save / Load</button>
                <div class="collapsible-content" id="save-load-section" style="display: none;">
                    <h2 class="layer-order-heading">Import / Export all settings</h2>
                    <p class="layer-order-hint">Export saves: layer order, volume, Status, Player Ratings, Logos (checkboxes + positions), VDO full and SC2 panel positions, Scenes visibility, Player Intros names, Chroma key, YT crop, Video button labels/URLs, and Break timer/message. <strong>Save to server</strong> stores the current setup so anyone opening this link gets the same settings. You can still <strong>Export all</strong> / <strong>Import all</strong> to share via file.</p>
                    <div class="layer-order-actions">
                        <button type="button" id="layer-export-btn">Export all</button>
                        <button type="button" id="layer-save-server-btn">Save to server</button>
                        <input type="file" id="layer-import-file" accept=".json" style="display: none;">
                        <button type="button" id="layer-import-btn">Import all</button>
                    </div>
                </div>

                <h3 class="settings-group-heading">Account</h3>
                <button class="collapsible-btn" id="btn-account" onclick="toggleAccount(this)">Change Password</button>
                <div class="collapsible-content" id="account-section" style="display: none;">
                    <p class="layer-order-hint">Logged in as <strong><?php echo $currentUser; ?></strong></p>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 8px;">
                        <label style="font-size: 0.78rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px;">Current password</label>
                        <input type="password" id="chpw-current" autocomplete="current-password" style="padding: 7px 9px; background: #2a2a2a; border: 1px solid #444; border-radius: 4px; color: #eee; font-size: 0.9rem; outline: none; width: 100%;">
                        <label style="font-size: 0.78rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px;">New password</label>
                        <input type="password" id="chpw-new" autocomplete="new-password" style="padding: 7px 9px; background: #2a2a2a; border: 1px solid #444; border-radius: 4px; color: #eee; font-size: 0.9rem; outline: none; width: 100%;">
                        <label style="font-size: 0.78rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px;">Confirm new password</label>
                        <input type="password" id="chpw-confirm" autocomplete="new-password" style="padding: 7px 9px; background: #2a2a2a; border: 1px solid #444; border-radius: 4px; color: #eee; font-size: 0.9rem; outline: none; width: 100%;">
                        <div id="chpw-error" style="color: #e44; font-size: 0.8rem; min-height: 1em;"></div>
                        <div id="chpw-ok" style="color: #4c4; font-size: 0.8rem; min-height: 1em; display: none;">Password changed.</div>
                        <button type="button" id="chpw-save" style="padding: 7px 14px; background: #f0a500; color: #111; font-weight: 700; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; align-self: flex-start;">Save Password</button>
                    </div>
                </div>
            </div>
            <hr>
            <br>
            <div class="vdo-controls">
                <button type="button" id="btn-hide-vdo" onclick="toggleVdoVisibility()">Hide VDO</button>
                <button type="button" id="btn-reload-vdo" onclick="reloadVdo()">Reload VDO</button>
                <button type="button" id="btn-refresh-panel" onclick="refreshProductionPanel()">Refresh RightPanel</button>
            </div>

            <!-- ── MUSIC PLAYER ────────────────── -->
            <div class="lp-music">
                <div class="lp-music-bar" id="lpMusicBar">
                    <span class="lp-mx-toggle-icon" id="lpMusicToggleIcon">&#8722;</span>
                    <span class="lp-music-label">&#9836; MUSIC</span>
                    <div class="lp-music-transport" onclick="event.stopPropagation()">
                        <button id="lpMusicPlayPause" class="lp-mx-dim" title="Play / Pause">&#9654;</button>
                        <button id="lpMusicStop" title="Stop">&#9632;</button>
                    </div>
                    <div class="lp-music-knobs" onclick="event.stopPropagation()">
                        <div class="lp-mx-knob-wrap" title="Volume — drag up/down or scroll">
                            <span class="lp-mx-dial-lbl">VOL</span>
                            <canvas id="lpMusicVolKnob" class="lp-mx-knob" width="30" height="30"></canvas>
                            <input type="number" id="lpMusicVol" min="5" max="100" value="22" style="display:none">
                        </div>
                        <div class="lp-mx-knob-wrap" title="Crossfade — drag up/down or scroll">
                            <span class="lp-mx-dial-lbl">FADE</span>
                            <canvas id="lpMusicFadeKnob" class="lp-mx-knob" width="30" height="30"></canvas>
                            <input type="number" id="lpMusicFade" min="0" max="10" step="0.5" value="1.5" style="display:none">
                        </div>
                    </div>
                </div>
                <div class="lp-mx-song-row">
                    <span class="lp-mx-song" id="lpMusicStatus">select a mood</span>
                    <div class="lp-mx-seek-wrap">
                        <span id="lpMusicTimeNow" class="lp-mx-time">0:00</span>
                        <input type="range" id="lpMusicSeek" class="lp-mx-seek" min="0" max="1000" value="0" step="1">
                        <span id="lpMusicTimeDur" class="lp-mx-time">-:--</span>
                    </div>
                </div>
                <div class="lp-music-grid" id="lpMusicGrid"></div>
            </div>

            <h2 class="collapsible-h2" id="h2-scenes" onclick="toggleH2Section('scenes-section', this)">Scenes</h2>
            <div id="scenes-section">
            <div style="margin-bottom: 0.1rem; margin-top: 0.35rem;">
                <div style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.2rem;">
                    <span style="font-size: 0.8rem; font-weight: 600; white-space: nowrap;">Videos:</span>
                    <button type="button" id="scene-btn-yt-intro" onclick="toggleYtIframeScene('intro')">INTRO</button>
                    <button type="button" id="scene-btn-yt-break" onclick="toggleYtIframeScene('break')">BREAK</button>
                    <input type="text" inputmode="numeric" id="break-quick-min" maxlength="2" value="05" style="width: 4.5ch; text-align: center; padding: 2px;" title="Break timer minutes">
                    <span style="font-size: 0.85rem; line-height: 1;">:</span>
                    <input type="text" inputmode="numeric" id="break-quick-sec" maxlength="2" value="00" style="width: 4.5ch; text-align: center; padding: 2px;" title="Break timer seconds">
                    <span style="font-size: 0.7rem; color: #aaa; white-space: nowrap;">mm:ss</span>
                </div>
            </div>
            <div class="scenes-buttons">
                <div style="display:flex; gap:4px; width:100%;">
                    <button type="button" id="scene-btn-sc2" class="scene-btn-major" onclick="toggleSceneOverlay('sc2')" style="flex:5;">SC2 (animated)</button>
                    <button type="button" id="scene-btn-sc2-quick" class="scene-btn-major" onclick="toggleSceneOverlay('sc2-quick')" style="flex:1;">Quick</button>
                </div>
                <button type="button" id="scene-btn-schedule" onclick="toggleSceneOverlay('schedule')">Schedule</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" id="scene-btn-scoreboard" onclick="toggleSceneOverlay('scoreboard')">FSL Scoreboard</button>
                    <button type="button" id="scene-btn-custom-scoreboard" onclick="toggleSceneOverlay('custom-scoreboard')">Custom Scoreboard</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" id="scene-btn-ash" onclick="toggleSceneOverlay('ash')">ASH</button>
                    <button type="button" id="scene-btn-pog" onclick="toggleSceneOverlay('pog')">POG</button>
                    <button type="button" id="scene-btn-ptb" onclick="toggleSceneOverlay('ptb')">PTB</button>
                    <button type="button" id="scene-btn-st" onclick="toggleSceneOverlay('st')">ST</button>
                </div>
                <span class="scenes-shared-group" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                    <button type="button" id="scene-btn-shared-window" onclick="toggleSceneOverlay('shared-window')">Shared Window</button>
                    <button type="button" id="scene-btn-full-shared" onclick="toggleSceneOverlay('full-shared')" style="display: none;">Shared (Partial)</button>
                    <button type="button" id="scene-btn-yt" onclick="toggleSceneOverlay('yt')" style="display: none;">YT</button>
                </span>
            </div>
            <div id="scene-video-error" class="scene-video-error" style="display: none; font-size: 0.8rem; color: #c00; margin-top: 4px;"></div>
            </div><!-- /scenes-section -->

            <h2 class="collapsible-h2" id="h2-player-intros" onclick="toggleH2Section('player-intros-section', this)">Player Intros & Effects</h2>
            <div id="player-intros-section">

            <button class="collapsible-btn" id="btn-forms" onclick="toggleForms(this)"> </button>

            <!-- Always visible forms -->
            <form class="media-form" id="media-form-1">
                <input type="text" class="player-name-input" placeholder="Enter player name" value="DarkMenace" required>
                <button type="submit">Go</button>
            </form>
            <form class="media-form" id="media-form-2">
                <input type="text" class="player-name-input" placeholder="Enter player name" value="LittleReaper" required>
                <button type="submit">Go</button>
            </form>

            <div class="collapsible-content" id="player-intros-list">
                <form class="media-form" id="media-form-3">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="PulledTheBoys" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-4">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="HyperTurtle" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-5">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="Harouz" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-6">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="InfiniteCyclists" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-7">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="Random Music" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-8">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="FSL intro" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-9">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="GG" required>
                    <button type="submit">Go</button>
                </form>
                <form class="media-form" id="media-form-10">
                    <input type="text" class="player-name-input" placeholder="Enter player name" value="Match GG" required>
                    <button type="submit">Go</button>
                </form>
            </div>
            </div><!-- /player-intros-section -->
        </div>
        <div class="right-column">
            <!-- Fixed 16:9 stream frame (1280×720) so insets and layout are consistent -->
            <div class="stream-frame">
            <!-- Scenes overlay: right panel only, does not cover left column -->
            <div id="scene-overlay-all-vdo" data-layer-id="scene-overlay-all-vdo" style="
                display: none;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
                background: #000;
            ">
                <iframe id="scene-overlay-all-vdo-iframe" style="
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    border: none;
                "></iframe>
            </div>
            <div class="video-wrapper">
                <div id="video-container" data-layer-id="video-container">
                    <video id="video-player" width="640" height="480">
                        Your browser does not support the video tag.
                    </video>
                    <canvas id="video-chroma-canvas" style="display:none; position:absolute; pointer-events:none;"></canvas>
                </div>
                <div id="right-column-result" data-layer-id="right-column-result" style="text-align: center;">
                    <!-- Status data will be dynamically inserted here -->
                </div>
                <div id="scoreboard-overlay" data-layer-id="scoreboard-overlay" class="scoreboard-overlay-wrap" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: auto; pointer-events: none;">
                    <div id="scoreboard-content" class="scoreboard-panel scoreboard-content-wrap"></div>
                </div>
                <div id="custom-scoreboard-overlay" class="scoreboard-overlay-wrap" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: auto; pointer-events: none;">
                    <div id="custom-scoreboard-content" class="scoreboard-panel scoreboard-content-wrap"></div>
                </div>
                <div id="gif-container" data-layer-id="gif-container">
                    <img id="gif-image" src="production_files/images/transparent_greenscreen.gif?v=<?php echo $v; ?>" alt="GIF">
                    <canvas id="gif-chroma-canvas" style="display:none; position:absolute; pointer-events:none;"></canvas>
                </div>
                <div id="chart-container" data-layer-id="chart-container" style="display: none;"></div>
                
                <!-- Dedicated External Chart Overlay -->
                <div id="external-chart-overlay" data-layer-id="external-chart-overlay" style="
                    display: none;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 1000;
                    pointer-events: none;
                    justify-content: center;
                    align-items: center;
                "></div>
                <div class="player-name-box" data-layer-id="player-name-box"></div>
            </div>

            <!-- VDO full: full-size overlay; inner panel is draggable/resizable and saveable like SC2 -->
            <div id="scene-overlay-vdo-full" data-layer-id="scene-overlay-vdo-full" class="vdo-full-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2000;">
                <div id="vdo-full-panel-wrap" class="vdo-full-panel-wrap" style="position: absolute; overflow: hidden; background: #000;">
                    <iframe data-src="https://vdo.ninja/?scene=1&room=KJNinjaRoom123&password=FSL&sl&cover&autostart" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen"></iframe>
                </div>
            </div>

            <!-- Logos overlay: each logo is a wrapper div (dragged/resized) with img inside -->
            <div id="logos-overlay" data-layer-id="logos-overlay" class="logos-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                <div id="logo-s10-wrap" class="logo-wrap" style="display: none; position: absolute;">
                    <img src="2026/FSL_s10_logo.png?v=<?php echo $v; ?>" alt="FSL S10" draggable="false">
                </div>
                <div id="logo-fsl-small-wrap" class="logo-wrap" style="display: none; position: absolute;">
                    <img src="2026/fsl_sc2_logo_small.png?v=<?php echo $v; ?>" alt="FSL SC2" draggable="false">
                </div>
            </div>

            <!-- SC2: smaller VDO panel; when SC2 scene is on, BG is hidden and this overlay is shown. Panel is draggable/resizable like logos. -->
            <div id="sc2-overlay" data-layer-id="sc2-overlay" class="sc2-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                <div id="sc2-panel-wrap" class="sc2-panel-wrap logo-wrap" style="display: none; position: absolute; overflow: hidden; background: #000;">
                    <iframe data-src="https://vdo.ninja/?scene=0&room=KJNinjaRoom123&password=FSL&sl&cover&916" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen"></iframe>
                </div>
            </div>
            <!-- Shared Window: shows getDisplayMedia() stream (browser tab/window). Works like other non-SC2 scenes. -->
            <div id="scene-overlay-shared-window" data-layer-id="scene-overlay-shared-window" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 99999; background: #000;">
                <video id="shared-window-video" autoplay playsinline muted style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;"></video>
            </div>
            <!-- Shared (Partial): same stream as Shared Window but inset (50px L/R, 100px top) with BG, logos, mini VDO on top -->
            <div id="scene-overlay-full-shared-panel" data-layer-id="scene-overlay-full-shared-panel" style="display: none; position: absolute; left: 50px; right: 50px; top: 100px; bottom: 0; z-index: 2; overflow: hidden; background: #000;">
                <video id="full-shared-panel-video" autoplay playsinline muted style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;"></video>
            </div>
            <!-- YT: same stream as Shared Window, full frame but cropped by top/left/right/bottom from YT Video settings -->
            <div id="scene-overlay-yt" data-layer-id="scene-overlay-yt" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 99999; background: #000;">
                <div id="yt-crop-wrap" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden;">
                    <video id="yt-video" autoplay playsinline muted style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; object-position: center center;"></video>
                </div>
            </div>
            <!-- YT iframe scene: shows embedded YouTube video filling the right panel -->
            <div id="scene-overlay-yt-iframe" data-layer-id="scene-overlay-yt-iframe" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 99999; background: #000;">
                <div id="yt-iframe-crop-wrap" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden;">
                    <iframe id="yt-iframe-player" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen" allowfullscreen></iframe>
                </div>
                <!-- Break countdown: shown on top of the iframe when BREAK scene is active -->
                <div id="break-countdown-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; z-index: 1; pointer-events: none; text-align: center; padding-top: 28px;">
                    <div id="break-message-display" style="font-family: Arial, Helvetica, sans-serif; font-size: 2rem; font-weight: bold; color: #ff0; text-shadow: -2px -2px 0 #000, 2px -2px 0 #000, -2px 2px 0 #000, 2px 2px 0 #000; margin-bottom: 6px;">be right back...</div>
                    <div id="break-timer-display" style="font-family: Arial, Helvetica, sans-serif; font-size: 5rem; font-weight: bold; color: #99eeff; text-shadow: -3px -3px 0 #000, 3px -3px 0 #000, -3px 3px 0 #000, 3px 3px 0 #000; letter-spacing: 0.05em; line-height: 1;">05:00</div>
                </div>
            </div>
            <!-- Fullscreen transition video overlay (fade in/out); used by playTransitionVideo() -->
            <div id="transition-video-overlay" class="transition-video-overlay" style="display: none;">
                <video id="transition-video-player" class="transition-video-player" muted playsinline></video>
            </div>
            </div>
        </div>
    </div>

    <audio id="audio-player" style="display:none;">
        Your browser does not support the audio element.
    </audio>

    <!-- Modal for Shared Window: choose window/tab to share and display mode -->
    <div id="shared-window-dialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; flex-direction: column; justify-content: center; align-items: center;">
        <div style="background: #fff; padding: 1.5rem; border-radius: 8px; max-width: 380px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <p style="margin: 0 0 0.75rem;">Select the window or browser tab you want to share.</p>
            <p style="margin: 0 0 1rem; font-size: 0.9rem; color: #555;">Then choose how to display it:</p>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <button type="button" class="shared-dialog-mode-btn" data-mode="full">Full screen – shared content fills the frame</button>
                <button type="button" class="shared-dialog-mode-btn" data-mode="partial">With overlays – shared panel + BG + logos + mini VDO</button>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" id="shared-window-dialog-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <script>window.ASSET_VERSION = "<?php echo $v; ?>";</script>
    <script type="module" src="js/stream_production.js?v=<?php echo $v; ?>"></script>

    <script>
        function toggleH2Section(sectionId, h2el) {
            var section = document.getElementById(sectionId);
            if (!section) return;
            var collapsed = section.style.display === 'none';
            section.style.display = collapsed ? '' : 'none';
            if (h2el) h2el.classList.toggle('collapsed', !collapsed);
        }

        function toggleSettings(btn) {
            var el = document.getElementById("settings-section");
            if (el.style.display === "none" || !el.style.display) {
                el.style.display = "block";
            } else {
                el.style.display = "none";
            }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleMusic(btn) {
            var el = document.getElementById("music-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleVolume(btn) {
            var el = document.getElementById("volume-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleScoreboardSettings(btn) {
            var el = document.getElementById("scoreboard-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleCustomScoreboardSettings(btn) {
            var el = document.getElementById("custom-scoreboard-settings-section");
            if (el.style.display === "none" || !el.style.display) {
                el.style.display = "block";
                csbLoad();
            } else {
                el.style.display = "none";
            }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function togglePlayerIntros(btn) {
            var el = document.getElementById("player-intros-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleLayerOrder(btn) {
            var el = document.getElementById("layer-order-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleSaveLoad(btn) {
            var el = document.getElementById("save-load-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleOverlays(btn) {
            var el = document.getElementById("overlays-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleYtVideo(btn) {
            var el = document.getElementById("yt-video-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleYtVideosSettings(btn) {
            var el = document.getElementById("yt-videos-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }
        function toggleBreakSettings(btn) {
            var el = document.getElementById("break-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }

        (function() {
            var LAYER_STORAGE_KEY = "stream_production_layer_order";
            var DEFAULT_ORDER = [
                "scene-overlay-all-vdo",   /* 1 = BG (very back) */
                "scene-overlay-full-shared-panel", /* 2 = Shared (Partial) inset panel */
                "scene-overlay-vdo-full",  /* 3 = VDO full */
                "logos-overlay",           /* Logos */
                "sc2-overlay",            /* SC2 panel */
                "container",
                "right-column-result",
                "scoreboard-overlay",
                "external-chart-overlay",
                "chart-container",
                "gif-container",           /* Player intros – top by default so they show over YT/Schedule */
                "video-container",
                "player-name-box"
            ];
            var LABELS = {
                "scene-overlay-all-vdo": "BG",
                "scene-overlay-full-shared-panel": "Shared (Partial) panel",
                "scene-overlay-vdo-full": "VDO full",
                "logos-overlay": "Logos",
                "sc2-overlay": "SC2",
                "container": "Main layout",
                "gif-container": "Player intros – GIF",
                "chart-container": "Player ratings – chart",
                "video-container": "Player intros – video",
                "right-column-result": "Status text",
                "scoreboard-overlay": "Scoreboard",
                "external-chart-overlay": "Player ratings – external chart",
                "player-name-box": "Player name"
            };

            function getStoredOrder() {
                try {
                    var raw = localStorage.getItem(LAYER_STORAGE_KEY);
                    if (raw) {
                        var parsed = JSON.parse(raw);
                        var order = Array.isArray(parsed) ? parsed : (parsed.order || DEFAULT_ORDER);
                        var filtered = order.filter(function(id) { return DEFAULT_ORDER.indexOf(id) !== -1; });
                        /* Merge in any DEFAULT_ORDER ids missing from stored (e.g. new layers) */
                        DEFAULT_ORDER.forEach(function(id) {
                            if (filtered.indexOf(id) === -1) {
                                filtered.splice(DEFAULT_ORDER.indexOf(id), 0, id);
                            }
                        });
                        return filtered;
                    }
                } catch (e) {}
                return DEFAULT_ORDER.slice();
            }

            function saveOrder(order) {
                try {
                    localStorage.setItem(LAYER_STORAGE_KEY, JSON.stringify(order));
                } catch (e) {}
            }

            function getElementByLayerId(id) {
                if (id === "logos-overlay") {
                    return document.getElementById("logos-overlay");
                }
                if (id === "sc2-overlay") {
                    return document.getElementById("sc2-overlay");
                }
                return document.querySelector("[data-layer-id=\"" + id + "\"]");
            }

            /* z-index scale: 10000 per slot so top layers (10+) exceed scene overlays (99999) */
            var Z_INDEX_BASE = 10000;
            function applyOrder(order) {
                order.forEach(function(id, index) {
                    var el = getElementByLayerId(id);
                    if (!el) return;
                    if (id === "right-column-result" && el.style) {
                        el.style.position = "relative";
                    }
                    el.style.zIndex = String((index + 1) * Z_INDEX_BASE);
                });
            }

            function showOutlineFor(id) {
                document.body.classList.add("layer-outline-debug");
                document.querySelectorAll("[data-layer-id]").forEach(function(el) {
                    el.classList.toggle("layer-outline-active", el.getAttribute("data-layer-id") === id);
                });
            }

            function hideOutline() {
                document.body.classList.remove("layer-outline-debug");
                document.querySelectorAll("[data-layer-id]").forEach(function(el) {
                    el.classList.remove("layer-outline-active");
                });
            }

            function buildLayerList() {
                var list = document.getElementById("layer-list");
                if (!list) return;
                var order = getStoredOrder();

                list.innerHTML = "";
                order.forEach(function(id) {
                    var li = document.createElement("li");
                    li.className = "layer-list-item";
                    li.setAttribute("data-layer-id", id);
                    li.setAttribute("draggable", "true");
                    li.textContent = LABELS[id] || id;
                    list.appendChild(li);
                });

                list.querySelectorAll(".layer-list-item").forEach(function(li) {
                    var id = li.getAttribute("data-layer-id");
                    li.addEventListener("dragstart", function(e) {
                        e.dataTransfer.setData("text/plain", id);
                        e.dataTransfer.effectAllowed = "move";
                        li.classList.add("layer-dragging");
                        showOutlineFor(id);
                    });
                    li.addEventListener("dragend", function() {
                        li.classList.remove("layer-dragging");
                        hideOutline();
                        var newOrder = [].map.call(list.querySelectorAll(".layer-list-item"), function(el) {
                            return el.getAttribute("data-layer-id");
                        });
                        saveOrder(newOrder);
                        applyOrder(newOrder);
                    });
                    li.addEventListener("dragover", function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = "move";
                        var target = e.currentTarget;
                        var dragging = list.querySelector(".layer-dragging");
                        if (!dragging || dragging === target) return;
                        list.insertBefore(dragging, target);
                    });
                });
            }

            function resetToDefault() {
                saveOrder(DEFAULT_ORDER.slice());
                applyOrder(DEFAULT_ORDER);
                buildLayerList();
                hideOutline();
            }

            document.getElementById("layer-reset-btn").addEventListener("click", resetToDefault);

            var DEFAULT_SETTINGS = {
                version: 1,
                volume: 50,
                layerOrder: DEFAULT_ORDER.slice(),
                status: { title: "", teamA: "", teamB: "", valueA: "", valueB: "" },
                playerRatings: { playerName: "littlereaper", division: "A" },
                logos: { s10: false, fslSmall: false, vdoLarge: true, sc2: false, positions: {}, sc2Panel: null, vdoFullPanel: { left: 50, top: 100, width: 1180, height: 570 } },
                scenes: { bg: false, vdoFull: false, logos: false, sc2: false },
                playerIntros: ["DarkMenace", "LittleReaper", "PulledTheBoys", "HyperTurtle", "Harouz", "InfiniteCyclists", "Random Music", "FSL intro", "GG", "Match GG"],
                chromaKey: true,
                ytCrop: { top: 150, left: 10, right: 20, bottom: 100 },
                ytIframeVideos: [
                    { label: "INTRO", url: "https://www.youtube.com/watch?v=vt04Xbq57Dk", vol: 100 },
                    { label: "BREAK", url: "https://youtu.be/O9lNetcn9Y8?si=FaqwLX5I9KkoJecK", vol: 100 }
                ],
                breakSettings: { min: 5, sec: 0, msg: "be right back..." },
                musicMode: 'sequence',
                musicVol: 22,
                musicFade: 4
            };

            function persistEditModePositions() {
                if (typeof logosEditMode === "undefined" || !logosEditMode) return;
                var overlay = document.getElementById("logos-overlay");
                var sc2OverlayEl = document.getElementById("sc2-overlay");
                var sc2PanelEl = document.getElementById("sc2-panel-wrap");
                var vdoFullOverlayEl = document.getElementById("scene-overlay-vdo-full");
                var vdoFullPanelEl = document.getElementById("vdo-full-panel-wrap");
                var saved = getSavedLogoPositions();
                logoIds.forEach(function(id) {
                    var el = document.getElementById(id);
                    var key = logoIdToKey[id];
                    if (!el || !key || !$(el).data("ui-draggable")) return;
                    var off = el.getBoundingClientRect();
                    var par = overlay ? overlay.getBoundingClientRect() : { left: 0, top: 0 };
                    saved[key] = { left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) };
                });
                setSavedLogoPositions(saved);
                if (sc2PanelEl && $(sc2PanelEl).data("ui-draggable") && sc2OverlayEl) {
                    var off = sc2PanelEl.getBoundingClientRect();
                    var par = sc2OverlayEl.getBoundingClientRect();
                    setSavedSc2Panel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                }
                if (vdoFullPanelEl && $(vdoFullPanelEl).data("ui-draggable") && vdoFullOverlayEl) {
                    var off = vdoFullPanelEl.getBoundingClientRect();
                    var par = vdoFullOverlayEl.getBoundingClientRect();
                    setSavedVdoFullPanel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                }
            }

            function exportAllSettings() {
                persistEditModePositions();
                var s = function(id) { var el = document.getElementById(id); return el ? el.value : ""; };
                var order = getStoredOrder();
                var logoPositions = {};
                try {
                    var raw = localStorage.getItem("stream_production_logo_positions");
                    if (raw) logoPositions = JSON.parse(raw);
                } catch (e) {}
                var sc2PanelPos = null;
                var vdoFullPanelPos = null;
                try {
                    var r2 = localStorage.getItem("stream_production_sc2_panel");
                    if (r2) sc2PanelPos = JSON.parse(r2);
                } catch (e2) {}
                try {
                    var r3 = localStorage.getItem("stream_production_vdo_full_panel");
                    if (r3) vdoFullPanelPos = JSON.parse(r3);
                } catch (e3) {}
                var sceneBg = document.getElementById("scene-overlay-all-vdo");
                var sceneVdo = document.getElementById("scene-overlay-vdo-full");
                var overlay = document.getElementById("logos-overlay");
                var sc2OverlayExport = document.getElementById("sc2-overlay");
                var intros = [1,2,3,4,5,6,7,8,9,10].map(function(n) {
                    var f = document.getElementById("media-form-" + n);
                    var inp = f ? f.querySelector(".player-name-input") : null;
                    return inp ? inp.value : "";
                });
                var volEl = document.getElementById("volume-slider");
                var out = {
                    version: 1,
                    volume: volEl ? parseInt(volEl.value, 10) : 50,
                    layerOrder: order,
                    status: { title: s("status-title"), teamA: s("status-teamA"), teamB: s("status-teamB"), valueA: s("status-valueA"), valueB: s("status-valueB") },
                    playerRatings: { playerName: s("player-input"), division: s("division-input") },
                    logos: { s10: !!document.getElementById("logo-s10-cb") && document.getElementById("logo-s10-cb").checked, fslSmall: !!document.getElementById("logo-fsl-small-cb") && document.getElementById("logo-fsl-small-cb").checked, vdoLarge: !!document.getElementById("logo-vdo-large-cb") && document.getElementById("logo-vdo-large-cb").checked, sc2: !!document.getElementById("logo-sc2-cb") && document.getElementById("logo-sc2-cb").checked, positions: logoPositions, sc2Panel: sc2PanelPos, vdoFullPanel: vdoFullPanelPos },
                    scenes: { bg: sceneBg && sceneBg.style.display === "block", vdoFull: sceneVdo && sceneVdo.style.display === "block", logos: overlay && overlay.style.display === "block", sc2: sc2OverlayExport && sc2OverlayExport.style.display === "block" },
                    playerIntros: intros,
                    chromaKey: !!document.getElementById("chroma-key-cb") && document.getElementById("chroma-key-cb").checked,
                    ytCrop: typeof getYtCrop === "function" ? getYtCrop() : { top: 150, left: 10, right: 20, bottom: 100 },
                    ytIframeVideos: typeof getYtIframeVideos === "function" ? getYtIframeVideos() : DEFAULT_SETTINGS.ytIframeVideos,
                    breakSettings: typeof getBreakSettings === "function" ? getBreakSettings() : DEFAULT_SETTINGS.breakSettings,
                    musicMode: (function() { var el = document.querySelector('input[name="music-mode"]:checked'); return el ? el.value : 'sequence'; })(),
                musicVol: (function() { var el = document.getElementById('lpMusicVol'); return el ? parseFloat(el.value) : 22; })(),
                musicFade: (function() { var el = document.getElementById('lpMusicFade'); return el ? parseFloat(el.value) : 1.5; })(),
                sceneMoodMap: (typeof MX_SCENE_MAP !== 'undefined' ? MX_SCENE_MAP : {}),
                moodSongs:    (typeof MX_TRACKS    !== 'undefined' ? MX_TRACKS    : {})
                };
                return out;
            }

            function importAllSettings(parsed) {
                var def = DEFAULT_SETTINGS;
                if (!parsed || typeof parsed !== "object") return;
                var set = function(id, val) { var el = document.getElementById(id); if (el && val !== undefined) el.value = String(val); };
                var setCheck = function(id, val) { var el = document.getElementById(id); if (el) el.checked = !!val; };
                if (parsed.volume !== undefined) { var vs = document.getElementById("volume-slider"); if (vs) vs.value = Math.max(5, Math.min(100, parseInt(parsed.volume, 10) || 50)); }
                var order = parsed.layerOrder || parsed.order;
                if (order && Array.isArray(order)) {
                    var filtered = order.filter(function(id) { return DEFAULT_ORDER.indexOf(id) !== -1; });
                    DEFAULT_ORDER.forEach(function(id) {
                        if (filtered.indexOf(id) === -1) {
                            filtered.splice(DEFAULT_ORDER.indexOf(id), 0, id);
                        }
                    });
                    if (filtered.length) { saveOrder(filtered); applyOrder(filtered); buildLayerList(); }
                }
                var st = parsed.status || def.status;
                if (st) { set("status-title", st.title); set("status-teamA", st.teamA); set("status-teamB", st.teamB); set("status-valueA", st.valueA); set("status-valueB", st.valueB); }
                var pr = parsed.playerRatings || def.playerRatings;
                if (pr) { set("player-input", pr.playerName); set("division-input", pr.division); }
                var lo = parsed.logos || def.logos;
                if (lo) {
                    setCheck("logo-s10-cb", lo.s10);
                    setCheck("logo-fsl-small-cb", lo.fslSmall);
                    setCheck("logo-vdo-large-cb", lo.vdoLarge !== false);
                    setCheck("logo-sc2-cb", lo.sc2);
                    if (lo.positions && typeof lo.positions === "object") { try { localStorage.setItem("stream_production_logo_positions", JSON.stringify(lo.positions)); } catch (e) {} }
                    if (lo.sc2Panel && typeof lo.sc2Panel === "object" && lo.sc2Panel.left != null) { try { localStorage.setItem("stream_production_sc2_panel", JSON.stringify(lo.sc2Panel)); } catch (e) {} }
                    if (lo.vdoFullPanel && typeof lo.vdoFullPanel === "object" && lo.vdoFullPanel.left != null) { try { localStorage.setItem("stream_production_vdo_full_panel", JSON.stringify(lo.vdoFullPanel)); } catch (e) {} }
                }
                var sc = parsed.scenes || def.scenes;
                if (sc) {
                    var o1 = document.getElementById("scene-overlay-all-vdo"); var b1 = document.getElementById("scene-btn-all-vdo");
                    var videoIframe = document.getElementById("scene-overlay-all-vdo-iframe");
                    if (o1 && b1) {
                        if (sc.bg) {
                            VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById("scene-btn-" + k); if (b) b.classList.remove("active"); });
                            if (videoIframe) videoIframe.src = "2026/video_player.php?v=" + encodeURIComponent(VIDEO_OVERLAY_FILES["all-vdo"]) + "&_t=" + Date.now();
                            o1.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1';
                            o1.style.display = "block";
                            b1.classList.add("active");
                        } else {
                            o1.style.display = "none";
                            b1.classList.remove("active");
                            if (videoIframe) videoIframe.removeAttribute("src");
                        }
                    }
                    var o2 = document.getElementById("scene-overlay-vdo-full"); var b2 = document.getElementById("scene-btn-vdo-full");
                    if (o2 && b2) {
                        o2.style.display = sc.vdoFull ? "block" : "none";
                        b2.classList.toggle("active", !!sc.vdoFull);
                        if (sc.vdoFull) {
                            var vdoPanel = document.getElementById("vdo-full-panel-wrap");
                            var vdoPos = (parsed.logos && parsed.logos.vdoFullPanel && parsed.logos.vdoFullPanel.left != null) ? parsed.logos.vdoFullPanel : null;
                            if (!vdoPos) try { var rv = localStorage.getItem("stream_production_vdo_full_panel"); if (rv) vdoPos = JSON.parse(rv); } catch (ev) {}
                            if (vdoPos && (vdoPos.width == null || vdoPos.width <= 0 || vdoPos.height == null || vdoPos.height <= 0) && o2) {
                                vdoPos = getDefaultVdoFullPanelPosition(o2.getBoundingClientRect());
                            }
                            if (vdoPanel && vdoPos) { vdoPanel.style.left = vdoPos.left + "px"; vdoPanel.style.top = vdoPos.top + "px"; vdoPanel.style.width = vdoPos.width + "px"; vdoPanel.style.height = vdoPos.height + "px"; }
                            var vdoFullIframe = vdoPanel ? vdoPanel.querySelector("iframe") : null;
                            ensureVdoIframeLoaded(vdoFullIframe);
                        }
                    }
                    var o3 = document.getElementById("logos-overlay"); var b3 = document.getElementById("scene-btn-logos");
                    if (o3 && b3) { o3.style.display = sc.logos ? "block" : "none"; b3.classList.toggle("active", !!sc.logos); }
                    var o4 = document.getElementById("sc2-overlay");
                    if (o4) {
                        o4.style.display = sc.sc2 ? "block" : "none";
                        setSc2ButtonsActive(!!sc.sc2);
                        if (sc.sc2 && o1) o1.style.display = "none";
                        if (sc.sc2) {
                            var sc2PanelImport = document.getElementById("sc2-panel-wrap");
                            if (sc2PanelImport) {
                                sc2PanelImport.style.display = "block";
                                var sc2Pos = getSavedSc2Panel() || (o4 ? getDefaultSc2PanelPosition(o4.getBoundingClientRect()) : null);
                                if (sc2Pos) applyPositionToSc2Panel(sc2PanelImport, sc2Pos);
                                var sc2IframeImport = sc2PanelImport.querySelector("iframe");
                                ensureVdoIframeLoaded(sc2IframeImport);
                            }
                        }
                    }
                }
                var yc = parsed.ytCrop;
                if (yc && typeof yc === "object" && (yc.top != null || yc.left != null || yc.right != null || yc.bottom != null)) {
                    if (typeof setYtCrop === "function") setYtCrop(yc);
                    if (typeof saveYtCrop === "function") saveYtCrop();
                    if (typeof applyYtCropToVideo === "function") applyYtCropToVideo();
                }
                var ytv = parsed.ytIframeVideos;
                if (ytv && Array.isArray(ytv) && ytv.length >= 1) {
                    if (typeof setYtIframeVideos === "function") {
                        setYtIframeVideos(ytv);
                        if (typeof saveYtIframeVideos === "function") saveYtIframeVideos();
                    }
                }
                var bks = parsed.breakSettings;
                if (bks && typeof bks === "object") {
                    if (typeof setBreakSettings === "function") {
                        setBreakSettings(bks);
                        if (typeof saveBreakSettings === "function") saveBreakSettings();
                    }
                }
                var intros = parsed.playerIntros;
                if (intros && Array.isArray(intros)) {
                    intros.forEach(function(val, i) {
                        var f = document.getElementById("media-form-" + (i + 1));
                        var inp = f ? f.querySelector(".player-name-input") : null;
                        if (inp) inp.value = val !== undefined && val !== null ? String(val) : "";
                    });
                }
                var chromaCb = document.getElementById("chroma-key-cb");
                if (chromaCb && parsed.chromaKey !== undefined) chromaCb.checked = !!parsed.chromaKey;
                if (parsed.musicMode) {
                    var modeEl = document.querySelector('input[name="music-mode"][value="' + parsed.musicMode + '"]');
                    if (modeEl) modeEl.checked = true;
                }
                (function() {
                    var fireChange = function(id, val) {
                        var el = document.getElementById(id);
                        if (el && val !== undefined && val !== null) {
                            el.value = val;
                            el.dispatchEvent(new Event('change'));
                        }
                    };
                    if (parsed.musicVol !== undefined) fireChange('lpMusicVol', Math.max(5, parsed.musicVol));
                    if (parsed.musicFade !== undefined) fireChange('lpMusicFade', parsed.musicFade);
                })();
                if (parsed.sceneMoodMap && typeof parsed.sceneMoodMap === 'object') {
                    MX_SCENE_MAP = parsed.sceneMoodMap;
                    var smmEd = document.getElementById('mx-scene-map-editor');
                    if (smmEd) smmEd.value = JSON.stringify(MX_SCENE_MAP, null, 2);
                }
                if (parsed.moodSongs && typeof parsed.moodSongs === 'object') {
                    MX_TRACKS = parsed.moodSongs;
                    var mseEd = document.getElementById('mx-mood-songs-editor');
                    if (mseEd) mseEd.value = JSON.stringify(MX_TRACKS, null, 2);
                    if (typeof mxBuildGrid === 'function') mxBuildGrid();
                }
                if (window.updateLogosOverlay) window.updateLogosOverlay();
                if (window.updateSc2Panel) window.updateSc2Panel();
                if (window.reapplyLayerOrder) window.reapplyLayerOrder();
            }

            window.reapplyLayerOrder = function() { applyOrder(getStoredOrder()); };
            window.exportAllSettings = exportAllSettings;
            window.importAllSettings = importAllSettings;

            document.getElementById("layer-export-btn").addEventListener("click", function() {
                var out = exportAllSettings();
                var blob = new Blob([JSON.stringify(out, null, 2)], { type: "application/json" });
                var a = document.createElement("a");
                a.href = URL.createObjectURL(blob);
                a.download = "stream_production_settings.json";
                a.click();
                URL.revokeObjectURL(a.href);
            });

            document.getElementById("layer-save-server-btn").addEventListener("click", function() {
                var btn = this;
                var out = exportAllSettings();
                btn.disabled = true;
                fetch("settings.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(out)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (res && res.ok) {
                        btn.textContent = "Saved";
                        setTimeout(function() { btn.textContent = "Save to server"; btn.disabled = false; }, 1500);
                    } else {
                        btn.textContent = "Error";
                        setTimeout(function() { btn.textContent = "Save to server"; btn.disabled = false; }, 2000);
                    }
                }).catch(function() {
                    btn.textContent = "Error";
                    setTimeout(function() { btn.textContent = "Save to server"; btn.disabled = false; }, 2000);
                });
            });

            document.getElementById("layer-import-btn").addEventListener("click", function() {
                document.getElementById("layer-import-file").click();
            });
            document.getElementById("layer-import-file").addEventListener("change", function() {
                var file = this.files[0];
                if (!file) return;
                var self = this;
                var r = new FileReader();
                r.onload = function() {
                    try {
                        var parsed = JSON.parse(r.result);
                        if (parsed.layerOrder !== undefined || parsed.order !== undefined || parsed.status !== undefined) {
                            importAllSettings(parsed);
                        } else if (Array.isArray(parsed)) {
                            var order = parsed.filter(function(id) { return DEFAULT_ORDER.indexOf(id) !== -1; });
                            if (order.length) { saveOrder(order); applyOrder(order); buildLayerList(); }
                        } else {
                            importAllSettings(parsed);
                        }
                    } catch (err) {}
                    self.value = "";
                };
                r.readAsText(file);
            });

            var scoreboardSaveBtn = document.getElementById("scoreboard-csv-save-btn");
            if (scoreboardSaveBtn) {
                scoreboardSaveBtn.addEventListener("click", function() {
                    var ta = document.getElementById("scoreboard-csv-input");
                    var csv = ta ? ta.value : "";
                    var btn = this;
                    btn.disabled = true;
                    fetch("save_scoreboard.php", {
                        method: "POST",
                        headers: { "Content-Type": "text/csv; charset=utf-8" },
                        body: csv
                    }).then(function(r) { return r.json(); }).then(function(res) {
                        if (res && res.ok) {
                            btn.textContent = "Saved";
                            if (typeof loadAndRenderScoreboard === "function") {
                                var overlay = document.getElementById("scoreboard-overlay");
                                if (overlay && overlay.style.display === "block") loadAndRenderScoreboard();
                            }
                        } else {
                            btn.textContent = "Error";
                        }
                        setTimeout(function() { btn.textContent = "Overwrite scoreboard.csv"; btn.disabled = false; }, 2000);
                    }).catch(function() {
                        btn.textContent = "Error";
                        setTimeout(function() { btn.textContent = "Overwrite scoreboard.csv"; btn.disabled = false; }, 2000);
                    });
                });
            }

            // --- Scoreboard Score Editor ---
            var _sbEditorRows = null;

            window.scoreboardEditorLoad = function scoreboardEditorLoad() {
                var statusEl = document.getElementById('scoreboard-load-status');
                var contentEl = document.getElementById('scoreboard-editor-content');
                var btn = document.getElementById('scoreboard-load-btn');
                if (statusEl) statusEl.textContent = 'Loading…';
                if (btn) btn.disabled = true;
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var baseUrl = base ? base + '/' : './';
                fetch(baseUrl + '2026/scoreboard.csv?_t=' + Date.now())
                    .then(function(r) { if (!r.ok) throw new Error(r.status); return r.text(); })
                    .then(function(text) {
                        var rows = parseCSV(text);
                        _sbEditorRows = rows;
                        scoreboardEditorRender(rows);
                        if (contentEl) contentEl.style.display = 'block';
                        var nonEmpty = rows.filter(function(r) { return r.some(function(c) { return c !== ''; }); }).length;
                        if (statusEl) statusEl.textContent = nonEmpty + ' rows loaded.';
                        var ta = document.getElementById('scoreboard-csv-input');
                        if (ta) ta.value = text.trim();
                    })
                    .catch(function(err) {
                        if (statusEl) statusEl.textContent = 'Error: ' + err.message;
                    })
                    .then(function() {
                        if (btn) btn.disabled = false;
                    });
            }

            window.sbRecalcTotals = function sbRecalcTotals() {
                var totalA = 0, totalB = 0;
                document.querySelectorAll('.sb-score-a-input').forEach(function(el) { totalA += parseInt(el.value, 10) || 0; });
                document.querySelectorAll('.sb-score-b-input').forEach(function(el) { totalB += parseInt(el.value, 10) || 0; });
                var elA = document.getElementById('sb-score-a');
                var elB = document.getElementById('sb-score-b');
                if (elA) elA.textContent = totalA;
                if (elB) elB.textContent = totalB;
            }

            function scoreboardEditorRender(rows) {
                if (!rows || rows.length === 0) return;
                var r0 = rows[0] || [];
                var teamA = (r0[2] || '').trim();
                var teamB = (r0[6] || '').trim();
                var nameAEl = document.getElementById('sb-team-a-name');
                var nameBEl = document.getElementById('sb-team-b-name');
                if (nameAEl) { nameAEl.textContent = teamA; nameAEl.title = teamA; }
                if (nameBEl) { nameBEl.textContent = teamB; nameBEl.title = teamB; }
                var container = document.getElementById('scoreboard-matchup-rows');
                if (!container) return;
                container.innerHTML = '';
                for (var i = 2; i < rows.length; i++) {
                    var row = rows[i];
                    var isEmpty = !row[1] && !row[2] && !row[3] && !row[4] && !row[6] && !row[7] && !row[8] && !row[10] && !row[11];
                    if (isEmpty) continue;
                    var matchType = (row[1] || '').trim();
                    var pA1 = (row[2] || '').trim();
                    var pA2 = (row[3] || '').trim();
                    var sA = row[4] || '';
                    var pB1 = (row[6] || '').trim();
                    var pB2 = (row[7] || '').trim();
                    var sB = row[8] || '';
                    var map1 = (row[10] || '').trim();
                    var map2 = (row[11] || '').trim();
                    var playersA = pA2 ? pA1 + ' / ' + pA2 : pA1;
                    var playersB = pB2 ? pB1 + ' / ' + pB2 : pB1;
                    var mapsText = [map1, map2].filter(Boolean).join(' | ');
                    var div = document.createElement('div');
                    div.className = 'sb-matchup-row';
                    div.dataset.rowIdx = i;
                    div.style.cssText = 'display:flex; align-items:center; gap:6px; padding:5px 8px; background:#111; border-radius:3px; font-size:12px; margin-bottom:3px;';
                    div.innerHTML =
                        '<span style="color:#666; min-width:28px; font-size:11px; flex-shrink:0;">' + sbEsc(matchType) + '</span>' +
                        '<span style="flex:1; text-align:right; color:#bbb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:4px;" title="' + sbEsc(playersA) + '">' + sbEsc(playersA) + '</span>' +
                        '<input type="number" class="sb-score-a-input" data-row="' + i + '" min="0" max="99" value="' + sbEsc(String(sA)) + '" oninput="sbRecalcTotals()" style="width:44px; text-align:center; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px; padding:2px 4px; flex-shrink:0;">' +
                        '<span style="color:#555; flex-shrink:0;">–</span>' +
                        '<input type="number" class="sb-score-b-input" data-row="' + i + '" min="0" max="99" value="' + sbEsc(String(sB)) + '" oninput="sbRecalcTotals()" style="width:44px; text-align:center; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px; padding:2px 4px; flex-shrink:0;">' +
                        '<span style="flex:1; text-align:left; color:#bbb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:4px;" title="' + sbEsc(playersB) + '">' + sbEsc(playersB) + '</span>' +
                        '<span style="color:#555; font-size:11px; min-width:56px; text-align:right; flex-shrink:0;">' + sbEsc(mapsText) + '</span>';
                    container.appendChild(div);
                }
                sbRecalcTotals();
            }

            function sbEsc(s) {
                if (s == null) return '';
                var d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML;
            }

            window.scoreboardEditorSave = function scoreboardEditorSave() {
                if (!_sbEditorRows) { alert('Load the CSV first.'); return; }
                var statusEl = document.getElementById('scoreboard-save-status');
                var btn = document.getElementById('scoreboard-score-save-btn');
                if (btn) btn.disabled = true;
                if (statusEl) statusEl.textContent = 'Saving…';
                var rows = _sbEditorRows;
                var elA = document.getElementById('sb-score-a');
                var elB = document.getElementById('sb-score-b');
                if (elA && rows[0]) rows[0][4] = elA.textContent.trim();
                if (elB && rows[0]) rows[0][8] = elB.textContent.trim();
                document.querySelectorAll('.sb-matchup-row').forEach(function(el) {
                    var rowIdx = parseInt(el.dataset.rowIdx, 10);
                    var inpSA = el.querySelector('.sb-score-a-input');
                    var inpSB = el.querySelector('.sb-score-b-input');
                    if (rows[rowIdx]) {
                        if (inpSA) rows[rowIdx][4] = inpSA.value;
                        if (inpSB) rows[rowIdx][8] = inpSB.value;
                    }
                });
                var csv = rows.map(function(row) {
                    return row.map(function(cell) {
                        var s = String(cell == null ? '' : cell);
                        if (s === '') return '""';
                        if (/^\d+(\.\d+)?$/.test(s)) return s;
                        return '"' + s.replace(/"/g, '""') + '"';
                    }).join(',');
                }).join('\n');
                var ta = document.getElementById('scoreboard-csv-input');
                if (ta) ta.value = csv;
                fetch('save_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'text/csv; charset=utf-8' },
                    body: csv
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (res && res.ok) {
                        if (statusEl) statusEl.textContent = 'Saved!';
                        if (typeof loadAndRenderScoreboard === 'function') {
                            var overlay = document.getElementById('scoreboard-overlay');
                            if (overlay && overlay.style.display === 'block') loadAndRenderScoreboard();
                        }
                    } else {
                        if (statusEl) statusEl.textContent = 'Error saving.';
                    }
                    setTimeout(function() { if (statusEl) statusEl.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                }).catch(function() {
                    if (statusEl) statusEl.textContent = 'Network error.';
                    setTimeout(function() { if (statusEl) statusEl.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                });
            }

            function doRefreshRankings() {
                var rankingsRefreshBtn = document.getElementById("rankings-refresh-btn");
                var rankingsRefreshStatus = document.getElementById("rankings-refresh-status");
                if (rankingsRefreshBtn && rankingsRefreshStatus) {
                    rankingsRefreshStatus.textContent = "…";
                    rankingsRefreshBtn.disabled = true;
                }
                fetch("rankings.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ refresh: true }) }).then(function(r) { return r.json(); }).then(function(result) {
                    if (result && result.ok) {
                        if (typeof window.reloadRankingsCache === "function") window.reloadRankingsCache();
                        if (rankingsRefreshStatus) rankingsRefreshStatus.textContent = "Updated.";
                    } else {
                        if (rankingsRefreshStatus) rankingsRefreshStatus.textContent = result && result.error ? result.error : "Save failed.";
                    }
                }).catch(function(e) {
                    if (rankingsRefreshStatus) rankingsRefreshStatus.textContent = "Error: " + (e.message || "fetch failed");
                }).finally(function() {
                    if (rankingsRefreshBtn) rankingsRefreshBtn.disabled = false;
                    if (rankingsRefreshStatus) setTimeout(function() { rankingsRefreshStatus.textContent = ""; }, 4000);
                });
            }
            var rankingsRefreshBtn = document.getElementById("rankings-refresh-btn");
            var rankingsRefreshStatus = document.getElementById("rankings-refresh-status");
            if (rankingsRefreshBtn && rankingsRefreshStatus) {
                rankingsRefreshBtn.addEventListener("click", doRefreshRankings);
            }
            doRefreshRankings();

            applyOrder(getStoredOrder());
            buildLayerList();

            fetch("settings.php").then(function(r) { return r.json(); }).then(function(parsed) {
                if (parsed && (parsed.layerOrder !== undefined || parsed.order !== undefined || parsed.status !== undefined || parsed.version !== undefined)) {
                    importAllSettings(parsed);
                }
                var shouldShowBg = !parsed || !parsed.scenes || parsed.scenes.bg === true;
                if (shouldShowBg && typeof showBgVideoOverlay === 'function') showBgVideoOverlay();
            }).catch(function() {
                if (typeof showBgVideoOverlay === 'function') showBgVideoOverlay();
            });
        })();

        function toggleForms(btn) {
            var content = document.getElementById("player-intros-list");
            if (content.style.display === "none" || content.style.display === "") {
                content.style.display = "block";
            } else {
                content.style.display = "none";
            }
            if (btn) btn.classList.toggle('open', content.style.display === 'block');
        }

        function toggleLogosSettings(btn) {
            var el = document.getElementById("logos-settings-section");
            if (el.style.display === "none" || !el.style.display) {
                el.style.display = "block";
            } else {
                el.style.display = "none";
            }
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
        }

        var LOGO_POSITIONS_KEY = "stream_production_logo_positions";
        var SC2_PANEL_KEY = "stream_production_sc2_panel";
        var logosEditMode = false;
        var logoIdToKey = { "logo-s10-wrap": "s10", "logo-fsl-small-wrap": "fsl-small" };
        var logoIds = ["logo-s10-wrap", "logo-fsl-small-wrap"];

        function getSavedLogoPositions() {
            try {
                var raw = localStorage.getItem(LOGO_POSITIONS_KEY);
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === "object") return parsed;
                }
            } catch (e) {}
            return {};
        }

        function setSavedLogoPositions(obj) {
            try {
                localStorage.setItem(LOGO_POSITIONS_KEY, JSON.stringify(obj));
            } catch (e) {}
        }

        function getSavedSc2Panel() {
            try {
                var raw = localStorage.getItem(SC2_PANEL_KEY);
                if (raw) {
                    var p = JSON.parse(raw);
                    if (p && typeof p === "object" && p.left != null) return p;
                }
            } catch (e) {}
            return null;
        }
        function setSavedSc2Panel(obj) {
            try {
                localStorage.setItem(SC2_PANEL_KEY, JSON.stringify(obj));
            } catch (e) {}
        }
        var YT_CROP_KEY = "stream_production_yt_crop";
        var YT_CROP_DEFAULTS = { top: 150, left: 10, right: 20, bottom: 100 };
        function getYtCrop() {
            var topEl = document.getElementById("yt-crop-top");
            var leftEl = document.getElementById("yt-crop-left");
            var rightEl = document.getElementById("yt-crop-right");
            var bottomEl = document.getElementById("yt-crop-bottom");
            if (!topEl || !leftEl || !rightEl || !bottomEl) return YT_CROP_DEFAULTS;
            var t = parseInt(topEl.value, 10); var l = parseInt(leftEl.value, 10);
            var r = parseInt(rightEl.value, 10); var b = parseInt(bottomEl.value, 10);
            return { top: isNaN(t) ? YT_CROP_DEFAULTS.top : Math.max(0, t), left: isNaN(l) ? YT_CROP_DEFAULTS.left : Math.max(0, l), right: isNaN(r) ? YT_CROP_DEFAULTS.right : Math.max(0, r), bottom: isNaN(b) ? YT_CROP_DEFAULTS.bottom : Math.max(0, b) };
        }
        function setYtCrop(obj) {
            var topEl = document.getElementById("yt-crop-top");
            var leftEl = document.getElementById("yt-crop-left");
            var rightEl = document.getElementById("yt-crop-right");
            var bottomEl = document.getElementById("yt-crop-bottom");
            if (topEl) topEl.value = (obj && obj.top != null) ? obj.top : YT_CROP_DEFAULTS.top;
            if (leftEl) leftEl.value = (obj && obj.left != null) ? obj.left : YT_CROP_DEFAULTS.left;
            if (rightEl) rightEl.value = (obj && obj.right != null) ? obj.right : YT_CROP_DEFAULTS.right;
            if (bottomEl) bottomEl.value = (obj && obj.bottom != null) ? obj.bottom : YT_CROP_DEFAULTS.bottom;
        }
        function saveYtCrop() {
            try { localStorage.setItem(YT_CROP_KEY, JSON.stringify(getYtCrop())); } catch (e) {}
        }
        function loadYtCrop() {
            try {
                var raw = localStorage.getItem(YT_CROP_KEY);
                if (raw) { var p = JSON.parse(raw); if (p && typeof p === "object") setYtCrop(p); }
            } catch (e) {}
        }
        function applyYtCropToVideo() {
            var crop = getYtCrop();
            var overlay = document.getElementById("scene-overlay-yt");
            var wrap = document.getElementById("yt-crop-wrap");
            var video = document.getElementById("yt-video");
            if (!overlay || !wrap || !video) return;
            var parent = overlay.parentElement || overlay.offsetParent;
            var rect = (parent && parent.getBoundingClientRect) ? parent.getBoundingClientRect() : { width: 1280, height: 720 };
            var w = rect.width || 1280;
            var h = rect.height || 720;
            var cropW = Math.max(1, w - crop.left - crop.right);
            var cropH = Math.max(1, h - crop.top - crop.bottom);
            var scale = Math.min(w / cropW, h / cropH);
            wrap.style.clipPath = "";
            wrap.style.webkitClipPath = "";
            wrap.style.left = "50%";
            wrap.style.top = "50%";
            wrap.style.width = cropW + "px";
            wrap.style.height = cropH + "px";
            wrap.style.transform = "translate(-50%, -50%) scale(" + scale + ")";
            wrap.style.transformOrigin = "center center";
            wrap.style.overflow = "hidden";
            video.style.clipPath = "";
            video.style.webkitClipPath = "";
            video.style.position = "absolute";
            video.style.left = (-crop.left) + "px";
            video.style.top = (-crop.top) + "px";
            video.style.width = (w) + "px";
            video.style.height = (h) + "px";
            video.style.objectFit = "contain";
            video.style.objectPosition = "center center";
        }
        (function initYtCrop() {
            loadYtCrop();
            ["yt-crop-top", "yt-crop-left", "yt-crop-right", "yt-crop-bottom"].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener("change", function() { saveYtCrop(); applyYtCropToVideo(); });
            });
            window.addEventListener("resize", function() {
                var ytOverlay = document.getElementById("scene-overlay-yt");
                if (ytOverlay && ytOverlay.style.display === "block") applyYtCropToVideo();
            });
        })();
        var YT_IFRAME_VIDEOS_KEY = 'stream_production_yt_iframe_videos';
        var YT_IFRAME_VIDEOS_DEFAULTS = [
            { label: 'INTRO', url: 'https://www.youtube.com/watch?v=vt04Xbq57Dk', vol: 100 },
            { label: 'BREAK', url: 'https://youtu.be/O9lNetcn9Y8?si=FaqwLX5I9KkoJecK', vol: 100 }
        ];

        function normalizeYtUrl(url) {
            if (!url || !url.trim()) return '';
            url = url.trim();
            var videoId = null;
            var m;
            /* youtu.be/ID */
            m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{11})/);
            if (m) videoId = m[1];
            /* youtube.com/watch?v=ID */
            if (!videoId) { m = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/); if (m) videoId = m[1]; }
            /* youtube.com/embed/ID */
            if (!videoId) { m = url.match(/\/embed\/([a-zA-Z0-9_-]{11})/); if (m) videoId = m[1]; }
            /* youtube.com/shorts/ID */
            if (!videoId) { m = url.match(/\/shorts\/([a-zA-Z0-9_-]{11})/); if (m) videoId = m[1]; }
            if (videoId) return 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&enablejsapi=1&controls=0';
            return url; /* unrecognised – pass through */
        }

        function updateYtResolvedDisplays() {
            [1, 2].forEach(function(n) {
                var u = document.getElementById('yt-video-' + n + '-url');
                var d = document.getElementById('yt-video-' + n + '-resolved');
                if (!u || !d) return;
                var raw = u.value.trim();
                if (!raw) { d.textContent = ''; return; }
                var resolved = normalizeYtUrl(raw);
                var isYt = /youtube\.com|youtu\.be/.test(raw);
                if (!isYt) {
                    d.style.color = '#fa8';
                    d.textContent = '⚠ Not a YouTube URL – used as-is';
                } else {
                    d.style.color = '#7c7';
                    d.textContent = '→ ' + resolved;
                }
            });
        }

        function getYtIframeVideos() {
            var l1 = document.getElementById('yt-video-1-label');
            var u1 = document.getElementById('yt-video-1-url');
            var v1 = document.getElementById('yt-video-1-vol');
            var l2 = document.getElementById('yt-video-2-label');
            var u2 = document.getElementById('yt-video-2-url');
            var v2 = document.getElementById('yt-video-2-vol');
            return [
                { label: (l1 && l1.value.trim()) || YT_IFRAME_VIDEOS_DEFAULTS[0].label, url: normalizeYtUrl((u1 && u1.value.trim()) || YT_IFRAME_VIDEOS_DEFAULTS[0].url), vol: v1 ? Math.max(5, Math.min(100, parseInt(v1.value, 10) || 100)) : 100 },
                { label: (l2 && l2.value.trim()) || YT_IFRAME_VIDEOS_DEFAULTS[1].label, url: normalizeYtUrl((u2 && u2.value.trim()) || YT_IFRAME_VIDEOS_DEFAULTS[1].url), vol: v2 ? Math.max(5, Math.min(100, parseInt(v2.value, 10) || 100)) : 100 }
            ];
        }

        function syncYtIframeButtonLabels() {
            var videos = getYtIframeVideos();
            var introBtn = document.getElementById('scene-btn-yt-intro');
            var breakBtn = document.getElementById('scene-btn-yt-break');
            if (introBtn) introBtn.textContent = videos[0].label;
            if (breakBtn) breakBtn.textContent = videos[1].label;
        }

        function saveYtIframeVideos() {
            var videos = getYtIframeVideos();
            try { localStorage.setItem(YT_IFRAME_VIDEOS_KEY, JSON.stringify(videos)); } catch (e) {}
        }

        function loadYtIframeVideos() {
            try {
                var raw = localStorage.getItem(YT_IFRAME_VIDEOS_KEY);
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) { setYtIframeVideos(parsed); return; }
                }
            } catch (e) {}
        }

        function setYtIframeVideos(arr) {
            var l1 = document.getElementById('yt-video-1-label');
            var u1 = document.getElementById('yt-video-1-url');
            var vol1 = document.getElementById('yt-video-1-vol');
            var l2 = document.getElementById('yt-video-2-label');
            var u2 = document.getElementById('yt-video-2-url');
            var vol2 = document.getElementById('yt-video-2-vol');
            var v0 = arr[0] || {};
            var v1 = arr[1] || {};
            if (l1) l1.value = v0.label != null ? v0.label : YT_IFRAME_VIDEOS_DEFAULTS[0].label;
            if (u1) u1.value = v0.url != null ? v0.url : YT_IFRAME_VIDEOS_DEFAULTS[0].url;
            if (vol1) vol1.value = v0.vol != null ? v0.vol : YT_IFRAME_VIDEOS_DEFAULTS[0].vol;
            if (l2) l2.value = v1.label != null ? v1.label : YT_IFRAME_VIDEOS_DEFAULTS[1].label;
            if (u2) u2.value = v1.url != null ? v1.url : YT_IFRAME_VIDEOS_DEFAULTS[1].url;
            if (vol2) vol2.value = v1.vol != null ? v1.vol : YT_IFRAME_VIDEOS_DEFAULTS[1].vol;
            syncYtIframeButtonLabels();
            updateYtResolvedDisplays();
        }

        (function initYtIframeVideos() {
            loadYtIframeVideos();
            syncYtIframeButtonLabels();
            updateYtResolvedDisplays();
            ['yt-video-1-label', 'yt-video-1-url', 'yt-video-2-label', 'yt-video-2-url'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', function() {
                    syncYtIframeButtonLabels();
                    updateYtResolvedDisplays();
                    saveYtIframeVideos();
                });
            });
            ['yt-video-1-vol', 'yt-video-2-vol'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', function() { saveYtIframeVideos(); });
            });
        })();

        /* ── Break countdown ─────────────────────────────────────── */
        var BREAK_SETTINGS_KEY = 'stream_production_break_settings';
        var BREAK_DEFAULTS = { min: 5, sec: 0, msg: 'be right back...' };
        var breakCountdownInterval = null;

        function getBreakSettings() {
            var m = document.getElementById('break-timer-min');
            var s = document.getElementById('break-timer-sec');
            var msg = document.getElementById('break-timer-msg');
            return {
                min: m ? Math.max(0, parseInt(m.value, 10) || 0) : BREAK_DEFAULTS.min,
                sec: s ? Math.max(0, Math.min(59, parseInt(s.value, 10) || 0)) : BREAK_DEFAULTS.sec,
                msg: msg ? msg.value : BREAK_DEFAULTS.msg
            };
        }

        function setBreakSettings(obj) {
            var m = document.getElementById('break-timer-min');
            var s = document.getElementById('break-timer-sec');
            var msg = document.getElementById('break-timer-msg');
            if (m) m.value = (obj && obj.min != null) ? obj.min : BREAK_DEFAULTS.min;
            if (s) s.value = (obj && obj.sec != null) ? obj.sec : BREAK_DEFAULTS.sec;
            if (msg) msg.value = (obj && obj.msg != null) ? obj.msg : BREAK_DEFAULTS.msg;
            syncBreakQuickFromSettings();
        }

        function saveBreakSettings() {
            try { localStorage.setItem(BREAK_SETTINGS_KEY, JSON.stringify(getBreakSettings())); } catch (e) {}
        }

        function loadBreakSettings() {
            try {
                var raw = localStorage.getItem(BREAK_SETTINGS_KEY);
                if (raw) { var p = JSON.parse(raw); if (p && typeof p === 'object') { setBreakSettings(p); return; } }
            } catch (e) {}
        }

        function syncBreakQuickFromSettings() {
            var m = document.getElementById('break-timer-min');
            var s = document.getElementById('break-timer-sec');
            var qm = document.getElementById('break-quick-min');
            var qs = document.getElementById('break-quick-sec');
            if (m && qm) qm.value = String(Math.max(0, parseInt(m.value, 10) || 0)).padStart(2, '0');
            if (s && qs) qs.value = String(Math.max(0, Math.min(59, parseInt(s.value, 10) || 0))).padStart(2, '0');
        }

        function syncBreakSettingsFromQuick() {
            var m = document.getElementById('break-timer-min');
            var s = document.getElementById('break-timer-sec');
            var qm = document.getElementById('break-quick-min');
            var qs = document.getElementById('break-quick-sec');
            if (m && qm) m.value = qm.value;
            if (s && qs) s.value = qs.value;
        }

        function formatBreakTime(totalSec) {
            var m = Math.floor(totalSec / 60);
            var s = totalSec % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        function startBreakCountdown() {
            stopBreakCountdown();
            var bs = getBreakSettings();
            var totalSec = bs.min * 60 + bs.sec;
            var msgEl = document.getElementById('break-message-display');
            var timerEl = document.getElementById('break-timer-display');
            var overlayEl = document.getElementById('break-countdown-overlay');
            if (msgEl) msgEl.textContent = bs.msg;
            if (timerEl) timerEl.textContent = formatBreakTime(totalSec);
            if (overlayEl) overlayEl.style.display = 'block';
            if (totalSec <= 0) {
                closeYtIframeScene();
                return;
            }
            breakCountdownInterval = setInterval(function() {
                totalSec--;
                if (timerEl) timerEl.textContent = formatBreakTime(Math.max(0, totalSec));
                if (totalSec <= 0) {
                    stopBreakCountdown();
                    closeYtIframeScene();
                }
            }, 1000);
        }

        function stopBreakCountdown() {
            if (breakCountdownInterval !== null) {
                clearInterval(breakCountdownInterval);
                breakCountdownInterval = null;
            }
            var overlayEl = document.getElementById('break-countdown-overlay');
            if (overlayEl) overlayEl.style.display = 'none';
        }

        /* Shared teardown: hide the YT iframe scene and restore the non-SC2 layout */
        function closeYtIframeScene() {
            var overlay = document.getElementById('scene-overlay-yt-iframe');
            var iframe = document.getElementById('yt-iframe-player');
            var introBtn = document.getElementById('scene-btn-yt-intro');
            var breakBtn = document.getElementById('scene-btn-yt-break');
            stopBreakCountdown();
            if (overlay) { overlay.style.display = 'none'; overlay.removeAttribute('data-yt-which'); }
            if (iframe) iframe.src = '';
            if (introBtn) introBtn.classList.remove('active');
            if (breakBtn) breakBtn.classList.remove('active');
            applyLayoutFromSc2Button();
        }

        (function initBreakSettings() {
            loadBreakSettings();
            /* Settings inputs → sync quick + save */
            ['break-timer-min', 'break-timer-sec', 'break-timer-msg'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', function() { syncBreakQuickFromSettings(); saveBreakSettings(); });
            });
            /* Quick inputs → sync settings + save */
            ['break-quick-min', 'break-quick-sec'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', function() { syncBreakSettingsFromQuick(); saveBreakSettings(); });
            });
        })();
        /* ── End break countdown ─────────────────────────────────── */

        /* Detect YouTube video end via postMessage (requires enablejsapi=1 in URL).
           YouTube only broadcasts state events after receiving a 'listening' subscription —
           see subscribeYtIframe(). State 0 = ended. */
        window.addEventListener('message', function(e) {
            if (!e.data) return;
            var data;
            try { data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (ex) { return; }
            /* Some builds nest state inside info.playerState; support both shapes */
            var state = null;
            if (data.event === 'onStateChange') state = data.info;
            else if (data.info && typeof data.info === 'object' && 'playerState' in data.info) state = data.info.playerState;
            if (state !== 0) return;
            var overlay = document.getElementById('scene-overlay-yt-iframe');
            if (!overlay || overlay.style.display !== 'block') return;
            closeYtIframeScene();
        });

        function subscribeYtIframe(iframe, vol) {
            if (!iframe || !iframe.contentWindow) return;
            try {
                iframe.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1 }), 'https://www.youtube.com');
                if (vol != null && vol !== 100) {
                    /* Small delay so the player is ready to receive commands */
                    setTimeout(function() {
                        try {
                            iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'setVolume', args: [vol] }), 'https://www.youtube.com');
                        } catch (e) {}
                    }, 800);
                }
            } catch (e) {}
        }

        function applyYtCropToIframe() {
            /* No-op: the iframe fills the overlay via CSS (100%×100%). No crop needed. */
        }

        function toggleYtIframeScene(which) {
            var overlay = document.getElementById('scene-overlay-yt-iframe');
            var iframe = document.getElementById('yt-iframe-player');
            var introBtn = document.getElementById('scene-btn-yt-intro');
            var breakBtn = document.getElementById('scene-btn-yt-break');
            var isActive = overlay && overlay.style.display === 'block';
            var currentWhich = overlay ? overlay.getAttribute('data-yt-which') : '';

            if (isActive && currentWhich === which) {
                closeYtIframeScene();
                if (typeof window.mxResumeForYt === 'function') window.mxResumeForYt();
            } else {
                closeYtIframeScene(); /* clear any prior YT iframe state before switching */
                if (typeof window.mxPauseForYt === 'function') window.mxPauseForYt();
                clearExclusiveScenes();
                var videos = getYtIframeVideos();
                var videoIdx = which === 'intro' ? 0 : 1;
                var url = videos[videoIdx].url;
                /* Hide other overlays */
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                if (bgOverlay) bgOverlay.style.display = 'none';
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                if (sharedOverlay) sharedOverlay.style.display = 'none';
                if (sharedBtn) sharedBtn.classList.remove('active');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                var ytOverlay = document.getElementById('scene-overlay-yt');
                var ytBtn = document.getElementById('scene-btn-yt');
                if (ytOverlay) ytOverlay.style.display = 'none';
                if (ytBtn) ytBtn.classList.remove('active');
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                if (vdoFullBtn) vdoFullBtn.classList.remove('active');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                if (sc2Overlay) { sc2Overlay.style.display = 'none'; sc2Overlay.style.zIndex = ''; }
                if (sc2Panel) sc2Panel.style.display = 'none';
                setSc2ButtonsActive(false);
                /* Show YT iframe overlay */
                overlay.setAttribute('data-yt-which', which);
                if (iframe) {
                    iframe.src = url;
                    var volForVideo = videos[videoIdx].vol;
                    /* Subscribe for state-change messages and apply volume once the iframe loads */
                    iframe.onload = function() { subscribeYtIframe(iframe, volForVideo); };
                }
                overlay.style.display = 'block';
                applyYtCropToIframe();
                if (introBtn) introBtn.classList.toggle('active', which === 'intro');
                if (breakBtn) breakBtn.classList.toggle('active', which === 'break');
                /* Start countdown only for BREAK */
                if (which === 'break') startBreakCountdown();
            }
        }

        function getDefaultSc2PanelPosition(overlayRect) {
            var ow = overlayRect.width || 1280;
            return { left: Math.round(ow - 420), top: 20, width: 400, height: 300 };
        }
        function applyPositionToSc2Panel(el, pos) {
            if (!el || !pos || pos.left == null) return;
            el.style.left = (pos.left + "px");
            el.style.top = (pos.top + "px");
            el.style.width = (pos.width + "px");
            el.style.height = (pos.height + "px");
        }

        var VDO_FULL_PANEL_KEY = "stream_production_vdo_full_panel";
        function getSavedVdoFullPanel() {
            try {
                var raw = localStorage.getItem(VDO_FULL_PANEL_KEY);
                if (raw) {
                    var p = JSON.parse(raw);
                    if (p && typeof p === "object" && p.left != null) return p;
                }
            } catch (e) {}
            return null;
        }
        function setSavedVdoFullPanel(obj) {
            try {
                localStorage.setItem(VDO_FULL_PANEL_KEY, JSON.stringify(obj));
            } catch (e) {}
        }
        function getDefaultVdoFullPanelPosition(overlayRect) {
            var w = overlayRect.width || 1280;
            var h = overlayRect.height || 720;
            return { left: 50, top: 100, width: Math.round(w - 100), height: Math.round(h - 150) };
        }
        function applyPositionToVdoFullPanel(el, pos) {
            if (!el || !pos || pos.left == null) return;
            var w = pos.width;
            var h = pos.height;
            if (w == null || w <= 0 || h == null || h <= 0) {
                var parent = document.getElementById("scene-overlay-vdo-full");
                var rect = parent ? parent.getBoundingClientRect() : { width: 1280, height: 720 };
                pos = getDefaultVdoFullPanelPosition(rect);
            }
            el.style.left = (pos.left + "px");
            el.style.top = (pos.top + "px");
            el.style.width = (pos.width + "px");
            el.style.height = (pos.height + "px");
        }

        function applySavedPositionToLogo(el, key) {
            var saved = getSavedLogoPositions()[key];
            if (!saved || saved.left == null) {
                el.style.left = "";
                el.style.top = "";
                el.style.width = "";
                el.style.height = "";
                el.style.right = "";
                el.style.transform = "";
                return;
            }
            el.style.left = (saved.left + "px") || "";
            el.style.top = (saved.top + "px") || "";
            el.style.width = (saved.width + "px") || "";
            el.style.height = (saved.height + "px") || "";
            el.style.right = "";
            el.style.transform = "";
        }

        function getDefaultLogoPosition(key, overlayRect, el) {
            var img = el && el.querySelector ? el.querySelector("img") : null;
            var w = (el && el.offsetWidth) ? el.offsetWidth : (img && img.naturalWidth) ? img.naturalWidth : 120;
            var h = (el && el.offsetHeight) ? el.offsetHeight : (img && img.naturalHeight) ? img.naturalHeight : 60;
            var top = 20;
            var ow = overlayRect.width || 1280;
            if (key === "s10") {
                return { left: Math.round((ow - w) / 2), top: top, width: w, height: h };
            }
            if (key === "fsl-small") {
                return { left: Math.round(ow - 50 - w), top: top, width: w, height: h };
            }
            return { left: 0, top: top, width: w, height: h };
        }

        function applyPositionToLogo(el, pos) {
            if (!pos || pos.left == null) return;
            el.style.left = (pos.left + "px");
            el.style.top = (pos.top + "px");
            el.style.width = (pos.width + "px");
            el.style.height = (pos.height + "px");
            el.style.right = "";
            el.style.transform = "";
        }

        function updateLogosOverlay() {
            var overlay = document.getElementById("logos-overlay");
            if (!overlay) return;
            var s10Cb = document.getElementById("logo-s10-cb");
            var fslCb = document.getElementById("logo-fsl-small-cb");
            var wrapS10 = document.getElementById("logo-s10-wrap");
            var wrapFsl = document.getElementById("logo-fsl-small-wrap");
            if (!wrapS10 || !wrapFsl) return;

            wrapS10.style.display = (s10Cb && s10Cb.checked) ? "block" : "none";
            wrapFsl.style.display = (fslCb && fslCb.checked) ? "block" : "none";
            overlay.className = "logos-overlay" + (logosEditMode ? " logos-edit-mode" : "");

            if (logosEditMode) {
                makeVisibleLogosDraggable();
                return;
            }

            var saved = getSavedLogoPositions();
            var overlayRect = overlay.getBoundingClientRect();
            logoIds.forEach(function(id) {
                var el = document.getElementById(id);
                var key = logoIdToKey[id];
                if (!el || !key) return;
                if (saved[key]) {
                    applySavedPositionToLogo(el, key);
                } else {
                    var pos = getDefaultLogoPosition(key, overlayRect, el);
                    applyPositionToLogo(el, pos);
                }
            });
        }

        function updateSc2Panel() {
            var sc2Overlay = document.getElementById("sc2-overlay");
            var panel = document.getElementById("sc2-panel-wrap");
            var cb = document.getElementById("logo-sc2-cb");
            if (!sc2Overlay || !panel || !cb) return;
            if (logosEditMode) return;
            panel.style.display = cb.checked ? "block" : "none";
            var saved = getSavedSc2Panel();
            var overlayRect = sc2Overlay.getBoundingClientRect();
            var pos = saved || getDefaultSc2PanelPosition(overlayRect);
            applyPositionToSc2Panel(panel, pos);
        }

        function makeVisibleLogosDraggable() {
            var overlay = document.getElementById("logos-overlay");
            if (!overlay || !logosEditMode) return;
            var saved = getSavedLogoPositions();
            var overlayRect = overlay.getBoundingClientRect();

            logoIds.forEach(function(id) {
                var el = document.getElementById(id);
                var key = logoIdToKey[id];
                if (!el || !key) return;
                if (el.style.display === "none") {
                    if ($(el).data("ui-draggable")) $(el).draggable("destroy");
                    if ($(el).data("ui-resizable")) $(el).resizable("destroy");
                    return;
                }
                el.style.pointerEvents = "auto";
                if ($(el).data("ui-draggable")) return;

                if (saved[key]) {
                    applySavedPositionToLogo(el, key);
                } else {
                    var pos = getDefaultLogoPosition(key, overlayRect, el);
                    applyPositionToLogo(el, pos);
                }
                var img = el.querySelector("img");
                var ratio = (img && img.naturalWidth && img.naturalHeight) ? img.naturalWidth / img.naturalHeight : 1;
                $(el).draggable({ containment: "#logos-overlay", scroll: false, cursor: "move" });
                $(el).resizable({ containment: "#logos-overlay", handles: "all", aspectRatio: ratio });
            });
            updateVdoPanelsInEditMode();
        }

        function resetLogosPositions() {
            var overlay = document.getElementById("logos-overlay");
            if (!overlay) return;
            var overlayRect = overlay.getBoundingClientRect();
            var saved = {};
            logoIds.forEach(function(id) {
                var key = logoIdToKey[id];
                var el = document.getElementById(id);
                if (key && el) saved[key] = getDefaultLogoPosition(key, overlayRect, el);
            });
            setSavedLogoPositions(saved);
            var sc2Panel = document.getElementById("sc2-panel-wrap");
            var sc2Overlay = document.getElementById("sc2-overlay");
            if (sc2Panel && sc2Overlay) {
                var sc2Rect = sc2Overlay.getBoundingClientRect();
                var sc2Pos = getDefaultSc2PanelPosition(sc2Rect);
                setSavedSc2Panel(sc2Pos);
                if (logosEditMode) {
                    if ($(sc2Panel).data("ui-draggable")) $(sc2Panel).draggable("destroy");
                    if ($(sc2Panel).data("ui-resizable")) $(sc2Panel).resizable("destroy");
                }
                applyPositionToSc2Panel(sc2Panel, sc2Pos);
            }
            var vdoFullOverlayReset = document.getElementById("scene-overlay-vdo-full");
            var vdoFullPanelReset = document.getElementById("vdo-full-panel-wrap");
            if (vdoFullPanelReset && vdoFullOverlayReset) {
                var vdoRect = vdoFullOverlayReset.getBoundingClientRect();
                var vdoPos = getDefaultVdoFullPanelPosition(vdoRect);
                setSavedVdoFullPanel(vdoPos);
                if (logosEditMode) {
                    if ($(vdoFullPanelReset).data("ui-draggable")) $(vdoFullPanelReset).draggable("destroy");
                    if ($(vdoFullPanelReset).data("ui-resizable")) $(vdoFullPanelReset).resizable("destroy");
                }
                applyPositionToVdoFullPanel(vdoFullPanelReset, vdoPos);
            }

            if (logosEditMode) {
                logoIds.forEach(function(id) {
                    var el = document.getElementById(id);
                    var key = logoIdToKey[id];
                    if (el && key) {
                        if ($(el).data("ui-draggable")) $(el).draggable("destroy");
                        if ($(el).data("ui-resizable")) $(el).resizable("destroy");
                        applyPositionToLogo(el, saved[key]);
                    }
                });
                makeVisibleLogosDraggable();
            } else {
                logoIds.forEach(function(id) {
                    var el = document.getElementById(id);
                    var key = logoIdToKey[id];
                    if (el && key) applyPositionToLogo(el, saved[key]);
                });
                if (window.updateSc2Panel) updateSc2Panel();
            }
        }

        function toggleLogosEditMode() {
            var overlay = document.getElementById("logos-overlay");
            var btn = document.getElementById("logos-edit-save-btn");
            if (!overlay || !btn) return;

            if (logosEditMode) {
                /* Save: read positions, destroy draggable/resizable, persist, lock */
                var saved = getSavedLogoPositions();
                logoIds.forEach(function(id) {
                    var el = document.getElementById(id);
                    var key = logoIdToKey[id];
                    if (!el || !key) return;
                    if ($(el).data("ui-draggable")) {
                        var off = el.getBoundingClientRect();
                        var par = overlay.getBoundingClientRect();
                        saved[key] = {
                            left: Math.round(off.left - par.left),
                            top: Math.round(off.top - par.top),
                            width: Math.round(off.width),
                            height: Math.round(off.height)
                        };
                        $(el).draggable("destroy");
                    }
                    if ($(el).data("ui-resizable")) $(el).resizable("destroy");
                });
                setSavedLogoPositions(saved);
                var sc2OverlayEl = document.getElementById("sc2-overlay");
                var sc2PanelEl = document.getElementById("sc2-panel-wrap");
                if (sc2PanelEl && $(sc2PanelEl).data("ui-draggable") && sc2OverlayEl) {
                    var off = sc2PanelEl.getBoundingClientRect();
                    var par = sc2OverlayEl.getBoundingClientRect();
                    setSavedSc2Panel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                    $(sc2PanelEl).draggable("destroy");
                    $(sc2PanelEl).resizable("destroy");
                }
                if (sc2OverlayEl) {
                    sc2OverlayEl.style.pointerEvents = "none";
                    sc2OverlayEl.style.zIndex = "";
                    sc2OverlayEl.classList.remove("logos-edit-mode");
                    var sceneSc2Btn = document.getElementById("scene-btn-sc2");
                    if (sceneSc2Btn && sceneSc2Btn.classList.contains("active")) {
                        sc2OverlayEl.style.display = "block";
                    } else {
                        sc2OverlayEl.style.display = "none";
                    }
                }
                if (sc2PanelEl) {
                    var sp = getSavedSc2Panel();
                    applyPositionToSc2Panel(sc2PanelEl, sp || getDefaultSc2PanelPosition(sc2OverlayEl ? sc2OverlayEl.getBoundingClientRect() : { width: 1280 }));
                }
                var vdoFullOverlayEl = document.getElementById("scene-overlay-vdo-full");
                var vdoFullPanelEl = document.getElementById("vdo-full-panel-wrap");
                if (vdoFullPanelEl && $(vdoFullPanelEl).data("ui-draggable") && vdoFullOverlayEl) {
                    var off = vdoFullPanelEl.getBoundingClientRect();
                    var par = vdoFullOverlayEl.getBoundingClientRect();
                    setSavedVdoFullPanel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                    $(vdoFullPanelEl).draggable("destroy");
                    $(vdoFullPanelEl).resizable("destroy");
                }
                var sc2BtnSave = document.getElementById("scene-btn-sc2");
                var sc2IsActiveSave = sc2BtnSave && sc2BtnSave.classList.contains("active");
                if (vdoFullOverlayEl) {
                    vdoFullOverlayEl.style.pointerEvents = "none";
                    vdoFullOverlayEl.classList.remove("logos-edit-mode");
                    if (sc2IsActiveSave) {
                        vdoFullOverlayEl.style.display = "none";
                        vdoFullOverlayEl.style.zIndex = "";
                    } else {
                        /* Hard rule: VDO full is always visible in non-SC2 mode */
                        vdoFullOverlayEl.style.display = "block";
                        vdoFullOverlayEl.style.zIndex = "30000";
                    }
                    var vdoFullBtnSave = document.getElementById("scene-btn-vdo-full");
                    if (vdoFullBtnSave) vdoFullBtnSave.classList.toggle("active", !sc2IsActiveSave);
                }
                if (vdoFullPanelEl) {
                    vdoFullPanelEl.style.display = sc2IsActiveSave ? "none" : "block";
                    if (!sc2IsActiveSave) {
                        var vp = getSavedVdoFullPanel();
                        var vdoRect = { width: vdoFullOverlayEl ? vdoFullOverlayEl.offsetWidth || 1280 : 1280, height: vdoFullOverlayEl ? vdoFullOverlayEl.offsetHeight || 720 : 720 };
                        applyPositionToVdoFullPanel(vdoFullPanelEl, vp || getDefaultVdoFullPanelPosition(vdoRect));
                    }
                }
                logoIds.forEach(function(id) {
                    var el = document.getElementById(id);
                    var key = logoIdToKey[id];
                    if (el && key) {
                        applySavedPositionToLogo(el, key);
                        el.style.pointerEvents = "";
                    }
                });
                overlay.style.pointerEvents = "none";
                overlay.style.zIndex = "";
                overlay.classList.remove("logos-edit-mode");
                logosEditMode = false;
                btn.textContent = "Edit and Move";
                if (window.reapplyLayerOrder) window.reapplyLayerOrder();
                if (window.updateSc2Panel) window.updateSc2Panel();
                return;
            }

            /* Enter edit mode: show overlay on top of right panel, enable pointer-events, make visible logos draggable */
            overlay.style.display = "block";
            document.getElementById("scene-btn-logos").classList.add("active");
            overlay.style.pointerEvents = "none";
            overlay.style.zIndex = "100002";
            overlay.classList.add("logos-edit-mode");
            logosEditMode = true;
            btn.textContent = "Save";
            updateVdoPanelsInEditMode();
            updateLogosOverlay();
        }

        function updateVdoPanelsInEditMode() {
            if (!logosEditMode) return;
            var sceneSc2Btn = document.getElementById("scene-btn-sc2");
            var sc2Active = sceneSc2Btn && sceneSc2Btn.classList.contains("active");
            var vdoFullOverlayEl = document.getElementById("scene-overlay-vdo-full");
            var vdoFullPanelEl = document.getElementById("vdo-full-panel-wrap");
            var sc2OverlayEl = document.getElementById("sc2-overlay");
            var sc2PanelEl = document.getElementById("sc2-panel-wrap");
            if (sc2Active) {
                if (vdoFullOverlayEl && vdoFullPanelEl) {
                    if ($(vdoFullPanelEl).data("ui-draggable")) {
                        var off = vdoFullPanelEl.getBoundingClientRect();
                        var par = vdoFullOverlayEl.getBoundingClientRect();
                        setSavedVdoFullPanel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                        $(vdoFullPanelEl).draggable("destroy");
                        $(vdoFullPanelEl).resizable("destroy");
                    }
                    vdoFullOverlayEl.style.display = "none";
                }
                if (sc2OverlayEl && sc2PanelEl) {
                    sc2OverlayEl.style.display = "block";
                    sc2OverlayEl.style.pointerEvents = "auto";
                    sc2OverlayEl.style.zIndex = "100001";
                    sc2OverlayEl.classList.add("logos-edit-mode");
                    var sc2Pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2OverlayEl.getBoundingClientRect());
                    applyPositionToSc2Panel(sc2PanelEl, sc2Pos);
                    sc2PanelEl.style.display = "block";
                    if (!$(sc2PanelEl).data("ui-draggable")) {
                        $(sc2PanelEl).draggable({ containment: "#sc2-overlay", scroll: false, cursor: "move" });
                        $(sc2PanelEl).resizable({ containment: "#sc2-overlay", handles: "all" });
                    }
                    var sc2Iframe = sc2PanelEl.querySelector("iframe");
                    ensureVdoIframeLoaded(sc2Iframe);
                }
            } else {
                if (vdoFullOverlayEl && vdoFullPanelEl) {
                    vdoFullOverlayEl.style.display = "block";
                    vdoFullOverlayEl.style.pointerEvents = "auto";
                    vdoFullOverlayEl.style.zIndex = "100001";
                    vdoFullOverlayEl.classList.add("logos-edit-mode");
                    var vdoFullBtnEdit = document.getElementById("scene-btn-vdo-full");
                    if (vdoFullBtnEdit) vdoFullBtnEdit.classList.add("active");
                    var pos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(vdoFullOverlayEl.getBoundingClientRect());
                    applyPositionToVdoFullPanel(vdoFullPanelEl, pos);
                    vdoFullPanelEl.style.display = "block";
                    if (!$(vdoFullPanelEl).data("ui-draggable")) {
                        $(vdoFullPanelEl).draggable({ containment: "#scene-overlay-vdo-full", scroll: false, cursor: "move" });
                        $(vdoFullPanelEl).resizable({ containment: "#scene-overlay-vdo-full", handles: "all" });
                    }
                    var vdoIframe = vdoFullPanelEl.querySelector("iframe");
                    ensureVdoIframeLoaded(vdoIframe);
                }
                if (sc2OverlayEl && sc2PanelEl) {
                    if ($(sc2PanelEl).data("ui-draggable")) {
                        var off = sc2PanelEl.getBoundingClientRect();
                        var par = sc2OverlayEl.getBoundingClientRect();
                        setSavedSc2Panel({ left: Math.round(off.left - par.left), top: Math.round(off.top - par.top), width: Math.round(off.width), height: Math.round(off.height) });
                        $(sc2PanelEl).draggable("destroy");
                        $(sc2PanelEl).resizable("destroy");
                    }
                    sc2OverlayEl.style.display = "none";
                }
            }
        }

        (function wireLogosSettings() {
            document.getElementById("logo-s10-cb").addEventListener("change", updateLogosOverlay);
            document.getElementById("logo-fsl-small-cb").addEventListener("change", updateLogosOverlay);
            document.getElementById("logo-sc2-cb").addEventListener("change", function() { if (!logosEditMode) updateSc2Panel(); });
        })();
        window.updateSc2Panel = updateSc2Panel;

        /**
         * Reusable fullscreen transition: play a video with fade-in at start and fade-out at end.
         * @param {Object} options - videoSrc (string), fadeInMs (number), fadeOutMs (number), onComplete (function)
         */
        function playTransitionVideo(options) {
            var opts = options || {};
            var videoSrc = opts.videoSrc;
            var fadeInMs = opts.fadeInMs != null ? opts.fadeInMs : 500;
            var fadeOutMs = opts.fadeOutMs != null ? opts.fadeOutMs : 500;
            var onComplete = typeof opts.onComplete === 'function' ? opts.onComplete : function() {};

            var overlay = document.getElementById('transition-video-overlay');
            var video = document.getElementById('transition-video-player');
            if (!overlay || !video || !videoSrc) {
                onComplete();
                return;
            }

            video.pause();
            video.removeAttribute('src');
            video.onloadeddata = null;
            video.onended = null;
            video.onerror = null;
            video.oncanplay = null;

            video.style.transition = 'opacity ' + (fadeInMs / 1000) + 's ease';
            video.style.opacity = '0';
            overlay.style.display = 'block';
            video.src = videoSrc;

            var fadeInStarted = false;
            function startFadeInAndPlay() {
                if (fadeInStarted) return;
                fadeInStarted = true;
                video.currentTime = 0;
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        video.style.opacity = '1';
                        video.play().catch(function() { onComplete(); });
                    });
                });
            }

            video.oncanplay = function() {
                video.oncanplay = null;
                startFadeInAndPlay();
            };

            video.onended = function() {
                video.onended = null;
                video.style.transition = 'opacity ' + (fadeOutMs / 1000) + 's ease';
                video.style.opacity = '0';
                setTimeout(function() {
                    overlay.style.display = 'none';
                    video.style.opacity = '1';
                    video.pause();
                    video.removeAttribute('src');
                    video.load();
                    onComplete();
                }, fadeOutMs);
            };

            video.onerror = function() {
                overlay.style.display = 'none';
                onComplete();
            };
        }

        var VIDEO_OVERLAY_KEYS = ['all-vdo', 'schedule', 'ash', 'pog', 'ptb', 'st'];
            var VIDEO_OVERLAY_FILES = { 'all-vdo': '2026_FSL_BG.mp4', 'schedule': '2026_FSL_schedule_now.mp4', 'ash': 'ASH.mp4', 'pog': 'POG.mp4', 'ptb': 'PTB.mp4', 'st': 'ST.mp4' };
            var VIDEO_OVERLAY_FRONT = ['schedule', 'ash', 'pog', 'ptb', 'st'];
            var VIDEO_OVERLAY_AUDIO = { 'ash': 'angry_space_hares.wav', 'pog': 'FSL_PSISOP_Gaming.wav', 'ptb': 'pulled_the_boys.wav', 'st': 'FSL_SpecialTactics2.wav' };

            // Deactivate all mutually-exclusive scenes: video overlay buttons, scoreboard, custom-scoreboard.
            // Call this at the start of any scene activation. Does NOT touch SC2, VDO full, or Logos.
            function clearExclusiveScenes() {
                VIDEO_OVERLAY_KEYS.forEach(function(k) {
                    var b = document.getElementById('scene-btn-' + k);
                    if (b) b.classList.remove('active');
                });
                var sbEl = document.getElementById('scoreboard-overlay');
                var sbBtn = document.getElementById('scene-btn-scoreboard');
                if (sbEl) { sbEl.style.display = 'none'; sbEl.style.zIndex = ''; }
                if (sbBtn) sbBtn.classList.remove('active');
                var csbEl = document.getElementById('custom-scoreboard-overlay');
                var csbBtn = document.getElementById('scene-btn-custom-scoreboard');
                if (csbEl) { csbEl.style.display = 'none'; csbEl.style.zIndex = ''; }
                if (csbBtn) csbBtn.classList.remove('active');
            }

            function getVideoOverlayDefaultZIndex() {
                try {
                    var raw = localStorage.getItem('stream_production_layer_order');
                    if (raw) {
                        var order = JSON.parse(raw);
                        var idx = order.indexOf('scene-overlay-all-vdo');
                        if (idx >= 0) return String((idx + 1) * 10000);
                    }
                } catch (e) {}
                return '10000';
            }

            function setSceneVideoError(msg) {
                var el = document.getElementById('scene-video-error');
                if (!el) return;
                if (msg) { el.textContent = msg; el.style.display = 'block'; } else { el.textContent = ''; el.style.display = 'none'; }
            }

            /* ---------- Scoreboard image/style replacements (adjust here for icons and formatting) ---------- */
            window.SCOREBOARD_REPLACEMENTS = {
                raceIconBase: 'https://psistorm.com/fsl/images/',
                raceIcons: {
                    Z: 'zerg_icon.png',
                    T: 'terran_icon.png',
                    P: 'protoss_icon.png',
                    R: 'random_icon.png'
                }
            };
            /** Overwrite rank from rankings when we have a match (CSV may have stale #07 etc). rankings = array from rankings.php. */
            function scoreboardCellWithRankFromJson(val, rankings) {
                if (val == null || val === '') return val;
                var raw = String(val).trim();
                var rest = raw.replace(/^(?:\[\d+\]|#?\d+)\s*/, '').trim();
                var nameForLookup = rest.replace(/\s*\([ZTPR]\)\s*/gi, '').trim();
                var rank = null;
                if (rankings && rankings.length) {
                    var nameLower = nameForLookup.toLowerCase();
                    for (var i = 0; i < rankings.length; i++) {
                        var p = rankings[i];
                        if (p.name && String(p.name).toLowerCase() === nameLower) { rank = p; break; }
                    }
                }
                if (rank == null && typeof window.getRankingForPlayer === 'function') rank = window.getRankingForPlayer(nameForLookup);
                if (rank != null) {
                    raw = '[' + rank.rank + '] ' + rest;
                }
                return raw;
            }
            function formatScoreboardTeamCell(val) {
                if (val == null || val === '') return '';
                var s = escapeHtml(String(val).trim());
                s = s.replace(/\[(\d+)\]/g, '<span class="scoreboard-slot">#$1</span>');
                var cfg = window.SCOREBOARD_REPLACEMENTS || {};
                var base = (cfg.raceIconBase || '').replace(/\/?$/, '/');
                ['Z', 'T', 'P', 'R'].forEach(function(letter) {
                    var img = (cfg.raceIcons && cfg.raceIcons[letter]) ? (base + cfg.raceIcons[letter]) : '';
                    if (img) s = s.replace(new RegExp('\\(' + letter + '\\)', 'g'), '<img src="' + escapeHtml(img) + '" alt="' + letter + '" class="scoreboard-race-icon">');
                });
                return s;
            }
            /* ---------- End scoreboard replacements ---------- */

            function parseCSVLine(line) {
                line = line.replace(/^\uFEFF/, '');
                var out = [], i = 0;
                while (i < line.length) {
                    if (line[i] === '"') {
                        var cell = '';
                        i++;
                        while (i < line.length) {
                            if (line[i] === '"') {
                                if (line[i + 1] === '"') { cell += '"'; i += 2; continue; }
                                break;
                            }
                            cell += line[i]; i++;
                        }
                        i++;
                        out.push(cell);
                        if (i < line.length && line[i] === ',') i++;
                    } else {
                        var start = i;
                        while (i < line.length && line[i] !== ',') i++;
                        out.push(line.slice(start, i).replace(/^[\s\uFEFF]+|[\s\uFEFF]+$/g, ''));
                        if (i < line.length && line[i] === ',') i++;
                    }
                }
                return out;
            }
            function parseCSV(text) {
                if (!text || !text.trim()) return [];
                text = text.replace(/^\uFEFF/, '');
                var lines = text.split(/\r?\n/);
                var numCols = 12;
                var rows = lines.map(function(l) {
                    var row = parseCSVLine(l.replace(/\r/g, ''));
                    var out = row.map(function(c) { return String(c).replace(/\r/g, '').trim(); });
                    if (out.length > numCols) out = out.slice(0, numCols);
                    while (out.length < numCols) out.push('');
                    return out;
                });
                return rows;
            }
            function cellStr(val) {
                if (val == null || val === undefined) return '';
                return String(val).replace(/\r/g, '').trim();
            }
            function buildScoreboardHTML(rows, rankings) {
                var teamA = '', scoreA = '', teamB = '', scoreB = '';
                var map1Label = 'Map 1', map2Label = 'Map 2';
                if (rows.length > 0) {
                    var r0 = rows[0];
                    teamA = cellStr(r0[2]);
                    scoreA = cellStr(r0[4]);
                    teamB = cellStr(r0[6]);
                    scoreB = cellStr(r0[8]);
                }
                if (rows.length > 1) {
                    var r1 = rows[1];
                    map1Label = cellStr(r1[10]) || map1Label;
                    map2Label = cellStr(r1[11]) || map2Label;
                }
                var dataRows = [];
                for (var r = 2; r < rows.length; r++) {
                    var row = rows[r];
                    if (!cellStr(row[1]) && !cellStr(row[2]) && !cellStr(row[3]) && !cellStr(row[4]) && !cellStr(row[6]) && !cellStr(row[7]) && !cellStr(row[8]) && !cellStr(row[10]) && !cellStr(row[11])) continue;
                    dataRows.push(row);
                }
                var sb = [];
                sb.push('<div class="scoreboard-panel-inner">');
                sb.push('<div class="scoreboard-header">');
                sb.push('<div class="scoreboard-team-block scoreboard-team-a"><span class="scoreboard-team-name">' + formatScoreboardTeamCell(teamA) + '</span></div>');
                sb.push('<div class="scoreboard-vs-block"><span class="scoreboard-score-main">' + escapeHtml(scoreA) + ' &ndash; ' + escapeHtml(scoreB) + '</span><span class="scoreboard-score-label"><!-- Score --></span></div>');
                sb.push('<div class="scoreboard-team-block scoreboard-team-b"><span class="scoreboard-team-name">' + formatScoreboardTeamCell(teamB) + '</span></div>');
                sb.push('</div>');
                sb.push('<div class="scoreboard-table-wrap"><table class="scoreboard-table"><colgroup><col><col><col><col><col><col><col><col><col><col><col><col></colgroup><thead><tr>');
                sb.push('<th class="scoreboard-th-empty"></th><th class="scoreboard-th-type">Type</th><th class="scoreboard-th-team scoreboard-th-group" colspan="3">Team A</th><th class="scoreboard-th-empty"></th><th class="scoreboard-th-team scoreboard-th-group" colspan="3">Team B</th><th class="scoreboard-th-empty"></th><th class="scoreboard-th-map">' + escapeHtml(map1Label) + '</th><th class="scoreboard-th-map">' + escapeHtml(map2Label) + '</th>');
                sb.push('</tr></thead><tbody>');
                dataRows.forEach(function(row) {
                    sb.push('<tr>');
                    for (var c = 0; c < 12; c++) {
                        var val = cellStr(row[c]);
                        var cls = c === 1 ? 'scoreboard-type' : c === 4 || c === 8 ? 'scoreboard-num' : c === 10 || c === 11 ? 'scoreboard-map' : (c === 0 || c === 5 || c === 9) ? 'scoreboard-empty-cell' : (c === 2 || c === 3) ? 'scoreboard-cell scoreboard-cell-team scoreboard-cell-team-a' : (c === 6 || c === 7) ? 'scoreboard-cell scoreboard-cell-team scoreboard-cell-team-b' : 'scoreboard-cell';
                        var cellContent = (c === 2 || c === 3 || c === 6 || c === 7) ? formatScoreboardTeamCell(scoreboardCellWithRankFromJson(val, rankings)) : escapeHtml(val);
                        sb.push('<td class="' + cls + '">' + cellContent + '</td>');
                    }
                    sb.push('</tr>');
                });
                sb.push('</tbody></table></div></div>');
                return sb.join('');
            }
            function escapeHtml(s) {
                if (s == null) return '';
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }
            window.loadAndRenderScoreboard = function() {
                var container = document.getElementById('scoreboard-content');
                if (!container) return;
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var baseUrl = (base ? base + '/' : '');
                var csvUrl = baseUrl + '2026/scoreboard.csv?_t=' + Date.now();
                var rankingsUrl = (baseUrl || './') + 'rankings.php';
                fetch(rankingsUrl).then(function(r) { return r.json(); }).then(function(rankings) {
                    if (!Array.isArray(rankings)) rankings = [];
                    return fetch(csvUrl).then(function(r) {
                        if (!r.ok) throw new Error(r.status + ' ' + r.statusText);
                        return r.text();
                    }).then(function(text) {
                        var raw = (text || '').trim();
                        if (!raw) throw new Error('Empty file');
                        var rows = parseCSV(raw);
                        container.innerHTML = rows.length ? buildScoreboardHTML(rows, rankings) : '<div class="scoreboard-panel-inner"><p class="scoreboard-empty">No scoreboard data. Add CSV in Settings &rarr; Player &rarr; Scoreboard.</p></div>';
                    });
                }).catch(function(err) {
                    container.innerHTML = '<div class="scoreboard-panel-inner"><p class="scoreboard-empty">Could not load scoreboard. ' + (err && err.message ? err.message : '') + '</p></div>';
                });
            };

            /* ---- Custom Scoreboard ---- */

            var _csbData = { matches: [] };

            var CSB_EXAMPLES = [
                { a: 'hyperturtle',              b: 'sgtabc',                desc: 'monobattles' },
                { a: 'nachoz/nukleo/chienpwn',   b: 'adastra/pebble/harouz', desc: '3v3' },
                { a: 'papella',                  b: 'neutrophil',            desc: 'random vs random' },
                { a: 'hyperturtle',              b: 'sgtabc',                desc: 'monobattles' },
                { a: 'nachoz/nukleo/chienpwn',   b: 'adastra/pebble/harouz', desc: '3v3' }
            ];

            function csbMakeRow(idx, m) {
                var ex = CSB_EXAMPLES[idx % CSB_EXAMPLES.length];
                var row = document.createElement('div');
                row.className = 'sb-matchup-row';
                row.style.cssText = 'padding:6px 8px; background:#111; border-radius:4px; font-size:12px; margin-bottom:6px; border:1px solid #222;';
                row.dataset.csbIdx = idx;
                row.innerHTML =
                    '<div style="font-size:10px; color:#555; margin-bottom:4px; letter-spacing:0.5px;">MATCH ' + (idx + 1) + '</div>' +
                    /* Side A row */
                    '<div style="display:flex; align-items:center; gap:4px; margin-bottom:3px;">' +
                        '<input type="text" class="csb-a" placeholder="' + csbEsc(ex.a) + '" value="' + csbEsc(m.a || '') + '" style="flex:1; min-width:0; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px; padding:3px 5px;">' +
                        '<button type="button" onclick="csbAdj(this,-1,\'a\')" style="width:22px; height:22px; line-height:1; padding:0; font-size:14px; flex-shrink:0; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px;">−</button>' +
                        '<input type="number" class="csb-score-a" min="0" max="99" value="' + (m.scoreA != null ? m.scoreA : 0) + '" style="width:40px; text-align:center; flex-shrink:0; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px; padding:2px 3px;">' +
                        '<button type="button" onclick="csbAdj(this,1,\'a\')" style="width:22px; height:22px; line-height:1; padding:0; font-size:14px; flex-shrink:0; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px;">+</button>' +
                    '</div>' +
                    /* Side B row */
                    '<div style="display:flex; align-items:center; gap:4px; margin-bottom:3px;">' +
                        '<input type="text" class="csb-b" placeholder="' + csbEsc(ex.b) + '" value="' + csbEsc(m.b || '') + '" style="flex:1; min-width:0; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px; padding:3px 5px;">' +
                        '<button type="button" onclick="csbAdj(this,-1,\'b\')" style="width:22px; height:22px; line-height:1; padding:0; font-size:14px; flex-shrink:0; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px;">−</button>' +
                        '<input type="number" class="csb-score-b" min="0" max="99" value="' + (m.scoreB != null ? m.scoreB : 0) + '" style="width:40px; text-align:center; flex-shrink:0; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px; padding:2px 3px;">' +
                        '<button type="button" onclick="csbAdj(this,1,\'b\')" style="width:22px; height:22px; line-height:1; padding:0; font-size:14px; flex-shrink:0; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px;">+</button>' +
                    '</div>' +
                    /* Description row */
                    '<input type="text" class="csb-desc" placeholder="' + csbEsc(ex.desc) + '" value="' + csbEsc(m.desc || '') + '" style="width:100%; box-sizing:border-box; background:#1a1a1a; color:#aaa; border:1px solid #333; border-radius:3px; padding:3px 5px;">';
                return row;
            }

            function csbEsc(s) {
                if (s == null) return '';
                var d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML.replace(/"/g, '&quot;');
            }

            window.csbAdj = function(btn, delta, side) {
                var row = btn.closest('.sb-matchup-row') || btn.parentNode.parentNode;
                var inp = row.querySelector(side === 'a' ? '.csb-score-a' : '.csb-score-b');
                if (!inp) return;
                var v = parseInt(inp.value, 10) || 0;
                inp.value = Math.max(0, v + delta);
            };

            window.csbSetNumMatches = function(n) {
                n = Math.max(1, Math.min(20, n || 1));
                var existing = _csbData.matches.slice();
                while (existing.length < n) existing.push({ a: '', b: '', scoreA: 0, scoreB: 0, desc: '' });
                existing = existing.slice(0, n);
                _csbData.matches = existing;
                csbRenderRows();
            };

            function csbRenderRows() {
                var container = document.getElementById('csb-rows-container');
                if (!container) return;
                container.innerHTML = '';
                _csbData.matches.forEach(function(m, i) {
                    container.appendChild(csbMakeRow(i, m));
                });
                var numInp = document.getElementById('csb-num-matches');
                if (numInp) numInp.value = _csbData.matches.length;
            }

            function csbCollect() {
                var rows = document.querySelectorAll('#csb-rows-container .sb-matchup-row');
                var matches = [];
                rows.forEach(function(row) {
                    matches.push({
                        a: (row.querySelector('.csb-a') || {}).value || '',
                        b: (row.querySelector('.csb-b') || {}).value || '',
                        scoreA: parseInt((row.querySelector('.csb-score-a') || {}).value, 10) || 0,
                        scoreB: parseInt((row.querySelector('.csb-score-b') || {}).value, 10) || 0,
                        desc: (row.querySelector('.csb-desc') || {}).value || ''
                    });
                });
                return {
                    labelA: (document.getElementById('csb-label-a') || {}).value || '',
                    labelB: (document.getElementById('csb-label-b') || {}).value || '',
                    matches: matches
                };
            }

            window.csbSave = function() {
                var collected = csbCollect();
                _csbData.labelA = collected.labelA;
                _csbData.labelB = collected.labelB;
                _csbData.matches = collected.matches;
                var btn = document.getElementById('csb-save-btn');
                var status = document.getElementById('csb-save-status');
                if (btn) btn.disabled = true;
                if (status) status.textContent = 'Saving…';
                fetch('save_custom_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(_csbData)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (res && res.ok) {
                        if (status) status.textContent = 'Saved!';
                        var overlay = document.getElementById('custom-scoreboard-overlay');
                        if (overlay && overlay.style.display === 'block') loadAndRenderCustomScoreboard();
                    } else {
                        if (status) status.textContent = 'Error saving.';
                    }
                    setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                }).catch(function() {
                    if (status) status.textContent = 'Network error.';
                    setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                });
            };

            window.csbLoad = function() {
                fetch('save_custom_scoreboard.php?_t=' + Date.now())
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        _csbData = data && Array.isArray(data.matches) ? data : { matches: [] };
                        if (_csbData.matches.length === 0) {
                            var n = parseInt((document.getElementById('csb-num-matches') || {}).value, 10) || 5;
                            for (var i = 0; i < n; i++) _csbData.matches.push({ a: '', b: '', scoreA: 0, scoreB: 0, desc: '' });
                        }
                        var inpA = document.getElementById('csb-label-a');
                        var inpB = document.getElementById('csb-label-b');
                        if (inpA) inpA.value = _csbData.labelA || '';
                        if (inpB) inpB.value = _csbData.labelB || '';
                        csbRenderRows();
                    })
                    .catch(function() {
                        if (_csbData.matches.length === 0) {
                            for (var i = 0; i < 5; i++) _csbData.matches.push({ a: '', b: '', scoreA: 0, scoreB: 0, desc: '' });
                        }
                        csbRenderRows();
                    });
            };

            function buildCustomScoreboardHTML(data) {
                var matches = (data && Array.isArray(data.matches)) ? data.matches : [];
                var active = matches.filter(function(m) { return m.a || m.b || m.scoreA || m.scoreB || m.desc; });
                if (!active.length) return '<div class="scoreboard-panel-inner"><p class="scoreboard-empty">No custom scoreboard data. Edit in Settings &rarr; Custom Scoreboard.</p></div>';

                var labelA = (data && data.labelA && data.labelA.trim()) ? data.labelA.trim() : 'Side A';
                var labelB = (data && data.labelB && data.labelB.trim()) ? data.labelB.trim() : 'Side B';

                var totalA = 0, totalB = 0;
                active.forEach(function(m) { totalA += (parseInt(m.scoreA, 10) || 0); totalB += (parseInt(m.scoreB, 10) || 0); });

                var hasDesc = active.some(function(m) { return m.desc && m.desc.trim(); });

                /*
                 * ALL rows (header banner, column header, data rows) share these exact same
                 * flex column definitions — that's what keeps every score vertically aligned.
                 *
                 * Layout: [NUM 2em] [NAMEA flex:1] [SCORE 10em] [NAMEB flex:1] [DESC 20%?]
                 */
                var SCORE_W  = '10em';
                var DESC_W   = '22%';
                var ROW_BASE = 'display:flex; align-items:center; width:100%; box-sizing:border-box;';
                var NUM_S    = 'width:2em; flex-shrink:0; text-align:right; padding-right:6px;';
                var NAMEA_S  = 'flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; padding-left:0.5rem;';
                var SCORE_S  = 'width:' + SCORE_W + '; flex-shrink:0; text-align:center; white-space:nowrap;';
                var NAMEB_S  = 'flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; padding-right:0.5rem;';
                var DESC_S   = 'width:' + DESC_W + '; flex-shrink:0; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:0.4rem;';

                var sb = [];
                sb.push('<div class="scoreboard-panel-inner">');

                /* ── Banner header: labelA | total score | labelB ── same columns as rows */
                sb.push('<div style="' + ROW_BASE + ' padding:0.55rem 0 0.45rem; border-bottom:2px solid rgba(108,92,231,0.35); margin-bottom:0.2rem;">');
                sb.push('<div style="' + NUM_S + '"></div>');
                sb.push('<div style="' + NAMEA_S + ' font-family:\'Exo 2\',sans-serif; font-size:2.2rem; font-weight:700; color:#e0e0e0; overflow:visible; text-overflow:clip; padding-left:0.4rem;">' + escapeHtml(labelA) + '</div>');
                sb.push('<div style="' + SCORE_S + '"><span class="scoreboard-score-main">' + totalA + ' &ndash; ' + totalB + '</span></div>');
                sb.push('<div style="' + NAMEB_S + ' font-family:\'Exo 2\',sans-serif; font-size:2.2rem; font-weight:700; color:#e0e0e0; flex:1.6; overflow:visible; text-overflow:clip; padding-right:0.4rem;">' + escapeHtml(labelB) + '</div>');
                sb.push('</div>');

                /* ── Column header row ── */
                sb.push('<div class="scoreboard-table-wrap" style="margin-top:0.3rem;">');
                sb.push('<div style="' + ROW_BASE + ' background:rgba(108,92,231,0.25); border-bottom:1px solid rgba(255,255,255,0.1); padding:0.32rem 0;">');
                sb.push('<div style="' + NUM_S + '"></div>');
                sb.push('<div style="' + NAMEA_S + ' font-weight:600; font-size:1.15rem; color:#fff;">' + escapeHtml(labelA) + '</div>');
                sb.push('<div style="' + SCORE_S + ' color:#a29bfe; font-size:0.9rem; font-weight:500; letter-spacing:0.04em;">Score</div>');
                sb.push('<div style="' + NAMEB_S + ' font-weight:600; font-size:1.15rem; color:#fff;">' + escapeHtml(labelB) + '</div>');
                if (hasDesc) sb.push('<div style="' + DESC_S + ' color:#a29bfe; font-size:0.9rem; font-weight:500;">Map / Desc</div>');
                sb.push('</div>');

                /* ── Data rows ── */
                active.forEach(function(m, i) {
                    var sA = parseInt(m.scoreA, 10) || 0;
                    var sB = parseInt(m.scoreB, 10) || 0;
                    var winA = sA > sB, winB = sB > sA;
                    var scoreCell =
                        '<span style="font-size:1.5rem; font-weight:700; color:' + (winA ? '#ffe082' : '#FFD700') + ';">' + sA + '</span>' +
                        '<span style="color:#444; font-size:1.1rem; margin:0 0.35em;">&ndash;</span>' +
                        '<span style="font-size:1.5rem; font-weight:700; color:' + (winB ? '#ffe082' : '#a29bfe') + ';">' + sB + '</span>';
                    var rowBg = (i % 2 === 1) ? 'background:rgba(255,255,255,0.03); ' : '';
                    var border = (i < active.length - 1) ? 'border-bottom:1px solid rgba(255,255,255,0.07); ' : '';
                    sb.push('<div style="' + ROW_BASE + rowBg + border + 'padding:0.38rem 0;">');
                    sb.push('<div style="' + NUM_S + ' font-size:0.85rem; color:#555;">' + escapeHtml(String(i + 1)) + '</div>');
                    sb.push('<div style="' + NAMEA_S + ' font-size:1.25rem;' + (winA ? 'font-weight:700; color:#ffe082;' : 'color:#e0e0e0;') + '">' + escapeHtml(m.a || '') + '</div>');
                    sb.push('<div style="' + SCORE_S + ' padding:0.1rem 0;">' + scoreCell + '</div>');
                    sb.push('<div style="' + NAMEB_S + ' font-size:1.25rem;' + (winB ? 'font-weight:700; color:#ffe082;' : 'color:#e0e0e0;') + '">' + escapeHtml(m.b || '') + '</div>');
                    if (hasDesc) sb.push('<div style="' + DESC_S + ' font-size:0.9rem; color:#a29bfe;">' + escapeHtml(m.desc || '') + '</div>');
                    sb.push('</div>');
                });
                sb.push('</div>'); /* scoreboard-table-wrap */
                sb.push('</div>'); /* scoreboard-panel-inner */
                return sb.join('');
            }

            window.loadAndRenderCustomScoreboard = function() {
                var container = document.getElementById('custom-scoreboard-content');
                if (!container) return;
                fetch('save_custom_scoreboard.php?_t=' + Date.now())
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        container.innerHTML = buildCustomScoreboardHTML(data);
                    })
                    .catch(function(err) {
                        container.innerHTML = '<div class="scoreboard-panel-inner"><p class="scoreboard-empty">Could not load custom scoreboard.</p></div>';
                    });
            };

            /* ---- End Custom Scoreboard ---- */

            /** Set VDO iframe src from data-src only if not already loaded (vdo.ninja), to avoid reload and preserve camera state. */
            function ensureVdoIframeLoaded(iframe) {
                if (!iframe) return;
                var dataSrc = iframe.getAttribute('data-src');
                if (!dataSrc) return;
                if (!iframe.src || iframe.src.indexOf('vdo.ninja') === -1) {
                    iframe.src = dataSrc;
                }
            }

            /** Load both VDO (vdo.ninja) iframes once on init so they run in background; we only hide/show panels. */
            function ensureVdoIframesLoadedOnce() {
                var vdoPanel = document.getElementById('vdo-full-panel-wrap');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                if (vdoPanel) ensureVdoIframeLoaded(vdoPanel.querySelector('iframe'));
                if (sc2Panel) ensureVdoIframeLoaded(sc2Panel.querySelector('iframe'));
            }

            /** Toggle visibility of both VDO panels (large + small) without affecting scene state. */
            var _vdoHidden = false;
            function toggleVdoVisibility() {
                _vdoHidden = !_vdoHidden;
                var largePanel = document.getElementById('vdo-full-panel-wrap');
                var smallPanel = document.getElementById('sc2-panel-wrap');
                var btn = document.getElementById('btn-hide-vdo');
                if (largePanel) largePanel.style.visibility = _vdoHidden ? 'hidden' : '';
                if (smallPanel) smallPanel.style.visibility = _vdoHidden ? 'hidden' : '';
                if (btn) {
                    btn.textContent = _vdoHidden ? 'Show VDO' : 'Hide VDO';
                    btn.classList.toggle('vdo-hidden', _vdoHidden);
                }
            }

            /** Force reload of VDO Ninja iframes (SC2 and VDO full). Use when camera feed is stuck. */
            var _reloadVdoLastMs = 0;
            var _reloadVdoCooldownTimer = null;
            function reloadVdo() {
                var btn = document.getElementById('btn-reload-vdo');
                var now = Date.now();
                var cooldown = 30000;
                var elapsed = now - _reloadVdoLastMs;
                if (elapsed < cooldown) {
                    var remaining = Math.ceil((cooldown - elapsed) / 1000);
                    if (btn) btn.textContent = 'Wait ' + remaining + 's';
                    return;
                }
                _reloadVdoLastMs = now;
                var vdoPanel = document.getElementById('vdo-full-panel-wrap');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                [vdoPanel, sc2Panel].forEach(function(panel) {
                    var iframe = panel ? panel.querySelector('iframe') : null;
                    if (!iframe) return;
                    var dataSrc = iframe.getAttribute('data-src');
                    if (!dataSrc) return;
                    iframe.src = '';
                    iframe.src = dataSrc;
                });
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('active');
                    btn.textContent = 'Reloaded!';
                    clearInterval(_reloadVdoCooldownTimer);
                    var remaining = 30;
                    _reloadVdoCooldownTimer = setInterval(function() {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(_reloadVdoCooldownTimer);
                            btn.disabled = false;
                            btn.classList.remove('active');
                            btn.textContent = 'Reload VDO';
                        } else {
                            btn.textContent = 'Wait ' + remaining + 's';
                        }
                    }, 1000);
                }
            }

            /** Force-reload all iframes in the stream frame (VDO, BG overlay, YT, etc.). */
            var _refreshPanelTimer = null;
            function refreshProductionPanel() {
                var btn = document.getElementById('btn-refresh-panel');
                var frame = document.querySelector('.stream-frame');
                if (!frame) return;
                frame.querySelectorAll('iframe').forEach(function(iframe) {
                    var dataSrc = iframe.getAttribute('data-src');
                    if (dataSrc) {
                        iframe.src = '';
                        iframe.src = dataSrc;
                    } else if (iframe.src) {
                        var s = iframe.src;
                        iframe.src = '';
                        iframe.src = s;
                    }
                });
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('active');
                    btn.textContent = 'Refreshed!';
                    clearTimeout(_refreshPanelTimer);
                    _refreshPanelTimer = setTimeout(function() {
                        btn.disabled = false;
                        btn.classList.remove('active');
                        btn.textContent = 'Refresh Right Panel';
                    }, 5000);
                }
            }

            function activateSc2Scene() {
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                if (bgOverlay) bgOverlay.style.display = 'none';
                if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                if (vdoFullBtn) vdoFullBtn.classList.remove('active');
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                var ytOverlay = document.getElementById('scene-overlay-yt');
                var ytBtn = document.getElementById('scene-btn-yt');
                var ytIframeOverlay = document.getElementById('scene-overlay-yt-iframe');
                var ytIframeIntroBtn = document.getElementById('scene-btn-yt-intro');
                var ytIframeBreakBtn = document.getElementById('scene-btn-yt-break');
                var ytIframePlayer = document.getElementById('yt-iframe-player');
                if (sharedOverlay) sharedOverlay.style.display = 'none';
                if (sharedBtn) sharedBtn.classList.remove('active');
                if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                if (ytOverlay) ytOverlay.style.display = 'none';
                if (ytBtn) ytBtn.classList.remove('active');
                stopBreakCountdown();
                if (ytIframeOverlay) ytIframeOverlay.style.display = 'none';
                if (ytIframePlayer) ytIframePlayer.src = '';
                if (ytIframeIntroBtn) ytIframeIntroBtn.classList.remove('active');
                if (ytIframeBreakBtn) ytIframeBreakBtn.classList.remove('active');
                clearExclusiveScenes();
                if (sc2Overlay) sc2Overlay.style.display = 'block';
                setSc2ButtonsActive(true);
                if (sc2Panel) {
                    sc2Panel.style.display = 'block';
                    var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay ? sc2Overlay.getBoundingClientRect() : { width: 1280, height: 720 });
                    applyPositionToSc2Panel(sc2Panel, pos);
                    var sc2Iframe = sc2Panel.querySelector('iframe');
                    ensureVdoIframeLoaded(sc2Iframe);
                }
                if (typeof logosEditMode !== 'undefined' && logosEditMode) updateVdoPanelsInEditMode();
            }

            function deactivateSc2Scene() {
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                var videoIframe = document.getElementById('scene-overlay-all-vdo-iframe');
                var bgBtn = document.getElementById('scene-btn-all-vdo');
                if (bgOverlay) {
                    bgOverlay.style.display = 'block';
                    bgOverlay.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1';
                }
                if (videoIframe) {
                    videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                }
                VIDEO_OVERLAY_KEYS.forEach(function(key) {
                    var b = document.getElementById('scene-btn-' + key);
                    if (b) b.classList.remove('active');
                });
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                var ytOverlay = document.getElementById('scene-overlay-yt');
                var ytBtn = document.getElementById('scene-btn-yt');
                var ytIframeOverlay = document.getElementById('scene-overlay-yt-iframe');
                var ytIframePlayer = document.getElementById('yt-iframe-player');
                var ytIframeIntroBtn = document.getElementById('scene-btn-yt-intro');
                var ytIframeBreakBtn = document.getElementById('scene-btn-yt-break');
                if (sharedOverlay) sharedOverlay.style.display = 'none';
                if (sharedBtn) sharedBtn.classList.remove('active');
                if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                if (ytOverlay) ytOverlay.style.display = 'none';
                if (ytBtn) ytBtn.classList.remove('active');
                stopBreakCountdown();
                if (ytIframeOverlay) ytIframeOverlay.style.display = 'none';
                if (ytIframePlayer) ytIframePlayer.src = '';
                if (ytIframeIntroBtn) ytIframeIntroBtn.classList.remove('active');
                if (ytIframeBreakBtn) ytIframeBreakBtn.classList.remove('active');
                if (bgBtn) bgBtn.classList.add('active');
                if (vdoFullOverlay) vdoFullOverlay.style.display = 'block';
                if (vdoFullBtn) vdoFullBtn.classList.add('active');
                var vdoPanel = document.getElementById('vdo-full-panel-wrap');
                if (vdoPanel) {
                    var vdoPos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(vdoFullOverlay ? vdoFullOverlay.getBoundingClientRect() : { width: 1280, height: 720 });
                    applyPositionToVdoFullPanel(vdoPanel, vdoPos);
                    var vdoIframe = vdoPanel.querySelector('iframe');
                    ensureVdoIframeLoaded(vdoIframe);
                }
                if (sc2Overlay) sc2Overlay.style.display = 'none';
                setSc2ButtonsActive(false);
                if (typeof logosEditMode !== 'undefined' && logosEditMode) updateVdoPanelsInEditMode();
            }

            function setSc2ButtonsActive(isActive) {
                var b1 = document.getElementById('scene-btn-sc2');
                var b2 = document.getElementById('scene-btn-sc2-quick');
                if (b1) b1.classList.toggle('active', !!isActive);
                if (b2) b2.classList.toggle('active', !!isActive);
            }

            /**
             * Apply layout to match current SC2 button state.
             * SC2 ON: no BG, video overlay hidden; SC2 overlay + small VDO; VDO full hidden.
             * SC2 OFF: BG in video overlay; SC2 hidden; VDO full shown.
             */
            function applyLayoutFromSc2Button() {
                var sc2Btn = document.getElementById('scene-btn-sc2');
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                var videoIframe = document.getElementById('scene-overlay-all-vdo-iframe');
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                var vdoPanel = document.getElementById('vdo-full-panel-wrap');
                if (!sc2Btn) return;
                VIDEO_OVERLAY_KEYS.forEach(function(key) {
                    var b = document.getElementById('scene-btn-' + key);
                    if (b) b.classList.remove('active');
                });
                setSceneVideoError('');
                if (sc2Btn.classList.contains('active')) {
                    if (bgOverlay) bgOverlay.style.display = 'none';
                    if (videoIframe) videoIframe.removeAttribute('src');
                    var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                    var sharedBtn = document.getElementById('scene-btn-shared-window');
                    var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                    var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                    var ytOverlay = document.getElementById('scene-overlay-yt');
                    var ytBtn = document.getElementById('scene-btn-yt');
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    if (ytOverlay) ytOverlay.style.display = 'none';
                    if (ytBtn) ytBtn.classList.remove('active');
                    var ytIframeOverlayAl = document.getElementById('scene-overlay-yt-iframe');
                    var ytIframePlayerAl = document.getElementById('yt-iframe-player');
                    var ytIframeIntroBtnAl = document.getElementById('scene-btn-yt-intro');
                    var ytIframeBreakBtnAl = document.getElementById('scene-btn-yt-break');
                    stopBreakCountdown();
                    if (ytIframeOverlayAl) ytIframeOverlayAl.style.display = 'none';
                    if (ytIframePlayerAl) ytIframePlayerAl.src = '';
                    if (ytIframeIntroBtnAl) ytIframeIntroBtnAl.classList.remove('active');
                    if (ytIframeBreakBtnAl) ytIframeBreakBtnAl.classList.remove('active');
                    if (sc2Overlay) sc2Overlay.style.display = 'block';
                    if (sc2Panel) {
                        sc2Panel.style.display = 'block';
                        var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay ? sc2Overlay.getBoundingClientRect() : { width: 1280, height: 720 });
                        applyPositionToSc2Panel(sc2Panel, pos);
                        var sc2Iframe = sc2Panel.querySelector('iframe');
                        ensureVdoIframeLoaded(sc2Iframe);
                    }
                    if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                    if (vdoFullBtn) vdoFullBtn.classList.remove('active');
                } else {
                    showBgVideoOverlay();
                    if (vdoFullOverlay) vdoFullOverlay.style.display = 'block';
                    if (vdoFullBtn) vdoFullBtn.classList.add('active');
                    if (vdoPanel) {
                        var vdoPos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(vdoFullOverlay ? vdoFullOverlay.getBoundingClientRect() : { width: 1280, height: 720 });
                        applyPositionToVdoFullPanel(vdoPanel, vdoPos);
                        var vdoIframe = vdoPanel.querySelector('iframe');
                        ensureVdoIframeLoaded(vdoIframe);
                    }
                    if (sc2Overlay) sc2Overlay.style.display = 'none';
                    setSc2ButtonsActive(false);
                }
            }

            function showBgVideoOverlay() {
                var overlay = document.getElementById('scene-overlay-all-vdo');
                var iframe = document.getElementById('scene-overlay-all-vdo-iframe');
                var bgBtn = document.getElementById('scene-btn-all-vdo');
                if (!overlay || !iframe || !bgBtn) return;
                VIDEO_OVERLAY_KEYS.forEach(function(key) {
                    var b = document.getElementById('scene-btn-' + key);
                    if (b) b.classList.remove('active');
                });
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                var ytOverlay = document.getElementById('scene-overlay-yt');
                var ytBtn = document.getElementById('scene-btn-yt');
                if (sharedOverlay) sharedOverlay.style.display = 'none';
                if (sharedBtn) sharedBtn.classList.remove('active');
                if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                if (ytOverlay) ytOverlay.style.display = 'none';
                if (ytBtn) ytBtn.classList.remove('active');
                iframe.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                overlay.style.zIndex = getVideoOverlayDefaultZIndex();
                overlay.style.display = 'block';
                bgBtn.classList.add('active');
                /* Sync non-video-overlay scene buttons so strikethrough matches overlay state on first load */
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                if (vdoFullBtn && vdoFullOverlay && (vdoFullOverlay.style.display === 'none' || !vdoFullOverlay.style.display)) vdoFullBtn.classList.remove('active');
                if (sc2Overlay && (sc2Overlay.style.display === 'none' || !sc2Overlay.style.display)) setSc2ButtonsActive(false);
            }

            function toggleVideoOverlay(sceneId) {
                var overlay = document.getElementById('scene-overlay-all-vdo');
                var iframe = document.getElementById('scene-overlay-all-vdo-iframe');
                var btn = document.getElementById('scene-btn-' + sceneId);
                if (!overlay || !iframe || !btn) return;
                var file = VIDEO_OVERLAY_FILES[sceneId];
                if (!file) return;
                setSceneVideoError('');
                var isThisActive = btn.classList.contains('active');
                var useFront = VIDEO_OVERLAY_FRONT.indexOf(sceneId) !== -1;
                if (isThisActive) {
                    if (useFront) {
                        applyLayoutFromSc2Button();
                    } else {
                        overlay.style.display = 'none';
                        btn.classList.remove('active');
                        iframe.removeAttribute('src');
                        setSceneVideoError('');
                    }
                    return;
                }
                function doShowVideoOverlay() {
                    clearExclusiveScenes();
                    var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                    var sharedBtn = document.getElementById('scene-btn-shared-window');
                    var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                    var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                    var ytOverlay = document.getElementById('scene-overlay-yt');
                    var ytBtn = document.getElementById('scene-btn-yt');
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    if (ytOverlay) ytOverlay.style.display = 'none';
                    if (ytBtn) ytBtn.classList.remove('active');
                    var url = (file.indexOf('.html') !== -1) ? ('2026/' + file) : ('2026/video_player.php?v=' + encodeURIComponent(file) + '&_t=' + Date.now());
                    if (useFront && file.indexOf('.html') === -1) url += '&front=true';
                    iframe.src = url;
                    overlay.style.zIndex = useFront ? '99999' : getVideoOverlayDefaultZIndex();
                    overlay.style.display = 'block';
                    btn.classList.add('active');
                    var teamAudio = VIDEO_OVERLAY_AUDIO[sceneId];
                    if (teamAudio) {
                        var ap = document.querySelector('#audio-player');
                        if (ap) {
                            ap.src = 'production_files/audio/' + teamAudio;
                            var vol = document.getElementById('volume-slider');
                            ap.volume = vol ? vol.value / 100 : 1;
                            ap.load();
                            ap.play();
                        }
                    }
                    if (typeof window.mxOnSceneChange === 'function') window.mxOnSceneChange(sceneId);
                }
                if (useFront) {
                    fetch('2026/' + file, { method: 'HEAD' })
                        .then(function(r) {
                            if (!r.ok) { setSceneVideoError('error: ' + file + ' not found'); return; }
                            doShowVideoOverlay();
                        })
                        .catch(function() { setSceneVideoError('error: ' + file + ' not found'); });
                } else {
                    doShowVideoOverlay();
                }
            }

            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'video-error' && e.data.file) {
                    setSceneVideoError('error: ' + e.data.file + ' not found');
                }
            });

            showBgVideoOverlay();
            ensureVdoIframesLoadedOnce();

            function toggleSceneOverlay(sceneId) {
            if (VIDEO_OVERLAY_KEYS.indexOf(sceneId) !== -1) {
                toggleVideoOverlay(sceneId);
                return;
            }
            if (sceneId === 'scoreboard') {
                var scoreboardOverlay = document.getElementById('scoreboard-overlay');
                var scoreboardBtn = document.getElementById('scene-btn-scoreboard');
                if (!scoreboardOverlay || !scoreboardBtn) return;
                var isScoreboardActive = scoreboardOverlay.style.display === 'block';
                if (isScoreboardActive) {
                    scoreboardOverlay.style.display = 'none';
                    scoreboardOverlay.style.zIndex = '';
                    scoreboardBtn.classList.remove('active');
                    applyLayoutFromSc2Button();
                } else {
                    clearExclusiveScenes();
                    var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                    var videoIframe = document.getElementById('scene-overlay-all-vdo-iframe');
                    var bgBtn = document.getElementById('scene-btn-all-vdo');
                    var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                    var sharedBtn = document.getElementById('scene-btn-shared-window');
                    var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                    var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                    var ytOverlay = document.getElementById('scene-overlay-yt');
                    var ytBtn = document.getElementById('scene-btn-yt');
                    var logosOverlay = document.getElementById('logos-overlay');
                    var logosBtn = document.getElementById('scene-btn-logos');
                    var sc2Overlay = document.getElementById('sc2-overlay');
                    var sc2Panel = document.getElementById('sc2-panel-wrap');
                    var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                    var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                    setSceneVideoError('');
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    if (ytOverlay) ytOverlay.style.display = 'none';
                    if (ytBtn) ytBtn.classList.remove('active');
                    if (bgOverlay) { bgOverlay.style.display = 'block'; bgOverlay.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1'; }
                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                    if (bgBtn) bgBtn.classList.add('active');
                    if (logosOverlay) { logosOverlay.style.display = 'block'; if (logosBtn) logosBtn.classList.add('active'); }
                    if (typeof updateLogosOverlay === 'function') updateLogosOverlay();
                    if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                    if (vdoFullBtn) vdoFullBtn.classList.remove('active');
                    if (sc2Overlay) { sc2Overlay.style.display = 'block'; sc2Overlay.style.zIndex = '60000'; }
                    if (sc2Panel) {
                        sc2Panel.style.display = 'block';
                        var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay ? sc2Overlay.getBoundingClientRect() : { width: 1280, height: 720 });
                        applyPositionToSc2Panel(sc2Panel, pos);
                        var sc2Iframe = sc2Panel.querySelector('iframe');
                        ensureVdoIframeLoaded(sc2Iframe);
                    }
                    scoreboardOverlay.style.display = 'block';
                    scoreboardOverlay.style.zIndex = '99998';
                    scoreboardBtn.classList.add('active');
                    if (typeof loadAndRenderScoreboard === 'function') loadAndRenderScoreboard();
                }
                return;
            }
            if (sceneId === 'custom-scoreboard') {
                var csbOverlay = document.getElementById('custom-scoreboard-overlay');
                var csbBtn = document.getElementById('scene-btn-custom-scoreboard');
                if (!csbOverlay || !csbBtn) return;
                var isCsbActive = csbOverlay.style.display === 'block';
                if (isCsbActive) {
                    csbOverlay.style.display = 'none';
                    csbOverlay.style.zIndex = '';
                    csbBtn.classList.remove('active');
                    applyLayoutFromSc2Button();
                } else {
                    clearExclusiveScenes();
                    var bgOverlayCsb = document.getElementById('scene-overlay-all-vdo');
                    var videoIframeCsb = document.getElementById('scene-overlay-all-vdo-iframe');
                    var bgBtnCsb = document.getElementById('scene-btn-all-vdo');
                    var sharedOverlayCsb = document.getElementById('scene-overlay-shared-window');
                    var sharedBtnCsb = document.getElementById('scene-btn-shared-window');
                    var fullSharedOverlayCsb = document.getElementById('scene-overlay-full-shared-panel');
                    var fullSharedBtnCsb = document.getElementById('scene-btn-full-shared');
                    var ytOverlayCsb = document.getElementById('scene-overlay-yt');
                    var ytBtnCsb = document.getElementById('scene-btn-yt');
                    var logosOverlayCsb = document.getElementById('logos-overlay');
                    var logosBtnCsb = document.getElementById('scene-btn-logos');
                    var sc2OverlayCsb = document.getElementById('sc2-overlay');
                    var sc2PanelCsb = document.getElementById('sc2-panel-wrap');
                    var vdoFullOverlayCsb = document.getElementById('scene-overlay-vdo-full');
                    var vdoFullBtnCsb = document.getElementById('scene-btn-vdo-full');
                    setSceneVideoError('');
                    if (sharedOverlayCsb) sharedOverlayCsb.style.display = 'none';
                    if (sharedBtnCsb) sharedBtnCsb.classList.remove('active');
                    if (fullSharedOverlayCsb) fullSharedOverlayCsb.style.display = 'none';
                    if (fullSharedBtnCsb) fullSharedBtnCsb.classList.remove('active');
                    if (ytOverlayCsb) ytOverlayCsb.style.display = 'none';
                    if (ytBtnCsb) ytBtnCsb.classList.remove('active');
                    if (bgOverlayCsb) { bgOverlayCsb.style.display = 'block'; bgOverlayCsb.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1'; }
                    if (videoIframeCsb) videoIframeCsb.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                    if (bgBtnCsb) bgBtnCsb.classList.add('active');
                    if (logosOverlayCsb) { logosOverlayCsb.style.display = 'block'; if (logosBtnCsb) logosBtnCsb.classList.add('active'); }
                    if (typeof updateLogosOverlay === 'function') updateLogosOverlay();
                    if (vdoFullOverlayCsb) vdoFullOverlayCsb.style.display = 'none';
                    if (vdoFullBtnCsb) vdoFullBtnCsb.classList.remove('active');
                    if (sc2OverlayCsb) { sc2OverlayCsb.style.display = 'block'; sc2OverlayCsb.style.zIndex = '60000'; }
                    if (sc2PanelCsb) {
                        sc2PanelCsb.style.display = 'block';
                        var posCsb = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2OverlayCsb ? sc2OverlayCsb.getBoundingClientRect() : { width: 1280, height: 720 });
                        applyPositionToSc2Panel(sc2PanelCsb, posCsb);
                        var sc2IframeCsb = sc2PanelCsb.querySelector('iframe');
                        ensureVdoIframeLoaded(sc2IframeCsb);
                    }
                    csbOverlay.style.display = 'block';
                    csbOverlay.style.zIndex = '99998';
                    csbBtn.classList.add('active');
                    if (typeof loadAndRenderCustomScoreboard === 'function') loadAndRenderCustomScoreboard();
                }
                return;
            }
            if (sceneId === 'vdo-full') {
                const overlay = document.getElementById('scene-overlay-vdo-full');
                const panel = document.getElementById('vdo-full-panel-wrap');
                const btn = document.getElementById('scene-btn-vdo-full');
                if (overlay.style.display === 'none' || !overlay.style.display) {
                    overlay.style.display = 'block';
                    btn.classList.add('active');
                    if (panel) {
                        var pos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(overlay.getBoundingClientRect());
                        applyPositionToVdoFullPanel(panel, pos);
                        var iframe = panel.querySelector('iframe');
                        ensureVdoIframeLoaded(iframe);
                    }
                } else {
                    overlay.style.display = 'none';
                    btn.classList.remove('active');
                }
            } else if (sceneId === 'logos') {
                const overlay = document.getElementById('logos-overlay');
                const btn = document.getElementById('scene-btn-logos');
                if (overlay.style.display === 'none' || !overlay.style.display) {
                    overlay.style.display = 'block';
                    btn.classList.add('active');
                    updateLogosOverlay();
                } else {
                    overlay.style.display = 'none';
                    btn.classList.remove('active');
                }
            } else if (sceneId === 'sc2' || sceneId === 'sc2-quick') {
                clearExclusiveScenes();
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2ActiveBtn = document.getElementById('scene-btn-sc2');
                if (!sc2ActiveBtn || !sc2ActiveBtn.classList.contains('active')) {
                    if (sceneId === 'sc2') {
                        /* SC2 on: 1) Random Music, 2) stinger + FSL Intro simultaneously, 3) activate */
                        var randomMusicForm = document.getElementById('media-form-7');
                        if (randomMusicForm) randomMusicForm.requestSubmit();
                        var fslIntroForm = document.getElementById('media-form-8');
                        if (fslIntroForm) fslIntroForm.requestSubmit();
                        playTransitionVideo({
                            videoSrc: '2026/2026_FSL_logo_reveal_GS_fast.mp4',
                            fadeInMs: 500,
                            fadeOutMs: 500,
                            onComplete: function() { activateSc2Scene(); }
                        });
                    } else {
                        /* Quick on: no animation, immediately activate */
                        activateSc2Scene(false);
                    }
                } else {
                    deactivateSc2Scene();
                }
            } else if (sceneId === 'shared-window') {
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedVideo = document.getElementById('shared-window-video');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                var bgBtn = document.getElementById('scene-btn-all-vdo');
                var isSharedActive = sharedOverlay && (sharedOverlay.style.display === 'block');
                var hasStream = sharedVideo && sharedVideo.srcObject && sharedVideo.srcObject.getTracks().some(function(t) { return t.readyState === 'live'; });

                if (hasStream && isSharedActive) {
                    sharedOverlay.style.display = 'none';
                    sharedBtn.classList.remove('active');
                    applyLayoutFromSc2Button();
                } else if (hasStream) {
                    VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById('scene-btn-' + k); if (b) b.classList.remove('active'); });
                    if (bgOverlay) bgOverlay.style.display = 'none';
                    var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                    var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                    var ytOverlay = document.getElementById('scene-overlay-yt');
                    var ytBtn = document.getElementById('scene-btn-yt');
                    var sc2Overlay = document.getElementById('sc2-overlay');
                    var sc2Panel = document.getElementById('sc2-panel-wrap');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    if (ytOverlay) ytOverlay.style.display = 'none';
                    if (ytBtn) ytBtn.classList.remove('active');
                    if (sc2Overlay) { sc2Overlay.style.display = 'none'; sc2Overlay.style.zIndex = ''; }
                    if (sc2Panel) sc2Panel.style.display = 'none';
                    var scoreboardOverlayShared = document.getElementById('scoreboard-overlay');
                    var scoreboardBtnShared = document.getElementById('scene-btn-scoreboard');
                    if (scoreboardOverlayShared) { scoreboardOverlayShared.style.display = 'none'; scoreboardOverlayShared.style.zIndex = ''; }
                    if (scoreboardBtnShared) scoreboardBtnShared.classList.remove('active');
                    sharedOverlay.style.display = 'block';
                    sharedBtn.classList.add('active');
                } else {
                    var dialog = document.getElementById('shared-window-dialog');
                    if (dialog) {
                        dialog.style.display = 'flex';
                        var cancelBtn = document.getElementById('shared-window-dialog-cancel');
                        var doSelect = function(showPartial) {
                            dialog.style.display = 'none';
                            if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                                setSceneVideoError('getDisplayMedia not supported');
                                return;
                            }
                            navigator.mediaDevices.getDisplayMedia({ video: true, audio: false }).then(function(stream) {
                                sharedVideo.srcObject = stream;
                                sharedVideo.play();
                                var fullSharedVideo = document.getElementById('full-shared-panel-video');
                                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                                var ytVideo = document.getElementById('yt-video');
                                var ytOverlay = document.getElementById('scene-overlay-yt');
                                var ytBtn = document.getElementById('scene-btn-yt');
                                if (fullSharedVideo) { fullSharedVideo.srcObject = stream; fullSharedVideo.play(); }
                                if (fullSharedBtn) fullSharedBtn.style.display = '';
                                if (ytVideo) { ytVideo.srcObject = stream; ytVideo.play(); }
                                if (ytBtn) ytBtn.style.display = '';
                                stream.getVideoTracks()[0].addEventListener('ended', function onEnded() {
                                    stream.getVideoTracks()[0].removeEventListener('ended', onEnded);
                                    sharedVideo.srcObject = null;
                                    if (fullSharedVideo) fullSharedVideo.srcObject = null;
                                    if (ytVideo) ytVideo.srcObject = null;
                                    sharedOverlay.style.display = 'none';
                                    sharedBtn.classList.remove('active');
                                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                                    if (fullSharedBtn) { fullSharedBtn.style.display = 'none'; fullSharedBtn.classList.remove('active'); }
                                    if (ytOverlay) ytOverlay.style.display = 'none';
                                    if (ytBtn) { ytBtn.style.display = 'none'; ytBtn.classList.remove('active'); }
                                    var sc2OverlayEl = document.getElementById('sc2-overlay');
                                    if (sc2OverlayEl) sc2OverlayEl.style.zIndex = '';
                                    applyLayoutFromSc2Button();
                                });
                                VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById('scene-btn-' + k); if (b) b.classList.remove('active'); });
                                if (showPartial) {
                                    if (bgOverlay) { bgOverlay.style.display = 'block'; bgOverlay.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1'; }
                                    var videoIframe = document.getElementById('scene-overlay-all-vdo-iframe');
                                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                                    if (bgBtn) bgBtn.classList.add('active');
                                    fullSharedOverlay.style.zIndex = '25000'; /* above BG, below logos when in layer order */
                                    fullSharedOverlay.style.display = 'block';
                                    fullSharedBtn.classList.add('active');
                                    var logosOverlay = document.getElementById('logos-overlay');
                                    var logosBtn = document.getElementById('scene-btn-logos');
                                    var sc2Overlay = document.getElementById('sc2-overlay');
                                    var sc2Panel = document.getElementById('sc2-panel-wrap');
                                    if (logosOverlay) { logosOverlay.style.display = 'block'; if (logosBtn) logosBtn.classList.add('active'); }
                                    updateLogosOverlay();
                                    if (sc2Overlay) { sc2Overlay.style.display = 'block'; sc2Overlay.style.zIndex = '60000'; }
                                    if (sc2Panel) {
                                        sc2Panel.style.display = 'block';
                                        var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay.getBoundingClientRect());
                                        applyPositionToSc2Panel(sc2Panel, pos);
                                        var sc2Iframe = sc2Panel.querySelector('iframe');
                                        ensureVdoIframeLoaded(sc2Iframe);
                                    }
                                } else {
                                    if (bgOverlay) bgOverlay.style.display = 'none';
                                    sharedOverlay.style.display = 'block';
                                    sharedBtn.classList.add('active');
                                }
                            }).catch(function(err) {
                                if (err.name !== 'NotAllowedError') setSceneVideoError(err.message || 'Share failed');
                            });
                        };
                        dialog.querySelectorAll('.shared-dialog-mode-btn').forEach(function(btn) {
                            btn.onclick = function() { doSelect(btn.getAttribute('data-mode') === 'partial'); };
                        });
                        cancelBtn.onclick = function() { dialog.style.display = 'none'; };
                    }
                }
            } else if (sceneId === 'full-shared') {
                var sharedVideo = document.getElementById('shared-window-video');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedVideo = document.getElementById('full-shared-panel-video');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                var bgOverlay = document.getElementById('scene-overlay-all-vdo');
                var videoIframe = document.getElementById('scene-overlay-all-vdo-iframe');
                var bgBtn = document.getElementById('scene-btn-all-vdo');
                var vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                var vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                var logosOverlay = document.getElementById('logos-overlay');
                var logosBtn = document.getElementById('scene-btn-logos');
                var sc2Overlay = document.getElementById('sc2-overlay');
                var sc2Panel = document.getElementById('sc2-panel-wrap');
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var hasStream = sharedVideo && sharedVideo.srcObject && sharedVideo.srcObject.getTracks().some(function(t) { return t.readyState === 'live'; });
                var isFullSharedActive = fullSharedOverlay && (fullSharedOverlay.style.display === 'block');

                if (!hasStream) return;
                if (isFullSharedActive) {
                    fullSharedOverlay.style.display = 'none';
                    fullSharedBtn.classList.remove('active');
                    if (sc2Overlay) sc2Overlay.style.zIndex = '';
                    applyLayoutFromSc2Button();
                } else {
                    VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById('scene-btn-' + k); if (b) b.classList.remove('active'); });
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (bgOverlay) {
                        bgOverlay.style.display = 'block';
                        bgOverlay.style.zIndex = typeof getVideoOverlayDefaultZIndex === 'function' ? getVideoOverlayDefaultZIndex() : '1';
                    }
                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(VIDEO_OVERLAY_FILES['all-vdo']) + '&_t=' + Date.now();
                    if (bgBtn) bgBtn.classList.add('active');
                    fullSharedVideo.srcObject = sharedVideo.srcObject;
                    fullSharedVideo.play();
                    fullSharedOverlay.style.zIndex = '25000'; /* above BG, below logos when in layer order */
                    fullSharedOverlay.style.display = 'block';
                    fullSharedBtn.classList.add('active');
                    if (logosOverlay) { logosOverlay.style.display = 'block'; if (logosBtn) logosBtn.classList.add('active'); }
                    updateLogosOverlay();
                    if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                    if (vdoFullBtn) vdoFullBtn.classList.remove('active');
                    var scoreboardOverlayFullShared = document.getElementById('scoreboard-overlay');
                    var scoreboardBtnFullShared = document.getElementById('scene-btn-scoreboard');
                    if (scoreboardOverlayFullShared) { scoreboardOverlayFullShared.style.display = 'none'; scoreboardOverlayFullShared.style.zIndex = ''; }
                    if (scoreboardBtnFullShared) scoreboardBtnFullShared.classList.remove('active');
                    if (sc2Overlay) { sc2Overlay.style.display = 'block'; sc2Overlay.style.zIndex = '60000'; }
                    if (sc2Panel) {
                        sc2Panel.style.display = 'block';
                        var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay.getBoundingClientRect());
                        applyPositionToSc2Panel(sc2Panel, pos);
                        var sc2Iframe = sc2Panel.querySelector('iframe');
                        ensureVdoIframeLoaded(sc2Iframe);
                    }
                }
            } else if (sceneId === 'yt') {
                var sharedVideo = document.getElementById('shared-window-video');
                var ytOverlay = document.getElementById('scene-overlay-yt');
                var ytVideo = document.getElementById('yt-video');
                var ytBtn = document.getElementById('scene-btn-yt');
                var sharedOverlay = document.getElementById('scene-overlay-shared-window');
                var sharedBtn = document.getElementById('scene-btn-shared-window');
                var fullSharedOverlay = document.getElementById('scene-overlay-full-shared-panel');
                var fullSharedBtn = document.getElementById('scene-btn-full-shared');
                var hasStream = sharedVideo && sharedVideo.srcObject && sharedVideo.srcObject.getTracks().some(function(t) { return t.readyState === 'live'; });
                var isYtActive = ytOverlay && (ytOverlay.style.display === 'block');

                if (!hasStream) return;
                if (isYtActive) {
                    ytOverlay.style.display = 'none';
                    ytBtn.classList.remove('active');
                    applyLayoutFromSc2Button();
                } else {
                    VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById('scene-btn-' + k); if (b) b.classList.remove('active'); });
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    var scoreboardOverlayYt = document.getElementById('scoreboard-overlay');
                    var scoreboardBtnYt = document.getElementById('scene-btn-scoreboard');
                    if (scoreboardOverlayYt) { scoreboardOverlayYt.style.display = 'none'; scoreboardOverlayYt.style.zIndex = ''; }
                    if (scoreboardBtnYt) scoreboardBtnYt.classList.remove('active');
                    ytVideo.srcObject = sharedVideo.srcObject;
                    ytVideo.play();
                    applyYtCropToVideo();
                    ytOverlay.style.display = 'block';
                    ytBtn.classList.add('active');
                }
            }
        }

        let chartDisplayed = false;
        
        function toggleExternalChart() {
            const chartOverlay = document.getElementById("external-chart-overlay");
            const chartButton = document.getElementById("chart-toggle-btn");
            const playerName = document.getElementById("player-input").value.trim();
            const division = document.getElementById("division-input").value.trim();
            
            if (chartDisplayed) {
                // Hide the chart
                chartOverlay.style.display = 'none';
                chartOverlay.innerHTML = '';
                chartDisplayed = false;
                chartButton.textContent = "Load External Chart";
                document.getElementById("error-message").textContent = "";
                return;
            }
            
            if (playerName && division) {
                const baseUrl = window.spiderChartBaseUrl || "http://localhost/psistorm.com/fsl/view_spider_chart_player.php";
                const chartUrl = `${baseUrl}?name=${encodeURIComponent(playerName)}&division=${encodeURIComponent(division)}`;
                
                const pos = window.displayPositions ? window.displayPositions.externalChart : {scale: '1.1'};
                const scale = pos.scale || '1.1';
                
                // Create iframe in dedicated overlay div, centered in right panel. Shorter height crops bottom black space.
                chartOverlay.innerHTML = `
                    <div style="
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        width: 100%;
                        height: 100%;
                    ">
                        <div class="external-chart-player-label" style="
                            flex-shrink: 0;
                            margin-bottom: 8px;
                            pointer-events: auto;
                        "></div>
                        <div style="
                            transform: scale(${scale});
                            transform-origin: center center;
                            overflow: hidden;
                        ">
                            <iframe 
                                src="${chartUrl}" 
                                width="800" 
                                height="600"
                                frameborder="0"
                                scrolling="no"
                                style="border: none; overflow: hidden; pointer-events: auto; display: block;">
                            </iframe>
                        </div>
                    </div>
                `;
                
                const labelBox = chartOverlay.querySelector('.external-chart-player-label');
                if (labelBox && typeof window.setPlayerLabelContent === 'function') {
                    window.setPlayerLabelContent(labelBox, playerName);
                }
                
                chartOverlay.style.display = 'flex';
                chartDisplayed = true;
                chartButton.textContent = "Hide External Chart";
                document.getElementById("error-message").textContent = "";
            } else {
                document.getElementById("error-message").textContent = "Please enter both player name and division";
            }
        }
    </script>

    <script>
        // ── Auth / User bar ──────────────────────────────────────────────
        window.CURRENT_USER = <?php echo json_encode($_SESSION['username']); ?>;

        document.getElementById('user-bar-logout').addEventListener('click', function() {
            var fd = new FormData();
            fd.append('action', 'logout');
            fetch('auth.php', { method: 'POST', body: fd })
                .then(function(){ window.location.reload(); })
                .catch(function(){ window.location.reload(); });
        });

        // Username button: open Settings > Account section
        document.getElementById('user-bar-name').addEventListener('click', function() {
            var settingsSection = document.getElementById('settings-section');
            var settingsBtn     = document.getElementById('btn-settings');
            var accountSection  = document.getElementById('account-section');
            var accountBtn      = document.getElementById('btn-account');
            if (settingsSection.style.display !== 'block') {
                settingsSection.style.display = 'block';
                if (settingsBtn) settingsBtn.textContent = 'Hide Settings';
            }
            if (accountSection.style.display !== 'block') {
                accountSection.style.display = 'block';
                if (accountBtn) accountBtn.textContent = 'Hide Change Password';
            }
            accountSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            document.getElementById('chpw-current').focus();
        });

        // ── Change password (inline in Settings > Account) ───────────────
        window.toggleAccount = function toggleAccount(btn) {
            var el = document.getElementById('account-section');
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
            if (btn) btn.classList.toggle('open', el.style.display === 'block');
            if (el.style.display === 'block') {
                document.getElementById('chpw-current').focus();
            }
        }

        document.getElementById('chpw-save').addEventListener('click', function() {
            var cur  = document.getElementById('chpw-current').value;
            var nw   = document.getElementById('chpw-new').value;
            var conf = document.getElementById('chpw-confirm').value;
            var err  = document.getElementById('chpw-error');
            var ok   = document.getElementById('chpw-ok');
            var btn  = this;
            ok.style.display = 'none';
            if (!cur) { err.textContent = 'Enter your current password.'; return; }
            if (!nw)  { err.textContent = 'Enter a new password.'; return; }
            if (nw !== conf) { err.textContent = 'New passwords do not match.'; return; }
            err.textContent = '';
            btn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'change_password');
            fd.append('current_password', cur);
            fd.append('new_password', nw);
            fetch('auth.php', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.ok) {
                        document.getElementById('chpw-current').value = '';
                        document.getElementById('chpw-new').value = '';
                        document.getElementById('chpw-confirm').value = '';
                        ok.style.display = 'block';
                        err.textContent = '';
                    } else {
                        err.textContent = res.error || 'Error saving password.';
                    }
                    btn.disabled = false;
                })
                .catch(function(){ err.textContent = 'Network error.'; btn.disabled = false; });
        });
    </script>

    <!-- StreamElements live alerts -->
    <div id="se-alert-overlay" style="position:fixed;top:8px;left:270px;z-index:99998;pointer-events:none;width:200px;overflow:visible;"></div>

    <style>
    #se-alert-overlay .se-card {
        --ac: #9147ff;
        width: 500px;
        background: linear-gradient(135deg, #0a0a0f 0%, #12122a 100%);
        border: 2px solid var(--ac);
        border-radius: 14px;
        padding: 24px 32px;
        color: #fff;
        font-family: 'Segoe UI', Arial, sans-serif;
        text-align: center;
        box-shadow: 0 0 40px var(--ac), 0 12px 48px rgba(0,0,0,0.9);
        transform-origin: top left;
        animation: seIn 0.65s cubic-bezier(0.34,1.56,0.64,1) forwards, seGlow 1.8s ease-in-out 0.65s infinite;
    }
    #se-alert-overlay .se-card.se-out {
        animation: seOut 0.5s ease-in forwards !important;
    }
    @keyframes seIn {
        0%   { transform: scale(0.08) translateY(400px); opacity: 0; }
        70%  { transform: scale(0.42) translateY(-30px); opacity: 1; }
        100% { transform: scale(0.4); opacity: 1; }
    }
    @keyframes seOut {
        0%   { transform: scale(0.4); opacity: 1; }
        100% { transform: scale(0.08) translateY(300px); opacity: 0; }
    }
    @keyframes seGlow {
        0%,100% { box-shadow: 0 0 30px var(--ac), 0 12px 48px rgba(0,0,0,0.9); }
        50%      { box-shadow: 0 0 70px var(--ac), 0 0 100px var(--ac), 0 12px 48px rgba(0,0,0,0.9); }
    }
    @keyframes sePulse {
        0%,100% { transform: scale(1); }
        50%     { transform: scale(1.18); }
    }
    .se-icon  { font-size: 3rem; display: block; margin-bottom: 8px; animation: sePulse 0.9s ease-in-out infinite; }
    .se-type  { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 4px; color: var(--ac); font-weight: 700; margin-bottom: 8px; }
    .se-user  { font-size: 2.2rem; font-weight: 900; color: #fff; text-shadow: 0 0 24px var(--ac); line-height: 1.1; }
    .se-extra { font-size: 1rem; color: #bbb; margin-top: 6px; }
    .se-bar   { height: 4px; border-radius: 2px; background: var(--ac); margin-top: 16px;
                animation: seBar 8s linear forwards; transform-origin: left; }
    @keyframes seBar { 0% { transform: scaleX(1); } 100% { transform: scaleX(0); } }
    </style>

    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    <script>
    (function() {
        var SE_TOKEN = '<?php echo SE_JWT; ?>';

        var TYPE_CFG = {
            'follow':     { label: 'New Follower', icon: '💜', color: '#9147ff' },
            'subscriber': { label: 'New Sub',      icon: '⭐', color: '#f0a500' },
            'cheer':      { label: 'Cheer',        icon: '💎', color: '#1db954' },
            'tip':        { label: 'Donation',     icon: '💰', color: '#e91e8c' },
            'raid':       { label: 'Raid!',        icon: '⚔️', color: '#ff4500' },
            'host':       { label: 'Host',         icon: '📡', color: '#1da1f2' },
        };

        function playChime() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                [523, 659, 784, 1047].forEach(function(freq, i) {
                    var osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine'; osc.frequency.value = freq;
                    var t = ctx.currentTime + i * 0.13;
                    gain.gain.setValueAtTime(0, t);
                    gain.gain.linearRampToValueAtTime(0.25, t + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
                    osc.start(t); osc.stop(t + 0.5);
                });
            } catch(e) {}
        }

        function showAlert(type, username, extra) {
            var cfg = TYPE_CFG[type] || { label: type, icon: '🔔', color: '#9147ff' };
            var overlay = document.getElementById('se-alert-overlay');
            overlay.innerHTML = '';
            var card = document.createElement('div');
            card.className = 'se-card';
            card.style.setProperty('--ac', cfg.color);
            card.innerHTML =
                '<span class="se-icon">' + cfg.icon + '</span>' +
                '<div class="se-type">' + cfg.label + '</div>' +
                '<div class="se-user">' + (username || 'Someone') + '</div>' +
                (extra ? '<div class="se-extra">' + extra + '</div>' : '') +
                '<div class="se-bar"></div>';
            overlay.appendChild(card);
            playChime();
            setTimeout(function() {
                card.classList.add('se-out');
                setTimeout(function() { overlay.innerHTML = ''; }, 600);
            }, 8000);
        }

        var socket = io('https://realtime.streamelements.com', { transports: ['websocket'] });
        socket.on('connect', function() {
            console.log('[SE] Connected, authenticating...');
            socket.emit('authenticate', { method: 'jwt', token: SE_TOKEN });
        });
        socket.on('authenticated', function() { console.log('[SE] Authenticated'); });
        socket.on('unauthorized',  function(e) { console.warn('[SE] Unauthorized:', e); });
        socket.on('event', function(data) {
            var type = data.type || '';
            var username = (data.data && (data.data.username || data.data.displayName || data.data.name)) || '';
            var extra = '';
            if (data.data) {
                if (data.data.amount)  extra = data.data.amount + (data.data.currency ? ' ' + data.data.currency : '');
                if (data.data.viewers) extra = data.data.viewers + ' viewers';
                if (data.data.message) extra = data.data.message;
            }
            showAlert(type, username, extra);
        });
        socket.on('disconnect', function() { console.log('[SE] Disconnected'); });
    })();

    // ── Music config globals (editable via Settings) ─────────────────────────
    var MX_TRACKS    = <?php echo json_encode($moodSongs,    JSON_UNESCAPED_SLASHES); ?>;
    var MX_SCENE_MAP = <?php echo json_encode($sceneMoodMap, JSON_UNESCAPED_SLASHES); ?>;

    // ── Music Player ────────────────────────────────────────────────
    (function () {
        var MX_LABELS = {
            opening_high:      'Open Hi',    opening_alert:     'Alert',
            combat_mid:        'Combat',      combat_high:       'Combat+',
            combat_extreme:    'Extreme',     climax:            'Climax',
            analysis:          'Analysis',    analysis_low:      'Analyze Lo',
            chill:             'Chill',       chill_low:         'Chill Lo',
            replay:            'Replay',      replay_reflective: 'Replay Lo',
            victory:           'Victory',     defeat:            'Defeat',
            defeat_somber:     'Defeat Lo',   suspense:          'Suspense',
        };

        var mx = null, mxGain = null;
        var mxCur = null, mxNxt = null;
        var mxPaused = false, mxMood = null, mxTrack = null, mxTimer = null;
        // Scene-aware playback state
        var mxSongIdx = 0;         // current song index within active mood
        var mxCurScene = null;     // current scene key
        var mxSceneMoods = null;   // mood list for current scene
        var mxSceneMoodIdx = 0;    // current index into mxSceneMoods

        var statusEl  = document.getElementById('lpMusicStatus');
        var volInput  = document.getElementById('lpMusicVol');
        var fadeInput = document.getElementById('lpMusicFade');
        var ppBtn     = document.getElementById('lpMusicPlayPause');
        var seekEl    = document.getElementById('lpMusicSeek');
        var timeNow   = document.getElementById('lpMusicTimeNow');
        var timeDur   = document.getElementById('lpMusicTimeDur');
        var mxSeekDragging = false;

        function mxFmtTime(s) {
            if (!isFinite(s) || s < 0) return '-:--';
            var m = Math.floor(s / 60);
            var sec = Math.floor(s % 60);
            return m + ':' + (sec < 10 ? '0' : '') + sec;
        }

        function mxSeekReset() {
            seekEl.value = 0;
            timeNow.textContent = '0:00';
            timeDur.textContent = '-:--';
        }

        seekEl.addEventListener('mousedown',  function () { mxSeekDragging = true; });
        seekEl.addEventListener('touchstart', function () { mxSeekDragging = true; }, { passive: true });
        seekEl.addEventListener('change', function () {
            mxSeekDragging = false;
            if (!mxCur) return;
            var dur = mxCur.audio.duration;
            if (!isFinite(dur) || dur <= 0) return;
            mxCur.audio.currentTime = (Number(seekEl.value) / 1000) * dur;
            mxSchedule(mxMood, mxTrack, mxSongIdx);
        });

        (function mxRafLoop() {
            requestAnimationFrame(mxRafLoop);
            if (!mxCur || mxSeekDragging) return;
            var dur = mxCur.audio.duration;
            var cur = mxCur.audio.currentTime;
            if (isFinite(dur) && dur > 0) {
                seekEl.value = Math.round((cur / dur) * 1000);
                timeNow.textContent = mxFmtTime(cur);
                timeDur.textContent = mxFmtTime(dur);
            }
        })();

        volInput.addEventListener('input', function () {
            if (mxGain) mxGain.gain.value = Number(volInput.value) / 100;
        });

        document.getElementById('lpMusicStop').addEventListener('click', mxStop);
        ppBtn.addEventListener('click', mxTogglePlay);

        function mxSetStatus(text, type) {
            statusEl.textContent = text;
            statusEl.className = type === 'err' ? 'lp-mx-song lp-mx-err' : (type === 'playing' ? 'lp-mx-song playing' : 'lp-mx-song');
        }

        function mxBuildGrid() {
            var grid = document.getElementById('lpMusicGrid');
            grid.innerHTML = '';
            Object.keys(MX_TRACKS).forEach(function (mood) {
                var btn = document.createElement('button');
                btn.textContent = MX_LABELS[mood] || mood;
                btn.dataset.mood = mood;
                btn.title = mood;
                btn.addEventListener('click', function () {
                    mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
                    if (mxMood === mood && mxCur) {
                        // Re-click on active mood — skip to next song sequentially
                        var songs = MX_TRACKS[mood] || [];
                        mxSwitch(mood, (mxSongIdx + 1) % songs.length);
                    } else {
                        mxSwitch(mood, mxRandIdx(mood));
                    }
                });
                grid.appendChild(btn);
            });
        }

        function mxUpdateActive() {
            document.querySelectorAll('#lpMusicGrid button').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mood === mxMood);
            });
        }

        function mxEnsure() {
            if (!mx) {
                mx = new (window.AudioContext || window.webkitAudioContext)();
                mxGain = mx.createGain();
                mxGain.gain.value = Number(volInput.value) / 100;
                mxGain.connect(mx.destination);
            }
            if (mx.state === 'suspended') return mx.resume();
            return Promise.resolve();
        }

        function mxMakeDeck(file) {
            var audio = new Audio('music/' + file);
            audio.crossOrigin = 'anonymous'; audio.loop = false; audio.preload = 'auto';
            var src  = mx.createMediaElementSource(audio);
            var gain = mx.createGain(); gain.gain.value = 0;
            src.connect(gain); gain.connect(mxGain);
            return { audio: audio, gain: gain, file: file };
        }

        function mxWaitReady(audio) {
            if (audio.readyState >= 3) return Promise.resolve();
            return new Promise(function (res, rej) {
                var done = function () { audio.removeEventListener('canplay', ok); audio.removeEventListener('error', er); };
                var ok = function () { done(); res(); };
                var er = function () { done(); rej(new Error('Load failed')); };
                audio.addEventListener('canplay', ok);
                audio.addEventListener('error', er);
                audio.load();
            });
        }

        function mxClearTimer() {
            if (mxTimer) { clearTimeout(mxTimer); mxTimer = null; }
        }

        // Random starting index within a mood's song list.
        function mxRandIdx(mood) {
            var songs = MX_TRACKS[mood] || [];
            if (songs.length <= 1) return 0;
            return Math.floor(Math.random() * songs.length);
        }

        // Advance to the next mood in the scene list (or loop current mood if no scene).
        function mxAdvanceMood(autoAdvance) {
            if (mxSceneMoods && mxSceneMoods.length > 0) {
                mxSceneMoodIdx = (mxSceneMoodIdx + 1) % mxSceneMoods.length;
                var nextMood = mxSceneMoods[mxSceneMoodIdx];
                mxSwitch(nextMood, mxRandIdx(nextMood), true, autoAdvance);
            } else {
                mxSwitch(mxMood, mxRandIdx(mxMood), true, autoAdvance); // no scene → loop this mood
            }
        }

        // Derive start offset and crossfade duration from the FADE knob value (0–10).
        // fade=0 → skip 20s into song, crossfade 0.5s
        // fade=10 → start at 0s, crossfade 2s
        function mxGetFadeParams() {
            var fade = Math.max(0, Math.min(10, Number(fadeInput.value)));
            return {
                startOffset:       40 * (1 - fade / 10),
                crossfadeDuration: 0.5 + 1.5 * (fade / 10)
            };
        }

        // Schedule the auto-advance timer for when the current track ends.
        function mxSchedule(mood, file, songIdx) {
            mxClearTimer();
            if (!mxCur || mxPaused) return;
            var fade = mxGetFadeParams().crossfadeDuration;
            var dur  = mxCur.audio.duration;
            if (!isFinite(dur) || dur <= 0) return;
            var wait = Math.max(0, dur - mxCur.audio.currentTime - fade - 0.15);
            mxTimer = setTimeout(function () {
                if (mxMood !== mood || !mxCur || mxCur.file !== file || mxPaused) return;
                var songs = MX_TRACKS[mood] || [];
                var nextIdx = songIdx + 1;
                if (nextIdx < songs.length) {
                    // More songs left in this mood — play next
                    mxSwitch(mood, nextIdx, true, true);
                } else {
                    // Mood's song set finished — advance to next mood
                    mxAdvanceMood(true);
                }
            }, wait * 1000);
        }

        // Switch to mood at songIdx.
        // keepScene=true:   preserve scene tracking (internal auto-advance calls).
        // autoAdvance=true: song ended naturally — start next song from 0, short fixed crossfade.
        function mxSwitch(mood, songIdx, keepScene, autoAdvance) {
            if (songIdx === undefined || songIdx === null) songIdx = 0;
            if (!keepScene) {
                mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
            }
            var songs = MX_TRACKS[mood] || [];
            var file = songs[songIdx] || null;
            if (!file) { mxSetStatus('No tracks: ' + mood, 'err'); return; }

            mxEnsure().then(function () {
                mxClearTimer();
                var fp = autoAdvance ? { startOffset: 0, crossfadeDuration: 0.5 } : mxGetFadeParams();
                var startOffset = fp.startOffset;
                var fade = fp.crossfadeDuration;
                mxSetStatus('Loading\u2026');
                var deck = mxMakeDeck(file);
                mxWaitReady(deck.audio).then(function () {
                    if (startOffset > 0 && isFinite(deck.audio.duration) && startOffset < deck.audio.duration) {
                        deck.audio.currentTime = startOffset;
                    }
                    var now = mx.currentTime;
                    deck.gain.gain.cancelScheduledValues(now);
                    deck.gain.gain.setValueAtTime(0, now);
                    deck.audio.play().then(function () {
                        if (!mxCur) {
                            deck.gain.gain.linearRampToValueAtTime(1, now + 0.05);
                            mxCur = deck;
                        } else {
                            mxCur.gain.gain.cancelScheduledValues(now);
                            mxCur.gain.gain.setValueAtTime(mxCur.gain.gain.value, now);
                            mxCur.gain.gain.linearRampToValueAtTime(0, now + fade);
                            deck.gain.gain.linearRampToValueAtTime(1, now + fade);
                            var old = mxCur;
                            setTimeout(function () { try { old.audio.pause(); old.audio.currentTime = 0; } catch(e){} }, fade * 1000 + 100);
                            mxCur = deck;
                        }
                        mxNxt = null;
                        mxMood = mood; mxTrack = file; mxSongIdx = songIdx;
                        mxUpdateActive();
                        mxPaused = false;
                        ppBtn.innerHTML = '&#x23F8;';
                        ppBtn.classList.remove('lp-mx-dim', 'lp-mx-paused');
                        mxSchedule(mood, file, songIdx);
                        mxSetStatus(file, 'playing');
                    }).catch(function (err) {
                        if (err.name === 'NotAllowedError') {
                            // Chrome blocked play — preserve mood state so the
                            // pre-registered unlock listener can resume transparently.
                            mxMood = mood; mxTrack = file; mxSongIdx = songIdx;
                            mxUpdateActive();
                            mxPaused = true;
                            ppBtn.innerHTML = '&#9654;';
                            ppBtn.classList.add('lp-mx-dim', 'lp-mx-paused');
                            mxSetStatus('\u25b6 click play to start');
                        } else {
                            mxSetStatus(err.message, 'err');
                        }
                    });
                }).catch(function (err) { mxSetStatus(err.message, 'err'); });
            });
        }

        function mxStop() {
            mxClearTimer();
            try {
                if (mxCur) { mxCur.audio.pause(); mxCur.audio.currentTime = 0; }
                if (mxNxt) { mxNxt.audio.pause(); mxNxt.audio.currentTime = 0; }
            } catch(e) {}
            mxCur = mxNxt = null; mxMood = mxTrack = null; mxPaused = false;
            mxSongIdx = 0; mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
            ppBtn.innerHTML = '&#9654;';
            ppBtn.classList.add('lp-mx-dim');
            ppBtn.classList.remove('lp-mx-paused');
            mxUpdateActive();
            mxSetStatus('select a mood');
            mxSeekReset();
        }

        function mxTogglePlay() {
            if (!mxCur) {
                var mood = mxMood || Object.keys(MX_TRACKS)[0];
                if (mood) mxSwitch(mood, mxSongIdx, mxSceneMoods !== null);
                return;
            }
            mxEnsure().then(function () {
                if (mxPaused) {
                    mxCur.audio.play().then(function () {
                        mxPaused = false;
                        ppBtn.innerHTML = '&#x23F8;';
                        ppBtn.classList.remove('lp-mx-paused');
                        mxSchedule(mxMood, mxTrack, mxSongIdx);
                        mxSetStatus(mxTrack, 'playing');
                    });
                } else {
                    mxClearTimer();
                    mxCur.audio.pause();
                    mxPaused = true;
                    ppBtn.innerHTML = '&#9654;';
                    ppBtn.classList.add('lp-mx-paused');
                    mxSetStatus('\u23f8 ' + mxTrack);
                }
            });
        }

        // Show/hide toggle — bar click (transport/knobs stop propagation)
        (function () {
            var bar     = document.getElementById('lpMusicBar');
            var icon    = document.getElementById('lpMusicToggleIcon');
            var grid    = document.getElementById('lpMusicGrid');
            var songRow = document.querySelector('.lp-mx-song-row');
            var collapsed = true; // default closed
            grid.style.display    = 'none';
            songRow.style.display = 'none';
            icon.innerHTML        = '+';
            bar.addEventListener('click', function () {
                collapsed = !collapsed;
                grid.style.display    = collapsed ? 'none' : '';
                songRow.style.display = collapsed ? 'none' : '';
                icon.innerHTML        = collapsed ? '+' : '&#8722;';
            });
        })();

        mxBuildGrid();

        // ── Rotary knob ──────────────────────────────────────────────
        function MusicKnob(canvasId, inputId, min, max, step) {
            var canvas = document.getElementById(canvasId);
            var input  = document.getElementById(inputId);
            if (!canvas || !input) return;

            var val = parseFloat(input.value) || min;
            var dragging = false, dragY0 = 0, dragV0 = 0;

            function clamp(v) { return Math.max(min, Math.min(max, v)); }
            function snap(v)  { return Math.round(v / step) * step; }

            function set(v) {
                val = clamp(snap(v));
                // keep one decimal for non-integer steps
                input.value = (step < 1) ? val.toFixed(1) : String(val);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                draw();
            }

            function draw() {
                var W = canvas.width, H = canvas.height;
                var ctx = canvas.getContext('2d');
                var cx = W / 2, cy = H / 2;
                var R = Math.min(W, H) / 2 - 2;
                var sc = W / 46; // scale factor relative to original 46px design
                var pct = (val - min) / (max - min);

                // Angles: start at 7:30, sweep 270° clockwise
                var S = Math.PI * 0.75;
                var SWEEP = Math.PI * 1.5;
                var angle = S + pct * SWEEP;

                ctx.clearRect(0, 0, W, H);

                // Outer circle background
                ctx.beginPath();
                ctx.arc(cx, cy, R, 0, Math.PI * 2);
                ctx.fillStyle = '#dde3ed';
                ctx.fill();
                ctx.strokeStyle = '#a8b4c8';
                ctx.lineWidth = Math.max(1, 1.5 * sc);
                ctx.stroke();

                // Track arc (full range, gray)
                var trackR = R - Math.max(3, 5 * sc);
                ctx.beginPath();
                ctx.arc(cx, cy, trackR, S, S + SWEEP, false);
                ctx.strokeStyle = '#b8c4d4';
                ctx.lineWidth = Math.max(1.5, 3.5 * sc);
                ctx.lineCap = 'round';
                ctx.stroke();

                // Value arc (blue)
                if (pct > 0.005) {
                    ctx.beginPath();
                    ctx.arc(cx, cy, trackR, S, angle, false);
                    ctx.strokeStyle = '#3b82f6';
                    ctx.lineWidth = Math.max(1.5, 3.5 * sc);
                    ctx.lineCap = 'round';
                    ctx.stroke();
                }

                // Dot indicator at current angle
                var dotR = Math.max(3, 4 * sc);
                var dotX = cx + (trackR) * Math.cos(angle);
                var dotY = cy + (trackR) * Math.sin(angle);
                ctx.beginPath();
                ctx.arc(dotX, dotY, Math.max(1.5, 2.5 * sc), 0, Math.PI * 2);
                ctx.fillStyle = '#1e293b';
                ctx.fill();

                // Center value text
                ctx.fillStyle = '#1e293b';
                var fontSize = Math.max(7, Math.round(10 * sc));
                ctx.font = 'bold ' + fontSize + 'px Segoe UI, system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText((step < 1) ? val.toFixed(1) : String(val), cx, cy);
            }

            canvas.addEventListener('mousedown', function (e) {
                dragging = true;
                dragY0 = e.clientY;
                dragV0 = val;
                e.preventDefault();
            });
            window.addEventListener('mousemove', function (e) {
                if (!dragging) return;
                // 80px drag = full range
                set(dragV0 + (dragY0 - e.clientY) * (max - min) / 80);
            });
            window.addEventListener('mouseup', function () { dragging = false; });
            canvas.addEventListener('wheel', function (e) {
                e.preventDefault();
                set(val + (e.deltaY < 0 ? step : -step));
            }, { passive: false });

            // Sync if external code changes the hidden input
            input.addEventListener('change', function () {
                val = clamp(parseFloat(input.value) || min);
                draw();
            });

            draw();
        }

        new MusicKnob('lpMusicVolKnob',  'lpMusicVol',  5, 100, 1);
        new MusicKnob('lpMusicFadeKnob', 'lpMusicFade', 0, 10,  0.5);

        // ── Config editors (Scene→Moods and Mood→Songs) ──────────────
        (function () {
            function mxSaveConfig(which, data, statusEl) {
                fetch('?save_music_config=' + which, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    statusEl.textContent = res.ok ? 'Saved!' : ('Error: ' + res.error);
                    setTimeout(function () { statusEl.textContent = ''; }, 3000);
                }).catch(function (e) {
                    statusEl.textContent = 'Network error';
                    setTimeout(function () { statusEl.textContent = ''; }, 3000);
                });
            }

            // Scene → Moods editor
            var smmEd  = document.getElementById('mx-scene-map-editor');
            var smmApp = document.getElementById('mx-scene-map-apply-btn');
            var smmSav = document.getElementById('mx-scene-map-save-btn');
            var smmSts = document.getElementById('mx-scene-map-status');
            if (smmEd) smmEd.value = JSON.stringify(MX_SCENE_MAP, null, 2);
            if (smmApp && smmEd) {
                smmApp.addEventListener('click', function () {
                    try {
                        MX_SCENE_MAP = JSON.parse(smmEd.value);
                        smmSts.textContent = 'Applied!';
                        setTimeout(function () { smmSts.textContent = ''; }, 2000);
                    } catch (e) { smmSts.textContent = 'JSON error: ' + e.message; }
                });
            }
            if (smmSav && smmEd) {
                smmSav.addEventListener('click', function () {
                    try {
                        var data = JSON.parse(smmEd.value);
                        MX_SCENE_MAP = data;
                        mxSaveConfig('scene_mood_map', data, smmSts);
                    } catch (e) { smmSts.textContent = 'JSON error: ' + e.message; }
                });
            }

            // Mood → Songs editor
            var mseEd  = document.getElementById('mx-mood-songs-editor');
            var mseApp = document.getElementById('mx-mood-songs-apply-btn');
            var mseSav = document.getElementById('mx-mood-songs-save-btn');
            var mseSts = document.getElementById('mx-mood-songs-status');
            if (mseEd) mseEd.value = JSON.stringify(MX_TRACKS, null, 2);
            if (mseApp && mseEd) {
                mseApp.addEventListener('click', function () {
                    try {
                        MX_TRACKS = JSON.parse(mseEd.value);
                        mxBuildGrid();
                        mseSts.textContent = 'Applied!';
                        setTimeout(function () { mseSts.textContent = ''; }, 2000);
                    } catch (e) { mseSts.textContent = 'JSON error: ' + e.message; }
                });
            }
            if (mseSav && mseEd) {
                mseSav.addEventListener('click', function () {
                    try {
                        var data = JSON.parse(mseEd.value);
                        MX_TRACKS = data;
                        mxBuildGrid();
                        mxSaveConfig('mood_songs', data, mseSts);
                    } catch (e) { mseSts.textContent = 'JSON error: ' + e.message; }
                });
            }
        })();

        // ── Scene → Mood handler ─────────────────────────────────────
        function mxOnSceneChange(key) {
            var moods = MX_SCENE_MAP[key];
            if (!moods || !moods.length) return;
            // Pick a random starting mood from the scene's list
            var randomIdx = Math.floor(Math.random() * moods.length);
            var newMood   = moods[randomIdx];
            // If same mood is already active, just update scene tracking — don't restart
            if (newMood === mxMood) {
                mxCurScene = key; mxSceneMoods = moods; mxSceneMoodIdx = randomIdx;
                return;
            }
            mxCurScene = key; mxSceneMoods = moods; mxSceneMoodIdx = randomIdx;
            if (mxCur && !mxPaused) {
                mxSwitch(newMood, mxRandIdx(newMood), true);
            } else {
                mxMood = newMood; mxSongIdx = mxRandIdx(newMood);
                mxUpdateActive();
                mxSetStatus(mxPaused
                    ? ('\u23f8 ' + (mxTrack || newMood))
                    : ('ready \u2014 ' + newMood)
                );
            }
        }
        window.mxOnSceneChange = mxOnSceneChange;
        window.mxBuildGrid    = mxBuildGrid;

        var mxWasPausedByYt = false;
        window.mxPauseForYt = function() {
            if (mxCur && !mxPaused) {
                mxWasPausedByYt = true;
                mxClearTimer();
                mxCur.audio.pause();
                mxPaused = true;
                ppBtn.innerHTML = '&#9654;';
                ppBtn.classList.add('lp-mx-paused');
                mxSetStatus('\u23f8 ' + mxTrack);
            } else {
                mxWasPausedByYt = false;
            }
        };
        window.mxResumeForYt = function() {
            if (!mxWasPausedByYt) return;
            mxWasPausedByYt = false;
            if (!mxCur || !mxPaused) return;
            mxEnsure().then(function() {
                mxCur.audio.play().then(function() {
                    mxPaused = false;
                    ppBtn.innerHTML = '&#x23F8;';
                    ppBtn.classList.remove('lp-mx-paused');
                    mxSchedule(mxMood, mxTrack, mxSongIdx);
                });
            });
        };

        // Unlock listener registered SYNCHRONOUSLY before the async play attempt,
        // so it's always in place regardless of timing.
        // Fires on first click anywhere → resumes AudioContext and starts music.
        document.addEventListener('click', function mxUnlockOnce(e) {
            // ppBtn's own handler calls mxTogglePlay; let it do so
            if (e.target === ppBtn) return;
            if (!mxCur) mxTogglePlay();
        }, { once: true, capture: true });

        // Auto-start on page load — random mood and random starting song.
        (function () {
            var moodKeys = Object.keys(MX_TRACKS);
            if (!moodKeys.length) return;
            var firstMood = moodKeys[Math.floor(Math.random() * moodKeys.length)];
            mxSwitch(firstMood, mxRandIdx(firstMood));
        })();
    })();

    // ── Scene → Music: wrap toggleSceneOverlay ───────────────────────────
    (function () {
        function doWrap() {
            var orig = window.toggleSceneOverlay;
            if (typeof orig !== 'function') return;
            window.toggleSceneOverlay = function (key) {
                // For SC2 scenes, the overlay is activated asynchronously (transition video),
                // so capture the pre-toggle state to know if we're turning ON.
                var sc2WasOn = false;
                if (key === 'sc2' || key === 'sc2-quick') {
                    var sc2ActiveBtnPre = document.getElementById('scene-btn-sc2');
                    sc2WasOn = !!(sc2ActiveBtnPre && sc2ActiveBtnPre.classList.contains('active'));
                }

                orig.apply(this, arguments);

                if (typeof window.mxOnSceneChange !== 'function') return;

                if (key === 'sc2' || key === 'sc2-quick') {
                    if (!sc2WasOn) {
                        /* SC2 turning ON → battle moods for the specific button pressed */
                        window.mxOnSceneChange(key);
                    } else {
                        /* SC2 turning OFF → relaxed moods (sc2-quick-off not in map = no change) */
                        window.mxOnSceneChange(key === 'sc2' ? 'sc2-off' : 'sc2-quick-off');
                    }
                    return;
                }
                // For all other scenes, check button active class (set synchronously)
                var btn = document.getElementById('scene-btn-' + key);
                if (btn && btn.classList.contains('active')) {
                    window.mxOnSceneChange(key);
                }
            };
        }
        // Run after DOM+scripts fully ready
        if (document.readyState === 'complete') {
            doWrap();
        } else {
            window.addEventListener('load', doWrap);
        }
    })();
    </script>

</body>

</html>


