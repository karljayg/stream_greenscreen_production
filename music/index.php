<?php
$tracks = [
    'opening_high' => [
        'sc2_opening_high_heroic_01_A.mp3',
        'sc2_opening_high_heroic_01_B.mp3',
    ],
    'opening_alert' => [
        'sc2_opening_alert_suspense_01_A.mp3',
        'sc2_opening_alert_suspense_01_B.mp3',
    ],
    'combat_mid' => [
        'sc2_combat_mid_driving_01_A.mp3',
        'sc2_combat_mid_driving_01_B.mp3',
    ],
    'combat_high' => [
        'sc2_combat_high_aggressive_01_A.mp3',
        'sc2_combat_high_aggressive_01_B.mp3',
    ],
    'combat_extreme' => [
        'sc2_combat_extreme_clutch_01_A.mp3',
        'sc2_combat_extreme_clutch_01_B.mp3',
    ],
    'climax' => [
        'sc2_climax_finalpush_01_A.mp3',
        'sc2_climax_finalpush_01_B.mp3',
    ],
    'analysis' => [
        'sc2_analysis_light_clean_01_A.mp3',
        'sc2_analysis_light_clean_01_B.mp3',
    ],
    'chill' => [
        'sc2_chill_upbeat_warm_01_A.mp3',
        'sc2_chill_upbeat_warm_01_B.mp3',
    ],
    'replay' => [
        'sc2_replay_clean_focused_01_A.mp3',
        'sc2_replay_clean_focused_01_B.mp3',
    ],
    'victory' => [
        'sc2_victory_high_triumphant_01_A.mp3',
        'sc2_victory_high_triumphant_01_B.mp3',
    ],
    'defeat' => [
        'sc2_defeat_neutral_reset_01_A.mp3',
        'sc2_defeat_neutral_reset_01_B.mp3',
    ],
    'suspense' => [
        'sc2_suspense_mid_dark_01_A.mp3',
        'sc2_suspense_mid_dark_01_B.mp3',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SC2 Music</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 16px;
        background: #0a0a0a;
        font-family: 'Segoe UI', system-ui, sans-serif;
        display: flex;
        justify-content: center;
    }

    /* ── Widget shell ── */
    .widget {
        width: 300px;
        background: #131313;
        border: 1px solid #2a2a2a;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,.6);
    }

    /* ── Header bar ── */
    .w-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px 8px 12px;
        background: #1a1a1a;
        border-bottom: 1px solid #222;
    }
    .w-title {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .12em;
        color: #555;
        text-transform: uppercase;
    }
    .transport {
        display: flex;
        gap: 2px;
    }
    .transport button {
        width: 28px;
        height: 28px;
        background: none;
        border: 1px solid #2c2c2c;
        border-radius: 6px;
        color: #888;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, color .15s, border-color .15s;
        padding: 0;
    }
    .transport button:hover {
        background: #252525;
        color: #ccc;
        border-color: #444;
    }
    .transport button.active-btn {
        background: #0d3d1a;
        color: #22dd66;
        border-color: #1a6630;
    }
    .transport button#stopBtn:hover    { color: #f55; border-color: #633; }
    .transport button#pauseBtn.paused  { color: #ffb800; border-color: #664a00; }

    /* ── Now playing ── */
    .now-playing {
        padding: 10px 12px 8px;
        border-bottom: 1px solid #1e1e1e;
        min-height: 54px;
    }
    .np-mood {
        font-size: 15px;
        font-weight: 600;
        color: #22dd66;
        letter-spacing: .04em;
        line-height: 1.2;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .np-mood.none { color: #383838; }
    .np-track {
        font-size: 10px;
        color: #484848;
        font-family: Consolas, monospace;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ── Slider rows ── */
    .sliders {
        padding: 8px 12px;
        border-bottom: 1px solid #1e1e1e;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .slider-row {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .slider-label {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .1em;
        color: #444;
        text-transform: uppercase;
        width: 30px;
        flex-shrink: 0;
    }
    .slider-val {
        font-size: 10px;
        color: #555;
        font-family: Consolas, monospace;
        width: 28px;
        text-align: right;
        flex-shrink: 0;
    }
    input[type="range"] {
        flex: 1;
        -webkit-appearance: none;
        height: 3px;
        border-radius: 2px;
        background: #2a2a2a;
        outline: none;
        cursor: pointer;
    }
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #22dd66;
        border: none;
        cursor: pointer;
    }
    input[type="range"]::-moz-range-thumb {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #22dd66;
        border: none;
        cursor: pointer;
    }

    /* ── Mood grid ── */
    .mood-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px;
        padding: 8px 10px;
        border-bottom: 1px solid #1e1e1e;
    }
    .mood-grid button {
        background: #1c1c1c;
        color: #666;
        border: 1px solid #252525;
        border-radius: 5px;
        padding: 5px 4px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .03em;
        cursor: pointer;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background .12s, color .12s, border-color .12s;
        text-transform: uppercase;
    }
    .mood-grid button:hover {
        background: #252525;
        color: #aaa;
        border-color: #3a3a3a;
    }
    .mood-grid button.active {
        background: #0d3d1a;
        color: #22dd66;
        border-color: #1a6630;
    }

    /* ── Status bar ── */
    .w-status {
        padding: 5px 12px;
        font-size: 10px;
        color: #3a3a3a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 22px;
    }
    .w-status.active { color: #555; }
    .w-status.error  { color: #c44; }
</style>
</head>
<body>

<div class="widget">

    <div class="w-header">
        <span class="w-title">SC2 Music</span>
        <div class="transport">
            <button id="startAudioBtn" title="Enable audio">&#9654;</button>
            <button id="pauseBtn"      title="Pause / Resume">&#9646;&#9646;</button>
            <button id="stopBtn"       title="Stop">&#9632;</button>
        </div>
    </div>

    <div class="now-playing">
        <div class="np-mood none" id="currentMood">No mood selected</div>
        <div class="np-track"    id="currentTrack">—</div>
    </div>

    <div class="sliders">
        <div class="slider-row">
            <span class="slider-label">Vol</span>
            <input type="range" id="masterVolume" min="0" max="1" step="0.01" value="0.8">
            <span class="slider-val" id="volumeValue">0.80</span>
        </div>
        <div class="slider-row">
            <span class="slider-label">Fade</span>
            <input type="range" id="fadeSeconds" min="0" max="10" step="0.5" value="4">
            <span class="slider-val" id="fadeValue">4.0s</span>
        </div>
    </div>

    <div class="mood-grid" id="moodButtons"></div>

    <div class="w-status" id="statusText">Click &#9654; to enable audio</div>

</div>

<script>
const TRACKS = <?php echo json_encode($tracks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

const MOOD_LABELS = {
    opening_high:    'Open High',
    opening_alert:   'Alert',
    combat_mid:      'Combat',
    combat_high:     'Combat+',
    combat_extreme:  'Extreme',
    climax:          'Climax',
    analysis:        'Analysis',
    chill:           'Chill',
    replay:          'Replay',
    victory:         'Victory',
    defeat:          'Defeat',
    suspense:        'Suspense',
};

let audioContext = null;
let masterGain   = null;
let currentDeck  = null;
let nextDeck     = null;
let isPaused     = false;
let currentMood  = null;
let currentTrack = null;

let autoAdvanceTimer = null;
let pendingAutoMood  = null;

const currentMoodEl  = document.getElementById('currentMood');
const currentTrackEl = document.getElementById('currentTrack');
const statusTextEl   = document.getElementById('statusText');

const fadeSlider   = document.getElementById('fadeSeconds');
const fadeValueEl  = document.getElementById('fadeValue');
const volumeSlider = document.getElementById('masterVolume');
const volumeValueEl= document.getElementById('volumeValue');
const pauseBtn     = document.getElementById('pauseBtn');
const startBtn     = document.getElementById('startAudioBtn');

fadeSlider.addEventListener('input', () => {
    fadeValueEl.textContent = Number(fadeSlider.value).toFixed(1) + 's';
});

volumeSlider.addEventListener('input', () => {
    volumeValueEl.textContent = Number(volumeSlider.value).toFixed(2);
    if (masterGain) masterGain.gain.value = Number(volumeSlider.value);
});

startBtn.addEventListener('click', async () => {
    await ensureAudio();
    startBtn.classList.add('active-btn');
    setStatus('Audio enabled — select a mood');
});

document.getElementById('stopBtn').addEventListener('click', stopAllPlayback);
document.getElementById('pauseBtn').addEventListener('click', togglePauseResume);

function setStatus(text, type = 'active') {
    statusTextEl.textContent = text;
    statusTextEl.className = 'w-status ' + type;
}

function createMoodButtons() {
    const container = document.getElementById('moodButtons');
    Object.keys(TRACKS).forEach(mood => {
        const btn = document.createElement('button');
        btn.textContent = MOOD_LABELS[mood] || mood;
        btn.dataset.mood = mood;
        btn.title = mood;
        btn.addEventListener('click', () => switchMood(mood));
        container.appendChild(btn);
    });
}

function updateActiveMoodButton() {
    document.querySelectorAll('#moodButtons button').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mood === currentMood);
    });
}

async function ensureAudio() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        masterGain = audioContext.createGain();
        masterGain.gain.value = Number(volumeSlider.value);
        masterGain.connect(audioContext.destination);
    }
    if (audioContext.state === 'suspended') await audioContext.resume();
}

