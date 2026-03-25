// Music player widget — mood-based audio deck with crossfade, knobs, and seek bar.
// Requires: window.MX_TRACKS and window.MX_SCENE_MAP (set in PHP config block).
// Also wraps window.toggleSceneOverlay (defined in main inline script) to trigger mood changes.

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
    var mxRandom = false;
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

    // ── Stats tracker ─────────────────────────────────────────────
    var mxStats = (function () {
        var pending   = { songs: {}, moods: {}, totals: { plays: 0, skips: 0, seconds: 0 } };
        var startTime = null;   // wall-clock ms when current track started
        var curFile   = null;
        var curMood   = null;
        var flushTimer = null;

        function songBucket(file) {
            if (!pending.songs[file]) pending.songs[file] = { plays: 0, skips: 0, seconds: 0 };
            return pending.songs[file];
        }
        function moodBucket(mood) {
            if (!pending.moods[mood]) pending.moods[mood] = { plays: 0, seconds: 0 };
            return pending.moods[mood];
        }

        // Accumulate elapsed time from current track into pending; does NOT clear state.
        function accrue() {
            if (!startTime || !curFile) return;
            var elapsed = (Date.now() - startTime) / 1000;
            songBucket(curFile).seconds += elapsed;
            if (curMood) moodBucket(curMood).seconds += elapsed;
            pending.totals.seconds += elapsed;
            startTime = Date.now(); // reset so next accrue doesn't double-count
        }

        return {
            // Called when a track successfully starts playing.
            onSongStart: function (mood, file) {
                accrue(); // close previous track's time
                curFile = file; curMood = mood; startTime = Date.now();
                songBucket(file).plays++;
                moodBucket(mood).plays++;
                pending.totals.plays++;
            },
            // Called by user-initiated skips (next/prev/mood change).
            onSkip: function () {
                if (!curFile) return;
                accrue();
                songBucket(curFile).skips++;
                pending.totals.skips++;
            },
            // Flush accumulated delta to the server.
            flush: function (keepalive) {
                accrue();
                var hasData = pending.totals.plays > 0 || pending.totals.skips > 0 || pending.totals.seconds >= 1;
                if (!hasData) return;
                var url = (typeof window.MX_STATS_URL !== 'undefined') ? window.MX_STATS_URL : 'save_music_stats.php';
                var body = JSON.stringify(pending);
                pending = { songs: {}, moods: {}, totals: { plays: 0, skips: 0, seconds: 0 } };
                startTime = Date.now(); // timer continues for current track
                try {
                    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: !!keepalive });
                } catch(e) {}
            },
            startPeriodicFlush: function () {
                if (flushTimer) return;
                flushTimer = setInterval(function () { mxStats.flush(false); }, 60000);
            }
        };
    })();

    // Flush on page leave.
    window.addEventListener('pagehide',         function () { mxStats.flush(true); });
    window.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') mxStats.flush(true); });
    mxStats.startPeriodicFlush();

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

    document.getElementById('lpMusicRandom').addEventListener('click', function (e) {
        e.stopPropagation();
        if (!mxRandom) {
            mxSetRandom(true);
            // Switch immediately whether or not something is already playing
            mxStats.onSkip();
            mxSwitchRandom(false);
        } else {
            mxSetRandom(false);
        }
    });
    ppBtn.addEventListener('click', mxTogglePlay);

    document.getElementById('lpMusicNext').addEventListener('click', function () {
        if (mxCur) mxStats.onSkip();
        if (mxRandom) { mxSwitchRandom(false); return; }
        if (!mxMood) return;
        var songs = MX_TRACKS[mxMood] || [];
        if (!songs.length) return;
        mxSwitch(mxMood, (mxSongIdx + 1) % songs.length, true);
    });

    document.getElementById('lpMusicPrev').addEventListener('click', function () {
        if (mxCur) mxStats.onSkip();
        if (mxRandom) { mxSwitchRandom(false); return; }
        if (!mxMood) return;
        var songs = MX_TRACKS[mxMood] || [];
        if (!songs.length) return;
        mxSwitch(mxMood, (mxSongIdx - 1 + songs.length) % songs.length, true);
    });

    function mxSetStatus(text, type) {
        statusEl.textContent = text;
        statusEl.className = type === 'err' ? 'lp-mx-song lp-mx-err' : (type === 'playing' ? 'lp-mx-song playing' : 'lp-mx-song');
    }

    function mxBuildGrid() {
        var grid = document.getElementById('lpMusicGrid');
        grid.innerHTML = '';
        Object.keys(MX_TRACKS).forEach(function (mood) {
            var btn = document.createElement('button');
            btn.textContent = MX_LABELS[mood] || mood.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            btn.dataset.mood = mood;
            btn.title = mood;
            btn.addEventListener('click', function () {
                mxSetRandom(false);
                mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
                if (mxMood === mood && mxCur) {
                    // Re-click on active mood — skip to next song sequentially
                    var songs = MX_TRACKS[mood] || [];
                    mxStats.onSkip();
                    mxSwitch(mood, (mxSongIdx + 1) % songs.length);
                } else {
                    if (mxCur) mxStats.onSkip();
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
        var audio = new Audio((window.MX_MUSIC_PATH !== undefined ? window.MX_MUSIC_PATH : 'music/') + file);
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
        if (mxRandom) { mxSwitchRandom(autoAdvance); return; }
        if (mxSceneMoods && mxSceneMoods.length > 0) {
            mxSceneMoodIdx = (mxSceneMoodIdx + 1) % mxSceneMoods.length;
            var nextMood = mxSceneMoods[mxSceneMoodIdx];
            mxSwitch(nextMood, mxRandIdx(nextMood), true, autoAdvance);
        } else {
            mxSwitch(mxMood, mxRandIdx(mxMood), true, autoAdvance); // no scene → loop this mood
        }
    }

    function mxSwitchRandom(autoAdvance) {
        var moods = Object.keys(MX_TRACKS).filter(function (m) { return (MX_TRACKS[m] || []).length > 0; });
        if (!moods.length) return;
        var mood = moods[Math.floor(Math.random() * moods.length)];
        mxSwitch(mood, mxRandIdx(mood), false, autoAdvance);
    }

    function mxSetRandom(on) {
        mxRandom = on;
        var btn = document.getElementById('lpMusicRandom');
        if (btn) btn.classList.toggle('active', on);
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
            if (mxRandom) {
                mxSwitchRandom(true);
            } else if (nextIdx < songs.length) {
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
                    mxStats.onSongStart(mood, file);
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

    // ── Help window ──────────────────────────────────────────────
    (function () {
        var helpBtn = document.getElementById('lpMusicHelp');
        if (!helpBtn) return;
        var helpWin = null;
        helpBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var url = (typeof window.MX_HELP_URL !== 'undefined') ? window.MX_HELP_URL : 'music-help.php';
            if (helpWin && !helpWin.closed) { helpWin.focus(); return; }
            helpWin = window.open(url, 'mx-help', 'width=440,height=640,resizable=yes,scrollbars=yes');
        });
    })();

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
            fetch('save_music_config.php?which=' + which, {
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
            var targetVol = Number(volInput.value) / 100;
            var now = mx.currentTime;
            mxGain.gain.cancelScheduledValues(now);
            mxGain.gain.setValueAtTime(0, now);
            mxGain.gain.linearRampToValueAtTime(targetVol, now + 1.0);
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
// Patches window.toggleSceneOverlay (defined in the main inline script) to
// trigger mood changes when scenes switch. Runs after DOM + scripts are ready.
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
