<?php
// Production quick-reference help window.
// Opened by window.open() from auth.js (? button or F1) — no login required.
$appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Production Help</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html { overflow-y: auto; }
body {
    font-family: system-ui, sans-serif;
    font-size: 0.78rem;
    background: #1e2d40;
    color: #cbd5e1;
    line-height: 1.55;
    padding: 14px 16px 20px;
    min-width: 400px;
    max-width: 540px;
}
h1 {
    font-size: 0.85rem;
    font-weight: 800;
    color: #7dd3fc;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 6px;
    border-bottom: 1px solid #2d4560;
    padding-bottom: 6px;
}
.lead {
    font-size: 0.68rem;
    color: #64748b;
    margin-bottom: 12px;
}
.lead kbd {
    background: rgba(255,255,255,.08);
    border: 1px solid #3b5270;
    border-radius: 3px;
    padding: 0 5px;
    font-family: ui-monospace, monospace;
    font-size: 0.65rem;
    color: #fbbf24;
}
nav.toc {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #2d4560;
}
nav.toc a {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #93c5fd;
    text-decoration: none;
    padding: 3px 8px;
    border: 1px solid #3b5270;
    border-radius: 4px;
    background: rgba(255,255,255,.04);
}
nav.toc a:hover { background: rgba(255,255,255,.12); color: #fff; }
h2 {
    font-size: 0.72rem;
    font-weight: 800;
    color: #7dd3fc;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin: 18px 0 8px;
    border-bottom: 1px solid #2d4560;
    padding-bottom: 5px;
    scroll-margin-top: 8px;
}
h2:first-of-type { margin-top: 0; }
h3 {
    font-size: 0.68rem;
    font-weight: 700;
    color: #93c5fd;
    margin: 10px 0 4px;
}
p, .hint { margin-bottom: 6px; color: #94a3b8; font-size: 0.72rem; }
.hint { color: #64748b; font-size: 0.68rem; }
code {
    background: rgba(255,255,255,.08);
    border-radius: 3px;
    padding: 1px 4px;
    font-size: 0.68rem;
    color: #fbbf24;
}
ul.ref-list {
    list-style: none;
    margin: 0 0 6px;
    padding: 0;
}
ul.ref-list li {
    padding: 4px 0;
    border-bottom: 1px solid rgba(45,69,96,.45);
    font-size: 0.72rem;
    color: #94a3b8;
}
ul.ref-list li:last-child { border-bottom: none; }
ul.ref-list strong { color: #cbd5e1; font-weight: 600; }
.tool-list { list-style: none; margin: 0 0 4px; padding: 0; }
.tool-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    margin-bottom: 6px;
    background: rgba(255,255,255,.06);
    border: 1px solid #3b5270;
    border-radius: 5px;
    color: inherit;
    cursor: pointer;
    font: inherit;
}
.tool-item:hover { background: rgba(255,255,255,.12); border-color: #5a8ab0; }
.tool-name {
    font-weight: 700;
    color: #7dd3fc;
    display: block;
    margin-bottom: 2px;
    font-size: 0.74rem;
}
.tool-desc {
    font-size: 0.66rem;
    color: #64748b;
    line-height: 1.45;
}
.hk-list { margin-bottom: 4px; }
.hk-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0 8px;
    align-items: end;
    margin-bottom: 4px;
}
.hk-key {
    font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
    font-size: 0.72rem;
    color: #fbbf24;
    white-space: nowrap;
    padding-bottom: 2px;
}
.hk-dots {
    border-bottom: 1px dotted #3b5270;
    min-width: 12px;
    margin-bottom: 4px;
}
.hk-desc {
    color: #94a3b8;
    white-space: nowrap;
    padding-bottom: 2px;
}
.hk-sub { color: #64748b; font-size: 0.68rem; }
.obsolete-note {
    font-size: 0.66rem;
    color: #64748b;
    font-style: italic;
    margin-bottom: 6px;
}
</style>
</head>
<body>
<h1>Production Help</h1>
<p class="lead">Quick reference for stream production admins. Press <kbd>F1</kbd> or click <strong>?</strong> in the top bar anytime.</p>

<nav class="toc">
    <a href="#popups">Pop-up tools</a>
    <a href="#settings">Settings</a>
    <a href="#scenes">Scenes</a>
    <a href="#overlay-tips">Overlay tips</a>
    <a href="#observer">Observer</a>
</nav>

<h2 id="popups">Pop-up tools</h2>
<p class="hint">Opens in a separate window so it stays off the live overlay.</p>
<ul class="tool-list">
    <li>
        <button type="button" class="tool-item" data-popup="<?php echo htmlspecialchars($appBase . '/player_scoreboard_editor.php', ENT_QUOTES); ?>" data-name="playerScoreboardEditor" data-features="width=470,height=600,resizable=yes,scrollbars=yes">
            <span class="tool-name">Player Scoreboard editor</span>
            <span class="tool-desc">Edit match overlay: player names, scores, Best-of, race, team colors. Saves to the live player scoreboard panel.</span>
        </button>
    </li>
    <li>
        <button type="button" class="tool-item" data-popup="<?php echo htmlspecialchars($appBase . '/scoreboard_editor.php', ENT_QUOTES); ?>" data-name="scoreboardEditor" data-features="width=980,height=760,resizable=yes,scrollbars=yes">
            <span class="tool-name">FSL TeamLeague scoreboard — detailed editor</span>
            <span class="tool-desc">Full team-league scoreboard: team names, map labels, every matchup row, per-map scores. Loads <code>2026/scoreboard.csv</code> on open.</span>
        </button>
    </li>
    <li>
        <button type="button" class="tool-item" data-popup="<?php echo htmlspecialchars($appBase . '/music_admin.php', ENT_QUOTES); ?>" data-name="mxMusicAdmin" data-features="width=900,height=920,resizable=yes,scrollbars=yes">
            <span class="tool-name">Music admin</span>
            <span class="tool-desc">Scene &rarr; mood mappings, mood &rarr; song lists, staged SC2 music, upload audio. Use <strong>Save to Server</strong> to apply.</span>
        </button>
    </li>
    <li>
        <button type="button" class="tool-item" data-popup="<?php echo htmlspecialchars($appBase . '/docs/music-help.php', ENT_QUOTES); ?>" data-name="mx-help" data-features="width=900,height=1640,resizable=yes,scrollbars=yes">
            <span class="tool-name">Music player help &amp; stats</span>
            <span class="tool-desc">Knob behaviour, scene/stage playback, variety mode, and playback statistics. Same as the <strong>?</strong> on the music widget.</span>
        </button>
    </li>
</ul>

<h2 id="settings">Settings panel</h2>
<p class="hint">Left column &rarr; <strong>&#9881; Settings</strong>. Expand the section you need.</p>
<ul class="ref-list">
    <li><strong>Scoreboard</strong> — Quick team totals and matchup scores. <em>Edit Details&hellip;</em> opens the full TeamLeague editor (or use the pop-up above).</li>
    <li><strong>Custom Scoreboard</strong> — Ad-hoc side labels and match rows for non-standard events.</li>
    <li><strong>Player Scoreboard</strong> — Inline editor for the SC2 match graphic; <em>Open in window</em> for the pop-up editor.</li>
    <li><strong>VDO</strong> — Director URL opened when you right-click the large or small VDO panel.</li>
    <li><strong>Positioning</strong> — Event Title text (styled banner), drag/resize logos, VDO panels, and the player scoreboard on the overlay.</li>
    <li><strong>Music</strong> — Link to Music Admin pop-up.</li>
    <li><strong>Status Message</strong> — On-screen title / team A / team B message overlay.</li>
    <li><strong>Layer Order</strong> — Z-order for logos, scoreboards, SC2 panel, etc.</li>
    <li><strong>Save / Load</strong> — Export or restore full production setup JSON.</li>
</ul>

<h2 id="scenes">Scenes</h2>
<p class="hint">Left column &rarr; <strong>Scenes</strong> section. One active scene at a time unless noted.</p>
<ul class="ref-list">
    <li><strong>SC2 (animated) / Quick</strong> — Main gameplay layouts with optional animated BG.</li>
    <li><strong>T &#9650;</strong> — Toggle team-league score banner (top-right) during SC2.</li>
    <li><strong>P &#9660;</strong> — Toggle player scoreboard overlay during SC2.</li>
    <li><strong>FSL TeamLeague Scoreboard</strong> — Full-screen team scoreboard scene (not the small banner).</li>
    <li><strong>Custom Scoreboard</strong> — Full-screen custom side-vs-side board.</li>
    <li><strong>Schedule / Bracket</strong> — Static schedule and bracket overlays.</li>
    <li><strong>INTRO / BREAK</strong> — YouTube intro or break timer videos.</li>
    <li><strong>Shared Window / Matchup</strong> — Browser tab capture or FSL matchup graphic.</li>
    <li><strong>ASH / POG / PTB / ST</strong> — Player intro chroma effect scenes.</li>
</ul>

<h2 id="overlay-tips">Overlay tips</h2>
<ul class="ref-list">
    <li><strong>Right-click player scoreboard</strong> on the overlay to open its pop-up editor.</li>
    <li><strong>Hide VDO / Reload VDO / Refresh RightPanel</strong> — Controls above the music widget.</li>
    <li><strong>Player intro forms</strong> — Enter a BattleTag and press Go to play chroma intro video.</li>
</ul>

<h2 id="observer">Observer hotkeys</h2>
<p class="hint">In-game SC2 observer controls (not this web app).</p>
<div class="hk-list">
    <div class="hk-item"><span class="hk-key">Z</span><span class="hk-dots"></span><span class="hk-desc">Zoom out</span></div>
    <div class="hk-item"><span class="hk-key">Shift + Z</span><span class="hk-dots"></span><span class="hk-desc">Zoom out more</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + F</span><span class="hk-dots"></span><span class="hk-desc">Hold to follow selected unit</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + Shift + F</span><span class="hk-dots"></span><span class="hk-desc">Start following selected unit</span></div>
    <div class="hk-item"><span class="hk-key">V</span><span class="hk-dots"></span><span class="hk-desc">Hold to see player vision</span></div>
    <div class="hk-item"><span class="hk-key">E</span><span class="hk-dots"></span><span class="hk-desc">Set vision back to normal</span></div>
    <div class="hk-item"><span class="hk-key"></span><span class="hk-dots"></span><span class="hk-desc"><span class="hk-sub">(if you're stuck to player vision)</span></span></div>
    <div class="hk-item"><span class="hk-key">D</span><span class="hk-dots"></span><span class="hk-desc">Toggle production tab</span></div>
    <div class="hk-item"><span class="hk-key">G</span><span class="hk-dots"></span><span class="hk-desc">Toggle upgrade tab</span></div>
    <div class="hk-item"><span class="hk-key">U</span><span class="hk-dots"></span><span class="hk-desc">Toggle units tab</span></div>
    <div class="hk-item"><span class="hk-key">I</span><span class="hk-dots"></span><span class="hk-desc">Toggle income tab</span></div>
    <div class="hk-item"><span class="hk-key">L</span><span class="hk-dots"></span><span class="hk-desc">Toggle lost resources tab</span></div>
    <div class="hk-item"><span class="hk-key">Shift + L</span><span class="hk-dots"></span><span class="hk-desc">Toggle units lost popup</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + R</span><span class="hk-dots"></span><span class="hk-desc">Toggle income &amp; lost workers popup</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + A</span><span class="hk-dots"></span><span class="hk-desc">Toggle army size popup</span></div>
    <div class="hk-item"><span class="hk-key">Shift + N</span><span class="hk-dots"></span><span class="hk-desc">Toggle player names from Battle.net</span></div>
    <div class="hk-item"><span class="hk-key">Shift + Alt + N</span><span class="hk-dots"></span><span class="hk-desc">Toggle player names from auto-detect (default)</span></div>
</div>

<h3>Other observer keys</h3>
<div class="hk-list">
    <div class="hk-item"><span class="hk-key">Grave</span><span class="hk-dots"></span><span class="hk-desc">Change info tab placement</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + C</span><span class="hk-dots"></span><span class="hk-desc">Toggle APM popup</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + V</span><span class="hk-dots"></span><span class="hk-desc">Toggle EPM popup</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + Shift + K</span><span class="hk-dots"></span><span class="hk-desc">Toggle king of the hill</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + Shift + O</span><span class="hk-dots"></span><span class="hk-desc">Toggle observer tools</span></div>
</div>

<h3>Obsolete — use Player Scoreboard editor</h3>
<p class="obsolete-note">Ctrl + X to switch player names top/bottom. These in-game score shortcuts are replaced by the web player scoreboard.</p>
<div class="hk-list">
    <div class="hk-item"><span class="hk-key">Double click score</span><span class="hk-dots"></span><span class="hk-desc">Add 10 to score</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + Shift + [1-9]</span><span class="hk-dots"></span><span class="hk-desc">Best of [1-9]</span></div>
    <div class="hk-item"><span class="hk-key">Shift + [1-9]</span><span class="hk-dots"></span><span class="hk-desc">Top player score</span></div>
    <div class="hk-item"><span class="hk-key">Ctrl + [1-9]</span><span class="hk-dots"></span><span class="hk-desc">Bottom player score</span></div>
</div>

<script>
(function () {
    var popupWins = {};
    document.querySelectorAll('.tool-item[data-popup]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-popup');
            var name = btn.getAttribute('data-name') || '_blank';
            var features = btn.getAttribute('data-features') || 'resizable=yes,scrollbars=yes';
            if (popupWins[name] && !popupWins[name].closed) {
                popupWins[name].focus();
                return;
            }
            popupWins[name] = window.open(url, name, features);
        });
    });

    var MAX_H = Math.min(720, (screen.availHeight || 800) - 48);
    function fitWindow() {
        try {
            var w = Math.ceil(document.body.scrollWidth);
            var contentH = Math.ceil(document.body.scrollHeight);
            var viewH = Math.min(contentH, MAX_H);
            if (contentH > MAX_H) {
                document.documentElement.style.overflowY = 'auto';
                document.body.style.maxHeight = MAX_H + 'px';
                document.body.style.overflowY = 'auto';
            }
            var chromeW = Math.max(0, window.outerWidth - window.innerWidth);
            var chromeH = Math.max(0, window.outerHeight - window.innerHeight);
            window.resizeTo(w + chromeW + 2, viewH + chromeH + 2);
        } catch (e) {}
    }
    window.addEventListener('load', function () { setTimeout(fitWindow, 0); });
})();
</script>
</body>
</html>