function getAlternateTrackForMood(mood, currentFile = null) {
    const files = TRACKS[mood] || [];
    if (!files.length) return null;
    if (files.length === 1) return files[0];
    if (!currentFile) return files[Math.floor(Math.random() * files.length)];
    return files.find(f => f !== currentFile) || files[0];
}

function getInitialTrackForMood(mood) {
    const files = TRACKS[mood] || [];
    if (!files.length) return null;
    return files[Math.floor(Math.random() * files.length)];
}

function createDeck(file) {
    const audio  = new Audio(file);
    audio.crossOrigin = 'anonymous';
    audio.loop    = false;
    audio.preload = 'auto';

    const source = audioContext.createMediaElementSource(audio);
    const gain   = audioContext.createGain();
    gain.gain.value = 0;
    source.connect(gain);
    gain.connect(masterGain);

    return { audio, source, gain, file };
}

async function waitForCanPlay(audio) {
    if (audio.readyState >= 3) return;
    await new Promise((resolve, reject) => {
        const onCanPlay = () => { cleanup(); resolve(); };
        const onError   = () => { cleanup(); reject(new Error('Failed to load audio')); };
        const cleanup   = () => {
            audio.removeEventListener('canplay', onCanPlay);
            audio.removeEventListener('error',   onError);
        };
        audio.addEventListener('canplay', onCanPlay);
        audio.addEventListener('error',   onError);
        audio.load();
    });
}

