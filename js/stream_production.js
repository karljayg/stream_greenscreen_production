function productionAudioBase() {
	return (typeof window.getProductionAudioBase === 'function' && window.getProductionAudioBase()) || 'production_files/audio/';
}
function productionVideoBase() {
	return (typeof window.getProductionVideoBase === 'function' && window.getProductionVideoBase()) || 'production_files/video/';
}
function productionImageBase() {
	return (typeof window.getProductionImagesBase === 'function' && window.getProductionImagesBase()) || 'production_files/images/';
}
function resolveMediaUrl(path) {
	return typeof window.resolveProductionUrl === 'function' ? window.resolveProductionUrl(path) : path;
}

function applyMediaCORSIfNeeded(mediaEl, resolvedSrc) {
	if (typeof window.applyAnonymousCORSIfNeeded === 'function') {
		window.applyAnonymousCORSIfNeeded(mediaEl, resolvedSrc);
	}
}

// Spider chart: PHP injects window.STREAM_FSL_SPIDER_PLAYER_URL from same origin as production_files remote (see production-files-bootstrap.php).
var spiderChartBaseUrl = (typeof window.STREAM_FSL_SPIDER_PLAYER_URL === 'string' && window.STREAM_FSL_SPIDER_PLAYER_URL.trim())
	? window.STREAM_FSL_SPIDER_PLAYER_URL.trim()
	: 'https://psistorm.com/fsl/view_spider_chart_player.php';

const _v = window.ASSET_VERSION || Date.now();
const { default: playerList } = await import(`./playerlist.js?v=${_v}`);
const { gifFiles, randomAudioFiles } = await import(`./other_lists.js?v=${_v}`);

// FSL rankings: local cache for player intro stats (season/all-time W-L)
let rankingsCache = [];
async function loadRankings() {
	try {
		const r = await fetch('rankings.php?_t=' + Date.now(), { cache: 'no-store' });
		const data = await r.json();
		rankingsCache = Array.isArray(data) ? data : [];
	} catch (e) {
		rankingsCache = [];
	}
}
function getRankingForPlayer(playerName) {
	if (!playerName || !rankingsCache.length) return null;
	const nameLower = playerName.trim().toLowerCase();
	return rankingsCache.find((p) => p.name && String(p.name).toLowerCase() === nameLower) || null;
}
function setPlayerIntroContent(playerName) {
	const box = document.querySelector('.player-name-box');
	if (!box) return;
	const name = String(playerName || '').trim();
	const rank = getRankingForPlayer(name);
	box.innerHTML = '';
	const nameWrap = document.createElement('span');
	nameWrap.className = 'player-intro-name';
	if (rank) {
		const rankSpan = document.createElement('span');
		rankSpan.className = 'player-intro-rank';
		rankSpan.textContent = `#${rank.rank} `;
		nameWrap.appendChild(rankSpan);
		nameWrap.appendChild(document.createTextNode(name));
		if (rank.group != null && rank.group !== '') {
			const groupSpan = document.createElement('span');
			groupSpan.className = 'player-intro-group';
			groupSpan.textContent = ` (G${rank.group})`;
			nameWrap.appendChild(groupSpan);
		}
	} else {
		nameWrap.textContent = name || '';
	}
	box.appendChild(nameWrap);
	if (rank) {
		const statsDiv = document.createElement('div');
		statsDiv.className = 'player-intro-stats';
		const s = rank;
		const season = `Season ${s.season_gw ?? 0}-${s.season_gl ?? 0}`;
		const alltime = `All-time ${s.alltime_gw ?? 0}-${s.alltime_gl ?? 0}`;
		statsDiv.textContent = `${season} · ${alltime}`;
		box.appendChild(statsDiv);
	}
}
/** Populate a target element with player name, rank, and stats (same as player intro). Used by external chart overlay. */
function setPlayerLabelContent(box, playerName) {
	if (!box) return;
	const name = String(playerName || '').trim();
	const rank = getRankingForPlayer(name);
	box.innerHTML = '';
	const nameWrap = document.createElement('span');
	nameWrap.className = 'player-intro-name';
	if (rank) {
		const rankSpan = document.createElement('span');
		rankSpan.className = 'player-intro-rank';
		rankSpan.textContent = `#${rank.rank} `;
		nameWrap.appendChild(rankSpan);
		nameWrap.appendChild(document.createTextNode(name));
		if (rank.group != null && rank.group !== '') {
			const groupSpan = document.createElement('span');
			groupSpan.className = 'player-intro-group';
			groupSpan.textContent = ` (G${rank.group})`;
			nameWrap.appendChild(groupSpan);
		}
	} else {
		nameWrap.textContent = name || '';
	}
	box.appendChild(nameWrap);
	if (rank) {
		const statsDiv = document.createElement('div');
		statsDiv.className = 'player-intro-stats';
		const s = rank;
		const season = `Season ${s.season_gw ?? 0}-${s.season_gl ?? 0}`;
		const alltime = `All-time ${s.alltime_gw ?? 0}-${s.alltime_gl ?? 0}`;
		statsDiv.textContent = `${season} · ${alltime}`;
		box.appendChild(statsDiv);
	}
}
loadRankings();
if (typeof window !== 'undefined') {
	window.reloadRankingsCache = loadRankings;
	window.getRankingForPlayer = getRankingForPlayer;
	window.setPlayerLabelContent = setPlayerLabelContent;
	window.STREAM_PLAYER_LIST = playerList;
}

