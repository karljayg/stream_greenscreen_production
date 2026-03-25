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
<h1>&#9836; Music Player</h1>

<h3>VOL knob</h3>
<p>Volume from 5–100%. Drag up/down or scroll.</p>

<h3>FADE knob (0–10)</h3>
<p>Controls the song start point and crossfade length.</p>
<p><code>start = 40 × (1 − fade/10)</code> seconds in<br>
<code>crossfade = 0.5 + 1.5 × (fade/10)</code> seconds</p>
<p>FADE 0 → jump 40s in, 0.5s blend &nbsp;|&nbsp; FADE 10 → from 0s, 2s blend</p>

<h3>&#x23EE; Prev &nbsp;/&nbsp; &#x23ED; Next</h3>
<p>Skip to the previous or next song within the current mood. Wraps around. Scene context is preserved.</p>

<h3>&#9646;&#9646; Pause</h3>
<p>Suspends playback and the auto-advance timer. Resuming continues from the exact position. If the browser blocked audio on load, the first click anywhere unlocks it.</p>

<h3>Mood buttons</h3>
<p>Each button starts that mood from a random song. Clicking an <em>already-active</em> mood skips to its next song sequentially.</p>

<h3>Songs &amp; rotation</h3>
<p>Songs within a mood play in order (1, 2, 3…). When the list ends, the next mood in the scene's list begins and cycles. With no active scene, the same mood loops indefinitely.</p>

<h3>Scenes</h3>
<p>Activating a scene picks a random starting mood from that scene's list. When a mood's songs finish, playback advances to the next mood in order. If the same mood is already playing when a scene switches, playback continues uninterrupted.</p>

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
        html.push('<h3>Top Moods</h3><table>');
        moodList.slice(0, 6).forEach(function(m) {
            var label = m.key.replace(/_/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
            html.push('<tr><td>' + label + '</td><td>' + m.plays + ' plays</td><td>' + fmtDuration(m.seconds) + '</td></tr>');
        });
        html.push('</table>');
    }

    var songList = Object.keys(songs).map(function(k) {
        return { file: k, plays: songs[k].plays || 0, skips: songs[k].skips || 0, seconds: songs[k].seconds || 0 };
    }).sort(function(a, b) { return b.plays - a.plays; });

    if (songList.length) {
        html.push('<h3>Top Songs</h3><table>');
        songList.slice(0, 10).forEach(function(s) {
            var name = s.file.replace(/\.[^.]+$/, '').replace(/_/g,' ');
            if (name.length > 34) name = name.slice(0, 32) + '\u2026';
            html.push('<tr><td title="' + s.file + '">' + name + '</td><td>' + s.plays + ' pl</td><td>' + s.skips + ' sk</td><td>' + fmtDuration(s.seconds) + '</td></tr>');
        });
        html.push('</table>');
    }

    if (!moodList.length && !songList.length) html.push('<em class="stats-empty">No stats recorded yet.</em>');
    if (data.updated) html.push('<p class="stats-updated">Updated: ' + new Date(data.updated).toLocaleString() + '</p>');

    el.innerHTML = html.join('');
}

function loadStats() {
    // Accept a statsUrl passed from the opener, fallback to sibling endpoint.
    var url = (window.opener && window.opener.MX_STATS_URL) ? window.opener.MX_STATS_URL : 'save_music_stats.php';
    document.getElementById('stats-body').innerHTML = '<em class="stats-empty">Loading\u2026</em>';
    fetch(url).then(function(r){ return r.json(); }).then(renderStats).catch(function() {
        document.getElementById('stats-body').innerHTML = '<em class="stats-empty">Could not load stats.</em>';
    });
}

loadStats();
</script>
</body>
</html>
