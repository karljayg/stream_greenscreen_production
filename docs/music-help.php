<?php
// Standalone help + stats window for the music player.
// Opened by window.open() from music-player.js — no login required.
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Music Player — Help &amp; Stats</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: system-ui, sans-serif;
    font-size: 0.78rem;
    background: #1e2d40;
    color: #cbd5e1;
    line-height: 1.55;
    padding: 14px 16px 20px;
}
h1 {
    font-size: 0.85rem;
    font-weight: 800;
    color: #7dd3fc;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 12px;
    border-bottom: 1px solid #2d4560;
    padding-bottom: 6px;
}
h2 {
    font-size: 0.72rem;
    font-weight: 800;
    color: #7dd3fc;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin: 18px 0 8px;
    border-bottom: 1px solid #2d4560;
    padding-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}
h3 {
    font-size: 0.7rem;
    font-weight: 700;
    color: #93c5fd;
    margin: 10px 0 3px;
}
p { margin-bottom: 5px; color: #94a3b8; }
code {
    background: rgba(255,255,255,.08);
    border-radius: 3px;
    padding: 1px 4px;
    font-size: 0.72rem;
    color: #fbbf24;
}
hr { border: none; border-top: 1px solid #2d4560; margin: 14px 0; }

/* Stats */
#stats-wrap { position: relative; }
.refresh-btn {
    margin-left: auto;
    background: rgba(255,255,255,.08);
    border: 1px solid #3b5270;
    border-radius: 4px;
    color: #7dd3fc;
    font-size: 0.65rem;
    padding: 2px 8px;
    cursor: pointer;
}
.refresh-btn:hover { background: rgba(255,255,255,.16); }
.stats-totals {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
}
.stats-totals span {
    flex: 1;
    background: rgba(255,255,255,.06);
    border-radius: 5px;
    padding: 6px 4px;
    text-align: center;
    line-height: 1.3;
    color: #94a3b8;
}
.stats-totals strong {
    display: block;
    font-size: 1rem;
    color: #7dd3fc;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.68rem;
    color: #94a3b8;
    margin-bottom: 6px;
}
td { padding: 3px 4px; vertical-align: top; }
td:first-child { color: #cbd5e1; }
tr:nth-child(odd) td { background: rgba(255,255,255,.03); }
.stats-updated { font-size: 0.6rem; color: #475569; text-align: right; margin-top: 6px; }
.stats-empty { color: #475569; font-style: italic; }
</style>
</head>
<body>
<h1>&#9836; Music Player — Help</h1>

<h2>&#127908; Controls</h2>

<h3>VOL knob</h3>
<p>Master volume from 5–100%. Drag up/down or scroll the mouse wheel over it.</p>

<h3>FADE knob (0–10)</h3>
<p>Controls two things at once: how far into a song playback starts, and how long the crossfade between songs lasts.</p>
<p><code>start offset = 40 × (1 − fade/10)</code> seconds in &nbsp;|&nbsp; <code>crossfade = 0.5 + 1.5 × (fade/10)</code> seconds</p>
<p>FADE 0 → skips 40 s in, 0.5 s blend &nbsp;|&nbsp; FADE 10 → starts from 0 s, 2 s blend</p>
<p>Auto-advances between songs always use a short fixed 0.5 s crossfade regardless of the knob, so transitions stay tight.</p>

<h3>&#x23EE; Prev &nbsp;/&nbsp; &#x23ED; Next</h3>
<p>Step backward or forward one song within the current mood. Wraps around at the ends. Scene context is preserved — the player remembers which scene and mood list you're in.
In <strong>Random</strong> mode, both buttons pick a completely random song from any mood instead of stepping through the current mood.</p>

<h3>&#x1F500; Random</h3>
<p>Toggles shuffle mode. When active (highlighted), every song advance — natural endings, Next, Prev — picks a random song from a random mood across the entire library. Activating it jumps to a random song immediately.
Clicking any mood button turns Random off. Changing scenes also turns it off and resets the variety timers (see below).</p>

<h3>&#9654; Play / &#x23F8; Pause</h3>
<p>Pause suspends playback and freezes the auto-advance timer. Resuming continues from the exact position. If Chrome blocked audio on page load, the first click anywhere on the page unlocks it.</p>

<h2>&#127925; Playback Behaviour</h2>

<h3>Mood buttons</h3>
<p>Each button starts that mood from a random starting song. Clicking an <em>already-active</em> mood button skips sequentially to its next song instead of restarting. Clicking any mood button also clears the current scene context and turns off Random mode — you're now DJing manually and the mood loops indefinitely until you change it.</p>

<h3>Song order within a mood</h3>
<p>Songs in a mood play in indexed order (A, B, C…). A timer fires near the end of each track (duration − crossfade − 0.15 s buffer) to start the next one seamlessly. When the last song in a mood finishes, the player either advances to the next mood (if a scene is active) or loops back to song A of the same mood (if no scene).</p>

<h3>Scenes</h3>
<p>Activating a scene sets a mood list for that scene. The player picks a <em>random starting mood</em> from that list. Each time a mood's songs finish, it advances to the next mood in the list and cycles back to the start when it reaches the end.</p>
<p>If the same mood that's already playing is picked at scene activation, playback continues uninterrupted — no restart, no crossfade. The scene context is updated silently.</p>
<p>Clicking a mood button while a scene is active clears the scene context. The mood then loops itself without cycling.</p>

<h3>&#x1F504; Variety mode — anti-repetition (automatic)</h3>
<p>A scene with only 1–2 moods would loop the same tracks indefinitely if the stream is left unattended. Variety mode prevents this. Two triggers run simultaneously — <strong>whichever fires first</strong> wins:</p>
<h3>Trigger 1 — Cycle counter (3 full loops)</h3>
<p>Every time <code>mxSceneMoodIdx</code> wraps back to 0 — meaning the player has played through the entire scene mood list once — a loop is counted. After <strong>3 complete loops</strong>, Random mode activates automatically.</p>
<p>Example: a scene with 2 moods × 2 songs each ≈ 15 min per loop → variety kicks in after ≈ 45 min. A scene with 6+ moods would need ~3 h to loop 3 times, so it rarely triggers — by design, long lists don't need help.</p>
<h3>Trigger 2 — Wall-clock timer (60 minutes)</h3>
<p>Regardless of how many loops have completed, if the same scene has been playing continuously for <strong>60 minutes</strong>, Random activates. This catches scenes with many moods that still feel stale in a very long unattended session.</p>
<h3>What "Random activates" means</h3>
<p>Both triggers do the same thing: enable the &#x1F500; Random button (visually) and immediately switch to a random song from a random mood across the full library. From that point it behaves identically to pressing &#x1F500; manually.</p>
<h3>What resets the variety timers</h3>
<p>Manual control always overrides automation. The loop counter and wall-clock timer both reset to zero when:</p>
<p>• You click a <strong>scene button</strong> (any scene, including re-clicking the current one)<br>
• You click a <strong>mood button</strong> directly (also clears scene context)<br>
• You <strong>turn Random off</strong> while a scene is active (timer restarts from 0)<br>
• You <strong>turn Random on</strong> manually (timer is cleared — already in random, no need for it)</p>

<hr>

<div id="stats-wrap">
    <h2>&#128202; Playback Stats <button class="refresh-btn" onclick="loadStats()">&#8635; Refresh</button></h2>
    <div id="stats-body"><em class="stats-empty">Loading…</em></div>
</div>

<script>
function fmtDuration(s) {
    s = Math.round(s || 0);
    if (s < 60)   return s + 's';
    if (s < 3600) return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
    return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
}

function renderStats(data) {
    var el = document.getElementById('stats-body');
    if (!data || typeof data !== 'object') { el.innerHTML = '<em class="stats-empty">No stats yet.</em>'; return; }

    var t     = data.totals || {};
    var songs = data.songs  || {};
    var moods = data.moods  || {};
    var html  = [];

    html.push('<div class="stats-totals">');
    html.push('<span><strong>' + (t.plays || 0)         + '</strong>plays</span>');
    html.push('<span><strong>' + (t.skips || 0)         + '</strong>skips</span>');
    html.push('<span><strong>' + fmtDuration(t.seconds) + '</strong>listen time</span>');
    html.push('</div>');

    var moodList = Object.keys(moods).map(function(k) {
        return { key: k, plays: moods[k].plays || 0, seconds: moods[k].seconds || 0 };
    }).sort(function(a, b) { return b.plays - a.plays; });

    if (moodList.length) {
        html.push('<h3>Moods (' + moodList.length + ')</h3><table>');
        moodList.forEach(function(m) {
            var label = m.key.replace(/_/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
            html.push('<tr><td>' + label + '</td><td>' + m.plays + ' plays</td><td>' + fmtDuration(m.seconds) + '</td></tr>');
        });
        html.push('</table>');
    }

    var songList = Object.keys(songs).map(function(k) {
        return { file: k, plays: songs[k].plays || 0, skips: songs[k].skips || 0, seconds: songs[k].seconds || 0 };
    }).sort(function(a, b) { return b.plays - a.plays; });

    if (songList.length) {
        html.push('<h3>Songs played (' + songList.length + ')</h3>');
        html.push('<p style="font-size:0.62rem;color:#475569;margin-bottom:4px;">Only songs that have been played appear here. Zero-play songs are not recorded.</p>');
        html.push('<table>');
        songList.forEach(function(s) {
            var name = s.file.replace(/\.[^.]+$/, '').replace(/_/g,' ');
            html.push('<tr><td title="' + s.file + '">' + name + '</td><td>' + s.plays + ' pl</td><td>' + s.skips + ' sk</td><td>' + fmtDuration(s.seconds) + '</td></tr>');
        });
        html.push('</table>');
    }

    if (!moodList.length && !songList.length) html.push('<em class="stats-empty">No stats recorded yet.</em>');
    if (data.updated) html.push('<p class="stats-updated">Updated: ' + new Date(data.updated).toLocaleString() + '</p>');

    el.innerHTML = html.join('');
}

function loadStats() {
    // MX_STATS_URL is set as an absolute path by the opener (index.php or music/index.php),
    // so window.opener.MX_STATS_URL is always correct when opened via the ? button.
    // Fallback: ../save_music_stats.php works for direct navigation to docs/music-help.php.
    var url = (window.opener && window.opener.MX_STATS_URL)
        ? window.opener.MX_STATS_URL
        : '../save_music_stats.php';
    document.getElementById('stats-body').innerHTML = '<em class="stats-empty">Loading\u2026</em>';
    fetch(url).then(function(r){ return r.json(); }).then(renderStats).catch(function() {
        document.getElementById('stats-body').innerHTML = '<em class="stats-empty">Could not load stats.</em>';
    });
}

loadStats();
</script>
</body>
</html>