// Chroma key: makes green transparent on video/GIF player intros
function isChromaKeyEnabled() {
	const cb = document.getElementById('chroma-key-cb');
	return cb && cb.checked;
}

function applyChromaKeyToContext(ctx, w, h) {
	try {
		const data = ctx.getImageData(0, 0, w, h);
		const d = data.data;
		for (let i = 0; i < d.length; i += 4) {
			const r = d[i], g = d[i + 1], b = d[i + 2];
			// Green screen: G dominant, R and B relatively low
			const greenness = g - Math.max(r, b);
			const isGreen = g > 60 && greenness > 40 && (r + b) < g * 1.2;
			if (isGreen) d[i + 3] = 0;
		}
		ctx.putImageData(data, 0, 0);
	} catch (e) {
		console.warn('Chroma key: canvas tainted (CORS). Ensure video/image is same-origin or has CORS headers.', e);
	}
}

let videoChromaRaf = null;
function startVideoChromaLoop(video, canvas) {
	let videoHidden = false;
	function draw() {
		if (!isChromaKeyEnabled() || video.paused || video.ended) {
			videoChromaRaf = null;
			canvas.style.display = 'none';
			video.style.display = 'block';
			return;
		}
		const vw = video.videoWidth;
		const vh = video.videoHeight;
		if (!vw || !vh) {
			videoChromaRaf = requestAnimationFrame(draw);
			return;
		}
		if (canvas.width !== vw || canvas.height !== vh) {
			canvas.width = vw;
			canvas.height = vh;
		}
		const ctx = canvas.getContext('2d');
		ctx.drawImage(video, 0, 0, vw, vh);
		applyChromaKeyToContext(ctx, vw, vh);
		if (!videoHidden) {
			video.style.display = 'none';
			canvas.style.display = 'block';
			videoHidden = true;
		}
		videoChromaRaf = requestAnimationFrame(draw);
	}
	if (videoChromaRaf) cancelAnimationFrame(videoChromaRaf);
	draw();
}

function stopVideoChromaLoop() {
	if (videoChromaRaf) {
		cancelAnimationFrame(videoChromaRaf);
		videoChromaRaf = null;
	}
	const video = document.getElementById('video-player');
	const canvas = document.getElementById('video-chroma-canvas');
	if (video) video.style.display = 'block';
	if (canvas) canvas.style.display = 'none';
}

