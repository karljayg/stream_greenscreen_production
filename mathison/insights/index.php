<?php
$pathPrefix = '../../';
require_once __DIR__ . '/../../partials/auth-gate.php';
require_once __DIR__ . '/../../asset_version.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mathison · Insights</title>
    <link rel="icon" href="<?= $pathPrefix ?>production_files/images/favicon.ico?v=<?= (int) $v ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/mathison.css?v=<?= (int) $v ?>">
    <style>
        .insight-card { background: linear-gradient(160deg, var(--mx-surface-2), var(--mx-surface)); border: 1px solid var(--mx-border); border-radius: 0.75rem; padding: 1rem 1.15rem; height: 100%; }
        .insight-card h2 { font-size: 0.95rem; color: var(--mx-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.75rem; }
        .stat-big { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
        .stat-sub { font-size: 0.8rem; color: var(--mx-muted); }
        .stale-banner { border: 1px solid #a37718; background: rgba(201, 162, 39, 0.12); color: #e8c96a; border-radius: 0.6rem; padding: 0.7rem 1rem; font-size: 0.9rem; }
        .fresh-banner { border: 1px solid #2d6a4a; background: rgba(62, 207, 142, 0.08); color: #7bd8ab; border-radius: 0.6rem; padding: 0.7rem 1rem; font-size: 0.9rem; }
        .stale-banner code, .fresh-banner code { color: inherit; background: rgba(0,0,0,0.3); padding: 0.1rem 0.35rem; border-radius: 0.25rem; }
        canvas { max-height: 320px; }
        .comment-cell { max-width: 26rem; white-space: normal; }
        .tag-chip { display: inline-block; background: var(--mx-accent-soft); color: var(--mx-accent); border-radius: 0.3rem; padding: 0.05rem 0.4rem; font-size: 0.72rem; margin: 0 0.15rem 0.15rem 0; }
        .timings-table td, .timings-table th { font-size: 0.82rem; }
        tr.likely-row td { opacity: 0.95; }
        #likely-panel h2 { color: #7ec4f8; }
    </style>
</head>
<body class="mathison-body">
    <div class="mathison-shell mathison-shell--wide">
        <header class="mathison-header">
            <div>
                <nav class="mathison-breadcrumb">
                    <a href="../index.php">Mathison</a>
                    <span>/</span>
                    <span>Insights</span>
                </nav>
                <h1>Scouting Insights</h1>
            </div>
            <div class="mathison-header-meta">
                <a class="btn btn-sm btn-outline-light" href="../replays.php">Replays table</a>
                <a class="btn btn-sm btn-outline-light" href="../index.php">Hub</a>
            </div>
        </header>

        <div id="status-banner" class="mb-3" hidden></div>

        <div class="insight-card mb-3">
            <div class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label mb-1" for="startWorkers" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--mx-muted);">Starting workers</label>
                    <select id="startWorkers" class="form-select form-select-sm" style="min-width: 16rem;">
                        <option value="">All eras</option>
                    </select>
                </div>
                <p class="stat-sub mb-0" style="max-width:36rem;">
                    Early timings are not comparable across eras (6 → 12 → 8 starting workers).
                    Filter before reading tactic charts or scouting reports.
                </p>
            </div>
        </div>

        <div class="row g-3 mb-3" id="overview-cards">
            <div class="col-6 col-lg-3"><div class="insight-card"><h2>Compound tactics</h2><div class="stat-big" id="st-games">…</div><div class="stat-sub" id="st-games-sub">chained plans from comments</div></div></div>
            <div class="col-6 col-lg-3"><div class="insight-card"><h2>Full chains (3+)</h2><div class="stat-big" id="st-tags">…</div><div class="stat-sub">multi-part plans like 3 hatch · ling bane · all in</div></div></div>
            <div class="col-6 col-lg-3"><div class="insight-card"><h2>Opponents</h2><div class="stat-big" id="st-opps">…</div><div class="stat-sub">with scouting data</div></div></div>
            <div class="col-6 col-lg-3"><div class="insight-card"><h2>Last relabel</h2><div class="stat-big" id="st-run" style="font-size:1.1rem;">…</div><div class="stat-sub" id="st-run-sub"></div></div></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="insight-card">
                    <h2>Most common compound tactics (3+ parts)</h2>
                    <canvas id="chart-tags"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="insight-card">
                    <h2>Our win rate vs compound tactic (min 3 games)</h2>
                    <canvas id="chart-winrate"></canvas>
                </div>
            </div>
        </div>

        <div class="insight-card mb-3">
            <div class="d-flex flex-wrap align-items-end gap-3 mb-2">
                <div>
                    <h2 class="mb-1">Opponent scouting report</h2>
                    <select id="opponent-select" class="form-select form-select-sm" style="min-width: 18rem;">
                        <option value="">Select tagged opponent…</option>
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1" for="opponent-lookup" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--mx-muted);">Or look up any player</label>
                    <div class="input-group input-group-sm" style="min-width: 14rem;">
                        <input type="text" id="opponent-lookup" class="form-control" placeholder="e.g. MedicJR" autocomplete="off">
                        <button type="button" class="btn btn-outline-info" id="opponent-lookup-btn">Go</button>
                    </div>
                </div>
                <div id="opp-record" class="stat-sub"></div>
                <a id="opp-replays-link" class="btn btn-sm btn-outline-info" href="#" hidden>All replays vs this player</a>
            </div>

            <div id="opp-panels" hidden>
                <div class="mb-3 p-3" id="likely-panel" style="border:1px solid rgba(61,156,240,0.45); border-radius:0.4rem; background:rgba(61,156,240,0.06);">
                    <h2 class="mb-1">Likely Strategy</h2>
                    <p class="stat-sub mb-2" id="likely-meta">
                        Auto-inferred from build timings when this player has no comment.
                        Match % is timing similarity to a labeled game in the corpus.
                    </p>
                    <div id="likely-summary" class="table-responsive">
                        <table class="table table-sm mathison-table mb-0">
                            <thead>
                                <tr>
                                    <th>Likely strategy</th>
                                    <th style="width:5rem;">Match</th>
                                    <th style="width:4rem;">Games</th>
                                    <th>Matched comment</th>
                                </tr>
                            </thead>
                            <tbody id="likely-body">
                                <tr><td colspan="4" class="stat-sub">Look up a player to compute likely strategies.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-3">
                <div class="col-lg-5">
                    <h2>Their compound plans</h2>
                    <p id="opp-tactics-empty" class="stat-sub" hidden>No human comments for this player — likely strategies above are from timing matches.</p>
                    <canvas id="chart-opp-tags"></canvas>
                </div>
                <div class="col-lg-3">
                    <h2>Their key timings</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mathison-table timings-table">
                            <thead><tr><th>Building</th><th>Earliest</th><th>Avg</th><th>N</th></tr></thead>
                            <tbody id="opp-timings"></tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-4">
                    <h2>Game history</h2>
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-sm mathison-table">
                            <thead><tr><th>Date</th><th>Res</th><th>Comment / likely strategy</th></tr></thead>
                            <tbody id="opp-games"></tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <div class="insight-card">
            <h2>Top scouted opponents</h2>
            <div class="table-responsive">
                <table class="table table-sm table-hover mathison-table">
                    <thead><tr><th>Opponent</th><th>Race</th><th>Tagged games</th><th>Our W-L</th></tr></thead>
                    <tbody id="top-opponents"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="js/insights.js?v=<?= (int) (@filemtime(__DIR__ . '/js/insights.js') ?: $v) ?>"></script>
</body>
</html>
