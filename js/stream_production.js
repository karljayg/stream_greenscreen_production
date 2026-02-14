var projectpath = "stream_production"; //in some servers this is '.' so switch between the 2 and test by running Random Music
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

window.toggleMatchup = function(btn) {
    const matchupSection = document.getElementById("matchup-section");
    if (matchupSection.style.display === "none" || matchupSection.style.display === "") {
        matchupSection.style.display = "block";
    } else {
        matchupSection.style.display = "none";
    }
    if (btn) btn.textContent = (matchupSection.style.display === "block") ? "Hide Matchup (2v2)" : "Show Matchup (2v2)";
}

window.togglePlayerRatings = function(btn) {
    const playerRatingsSection = document.getElementById("player-ratings-section");
    if (playerRatingsSection.style.display === "none" || playerRatingsSection.style.display === "") {
        playerRatingsSection.style.display = "block";
    } else {
        playerRatingsSection.style.display = "none";
    }
    if (btn) btn.textContent = (playerRatingsSection.style.display === "block") ? "Hide Player Ratings" : "Show Player Ratings";
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
		videoContainer.style.display = 'none';
		playerNameBox.style.display = 'none';
	};

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		const playerName = playerNameInput.value.trim();
		const matchingPlayer = playerList.find(p => p[0] === playerName);
		playerNameBox.textContent = matchingPlayer[0];

		if (!matchingPlayer) {
			errorMessage.textContent = `Error: Player "${playerName}" not found.`;
			return;
		}
		const videoPath = videopath + matchingPlayer[1];
		const audioPath = audiopath + matchingPlayer[2];

		audioFiles.push(audioPath);

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

		playerNameBox.style.display = 'block';

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
					playerNameBox.textContent = playerName;
					playerNameBox.style.display = 'block';
				  }
				} catch (error) {
				  console.error('Error parsing GIF index:', error);
				  playerNameBox.textContent = playerName;
				  playerNameBox.style.display = 'block';
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
		videoPlayer.addEventListener('ended', onVideoEnded, { once: true });
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
        const audioPath = window.location.origin + '/' + projectpath + '/' + randomAudioFiles[randomIndex];        
        const audio = new Audio(audioPath);
        audio.volume = document.getElementById('volume-slider').value / 100;
        audio.play();
    }     

	function gifPlayer(gifIndex = 0) {
	  const gifFileName = gifFiles.find(file => file[0] === gifIndex)[1];
	  const gifPath = imagepath + gifFileName;

	  const gifContainer = document.querySelector('#gif-container');
	  const gifImage = document.querySelector('#gif-image');
	  const gifTimeout = 8000;

	  gifImage.src = gifPath;

	  gifContainer.style.display = 'flex';
	  setTimeout(() => {
		gifContainer.style.display = 'none';
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
    matchup: {
        left: '-20%',
        top: '20%',
        scale: '1.2'
    },
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

// Matchup functionality
let matchupDisplayed = false;

window.handleMatchupDisplay = function(btn) {
    const teamAName = document.getElementById('matchup-teamA-name').value.trim() || 'Team A';
    const teamBName = document.getElementById('matchup-teamB-name').value.trim() || 'Team B';
    const teamA1 = document.getElementById('matchup-teamA1').value.trim();
    const teamA2 = document.getElementById('matchup-teamA2').value.trim();
    const teamB1 = document.getElementById('matchup-teamB1').value.trim();
    const teamB2 = document.getElementById('matchup-teamB2').value.trim();
    const teamAComment = document.getElementById('matchup-teamA-comment').value.trim();
    const teamBComment = document.getElementById('matchup-teamB-comment').value.trim();
    const resultDiv = document.getElementById('right-column-result');
    
    if (matchupDisplayed) {
        // Hide the matchup
        resultDiv.innerHTML = '';
        matchupDisplayed = false;
        if (btn) btn.textContent = 'Show Matchup';
        return;
    }
    
    // No validation - flexible to show any number of players
    const players = [
        { name: teamA1, side: 'A', pos: 1 },
        { name: teamA2, side: 'A', pos: 2 },
        { name: teamB1, side: 'B', pos: 1 },
        { name: teamB2, side: 'B', pos: 2 }
    ].filter(p => p.name); // Only include players with names
    
    console.log('Players to display:', players);
    
    // Find matching players in database
    const matchedPlayers = players.map(p => ({
        ...p,
        data: playerList.find(pl => pl[0] === p.name)
    }));
    
    // Generate HTML for team sides dynamically
    const teamAPlayers = matchedPlayers.filter(p => p.side === 'A');
    const teamBPlayers = matchedPlayers.filter(p => p.side === 'B');
    
    const generatePlayerHTML = (player, videoId) => {
        if (!player.data) {
            return `<div style="text-align: center; color: red; font-size: 12px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">Player not found: ${player.name}</div>`;
        }
        return `
            <div style="text-align: center;">
                <video id="${videoId}" width="140" height="105" muted loop>
                    Your browser does not support the video tag.
                </video>
                <p style="font-size: 12px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"><strong>${player.name}</strong></p>
            </div>
        `;
    };
    
    const teamAHTML = teamAPlayers.map((p, i) => generatePlayerHTML(p, `matchup-video-a${p.pos}`)).join('');
    const teamBHTML = teamBPlayers.map((p, i) => generatePlayerHTML(p, `matchup-video-b${p.pos}`)).join('');
    
    // Create the flexible container structure (positioned and scaled for greenscreen overlay)
    resultDiv.innerHTML = `
        <div style="
            position: relative;
            left: ${displayPositions.matchup.left};
            top: ${displayPositions.matchup.top};
            transform: scale(${displayPositions.matchup.scale});
            transform-origin: top left;
            width: 100%;
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div style="text-align: center; flex: 1;"><h3 style="font-size: 16px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">${teamAName}</h3></div>
                <div style="text-align: center; flex: 0 0 40px;"><h3 style="font-size: 18px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"></h3></div>
                <div style="text-align: center; flex: 1;"><h3 style="font-size: 16px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">${teamBName}</h3></div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="display: flex; flex-direction: column; align-items: center; width: 45%;">
                    <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; justify-content: center;">
                        ${teamAHTML}
                    </div>
                    ${teamAComment ? `<p style="font-size: 13px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"><strong>${teamAComment}</strong></p>` : ''}
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; width: 45%;">
                    <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; justify-content: center;">
                        ${teamBHTML}
                    </div>
                    ${teamBComment ? `<p style="font-size: 13px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"><strong>${teamBComment}</strong></p>` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Load and play videos for matched players only
    const videoPromises = [];
    matchedPlayers.forEach(player => {
        if (player.data) {
            const videoId = `matchup-video-${player.side.toLowerCase()}${player.pos}`;
            const video = document.getElementById(videoId);
            if (video) {
                const videoPath = videopath + player.data[1];
                console.log(`Loading video for ${player.name}: ${videoPath}`);
                video.setAttribute('src', videoPath);
                video.load();
                videoPromises.push(
                    video.play().catch(err => console.log(`Video ${player.name} play error:`, err))
                );
            }
        }
    });
    
    // Wait for all videos to start
    Promise.all(videoPromises).then(() => {
        console.log(`${videoPromises.length} matchup videos started successfully`);
    }).catch(error => {
        console.error('Error playing matchup videos:', error);
    });
    
    matchupDisplayed = true;
    if (btn) btn.textContent = 'Hide Matchup';
};

// Add autocomplete functionality for matchup inputs  
document.addEventListener('DOMContentLoaded', function() {
    const matchupInputs = document.querySelectorAll('.matchup-input');
    
    matchupInputs.forEach(input => {
        if (!input) return;
        
        input.addEventListener('input', (event) => {
            const inputValue = event.target.value.trim().toLowerCase();
            if (inputValue.length < 3) {
                clearMatchupSuggestions(input);
                return;
            }
            const matchingPlayers = playerList.filter(p => p[0].toLowerCase().includes(inputValue));
            if (matchingPlayers.length > 0) {
                showMatchupSuggestions(input, matchingPlayers);
            } else {
                clearMatchupSuggestions(input);
            }
        });
        
        input.addEventListener('blur', () => {
            setTimeout(() => clearMatchupSuggestions(input), 300);
        });
    });
    
    // Log available default players on load
    setTimeout(() => {
        const inputs = ['matchup-teamA1', 'matchup-teamA2', 'matchup-teamB1', 'matchup-teamB2']
            .map(id => document.getElementById(id))
            .filter(input => input && input.value);
        
        if (inputs.length > 0) {
            console.log('Default players available:', inputs.map(i => i.value).join(', '));
        }
    }, 100);

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

function showMatchupSuggestions(input, players) {
    const suggestionList = document.createElement('ul');
    suggestionList.setAttribute('data-layer-id', 'matchup-suggestions');
    suggestionList.classList.add('suggestion-list', 'matchup-suggestions');
    suggestionList.style.cssText = `
        position: absolute;
        background: white;
        border: 1px solid #ccc;
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        width: 100%;
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
            input.value = p[0];
            clearMatchupSuggestions(input);
            input.focus();
        });
        suggestionList.appendChild(suggestionItem);
    });
    
    clearMatchupSuggestions(input);
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(suggestionList);
    if (window.reapplyLayerOrder) window.reapplyLayerOrder();
}

function clearMatchupSuggestions(input) {
    const suggestionList = input.parentNode.querySelector('.matchup-suggestions');
    if (suggestionList) {
        suggestionList.remove();
    }
}

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