let gifChromaRaf = null;
let gifChromaTimeout = null;
function startGifChromaLoop(img, canvas, durationMs) {
	function draw() {
		if (!isChromaKeyEnabled() || !img.parentElement || img.parentElement.style.display === 'none') {
			gifChromaRaf = null;
			canvas.style.display = 'none';
			img.style.display = '';
			return;
		}
		const w = img.naturalWidth || img.clientWidth;
		const h = img.naturalHeight || img.clientHeight;
		if (w && h) {
			if (canvas.width !== w || canvas.height !== h) {
				canvas.width = w;
				canvas.height = h;
			}
			const ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, w, h);
			ctx.drawImage(img, 0, 0, w, h);
			applyChromaKeyToContext(ctx, w, h);
		}
		gifChromaRaf = requestAnimationFrame(draw);
	}
	if (gifChromaRaf) cancelAnimationFrame(gifChromaRaf);
	if (gifChromaTimeout) clearTimeout(gifChromaTimeout);
	img.style.display = 'none';
	canvas.style.display = 'block';
	draw();
	gifChromaTimeout = setTimeout(() => {
		gifChromaTimeout = null;
		if (gifChromaRaf) cancelAnimationFrame(gifChromaRaf);
		gifChromaRaf = null;
		canvas.style.display = 'none';
		img.style.display = '';
	}, durationMs);
}

const forms = document.querySelectorAll('.media-form');
const audioPlayer = document.querySelector('#audio-player');

/** Wav paths for chained playback on #audio-player (shared across media forms; not per-form closure). */
let introWavQueue = [];

// add an event listener for the "ended" event on the audio player
audioPlayer.addEventListener('ended', function() {
	const el = this;
	if (!introWavQueue.length) return;
	const nextAudioPath = introWavQueue.shift();
	const nextResolved = resolveMediaUrl(nextAudioPath);
	applyMediaCORSIfNeeded(el, nextResolved);
	el.setAttribute('src', nextResolved);
	el.load();
	el.volume = document.getElementById('volume-slider').value / 100;
	el.play();
});

// Attach the functions to the global window object to make them accessible in the HTML
window.toggleStatus = function(btn) {
    const statusSection = document.getElementById("status-section");
    if (statusSection.style.display === "none" || statusSection.style.display === "") {
        statusSection.style.display = "block";
    } else {
        statusSection.style.display = "none";
    }
    if (btn) btn.classList.toggle('open', statusSection.style.display === 'block');
}

window.togglePlayerRatings = function(btn) {
    const playerRatingsSection = document.getElementById("player-ratings-section");
    if (playerRatingsSection.style.display === "none" || playerRatingsSection.style.display === "") {
        playerRatingsSection.style.display = "block";
    } else {
        playerRatingsSection.style.display = "none";
    }
    if (btn) btn.classList.toggle('open', playerRatingsSection.style.display === 'block');
}

window.showFormattedResult = function(btn) {
    const statusResult = document.getElementById("right-column-result"); // Target the right-column div for the result
    
    // Check if the status result is already displayed, if yes, hide it
    if (statusResult.style.display === "block") {
        statusResult.style.display = "none"; // Hide the result
    } else {
        // If hidden, display the formatted result
        const title = document.getElementById("status-title").value;
        const teamA = document.getElementById("status-teamA").value;
        const teamB = document.getElementById("status-teamB").value;
        const valueA = document.getElementById("status-valueA").value;
        const valueB = document.getElementById("status-valueB").value;

        const resultHTML = `
            <div style="
                position: relative;
                left: ${displayPositions.status.left};
                top: ${displayPositions.status.top};
                transform: scale(${displayPositions.status.scale});
                transform-origin: top left;
                width: 100%;
            ">
                <h2>${title}</h2>
                <div style="display: flex; justify-content: center;">
                    <div style="margin-right: 20px;">
                        <p><strong>${teamA}</strong></p>
                        <p>${valueA}</p>
                    </div>
                    <div style="margin-left: 20px;">
                        <p><strong>${teamB}</strong></p>
                        <p>${valueB}</p>
                    </div>
                </div>
            </div>
        `;

        statusResult.innerHTML = resultHTML;
        statusResult.style.display = "block"; // Show the result
    }
    if (btn) btn.textContent = (statusResult.style.display === "block") ? "Hide Status" : "Show Status";
}

