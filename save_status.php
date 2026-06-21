<?php
/**
 * Status writer — receives the producer's live UI state from status-reporter.js,
 * keeps a live snapshot in data/status.json, and appends meaningful events to a
 * per-day activity log (data/status_log/<YYYY-MM-DD>.jsonl). Public, no auth.
 *
 * The authoritative match score is read here from 2026/scoreboard.csv and diffed,
 * so score changes are captured no matter how the producer edits them.
 *
 * GET  → nothing useful (use status.php); returns the live snapshot for debugging.
 * POST → merge a client push: { user, scene, music, events: [...] }.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDir    = __DIR__ . '/data';
$logDir     = $dataDir . '/status_log';
$statusFile = $dataDir . '/status.json';
$indexFile  = $logDir . '/index.json';
$csvFile    = __DIR__ . '/2026/scoreboard.csv';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo file_exists($statusFile) ? file_get_contents($statusFile) : '{}';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$now   = date('c');
$today = date('Y-m-d');

// ---- Load current status ------------------------------------------------
$status = [];
if (file_exists($statusFile)) {
    $raw = @file_get_contents($statusFile);
    $d   = json_decode($raw, true);
    if (is_array($d)) $status = $d;
}
$seq = isset($status['seq']) ? (int) $status['seq'] : 0;

// ---- Read authoritative score from scoreboard.csv -----------------------
function readScoreboard($csvFile) {
    if (!file_exists($csvFile)) return null;
    $fh = @fopen($csvFile, 'r');
    if (!$fh) return null;
    $row = fgetcsv($fh);
    fclose($fh);
    if (!is_array($row)) return null;
    // Row 1 layout: [_, _, teamA, _, scoreA, _, teamB, _, scoreB, ...]
    $nameA = isset($row[2]) ? trim($row[2]) : '';
    $nameB = isset($row[6]) ? trim($row[6]) : '';
    $scoreA = (isset($row[4]) && $row[4] !== '') ? (int) $row[4] : 0;
    $scoreB = (isset($row[8]) && $row[8] !== '') ? (int) $row[8] : 0;
    return ['nameA' => $nameA, 'nameB' => $nameB, 'a' => $scoreA, 'b' => $scoreB];
}

$newEvents = []; // each: ['type'=>..., 'data'=>...]

// ---- Snapshot: scene ----------------------------------------------------
if (isset($in['scene']) && is_array($in['scene'])) {
    $active   = isset($in['scene']['active']) ? $in['scene']['active'] : null;
    $label    = isset($in['scene']['label']) ? $in['scene']['label'] : null;
    $previous = isset($in['scene']['previous']) ? $in['scene']['previous'] : null;
    $prevScene = isset($status['scene']) && is_array($status['scene']) ? $status['scene'] : null;
    $since = ($prevScene && ($prevScene['active'] ?? null) === $active && !empty($prevScene['since']))
        ? $prevScene['since'] : $now;
    $status['scene'] = ['active' => $active, 'label' => $label, 'since' => $since, 'previous' => $previous];
}

// ---- Snapshot: music ----------------------------------------------------
if (isset($in['music']) && is_array($in['music'])) {
    $status['music'] = $in['music'];
}

// ---- Client events (scene/music/intro/gg) -------------------------------
foreach ((isset($in['events']) && is_array($in['events'])) ? $in['events'] : [] as $ev) {
    if (!is_array($ev) || empty($ev['type'])) continue;
    $type = $ev['type'];
    $data = isset($ev['data']) && is_array($ev['data']) ? $ev['data'] : [];
    if ($type === 'gg') {
        $kind = ($data['kind'] ?? '') === 'match_gg' ? 'match_gg' : 'gg';
        $status['gg'] = ['state' => $kind, 'last_event' => ['type' => $kind, 'at' => $now]];
    } elseif ($type === 'intro') {
        $status['player_intro'] = ['last_played' => ($data['player'] ?? null), 'at' => $now];
    }
    $newEvents[] = ['type' => $type, 'data' => $data];
}

// ---- Score diff (authoritative) -----------------------------------------
$sb = readScoreboard($csvFile);
if ($sb !== null) {
    $prev = isset($status['_score']) && is_array($status['_score']) ? $status['_score'] : null;
    $teamA = ['name' => $sb['nameA'], 'score' => $sb['a']];
    $teamB = ['name' => $sb['nameB'], 'score' => $sb['b']];
    if (!isset($status['match']) || !is_array($status['match'])) {
        $status['match'] = ['team_a' => $teamA, 'team_b' => $teamB, 'last_change' => null, 'series_winner' => null];
    } else {
        $status['match']['team_a'] = $teamA;
        $status['match']['team_b'] = $teamB;
    }
    $ggState = $status['gg']['state'] ?? null;
    $changes = [];
    if ($prev !== null) {
        if ((int) $prev['a'] !== $sb['a']) $changes[] = ['team' => 'a', 'from' => (int) $prev['a'], 'to' => $sb['a']];
        if ((int) $prev['b'] !== $sb['b']) $changes[] = ['team' => 'b', 'from' => (int) $prev['b'], 'to' => $sb['b']];
    }
    foreach ($changes as $c) {
        $c['team_a'] = $teamA;
        $c['team_b'] = $teamB;
        $newEvents[] = ['type' => 'score', 'data' => $c];
        $status['match']['last_change'] = ['at' => $now, 'team' => $c['team'], 'from' => $c['from'], 'to' => $c['to']];
        // Score-based series winner: a score change while Match GG is active.
        if ($ggState === 'match_gg' && $sb['a'] !== $sb['b']) {
            $winTeam = $sb['a'] > $sb['b'] ? 'a' : 'b';
            $winName = $winTeam === 'a' ? $sb['nameA'] : $sb['nameB'];
            $status['match']['series_winner'] = $winName;
            $newEvents[] = ['type' => 'winner', 'data' => ['team' => $winTeam, 'name' => $winName]];
        }
    }
    $status['_score'] = ['a' => $sb['a'], 'b' => $sb['b']];
}

// ---- Day rollover: nothing to reset (event ids are global) --------------
// ---- Append events to the day log + assign global ids -------------------
$recent = isset($status['recent_events']) && is_array($status['recent_events']) ? $status['recent_events'] : [];
if (!empty($newEvents)) {
    $logFile = $logDir . '/' . $today . '.jsonl';
    $existed = file_exists($logFile);
    $fh = @fopen($logFile, 'a');
    foreach ($newEvents as $ev) {
        $seq++;
        $record = ['id' => $seq, 'at' => $now, 'type' => $ev['type'], 'data' => $ev['data']];
        if ($fh) fwrite($fh, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $recent[] = $record;
    }
    if ($fh) fclose($fh);

    // Keep only the most recent 50 in the live snapshot ring buffer.
    if (count($recent) > 50) $recent = array_slice($recent, -50);

    $status['updated_at'] = $now;
    updateIndex($indexFile, $today, count($newEvents), $now, !$existed);
}

$status['recent_events'] = $recent;
$status['schema_version'] = 1;
$status['seq']          = $seq;
$status['date']         = $today;
$status['heartbeat_at'] = $now;

// ---- Atomic write -------------------------------------------------------
$tmp = $statusFile . '.tmp';
@file_put_contents($tmp, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
@rename($tmp, $statusFile);

echo json_encode(['ok' => true, 'seq' => $seq]);

function updateIndex($indexFile, $today, $added, $now, $isNewDay) {
    $idx = ['schema_version' => 1, 'generated_at' => $now, 'days' => []];
    if (file_exists($indexFile)) {
        $d = json_decode(@file_get_contents($indexFile), true);
        if (is_array($d)) $idx = array_merge($idx, $d);
    }
    if (!isset($idx['days']) || !is_array($idx['days'])) $idx['days'] = [];
    if (!isset($idx['days'][$today]) || !is_array($idx['days'][$today])) {
        $idx['days'][$today] = ['date' => $today, 'event_count' => 0, 'first_event' => $now, 'last_event' => $now];
    }
    $idx['days'][$today]['event_count'] += $added;
    $idx['days'][$today]['last_event']   = $now;
    if (empty($idx['days'][$today]['first_event'])) $idx['days'][$today]['first_event'] = $now;
    $idx['generated_at'] = $now;
    $tmp = $indexFile . '.tmp';
    @file_put_contents($tmp, json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    @rename($tmp, $indexFile);
}