function clearAutoAdvanceTimer() {
    if (autoAdvanceTimer) { clearTimeout(autoAdvanceTimer); autoAdvanceTimer = null; }
    pendingAutoMood = null;
}

function scheduleAutoAdvance(mood, file) {
    clearAutoAdvanceTimer();
    if (!currentDeck || !currentDeck.audio || isPaused) return;

    const fadeDuration = Number(fadeSlider.value);
    const duration     = currentDeck.audio.duration;

    if (!isFinite(duration) || duration <= 0) return;

    const remainingUntilFade = Math.max(0, duration - currentDeck.audio.currentTime - fadeDuration - 0.15);
    pendingAutoMood = mood;

    autoAdvanceTimer = setTimeout(async () => {
        if (currentMood !== mood) return;
        if (!currentDeck || currentDeck.file !== file) return;
        if (isPaused) return;

        const nextFile = getAlternateTrackForMood(mood, file);
        if (!nextFile || nextFile === file) return;
        await switchMood(mood, { forceFile: nextFile, autoAdvance: true });
    }, remainingUntilFade * 1000);
}

async function switchMood(mood, options = {}) {
    try {
        await ensureAudio();
        startBtn.classList.add('active-btn');
        clearAutoAdvanceTimer();

        const forceFile   = options.forceFile  || null;
        const autoAdvance = options.autoAdvance || false;

        let file = forceFile;
        if (!file) {
            file = currentMood === mood
                ? getAlternateTrackForMood(mood, currentTrack)
                : getInitialTrackForMood(mood);
        }

        if (!file) { setStatus('No tracks for: ' + mood, 'error'); return; }

        const fadeDuration = Number(fadeSlider.value);
        setStatus('Loading…');
        currentMoodEl.textContent = MOOD_LABELS[mood] || mood;
        currentMoodEl.classList.remove('none');

        nextDeck = createDeck(file);
        await waitForCanPlay(nextDeck.audio);

        const now = audioContext.currentTime;
        nextDeck.gain.gain.cancelScheduledValues(now);
        nextDeck.gain.gain.setValueAtTime(0, now);
        await nextDeck.audio.play();

        if (!currentDeck || fadeDuration === 0) {
            nextDeck.gain.gain.linearRampToValueAtTime(1, now + 0.05);
            if (currentDeck) {
                try { currentDeck.audio.pause(); currentDeck.audio.currentTime = 0; } catch(e) {}
            }
            currentDeck = nextDeck;
            nextDeck    = null;
        } else {
            currentDeck.gain.gain.cancelScheduledValues(now);
            currentDeck.gain.gain.setValueAtTime(currentDeck.gain.gain.value, now);
            currentDeck.gain.gain.linearRampToValueAtTime(0, now + fadeDuration);
            nextDeck.gain.gain.linearRampToValueAtTime(1,  now + fadeDuration);

            const oldDeck      = currentDeck;
            const promotedDeck = nextDeck;

            setTimeout(() => {
                try { oldDeck.audio.pause(); oldDeck.audio.currentTime = 0; } catch(e) {}
            }, fadeDuration * 1000 + 100);

            currentDeck = promotedDeck;
            nextDeck    = null;
        }

        currentMood  = mood;
        currentTrack = file;
        currentTrackEl.textContent = file;
        updateActiveMoodButton();
        isPaused = false;
        pauseBtn.classList.remove('paused');

        scheduleAutoAdvance(mood, file);
        setStatus(autoAdvance ? 'Auto → ' + file : 'Playing: ' + (MOOD_LABELS[mood] || mood));

    } catch (err) {
        console.error(err);
        setStatus(err.message, 'error');
    }
}