function playRandomAudio() {
    const modeEl = document.querySelector('input[name="music-mode"]:checked');
    const mode = modeEl ? modeEl.value : 'sequence';
    let audioPath;
    if (mode === 'sequence') {
        const key = 'stream_production_music_index';
        let idx = parseInt(localStorage.getItem(key) || '0', 10);
        if (isNaN(idx) || idx < 0 || idx >= randomAudioFiles.length) idx = 0;
        audioPath = randomAudioFiles[idx];
        localStorage.setItem(key, (idx + 1) % randomAudioFiles.length);
    } else {
        audioPath = randomAudioFiles[Math.floor(Math.random() * randomAudioFiles.length)];
    }
    const audio = new Audio(resolveMediaUrl(audioPath));
    audio.volume = document.getElementById('volume-slider').value / 100;
    audio.play();
}

const playerNameBox = document.querySelector('.player-name-box');
const videoPlayer = document.querySelector('#video-player');
const errorMessage = document.querySelector('#error-message');
const videoContainer = document.querySelector('#video-container');

function hidePlayerIntroTitle() {
	if (playerNameBox) playerNameBox.style.display = 'none';
}

function gifPlayer(gifIndex = 0) {
	const gifFileName = gifFiles.find(file => file[0] === gifIndex)[1];
	let gifPath = productionImageBase() + gifFileName;
	if (typeof window.ASSET_VERSION !== 'undefined') gifPath += '?v=' + window.ASSET_VERSION;
	gifPath += (gifPath.includes('?') ? '&' : '?') + 't=' + Date.now();

	const gifContainer = document.querySelector('#gif-container');
	const gifImage = document.querySelector('#gif-image');
	const gifTimeout = 8000;

	applyMediaCORSIfNeeded(gifImage, gifPath.split('?')[0]);
	gifImage.src = gifPath;
	gifContainer.style.display = 'flex';
	setTimeout(() => {
		gifContainer.style.display = 'none';
	}, gifTimeout);
}

function onIntroVideoEnded() {
	stopVideoChromaLoop();
	if (videoContainer) videoContainer.style.display = 'none';
	hidePlayerIntroTitle();
}

