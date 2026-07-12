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
    <title>Mathison DB</title>
    <link rel="icon" href="<?= $pathPrefix ?>production_files/images/favicon.ico?v=<?= (int) $v ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/mathison.css?v=<?= (int) $v ?>">
</head>
<body class="mathison-body">
    <div class="mathison-shell">
        <header class="mathison-header">
            <div>
                <p class="mathison-eyebrow">Database manager</p>
                <h1>Mathison</h1>
            </div>
            <div class="mathison-header-meta">
                <span class="badge text-bg-secondary"><?= htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') ?></span>
                <a class="btn btn-sm btn-outline-light" href="<?= $pathPrefix ?>index.php">Stream tool</a>
            </div>
        </header>

        <p class="mathison-lead">Browse and edit tables in the Mathison MySQL database.</p>

        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <a class="mathison-card-link" href="replays.php">
                    <article class="mathison-card">
                        <h2>Replays</h2>
                        <p>Paginated query table with filters, lazy detail load, and full CRUD for ~26k SC2 replays.</p>
                        <span class="mathison-card-cta">Open table →</span>
                    </article>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a class="mathison-card-link" href="insights/index.php">
                    <article class="mathison-card">
                        <h2>Insights</h2>
                        <p>Scouting dashboard: opponent tendencies, tactic win rates, and key timings derived from player comments.</p>
                        <span class="mathison-card-cta">Open dashboard →</span>
                    </article>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a class="mathison-card-link" href="api/README.md">
                    <article class="mathison-card">
                        <h2>External API</h2>
                        <p>Token-auth JSON endpoints for player info, likely strategies, matchups, and timings — for other tools.</p>
                        <span class="mathison-card-cta">API guide →</span>
                    </article>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <article class="mathison-card is-muted">
                    <h2>Players</h2>
                    <p>Coming next — manage player IDs and SC2 usernames.</p>
                </article>
            </div>
            <div class="col-md-6 col-xl-4">
                <article class="mathison-card is-muted">
                    <h2>Other tables</h2>
                    <p>core_values, goals, memory, moods, personality, and more.</p>
                </article>
            </div>
        </div>
    </div>
</body>
</html>