function stopAllPlayback() {
    clearAutoAdvanceTimer();
    try {
        if (currentDeck) { currentDeck.audio.pause(); currentDeck.audio.currentTime = 0; }
        if (nextDeck)    { nextDeck.audio.pause();    nextDeck.audio.currentTime = 0;    }
    } catch(e) {}

    currentDeck  = null;
    nextDeck     = null;
    currentMood  = null;
    currentTrack = null;
    isPaused     = false;

    currentMoodEl.textContent = 'No mood selected';
    currentMoodEl.classList.add('none');
    currentTrackEl.textContent = '—';
    pauseBtn.classList.remove('paused');
    updateActiveMoodButton();
    setStatus('Stopped', 'active');
}

async function togglePauseResume() {
    if (!currentDeck) { setStatus('Nothing playing'); return; }
    await ensureAudio();

    if (isPaused) {
        await currentDeck.audio.play();
        isPaused = false;
        pauseBtn.classList.remove('paused');
        scheduleAutoAdvance(currentMood, currentTrack);
        setStatus('Playing: ' + (MOOD_LABELS[currentMood] || currentMood));
    } else {
        clearAutoAdvanceTimer();
        currentDeck.audio.pause();
        isPaused = true;
        pauseBtn.classList.add('paused');
        setStatus('Paused');
    }
}

createMoodButtons();
fadeValueEl.textContent  = Number(fadeSlider.value).toFixed(1) + 's';
volumeValueEl.textContent = Number(volumeSlider.value).toFixed(2);
</script>

</body>
</html>