function playPlayerIntroByName(playerName) {
	// Notify the status reporter of every intro/GG press, regardless of caller
	// (form submit, suggestion click, SC2 scene, custom buttons).
	try {
		document.dispatchEvent(new CustomEvent('status:intro', { detail: { name: playerName } }));
	} catch (e) {}

	const matchingPlayer = playerList.find(p => p[0] === playerName);

	if (!matchingPlayer) {
		if (errorMessage) errorMessage.textContent = `Error: Player "${playerName}" not found.`;
		return false;
	}
	const videoPath = productionVideoBase() + matchingPlayer[1];

	introWavQueue.length = 0;
	if (matchingPlayer[2]) {
		introWavQueue.push(productionAudioBase() + matchingPlayer[2]);
	}

	videoPlayer.removeEventListener('ended', onIntroVideoEnded);

	const playNextAudio = (index) => {
		if (index < introWavQueue.length) {
			const audio = new Audio(resolveMediaUrl(introWavQueue[index]));
			audio.load();
			audio.volume = document.getElementById('volume-slider').value / 100;
			audio.play();
			audio.addEventListener('ended', () => {
				playNextAudio(index + 1);
			});
		} else {
			introWavQueue.length = 0;
		}
	};

	if (introWavQueue.length === 1) {
		playNextAudio(0);
	}

	applyMediaCORSIfNeeded(videoPlayer, videoPath);
	videoPlayer.setAttribute('src', videoPath);
	videoPlayer.load();

	setPlayerIntroContent(playerName);
	if (playerNameBox) playerNameBox.style.display = 'inline-block';

	if (matchingPlayer.length > 3) {
		switch (matchingPlayer[3]) {
		  case 'noTitle':
			hidePlayerIntroTitle();
			break;
		  case 'randomAudio':
			playRandomAudio();
			hidePlayerIntroTitle();
			break;
		  case 'gifPlayer':
			gifPlayer();
			hidePlayerIntroTitle();
			break;
		  default:
			try {
			  if (matchingPlayer[3].match(/-\d+$/)) {
				const gifIndex = parseInt(matchingPlayer[3].split('-')[1]);
				gifPlayer(gifIndex);
				hidePlayerIntroTitle();
			  } else {
				setPlayerIntroContent(playerName);
				if (playerNameBox) playerNameBox.style.display = 'inline-block';
			  }
			} catch (error) {
			  console.error('Error parsing GIF index:', error);
			  setPlayerIntroContent(playerName);
			  if (playerNameBox) playerNameBox.style.display = 'inline-block';
			}
			break;
		}
	}

	audioPlayer.pause();
	audioPlayer.removeAttribute('src');
	audioPlayer.load();

	videoPlayer.play().catch((error) => {
		if (errorMessage) errorMessage.textContent = `Error: ${error.message}`;
		console.error('Error playing video ' + videoPath + ': ', error);
	});
	if (videoContainer) videoContainer.style.display = 'flex';
	const videoChromaCanvas = document.getElementById('video-chroma-canvas');
	videoPlayer.addEventListener('ended', onIntroVideoEnded, { once: true });
	videoPlayer.addEventListener('playing', function onPlaying() {
		videoPlayer.removeEventListener('playing', onPlaying);
		if (isChromaKeyEnabled() && videoChromaCanvas) {
			startVideoChromaLoop(videoPlayer, videoChromaCanvas);
		}
	}, { once: true });
	return true;
}

window.playPlayerIntroByName = playPlayerIntroByName;

forms.forEach((form) => {

	const playerNameInput = form.querySelector('.player-name-input');

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		playPlayerIntroByName(playerNameInput.value.trim());
	});

	playerNameInput.addEventListener('input', (event) => {
		const inputValue = event.target.value.trim().toLowerCase();
		if (inputValue.length < 3) {
			clearSuggestions();
			return;
		}
		const matchingPlayers = playerList.filter(p => p[0].toLowerCase().includes(inputValue));
		if (matchingPlayers.length > 0) {
			showSuggestions(matchingPlayers);
		} else {
			clearSuggestions();
		}
	});

	playerNameInput.addEventListener('keydown', (e) => {
		if (e.key === 'Tab') {
			const suggestionList = playerNameInput.parentNode.querySelector('.suggestion-list');
			const items = suggestionList?.querySelectorAll('li');
			if (items?.length === 1) {
				playerNameInput.value = items[0].textContent;
				clearSuggestions();
			}
		}
	});

	playerNameInput.addEventListener('blur', () => {
		setTimeout(clearSuggestions, 100);
	});

	function showSuggestions(players) {
		const suggestionList = document.createElement('ul');
        suggestionList.setAttribute('id', 'suggestion-list');
		suggestionList.classList.add('suggestion-list');
		players.forEach(p => {
			const suggestionItem = document.createElement('li');
			suggestionItem.textContent = p[0];
			suggestionItem.addEventListener('mousedown', (e) => {
				e.preventDefault();
			});
			suggestionItem.addEventListener('click', () => {
				playerNameInput.value = p[0];
				clearSuggestions();
			});
			suggestionList.appendChild(suggestionItem);
		});
		clearSuggestions();
		playerNameInput.parentNode.appendChild(suggestionList);
	}

	function clearSuggestions() {
		const suggestionList = playerNameInput.parentNode.querySelector('.suggestion-list');
		if (suggestionList) {
			suggestionList.remove();
		}
	}
});

// Display positioning controls for each section
const displayPositions = {
    status: {
        left: '-30%',
        top: '0%', 
        scale: '1.0'
    },
    externalChart: {
        left: '-40%',
        top: '-10%',
        scale: '1.1'
    }
};

