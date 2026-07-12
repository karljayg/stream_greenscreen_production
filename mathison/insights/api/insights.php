<?php
/**
 * Mathison Insights API (read-only)
 *
 * GET ?action=status              relabeler freshness check (drives the
 *                                 "rerun relabeler" banner on the dashboard)
 * GET ?action=overview            global tag stats + top opponents
 * GET ?action=opponents           opponent list with tagged-game counts
 * GET ?action=opponent&name=X     scouting profile for one opponent
 *
 * All data is served from the derived insight_* tables plus read-only
 * lookups into PatternLearning (key timings) - nothing here writes.
 */
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pathPrefix = '../../../';
require_once __DIR__ . '/../../../partials/auth-gate.php';
require_once __DIR__ . '/../../db.php';

try {
    $pdo = mathison_pdo();
    $action = $_GET['action'] ?? 'status';
    $startWorkers = parse_start_workers_param($_GET['startWorkers'] ?? '');

    switch ($action) {
        case 'status':
            $payload = ['ok' => true, 'data' => status($pdo)];
            break;
        case 'overview':
            $payload = ['ok' => true, 'data' => overview($pdo, $startWorkers)];
            break;
        case 'opponents':
            $payload = ['ok' => true, 'data' => opponents($pdo, $startWorkers)];
            break;
        case 'opponent':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Missing name');
            }
            $payload = ['ok' => true, 'data' => opponent_profile($pdo, $name, $startWorkers)];
            break;
        default:
            throw new InvalidArgumentException('Unknown action');
    }

    $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('JSON encode failed: ' . json_last_error_msg());
    }
    echo $json;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Accept 6, 8, or 12; empty = no filter. */
function parse_start_workers_param($raw): ?int
{
    $raw = trim((string) $raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return null;
    }
    $n = (int) $raw;
    return in_array($n, [6, 8, 12], true) ? $n : null;
}

function workers_where(?int $startWorkers, string $alias = ''): array
{
    if ($startWorkers === null) {
        return ['', []];
    }
    $col = $alias !== '' ? "{$alias}.start_workers" : 'start_workers';
    return [" AND {$col} = ?", [$startWorkers]];
}

/**
 * Compare the last finished relabeler run's watermarks against live
 * source-table counts. "stale" means new commented replays or new/updated
 * PatternLearning rows exist that the relabeler has not seen.
 */
function status(PDO $pdo): array
{
    $R = mathison_table('Replays');
    $PL = mathison_table('PatternLearning');

    $hasTables = (bool) $pdo->query("SHOW TABLES LIKE 'insight_runs'")->fetchColumn();
    $lastRun = null;
    if ($hasTables) {
        $lastRun = $pdo->query(
            'SELECT * FROM insight_runs WHERE finished_at IS NOT NULL ORDER BY run_id DESC LIMIT 1'
        )->fetch() ?: null;
    }

    $current = $pdo->query(
        "SELECT
           (SELECT COUNT(*) FROM $R WHERE Player_Comments IS NOT NULL AND Player_Comments <> '') AS replay_comment_count,
           (SELECT COALESCE(MAX(ReplayId),0) FROM $R WHERE Player_Comments IS NOT NULL AND Player_Comments <> '') AS max_replay_id,
           (SELECT COUNT(*) FROM $PL) AS pattern_count,
           (SELECT MAX(updated_at) FROM $PL) AS max_pattern_updated_at"
    )->fetch();

    $stale = true;
    $newReplays = (int) $current['replay_comment_count'];
    $newPatterns = (int) $current['pattern_count'];
    if ($lastRun) {
        $newReplays = max(0, (int) $current['replay_comment_count'] - (int) $lastRun['watermark_replay_comment_count']);
        $patternsUpdated = $lastRun['watermark_pattern_updated_at'] !== null
            && $current['max_pattern_updated_at'] > $lastRun['watermark_pattern_updated_at'];
        $newPatterns = max(0, (int) $current['pattern_count'] - (int) $lastRun['watermark_pattern_count']);
        $stale = $newReplays > 0 || $newPatterns > 0 || $patternsUpdated;
    }

    $rules = require __DIR__ . '/../rules.php';
    $workerCounts = [];
    if ($hasTables && $pdo->query("SHOW COLUMNS FROM insight_tactics LIKE 'start_workers'")->fetch()) {
        $workerCounts = $pdo->query(
            'SELECT start_workers, COUNT(*) AS c FROM insight_tactics
             WHERE start_workers IS NOT NULL GROUP BY start_workers ORDER BY start_workers'
        )->fetchAll();
    }

    return [
        'last_run' => $lastRun,
        'current' => $current,
        'stale' => $stale,
        'new_commented_replays' => $newReplays,
        'new_patterns' => $newPatterns,
        'never_run' => $lastRun === null,
        'worker_eras' => $rules['worker_eras'] ?? [],
        'worker_counts' => $workerCounts,
    ];
}

