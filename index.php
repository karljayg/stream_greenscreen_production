<?php
require_once __DIR__ . '/asset_version.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
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
        #scoreboard-content.scoreboard-content-wrap {
            position: absolute; left: 50%; top: 100px; bottom: 80px;
            width: calc(100% - 200px); max-width: calc(100vw - 200px);
            transform: translateX(-50%);
            display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
            overflow: auto; box-sizing: border-box;
        }
        .scoreboard-panel { padding: 0.35rem 0.4rem; color: #fff; font-family: 'Exo 2', sans-serif; box-sizing: border-box; width: 100%; max-width: 100%; min-width: 0; }
        .scoreboard-panel-inner { max-width: 100%; min-width: 0; margin: 0 auto; }
        .scoreboard-header { display: flex; justify-content: center; align-items: center; gap: 0.6rem; margin-bottom: 0.45rem; padding-bottom: 0.4rem; border-bottom: 2px solid rgba(108, 92, 231, 0.3); flex-wrap: nowrap; }
        .scoreboard-team-block { min-width: 0; flex: 1; }
        .scoreboard-team-a { text-align: center; }
        .scoreboard-team-b { text-align: center; }
        .scoreboard-team-name { display: block; font-size: 2.525rem; font-weight: 600; line-height: 1.2; color: #e0e0e0; font-family: 'Exo 2', sans-serif; }
        .scoreboard-team-a .scoreboard-team-name { color: #e0e0e0; }
        .scoreboard-team-b .scoreboard-team-name { color: #e0e0e0; }
        .scoreboard-vs-block { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 0.4rem; flex-shrink: 0; }
        .scoreboard-score-main { font-size: 2.625rem; font-weight: 700; color: #FFFF99; font-family: 'Rajdhani', sans-serif; }
        .scoreboard-score-label { font-size: 0.825rem; color: #00b894; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 0.08rem; font-weight: 600; font-family: 'Rajdhani', sans-serif; }
        .scoreboard-table-wrap { background: rgba(0, 0, 0, 0.25); border-radius: 8px; overflow: hidden; border: 1px solid rgba(108, 92, 231, 0.2); margin-top: 0.35rem; width: 100%; max-width: 100%; min-width: 0; }
        .scoreboard-table { width: 100%; max-width: 100%; min-width: 0; table-layout: auto; border-collapse: collapse; font-size: 1.045rem; font-family: 'Exo 2', sans-serif; }
        /* Narrow columns shrink to content; team/map columns autosize (no width = fit content) */
        .scoreboard-table col:nth-child(1), .scoreboard-table col:nth-child(6), .scoreboard-table col:nth-child(10) { width: 0.1%; }
        .scoreboard-table col:nth-child(2) { width: 1%; min-width: 2.8em; }
        .scoreboard-table col:nth-child(5), .scoreboard-table col:nth-child(9) { width: 1%; min-width: 2em; }
        .scoreboard-table thead { border-bottom: none; }
        .scoreboard-table th { padding: 0.28rem 0.35rem; text-align: center; color: #fff; font-weight: 600; font-size: 1.005rem; background: rgba(108, 92, 231, 0.25); border-bottom: 1px solid rgba(255,255,255,0.08); white-space: nowrap; }
        .scoreboard-table th.scoreboard-th-empty { padding-left: 4px; padding-right: 4px; background: rgba(108, 92, 231, 0.15); }
        .scoreboard-table th.scoreboard-th-map { color: #a29bfe; font-weight: 500; font-size: 0.82rem; }
        .scoreboard-table th.scoreboard-th-group { text-align: center; }
        .scoreboard-table td { padding: 0.25rem 0.35rem; border-bottom: 1px solid rgba(255,255,255,0.08); color: #e0e0e0; text-align: center; font-size: 1.025rem; }
        .scoreboard-table tr:last-child td { border-bottom: none; }
        .scoreboard-table tr:nth-child(even) td { background: rgba(255,255,255,0.03); }
        .scoreboard-table td.scoreboard-empty-cell { padding-left: 4px; padding-right: 4px; overflow: hidden; border-color: rgba(255,255,255,0.06); }
        .scoreboard-table td.scoreboard-type { font-size: 1.025rem; }
        .scoreboard-table td.scoreboard-num { color: #FFD700; font-size: 1.1rem; font-weight: 700; text-align: center; white-space: nowrap; }
        .scoreboard-table tbody tr:hover td { background: rgba(108, 92, 231, 0.12); }
        .scoreboard-type { color: #00b894; font-weight: 600; font-family: 'Rajdhani', sans-serif; text-align: center; white-space: nowrap; }
        .scoreboard-num { color: #FFD700; font-weight: 700; text-align: center; white-space: nowrap; }
        .scoreboard-table td.scoreboard-map { color: #a29bfe; font-size: 0.82rem; text-align: center; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .scoreboard-map { color: #a29bfe; text-align: center; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .scoreboard-cell { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: center; }
        .scoreboard-table td.scoreboard-cell-team { padding-left: 0.5rem; padding-right: 0.5rem; white-space: normal; overflow-wrap: break-word; text-overflow: clip; }
        .scoreboard-table td.scoreboard-cell-team-a { text-align: left; padding-right: 0.85rem; }
        .scoreboard-table td.scoreboard-cell-team-b { text-align: left; padding-left: 0.85rem; padding-right: 0.5rem; }
        .scoreboard-slot { font-size: 0.75em; color: #a29bfe; font-weight: 600; }
        .scoreboard-race-icon { width: 1em; height: 1em; vertical-align: middle; }
        .scoreboard-empty { color: #a29bfe; text-align: center; padding: 1rem; font-size: 1.075rem; }
    </style>
</head>

<body>
    <div class="container" data-layer-id="container">
        <div class="left-column">
            <button class="collapsible-btn" id="btn-settings" onclick="toggleSettings(this)">Show Settings</button>
            <div class="collapsible-content" id="settings-section" style="display: none;">
                <h3 class="settings-group-heading">Onscreen Messages</h3>
                <button class="collapsible-btn" id="btn-status" onclick="toggleStatus(this)">Show Status Message</button>
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
                    <h2>Scoreboard CSV</h2>
                    <p class="layer-order-hint">Paste CSV below to overwrite <code>2026/scoreboard.csv</code>. The scoreboard panel will use this file when you click the Scoreboard scene button.</p>
                    <textarea id="scoreboard-csv-input" rows="12" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder="Paste CSV data here..."></textarea>
                    <button type="button" id="scoreboard-csv-save-btn" style="margin-top: 6px;">Overwrite scoreboard.csv</button>
                </div>
                <button class="collapsible-btn" id="btn-player-intros" onclick="togglePlayerIntros(this)">Show Player Chroma</button>
                <div class="collapsible-content" id="player-intros-settings-section" style="display: none;">
                    <h2>Player Intros</h2>
                    <label><input type="checkbox" id="chroma-key-cb" checked> Chroma key (green transparent)</label>
                    <p class="layer-order-hint" style="margin-top: 8px;">FSL rankings (season/all-time W–L under player name):</p>
                    <button type="button" id="rankings-refresh-btn" style="margin-top: 4px;">Refresh rankings</button>
                    <span id="rankings-refresh-status" style="margin-left: 6px; font-size: 0.9rem;"></span>
                </div>
                <button type="button" id="btn-reload-vdo" class="collapsible-btn" onclick="reloadVdo()">Reload VDO</button>
                <button class="collapsible-btn" id="btn-player-ratings" onclick="togglePlayerRatings(this)">Show Spider Ratings</button>
                <div class="collapsible-content" id="player-ratings-section" style="display: none;">
                    <h2>Spider Ratings</h2>
                    <p>External Spider Chart</p>
                    <input id="player-input" type="text" placeholder="Enter player name" value="littlereaper">
                    <input id="division-input" type="text" placeholder="Division" value="A" style="width: 5ch; margin-left: 5px;">
                    <button id="chart-toggle-btn" onclick="toggleExternalChart()">Load External Chart</button>
                    <div id="error-message" style="color: red;"></div>
                </div>

                <h3 class="settings-group-heading">Layouts and Layers</h3>
                <button class="collapsible-btn" id="btn-volume" onclick="toggleVolume(this)">Show Volume</button>
                <div class="collapsible-content" id="volume-section" style="display: none;">
                    <h2>Volume</h2>
                    <div>
                        <label for="volume-slider">Volume: </label>
                        <input type="range" id="volume-slider" min="0" max="100" value="50">
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-layer-order" onclick="toggleLayerOrder(this)">Show Layer order</button>
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
                <button class="collapsible-btn" id="btn-yt-video" onclick="toggleYtVideo(this)">Show YT Video</button>
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
                <button class="collapsible-btn" id="btn-logos-settings" onclick="toggleLogosSettings(this)">Show Positioning settings</button>
                <div class="collapsible-content" id="logos-settings-section" style="display: none;">
                    <h2>Positional settings</h2>
                    <div class="logos-checkboxes">
                        <label><input type="checkbox" id="logo-s10-cb" data-logo="s10"> FSL S10 logo</label><br>
                        <label><input type="checkbox" id="logo-fsl-small-cb" data-logo="fsl-small"> FSL SC2 logo small</label><br>
                        <label><input type="checkbox" id="logo-sc2-cb" data-logo="sc2"> SC2</label>
                    </div>
                    <div class="logos-edit-actions" style="margin-top: 10px;">
                        <p class="layer-order-hint">Edit and Move: drag/resize logos, SC2 panel, and the big VDO full panel. Save stores all positions.</p>
                        <button type="button" id="logos-edit-save-btn" onclick="toggleLogosEditMode()">Edit and Move</button>
                        <button type="button" id="logos-reset-btn" onclick="resetLogosPositions()">Reset</button>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-overlays" onclick="toggleOverlays(this)">Show Overlays</button>
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
                <button class="collapsible-btn" id="btn-save-load" onclick="toggleSaveLoad(this)">Show Save/Load setup</button>
                <div class="collapsible-content" id="save-load-section" style="display: none;">
                    <h2 class="layer-order-heading">Import / Export all settings</h2>
                    <p class="layer-order-hint">Export saves layer order, volume, Status, Player Ratings, Logos (checkboxes + positions), VDO full and SC2 panel positions, Scenes visibility, and Player Intros names. <strong>Save to server</strong> stores the current setup so anyone opening this link gets the same settings. You can still <strong>Export all</strong> / <strong>Import all</strong> to share via file.</p>
                    <div class="layer-order-actions">
                        <button type="button" id="layer-export-btn">Export all</button>
                        <button type="button" id="layer-save-server-btn">Save to server</button>
                        <input type="file" id="layer-import-file" accept=".json" style="display: none;">
                        <button type="button" id="layer-import-btn">Import all</button>
                    </div>
                </div>
            </div>

            <h2>Scenes</h2>
            <div class="scenes-buttons">
                <button type="button" id="scene-btn-sc2" class="scene-btn-major" onclick="toggleSceneOverlay('sc2')">SC2</button>
                <button type="button" id="scene-btn-schedule" onclick="toggleSceneOverlay('schedule')">Schedule</button>
                <button type="button" id="scene-btn-scoreboard" onclick="toggleSceneOverlay('scoreboard')">Scoreboard</button>
                <button type="button" id="scene-btn-ash" onclick="toggleSceneOverlay('ash')">ASH</button>
                <button type="button" id="scene-btn-pog" onclick="toggleSceneOverlay('pog')">POG</button>
                <button type="button" id="scene-btn-ptb" onclick="toggleSceneOverlay('ptb')">PTB</button>
                <button type="button" id="scene-btn-st" onclick="toggleSceneOverlay('st')">ST</button>
                <span class="scenes-shared-group" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                    <button type="button" id="scene-btn-shared-window" onclick="toggleSceneOverlay('shared-window')">Shared Window</button>
                    <button type="button" id="scene-btn-full-shared" onclick="toggleSceneOverlay('full-shared')" style="display: none;">Shared (Partial)</button>
                    <button type="button" id="scene-btn-yt" onclick="toggleSceneOverlay('yt')" style="display: none;">YT</button>
                </span>
            </div>
            <div id="scene-video-error" class="scene-video-error" style="display: none; font-size: 0.8rem; color: #c00; margin-top: 4px;"></div>

            <h2>Player Intros, Memes & Effects</h2>

            <button class="collapsible-btn" id="btn-forms" onclick="toggleForms(this)">Show More</button>

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
                    <iframe data-src="https://vdo.ninja/?scene=1&room=KJNinjaRoom123&password=FSL&sl&cover&autostart" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen"></iframe>
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
        function toggleSettings(btn) {
            var el = document.getElementById("settings-section");
            if (el.style.display === "none" || !el.style.display) {
                el.style.display = "block";
            } else {
                el.style.display = "none";
            }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Settings" : "Show Settings";
        }
        function toggleVolume(btn) {
            var el = document.getElementById("volume-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Volume" : "Show Volume";
        }
        function toggleScoreboardSettings(btn) {
            var el = document.getElementById("scoreboard-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Scoreboard" : "Scoreboard";
        }
        function togglePlayerIntros(btn) {
            var el = document.getElementById("player-intros-settings-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Player Chroma" : "Show Player Chroma";
        }
        function toggleLayerOrder(btn) {
            var el = document.getElementById("layer-order-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Layer order" : "Show Layer order";
        }
        function toggleSaveLoad(btn) {
            var el = document.getElementById("save-load-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Save/Load setup" : "Show Save/Load setup";
        }
        function toggleOverlays(btn) {
            var el = document.getElementById("overlays-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Overlays" : "Show Overlays";
        }
        function toggleYtVideo(btn) {
            var el = document.getElementById("yt-video-section");
            if (el.style.display === "none" || !el.style.display) { el.style.display = "block"; } else { el.style.display = "none"; }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide YT Video" : "Show YT Video";
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
                logos: { s10: false, fslSmall: false, sc2: false, positions: {}, sc2Panel: null, vdoFullPanel: { left: 50, top: 100, width: 1180, height: 570 } },
                scenes: { bg: false, vdoFull: false, logos: false, sc2: false },
                playerIntros: ["DarkMenace", "LittleReaper", "PulledTheBoys", "HyperTurtle", "Harouz", "InfiniteCyclists", "Random Music", "FSL intro", "GG", "Match GG"],
                chromaKey: true,
                ytCrop: { top: 150, left: 10, right: 20, bottom: 100 }
            };

            function exportAllSettings() {
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
                    logos: { s10: !!document.getElementById("logo-s10-cb") && document.getElementById("logo-s10-cb").checked, fslSmall: !!document.getElementById("logo-fsl-small-cb") && document.getElementById("logo-fsl-small-cb").checked, sc2: !!document.getElementById("logo-sc2-cb") && document.getElementById("logo-sc2-cb").checked, positions: logoPositions, sc2Panel: sc2PanelPos, vdoFullPanel: vdoFullPanelPos },
                    scenes: { bg: sceneBg && sceneBg.style.display === "block", vdoFull: sceneVdo && sceneVdo.style.display === "block", logos: overlay && overlay.style.display === "block", sc2: sc2OverlayExport && sc2OverlayExport.style.display === "block" },
                    playerIntros: intros,
                    chromaKey: !!document.getElementById("chroma-key-cb") && document.getElementById("chroma-key-cb").checked,
                    ytCrop: typeof getYtCrop === "function" ? getYtCrop() : { top: 150, left: 10, right: 20, bottom: 100 }
                };
                return out;
            }

            function importAllSettings(parsed) {
                var def = DEFAULT_SETTINGS;
                if (!parsed || typeof parsed !== "object") return;
                var set = function(id, val) { var el = document.getElementById(id); if (el && val !== undefined) el.value = String(val); };
                var setCheck = function(id, val) { var el = document.getElementById(id); if (el) el.checked = !!val; };
                if (parsed.volume !== undefined) { var vs = document.getElementById("volume-slider"); if (vs) vs.value = Math.max(0, Math.min(100, parseInt(parsed.volume, 10) || 50)); }
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
                    var o4 = document.getElementById("sc2-overlay"); var b4 = document.getElementById("scene-btn-sc2");
                    if (o4 && b4) {
                        o4.style.display = sc.sc2 ? "block" : "none";
                        b4.classList.toggle("active", !!sc.sc2);
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

            var rankingsRefreshBtn = document.getElementById("rankings-refresh-btn");
            var rankingsRefreshStatus = document.getElementById("rankings-refresh-status");
            if (rankingsRefreshBtn && rankingsRefreshStatus) {
                rankingsRefreshBtn.addEventListener("click", function() {
                    rankingsRefreshStatus.textContent = "…";
                    rankingsRefreshBtn.disabled = true;
                    fetch("rankings.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ refresh: true }) }).then(function(r) { return r.json(); }).then(function(result) {
                        if (result && result.ok) {
                            if (typeof window.reloadRankingsCache === "function") window.reloadRankingsCache();
                            rankingsRefreshStatus.textContent = "Updated.";
                        } else {
                            rankingsRefreshStatus.textContent = result && result.error ? result.error : "Save failed.";
                        }
                    }).catch(function(e) {
                        rankingsRefreshStatus.textContent = "Error: " + (e.message || "fetch failed");
                    }).finally(function() {
                        rankingsRefreshBtn.disabled = false;
                        setTimeout(function() { rankingsRefreshStatus.textContent = ""; }, 4000);
                    });
                });
            }

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
            if (btn) btn.textContent = (content.style.display === "block") ? "Hide More" : "Show More";
        }

        function toggleLogosSettings(btn) {
            var el = document.getElementById("logos-settings-section");
            if (el.style.display === "none" || !el.style.display) {
                el.style.display = "block";
            } else {
                el.style.display = "none";
            }
            if (btn) btn.textContent = (el.style.display === "block") ? "Hide Logos settings" : "Show Logos settings";
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
            overlay.className = "logos-overlay";

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
            var sc2Cb = document.getElementById("logo-sc2-cb");
            var sc2Overlay = document.getElementById("sc2-overlay");
            var sc2Panel = document.getElementById("sc2-panel-wrap");
            if (sc2Cb && sc2Cb.checked && sc2Overlay && sc2Panel) {
                sc2Overlay.style.display = "block";
                sc2Overlay.style.pointerEvents = "auto";
                sc2Overlay.style.zIndex = "99999";
                sc2Overlay.classList.add("logos-edit-mode");
                var saved = getSavedSc2Panel();
                var overlayRect = sc2Overlay.getBoundingClientRect();
                var pos = saved || getDefaultSc2PanelPosition(overlayRect);
                applyPositionToSc2Panel(sc2Panel, pos);
                sc2Panel.style.display = "block";
                if (!$(sc2Panel).data("ui-draggable")) {
                    $(sc2Panel).draggable({ containment: "#sc2-overlay", scroll: false, cursor: "move" });
                    $(sc2Panel).resizable({ containment: "#sc2-overlay", handles: "all" });
                }
            }
            var vdoFullOverlay = document.getElementById("scene-overlay-vdo-full");
            var vdoFullPanel = document.getElementById("vdo-full-panel-wrap");
            if (vdoFullOverlay && vdoFullPanel && vdoFullOverlay.style.display === "block") {
                var vdoSaved = getSavedVdoFullPanel();
                var vdoOverlayRect = vdoFullOverlay.getBoundingClientRect();
                var vdoPos = vdoSaved || getDefaultVdoFullPanelPosition(vdoOverlayRect);
                applyPositionToVdoFullPanel(vdoFullPanel, vdoPos);
                if (!$(vdoFullPanel).data("ui-draggable")) {
                    $(vdoFullPanel).draggable({ containment: "#scene-overlay-vdo-full", scroll: false, cursor: "move" });
                    $(vdoFullPanel).resizable({ containment: "#scene-overlay-vdo-full", handles: "all" });
                }
            }
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
                    if (!sceneSc2Btn || !sceneSc2Btn.classList.contains("active")) sc2OverlayEl.style.display = "none";
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
                if (vdoFullOverlayEl) {
                    vdoFullOverlayEl.style.pointerEvents = "none";
                    vdoFullOverlayEl.style.zIndex = "";
                    vdoFullOverlayEl.classList.remove("logos-edit-mode");
                    var vdoFullBtn = document.getElementById("scene-btn-vdo-full");
                    if (!vdoFullBtn || !vdoFullBtn.classList.contains("active")) vdoFullOverlayEl.style.display = "none";
                }
                if (vdoFullPanelEl) {
                    var vp = getSavedVdoFullPanel();
                    var vdoRect = vdoFullOverlayEl ? vdoFullOverlayEl.getBoundingClientRect() : { width: 1280, height: 720 };
                    applyPositionToVdoFullPanel(vdoFullPanelEl, vp || getDefaultVdoFullPanelPosition(vdoRect));
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
            overlay.style.zIndex = "100001";
            overlay.classList.add("logos-edit-mode");
            logosEditMode = true;
            btn.textContent = "Save";
            var vdoFullOverlayEl = document.getElementById("scene-overlay-vdo-full");
            var vdoFullPanelEl = document.getElementById("vdo-full-panel-wrap");
            if (vdoFullOverlayEl && vdoFullPanelEl) {
                vdoFullOverlayEl.style.display = "block";
                vdoFullOverlayEl.style.pointerEvents = "auto";
                vdoFullOverlayEl.style.zIndex = "100000";
                vdoFullOverlayEl.classList.add("logos-edit-mode");
                var pos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(vdoFullOverlayEl.getBoundingClientRect());
                applyPositionToVdoFullPanel(vdoFullPanelEl, pos);
                var vdoIframe = vdoFullPanelEl.querySelector("iframe");
                ensureVdoIframeLoaded(vdoIframe);
            }
            var sc2Cb = document.getElementById("logo-sc2-cb");
            if (sc2Cb && sc2Cb.checked) {
                var sc2OverlayEl = document.getElementById("sc2-overlay");
                if (sc2OverlayEl) {
                    sc2OverlayEl.style.display = "block";
                    sc2OverlayEl.style.pointerEvents = "auto";
                    sc2OverlayEl.style.zIndex = "99999";
                    sc2OverlayEl.classList.add("logos-edit-mode");
                    var sc2PanelEdit = document.getElementById("sc2-panel-wrap");
                    if (sc2PanelEdit) {
                        sc2PanelEdit.style.display = "block";
                        var sc2PosEdit = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2OverlayEl.getBoundingClientRect());
                        if (sc2PosEdit) applyPositionToSc2Panel(sc2PanelEdit, sc2PosEdit);
                        var sc2IframeEdit = sc2PanelEdit.querySelector("iframe");
                        ensureVdoIframeLoaded(sc2IframeEdit);
                    }
                }
            }
            updateLogosOverlay();
        }

        (function wireLogosSettings() {
            document.getElementById("logo-s10-cb").addEventListener("change", updateLogosOverlay);
            document.getElementById("logo-fsl-small-cb").addEventListener("change", updateLogosOverlay);
            document.getElementById("logo-sc2-cb").addEventListener("change", function() { updateSc2Panel(); });
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

            /** Force reload of VDO Ninja iframes (SC2 and VDO full). Use when camera feed is stuck. */
            function reloadVdo() {
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
                    sc2Btn.classList.remove('active');
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
                var sc2Btn = document.getElementById('scene-btn-sc2');
                if (vdoFullBtn && vdoFullOverlay && (vdoFullOverlay.style.display === 'none' || !vdoFullOverlay.style.display)) vdoFullBtn.classList.remove('active');
                if (sc2Btn && sc2Overlay && (sc2Overlay.style.display === 'none' || !sc2Overlay.style.display)) sc2Btn.classList.remove('active');
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
                    var url = (file.indexOf('.html') !== -1) ? ('2026/' + file) : ('2026/video_player.php?v=' + encodeURIComponent(file) + '&_t=' + Date.now());
                    if (useFront && file.indexOf('.html') === -1) url += '&front=true';
                    iframe.src = url;
                    overlay.style.zIndex = useFront ? '99999' : getVideoOverlayDefaultZIndex();
                    overlay.style.display = 'block';
                    btn.classList.add('active');
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
                    VIDEO_OVERLAY_KEYS.forEach(function(k) { var b = document.getElementById('scene-btn-' + k); if (b) b.classList.remove('active'); });
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
            } else if (sceneId === 'sc2') {
                const bgOverlay = document.getElementById('scene-overlay-all-vdo');
                const vdoFullOverlay = document.getElementById('scene-overlay-vdo-full');
                const vdoFullBtn = document.getElementById('scene-btn-vdo-full');
                const sc2Overlay = document.getElementById('sc2-overlay');
                const sc2Panel = document.getElementById('sc2-panel-wrap');
                const btn = document.getElementById('scene-btn-sc2');
                if (sc2Overlay.style.display === 'none' || !sc2Overlay.style.display) {
                    /* SC2 on: play transition video then show small VDO panel */
                    playTransitionVideo({
                        videoSrc: '2026/2026_FSL_logo_reveal_GS_fast.mp4',
                        fadeInMs: 500,
                        fadeOutMs: 500,
                        onComplete: function() {
                            if (bgOverlay) bgOverlay.style.display = 'none';
                            if (vdoFullOverlay) vdoFullOverlay.style.display = 'none';
                            if (vdoFullBtn) vdoFullBtn.classList.remove('active');
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
                            sc2Overlay.style.display = 'block';
                            btn.classList.add('active');
                            if (sc2Panel) {
                                sc2Panel.style.display = 'block';
                                var pos = getSavedSc2Panel() || getDefaultSc2PanelPosition(sc2Overlay.getBoundingClientRect());
                                applyPositionToSc2Panel(sc2Panel, pos);
                                var sc2Iframe = sc2Panel.querySelector('iframe');
                                ensureVdoIframeLoaded(sc2Iframe);
                            }
                        }
                    });
                } else {
                    /* SC2 off: BG and VDO full reappear, small VDO panel disappears; restore video overlay to BG */
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
                    if (sharedOverlay) sharedOverlay.style.display = 'none';
                    if (sharedBtn) sharedBtn.classList.remove('active');
                    if (fullSharedOverlay) fullSharedOverlay.style.display = 'none';
                    if (fullSharedBtn) fullSharedBtn.classList.remove('active');
                    if (ytOverlay) ytOverlay.style.display = 'none';
                    if (ytBtn) ytBtn.classList.remove('active');
                    if (bgBtn) bgBtn.classList.add('active');
                    if (vdoFullOverlay) vdoFullOverlay.style.display = 'block';
                    if (vdoFullBtn) vdoFullBtn.classList.add('active');
                    var vdoPanel = document.getElementById('vdo-full-panel-wrap');
                    if (vdoPanel) {
                        var pos = getSavedVdoFullPanel() || getDefaultVdoFullPanelPosition(vdoFullOverlay ? vdoFullOverlay.getBoundingClientRect() : { width: 1280, height: 720 });
                        applyPositionToVdoFullPanel(vdoPanel, pos);
                        var vdoIframe = vdoPanel.querySelector('iframe');
                        ensureVdoIframeLoaded(vdoIframe);
                    }
                    sc2Overlay.style.display = 'none';
                    btn.classList.remove('active');
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
                
                // Get positioning from JS variables  
                const pos = window.displayPositions ? window.displayPositions.externalChart : {left: '-20%', top: '20%', scale: '1.2'};
                
                // Create iframe in dedicated overlay div
                chartOverlay.innerHTML = `
                    <div style="
                        position: relative;
                        left: ${pos.left};
                        top: ${pos.top};
                        transform: scale(${pos.scale});
                        transform-origin: top left;
                        width: 100%;
                    ">
                        <iframe 
                            src="${chartUrl}" 
                            width="800" 
                            height="800" 
                            frameborder="0"
                            scrolling="no"
                            style="border: none; overflow: hidden; pointer-events: auto;">
                        </iframe>
                    </div>
                `;
                
                chartOverlay.style.display = 'block';
                chartDisplayed = true;
                chartButton.textContent = "Hide External Chart";
                document.getElementById("error-message").textContent = "";
            } else {
                document.getElementById("error-message").textContent = "Please enter both player name and division";
            }
        }
    </script>

</body>

</html>


