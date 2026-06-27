<?php
$pathPrefix = '';
require_once __DIR__ . '/partials/auth-gate.php'; // session_start, login gate, defines $currentUser
require_once __DIR__ . '/asset_version.php';

// Music config saving is handled by save_music_config.php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/config.local.php';

require_once __DIR__ . '/partials/music-config.php'; // defines $safeUser, $moodSongs, $sceneMoodMap, $musicFiles
require_once __DIR__ . '/partials/production-files-bootstrap.php'; // $streamPfBootstrap, $streamPfIconHref, $streamPfGifHref, $streamMxMusicPath, $streamSceneAssetsBase

$streamLogoS10Src = ($streamSceneAssetsBase !== '') ? ($streamSceneAssetsBase . '2026/FSL_s10_logo.png') : '2026/FSL_s10_logo.png';
$streamLogoSmallSrc = ($streamSceneAssetsBase !== '') ? ($streamSceneAssetsBase . '2026/fsl_sc2_logo_small.png') : '2026/fsl_sc2_logo_small.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Stream Production Tool</title>
    <link rel="icon" href="<?php echo htmlspecialchars($streamPfIconHref, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($streamPfIconHref, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>" type="image/x-icon">
    <link rel="stylesheet" href="styles/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="styles/main.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/smoothness/jquery-ui.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@500;600;700;800;900&family=Teko:wght@700&family=Orbitron:wght@700;800;900&family=Exo+2:wght@400;600;700&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>
    <script src="js/popper.min.js?v=<?php echo $v; ?>"></script>
    <script src="js/chart.js?v=<?php echo $v; ?>"></script>
    <link rel="stylesheet" href="styles/app.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="styles/stream-elements.css?v=<?php echo $v; ?>">
</head>

