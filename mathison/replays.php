<?php
$pathPrefix = '../';
require_once __DIR__ . '/../partials/auth-gate.php';
require_once __DIR__ . '/../asset_version.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mathison · Replays</title>
    <link rel="icon" href="<?= $pathPrefix ?>production_files/images/favicon.ico?v=<?= (int) $v ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/mathison.css?v=<?= (int) $v ?>">
</head>
<body class="mathison-body">
    <div class="mathison-shell mathison-shell--wide">
        <header class="mathison-header">
            <div>
                <nav class="mathison-breadcrumb">
                    <a href="index.php">Mathison</a>
                    <span>/</span>
                    <span>Replays</span>
                </nav>
                <h1>Replays</h1>
            </div>
            <div class="mathison-header-meta">
                <span id="total-badge" class="badge text-bg-secondary">…</span>
                <button type="button" class="btn btn-sm btn-success" id="btn-create">+ New replay</button>
                <a class="btn btn-sm btn-outline-light" href="index.php">Hub</a>
            </div>
        </header>

        <section class="mathison-filters card border-0">
            <div class="card-body">
                <form id="filter-form" class="row g-2 align-items-end" autocomplete="off">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="q">Search</label>
                        <input type="search" class="form-control form-control-sm" id="q" name="q" placeholder="Player, map, id, comments…">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label" for="playerA">Matchup A</label>
                        <input type="search" class="form-control form-control-sm" id="playerA" name="playerA" placeholder="Name or player id">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label" for="playerB">vs B</label>
                        <input type="search" class="form-control form-control-sm" id="playerB" name="playerB" placeholder="Name or player id">
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="idFrom">ID from</label>
                        <input type="number" class="form-control form-control-sm" id="idFrom" name="idFrom" min="1" placeholder="e.g. 100">
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="idTo">ID to</label>
                        <input type="number" class="form-control form-control-sm" id="idTo" name="idTo" min="1" placeholder="e.g. 200">
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label" for="startWorkers">Start workers</label>
                        <select class="form-select form-select-sm" id="startWorkers" name="startWorkers">
                            <option value="">All eras</option>
                            <option value="6">6 workers (WoL/HotS)</option>
                            <option value="12">12 workers (LotV-5.0.15)</option>
                            <option value="8">8 workers (5.0.16+)</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label" for="Map">Map</label>
                        <select class="form-select form-select-sm" id="Map" name="Map">
                            <option value="">All maps</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="Region">Region</label>
                        <select class="form-select form-select-sm" id="Region" name="Region">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="GameType">Type</label>
                        <select class="form-select form-select-sm" id="GameType" name="GameType">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="race">Race</label>
                        <select class="form-select form-select-sm" id="race" name="race">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="result">Result</label>
                        <select class="form-select form-select-sm" id="result" name="result">
                            <option value="">All</option>
                            <option value="Win">Win</option>
                            <option value="Lose">Lose</option>
                            <option value="Tie">Tie</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="dateFrom">From</label>
                        <input type="date" class="form-control form-control-sm" id="dateFrom" name="dateFrom">
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="dateTo">To</label>
                        <input type="date" class="form-control form-control-sm" id="dateTo" name="dateTo">
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label" for="limit">Page size</label>
                        <select class="form-select form-select-sm" id="limit" name="limit">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                        <button type="submit" class="btn btn-sm btn-primary">Apply filters</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset">Reset</button>
                        <span id="filter-status" class="mathison-status align-self-center"></span>
                    </div>
                </form>
            </div>
        </section>

        <div class="mathison-table-toolbar">
            <div id="page-info" class="mathison-status">Loading…</div>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-light" id="btn-prev" disabled>← Prev</button>
                <button type="button" class="btn btn-outline-light" id="btn-next" disabled>Next →</button>
            </div>
        </div>

        <div class="mathison-table-wrap">
            <table class="table table-sm table-hover mathison-table mb-0" id="replays-table">
                <thead>
                    <tr>
                        <th data-sort="ReplayId" class="is-sorted">ID</th>
                        <th data-sort="Date_Played">Played</th>
                        <th data-sort="Player1_Name">Player 1</th>
                        <th data-sort="Player1_Race">Race</th>
                        <th data-sort="Player1_Result">Res</th>
                        <th data-sort="Player2_Name">Player 2</th>
                        <th data-sort="Player2_Race">Race</th>
                        <th>Res</th>
                        <th data-sort="Map">Map</th>
                        <th data-sort="Region">Reg</th>
                        <th data-sort="GameType">Type</th>
                        <th data-sort="GameDuration">Dur</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="replays-body"></tbody>
            </table>
            <div id="lazy-sentinel" class="mathison-lazy-sentinel" aria-hidden="true"></div>
            <div id="lazy-status" class="mathison-lazy-status"></div>
        </div>
    </div>

    <!-- Detail / Edit modal -->
    <div class="modal fade" id="replay-modal" tabindex="-1" aria-labelledby="replay-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content mathison-modal">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="replay-modal-title">Replay</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="replay-form" class="row g-3">
                        <input type="hidden" id="f-ReplayId" name="ReplayId">
                        <div class="col-md-2">
                            <label class="form-label" for="f-UnixTimestamp">UnixTimestamp</label>
                            <input type="number" class="form-control form-control-sm font-mono" id="f-UnixTimestamp" name="UnixTimestamp">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player1_Id">Player1_Id</label>
                            <input type="number" class="form-control form-control-sm" id="f-Player1_Id" name="Player1_Id">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player2_Id">Player2_Id</label>
                            <input type="number" class="form-control form-control-sm" id="f-Player2_Id" name="Player2_Id">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="f-Date_Played">Date_Played</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="f-Date_Played" name="Date_Played">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="f-Date_Uploaded">Date_Uploaded</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="f-Date_Uploaded" name="Date_Uploaded">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label" for="f-Player1_Race">P1 Race</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player1_Race" name="Player1_Race" list="race-list">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player1_PickRace">P1 Pick</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player1_PickRace" name="Player1_PickRace" list="race-list">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player1_Result">P1 Result</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player1_Result" name="Player1_Result">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player2_Race">P2 Race</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player2_Race" name="Player2_Race" list="race-list">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player2_PickRace">P2 Pick</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player2_PickRace" name="Player2_PickRace" list="race-list">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Player2_Result">P2 Result</label>
                            <input type="text" class="form-control form-control-sm" id="f-Player2_Result" name="Player2_Result">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="f-Map">Map</label>
                            <input type="text" class="form-control form-control-sm" id="f-Map" name="Map">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-Region">Region</label>
                            <input type="text" class="form-control form-control-sm" id="f-Region" name="Region">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="f-GameType">GameType</label>
                            <input type="text" class="form-control form-control-sm" id="f-GameType" name="GameType">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="f-GameDuration">GameDuration</label>
                            <input type="text" class="form-control form-control-sm" id="f-GameDuration" name="GameDuration" placeholder="e.g. 12m 34s">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="f-Player_Comments">Player_Comments</label>
                            <textarea class="form-control form-control-sm" id="f-Player_Comments" name="Player_Comments" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="f-Replay_Summary">Replay_Summary</label>
                            <textarea class="form-control form-control-sm font-mono" id="f-Replay_Summary" name="Replay_Summary" rows="14"></textarea>
                        </div>
                        <datalist id="race-list">
                            <option value="Protoss">
                            <option value="Terran">
                            <option value="Zerg">
                            <option value="Random">
                        </datalist>
                    </form>
                    <div id="modal-names" class="mathison-modal-names mt-2"></div>
                    <div id="modal-error" class="text-danger small mt-2" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="btn-delete" hidden>Delete</button>
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btn-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/replays.js?v=<?= (int) (@filemtime(__DIR__ . '/js/replays.js') ?: $v) ?>"></script>
</body>
</html>
