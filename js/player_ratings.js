// Define player data
const playerData = {
    Alisaunder: {
        offense: 20,
        defense: 20,
        versatility: 10,
        multitasking: null,
        mechanics: null,
        speed: null,
        cheese_factor: null,
        macro: null,
        micro: null,
        overall: null
    },
    grey: {
        offense: 60,
        defense: 60,
        versatility: 60,
        multitasking: 50,
        mechanics: 60,
        speed: 60,
        cheese_factor: 60,
        macro: 60,
        micro: 58.88888889,
        overall: null
    }
};

// Define spider chart labels and data keys
const chartLabels = [
    'Offense', 'Defense', 'Versatility', 'Multitasking', 'Mechanics',
    'Speed', 'Cheese Factor', 'Macro', 'Micro', 'Overall'
];
const chartDataKeys = [
    'offense', 'defense', 'versatility', 'multitasking', 'mechanics',
    'speed', 'cheese_factor', 'macro', 'micro', 'overall'
];

// Load autocomplete options
const playerOptions = Object.keys(playerData);

// Load autocomplete textbox
$("#player-input").autocomplete({
    source: function(request, response) {
        var matcher = new RegExp("^" + $.ui.autocomplete.escapeRegex(request.term), "i");
        response($.grep(playerOptions, function(item) {
            return matcher.test(item);
        }));
    },
    minLength: 3
});

function searchPlayers(playerName) {
    let matches = [];
    for (const player in playerData) {
        if (player.toLowerCase().includes(playerName.toLowerCase())) {
            matches.push(player);
        }
    }
    return matches;
}

function hideChart() {
    document.getElementById('chart-container').style.display = 'none';
}

// Generate spider chart
function loadSpiderChart() {

    //show Chart in case it's hidden
    document.getElementById('chart-container').style.display = 'block';

    // Get the player name from the input box
    const playerName = document.getElementById("player-input").value;

    // Get the player data from the playerData object
    const player = playerData[playerName];

    // Get the chart data keys
    const chartDataKeys = Object.keys(player).filter(key => key !== "overall" && player[key] !== null);

    // Get the chart data values
    const chartDataValues = chartDataKeys.map(key => player[key]);

    // Destroy the previous Chart instance if it exists
    if (window.spiderChart) {
        window.spiderChart.destroy();
    }

    // Set chart options
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
            padding: {
                top: 40,
                left: 40,
                right: 40,
                bottom: 40
            }
        },
        scale: {
            pointLabels: {
                fontColor: 'yellow',
                fontSize: 50
            }
        },
        scales: {
            r: {
                grid: {
                    color: 'gray',
                    lineWidth: 1
                },
                angleLines: {
                    color: 'gray',
                    lineWidth: 2
                },
                ticks: {
                    beginAtZero: true,
                    max: 100,
                    fontColor: 'white',
                    fontSize: 20,
                }
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    };

    // Create a new Chart instance
    window.spiderChart = new Chart(document.getElementById("spider-chart"), {
        type: 'radar',
        data: {
            labels: chartDataKeys,
            datasets: [{
                label: playerName,
                data: chartDataValues,
                //backgroundColor: 'rgba(255, 206, 86, 0.2)', // Yellow
                //borderColor: 'rgba(255, 206, 86, 1)', // Yellow
                borderWidth: 2,
                //pointBackgroundColor: 'white',
                //pointBorderColor: 'white',
                pointHoverBackgroundColor: 'white',
                pointHoverBorderColor: 'rgba(255, 206, 86, 1)', // Yellow
                pointRadius: 4,
                pointHoverRadius: 6,
                pointHitRadius: 10,
                lineTension: 0.3,
                fill: true,
                tension: 0.4
            }]
        },
        options: chartOptions
    });
}