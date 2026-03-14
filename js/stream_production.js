var projectpath = "psistorm.com/tools/stream_greenscreen_production"; // base path for asset URLs; test by running Random Music
var masterpath = ".";
var production_files = masterpath + "/production_files";
var audiopath = production_files + "/audio/";
var videopath = production_files + "/video/";
var imagepath = production_files + "/images/";

// Spider Chart URL Configuration - Change this for live vs dev
//var spiderChartBaseUrl = "http://localhost/psistorm.com/fsl/view_spider_chart_player.php";
// For LIVE use: 
var spiderChartBaseUrl = "https://psistorm.com/fsl/view_spider_chart_player.php";

import playerList from './playerlist.js';
import { gifFiles, randomAudioFiles } from './other_lists.js';

// FSL rankings: local cache for player intro stats (season/all-time W-L)
let rankingsCache = [];
async function loadRankings() {
	try {
		const r = await fetch('rankings.php');
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

// add an event listener for the "ended" event on the audio player
audioPlayer.addEventListener('ended', function() {
	const audioPlayer = this;
	// play the next audio file in the array
	if (audioFiles.length > 0) {
		const nextAudioPath = audioFiles.shift();
		audioPlayer.setAttribute('src', nextAudioPath);
		audioPlayer.load();
        audioPlayer.volume = document.getElementById('volume-slider').value / 100;
		audioPlayer.play();
	}
});

// Attach the functions to the global window object to make them accessible in the HTML
window.toggleStatus = function(btn) {
    const statusSection = document.getElementById("status-section");
    if (statusSection.style.display === "none" || statusSection.style.display === "") {
        statusSection.style.display = "block";
    } else {
        statusSection.style.display = "none";
    }
    if (btn) btn.textContent = (statusSection.style.display === "block") ? "Hide Status" : "Show Status";
}

window.togglePlayerRatings = function(btn) {
    const playerRatingsSection = document.getElementById("player-ratings-section");
    if (playerRatingsSection.style.display === "none" || playerRatingsSection.style.display === "") {
        playerRatingsSection.style.display = "block";
    } else {
        playerRatingsSection.style.display = "none";
    }
    if (btn) btn.textContent = (playerRatingsSection.style.display === "block") ? "Hide Spider Ratings" : "Show Spider Ratings";
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

forms.forEach((form) => {

	// get the player name input and the player name box elements
	const playerNameBox = document.querySelector('.player-name-box');
	const playerNameInput = form.querySelector('.player-name-input');
	const videoPlayer = document.querySelector('#video-player');
	const audioPlayer = document.querySelector('#audio-player');
	const errorMessage = document.querySelector('#error-message');
	const videoContainer = document.querySelector('#video-container');

	let audioFiles = [];

	const onVideoEnded = () => {
		stopVideoChromaLoop();
		videoContainer.style.display = 'none';
		playerNameBox.style.display = 'none';
	};

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		const playerName = playerNameInput.value.trim();
		const matchingPlayer = playerList.find(p => p[0] === playerName);

		if (!matchingPlayer) {
			errorMessage.textContent = `Error: Player "${playerName}" not found.`;
			return;
		}
		const videoPath = videopath + matchingPlayer[1];

		if (matchingPlayer[2]) {
			audioFiles.push(audiopath + matchingPlayer[2]);
		}

		videoPlayer.removeEventListener('ended', onVideoEnded);

		const playNextAudio = (index) => {
			if (index < audioFiles.length) {
				const audio = new Audio(audioFiles[index]);
				audio.load();
				audio.volume = document.getElementById('volume-slider').value / 100;
				audio.play();
				audio.addEventListener('ended', () => {
					playNextAudio(index + 1);
				});
			} else {
				audioFiles = [];
			}
		};   

		if (audioFiles.length === 1) {
			playNextAudio(0);
		}

		videoPlayer.setAttribute('src', videoPath);
		videoPlayer.load();

		setPlayerIntroContent(playerName);
		playerNameBox.style.display = 'inline-block';

		if (matchingPlayer.length > 3) {
			switch (matchingPlayer[3]) {
			  case 'noTitle':
				noTitle();
				break;
			  case 'randomAudio':
				playRandomAudio();
				noTitle();
				break;
			  case 'gifPlayer':
				gifPlayer();
				noTitle();
				break;
			  default:
				try {
				  if (matchingPlayer[3].match(/-\d+$/)) {
					const gifIndex = parseInt(matchingPlayer[3].split('-')[1]);
					gifPlayer(gifIndex);
					noTitle();
				  } else {
					setPlayerIntroContent(playerName);
					playerNameBox.style.display = 'inline-block';
				  }
				} catch (error) {
				  console.error('Error parsing GIF index:', error);
				  setPlayerIntroContent(playerName);
				  playerNameBox.style.display = 'inline-block';
				}
				break;
			}
		}

		Promise.all([
			videoPlayer.play(),            
			audioPlayer.play()
		]).catch((error) => {
			errorMessage.textContent = `Error: ${error.message}`;
			console.error('Error playing video ' + audioPath + ' or audio: ' + videoPath + ' ', error);
		});
		videoContainer.style.display = 'flex';
		const videoChromaCanvas = document.getElementById('video-chroma-canvas');
		videoPlayer.addEventListener('ended', onVideoEnded, { once: true });
		videoPlayer.addEventListener('playing', function onPlaying() {
			videoPlayer.removeEventListener('playing', onPlaying);
			if (isChromaKeyEnabled() && videoChromaCanvas) {
				startVideoChromaLoop(videoPlayer, videoChromaCanvas);
			}
		}, { once: true });
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

	function noTitle() {
		playerNameBox.style.display = 'none';
	}

    function playRandomAudio() {
        const randomIndex = Math.floor(Math.random() * randomAudioFiles.length);
        const audioPath = randomAudioFiles[randomIndex];
        const audio = new Audio(audioPath);
        audio.volume = document.getElementById('volume-slider').value / 100;
        audio.play();
    }     

	function gifPlayer(gifIndex = 0) {
	  const gifFileName = gifFiles.find(file => file[0] === gifIndex)[1];
	  let gifPath = imagepath + gifFileName;
	  if (typeof window.ASSET_VERSION !== 'undefined') gifPath += '?v=' + window.ASSET_VERSION;

	  const gifContainer = document.querySelector('#gif-container');
	  const gifImage = document.querySelector('#gif-image');
	  const gifChromaCanvas = document.querySelector('#gif-chroma-canvas');
	  const gifTimeout = 8000;

	  gifImage.src = gifPath;

	  gifContainer.style.display = 'flex';
	  gifImage.onload = function() {
		if (isChromaKeyEnabled() && gifChromaCanvas) {
		  startGifChromaLoop(gifImage, gifChromaCanvas, gifTimeout);
		}
	  };
	  if (gifImage.complete && gifImage.naturalWidth) {
		if (isChromaKeyEnabled() && gifChromaCanvas) {
		  startGifChromaLoop(gifImage, gifChromaCanvas, gifTimeout);
		}
	  }
	  setTimeout(() => {
		gifContainer.style.display = 'none';
		if (gifChromaRaf) cancelAnimationFrame(gifChromaRaf);
		gifChromaRaf = null;
		if (gifChromaTimeout) clearTimeout(gifChromaTimeout);
		gifChromaTimeout = null;
		if (gifChromaCanvas) gifChromaCanvas.style.display = 'none';
		if (gifImage) gifImage.style.display = '';
	  }, gifTimeout);
	}

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