// Expose positioning and spider chart URL to global window for HTML access
window.displayPositions = displayPositions;
window.spiderChartBaseUrl = spiderChartBaseUrl;
window.playRandomAudio = playRandomAudio;

document.addEventListener('DOMContentLoaded', function() {
    const chromaCb = document.getElementById('chroma-key-cb');
    if (chromaCb) {
        chromaCb.addEventListener('change', function() {
            const videoContainer = document.getElementById('video-container');
            const videoPlayer = document.getElementById('video-player');
            const videoChromaCanvas = document.getElementById('video-chroma-canvas');
            const gifContainer = document.getElementById('gif-container');
            const gifImage = document.getElementById('gif-image');
            const gifChromaCanvas = document.getElementById('gif-chroma-canvas');
            if (isChromaKeyEnabled()) {
                if (videoContainer && videoContainer.style.display !== 'none' && videoPlayer && !videoPlayer.paused && !videoPlayer.ended && videoChromaCanvas) {
                    startVideoChromaLoop(videoPlayer, videoChromaCanvas);
                }
                if (gifContainer && gifContainer.style.display !== 'none' && gifImage && gifChromaCanvas) {
                    startGifChromaLoop(gifImage, gifChromaCanvas, 8000);
                }
            } else {
                stopVideoChromaLoop();
            }
        });
    }

    // Spider Chart Player Input Autocomplete
    const spiderPlayerInput = document.getElementById('player-input');
    if (spiderPlayerInput) {
        spiderPlayerInput.addEventListener('input', (event) => {
            const inputValue = event.target.value.trim().toLowerCase();
            if (inputValue.length < 3) {
                clearSpiderSuggestions();
                return;
            }
            const matchingPlayers = playerList.filter(p => p[0].toLowerCase().includes(inputValue));
            if (matchingPlayers.length > 0) {
                showSpiderSuggestions(matchingPlayers);
            } else {
                clearSpiderSuggestions();
            }
        });
        
        spiderPlayerInput.addEventListener('blur', () => {
            setTimeout(clearSpiderSuggestions, 300);
        });
    }
});

function showSpiderSuggestions(players) {
    const spiderPlayerInput = document.getElementById('player-input');
    
    // Get input position relative to viewport
    const inputRect = spiderPlayerInput.getBoundingClientRect();
    
    const suggestionList = document.createElement('ul');
    suggestionList.setAttribute('id', 'spider-suggestion-list');
    suggestionList.classList.add('suggestion-list');
    
    // Position absolutely to appear right under the input
    suggestionList.style.cssText = `
        position: fixed;
        top: ${inputRect.bottom + window.scrollY}px;
        left: ${inputRect.left + window.scrollX}px;
        width: ${inputRect.width}px;
        background: white;
        border: 1px solid #ccc;
        z-index: 1000;
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    `;
    
    players.forEach(p => {
        const suggestionItem = document.createElement('li');
        suggestionItem.textContent = p[0];
        suggestionItem.style.cssText = `
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        `;
        suggestionItem.addEventListener('mouseenter', () => {
            suggestionItem.style.backgroundColor = '#f0f0f0';
        });
        suggestionItem.addEventListener('mouseleave', () => {
            suggestionItem.style.backgroundColor = 'white';
        });
        suggestionItem.addEventListener('mousedown', (e) => {
            e.preventDefault(); // Prevent blur from firing
        });
        suggestionItem.addEventListener('click', (e) => {
            e.preventDefault();
            spiderPlayerInput.value = p[0];
            clearSpiderSuggestions();
            spiderPlayerInput.focus();
        });
        suggestionList.appendChild(suggestionItem);
    });
    
    clearSpiderSuggestions();
    document.body.appendChild(suggestionList);
}

function clearSpiderSuggestions() {
    const suggestionList = document.querySelector('#spider-suggestion-list');
    if (suggestionList) {
        suggestionList.remove();
    }
}