function overview(PDO $pdo, ?int $startWorkers = null): array
{
    [$wSql, $wParams] = workers_where($startWorkers);

    $topStmt = $pdo->prepare(
        "SELECT tactic_key, tactic_label, parts_count,
                COUNT(*) AS games,
                SUM(self_result = 'Win') AS our_wins,
                SUM(self_result = 'Lose') AS our_losses
         FROM insight_tactics
         WHERE parts_count >= 3 {$wSql}
         GROUP BY tactic_key, tactic_label, parts_count
         ORDER BY games DESC
         LIMIT 25"
    );
    $topStmt->execute($wParams);
    $topTactics = $topStmt->fetchAll();

    $resStmt = $pdo->prepare(
        "SELECT tactic_key, tactic_label, parts_count,
                COUNT(*) AS games,
                SUM(self_result = 'Win') AS our_wins,
                SUM(self_result = 'Lose') AS our_losses
         FROM insight_tactics
         WHERE parts_count >= 3 AND self_result IN ('Win','Lose') {$wSql}
         GROUP BY tactic_key, tactic_label, parts_count
         HAVING games >= 3
         ORDER BY games DESC
         LIMIT 40"
    );
    $resStmt->execute($wParams);
    $tacticResults = $resStmt->fetchAll();

    $oppStmt = $pdo->prepare(
        "SELECT opponent_name, opponent_race,
                COUNT(*) AS tagged_games,
                SUM(self_result = 'Win') AS our_wins,
                SUM(self_result = 'Lose') AS our_losses
         FROM insight_tactics
         WHERE opponent_name IS NOT NULL {$wSql}
         GROUP BY opponent_name, opponent_race
         ORDER BY tagged_games DESC LIMIT 25"
    );
    $oppStmt->execute($wParams);
    $topOpponents = $oppStmt->fetchAll();

    $totStmt = $pdo->prepare(
        "SELECT
           COUNT(*) AS tactic_rows,
           COUNT(DISTINCT source, source_id) AS tagged_sources,
           COUNT(DISTINCT opponent_name) AS opponents,
           SUM(parts_count >= 3) AS full_chains
         FROM insight_tactics
         WHERE 1=1 {$wSql}"
    );
    $totStmt->execute($wParams);
    $totals = $totStmt->fetch();
    $totals['tag_rows'] = null;
    $totals['start_workers_filter'] = $startWorkers;

    return [
        'totals' => $totals,
        'top_tactics' => $topTactics,
        'tactic_results' => $tacticResults,
        'top_opponents' => $topOpponents,
        'start_workers' => $startWorkers,
    ];
}

function opponents(PDO $pdo, ?int $startWorkers = null): array
{
    [$wSql, $wParams] = workers_where($startWorkers);
    $stmt = $pdo->prepare(
        "SELECT opponent_name, MAX(opponent_race) AS opponent_race,
                COUNT(*) AS tagged_games,
                MAX(date_played) AS last_seen
         FROM insight_tactics
         WHERE opponent_name IS NOT NULL {$wSql}
         GROUP BY opponent_name
         ORDER BY tagged_games DESC, last_seen DESC"
    );
    $stmt->execute($wParams);
    return $stmt->fetchAll();
}

/**
 * Scouting profile: compound tactic tendencies first, then game history,
 * W/L, and PatternLearning key timings.
 */
