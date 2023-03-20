var masterpath = ".";
var production_files = masterpath + "/production_files";
var audiopath = production_files + "/audio/";
var videopath = production_files + "/video/";
var imagepath = production_files + "/images/";

import playerList from './playerlist.js';
import { gifFiles, randomAudioFiles } from './other_lists.js';

const forms = document.querySelectorAll('.media-form');
const audioPlayer = document.querySelector('#audio-player');
const volumeSlider = document.getElementById('volume-slider');
const audioObjects = []; // Create an array to store all audio objects for subsequent submits while 1 or more audio is playing

const setVolume = (volume) => {
  audioObjects.forEach((audio) => {
    audio.volume = volume;
    console.log("volume set to " + volume);    
  });
};

volumeSlider.addEventListener('input', () => {
  const volume = volumeSlider.value / 100;
  setVolume(volume);
  console.log("slider event detected " + volume);
});

// add an event listener for the "ended" event on the audio player
audioPlayer.addEventListener('ended', function() {
	const audioPlayer = this;
	// play the next audio file in the array
	if (audioFiles.length > 0) {
		const nextAudioPath = audioFiles.shift();
		audioPlayer.setAttribute('src', nextAudioPath);
		audioPlayer.load();
		audioPlayer.play();
		// add the audio object to the array
		audioObjects.push(audio);        
	}
});

const setAllVolumes = (volume) => {
	// loop through all the audio objects and set their volume
	for (let i = 0; i < audioObjects.length; i++) {
		const audio = audioObjects[i];
		audio.volume = volume;
	}
};

forms.forEach((form) => {

	// get the player name input and the player name box elements
	const playerNameBox = document.querySelector('.player-name-box');

	const playerNameInput = form.querySelector('.player-name-input');
	const videoPlayer = document.querySelector('#video-player');
	const audioPlayer = document.querySelector('#audio-player');
	const errorMessage = document.querySelector('#error-message');
	const videoContainer = document.querySelector('#video-container');

	// Create an array to store the audio file paths to allow for continuous play of multiple audio after subsequent button presses
	let audioFiles = [];

	form.addEventListener('submit', (event) => {

		event.preventDefault();
		//const playerName = playerNameInput.value.trim().toLowerCase();
		const playerName = playerNameInput.value.trim();
		//const matchingPlayer = playerList.find(p => p[0].toLowerCase() === playerName);
		const matchingPlayer = playerList.find(p => p[0] === playerName);
		playerNameBox.textContent = matchingPlayer[0];

		if (!matchingPlayer) {
			errorMessage.textContent = `Error: Player "${playerName}" not found.`;
			return;
		}
		const videoPath = videopath + matchingPlayer[1];
		const audioPath = audiopath + matchingPlayer[2];

		// Add the audio file path to the array
		audioFiles.push(audioPath);

		// Play the audio files in the array one after the other
		/*
		In this code, we added an array called audioFiles to store the audio file paths. On each button press, we push the new audio file path to the audioFiles array. We also added a playNextAudio function to play the audio files in the array one after the other.
		When the form is submitted, we add the new audio file path to the audioFiles array and check if it is the first audio file in the array. If it is the first audio file, we call the playNextAudio function with an index of 0 to play the first audio file in the array. If there are already audio files in the audioFiles array, we don't call the playNextAudio function again, because it is already playing the previous audio files.
		*/

        /*
		const playNextAudio = (index) => {
			if (index < audioFiles.length) {
				const audio = new Audio(audioFiles[index]);
				audio.load();
				audio.play();
				audio.addEventListener('ended', () => {
					playNextAudio(index + 1);
				});
			} else {
				audioFiles = [];
			}
		};
        */

       const playNextAudio = (index) => {
        if (index < audioFiles.length) {
          const audio = new Audio(audioFiles[index]);
          audio.load();
          audio.play();
          audioObjects.push(audio); // add the audio object to the array
          audio.addEventListener('ended', () => {
            // remove the audio object from the array when it ends
            const index = audioObjects.indexOf(audio);
            if (index > -1) {
              audioObjects.splice(index, 1);
            }
            playNextAudio(index + 1);
          });
        } else {
          audioFiles = [];
        }
      };   

		// Play the first audio file in the array
		if (audioFiles.length === 1) {
			playNextAudio(0);
		}

		videoPlayer.setAttribute('src', videoPath);
		videoPlayer.load();

		//show player name by default
		playerNameBox.style.display = 'block';

		// Check if there is a fourth element in the matchingPlayer array
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
				// Check if the command has a number suffix
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

	//CUSTOM 4th parameter functions

	//hide title or player name for certain types
	function noTitle() {
		playerNameBox.style.display = 'none';
	}

    function playRandomAudio() {
        const randomIndex = Math.floor(Math.random() * randomAudioFiles.length);
        const audioPath = window.location.origin + '/' + randomAudioFiles[randomIndex];
        const audio = new Audio(audioPath);
        audio.play();
      }     

	function gifPlayer(gifIndex = 0) {
	  // Select the GIF file based on the gifIndex parameter
	  const gifFileName = gifFiles.find(file => file[0] === gifIndex)[1];
	  const gifPath = imagepath + gifFileName;

	  const gifContainer = document.querySelector('#gif-container');
	  const gifImage = document.querySelector('#gif-image');
	  const gifTimeout = 8000; // in ms

	  // Set the GIF image source
	  gifImage.src = gifPath;

	  // Show the GIF container and hide it after the timeout
	  gifContainer.style.display = 'block';
	  setTimeout(() => {
		gifContainer.style.display = 'none';
	  }, gifTimeout);
	}


	function showSuggestions(players) {
		const suggestionList = document.createElement('ul');
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