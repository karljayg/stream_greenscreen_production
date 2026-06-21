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

    // ── Staged playback state (detailed scenes, e.g. SC2) ──
    // A "detailed" scene is any key present in window.MX_SCENE_STAGES. Instead of
    // a flat mood list, its music is organized into numbered gameplay stages
    // (1–6). Each stage owns a pool of songs; selection is stage-first,
    // variation-second. See mxStageSelect for the algorithm.
    var mxStagedScene = null;  // active staged scene key, or null when not in staged mode
    var mxCurStage    = 0;     // current stage number (1-based) within mxStagedScene
    var mxStagePlayed = {};    // { stageN: { filename: true } } — songs played this session per stage
    var mxStageLast   = {};    // { stageN: filename } — most recently played song per stage
    var mxCollapsed   = true;  // widget grid collapsed state (mirrors the toggle below)

    // ── Variety mode: prevents endless repetition on short scene mood lists ──
    // Problem: a scene with only 1-2 moods will loop those same tracks forever
    // if the stream is left unattended (e.g. producer steps away mid-session).
    // Solution: two complementary triggers — whichever fires first wins.
    //
    //   Trigger 1 — CYCLE COUNTER:
    //     After VARIETY_CYCLE_THRESHOLD complete loops through the scene's mood
    //     list, break out into full-random mode across all moods. A "loop" means
    //     mxSceneMoodIdx wrapped from the last entry back to index 0.
    //     A scene with 2 moods × 2 songs each ≈ 15 min/cycle → 3 cycles ≈ 45 min.
    //     A scene with 6 moods × 2 songs each ≈ 45 min/cycle → threshold rarely
    //     fires, which is intentional — long scene lists don't need variety help.
    //
    //   Trigger 2 — WALL-CLOCK TIMER (VARIETY_TIME_MS):
    //     After this many ms of continuous scene play, trigger variety regardless
    //     of cycle count. Safety net for very long sessions where the cycle
    //     threshold alone might not be enough.
    //
    // Both counters reset whenever the user actively changes scene or mood, so
    // manual control always wins over the automation.
    var VARIETY_CYCLE_THRESHOLD = 3;             // full mood-list loops before variety kicks in
    var VARIETY_TIME_MS         = 60 * 60 * 1000; // 60-min wall-clock fallback

    var mxSceneCycles  = 0;    // counts complete loops through the scene mood list
    var mxVarietyTimer = null; // handle for the wall-clock variety fallback

    var statusEl  = document.getElementById('lpMusicStatus');
    var stageWrap  = document.getElementById('lpMusicStage');
    var stageGrid  = document.getElementById('lpMusicStageGrid');
    var stageNow   = document.getElementById('lpMusicStageNow');
    var stageTitle = document.getElementById('lpMusicStageTitle');
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
        if (mxStagedScene) mxScheduleStaged(mxTrack);
        else mxSchedule(mxMood, mxTrack, mxSongIdx);
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

    function mxApplyMusicVolFromUi() {
        if (!volInput) return;
        var v = Number(volInput.value);
        if (!isFinite(v)) v = 22;
        v = Math.max(0, Math.min(1, v / 100));
        if (mxGain) mxGain.gain.value = v;
    }
    /* 'input' = knob drag; 'change' = import/settings (fireChange) — both must update mxGain */
    volInput.addEventListener('input', mxApplyMusicVolFromUi);
    volInput.addEventListener('change', mxApplyMusicVolFromUi);
    window.mxApplyMusicVolFromUi = mxApplyMusicVolFromUi;

    document.getElementById('lpMusicRandom').addEventListener('click', function (e) {
        e.stopPropagation();
        if (!mxRandom) {
            mxSetRandom(true);
            mxExitStaged(); // random across all moods overrides staged mode
            // Switch immediately whether or not something is already playing.
            // Also clear the variety timer — we're already in random mode, so
            // the timer has nothing left to do and shouldn't fire redundantly.
            mxClearVarietyTimer();
            mxStats.onSkip();
            mxSwitchRandom(false);
        } else {
            mxSetRandom(false);
            // User turned random OFF — if we're still in a scene context, restart
            // the variety timer so the cycle-based protection kicks back in from
            // this point forward (don't silently lose the safety net).
            if (mxCurScene) { mxSceneCycles = 0; mxStartVarietyTimer(); }
        }
    });
    ppBtn.addEventListener('click', mxTogglePlay);

    document.getElementById('lpMusicNext').addEventListener('click', function () {
        if (mxCur) mxStats.onSkip();
        if (mxStagedScene) { mxAdvanceStage(1, false); return; } // forward one stage
        if (mxRandom) { mxSwitchRandom(false); return; }
        if (!mxMood) return;
        var songs = MX_TRACKS[mxMood] || [];
        if (!songs.length) return;
        mxSwitch(mxMood, (mxSongIdx + 1) % songs.length, true);
    });

    document.getElementById('lpMusicPrev').addEventListener('click', function () {
        if (mxCur) mxStats.onSkip();
        if (mxStagedScene) { mxAdvanceStage(-1, false); return; } // back one stage
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
                // User is manually DJing — stop all variety automation so their
                // explicit choice loops freely without being overridden.
                mxClearVarietyTimer();
                mxSceneCycles = 0;
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

    function mxMusicBase() {
        var b = (window.MX_MUSIC_PATH !== undefined && window.MX_MUSIC_PATH !== '') ? String(window.MX_MUSIC_PATH) : 'music/';
        if (b.indexOf('/') === -1) return b + '/';
        return /\/$/.test(b) ? b : b + '/';
    }

    /** Full URL for a track entry (filename, site-absolute path, or absolute URL). */
    function mxResolveMusicSrc(file) {
        var f = String(file || '').trim();
        if (!f) return '';
        if (/^https?:\/\//i.test(f) || f.indexOf('//') === 0) return f;
        try {
            if (f.charAt(0) === '/') {
                return new URL(f, window.location.origin).href;
            }
            return new URL(f, new URL(mxMusicBase(), window.location.href)).href;
        } catch (e) {
            return mxMusicBase() + f;
        }
    }

    function mxMusicNeedsAnonymousCORS(absUrl) {
        try {
            return new URL(absUrl, window.location.href).origin !== window.location.origin;
        } catch (e2) {
            return false;
        }
    }

    function mxMakeDeck(file) {
        var url = mxResolveMusicSrc(file);
        try {
            console.log('[MX music] resolved URL:', url, '| MX_MUSIC_PATH:', (typeof window.MX_MUSIC_PATH !== 'undefined' ? window.MX_MUSIC_PATH : '(unset)'), '| entry:', file);
        } catch (logErr) { /* ignore */ }
        var audio = new Audio(url);
        // CORS mode breaks many same-origin static hosts (no ACAO on mp3). Only
        // set anonymous crossOrigin when the track is actually cross-origin.
        if (mxMusicNeedsAnonymousCORS(url)) audio.crossOrigin = 'anonymous';
        audio.loop = false; audio.preload = 'auto';
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
            var er = function () {
                done();
                var err = audio.error;
                var code = err && err.code ? err.code : 0;
                var detail = err && err.message ? err.message : 'Load failed';
                rej(new Error(detail + (code ? ' (' + code + ')' : '')));
            };
            audio.addEventListener('canplay', ok);
            audio.addEventListener('error', er);
            audio.load();
        });
    }

    function mxClearTimer() {
        if (mxTimer) { clearTimeout(mxTimer); mxTimer = null; }
    }

    // Clear the wall-clock variety fallback timer.
    function mxClearVarietyTimer() {
        if (mxVarietyTimer) { clearTimeout(mxVarietyTimer); mxVarietyTimer = null; }
    }

    // Start (or restart) the wall-clock variety fallback timer.
    // Called whenever a scene activates or re-activates.
    function mxStartVarietyTimer() {
        mxClearVarietyTimer();
        mxVarietyTimer = setTimeout(function () {
            // Only act if we're still in a scene context — if the user manually
            // picked a mood this timer will still be running but mxCurScene is null,
            // so we bail out and let them keep their manual choice.
            if (!mxCurScene) return;
            // Wall-clock threshold reached: open up to all moods randomly.
            // This fires even if the cycle counter hasn't hit its limit yet,
            // e.g. a scene with many moods that still feels stale after an hour.
            mxSetRandom(true);
            mxSwitchRandom(true);
        }, VARIETY_TIME_MS);
    }

    // Reset variety state — call whenever the user actively starts a scene
    // or the scene changes, so counting always begins fresh.
    function mxResetVariety() {
        mxSceneCycles = 0;
        mxStartVarietyTimer();
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
            var nextIdx = (mxSceneMoodIdx + 1) % mxSceneMoods.length;

            // Detect a completed loop: wrapping back to index 0 means we've played
            // through the entire scene mood list one more time.
            if (nextIdx === 0) {
                mxSceneCycles++;

                // Cycle threshold reached — the same short list has looped enough
                // times that it's now audibly repetitive. Break out into full-random
                // mode so the listener hears something fresh across all moods.
                // The threshold is intentionally low (3) because a scene with only
                // 1-2 moods can repeat noticeably within a single broadcast.
                if (mxSceneCycles >= VARIETY_CYCLE_THRESHOLD) {
                    mxClearVarietyTimer(); // wall-clock timer no longer needed
                    mxSetRandom(true);
                    mxSwitchRandom(autoAdvance);
                    return;
                }
            }

            mxSceneMoodIdx = nextIdx;
            var nextMood = mxSceneMoods[mxSceneMoodIdx];
            mxSwitch(nextMood, mxRandIdx(nextMood), true, autoAdvance);
        } else {
            // No scene context — the user manually chose this mood and expects it
            // to loop. We don't apply variety logic here because manual choices
            // should always be respected without automatic interference.
            mxSwitch(mxMood, mxRandIdx(mxMood), true, autoAdvance);
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

    // Core deck loader + crossfade. Shared by both the mood engine (mxSwitch)
    // and the staged engine (mxPlayStagedFile). Loads `file`, crossfades it in,
    // and invokes onStarted(deck) once it is actually playing, or onBlocked(deck)
    // when the browser blocks autoplay (NotAllowedError).
    function mxStartDeck(file, fp, onStarted, onBlocked) {
        mxEnsure().then(function () {
            mxClearTimer();
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
                    onStarted(deck);
                }).catch(function (err) {
                    if (err.name === 'NotAllowedError') { onBlocked(deck); }
                    else { mxSetStatus(err.message, 'err'); }
                });
            }).catch(function (err) { mxSetStatus(err.message, 'err'); });
        });
    }

    // Switch to mood at songIdx.
    // keepScene=true:   preserve scene tracking (internal auto-advance calls).
    // autoAdvance=true: song ended naturally — start next song from 0, short fixed crossfade.
    function mxSwitch(mood, songIdx, keepScene, autoAdvance) {
        if (songIdx === undefined || songIdx === null) songIdx = 0;
        if (!keepScene) {
            mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
            mxExitStaged(); // manual mood choice leaves staged mode
        }
        var songs = MX_TRACKS[mood] || [];
        var file = songs[songIdx] || null;
        if (!file) { mxSetStatus('No tracks: ' + mood, 'err'); return; }

        var fp = autoAdvance ? { startOffset: 0, crossfadeDuration: 0.5 } : mxGetFadeParams();
        mxStartDeck(file, fp, function () {
            mxMood = mood; mxTrack = file; mxSongIdx = songIdx;
            mxStats.onSongStart(mood, file);
            mxUpdateActive();
            mxPaused = false;
            ppBtn.innerHTML = '&#x23F8;';
            ppBtn.classList.remove('lp-mx-dim', 'lp-mx-paused');
            mxSchedule(mood, file, songIdx);
            mxSetStatus(file, 'playing');
        }, function () {
            // Chrome blocked play — preserve mood state so the
            // pre-registered unlock listener can resume transparently.
            mxMood = mood; mxTrack = file; mxSongIdx = songIdx;
            mxUpdateActive();
            mxPaused = true;
            ppBtn.innerHTML = '&#9654;';
            ppBtn.classList.add('lp-mx-dim', 'lp-mx-paused');
            mxSetStatus('\u25b6 click play to start');
        });
    }

    // ── Staged engine (stage-first, variation-second) ─────────────────
    function mxStageScenes()       { return window.MX_SCENE_STAGES || {}; }
    function mxIsStaged(key)        { return !!mxStageScenes()[key]; }
    function mxStageDefFor(key)     { return mxStageScenes()[key] || null; }
    function mxStagesOf(key) {
        var def = mxStageDefFor(key);
        return (def && Array.isArray(def.stages)) ? def.stages : [];
    }
    function mxStageEntry(key, n) {
        var stages = mxStagesOf(key);
        for (var i = 0; i < stages.length; i++) {
            if (Number(stages[i].n) === Number(n)) return stages[i];
        }
        return null;
    }
    function mxStagePool(key, n) {
        var e = mxStageEntry(key, n);
        return (e && Array.isArray(e.songs)) ? e.songs.filter(Boolean) : [];
    }

    // Pick one song from stage `n`'s pool following the priority rules:
    //   1. prefer songs not yet played this session;
    //   2. when the pool is exhausted, reset only this stage's history and
    //      restart, avoiding the most-recently-played song when possible;
    //   3. pick randomly or sequentially based on the scene's `select` mode.
    function mxStageSelect(key, n) {
        var pool = mxStagePool(key, n);
        if (!pool.length) return null;
        var played = mxStagePlayed[n] || (mxStagePlayed[n] = {});

        var unplayed = pool.filter(function (f) { return !played[f]; });
        if (unplayed.length === 0) {
            // Whole pool used — reset just this stage and avoid repeating the last track.
            mxStagePlayed[n] = played = {};
            var last = mxStageLast[n];
            unplayed = pool.filter(function (f) { return f !== last; });
            if (unplayed.length === 0) unplayed = pool.slice(); // pool of 1
        }

        var mode = (mxStageDefFor(key) || {}).select || 'random';
        if (mode === 'sequential') return unplayed[0]; // earliest unused in pool order
        return unplayed[Math.floor(Math.random() * unplayed.length)];
    }

    // Schedule auto-advance for staged playback: when the current staged track
    // ends, move FORWARD to the next stage and play one (unplayed) song there —
    // one song per stage. Wraps from the last stage back to the first so the
    // scene keeps looping through every stage while it stays active.
    function mxScheduleStaged(file) {
        mxClearTimer();
        if (!mxCur || mxPaused) return;
        var fade = mxGetFadeParams().crossfadeDuration;
        var dur  = mxCur.audio.duration;
        if (!isFinite(dur) || dur <= 0) return;
        var wait = Math.max(0, dur - mxCur.audio.currentTime - fade - 0.15);
        mxTimer = setTimeout(function () {
            if (!mxStagedScene || !mxCur || mxCur.file !== file || mxPaused) return;
            mxAdvanceStage(1, true);
        }, wait * 1000);
    }

    // Step between stages in their listed order, wrapping around: advancing past
    // the last stage loops back to the first, and stepping before the first
    // wraps to the last. Used by song-end auto-advance and the Fwd/Rwd buttons.
    function mxAdvanceStage(step, autoAdvance) {
        var stages = mxStagesOf(mxStagedScene);
        if (!stages.length) return;
        var idx = 0;
        for (var i = 0; i < stages.length; i++) {
            if (Number(stages[i].n) === Number(mxCurStage)) { idx = i; break; }
        }
        var len = stages.length;
        var nextIdx = ((idx + step) % len + len) % len;
        mxPlayStage(Number(stages[nextIdx].n), autoAdvance);
    }

    // Load and play a single staged file (not tied to a mood/MX_TRACKS index).
    function mxPlayStagedFile(file, autoAdvance) {
        var fp = autoAdvance ? { startOffset: 0, crossfadeDuration: 0.5 } : mxGetFadeParams();
        var statMood = (mxStagedScene || 'staged') + ':stage' + mxCurStage;
        mxStartDeck(file, fp, function () {
            mxMood = null; mxTrack = file; mxSongIdx = 0;
            mxStats.onSongStart(statMood, file);
            mxUpdateActive();
            mxPaused = false;
            ppBtn.innerHTML = '&#x23F8;';
            ppBtn.classList.remove('lp-mx-dim', 'lp-mx-paused');
            mxScheduleStaged(file);
            mxSetStatus(file, 'playing');
        }, function () {
            mxTrack = file; mxSongIdx = 0;
            mxPaused = true;
            ppBtn.innerHTML = '&#9654;';
            ppBtn.classList.add('lp-mx-dim', 'lp-mx-paused');
            mxSetStatus('\u25b6 click play to start');
        });
    }

    // Enter / switch to stage `n`. autoAdvance=true → short crossfade (song ended).
    function mxPlayStage(n, autoAdvance) {
        if (!mxStagedScene) return;
        var file = mxStageSelect(mxStagedScene, n);
        if (!file) { mxSetStatus('No songs: stage ' + n, 'err'); return; }
        mxCurStage = Number(n);
        var played = mxStagePlayed[n] || (mxStagePlayed[n] = {});
        played[file] = true;
        mxStageLast[n] = file;
        mxUpdateStageActive();
        mxPlayStagedFile(file, autoAdvance);
    }

    // User picked a stage button — switch immediately to the new stage pool.
    function mxSetStage(n) {
        if (!mxStagedScene) return;
        if (mxCur) mxStats.onSkip();
        mxSetRandom(false);
        mxClearVarietyTimer();
        mxPlayStage(n, false);
    }
    window.mxSetStage = mxSetStage;

    // Show the stage bar UI as soon as a detailed scene is entered, WITHOUT
    // starting staged playback. For SC2 the actual song switch is deliberately
    // held until the animated intro/transition finishes (mxStartStaged runs
    // then), but the producer should see the stage controls immediately.
    window.mxPrepareStageBar = function (key) {
        if (!mxIsStaged(key)) return;
        mxStagedScene = key;
        if (!mxCurStage) {
            var stages = mxStagesOf(key);
            mxCurStage = stages.length ? Number(stages[0].n) : 1;
        }
        mxBuildStageBar();
        mxRefreshStageBarVisibility();
    };

    // Start staged playback for a detailed scene. Begins at the FIRST stage and
    // auto-advances one song per stage from there. Played-history is preserved
    // across activations (so re-entering the scene hands out songs not yet
    // played); it is only reset per-stage when that stage's pool is exhausted.
    function mxStartStaged(key) {
        mxStagedScene = key;
        mxCurScene = null; mxSceneMoods = null; mxSceneMoodIdx = 0;
        mxSetRandom(false);
        mxClearVarietyTimer();
        var stages = mxStagesOf(key);
        mxCurStage = stages.length ? Number(stages[0].n) : 1;
        mxBuildStageBar();
        mxRefreshStageBarVisibility();
        if (mxCur && !mxPaused) {
            mxPlayStage(mxCurStage, false);
        } else {
            // Deck idle/paused — selection happens when play is pressed
            // (mxTogglePlay → mxPlayStage). Just reflect the armed stage.
            mxMood = null;
            mxUpdateStageActive();
            mxUpdateActive();
            mxSetStatus(mxPaused ? ('\u23f8 stage ' + mxCurStage) : ('ready \u2014 stage ' + mxCurStage));
        }
    }

    // Leave staged mode (manual mood pick, random, or non-detailed scene).
    function mxExitStaged() {
        if (!mxStagedScene) return;
        mxStagedScene = null; mxCurStage = 0;
        mxRefreshStageBarVisibility();
    }

    // ── Stage bar UI ──────────────────────────────────────────────────
    function mxRefreshStageBarVisibility() {
        if (!stageWrap) return;
        stageWrap.style.display = (!mxCollapsed && mxStagedScene) ? '' : 'none';
    }

    function mxBuildStageBar() {
        if (!stageGrid) return;
        stageGrid.innerHTML = '';
        if (!mxStagedScene) return;
        if (stageTitle) {
            var def = mxStageDefFor(mxStagedScene);
            stageTitle.textContent = (def && def.label ? def.label : mxStagedScene).toUpperCase() + ' \u2014 STAGES';
        }
        mxStagesOf(mxStagedScene).forEach(function (st) {
            var btn = document.createElement('button');
            btn.dataset.stage = st.n;
            var t = (st.label || ('Stage ' + st.n)) + (st.time ? ' (' + st.time + ')' : '');
            btn.title = t + (st.desc ? '\n' + st.desc : '');
            btn.innerHTML = '<span class="lp-mx-stage-n">' + st.n + '</span>' +
                            '<span class="lp-mx-stage-lbl">' + String(st.label || '').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</span>';
            btn.addEventListener('click', function () { mxSetStage(st.n); });
            stageGrid.appendChild(btn);
        });
        mxUpdateStageActive();
    }

    function mxUpdateStageActive() {
        if (!stageGrid) return;
        stageGrid.querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('active', Number(b.dataset.stage) === Number(mxCurStage));
        });
        if (stageNow) {
            var e = mxStagedScene ? mxStageEntry(mxStagedScene, mxCurStage) : null;
            stageNow.textContent = e ? (e.label || ('Stage ' + e.n)) : '';
        }
    }

    function mxTogglePlay() {
        if (!mxCur) {
            if (mxStagedScene) { mxPlayStage(mxCurStage, false); return; }
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
                    if (mxStagedScene) mxScheduleStaged(mxTrack);
                    else mxSchedule(mxMood, mxTrack, mxSongIdx);
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
        mxCollapsed = true; // default closed
        grid.style.display    = 'none';
        songRow.style.display = 'none';
        icon.innerHTML        = '+';
        mxRefreshStageBarVisibility();
        bar.addEventListener('click', function () {
            mxCollapsed = !mxCollapsed;
            grid.style.display    = mxCollapsed ? 'none' : '';
            songRow.style.display = mxCollapsed ? 'none' : '';
            icon.innerHTML        = mxCollapsed ? '+' : '&#8722;';
            mxRefreshStageBarVisibility();
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
            var url = (typeof window.MX_HELP_URL !== 'undefined') ? window.MX_HELP_URL : 'docs/music-help.php';
            if (helpWin && !helpWin.closed) { helpWin.focus(); return; }
            helpWin = window.open(url, 'mx-help', 'width=900,height=1640,resizable=yes,scrollbars=yes');
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
        // Detailed/staged scenes route to the stage engine instead of moods.
        if (mxIsStaged(key)) { mxStartStaged(key); return; }

        // Any non-staged scene leaves staged mode (e.g. SC2 → sc2-off).
        mxExitStaged();

        var moods = MX_SCENE_MAP[key];
        if (!moods || !moods.length) return;

        // Pick a random starting mood from the scene's list
        var randomIdx = Math.floor(Math.random() * moods.length);
        var newMood   = moods[randomIdx];

        // Every scene activation (including re-activating the same scene) gets a
        // fresh start on variety tracking. This way switching scenes always resets
        // the clock — the listener gets a full window before variety kicks in again.
        mxSetRandom(false);
        mxResetVariety(); // resets mxSceneCycles = 0 and restarts the wall-clock timer

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
    // Rebuild the stage bar (e.g. after the admin edits stage definitions).
    window.mxBuildStageBar = function () {
        if (mxStagedScene && !mxIsStaged(mxStagedScene)) mxExitStaged();
        mxBuildStageBar();
        mxRefreshStageBarVisibility();
    };

    // Read-only snapshot of the deck state for the status reporter (status-reporter.js).
    window.mxGetStatusState = function () {
        var stageEntry = mxStagedScene ? mxStageEntry(mxStagedScene, mxCurStage) : null;
        return {
            playing:         !!mxCur && !mxPaused,
            mood:            mxMood || null,
            mood_label:      mxMood ? (MX_LABELS[mxMood] || mxMood) : null,
            track:           mxTrack || null,
            random:          !!mxRandom,
            driven_by_scene: mxCurScene || mxStagedScene || null,
            staged_scene:    mxStagedScene || null,
            stage:           mxStagedScene ? mxCurStage : null,
            stage_label:     stageEntry ? (stageEntry.label || ('Stage ' + stageEntry.n)) : null
        };
    };

    // SC2 (animated): logo transition + player-intro video/WAV run over the stream.
    // Duck the mood deck on the master gain until transition ends and intro video
    // has finished, plus a tail so trailing WAV (e.g. FSL voiceover) does not clash.
    var MX_SC2_INTRO_FADE_OUT_S   = 0.75;
    var MX_SC2_INTRO_FADE_IN_S    = 1.0;
    var MX_SC2_INTRO_POST_VIDEO_MS = 1000;
    var MX_SC2_INTRO_FALLBACK_MS = 60000;
    var mxSc2IntroSeq = 0;
    var mxSc2IntroGates = null; // { gen, tr, vp } or null when idle / done
    var mxSc2IntroFallbackTimer = null;
    var mxSc2IntroVideoHandler = null;

    function mxClearSc2IntroFallback() {
        if (mxSc2IntroFallbackTimer) {
            clearTimeout(mxSc2IntroFallbackTimer);
            mxSc2IntroFallbackTimer = null;
        }
    }

    function mxSc2AnimatedIntroDetachVideoListener() {
        var vp = document.getElementById('video-player');
        if (vp && mxSc2IntroVideoHandler) {
            vp.removeEventListener('ended', mxSc2IntroVideoHandler);
            mxSc2IntroVideoHandler = null;
        }
    }

    function mxTrySc2IntroComplete() {
        if (!mxSc2IntroGates || !mxSc2IntroGates.tr || !mxSc2IntroGates.vp) return;
        mxClearSc2IntroFallback();
        mxSc2IntroGates = null;
        mxSc2AnimatedIntroDetachVideoListener();
        mxOnSceneChange('sc2');
        mxEnsure().then(function () {
            if (!mxGain) return;
            var targetVol = Number(volInput.value) / 100;
            var now = mx.currentTime;
            mxGain.gain.cancelScheduledValues(now);
            mxGain.gain.setValueAtTime(0, now);
            mxGain.gain.linearRampToValueAtTime(targetVol, now + MX_SC2_INTRO_FADE_IN_S);
        });
    }

    window.mxSc2IntroPeekSession = function () {
        return mxSc2IntroGates ? mxSc2IntroGates.gen : null;
    };

    window.mxSc2AnimatedIntroBegin = function () {
        mxClearSc2IntroFallback();
        mxSc2AnimatedIntroDetachVideoListener();
        mxSc2IntroSeq++;
        var session = mxSc2IntroSeq;
        mxSc2IntroGates = { gen: session, tr: false, vp: false };
        mxEnsure().then(function () {
            if (!mxGain) return;
            var now = mx.currentTime;
            var cur = mxGain.gain.value;
            mxGain.gain.cancelScheduledValues(now);
            mxGain.gain.setValueAtTime(cur, now);
            mxGain.gain.linearRampToValueAtTime(0, now + MX_SC2_INTRO_FADE_OUT_S);
        });
        mxSc2IntroFallbackTimer = setTimeout(function () {
            mxSc2IntroFallbackTimer = null;
            if (mxSc2IntroGates && mxSc2IntroGates.gen === session) {
                mxSc2IntroGates.tr = true;
                mxSc2IntroGates.vp = true;
                mxTrySc2IntroComplete();
            }
        }, MX_SC2_INTRO_FALLBACK_MS);
    };

    window.mxSc2AnimatedIntroArmIntroVideoListener = function () {
        var vp = document.getElementById('video-player');
        if (!vp || !mxSc2IntroGates) return;
        var session = mxSc2IntroGates.gen;
        mxSc2AnimatedIntroDetachVideoListener();
        mxSc2IntroVideoHandler = function () {
            vp.removeEventListener('ended', mxSc2IntroVideoHandler);
            mxSc2IntroVideoHandler = null;
            setTimeout(function () {
                if (typeof window.mxSc2AnimatedIntroMarkIntroVideoDone === 'function') {
                    window.mxSc2AnimatedIntroMarkIntroVideoDone(session);
                }
            }, MX_SC2_INTRO_POST_VIDEO_MS);
        };
        vp.addEventListener('ended', mxSc2IntroVideoHandler);
    };

    window.mxSc2AnimatedIntroMarkTransitionDone = function (expectedGen) {
        if (!mxSc2IntroGates) return;
        if (expectedGen != null && mxSc2IntroGates.gen !== expectedGen) return;
        mxSc2IntroGates.tr = true;
        mxTrySc2IntroComplete();
    };

    window.mxSc2AnimatedIntroMarkIntroVideoDone = function (expectedGen) {
        if (!mxSc2IntroGates) return;
        if (expectedGen != null && mxSc2IntroGates.gen !== expectedGen) return;
        mxSc2IntroGates.vp = true;
        mxTrySc2IntroComplete();
    };

    window.mxSc2AnimatedIntroCancel = function () {
        mxClearSc2IntroFallback();
        mxSc2AnimatedIntroDetachVideoListener();
        if (!mxSc2IntroGates) return;
        mxSc2IntroGates = null;
        mxEnsure().then(function () {
            if (!mxGain) return;
            var targetVol = Number(volInput.value) / 100;
            var now = mx.currentTime;
            mxGain.gain.cancelScheduledValues(now);
            mxGain.gain.linearRampToValueAtTime(targetVol, now + 0.5);
        });
    };

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
        } else if (mxCur && mxPaused && mxWasPausedByYt) {
            /* Already paused for YT (e.g. closeYtIframeScene(true) then opening another YT clip). */
        } else {
            mxWasPausedByYt = false;
        }
    };
    window.mxResumeForYt = function() {
        if (!mxWasPausedByYt) return;
        if (!mxCur || !mxPaused) {
            mxWasPausedByYt = false;
            return;
        }
        mxWasPausedByYt = false;
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
                if (mxStagedScene) mxScheduleStaged(mxTrack);
                else mxSchedule(mxMood, mxTrack, mxSongIdx);
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
                /* Fade deck down before intro SFX / transition (must run before orig). */
                if (key === 'sc2' && !sc2WasOn && typeof window.mxSc2AnimatedIntroBegin === 'function') {
                    window.mxSc2AnimatedIntroBegin();
                }
            }

            orig.apply(this, arguments);

            if (typeof window.mxOnSceneChange !== 'function') return;

            if (key === 'sc2' || key === 'sc2-quick') {
                if (!sc2WasOn) {
                    /* SC2 animated: mood switch runs when mxSc2AnimatedIntro* signals done. */
                    if (key === 'sc2-quick') {
                        window.mxOnSceneChange(key);
                    } else if (key === 'sc2' && typeof window.mxPrepareStageBar === 'function') {
                        /* Show stage controls right away; staged playback starts at intro-complete. */
                        window.mxPrepareStageBar('sc2');
                    }
                } else {
                    if (typeof window.mxSc2AnimatedIntroCancel === 'function') {
                        window.mxSc2AnimatedIntroCancel();
                    }
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