function opponent_profile(PDO $pdo, string $name, ?int $startWorkers = null): array
{
    require_once __DIR__ . '/../likely_strategy.php';
    $name = likely_strategy_resolve_name($pdo, $name);

    [$wSql, $wParams] = workers_where($startWorkers);
    $params = array_merge([$name], $wParams);

    $tactics = $pdo->prepare(
        "SELECT tactic_key, tactic_label, parts_count, phase, COUNT(*) AS games,
                SUM(self_result = 'Win') AS our_wins,
                SUM(self_result = 'Lose') AS our_losses
         FROM insight_tactics WHERE opponent_name = ? {$wSql}
         GROUP BY tactic_key, tactic_label, parts_count, phase
         ORDER BY games DESC, parts_count DESC"
    );
    $tactics->execute($params);

    $games = $pdo->prepare(
        "SELECT source, source_id, replay_id, date_played, self_result,
                opponent_race, comment, phase, tactic_label, parts_count, start_workers
         FROM insight_tactics WHERE opponent_name = ? {$wSql}
         ORDER BY date_played DESC"
    );
    $games->execute($params);

    $record = $pdo->prepare(
        "SELECT SUM(r = 'Win') AS our_wins, SUM(r = 'Lose') AS our_losses FROM (
           SELECT source, source_id, MAX(self_result) AS r
           FROM insight_tactics WHERE opponent_name = ? {$wSql}
           GROUP BY source, source_id
         ) g"
    );
    $record->execute($params);

    // Lazy: only on this single-opponent endpoint — infer labels for
    // replays that have no Player_Comments (timing match vs labeled corpus).
    $likely = likely_strategies_for_opponent($pdo, $name, $startWorkers);

    return [
        'name' => $name,
        'start_workers' => $startWorkers,
        'record' => $record->fetch(),
        'tactics' => $tactics->fetchAll(),
        'games' => $games->fetchAll(),
        'key_timings' => opponent_key_timings($pdo, $name),
        'likely_strategies' => $likely,
    ];
}

/**
 * Average per-building timings from PatternLearning signature.key_timings
 * for one opponent, e.g. "their Spawning Pool lands at 1:38 on average".
 * Rows are ordered earliest → latest (build timeline), not by sample size.
 */
function opponent_key_timings(PDO $pdo, string $name): array
{
    $PL = mathison_table('PatternLearning');
    $stmt = $pdo->prepare(
        "SELECT JSON_EXTRACT(signature, '$.key_timings') AS kt
         FROM $PL
         WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.game_data.opponent_name')) = ?"
    );
    $stmt->execute([$name]);

    $acc = []; // building => [seconds, ...]
    foreach ($stmt as $row) {
        $kt = json_decode((string) $row['kt'], true);
        if (!is_array($kt)) {
            continue;
        }
        foreach ($kt as $building => $time) {
            $secs = clock_to_seconds_flexible($time);
            if ($secs !== null && $secs >= 0) {
                $acc[$building][] = $secs;
            }
        }
    }

    $out = [];
    foreach ($acc as $building => $times) {
        $avgSec = (int) round(array_sum($times) / count($times));
        $out[] = [
            'building' => $building,
            'games' => count($times),
            'avg_sec' => $avgSec,
            'earliest_sec' => min($times),
            'avg' => seconds_to_clock($avgSec),
            'earliest' => seconds_to_clock((int) min($times)),
            'latest' => seconds_to_clock((int) max($times)),
        ];
    }
    // Chronological: earliest average first, then earliest seen, then name.
    usort($out, static function ($a, $b) {
        $c = $a['avg_sec'] <=> $b['avg_sec'];
        if ($c !== 0) {
            return $c;
        }
        $c = $a['earliest_sec'] <=> $b['earliest_sec'];
        if ($c !== 0) {
            return $c;
        }
        return strcasecmp($a['building'], $b['building']);
    });
    foreach ($out as &$row) {
        unset($row['avg_sec'], $row['earliest_sec']);
    }
    unset($row);
    return $out;
}

/** Accept "m:ss" clocks or raw second ints from PatternLearning. */
function clock_to_seconds_flexible($time): ?int
{
    if (is_int($time) || is_float($time)) {
        return (int) $time;
    }
    $time = trim((string) $time);
    if ($time !== '' && ctype_digit($time)) {
        return (int) $time;
    }
    return clock_to_seconds($time);
}

function clock_to_seconds(string $clock): ?int
{
    if (!preg_match('/^(\d+):(\d{2})$/', trim($clock), $m)) {
        return null;
    }
    return (int) $m[1] * 60 + (int) $m[2];
}

function seconds_to_clock(int $secs): string
{
    return floor($secs / 60) . ':' . str_pad((string) ($secs % 60), 2, '0', STR_PAD_LEFT);
}
