var projectpath = "stream_production"; //in some servers this is '.' so switch between the 2 and test by running Random Music
var masterpath = ".";
var production_files = masterpath + "/production_files";
var audiopath = production_files + "/audio/";
var videopath = production_files + "/video/";
var imagepath = production_files + "/images/";

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
window.toggleStatus = function() {
    const statusSection = document.getElementById("status-section");
    if (statusSection.style.display === "none" || statusSection.style.display === "") {
        statusSection.style.display = "block";
    } else {
        statusSection.style.display = "none";
    }
}

window.showFormattedResult = function() {
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
            <div>
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
		videoContainer.style.display = 'block';
		videoPlayer.addEventListener('ended', () => {
			videoContainer.style.display = 'none';
			playerNameBox.style.display = 'none';
		});
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

	  gifContainer.style.display = 'block';
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