<body>
    <div class="container" data-layer-id="container">
        <div class="left-column">
            <!-- User bar -->
            <div id="user-bar">
                <button id="user-bar-name" title="Click to change password"><?php echo $currentUser; ?></button>
                <button class="collapsible-btn" id="btn-settings" onclick="toggleSection('settings-section', this)">&#9881; Settings</button>
                <button id="user-bar-logout">Logout</button>
            </div>
            <div class="collapsible-content" id="settings-section" style="display: none;">
                <h3 class="settings-group-heading">Production files</h3>
                <button type="button" class="collapsible-btn" id="btn-production-files" onclick="toggleSection('production-files-section', this)">Media file location</button>
                <div class="collapsible-content" id="production-files-section" style="display: none;">
                    <p class="layer-order-hint">Intro videos, GIFs, and team audio live under <code>production_files/</code>. Use <strong>Local</strong> when this app and files are on the same host, or <strong>Remote</strong> when files are served from a URL with the same folder layout (<code>audio/</code>, <code>video/</code>, <code>images/</code>).</p>
                    <div style="margin-bottom:8px;">
                        <label style="display:block;margin-bottom:4px;"><input type="radio" name="production-files-mode" value="local" checked> Local (relative <code>production_files/</code> on this site)</label>
                        <label style="display:block;"><input type="radio" name="production-files-mode" value="remote"> Remote (base URL for the <code>production_files</code> folder)</label>
                    </div>
                    <label for="production-files-remote-url" style="font-size:12px;color:#aaa;">Remote base URL</label>
                    <input type="url" id="production-files-remote-url" placeholder="https://psistorm.com/stream_production/production_files/" autocomplete="off" style="width:100%;max-width:40rem;display:block;margin:4px 0 8px;padding:4px 6px;font-size:12px;background:#111;color:#eee;border:1px solid #444;border-radius:3px;">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <button type="button" id="production-files-apply-btn" style="font-size:12px;padding:4px 10px;cursor:pointer;">Apply to page</button>
                        <span style="font-size:12px;color:#888;" id="production-files-status"></span>
                    </div>
                </div>
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
                <button class="collapsible-btn" id="btn-scoreboard-settings" onclick="toggleSection('scoreboard-settings-section', this)">Scoreboard</button>
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
                        <div style="margin-top:8px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            <button type="button" id="scoreboard-score-save-btn" onclick="scoreboardEditorSave()">Save Scores</button>
                            <button type="button" id="scoreboard-detail-btn" onclick="sbEditorOpenWindow()">Edit Details…</button>
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
                        <label style="grid-column:1 / -1; font-size:12px; color:#ccc; display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" id="csb-show-summary">
                            Show top summary and labels
                        </label>
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
                <button class="collapsible-btn" id="btn-player-scoreboard-settings" onclick="togglePlayerScoreboardSettings(this)">Player Scoreboard</button>
                <div class="collapsible-content" id="player-scoreboard-settings-section" style="display: none;">
                    <h2>Player Scoreboard</h2>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:#ccc;">
                            <input type="checkbox" id="psb-show"> Show on overlay
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:#ccc;">
                            Best of
                            <input type="number" id="psb-best-of" min="1" max="99" value="1" style="width:52px; text-align:center; padding:3px 4px;">
                        </label>
                        <button type="button" id="psb-swap-btn" onclick="psbSwapPlayers()" title="Swap top and bottom players">&#8645; Swap A/B</button>
                    </div>
                    <div class="psb-player-edit" data-idx="0" style="border:1px solid #333; border-radius:5px; padding:8px; margin-bottom:8px;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#8f8; margin-bottom:6px;">Player A (top)</div>
                        <div style="display:grid; grid-template-columns:auto 1fr; gap:5px 8px; align-items:center;">
                            <label style="font-size:12px; color:#aaa;">Name:</label>
                            <input type="text" class="psb-name" placeholder="Player name" style="background:#1a1a1a; color:#eee; border:1px solid #444; border-radius:3px; padding:3px 6px; font-size:12px;">
                            <label style="font-size:12px; color:#aaa;">Score:</label>
                            <input type="number" class="psb-score" min="0" max="99" value="0" style="width:60px; text-align:center; padding:3px 4px;">
                            <label style="font-size:12px; color:#aaa;">Color:</label>
                            <select class="psb-color" style="background:#fff; color:#111; border:1px solid #888; padding:3px;"></select>
                            <label style="font-size:12px; color:#aaa;">Team:</label>
                            <select class="psb-team" style="background:#fff; color:#111; border:1px solid #888; padding:3px;"></select>
                            <label style="font-size:12px; color:#aaa;">Race:</label>
                            <select class="psb-race" style="background:#fff; color:#111; border:1px solid #888; padding:3px;">
                                <option value="">Auto (from rankings)</option>
                                <option value="Z">Zerg</option>
                                <option value="T">Terran</option>
                                <option value="P">Protoss</option>
                                <option value="R">Random</option>
                            </select>
                        </div>
                    </div>
                    <div class="psb-player-edit" data-idx="1" style="border:1px solid #333; border-radius:5px; padding:8px; margin-bottom:8px;">
                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#88f; margin-bottom:6px;">Player B (bottom)</div>
                        <div style="display:grid; grid-template-columns:auto 1fr; gap:5px 8px; align-items:center;">
                            <label style="font-size:12px; color:#aaa;">Name:</label>
                            <input type="text" class="psb-name" placeholder="Player name" style="background:#1a1a1a; color:#eee; border:1px solid #444; border-radius:3px; padding:3px 6px; font-size:12px;">
                            <label style="font-size:12px; color:#aaa;">Score:</label>
                            <input type="number" class="psb-score" min="0" max="99" value="0" style="width:60px; text-align:center; padding:3px 4px;">
                            <label style="font-size:12px; color:#aaa;">Color:</label>
                            <select class="psb-color" style="background:#fff; color:#111; border:1px solid #888; padding:3px;"></select>
                            <label style="font-size:12px; color:#aaa;">Team:</label>
                            <select class="psb-team" style="background:#fff; color:#111; border:1px solid #888; padding:3px;"></select>
                            <label style="font-size:12px; color:#aaa;">Race:</label>
                            <select class="psb-race" style="background:#fff; color:#111; border:1px solid #888; padding:3px;">
                                <option value="">Auto (from rankings)</option>
                                <option value="Z">Zerg</option>
                                <option value="T">Terran</option>
                                <option value="P">Protoss</option>
                                <option value="R">Random</option>
                            </select>
                        </div>
                    </div>
                    <details style="margin-bottom:8px;">
                        <summary style="cursor:pointer; font-size:11px; color:#888; user-select:none;">Team list (name &rarr; acronym)</summary>
                        <div id="psb-teams-rows" style="margin-top:6px; display:flex; flex-direction:column; gap:4px;"></div>
                        <button type="button" id="psb-team-add-btn" style="margin-top:6px; font-size:11px;">+ Add team</button>
                    </details>
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
                        <button type="button" id="psb-open-window-btn" onclick="psbOpenEditorWindow()" title="Open this editor in a separate window (or right-click the panel on the overlay)">&#8599; Open in window</button>
                        <span class="layer-order-hint" style="margin:0;">Position &amp; size: Settings &rarr; Positioning.</span>
                    </div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button type="button" id="psb-save-btn" onclick="psbSave()">Save Player Scoreboard</button>
                        <span id="psb-save-status" style="font-size:12px; color:#aaa;"></span>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-player-intros" onclick="toggleSection('player-intros-settings-section', this)">Player Chroma</button>
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
                <button class="collapsible-btn" id="btn-volume" onclick="toggleSection('volume-section', this)">Volume</button>
                <div class="collapsible-content" id="volume-section" style="display: none;">
                    <h2>Volume</h2>
                    <div>
                        <label for="volume-slider">Volume: </label>
                        <input type="range" id="volume-slider" min="5" max="100" value="50">
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-music" onclick="toggleSection('music-section', this)">Music</button>
                <div class="collapsible-content" id="music-section" style="display: none;">
                    <h2>Music Mode</h2>
                    <p class="layer-order-hint">Controls how "Random Music" picks its clips.</p>
                    <div style="display: flex; flex-direction: column; gap: 0.35rem; margin-top: 6px;">
                        <label><input type="radio" name="music-mode" value="sequence" checked> Sequence &mdash; plays in order, loops back to start</label>
                        <label><input type="radio" name="music-mode" value="random"> Random</label>
                    </div>
                    <div style="margin-top:10px;">
                        <button type="button" onclick="window.open('music_admin.php', 'mxMusicAdmin', 'width=900,height=920,resizable=yes,scrollbars=yes')" style="font-size:0.82rem;padding:5px 13px;">&#9836; Open Music Admin</button>
                        <p class="layer-order-hint" style="margin-top:5px;">Opens in a separate window so it won't overlay the stream. Visual editor for Scene&nbsp;&rarr;&nbsp;Mood mappings, Mood&nbsp;&rarr;&nbsp;Song assignments, and detailed stage scenes. Upload new audio files, add/remove moods, reorder songs. Use Save to Server to apply.</p>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-sc2-button-effects" onclick="toggleSection('sc2-button-effects-section', this)">SC2 button effects</button>
                <div class="collapsible-content" id="sc2-button-effects-section" style="display: none;">
                    <h2 class="layer-order-heading">SC2 (animated) button effects</h2>
                    <p class="layer-order-hint">When SC2 (animated) turns on, these player intros run automatically (in order). Use exact names from the player list. Leave blank to skip an effect.</p>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 0.35rem 0.5rem; align-items: center; margin-top: 6px;">
                        <label style="font-size: 0.85rem; white-space: nowrap;" for="sc2-effect-1">Effect 1:</label>
                        <input type="text" id="sc2-effect-1" value="Random Music" placeholder="e.g. Random Music">
                        <label style="font-size: 0.85rem; white-space: nowrap;" for="sc2-effect-2">Effect 2:</label>
                        <input type="text" id="sc2-effect-2" value="FSL intro" placeholder="e.g. FSL intro">
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-layer-order" onclick="toggleSection('layer-order-section', this)">Layer Order</button>
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
                <button class="collapsible-btn" id="btn-yt-video" onclick="toggleSection('yt-video-section', this)">YT Video</button>
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
                <button class="collapsible-btn" id="btn-yt-videos-settings" onclick="toggleSection('yt-videos-settings-section', this)">Video Buttons</button>
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
                <button class="collapsible-btn" id="btn-scene-videos-settings" onclick="toggleSection('scene-videos-settings-section', this)">Scene Videos</button>
                <div class="collapsible-content" id="scene-videos-settings-section" style="display: none;">
                    <h2 class="layer-order-heading">Schedule / Bracket media files</h2>
                    <p class="layer-order-hint">Schedule/Bracket list is scoped to <code style="font-size:0.78rem;">production_files/</code>. Team button list uses the selected root (<code style="font-size:0.78rem;">2026/</code> or <code style="font-size:0.78rem;">production_files/</code>). Upload target is chosen below.</p>
                    <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 0.45rem 0.5rem; align-items: center; margin-top: 6px;">
                        <label style="font-size: 0.85rem; white-space: nowrap;">Folder:</label>
                        <select id="scene-video-folder" style="min-width: 0; background:#fff; color:#111; border:1px solid #888;"></select>
                        <button type="button" id="scene-video-refresh-btn">Refresh</button>

                        <label style="font-size: 0.85rem; white-space: nowrap;">Schedule file:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-schedule-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters to search files...">
                            <div id="scene-video-schedule-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">Bracket file:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-bracket-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters to search files...">
                            <div id="scene-video-bracket-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap; grid-column: 1 / -1; margin-top: 0.35rem;">Team buttons (paths under <code style="font-size:0.78rem;">2026/</code> or <code style="font-size:0.78rem;">production_files/</code>)</label>
                        <span style="grid-column: 1 / -1;"></span>
                        <span style="grid-column: 1 / -1;"></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">Media root:</label>
                        <select id="scene-team-media-root" style="min-width: 0; background:#fff; color:#111; border:1px solid #888;">
                            <option value="2026" selected>2026</option>
                            <option value="production_files">production_files</option>
                        </select>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">Folder:</label>
                        <select id="scene-team-video-folder" style="min-width: 0; background:#fff; color:#111; border:1px solid #888;"></select>
                        <button type="button" id="scene-team-video-refresh-btn">Refresh</button>

                        <label style="font-size: 0.85rem; white-space: nowrap;">ASH:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-team-ash-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters...">
                            <div id="scene-video-team-ash-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">POG:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-team-pog-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters...">
                            <div id="scene-video-team-pog-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">PTB:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-team-ptb-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters...">
                            <div id="scene-video-team-ptb-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>

                        <label style="font-size: 0.85rem; white-space: nowrap;">ST:</label>
                        <div style="position: relative;">
                            <input type="text" id="scene-video-team-st-file" style="min-width: 0; width: 100%; background:#fff; color:#111; border:1px solid #888;" placeholder="Type 3+ letters...">
                            <div id="scene-video-team-st-suggestions" style="display:none; position:absolute; left:0; right:0; top:100%; z-index:1001; max-height:220px; overflow-y:auto; background:#fff; color:#111; border:1px solid #888;"></div>
                        </div>
                        <span></span>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.78rem; color: #ccc;">
                        <span style="margin-right: 0.5rem;">Upload goes to:</span>
                        <label style="margin-right: 0.75rem;"><input type="radio" name="scene-upload-dest" id="scene-upload-dest-schedule" value="schedule" checked> Schedule/Bracket folder (above)</label>
                        <label><input type="radio" name="scene-upload-dest" id="scene-upload-dest-team" value="team"> Team folder (above)</label>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 8px;">
                        <input type="file" id="scene-video-upload-file" accept=".mp4,.png,.jpg,.jpeg,.webp,.gif,video/mp4,image/png,image/jpeg,image/webp,image/gif">
                    </div>
                    <div style="margin-top: 6px;">
                        <button type="button" id="scene-video-upload-btn" style="font-weight:700; border:1px solid #777; width:100%;">Upload Media</button>
                    </div>
                    <div style="margin-top: 6px;">
                        <span id="scene-video-status" style="font-size: 0.8rem; min-height: 1em; display:block;"></span>
                    </div>
                    <p class="layer-order-hint" style="margin-top:6px;">After choosing a file, click <strong>Upload Media</strong>.</p>
                </div>
                <button class="collapsible-btn" id="btn-break-settings" onclick="toggleSection('break-settings-section', this)">Break</button>
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
                    <hr style="border-color:#2a2a2a; margin:12px 0;">
                    <div class="psb-edit-actions" style="margin-top: 10px;">
                        <p class="layer-order-hint"><strong>Player Scoreboard</strong> &mdash; drag/resize the player scoreboard panel. Position is saved for everyone. (Content like names &amp; scores is in the Player Scoreboard section.)</p>
                        <button type="button" id="psb-editmove-btn" onclick="psbToggleEditMode()">Edit and Move</button>
                        <button type="button" id="psb-reset-btn" onclick="psbResetPosition()">Reset position</button>
                    </div>
                </div>
                <button class="collapsible-btn" id="btn-overlays" onclick="toggleSection('overlays-section', this)">Overlays</button>
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
                <button class="collapsible-btn" id="btn-save-load" onclick="toggleSection('save-load-section', this)">Save / Load</button>
                <div class="collapsible-content" id="save-load-section" style="display: none;">
                    <h2 class="layer-order-heading">Import / Export all settings</h2>
                    <p class="layer-order-hint">Export saves: layer order, volume, Status, Player Ratings, Logos (checkboxes + positions), VDO full and SC2 panel positions, Scenes visibility, Player Intros names, SC2 button effects, Chroma key, YT crop, Video button labels/URLs, and Break timer/message. <strong>Save to server</strong> stores the current setup so anyone opening this link gets the same settings. You can still <strong>Export all</strong> / <strong>Import all</strong> to share via file.</p>
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

            <?php include __DIR__ . '/partials/music-player-widget.php'; ?>

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
                <div style="display:flex; gap:8px;">
                    <button type="button" id="scene-btn-schedule" onclick="toggleSceneOverlay('schedule')">Schedule</button>
                    <button type="button" id="scene-btn-bracket" onclick="toggleSceneOverlay('bracket')">Bracket</button>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" id="tls-score-toggle-btn" class="tls-score-toggle on" onclick="toggleTeamLeagueScoreBanner()" title="Show/hide the team league score banner (top-right)">T<br>&#9650;</button>
                    <button type="button" id="psb-quick-toggle-btn" class="tls-score-toggle off" onclick="psbToggleShow()" title="Show/hide the player scoreboard (SC2 scene only)">P<br>&#9660;</button>
                    <button type="button" id="scene-btn-scoreboard" onclick="toggleSceneOverlay('scoreboard')">FSL TeamLeague Scoreboard</button>
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
                    <button type="button" id="scene-btn-matchup" onclick="toggleMatchup()">Matchup</button>
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
            <!-- Team League total score banner: top-right, between SEASON 11 and FSL branding. Hidden while the full Scoreboard scene is shown. -->
            <div id="team-league-score-banner" class="team-league-score-banner" style="display: none;"></div>
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
                <!-- Player scoreboard: movable/resizable graphic (players, score, Bo, color, race, team). Draggable like VDO panels. -->
                <div id="player-scoreboard-overlay" data-layer-id="player-scoreboard-overlay" class="psb-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                    <div id="player-scoreboard-panel" class="psb-panel" style="display: none; position: absolute;"></div>
                </div>
                <div id="gif-container" data-layer-id="gif-container">
                    <img id="gif-image" src="<?php echo htmlspecialchars($streamPfGifHref, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>" alt="GIF">
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
                <!-- Matchup Overlay: two-column player stats comparison -->
                <div id="matchup-overlay" data-layer-id="matchup-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 99990; background: rgba(0,0,0,0.92); pointer-events: none; flex-direction: row; justify-content: space-evenly; align-items: center;">
                    <div class="matchup-col matchup-col-a">
                        <div class="external-chart-player-label"></div>
                        <div class="matchup-chart-slot"></div>
                    </div>
                    <div class="matchup-vs">VS</div>
                    <div class="matchup-col matchup-col-b">
                        <div class="external-chart-player-label"></div>
                        <div class="matchup-chart-slot"></div>
                    </div>
                </div>
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
                    <img src="<?php echo htmlspecialchars($streamLogoS10Src, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>" alt="FSL S10" draggable="false">
                </div>
                <div id="logo-fsl-small-wrap" class="logo-wrap" style="display: none; position: absolute;">
                    <img src="<?php echo htmlspecialchars($streamLogoSmallSrc, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>" alt="FSL SC2" draggable="false">
                </div>
            </div>

            <!-- SC2: smaller VDO panel; when SC2 scene is on, BG is hidden and this overlay is shown. Panel is draggable/resizable like logos. -->
            <div id="sc2-overlay" data-layer-id="sc2-overlay" class="sc2-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                <div id="sc2-panel-wrap" class="sc2-panel-wrap logo-wrap" style="display: none; position: absolute; overflow: hidden; background: #000;">
                    <iframe data-src="https://vdo.ninja/?scene=1&room=KJNinjaRoom123&password=FSL&sl&cover" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen"></iframe>
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

    <script>
        // PHP → JS config globals — all PHP-injected values in one place
        window.ASSET_VERSION = "<?php echo $v; ?>";
        window.CURRENT_USER  = <?php echo json_encode($_SESSION['username']); ?>;
        window.SE_TOKEN      = "<?php echo addslashes(SE_JWT); ?>";
        window.MX_TRACKS       = <?php echo json_encode($moodSongs,    JSON_UNESCAPED_SLASHES); ?>;
        window.MX_SCENE_MAP    = <?php echo json_encode($sceneMoodMap, JSON_UNESCAPED_SLASHES); ?>;
        window.MX_SCENE_STAGES = <?php echo json_encode($sceneStages,  JSON_UNESCAPED_SLASHES); ?>;
        window.MX_MUSIC_FILES  = <?php echo json_encode($musicFiles,   JSON_UNESCAPED_SLASHES); ?>;
        window.MX_STATS_URL   = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/save_music_stats.php';
        window.STATUS_URL     = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/save_status.php';
        window.MX_HELP_URL    = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/docs/music-help.php';
        window.MX_MUSIC_PATH  = <?php echo json_encode($streamMxMusicPath, JSON_UNESCAPED_SLASHES); ?>;
        window.MX_MUSIC_PATH_LOCKED = <?php echo $streamMxMusicPathLocked ? 'true' : 'false'; ?>;
        window.__INITIAL_PRODUCTION_FILES__ = <?php echo json_encode($streamPfBootstrap, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_SCENE_ASSETS_BASE = <?php echo json_encode($streamSceneAssetsBase, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_SCENE_ASSETS_BASE_LOCKED = <?php echo $streamSceneAssetsBaseLocked ? 'true' : 'false'; ?>;
        window.STREAM_REMOTE_SITE_ORIGIN = <?php echo json_encode($streamRemoteSiteOrigin, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_FSL_SPIDER_PLAYER_URL = <?php echo json_encode($streamFslSpiderPlayerUrl, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_FSL_SPIDER_MATCHUP_URL = <?php echo json_encode($streamFslSpiderMatchupUrl, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_FSL_IMAGES_BASE = <?php echo json_encode($streamFslImagesBase, JSON_UNESCAPED_SLASHES); ?>;
        window.STREAM_FSL_PROXY_MATCHUP_URL = '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/fsl_proxy_matchup.php';
    </script>
    <script src="js/production-files.js?v=<?php echo $v; ?>"></script>
    <script type="module" src="js/stream_production.js?v=<?php echo $v; ?>"></script>

    <script>
        function toggleH2Section(sectionId, h2el) {
            var section = document.getElementById(sectionId);
            if (!section) return;
            var collapsed = section.style.display === 'none';
            section.style.display = collapsed ? '' : 'none';
            if (h2el) h2el.classList.toggle('collapsed', !collapsed);
        }

        // Generic collapsible panel toggle — replaces 11 near-identical named functions.
        // Called from HTML: onclick="toggleSection('settings-section', this)"
        function toggleSection(sectionId, btn) {
            var el = document.getElementById(sectionId);
            if (!el) return;
            var visible = el.style.display === 'block';
            el.style.display = visible ? 'none' : 'block';
            if (btn) btn.classList.toggle('open', !visible);
        }

        // Custom scoreboard has extra logic: load data on first open.
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

        /** Path passed to 2026/video_player.php?v= — project-root-relative. */
        function videoPlayerMediaPath(file) {
            if (!file) return '';
            var f = String(file).replace(/\\/g, '/').trim();
            if (!f) return '';
            if (f.indexOf('production_files/') === 0) return f;
            if (f.indexOf('2026/') === 0) return f;
            return '2026/' + f;
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
                "player-scoreboard-overlay",
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
                "player-scoreboard-overlay": "Player scoreboard",
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
                sceneVideos: {
                    schedule: "production_files/2026_FSL_schedule_now.mp4",
                    bracket: "production_files/2026_FSL_schedule_now.mp4",
                    teams: { ash: "2026/ASH.mp4", pog: "2026/POG.mp4", ptb: "2026/PTB.mp4", st: "2026/ST.mp4" }
                },
                breakSettings: { min: 5, sec: 0, msg: "be right back..." },
                sc2ButtonEffects: { effect1: "Random Music", effect2: "FSL intro" },
                musicMode: 'sequence',
                musicVol: 22,
                musicFade: 4,
                productionFiles: { mode: "remote", remoteBaseUrl: "https://psistorm.com/stream_production/production_files/" }
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
                    sceneVideos: typeof getSceneVideoSettings === "function" ? getSceneVideoSettings() : DEFAULT_SETTINGS.sceneVideos,
                    breakSettings: typeof getBreakSettings === "function" ? getBreakSettings() : DEFAULT_SETTINGS.breakSettings,
                    sc2ButtonEffects: typeof getSc2ButtonEffects === "function" ? getSc2ButtonEffects() : DEFAULT_SETTINGS.sc2ButtonEffects,
                    musicMode: (function() { var el = document.querySelector('input[name="music-mode"]:checked'); return el ? el.value : 'sequence'; })(),
                musicVol: (function() { var el = document.getElementById('lpMusicVol'); return el ? parseFloat(el.value) : 22; })(),
                musicFade: (function() { var el = document.getElementById('lpMusicFade'); return el ? parseFloat(el.value) : 1.5; })(),
                sceneMoodMap: (typeof MX_SCENE_MAP    !== 'undefined' ? MX_SCENE_MAP    : {}),
                moodSongs:    (typeof MX_TRACKS       !== 'undefined' ? MX_TRACKS       : {}),
                sceneStages:  (typeof MX_SCENE_STAGES !== 'undefined' ? MX_SCENE_STAGES : {}),
                productionFiles: {
                    mode: (function() { var el = document.querySelector('input[name="production-files-mode"]:checked'); return el && el.value === "remote" ? "remote" : "local"; })(),
                    remoteBaseUrl: (function() { var el = document.getElementById("production-files-remote-url"); return el ? el.value.trim() : ""; })()
                }
                };
                return out;
            }

            function importAllSettings(parsed) {
                var def = DEFAULT_SETTINGS;
                if (!parsed || typeof parsed !== "object") return;
                var pfDef = def.productionFiles || { mode: "local", remoteBaseUrl: "" };
                var pf = Object.assign({}, pfDef, (parsed.productionFiles && typeof parsed.productionFiles === "object") ? parsed.productionFiles : {});
                var modeRadio = document.querySelector('input[name="production-files-mode"][value="' + (pf.mode === "remote" ? "remote" : "local") + '"]');
                if (modeRadio) modeRadio.checked = true;
                var pfUrl = document.getElementById("production-files-remote-url");
                if (pfUrl) {
                    if (pf.remoteBaseUrl) pfUrl.value = String(pf.remoteBaseUrl);
                    else if (!pfUrl.value && window.PRODUCTION_FILES_DEFAULT_REMOTE) pfUrl.value = window.PRODUCTION_FILES_DEFAULT_REMOTE + "/";
                }
                if (typeof window.applyProductionFilesSettings === "function") {
                    window.applyProductionFilesSettings(pf);
                }
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
                            if (videoIframe) videoIframe.src = "2026/video_player.php?v=" + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES["all-vdo"])) + "&_t=" + Date.now();
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
                var sv = parsed.sceneVideos;
                if (sv && typeof sv === "object") {
                    if (typeof setSceneVideoSettings === "function") setSceneVideoSettings(sv);
                }
                var bks = parsed.breakSettings;
                if (bks && typeof bks === "object") {
                    if (typeof setBreakSettings === "function") {
                        setBreakSettings(bks);
                        if (typeof saveBreakSettings === "function") saveBreakSettings();
                    }
                }
                var sc2Fx = parsed.sc2ButtonEffects;
                if (sc2Fx && typeof sc2Fx === "object") {
                    if (typeof setSc2ButtonEffects === "function") setSc2ButtonEffects(sc2Fx);
                } else if (def.sc2ButtonEffects) {
                    if (typeof setSc2ButtonEffects === "function") setSc2ButtonEffects(def.sc2ButtonEffects);
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
                if (parsed.sceneStages && typeof parsed.sceneStages === 'object') {
                    MX_SCENE_STAGES = parsed.sceneStages;
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
                            try { document.dispatchEvent(new CustomEvent('status:score-saved')); } catch (err) {}
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
            var _sbEditorWindow = null;

            window.addEventListener('message', function(e) {
                if (e.origin !== window.location.origin) return;
                if (!e.data || e.data.type !== 'scoreboard-editor-saved') return;
                scoreboardEditorLoad();
                try { document.dispatchEvent(new CustomEvent('status:score-saved')); } catch (err) {}
            });

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
                document.querySelectorAll('#scoreboard-editor-content .sb-score-a-input').forEach(function(el) { totalA += parseInt(el.value, 10) || 0; });
                document.querySelectorAll('#scoreboard-editor-content .sb-score-b-input').forEach(function(el) { totalB += parseInt(el.value, 10) || 0; });
                var elA = document.getElementById('sb-score-a');
                var elB = document.getElementById('sb-score-b');
                if (elA) elA.textContent = totalA;
                if (elB) elB.textContent = totalB;
            }

            function sbEsc(s) {
                if (s == null) return '';
                var d = document.createElement('div');
                d.textContent = String(s);
                return d.innerHTML;
            }

            function sbAttr(s) {
                return sbEsc(s).replace(/"/g, '&quot;');
            }

            function sbEditorEmptyRow() {
                return ['', '', '', '', '', '', '', '', '', '', '', ''];
            }

            function sbEditorIsDataRowEmpty(row) {
                if (!row) return true;
                return !row[1] && !row[2] && !row[3] && !row[4] && !row[6] && !row[7] && !row[8] && !row[10] && !row[11];
            }

            function sbEditorGetDataRows(rows) {
                var data = [];
                for (var i = 2; i < rows.length; i++) {
                    if (!sbEditorIsDataRowEmpty(rows[i])) data.push(rows[i].slice());
                }
                return data;
            }

            function sbEditorRebuildRows(headerRow, mapRow, dataRows) {
                var rows = [headerRow.slice(), mapRow.slice()];
                dataRows.forEach(function(row) { rows.push(row.slice()); });
                rows.push(sbEditorEmptyRow());
                return rows;
            }

            function sbEditorRowsToCsv(rows) {
                return rows.map(function(row) {
                    return row.map(function(cell) {
                        var s = String(cell == null ? '' : cell);
                        if (s === '') return '""';
                        if (/^\d+(\.\d+)?$/.test(s)) return s;
                        return '"' + s.replace(/"/g, '""') + '"';
                    }).join(',');
                }).join('\n');
            }

            function sbEditorSaveCsv(rows, statusEl, btn, onSuccess) {
                var csv = sbEditorRowsToCsv(rows);
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
                        try { document.dispatchEvent(new CustomEvent('status:score-saved')); } catch (err) {}
                        if (onSuccess) onSuccess();
                    } else {
                        if (statusEl) statusEl.textContent = 'Error saving.';
                    }
                    setTimeout(function() { if (statusEl) statusEl.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                }).catch(function() {
                    if (statusEl) statusEl.textContent = 'Network error.';
                    setTimeout(function() { if (statusEl) statusEl.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                });
            }

            function sbEditorApplySimpleScores() {
                if (!_sbEditorRows || _sbEditorRows.length === 0) return;
                var headerRow = (_sbEditorRows[0] || sbEditorEmptyRow()).slice();
                var mapRow = (_sbEditorRows[1] || sbEditorEmptyRow()).slice();
                var dataRows = sbEditorGetDataRows(_sbEditorRows);
                document.querySelectorAll('#scoreboard-matchup-rows .sb-matchup-row').forEach(function(el, idx) {
                    if (!dataRows[idx]) return;
                    var inpA = el.querySelector('.sb-score-a-input');
                    var inpB = el.querySelector('.sb-score-b-input');
                    if (inpA) dataRows[idx][4] = inpA.value;
                    if (inpB) dataRows[idx][8] = inpB.value;
                });
                sbRecalcTotals();
                headerRow[4] = (document.getElementById('sb-score-a') || {}).textContent || headerRow[4] || '';
                headerRow[8] = (document.getElementById('sb-score-b') || {}).textContent || headerRow[8] || '';
                _sbEditorRows = sbEditorRebuildRows(headerRow, mapRow, dataRows);
            }

            function scoreboardEditorRenderSimple(rows) {
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
                var dataRows = sbEditorGetDataRows(rows);
                dataRows.forEach(function(row, idx) {
                    var matchType = (row[1] || '').trim();
                    var pA1 = (row[2] || '').trim();
                    var pA2 = (row[3] || '').trim();
                    var sA = row[4] != null ? row[4] : '';
                    var pB1 = (row[6] || '').trim();
                    var pB2 = (row[7] || '').trim();
                    var sB = row[8] != null ? row[8] : '';
                    var map1 = (row[10] || '').trim();
                    var map2 = (row[11] || '').trim();
                    var playersA = pA2 ? pA1 + ' / ' + pA2 : pA1;
                    var playersB = pB2 ? pB1 + ' / ' + pB2 : pB1;
                    var mapsText = [map1, map2].filter(Boolean).join(' | ');
                    var div = document.createElement('div');
                    div.className = 'sb-matchup-row';
                    div.dataset.rowIdx = idx;
                    div.style.cssText = 'display:flex; align-items:center; gap:6px; padding:5px 8px; background:#111; border-radius:3px; font-size:12px; margin-bottom:3px;';
                    div.innerHTML =
                        '<span style="color:#666; min-width:28px; font-size:11px; flex-shrink:0;">' + sbEsc(matchType) + '</span>' +
                        '<span style="flex:1; text-align:right; color:#bbb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-right:4px;" title="' + sbAttr(playersA) + '">' + sbEsc(playersA) + '</span>' +
                        '<input type="number" class="sb-score-a-input" min="0" max="99" value="' + sbAttr(String(sA)) + '" oninput="sbRecalcTotals()" style="width:44px; text-align:center; background:#1a2a1a; color:#8f8; border:1px solid #484; border-radius:3px; padding:2px 4px; flex-shrink:0;">' +
                        '<span style="color:#555; flex-shrink:0;">–</span>' +
                        '<input type="number" class="sb-score-b-input" min="0" max="99" value="' + sbAttr(String(sB)) + '" oninput="sbRecalcTotals()" style="width:44px; text-align:center; background:#1a1a2a; color:#88f; border:1px solid #448; border-radius:3px; padding:2px 4px; flex-shrink:0;">' +
                        '<span style="flex:1; text-align:left; color:#bbb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:4px;" title="' + sbAttr(playersB) + '">' + sbEsc(playersB) + '</span>' +
                        '<span style="color:#555; font-size:11px; min-width:56px; text-align:right; flex-shrink:0;">' + sbEsc(mapsText) + '</span>';
                    container.appendChild(div);
                });
                sbRecalcTotals();
            }

            function scoreboardEditorRender(rows) {
                scoreboardEditorRenderSimple(rows);
            }

            window.sbEditorOpenWindow = function sbEditorOpenWindow() {
                if (!_sbEditorRows) { alert('Load the CSV first.'); return; }
                sbEditorApplySimpleScores();
                try {
                    sessionStorage.setItem('sb_editor_bootstrap', JSON.stringify(_sbEditorRows));
                } catch (e) {}
                if (_sbEditorWindow && !_sbEditorWindow.closed) {
                    _sbEditorWindow.focus();
                    return;
                }
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var url = (base ? base + '/' : './') + 'scoreboard_editor.php';
                _sbEditorWindow = window.open(url, 'scoreboardEditor', 'width=980,height=760,resizable=yes,scrollbars=yes');
            };

            window.scoreboardEditorSave = function scoreboardEditorSave() {
                if (!_sbEditorRows) { alert('Load the CSV first.'); return; }
                var statusEl = document.getElementById('scoreboard-save-status');
                var btn = document.getElementById('scoreboard-score-save-btn');
                if (btn) btn.disabled = true;
                if (statusEl) statusEl.textContent = 'Saving…';
                sbEditorApplySimpleScores();
                var rows = _sbEditorRows;
                sbEditorSaveCsv(rows, statusEl, btn, null);
            }

            function doRefreshRankings() {
                var rankingsRefreshBtn = document.getElementById("rankings-refresh-btn");
                var rankingsRefreshStatus = document.getElementById("rankings-refresh-status");
                if (rankingsRefreshBtn && rankingsRefreshStatus) {
                    rankingsRefreshStatus.textContent = "…";
                    rankingsRefreshBtn.disabled = true;
                }
                fetch("rankings.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ refresh: true }), cache: "no-store" }).then(function(r) { return r.json(); }).then(function(result) {
                    if (result && result.ok) {
                        var refreshTasks = [];
                        if (typeof window.reloadRankingsCache === "function") refreshTasks.push(window.reloadRankingsCache());
                        var overlay = document.getElementById('scoreboard-overlay');
                        if (overlay && overlay.style.display === 'block' && typeof window.loadAndRenderScoreboard === 'function') {
                            refreshTasks.push(window.loadAndRenderScoreboard());
                        }
                        return Promise.all(refreshTasks).then(function() {
                            if (typeof window.psbRenderPanel === 'function') window.psbRenderPanel();
                            if (rankingsRefreshStatus) rankingsRefreshStatus.textContent = "Updated.";
                        });
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
            // After DOMContentLoaded, deferred module stream_production.js has run so reloadRankingsCache exists when refresh finishes.
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function rankingsAutoRefreshOnLoad() {
                    doRefreshRankings();
                });
            } else {
                doRefreshRankings();
            }

            applyOrder(getStoredOrder());
            buildLayerList();

            (function initProductionFilesUi() {
                var urlEl = document.getElementById("production-files-remote-url");
                if (urlEl && !urlEl.value && window.PRODUCTION_FILES_DEFAULT_REMOTE) {
                    urlEl.value = window.PRODUCTION_FILES_DEFAULT_REMOTE + "/";
                }
                var btn = document.getElementById("production-files-apply-btn");
                if (!btn) return;
                btn.addEventListener("click", function() {
                    var modeEl = document.querySelector('input[name="production-files-mode"]:checked');
                    var st = document.getElementById("production-files-status");
                    if (typeof window.applyProductionFilesSettings !== "function") return;
                    window.applyProductionFilesSettings({ mode: modeEl ? modeEl.value : "local", remoteBaseUrl: urlEl ? urlEl.value.trim() : "" });
                    if (st) {
                        st.textContent = "Applied.";
                        setTimeout(function() { st.textContent = ""; }, 2500);
                    }
                });
            })();

            fetch("settings.php").then(function(r) { return r.json(); }).then(function(parsed) {
                if (parsed && (parsed.layerOrder !== undefined || parsed.order !== undefined || parsed.status !== undefined || parsed.version !== undefined || parsed.productionFiles !== undefined)) {
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

        /* ── SC2 (animated) button effects ───────────────────────── */
        var SC2_BUTTON_EFFECTS_DEFAULTS = { effect1: 'Random Music', effect2: 'FSL intro' };

        function getSc2ButtonEffects() {
            var e1 = document.getElementById('sc2-effect-1');
            var e2 = document.getElementById('sc2-effect-2');
            return {
                effect1: e1 ? e1.value.trim() : SC2_BUTTON_EFFECTS_DEFAULTS.effect1,
                effect2: e2 ? e2.value.trim() : SC2_BUTTON_EFFECTS_DEFAULTS.effect2
            };
        }

        function setSc2ButtonEffects(obj) {
            var e1 = document.getElementById('sc2-effect-1');
            var e2 = document.getElementById('sc2-effect-2');
            if (e1) e1.value = (obj && obj.effect1 != null) ? String(obj.effect1) : SC2_BUTTON_EFFECTS_DEFAULTS.effect1;
            if (e2) e2.value = (obj && obj.effect2 != null) ? String(obj.effect2) : SC2_BUTTON_EFFECTS_DEFAULTS.effect2;
        }

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
        /** @param {boolean} [skipMusicResume] if true, do not resume mood deck (used when swapping Intro↔Break). */
        function closeYtIframeScene(skipMusicResume) {
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
            if (!skipMusicResume && typeof window.mxResumeForYt === 'function') window.mxResumeForYt();
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

        var ytIframeVolumeApplyGen = 0;

        function ytIframePostSetVolume(iframe, vol) {
            if (!iframe || !iframe.contentWindow) return;
            var v = Math.max(0, Math.min(100, vol != null ? Number(vol) : 100));
            if (!isFinite(v)) v = 100;
            try {
                iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'setVolume', args: [v] }), 'https://www.youtube.com');
            } catch (e) {}
        }

        function subscribeYtIframe(iframe, vol) {
            if (!iframe || !iframe.contentWindow) return;
            var v = vol != null ? Math.max(0, Math.min(100, Number(vol))) : 100;
            if (!isFinite(v)) v = 100;
            var gen = ++ytIframeVolumeApplyGen;
            try {
                iframe.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1 }), 'https://www.youtube.com');
            } catch (e) {}
            ytIframePostSetVolume(iframe, v);
            /* YouTube often ignores the first command until the player is ready — fire now then retry. */
            [16, 50, 120, 300, 600].forEach(function (ms) {
                setTimeout(function () {
                    if (gen !== ytIframeVolumeApplyGen) return;
                    ytIframePostSetVolume(iframe, v);
                }, ms);
            });
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
                closeYtIframeScene(); /* mxResumeForYt called inside */
            } else {
                /* Skip deck resume when only swapping Intro↔Break so mxPauseForYt / mxResumeForYt stay consistent. */
                closeYtIframeScene(isActive);
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
        function resolveStreamSceneVideoSrc(videoSrc) {
            if (!videoSrc) return videoSrc;
            if (/^https?:\/\//i.test(videoSrc)) return videoSrc;
            var base = (typeof window.STREAM_SCENE_ASSETS_BASE === 'string') ? window.STREAM_SCENE_ASSETS_BASE : '';
            if (base) {
                return String(base).replace(/\/?$/, '/') + String(videoSrc).replace(/^\.?\//, '');
            }
            return videoSrc;
        }

        function playTransitionVideo(options) {
            var opts = options || {};
            var videoSrc = resolveStreamSceneVideoSrc(opts.videoSrc);
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
            if (typeof window.applyAnonymousCORSIfNeeded === 'function') {
                window.applyAnonymousCORSIfNeeded(video, videoSrc);
            }
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

        var VIDEO_OVERLAY_KEYS = ['all-vdo', 'schedule', 'bracket', 'ash', 'pog', 'ptb', 'st'];
            var VIDEO_OVERLAY_FILES = {
                'all-vdo': '2026/2026_FSL_BG.mp4',
                'schedule': 'production_files/2026_FSL_schedule_now.mp4',
                'bracket': 'production_files/2026_FSL_schedule_now.mp4',
                'ash': '2026/ASH.mp4',
                'pog': '2026/POG.mp4',
                'ptb': '2026/PTB.mp4',
                'st': '2026/ST.mp4'
            };
            var VIDEO_OVERLAY_FRONT = ['schedule', 'bracket', 'ash', 'pog', 'ptb', 'st'];
            var VIDEO_OVERLAY_AUDIO = { 'ash': 'angry_space_hares.wav', 'pog': 'FSL_PSISOP_Gaming.wav', 'ptb': 'pulled_the_boys.wav', 'st': 'FSL_SpecialTactics2.wav' };

            function getSceneVideoSettings() {
                return {
                    schedule: VIDEO_OVERLAY_FILES.schedule || 'production_files/2026_FSL_schedule_now.mp4',
                    bracket: VIDEO_OVERLAY_FILES.bracket || 'production_files/2026_FSL_schedule_now.mp4',
                    teams: {
                        ash: VIDEO_OVERLAY_FILES.ash || '2026/ASH.mp4',
                        pog: VIDEO_OVERLAY_FILES.pog || '2026/POG.mp4',
                        ptb: VIDEO_OVERLAY_FILES.ptb || '2026/PTB.mp4',
                        st: VIDEO_OVERLAY_FILES.st || '2026/ST.mp4'
                    }
                };
            }

            function syncSceneVideoInputValue(inputId, filePath) {
                var input = document.getElementById(inputId);
                if (!input || !filePath) return;
                input.value = filePath;
            }

            function setSceneVideoSettings(sceneVideos) {
                if (!sceneVideos || typeof sceneVideos !== 'object') return;
                if (typeof sceneVideos.schedule === 'string' && sceneVideos.schedule.trim()) VIDEO_OVERLAY_FILES.schedule = sceneVideos.schedule.trim();
                if (typeof sceneVideos.bracket === 'string' && sceneVideos.bracket.trim()) VIDEO_OVERLAY_FILES.bracket = sceneVideos.bracket.trim();
                var teams = sceneVideos.teams;
                if (teams && typeof teams === 'object') {
                    ['ash', 'pog', 'ptb', 'st'].forEach(function(k) {
                        if (typeof teams[k] === 'string' && teams[k].trim()) VIDEO_OVERLAY_FILES[k] = teams[k].trim();
                    });
                }
                syncSceneVideoInputValue('scene-video-schedule-file', VIDEO_OVERLAY_FILES.schedule);
                syncSceneVideoInputValue('scene-video-bracket-file', VIDEO_OVERLAY_FILES.bracket);
                syncSceneVideoInputValue('scene-video-team-ash-file', VIDEO_OVERLAY_FILES.ash);
                syncSceneVideoInputValue('scene-video-team-pog-file', VIDEO_OVERLAY_FILES.pog);
                syncSceneVideoInputValue('scene-video-team-ptb-file', VIDEO_OVERLAY_FILES.ptb);
                syncSceneVideoInputValue('scene-video-team-st-file', VIDEO_OVERLAY_FILES.st);
            }

            window.getSceneVideoSettings = getSceneVideoSettings;
            window.setSceneVideoSettings = setSceneVideoSettings;

            function setupSceneVideoSettingsUi() {
                var folderSelect = document.getElementById('scene-video-folder');
                var scheduleInput = document.getElementById('scene-video-schedule-file');
                var bracketInput = document.getElementById('scene-video-bracket-file');
                var scheduleSuggestions = document.getElementById('scene-video-schedule-suggestions');
                var bracketSuggestions = document.getElementById('scene-video-bracket-suggestions');
                var teamRootSelect = document.getElementById('scene-team-media-root');
                var teamFolderSelect = document.getElementById('scene-team-video-folder');
                var teamRefreshBtn = document.getElementById('scene-team-video-refresh-btn');
                var ashInput = document.getElementById('scene-video-team-ash-file');
                var pogInput = document.getElementById('scene-video-team-pog-file');
                var ptbInput = document.getElementById('scene-video-team-ptb-file');
                var stInput = document.getElementById('scene-video-team-st-file');
                var ashSuggestions = document.getElementById('scene-video-team-ash-suggestions');
                var pogSuggestions = document.getElementById('scene-video-team-pog-suggestions');
                var ptbSuggestions = document.getElementById('scene-video-team-ptb-suggestions');
                var stSuggestions = document.getElementById('scene-video-team-st-suggestions');
                var refreshBtn = document.getElementById('scene-video-refresh-btn');
                var uploadBtn = document.getElementById('scene-video-upload-btn');
                var uploadFile = document.getElementById('scene-video-upload-file');
                var statusEl = document.getElementById('scene-video-status');
                var uploadDestSchedule = document.getElementById('scene-upload-dest-schedule');
                var uploadDestTeam = document.getElementById('scene-upload-dest-team');
                if (!folderSelect || !scheduleInput || !bracketInput || !scheduleSuggestions || !bracketSuggestions || !refreshBtn || !uploadBtn || !uploadFile || !statusEl) return;
                if (!teamRootSelect || !teamFolderSelect || !teamRefreshBtn) return;
                if (!ashInput || !pogInput || !ptbInput || !stInput || !ashSuggestions || !pogSuggestions || !ptbSuggestions || !stSuggestions) return;
                if (!uploadDestSchedule || !uploadDestTeam) return;

                var availableFiles = [];
                var availableTeamFiles = [];

                function setStatus(msg, isError) {
                    statusEl.textContent = msg || '';
                    statusEl.style.color = isError ? '#c55' : '#8bc34a';
                }

                function inferFolderUnderPrefix(paths, prefix) {
                    for (var i = 0; i < paths.length; i++) {
                        var fp = String(paths[i] || '').replace(/\\/g, '/');
                        if (!fp) continue;
                        if (fp.indexOf(prefix + '/') !== 0) continue;
                        var rel = fp.slice((prefix + '/').length);
                        var slash = rel.lastIndexOf('/');
                        return slash >= 0 ? rel.slice(0, slash) : '';
                    }
                    return '';
                }

                function inferFolderFromCurrentSelection() {
                    return inferFolderUnderPrefix([VIDEO_OVERLAY_FILES.schedule, VIDEO_OVERLAY_FILES.bracket], 'production_files');
                }

                function inferTeamFolderFromCurrentSelection() {
                    var root = teamRootSelect ? teamRootSelect.value : '2026';
                    var paths = [VIDEO_OVERLAY_FILES.ash, VIDEO_OVERLAY_FILES.pog, VIDEO_OVERLAY_FILES.ptb, VIDEO_OVERLAY_FILES.st];
                    return inferFolderUnderPrefix(paths, root);
                }

                function renderSuggestions(containerEl, matches, onPick) {
                    containerEl.innerHTML = '';
                    if (!matches.length) {
                        containerEl.style.display = 'none';
                        return;
                    }
                    matches.forEach(function(fp) {
                        var row = document.createElement('div');
                        row.textContent = fp;
                        row.style.padding = '4px 8px';
                        row.style.cursor = 'pointer';
                        row.style.fontSize = '12px';
                        row.style.color = '#111';
                        row.style.background = '#fff';
                        row.style.borderBottom = '1px solid #ddd';
                        row.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            onPick(fp);
                            containerEl.style.display = 'none';
                        });
                        row.addEventListener('mouseenter', function() { row.style.background = '#eaf2ff'; });
                        row.addEventListener('mouseleave', function() { row.style.background = '#fff'; });
                        containerEl.appendChild(row);
                    });
                    containerEl.style.display = 'block';
                }

                function saveGlobalSceneVideoSettings() {
                    var payload = getSceneVideoSettings();
                    return fetch('scene_video_settings.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(function(r) { return r.json(); });
                }

                function commitScheduleBracketValue(sceneKey, value, list) {
                    if (!value) return;
                    var exists = list.indexOf(value) !== -1;
                    if (!exists) {
                        setStatus('File not in current list for this folder.', true);
                        return;
                    }
                    if (sceneKey === 'schedule') VIDEO_OVERLAY_FILES.schedule = value;
                    if (sceneKey === 'bracket') VIDEO_OVERLAY_FILES.bracket = value;
                    saveGlobalSceneVideoSettings().then(function() {
                        setStatus('Saved ' + sceneKey + ' file.', false);
                    }).catch(function() {});
                }

                function commitTeamValue(overlayKey, value, list) {
                    if (!value) return;
                    var exists = list.indexOf(value) !== -1;
                    if (!exists) {
                        setStatus('File not in current team folder list.', true);
                        return;
                    }
                    VIDEO_OVERLAY_FILES[overlayKey] = value;
                    saveGlobalSceneVideoSettings().then(function() {
                        setStatus('Saved ' + String(overlayKey).toUpperCase() + ' file.', false);
                    }).catch(function() {});
                }

                function wireAutocomplete(inputEl, listEl, sceneKey, listGetter, commitFn) {
                    inputEl.addEventListener('input', function() {
                        var q = String(inputEl.value || '').trim().toLowerCase();
                        if (q.length < 3) {
                            listEl.style.display = 'none';
                            listEl.innerHTML = '';
                            return;
                        }
                        var list = listGetter();
                        var matches = list.filter(function(fp) { return fp.toLowerCase().indexOf(q) !== -1; }).slice(0, 40);
                        renderSuggestions(listEl, matches, function(picked) {
                            inputEl.value = picked;
                            commitFn(sceneKey, picked, list);
                        });
                    });
                    inputEl.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            commitFn(sceneKey, String(inputEl.value || '').trim(), listGetter());
                            listEl.style.display = 'none';
                        }
                    });
                    inputEl.addEventListener('blur', function() {
                        commitFn(sceneKey, String(inputEl.value || '').trim(), listGetter());
                        setTimeout(function() { listEl.style.display = 'none'; }, 150);
                    });
                }

                function loadGlobalSceneVideoSettings() {
                    return fetch('scene_video_settings.php')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.ok && data.settings) {
                                setSceneVideoSettings(data.settings);
                            }
                        })
                        .catch(function() {});
                }

                function loadSceneVideoList() {
                    var folder = folderSelect.value || '';
                    setStatus('Loading...', false);
                    fetch('scene_videos.php?root=production_files&folder=' + encodeURIComponent(folder))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data || !data.ok) {
                                setStatus((data && data.error) ? data.error : 'Failed to load list', true);
                                return;
                            }
                            var folders = data.folders || [];
                            var files = data.files || [];
                            var prevFolder = folderSelect.value || '';
                            folderSelect.innerHTML = '';
                            var rootOpt = document.createElement('option');
                            rootOpt.value = '';
                            rootOpt.textContent = '(production_files root)';
                            folderSelect.appendChild(rootOpt);
                            folders.forEach(function(f) {
                                var opt = document.createElement('option');
                                opt.value = f;
                                opt.textContent = f;
                                folderSelect.appendChild(opt);
                            });
                            folderSelect.value = folders.indexOf(prevFolder) !== -1 || prevFolder === '' ? prevFolder : '';

                            availableFiles = files.slice();
                            if (availableFiles.indexOf(VIDEO_OVERLAY_FILES.schedule) === -1 && availableFiles.length) {
                                VIDEO_OVERLAY_FILES.schedule = availableFiles[0];
                            }
                            if (availableFiles.indexOf(VIDEO_OVERLAY_FILES.bracket) === -1 && availableFiles.length) {
                                VIDEO_OVERLAY_FILES.bracket = availableFiles[0];
                            }
                            scheduleInput.value = VIDEO_OVERLAY_FILES.schedule || '';
                            bracketInput.value = VIDEO_OVERLAY_FILES.bracket || '';
                            setStatus('Loaded ' + files.length + ' media file(s) (production_files).', false);
                        })
                        .catch(function() {
                            setStatus('Failed to load list', true);
                        });
                }

                function loadTeamSceneVideoList() {
                    var root = teamRootSelect.value || '2026';
                    var folder = teamFolderSelect.value || '';
                    setStatus('Loading team list...', false);
                    fetch('scene_videos.php?root=' + encodeURIComponent(root) + '&folder=' + encodeURIComponent(folder))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data || !data.ok) {
                                setStatus((data && data.error) ? data.error : 'Failed to load team list', true);
                                return;
                            }
                            var folders = data.folders || [];
                            var files = data.files || [];
                            var prevFolder = teamFolderSelect.value || '';
                            teamFolderSelect.innerHTML = '';
                            var tro = document.createElement('option');
                            tro.value = '';
                            tro.textContent = '(' + root + ' root)';
                            teamFolderSelect.appendChild(tro);
                            folders.forEach(function(f) {
                                var opt = document.createElement('option');
                                opt.value = f;
                                opt.textContent = f;
                                teamFolderSelect.appendChild(opt);
                            });
                            teamFolderSelect.value = folders.indexOf(prevFolder) !== -1 || prevFolder === '' ? prevFolder : '';

                            availableTeamFiles = files.slice();
                            function pathMatchesRoot(fp, r) {
                                fp = String(fp || '').replace(/\\/g, '/');
                                if (!fp) return false;
                                if (r === 'production_files') return fp.indexOf('production_files/') === 0;
                                return fp.indexOf('2026/') === 0;
                            }
                            ['ash', 'pog', 'ptb', 'st'].forEach(function(k) {
                                var cur = VIDEO_OVERLAY_FILES[k];
                                if (availableTeamFiles.indexOf(cur) !== -1) return;
                                if (availableTeamFiles.length && (!pathMatchesRoot(cur, root) || !cur)) {
                                    VIDEO_OVERLAY_FILES[k] = availableTeamFiles[0];
                                }
                            });
                            ashInput.value = VIDEO_OVERLAY_FILES.ash || '';
                            pogInput.value = VIDEO_OVERLAY_FILES.pog || '';
                            ptbInput.value = VIDEO_OVERLAY_FILES.ptb || '';
                            stInput.value = VIDEO_OVERLAY_FILES.st || '';
                            setStatus('Loaded ' + files.length + ' media file(s) (' + root + ').', false);
                        })
                        .catch(function() {
                            setStatus('Failed to load team list', true);
                        });
                }

                wireAutocomplete(scheduleInput, scheduleSuggestions, 'schedule', function() { return availableFiles; }, commitScheduleBracketValue);
                wireAutocomplete(bracketInput, bracketSuggestions, 'bracket', function() { return availableFiles; }, commitScheduleBracketValue);
                wireAutocomplete(ashInput, ashSuggestions, 'ash', function() { return availableTeamFiles; }, commitTeamValue);
                wireAutocomplete(pogInput, pogSuggestions, 'pog', function() { return availableTeamFiles; }, commitTeamValue);
                wireAutocomplete(ptbInput, ptbSuggestions, 'ptb', function() { return availableTeamFiles; }, commitTeamValue);
                wireAutocomplete(stInput, stSuggestions, 'st', function() { return availableTeamFiles; }, commitTeamValue);

                folderSelect.addEventListener('change', loadSceneVideoList);
                refreshBtn.addEventListener('click', loadSceneVideoList);
                teamFolderSelect.addEventListener('change', loadTeamSceneVideoList);
                teamRefreshBtn.addEventListener('click', loadTeamSceneVideoList);
                teamRootSelect.addEventListener('change', function() {
                    teamFolderSelect.value = inferTeamFolderFromCurrentSelection();
                    loadTeamSceneVideoList();
                });

                function uploadSelectedMedia() {
                    if (!uploadFile.files || !uploadFile.files.length) {
                        setStatus('Pick a media file first.', true);
                        return;
                    }
                    var toTeam = uploadDestTeam && uploadDestTeam.checked;
                    var mediaRoot = toTeam ? (teamRootSelect.value || '2026') : 'production_files';
                    var targetFolder = toTeam ? (teamFolderSelect.value || '') : (folderSelect.value || '');
                    var targetLabel = mediaRoot + (targetFolder ? '/' + targetFolder : '');
                    var fd = new FormData();
                    fd.append('video_file', uploadFile.files[0]);
                    fd.append('media_root', mediaRoot);
                    fd.append('target_folder', targetFolder);
                    uploadBtn.disabled = true;
                    setStatus('Uploading to ' + targetLabel + ' ...', false);
                    fetch('scene_videos.php', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data || !data.ok) {
                                setStatus((data && data.error) ? data.error : 'Upload failed', true);
                                return;
                            }
                            setStatus('Uploaded: ' + data.path, false);
                            uploadFile.value = '';
                            if (toTeam) loadTeamSceneVideoList();
                            else loadSceneVideoList();
                        })
                        .catch(function() {
                            setStatus('Upload failed', true);
                        })
                        .finally(function() {
                            uploadBtn.disabled = false;
                        });
                }

                uploadBtn.addEventListener('click', uploadSelectedMedia);

                loadGlobalSceneVideoSettings().finally(function() {
                    folderSelect.value = inferFolderFromCurrentSelection();
                    loadSceneVideoList();
                    teamRootSelect.value = (function() {
                        var p = String(VIDEO_OVERLAY_FILES.ash || '').replace(/\\/g, '/');
                        if (p.indexOf('production_files/') === 0) return 'production_files';
                        return '2026';
                    })();
                    teamFolderSelect.value = inferTeamFolderFromCurrentSelection();
                    loadTeamSceneVideoList();
                });
            }
            setupSceneVideoSettingsUi();

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
                if (typeof window.tlsShowTeamLeagueScoreBannerIfReady === 'function') window.tlsShowTeamLeagueScoreBannerIfReady();
                var csbEl = document.getElementById('custom-scoreboard-overlay');
                var csbBtn = document.getElementById('scene-btn-custom-scoreboard');
                if (csbEl) { csbEl.style.display = 'none'; csbEl.style.zIndex = ''; }
                if (csbBtn) csbBtn.classList.remove('active');
                var matchupOverlayEl = document.getElementById('matchup-overlay');
                var matchupBtnEl = document.getElementById('scene-btn-matchup');
                if (matchupOverlayEl) matchupOverlayEl.style.display = 'none';
                if (matchupBtnEl) { matchupBtnEl.classList.remove('active'); matchupBtnEl.classList.remove('armed'); }
                if (typeof disarmMatchup === 'function') {
                    disarmMatchup();
                } else if (typeof matchupArmed !== 'undefined') {
                    matchupArmed = false;
                    matchupPicks = [];
                    if (typeof refreshMatchupPickHighlights === 'function') refreshMatchupPickHighlights();
                }
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
                raceIconBase: (typeof window.STREAM_FSL_IMAGES_BASE === 'string' && window.STREAM_FSL_IMAGES_BASE) ? window.STREAM_FSL_IMAGES_BASE : 'https://psistorm.com/fsl/images/',
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
                var rankingsUrl = (baseUrl || './') + 'rankings.php?_t=' + Date.now();
                fetch(rankingsUrl, { cache: 'no-store' }).then(function(r) { return r.json(); }).then(function(rankings) {
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

            /* ---- Team League total score banner (top-right brand area) ---- */
            var TLS_ENABLED_KEY = 'stream_production_tls_score_enabled';
            window.tlsScoreEnabled = (function() {
                try { return localStorage.getItem(TLS_ENABLED_KEY) !== 'false'; } catch (e) { return true; }
            })();
            function tlsCleanTeamName(s) {
                return String(s == null ? '' : s).replace(/\[\d+\]/g, '').replace(/^\s*\([A-Za-z]\)\s*/, '').trim();
            }
            window.tlsHideTeamLeagueScoreBanner = function() {
                var b = document.getElementById('team-league-score-banner');
                if (b) b.style.display = 'none';
            };
            window.tlsShowTeamLeagueScoreBannerIfReady = function() {
                if (!window.tlsScoreEnabled) return;
                var sbOverlay = document.getElementById('scoreboard-overlay');
                if (sbOverlay && sbOverlay.style.display === 'block') return;
                var b = document.getElementById('team-league-score-banner');
                if (b && b.innerHTML && b.innerHTML.trim()) b.style.display = 'block';
            };
            window.toggleTeamLeagueScoreBanner = function() {
                window.tlsScoreEnabled = !window.tlsScoreEnabled;
                try { localStorage.setItem(TLS_ENABLED_KEY, window.tlsScoreEnabled ? 'true' : 'false'); } catch (e) {}
                tlsSyncScoreToggleBtn();
                if (window.tlsScoreEnabled) {
                    window.tlsShowTeamLeagueScoreBannerIfReady();
                } else {
                    window.tlsHideTeamLeagueScoreBanner();
                }
            };
            function tlsSyncScoreToggleBtn() {
                var btn = document.getElementById('tls-score-toggle-btn');
                if (!btn) return;
                var on = !!window.tlsScoreEnabled;
                btn.classList.toggle('on', on);
                btn.classList.toggle('off', !on);
                btn.innerHTML = 'T<br>' + (on ? '&#9650;' : '&#9660;');
            }
            tlsSyncScoreToggleBtn();
            window.updateTeamLeagueScoreBanner = function() {
                var banner = document.getElementById('team-league-score-banner');
                if (!banner) return;
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var baseUrl = (base ? base + '/' : '');
                fetch(baseUrl + '2026/scoreboard.csv?_t=' + Date.now(), { cache: 'no-store' })
                    .then(function(r) { return r.ok ? r.text() : ''; })
                    .then(function(text) {
                        var raw = (text || '').trim();
                        var rows = raw ? parseCSV(raw) : [];
                        if (!rows.length) { banner.innerHTML = ''; banner.style.display = 'none'; return; }
                        var r0 = rows[0];
                        var teamA = tlsCleanTeamName(cellStr(r0[2]));
                        var teamB = tlsCleanTeamName(cellStr(r0[6]));
                        var scoreA = cellStr(r0[4]).trim();
                        var scoreB = cellStr(r0[8]).trim();
                        if (!teamA || !teamB) { banner.innerHTML = ''; banner.style.display = 'none'; return; }
                        if (scoreA === '') scoreA = '0';
                        if (scoreB === '') scoreB = '0';
                        banner.innerHTML = '<span class="tls-team">' + escapeHtml(teamA) + '</span>' +
                            '<span class="tls-score">' + escapeHtml(scoreA) + '&ndash;' + escapeHtml(scoreB) + '</span>' +
                            '<span class="tls-team">' + escapeHtml(teamB) + '</span>';
                        var sbOverlay = document.getElementById('scoreboard-overlay');
                        var scoreboardActive = sbOverlay && sbOverlay.style.display === 'block';
                        banner.style.display = (!window.tlsScoreEnabled || scoreboardActive) ? 'none' : 'block';
                    })
                    .catch(function() {});
            };
            document.addEventListener('status:score-saved', function() { window.updateTeamLeagueScoreBanner(); });
            window.updateTeamLeagueScoreBanner();

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
                    showSummary: !!((document.getElementById('csb-show-summary') || {}).checked),
                    matches: matches
                };
            }

            window.csbSave = function() {
                var collected = csbCollect();
                _csbData.labelA = collected.labelA;
                _csbData.labelB = collected.labelB;
                _csbData.showSummary = !!collected.showSummary;
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
                        var cbSummary = document.getElementById('csb-show-summary');
                        if (inpA) inpA.value = _csbData.labelA || '';
                        if (inpB) inpB.value = _csbData.labelB || '';
                        if (cbSummary) cbSummary.checked = !!_csbData.showSummary;
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
                var showSummary = !!(data && data.showSummary);

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

                sb.push('<div class="scoreboard-table-wrap" style="margin-top:0.3rem;">');
                if (showSummary) {
                    /* ── Banner header: labelA | total score | labelB ── same columns as rows */
                    sb.push('<div style="' + ROW_BASE + ' padding:0.55rem 0 0.45rem; border-bottom:2px solid rgba(108,92,231,0.35); margin-bottom:0.2rem;">');
                    sb.push('<div style="' + NUM_S + '"></div>');
                    sb.push('<div style="' + NAMEA_S + ' font-family:\'Exo 2\',sans-serif; font-size:2.2rem; font-weight:700; color:#e0e0e0; overflow:visible; text-overflow:clip; padding-left:0.4rem;">' + escapeHtml(labelA) + '</div>');
                    sb.push('<div style="' + SCORE_S + '"><span class="scoreboard-score-main">' + totalA + ' &ndash; ' + totalB + '</span></div>');
                    sb.push('<div style="' + NAMEB_S + ' font-family:\'Exo 2\',sans-serif; font-size:2.2rem; font-weight:700; color:#e0e0e0; flex:1.6; overflow:visible; text-overflow:clip; padding-right:0.4rem;">' + escapeHtml(labelB) + '</div>');
                    sb.push('</div>');

                    /* ── Column header row ── */
                    sb.push('<div style="' + ROW_BASE + ' background:rgba(108,92,231,0.25); border-bottom:1px solid rgba(255,255,255,0.1); padding:0.32rem 0;">');
                    sb.push('<div style="' + NUM_S + '"></div>');
                    sb.push('<div style="' + NAMEA_S + ' font-weight:600; font-size:1.15rem; color:#fff;">' + escapeHtml(labelA) + '</div>');
                    sb.push('<div style="' + SCORE_S + ' color:#a29bfe; font-size:0.9rem; font-weight:500; letter-spacing:0.04em;">Score</div>');
                    sb.push('<div style="' + NAMEB_S + ' font-weight:600; font-size:1.15rem; color:#fff;">' + escapeHtml(labelB) + '</div>');
                    if (hasDesc) sb.push('<div style="' + DESC_S + ' color:#a29bfe; font-size:0.9rem; font-weight:500;">Map / Desc</div>');
                    sb.push('</div>');
                }

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

            /* ===== Player Scoreboard (movable graphic: players, score, Bo, color, race, team) ===== */
            var PSB_COLORS = [
                { id: 'default',    label: 'Default',     hex: '#3f8f3f' },
                { id: 'white',      label: 'White',       hex: '#f0f0f0' },
                { id: 'red',        label: 'Red',         hex: '#c0282d' },
                { id: 'blue',       label: 'Blue',        hex: '#2a4fd6' },
                { id: 'teal',       label: 'Teal',        hex: '#1f8a8a' },
                { id: 'purple',     label: 'Purple',      hex: '#7d3fb5' },
                { id: 'yellow',     label: 'Yellow',      hex: '#e8d33a' },
                { id: 'orange',     label: 'Orange',      hex: '#e8852a' },
                { id: 'green',      label: 'Green',       hex: '#3fae3f' },
                { id: 'lightpink',  label: 'Light Pink',  hex: '#e0a8e0' },
                { id: 'violet',     label: 'Violet',      hex: '#8a7fb5' },
                { id: 'lightgrey',  label: 'Light Grey',  hex: '#aab2ba' },
                { id: 'darkgreen',  label: 'Dark Green',  hex: '#2e6b2e' },
                { id: 'brown',      label: 'Brown',       hex: '#7a5230' },
                { id: 'lightgreen', label: 'Light Green', hex: '#7be07b' },
                { id: 'darkgrey',   label: 'Dark Grey',   hex: '#4a4a4a' },
                { id: 'pink',       label: 'Pink',        hex: '#e84fb5' }
            ];
            var PSB_RACE_ICON = {
                Z: 'images/races/zerg.png',
                T: 'images/races/terran.png',
                P: 'images/races/protoss.png',
                R: 'images/races/dice.svg'
            };
            function psbRaceIconHtml(code) {
                var src = PSB_RACE_ICON[code];
                if (!src) return '';
                var v = window.ASSET_VERSION ? ('?v=' + window.ASSET_VERSION) : '';
                return '<img src="' + src + v + '" alt="" draggable="false">';
            }
            var PSB_DEFAULT_TEAMS = [
                { name: 'PulledTheBoys', acr: 'PTB' },
                { name: 'Angry Space Hares', acr: 'ASH' },
                { name: 'Special Tactics', acr: 'ST' },
                { name: 'PSIOP Gaming', acr: 'POG' }
            ];
            var PSB_DEFAULT_POS = { left: 24, top: 24, width: 320, height: 92 };

            var psbData = null;
            var psbEditMode = false;

            function psbDefaultData() {
                return {
                    show: false,
                    bestOf: 1,
                    pos: Object.assign({}, PSB_DEFAULT_POS),
                    players: [
                        { name: '', score: 0, color: 'purple', team: '', race: '' },
                        { name: '', score: 0, color: 'blue', team: '', race: '' }
                    ],
                    teams: PSB_DEFAULT_TEAMS.map(function(t) { return { name: t.name, acr: t.acr }; })
                };
            }

            function psbColorHex(id) {
                for (var i = 0; i < PSB_COLORS.length; i++) { if (PSB_COLORS[i].id === id) return PSB_COLORS[i].hex; }
                return PSB_COLORS[0].hex;
            }
            /** Shade a hex color: pct < 0 darken, pct > 0 lighten (range -1..1). */
            function psbShade(hex, pct) {
                var h = String(hex).replace('#', '');
                if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
                var t = pct < 0 ? 0 : 255;
                var p = Math.abs(pct);
                r = Math.round((t - r) * p) + r;
                g = Math.round((t - g) * p) + g;
                b = Math.round((t - b) * p) + b;
                return 'rgb(' + r + ',' + g + ',' + b + ')';
            }
            function psbRaceMeta(code) {
                switch (code) {
                    case 'Z': return { accent: '#9b59b6' };
                    case 'T': return { accent: '#3a7bd5' };
                    case 'P': return { accent: '#e0b13a' };
                    default:  return { accent: '#888888' };
                }
            }
            /** Resolve a player's race: explicit override, else rankings lookup, else ''. */
            function psbResolveRace(player) {
                if (player && player.race) return player.race;
                if (player && player.name && typeof window.getRankingForPlayer === 'function') {
                    var rk = window.getRankingForPlayer(player.name);
                    if (rk && rk.race) return String(rk.race).toUpperCase().charAt(0);
                }
                return '';
            }
            /** Normalize a team name for tolerant matching (case/space/punctuation-insensitive). */
            function psbNormTeam(s) {
                return String(s == null ? '' : s).toLowerCase().replace(/[^a-z0-9]/g, '');
            }
            function psbTeamAcr(teamName) {
                if (!teamName) return '';
                var teams = (psbData && Array.isArray(psbData.teams)) ? psbData.teams : [];
                var norm = psbNormTeam(teamName);
                for (var i = 0; i < teams.length; i++) {
                    if (psbNormTeam(teams[i].name) === norm) return teams[i].acr || '';
                }
                return '';
            }

            /* Player -> team roster, derived from the current FSL match in 2026/scoreboard.csv.
               There is no global team field in rankings, so we map the two competing teams'
               rosters from the live scoreboard (same source as the team-league banner). */
            var psbTeamRoster = {};
            function psbStripPlayerCell(s) {
                return String(s == null ? '' : s).replace(/^\s*\([A-Za-z]\)\s*/, '').replace(/\[\d+\]/g, '').trim();
            }
            function psbLoadTeamRoster() {
                if (typeof parseCSV !== 'function' || typeof cellStr !== 'function') return;
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var baseUrl = (base ? base + '/' : '');
                fetch(baseUrl + '2026/scoreboard.csv?_t=' + Date.now(), { cache: 'no-store' })
                    .then(function(r) { return r.ok ? r.text() : ''; })
                    .then(function(text) {
                        var raw = (text || '').trim();
                        var rows = raw ? parseCSV(raw) : [];
                        var map = {};
                        if (rows.length) {
                            var r0 = rows[0];
                            var teamA = tlsCleanTeamName(cellStr(r0[2]));
                            var teamB = tlsCleanTeamName(cellStr(r0[6]));
                            for (var i = 1; i < rows.length; i++) {
                                var row = rows[i];
                                [2, 3].forEach(function(c) { var nm = psbStripPlayerCell(cellStr(row[c])); if (nm && teamA) map[nm.toLowerCase()] = teamA; });
                                [6, 7].forEach(function(c) { var nm = psbStripPlayerCell(cellStr(row[c])); if (nm && teamB) map[nm.toLowerCase()] = teamB; });
                            }
                        }
                        psbTeamRoster = map;
                        if (typeof psbRenderPanel === 'function') psbRenderPanel();
                    })
                    .catch(function() {});
            }
            window.psbLoadTeamRoster = psbLoadTeamRoster;

            /** Resolve a player's team: explicit override (settings dropdown), else roster lookup by name. */
            function psbResolveTeamName(player) {
                if (player && player.team) return player.team;
                if (player && player.name && psbTeamRoster) {
                    var t = psbTeamRoster[String(player.name).trim().toLowerCase()];
                    if (t) return t;
                }
                return '';
            }
            /** Show a real value (team name or FA); skip only when there's nothing (empty / Null). */
            function psbIsRealTeamAcr(acr) {
                if (!acr) return false;
                var u = String(acr).trim().toUpperCase();
                return u !== '' && u !== 'NULL';
            }
            /** Resolve the team ACRONYM to display after the name.
               Priority: explicit override -> rankings.team (team name or FA) -> scoreboard roster.
               Returns '' only when there's no team value, so no empty tag is shown. */
            function psbResolveTeamAcr(player) {
                var acr = '';
                if (player && player.team) {
                    acr = psbTeamAcr(player.team);
                } else if (player && player.name && typeof window.getRankingForPlayer === 'function') {
                    var rk = window.getRankingForPlayer(player.name);
                    if (rk && rk.team) acr = String(rk.team).trim();
                }
                return psbIsRealTeamAcr(acr) ? acr : '';
            }
            /** Resolve a player's rank/group from rankings (same source as race). */
            function psbResolveRank(player) {
                if (player && player.name && typeof window.getRankingForPlayer === 'function') {
                    var rk = window.getRankingForPlayer(player.name);
                    if (rk) return { rank: rk.rank, group: rk.group };
                }
                return null;
            }

            function psbRowHtml(player) {
                var hex = psbColorHex(player.color);
                var grad = 'linear-gradient(100deg, ' + psbShade(hex, -0.45) + ' 0%, ' + hex + ' 60%, ' + psbShade(hex, 0.12) + ' 100%)';
                var race = psbResolveRace(player);
                var raceIcon = psbRaceIconHtml(race);
                var acr = psbResolveTeamAcr(player);
                var rk = psbResolveRank(player);
                var rankNum = (rk && rk.rank != null && String(rk.rank) !== '') ? String(rk.rank) : '';
                var groupNum = (rk && rk.group != null && String(rk.group) !== '') ? String(rk.group) : '';
                var nameHtml = escapeHtml(player.name || '');
                if (acr) nameHtml += ' <span class="psb-team">(' + escapeHtml(acr) + ')</span>';
                var rgHtml = '';
                if (rankNum) {
                    rgHtml = '<span class="psb-num">#' + escapeHtml(rankNum) + '</span>';
                    if (groupNum) rgHtml += '<span class="psb-grp">G' + escapeHtml(groupNum) + '</span>';
                }
                var html = '<div class="psb-row" style="background:' + grad + ';">';
                html += '<div class="psb-score">' + (parseInt(player.score, 10) || 0) + '</div>';
                html += '<div class="psb-race">' + raceIcon + '</div>';
                if (rgHtml) html += '<div class="psb-rg">' + rgHtml + '</div>';
                html += '<div class="psb-name">' + nameHtml + '</div>';
                if (raceIcon) html += '<div class="psb-watermark">' + raceIcon + '</div>';
                html += '</div>';
                return html;
            }

            function psbApplyPosition(pos) {
                var panel = document.getElementById('player-scoreboard-panel');
                if (!panel || !pos) return;
                panel.style.left = (pos.left || 0) + 'px';
                panel.style.top = (pos.top || 0) + 'px';
                panel.style.width = (pos.width || PSB_DEFAULT_POS.width) + 'px';
                panel.style.height = (pos.height || PSB_DEFAULT_POS.height) + 'px';
                panel.style.fontSize = Math.max(8, Math.round((pos.height || PSB_DEFAULT_POS.height) * 0.30)) + 'px';
            }

            function psbRenderPanel() {
                var overlay = document.getElementById('player-scoreboard-overlay');
                var panel = document.getElementById('player-scoreboard-panel');
                if (!overlay || !panel || !psbData) return;
                var pa = psbData.players[0] || {};
                var pb = psbData.players[1] || {};
                var bo = parseInt(psbData.bestOf, 10) || 1;
                panel.innerHTML =
                    '<div class="psb-inner">' +
                        '<div class="psb-bo">Bo' + bo + '</div>' +
                        '<div class="psb-rows">' + psbRowHtml(pa) + psbRowHtml(pb) + '</div>' +
                    '</div>';
                psbApplyPosition(psbData.pos || PSB_DEFAULT_POS);
                psbSyncQuickToggleBtn();
                psbUpdateVisibility();
                if (typeof psbUpdateAutoTeamLabels === 'function') psbUpdateAutoTeamLabels();
            }
            window.psbRenderPanel = psbRenderPanel;

            /* True only when SC2 is the active scene AND no other scene button is active.
               Other scenes (Schedule, Bracket, ASH, POG, scoreboards, matchup, videos...)
               go through clearExclusiveScenes() which does NOT clear the SC2 button, so we
               must also confirm none of the competing scene buttons are active. */
            function psbIsSc2ActiveScene() {
                var sc2Btn = document.getElementById('scene-btn-sc2');
                if (!sc2Btn || !sc2Btn.classList.contains('active')) return false;
                var actives = document.querySelectorAll('#scenes-section [id^="scene-btn-"].active');
                for (var i = 0; i < actives.length; i++) {
                    var id = actives[i].id;
                    if (id === 'scene-btn-sc2' || id === 'scene-btn-sc2-quick') continue;
                    return false;
                }
                return true;
            }
            window.psbIsSc2ActiveScene = psbIsSc2ActiveScene;

            /* Lightweight show/hide only (no innerHTML rebuild). Safe to call from the
               scenes MutationObserver on every scene-button class change. */
            function psbUpdateVisibility() {
                var overlay = document.getElementById('player-scoreboard-overlay');
                var panel = document.getElementById('player-scoreboard-panel');
                if (!overlay || !panel || !psbData) return;
                var visible = psbEditMode || (psbData.show && psbIsSc2ActiveScene());
                overlay.style.display = visible ? 'block' : 'none';
                panel.style.display = visible ? 'block' : 'none';
            }
            window.psbUpdateVisibility = psbUpdateVisibility;

            /* ---- Settings UI ---- */
            function psbPopulateColorSelects() {
                document.querySelectorAll('#player-scoreboard-settings-section .psb-color').forEach(function(sel) {
                    var current = sel.value;
                    sel.innerHTML = '';
                    PSB_COLORS.forEach(function(c) {
                        var o = document.createElement('option');
                        o.value = c.id; o.textContent = c.label;
                        sel.appendChild(o);
                    });
                    if (current) sel.value = current;
                });
            }
            function psbPopulateTeamSelects() {
                var teams = (psbData && Array.isArray(psbData.teams)) ? psbData.teams : [];
                document.querySelectorAll('#player-scoreboard-settings-section .psb-team').forEach(function(sel) {
                    var current = sel.value;
                    sel.innerHTML = '';
                    var none = document.createElement('option');
                    none.value = ''; none.textContent = 'Auto'; none.className = 'psb-team-auto-opt';
                    sel.appendChild(none);
                    teams.forEach(function(t) {
                        if (!t.name) return;
                        var o = document.createElement('option');
                        o.value = t.name; o.textContent = t.name + (t.acr ? ' (' + t.acr + ')' : '');
                        sel.appendChild(o);
                    });
                    sel.value = current;
                });
                psbUpdateAutoTeamLabels();
            }
            /** Show what the "Auto" team option resolves to for each player's current name. */
            function psbUpdateAutoTeamLabels() {
                document.querySelectorAll('#player-scoreboard-settings-section .psb-player-edit').forEach(function(box) {
                    var sel = box.querySelector('.psb-team');
                    var nameEl = box.querySelector('.psb-name');
                    if (!sel || !sel.options.length) return;
                    var nm = nameEl ? nameEl.value : '';
                    var acr = nm ? psbResolveTeamAcr({ name: nm, team: '' }) : '';
                    sel.options[0].textContent = acr ? ('Auto (' + acr + ')') : 'Auto';
                });
            }
            function psbRenderTeamRows() {
                var wrap = document.getElementById('psb-teams-rows');
                if (!wrap) return;
                wrap.innerHTML = '';
                var teams = (psbData && Array.isArray(psbData.teams)) ? psbData.teams : [];
                teams.forEach(function(t, i) {
                    var row = document.createElement('div');
                    row.style.cssText = 'display:flex; gap:4px; align-items:center;';
                    row.innerHTML =
                        '<input type="text" class="psb-team-name" data-i="' + i + '" value="' + escapeHtml(t.name || '') + '" placeholder="Team name" style="flex:1; min-width:0; background:#1a1a1a; color:#eee; border:1px solid #444; border-radius:3px; padding:2px 5px; font-size:11px;">' +
                        '<input type="text" class="psb-team-acr" data-i="' + i + '" value="' + escapeHtml(t.acr || '') + '" placeholder="ACR" style="width:60px; background:#1a1a1a; color:#eee; border:1px solid #444; border-radius:3px; padding:2px 5px; font-size:11px;">' +
                        '<button type="button" class="psb-team-del" data-i="' + i + '" style="font-size:11px; padding:1px 7px;">&times;</button>';
                    wrap.appendChild(row);
                });
                wrap.querySelectorAll('.psb-team-name, .psb-team-acr').forEach(function(inp) {
                    inp.addEventListener('input', function() {
                        var i = parseInt(inp.getAttribute('data-i'), 10);
                        if (!psbData.teams[i]) return;
                        if (inp.classList.contains('psb-team-name')) psbData.teams[i].name = inp.value;
                        else psbData.teams[i].acr = inp.value;
                        psbPopulateTeamSelects();
                        psbRenderPanel();
                    });
                });
                wrap.querySelectorAll('.psb-team-del').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var i = parseInt(btn.getAttribute('data-i'), 10);
                        psbData.teams.splice(i, 1);
                        psbRenderTeamRows();
                        psbPopulateTeamSelects();
                        psbRenderPanel();
                    });
                });
            }

            /** Push the current settings inputs into psbData (excludes pos, set during edit/move). */
            function psbCollectSettings() {
                var showEl = document.getElementById('psb-show');
                var boEl = document.getElementById('psb-best-of');
                if (showEl) psbData.show = showEl.checked;
                if (boEl) psbData.bestOf = Math.max(1, parseInt(boEl.value, 10) || 1);
                document.querySelectorAll('#player-scoreboard-settings-section .psb-player-edit').forEach(function(box) {
                    var idx = parseInt(box.getAttribute('data-idx'), 10) || 0;
                    if (!psbData.players[idx]) psbData.players[idx] = { name: '', score: 0, color: 'default', team: '', race: '' };
                    var p = psbData.players[idx];
                    var nameEl = box.querySelector('.psb-name');
                    var scoreEl = box.querySelector('.psb-score');
                    var colorEl = box.querySelector('.psb-color');
                    var teamEl = box.querySelector('.psb-team');
                    var raceEl = box.querySelector('.psb-race');
                    if (nameEl) p.name = nameEl.value;
                    if (scoreEl) p.score = parseInt(scoreEl.value, 10) || 0;
                    if (colorEl) p.color = colorEl.value;
                    if (teamEl) p.team = teamEl.value;
                    if (raceEl) p.race = raceEl.value;
                });
            }

            /** Fill the settings inputs from psbData. */
            function psbFillSettings() {
                var showEl = document.getElementById('psb-show');
                var boEl = document.getElementById('psb-best-of');
                if (showEl) showEl.checked = !!psbData.show;
                if (boEl) boEl.value = parseInt(psbData.bestOf, 10) || 1;
                psbPopulateColorSelects();
                psbPopulateTeamSelects();
                document.querySelectorAll('#player-scoreboard-settings-section .psb-player-edit').forEach(function(box) {
                    var idx = parseInt(box.getAttribute('data-idx'), 10) || 0;
                    var p = psbData.players[idx] || { name: '', score: 0, color: 'default', team: '', race: '' };
                    var nameEl = box.querySelector('.psb-name');
                    var scoreEl = box.querySelector('.psb-score');
                    var colorEl = box.querySelector('.psb-color');
                    var teamEl = box.querySelector('.psb-team');
                    var raceEl = box.querySelector('.psb-race');
                    if (nameEl) nameEl.value = p.name || '';
                    if (scoreEl) scoreEl.value = parseInt(p.score, 10) || 0;
                    if (colorEl) colorEl.value = p.color || 'default';
                    if (teamEl) teamEl.value = p.team || '';
                    if (raceEl) raceEl.value = p.race || '';
                });
                psbRenderTeamRows();
            }

            /* ---- Player-name autocomplete (same player list as Player Intros) ---- */
            var psbAcDropdown = null;
            function psbClearAutocomplete() {
                if (psbAcDropdown && psbAcDropdown.parentNode) psbAcDropdown.parentNode.removeChild(psbAcDropdown);
                psbAcDropdown = null;
            }
            function psbShowAutocomplete(input, players) {
                psbClearAutocomplete();
                var rect = input.getBoundingClientRect();
                var ul = document.createElement('ul');
                ul.className = 'suggestion-list psb-autocomplete';
                ul.style.cssText = 'position:fixed; top:' + (rect.bottom + window.scrollY) + 'px; left:' + (rect.left + window.scrollX) + 'px; width:' + Math.max(160, rect.width) + 'px; background:#fff; color:#111; border:1px solid #888; z-index:100020; list-style:none; padding:0; margin:0; max-height:220px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,0.4);';
                players.slice(0, 50).forEach(function(p) {
                    var li = document.createElement('li');
                    li.textContent = p[0];
                    li.style.cssText = 'padding:4px 8px; cursor:pointer; font-size:12px;';
                    li.addEventListener('mousedown', function(e) { e.preventDefault(); });
                    li.addEventListener('mouseenter', function() { li.style.background = '#e6e6e6'; });
                    li.addEventListener('mouseleave', function() { li.style.background = '#fff'; });
                    li.addEventListener('click', function() {
                        input.value = p[0];
                        psbClearAutocomplete();
                        psbCollectSettings();
                        psbRenderPanel();
                    });
                    ul.appendChild(li);
                });
                document.body.appendChild(ul);
                psbAcDropdown = ul;
            }
            function psbAttachAutocomplete(input) {
                if (!input || input._psbAcBound) return;
                input._psbAcBound = true;
                input.addEventListener('input', function() {
                    var v = input.value.trim().toLowerCase();
                    var list = window.STREAM_PLAYER_LIST || [];
                    if (v.length < 3 || !list.length) { psbClearAutocomplete(); return; }
                    var matches = list.filter(function(p) { return p[0] && p[0].toLowerCase().indexOf(v) !== -1; });
                    if (matches.length) psbShowAutocomplete(input, matches); else psbClearAutocomplete();
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab' && psbAcDropdown) {
                        var items = psbAcDropdown.querySelectorAll('li');
                        if (items.length === 1) { input.value = items[0].textContent; psbClearAutocomplete(); psbCollectSettings(); psbRenderPanel(); }
                    }
                });
                input.addEventListener('blur', function() { setTimeout(psbClearAutocomplete, 150); });
            }

            var psbSettingsBound = false;
            function psbBindSettings() {
                if (psbSettingsBound) return;
                psbSettingsBound = true;
                var section = document.getElementById('player-scoreboard-settings-section');
                if (!section) return;
                section.querySelectorAll('.psb-player-edit .psb-name').forEach(psbAttachAutocomplete);
                section.addEventListener('input', function(e) {
                    if (e.target.closest('#psb-teams-rows')) return; /* handled per-row */
                    if (e.target.id === 'psb-show' || e.target.id === 'psb-best-of' || e.target.closest('.psb-player-edit')) {
                        psbCollectSettings();
                        psbRenderPanel();
                    }
                });
                section.addEventListener('change', function(e) {
                    if (e.target.classList && (e.target.classList.contains('psb-color') || e.target.classList.contains('psb-team') || e.target.classList.contains('psb-race') || e.target.id === 'psb-show')) {
                        psbCollectSettings();
                        psbRenderPanel();
                    }
                });
                var addBtn = document.getElementById('psb-team-add-btn');
                if (addBtn) addBtn.addEventListener('click', function() {
                    psbData.teams.push({ name: '', acr: '' });
                    psbRenderTeamRows();
                    psbPopulateTeamSelects();
                });
            }

            window.togglePlayerScoreboardSettings = function(btn) {
                var el = document.getElementById('player-scoreboard-settings-section');
                if (!el) return;
                if (el.style.display === 'none' || !el.style.display) {
                    el.style.display = 'block';
                    psbBindSettings();
                    psbFillSettings();
                } else {
                    el.style.display = 'none';
                }
                if (btn) btn.classList.toggle('open', el.style.display === 'block');
            };

            function psbSyncQuickToggleBtn() {
                var btn = document.getElementById('psb-quick-toggle-btn');
                if (!btn || !psbData) return;
                var on = !!psbData.show;
                btn.classList.toggle('on', on);
                btn.classList.toggle('off', !on);
                btn.innerHTML = 'P<br>' + (on ? '&#9650;' : '&#9660;');
            }
            function psbSaveQuiet() {
                if (!psbData) return;
                fetch('save_player_scoreboard.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(psbData)
                }).catch(function() {});
            }
            window.psbToggleShow = function() {
                if (!psbData) return;
                psbData.show = !psbData.show;
                var cb = document.getElementById('psb-show');
                if (cb) cb.checked = psbData.show;
                psbRenderPanel();
                psbSaveQuiet();
            };

            window.psbSwapPlayers = function() {
                if (!psbData) return;
                psbCollectSettings();
                var tmp = psbData.players[0];
                psbData.players[0] = psbData.players[1];
                psbData.players[1] = tmp;
                psbFillSettings();
                psbRenderPanel();
            };

            /* ---- Edit / move ---- */
            window.psbToggleEditMode = function() {
                var overlay = document.getElementById('player-scoreboard-overlay');
                var panel = document.getElementById('player-scoreboard-panel');
                var btn = document.getElementById('psb-editmove-btn');
                if (!overlay || !panel) return;
                if (psbEditMode) {
                    var off = panel.getBoundingClientRect();
                    var par = overlay.getBoundingClientRect();
                    psbData.pos = {
                        left: Math.round(off.left - par.left),
                        top: Math.round(off.top - par.top),
                        width: Math.round(off.width),
                        height: Math.round(off.height)
                    };
                    if ($(panel).data('ui-draggable')) $(panel).draggable('destroy');
                    if ($(panel).data('ui-resizable')) $(panel).resizable('destroy');
                    overlay.classList.remove('psb-edit-mode');
                    overlay.style.pointerEvents = 'none';
                    overlay.style.zIndex = '';
                    psbEditMode = false;
                    if (btn) { btn.textContent = 'Edit and Move'; btn.classList.remove('active'); }
                    psbRenderPanel();
                    if (window.reapplyLayerOrder) window.reapplyLayerOrder();
                    if (typeof psbSaveQuiet === 'function') psbSaveQuiet(); /* persist position for everyone */
                    return;
                }
                psbEditMode = true;
                overlay.style.display = 'block';
                panel.style.display = 'block';
                psbApplyPosition(psbData.pos || PSB_DEFAULT_POS);
                overlay.classList.add('psb-edit-mode');
                overlay.style.pointerEvents = 'none';
                overlay.style.zIndex = '100002';
                panel.style.pointerEvents = 'auto';
                $(panel).draggable({ containment: '#player-scoreboard-overlay', scroll: false, cursor: 'move' });
                $(panel).resizable({
                    containment: '#player-scoreboard-overlay', handles: 'all',
                    resize: function(e, ui) {
                        panel.style.fontSize = Math.max(8, Math.round(ui.size.height * 0.30)) + 'px';
                    }
                });
                if (btn) { btn.textContent = 'Save layout'; btn.classList.add('active'); }
            };

            window.psbResetPosition = function() {
                psbData.pos = Object.assign({}, PSB_DEFAULT_POS);
                if (psbEditMode) {
                    var panel = document.getElementById('player-scoreboard-panel');
                    if (panel && $(panel).data('ui-draggable')) { $(panel).draggable('destroy'); $(panel).resizable('destroy'); }
                    psbApplyPosition(psbData.pos);
                    var p2 = document.getElementById('player-scoreboard-panel');
                    $(p2).draggable({ containment: '#player-scoreboard-overlay', scroll: false, cursor: 'move' });
                    $(p2).resizable({ containment: '#player-scoreboard-overlay', handles: 'all', resize: function(e, ui) { p2.style.fontSize = Math.max(8, Math.round(ui.size.height * 0.30)) + 'px'; } });
                } else {
                    psbApplyPosition(psbData.pos);
                }
                if (typeof psbSaveQuiet === 'function') psbSaveQuiet(); /* persist reset for everyone */
            };

            /* ---- Persistence ---- */
            window.psbSave = function() {
                if (psbEditMode) window.psbToggleEditMode(); /* lock in position first */
                psbCollectSettings();
                var btn = document.getElementById('psb-save-btn');
                var status = document.getElementById('psb-save-status');
                if (btn) btn.disabled = true;
                if (status) status.textContent = 'Saving…';
                fetch('save_player_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(psbData)
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (status) status.textContent = (res && res.ok) ? 'Saved!' : 'Error saving.';
                    setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                }).catch(function() {
                    if (status) status.textContent = 'Network error.';
                    setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
                });
            };

            function psbNormalize(data) {
                var d = psbDefaultData();
                if (data && typeof data === 'object') {
                    if (typeof data.show === 'boolean') d.show = data.show;
                    if (data.bestOf != null) d.bestOf = Math.max(1, parseInt(data.bestOf, 10) || 1);
                    if (data.pos && typeof data.pos === 'object') d.pos = Object.assign({}, PSB_DEFAULT_POS, data.pos);
                    if (Array.isArray(data.players)) {
                        for (var i = 0; i < 2; i++) {
                            if (data.players[i]) d.players[i] = Object.assign(d.players[i], data.players[i]);
                        }
                    }
                    if (Array.isArray(data.teams) && data.teams.length) {
                        d.teams = data.teams.map(function(t) { return { name: (t && t.name) || '', acr: (t && t.acr) || '' }; });
                    }
                }
                return d;
            }

            window.psbLoad = function() {
                return fetch('save_player_scoreboard.php?_t=' + Date.now())
                    .then(function(r) { return r.json(); })
                    .then(function(data) { psbData = psbNormalize(data); })
                    .catch(function() { psbData = psbDefaultData(); })
                    .then(function() {
                        psbRenderPanel();
                        var section = document.getElementById('player-scoreboard-settings-section');
                        if (section && section.style.display === 'block') { psbBindSettings(); psbFillSettings(); }
                    });
            };

            (function psbInit() {
                psbData = psbDefaultData();
                window.psbLoad();
                psbLoadTeamRoster();
                /* Reload roster when the match scoreboard changes (teams/players may differ). */
                document.addEventListener('status:score-saved', function() { psbLoadTeamRoster(); });
                /* Robust visibility: re-check whenever ANY scene button's active state changes
                   (SC2 activate/deactivate, or switching to Schedule/Bracket/ASH/POG/scoreboards/
                   matchup/videos, etc.), regardless of which code path toggled it. The panel must
                   show ONLY when SC2 is the active scene. */
                var scenesSection = document.getElementById('scenes-section');
                if (scenesSection && typeof MutationObserver !== 'undefined') {
                    new MutationObserver(function() { psbUpdateVisibility(); })
                        .observe(scenesSection, { attributes: true, attributeFilter: ['class'], subtree: true });
                }
                /* Re-render once rankings are available so Auto race resolves. */
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() { if (psbData) psbRenderPanel(); }, 1500);
                });
            })();

            /* ---- Popup editor: edit the player scoreboard data from a separate window ---- */
            var _psbEditorWin = null;
            window.psbOpenEditorWindow = function() {
                if (_psbEditorWin && !_psbEditorWin.closed) { _psbEditorWin.focus(); return; }
                var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
                var url = (base ? base + '/' : './') + 'player_scoreboard_editor.php';
                _psbEditorWin = window.open(url, 'playerScoreboardEditor', 'width=470,height=600,resizable=yes,scrollbars=yes');
            };
            /* Right-click the panel on the overlay to open the editor (coordinate hit-test,
               since the overlay uses pointer-events:none and won't be the event target). */
            document.addEventListener('contextmenu', function(e) {
                var overlay = document.getElementById('player-scoreboard-overlay');
                var panel = document.getElementById('player-scoreboard-panel');
                if (!overlay || !panel) return;
                if (overlay.style.display === 'none' || panel.style.display === 'none') return;
                var r = panel.getBoundingClientRect();
                if (e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) {
                    e.preventDefault();
                    window.psbOpenEditorWindow();
                }
            });
            /* Live-update the overlay when the popup editor saves. */
            window.addEventListener('message', function(e) {
                if (e.origin !== window.location.origin) return;
                if (!e.data || e.data.type !== 'psb-editor-saved') return;
                if (typeof window.psbLoad === 'function') window.psbLoad();
            });
            /* ===== End Player Scoreboard ===== */

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
                    videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                if (typeof window.psbUpdateVisibility === 'function') window.psbUpdateVisibility();
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
                iframe.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                var mediaPath = videoPlayerMediaPath(file);
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
                    var url = (file.indexOf('.html') !== -1) ? ('2026/' + file) : ('2026/video_player.php?v=' + encodeURIComponent(mediaPath) + '&_t=' + Date.now());
                    if (useFront && file.indexOf('.html') === -1) url += '&front=true';
                    iframe.src = url;
                    overlay.style.zIndex = useFront ? '99999' : getVideoOverlayDefaultZIndex();
                    overlay.style.display = 'block';
                    btn.classList.add('active');
                    var teamAudio = VIDEO_OVERLAY_AUDIO[sceneId];
                    if (teamAudio) {
                        var ap = document.querySelector('#audio-player');
                        if (ap) {
                            var teamSrc = typeof window.resolveProductionUrl === 'function' ? window.resolveProductionUrl('production_files/audio/' + teamAudio) : ('production_files/audio/' + teamAudio);
                            if (typeof window.applyAnonymousCORSIfNeeded === 'function') {
                                window.applyAnonymousCORSIfNeeded(ap, teamSrc);
                            }
                            ap.src = teamSrc;
                            var vol = document.getElementById('volume-slider');
                            ap.volume = vol ? vol.value / 100 : 1;
                            ap.load();
                            ap.play();
                        }
                    }
                    if (typeof window.mxOnSceneChange === 'function') window.mxOnSceneChange(sceneId);
                }
                if (useFront) {
                    var skipHead = !!(typeof window.STREAM_SCENE_ASSETS_BASE === 'string' && window.STREAM_SCENE_ASSETS_BASE);
                    if (!skipHead && typeof window.getProductionFilesMode === 'function' && window.getProductionFilesMode() === 'remote') {
                        skipHead = true;
                    }
                    if (skipHead) {
                        doShowVideoOverlay();
                    } else {
                        fetch('2026/video_player.php?stream=1&v=' + encodeURIComponent(mediaPath), { method: 'HEAD', cache: 'no-store' })
                            .then(function(r) {
                                if (!r.ok) { setSceneVideoError('error: ' + mediaPath + ' not found'); return; }
                                doShowVideoOverlay();
                            })
                            .catch(function() { setSceneVideoError('error: ' + mediaPath + ' not found'); });
                    }
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
                    if (typeof window.tlsShowTeamLeagueScoreBannerIfReady === 'function') window.tlsShowTeamLeagueScoreBannerIfReady();
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
                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                    if (typeof window.tlsHideTeamLeagueScoreBanner === 'function') window.tlsHideTeamLeagueScoreBanner();
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
                    if (videoIframeCsb) videoIframeCsb.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                        /* SC2 on: 1) effect 1, 2) stinger + effect 2 simultaneously, 3) activate */
                        var sc2Fx = typeof getSc2ButtonEffects === 'function'
                            ? getSc2ButtonEffects()
                            : { effect1: 'Random Music', effect2: 'FSL intro' };
                        if (sc2Fx.effect1 && typeof window.playPlayerIntroByName === 'function') {
                            window.playPlayerIntroByName(sc2Fx.effect1);
                        }
                        if (sc2Fx.effect2 && typeof window.playPlayerIntroByName === 'function') {
                            window.playPlayerIntroByName(sc2Fx.effect2);
                        }
                        if (typeof window.mxSc2AnimatedIntroArmIntroVideoListener === 'function') {
                            window.mxSc2AnimatedIntroArmIntroVideoListener();
                        }
                        var sc2IntroMxSession = (typeof window.mxSc2IntroPeekSession === 'function')
                            ? window.mxSc2IntroPeekSession() : null;
                        playTransitionVideo({
                            videoSrc: '2026/2026_FSL_logo_reveal_GS_fast.mp4',
                            fadeInMs: 500,
                            fadeOutMs: 500,
                            onComplete: function() {
                                activateSc2Scene();
                                if (typeof window.mxSc2AnimatedIntroMarkTransitionDone === 'function') {
                                    window.mxSc2AnimatedIntroMarkTransitionDone(sc2IntroMxSession);
                                }
                            }
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
                    if (typeof window.tlsShowTeamLeagueScoreBannerIfReady === 'function') window.tlsShowTeamLeagueScoreBannerIfReady();
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
                                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                    if (videoIframe) videoIframe.src = '2026/video_player.php?v=' + encodeURIComponent(videoPlayerMediaPath(VIDEO_OVERLAY_FILES['all-vdo'])) + '&_t=' + Date.now();
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
                    if (typeof window.tlsShowTeamLeagueScoreBannerIfReady === 'function') window.tlsShowTeamLeagueScoreBannerIfReady();
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
                    if (typeof window.tlsShowTeamLeagueScoreBannerIfReady === 'function') window.tlsShowTeamLeagueScoreBannerIfReady();
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
                const baseUrl = (typeof window.STREAM_FSL_SPIDER_MATCHUP_URL === 'string' && window.STREAM_FSL_SPIDER_MATCHUP_URL.trim())
                    ? window.STREAM_FSL_SPIDER_MATCHUP_URL.trim()
                    : ((window.spiderChartBaseUrl || '').replace(/view_spider_chart_player\.php$/i, 'view_spider_chart_player_matchup.php') || 'https://psistorm.com/fsl/view_spider_chart_player_matchup.php');
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

            // ---- Matchup scene ----
            var matchupArmed = false;
            var matchupPicks = []; // up to 2 .player-name-input elements

            function toggleMatchup() {
                var overlay = document.getElementById('matchup-overlay');
                var isOpen = overlay && overlay.style.display === 'flex';

                if (isOpen) {
                    closeMatchupOverlay();
                    return;
                }

                if (!matchupArmed) {
                    matchupArmed = true;
                    matchupPicks = [];
                    var btn = document.getElementById('scene-btn-matchup');
                    if (btn) btn.classList.add('armed');
                    var intros = document.getElementById('player-intros-section');
                    if (intros) intros.classList.add('matchup-picking-mode');
                    setSceneVideoError('Pick Player A field, then Player B field, then press Matchup again.');
                    return;
                }

                // Already armed — check picks
                if (matchupPicks.length === 0) {
                    disarmMatchup();
                    return;
                }
                if (matchupPicks.length === 1) {
                    setSceneVideoError('Need a second player field — click another name input, then press Matchup.');
                    return;
                }
                // Two picks — validate and open
                var nameA = matchupPicks[0].value.trim();
                var nameB = matchupPicks[1].value.trim();
                if (!nameA || !nameB) {
                    setSceneVideoError('Both player name fields must be filled in before opening Matchup.');
                    return;
                }
                openMatchupOverlay(nameA, nameB);
            }

            function openMatchupOverlay(nameA, nameB) {
                var overlay = document.getElementById('matchup-overlay');
                var btn = document.getElementById('scene-btn-matchup');
                if (!overlay) return;
                clearExclusiveScenes();
                var boxA = overlay.querySelector('.matchup-col-a .external-chart-player-label');
                var boxB = overlay.querySelector('.matchup-col-b .external-chart-player-label');
                if (typeof window.setPlayerLabelContent === 'function') {
                    window.setPlayerLabelContent(boxA, nameA);
                    window.setPlayerLabelContent(boxB, nameB);
                }
                // Clear any stale chart content before loading
                var slotA = overlay.querySelector('.matchup-col-a .matchup-chart-slot');
                var slotB = overlay.querySelector('.matchup-col-b .matchup-chart-slot');
                overlay.style.display = 'flex';
                if (btn) { btn.classList.add('active'); btn.classList.remove('armed'); }
                matchupArmed = false;
                matchupPicks = [];
                refreshMatchupPickHighlights();
                setSceneVideoError('');
                // Load spider charts asynchronously (S → A → B fallback)
                loadMatchupChart(slotA, nameA);
                loadMatchupChart(slotB, nameB);
            }

            function closeMatchupOverlay() {
                var overlay = document.getElementById('matchup-overlay');
                var btn = document.getElementById('scene-btn-matchup');
                if (overlay) {
                    // Clear chart iframes to stop network requests
                    overlay.querySelectorAll('.matchup-chart-slot').forEach(function(s) { s.innerHTML = ''; });
                    overlay.style.display = 'none';
                }
                if (btn) { btn.classList.remove('active'); btn.classList.remove('armed'); }
                disarmMatchup();
            }

            function loadMatchupChart(slot, name) {
                if (!slot) return;
                slot.innerHTML = '<div class="matchup-chart-loading">Loading\u2026</div>';
                tryMatchupDivision(slot, name, 0);
            }

            function tryMatchupDivision(slot, name, divIdx) {
                var divisions = ['S', 'A', 'B'];
                if (divIdx >= divisions.length) {
                    slot.innerHTML = '<div class="matchup-chart-none">No chart found</div>';
                    return;
                }
                var division = divisions[divIdx];
                var chartBase = (typeof window.STREAM_FSL_SPIDER_MATCHUP_URL === 'string' && window.STREAM_FSL_SPIDER_MATCHUP_URL.trim())
                    ? window.STREAM_FSL_SPIDER_MATCHUP_URL.trim()
                    : 'https://psistorm.com/fsl/view_spider_chart_player_matchup.php';
                var chartUrl = chartBase
                    + '?name=' + encodeURIComponent(name)
                    + '&division=' + encodeURIComponent(division);
                var proxyBase = (typeof window.STREAM_FSL_PROXY_MATCHUP_URL === 'string' && window.STREAM_FSL_PROXY_MATCHUP_URL.trim())
                    ? window.STREAM_FSL_PROXY_MATCHUP_URL.trim()
                    : 'fsl_proxy_matchup.php';
                var sniffUrl = proxyBase + (proxyBase.indexOf('?') === -1 ? '?' : '&')
                    + 'name=' + encodeURIComponent(name)
                    + '&division=' + encodeURIComponent(division);

                fetch(sniffUrl)
                    .then(function(res) { return res.text(); })
                    .then(function(text) {
                        var t = text.toLowerCase();
                        var isError = (
                            t.indexOf('no spider chart data available') !== -1 ||
                            t.indexOf('has not been analyzed') !== -1 ||
                            t.indexOf('not found in division') !== -1 ||
                            t.indexOf('player name is required') !== -1 ||
                            t.indexOf('division parameter is required') !== -1 ||
                            t.indexOf('database connection failed') !== -1
                        );
                        if (isError) {
                            tryMatchupDivision(slot, name, divIdx + 1);
                        } else {
                            var wrap = document.createElement('div');
                            wrap.className = 'matchup-chart-frame-wrap';
                            var iframe = document.createElement('iframe');
                            iframe.className = 'matchup-chart-iframe';
                            iframe.setAttribute('frameborder', '0');
                            iframe.setAttribute('scrolling', 'no');
                            iframe.src = chartUrl;
                            wrap.appendChild(iframe);
                            slot.innerHTML = '';
                            slot.appendChild(wrap);
                        }
                    })
                    .catch(function(err) {
                        console.warn('[matchup] fetch failed for', division, err);
                        tryMatchupDivision(slot, name, divIdx + 1);
                    });
            }

            function disarmMatchup() {
                matchupArmed = false;
                matchupPicks = [];
                refreshMatchupPickHighlights();
                var btn = document.getElementById('scene-btn-matchup');
                if (btn) btn.classList.remove('armed');
                var intros = document.getElementById('player-intros-section');
                if (intros) intros.classList.remove('matchup-picking-mode');
                setSceneVideoError('');
            }

            function refreshMatchupPickHighlights() {
                document.querySelectorAll('.player-name-input').forEach(function(inp) {
                    inp.classList.remove('matchup-pick-first', 'matchup-pick-second');
                    var form = inp.closest ? inp.closest('.media-form') : inp.parentElement;
                    if (form) form.classList.remove('matchup-form-first', 'matchup-form-second');
                });
                if (matchupPicks[0]) {
                    matchupPicks[0].classList.add('matchup-pick-first');
                    var formA = matchupPicks[0].closest ? matchupPicks[0].closest('.media-form') : matchupPicks[0].parentElement;
                    if (formA) formA.classList.add('matchup-form-first');
                }
                if (matchupPicks[1]) {
                    matchupPicks[1].classList.add('matchup-pick-second');
                    var formB = matchupPicks[1].closest ? matchupPicks[1].closest('.media-form') : matchupPicks[1].parentElement;
                    if (formB) formB.classList.add('matchup-form-second');
                }
            }

            // Event delegation on player-intros-section for input pick clicks while armed
            (function() {
                var introsContainer = document.getElementById('player-intros-section');
                if (!introsContainer) return;
                introsContainer.addEventListener('click', function(e) {
                    if (!matchupArmed) return;
                    // Ignore clicks on the Go submit button — let it fire the intro normally
                    if (e.target.type === 'submit' || (e.target.closest && e.target.closest('button[type="submit"]'))) return;
                    // Find the form row that was clicked (input, label, or the form bg itself)
                    var form = e.target.closest ? e.target.closest('.media-form') : null;
                    if (!form) return;
                    var inp = form.querySelector('.player-name-input');
                    if (!inp) return;
                    var idx = matchupPicks.indexOf(inp);
                    if (idx !== -1) {
                        matchupPicks.splice(idx, 1);
                    } else if (matchupPicks.length < 2) {
                        matchupPicks.push(inp);
                    }
                    refreshMatchupPickHighlights();
                    if (matchupPicks.length === 0) {
                        setSceneVideoError('Pick Player A field, then Player B field, then press Matchup again.');
                    } else if (matchupPicks.length === 1) {
                        setSceneVideoError('\u25b6 A: ' + (matchupPicks[0].value.trim() || '(empty)') + ' \u2014 click Player B field, then press Matchup.');
                    } else {
                        setSceneVideoError('\u25b6 A: ' + (matchupPicks[0].value.trim() || '(empty)') + ' vs B: ' + (matchupPicks[1].value.trim() || '(empty)') + ' \u2014 press Matchup to open.');
                    }
                });
            })();
    </script>

    <script src="js/auth.js?v=<?php echo $v; ?>"></script>

    <!-- StreamElements live alerts -->
    <div id="se-alert-overlay" style="position:fixed;top:28px;left:290px;z-index:99998;pointer-events:none;width:200px;overflow:visible;"></div>

    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    <script src="js/stream-elements.js?v=<?php echo $v; ?>"></script>

    <script src="js/music-player.js?v=<?php echo $v; ?>"></script>
    <!-- Music Admin runs in its own window (music_admin.php), not as an overlay here. -->

    <!-- Status reporter: pushes live UI state to save_status.php for /status -->
    <script src="js/status-reporter.js?v=<?php echo $v; ?>"></script>

</body>

</html>


