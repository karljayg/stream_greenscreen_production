// Status reporter — pushes the producer's live UI state to save_status.php so a
// separate program can read it from /status and /status/log.
//
// The production tool keeps its state in the browser (scene .active classes,
// music-player closure vars, transient intro/GG button presses). This observer
// derives that state and POSTs it on a heartbeat plus immediately on key events.
// The server diffs the authoritative score (2026/scoreboard.csv) on its own.
//
// Requires (all optional, degrades gracefully): window.STATUS_URL,
// window.CURRENT_USER, window.mxGetStatusState, window.playPlayerIntroByName.

(function () {
    var STATUS_URL   = window.STATUS_URL || 'save_status.php';
    var HEARTBEAT_MS = 2000;
    var user         = window.CURRENT_USER || '';

    // Most-specific (foreground) scene wins when several overlays are active at
    // once (e.g. BG + Logos sit behind SC2 / Scoreboard).
    var SCENE_PRIORITY = [
        'sc2', 'sc2-quick', 'scoreboard', 'custom-scoreboard', 'bracket',
        'schedule', 'ash', 'pog', 'ptb', 'st', 'yt', 'shared-window',
        'full-shared', 'vdo-full', 'logos', 'all-vdo'
    ];

    // Player-intro names that mean "GG", not a normal player intro.
    var GG_NAMES       = { 'GG': 'gg' };
    var MATCH_GG_NAMES = { 'Match GG': 'match_gg' };

    var pending       = [];     // events queued for the next POST
    var currentScene  = null;   // active scene key last observed
    var previousScene = null;   // scene before the current one
    var lastMusicKey  = null;   // fingerprint of last reported music state
    var sending       = false;

    function getActiveScene() {
        for (var i = 0; i < SCENE_PRIORITY.length; i++) {
            var k = SCENE_PRIORITY[i];
            var btn = document.getElementById('scene-btn-' + k);
            if (btn && btn.classList.contains('active')) {
                return { key: k, label: (btn.textContent || '').trim() };
            }
        }
        return null;
    }

    function getMusicState() {
        if (typeof window.mxGetStatusState === 'function') {
            try { return window.mxGetStatusState(); } catch (e) {}
        }
        return null;
    }

    // Detect scene/music changes since the last call and queue events for them.
    function detectChanges(scene, music) {
        var sceneKey = scene ? scene.key : null;
        if (sceneKey !== currentScene) {
            previousScene = currentScene;
            currentScene = sceneKey;
            pending.push({ type: 'scene', data: { to: sceneKey, from: previousScene } });
        }
        if (music && (music.mood || music.track)) {
            var key = JSON.stringify([music.playing, music.mood, music.track, music.random]);
            if (key !== lastMusicKey) {
                lastMusicKey = key;
                pending.push({ type: 'music', data: {
                    mood: music.mood, mood_label: music.mood_label,
                    track: music.track, playing: music.playing, random: music.random
                } });
            }
        }
    }

    function send(isUnload) {
        if (sending && !isUnload) return;
        var scene = getActiveScene();
        var music = getMusicState();
        detectChanges(scene, music);

        var payload = {
            user: user,
            scene: scene
                ? { active: scene.key, label: scene.label, previous: previousScene }
                : { active: null, label: null, previous: previousScene },
            music: music,
            events: pending.splice(0, pending.length)
        };

        sending = true;
        try {
            fetch(STATUS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: !!isUnload
            }).then(function () { sending = false; }).catch(function () { sending = false; });
        } catch (e) { sending = false; }
    }

    // Intros/GG are reported via a CustomEvent fired at the real call site inside
    // stream_production.js (playPlayerIntroByName), so every trigger is captured:
    // form submits, suggestion clicks, SC2 scene, and custom buttons.
    function onIntroEvent(e) {
        var n = String((e && e.detail && e.detail.name) || '').trim();
        if (!n) return;
        if (MATCH_GG_NAMES[n]) {
            pending.push({ type: 'gg', data: { kind: 'match_gg' } });
        } else if (GG_NAMES[n]) {
            pending.push({ type: 'gg', data: { kind: 'gg' } });
        } else {
            pending.push({ type: 'intro', data: { player: n } });
        }
        send(false);
    }

    function start() {
        document.addEventListener('status:intro', onIntroEvent);
        // A scoreboard save just hit save_scoreboard.php — push now so the server
        // re-reads the CSV and emits the score/winner change immediately.
        document.addEventListener('status:score-saved', function () { send(false); });
        pending.push({ type: 'connect', data: { user: user } });
        send(false);
        setInterval(function () { send(false); }, HEARTBEAT_MS);
        window.addEventListener('pagehide',         function () { send(true); });
        window.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') send(true);
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        start();
    } else {
        window.addEventListener('DOMContentLoaded', start);
    }
})();
