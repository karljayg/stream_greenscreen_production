<?php
/**
 * /status/log — public read-only activity log.
 *
 *   GET /status/log                    → index of days (newest first)
 *   GET /status/log?date=YYYY-MM-DD    → that day's full events + summary
 *   GET /status/log?date=today         → current day
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$logDir    = __DIR__ . '/data/status_log';
$indexFile = $logDir . '/index.json';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if ($date === 'today') $date = date('Y-m-d');

// ---- Index mode ---------------------------------------------------------
if ($date === '') {
    $days = [];
    if (file_exists($indexFile)) {
        $idx = json_decode(@file_get_contents($indexFile), true);
        if (is_array($idx) && isset($idx['days']) && is_array($idx['days'])) {
            $days = array_values($idx['days']);
        }
    }
    usort($days, function ($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });
    echo json_encode([
        'schema_version' => 1,
        'generated_at'   => date('c'),
        'days'           => $days,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Single-day mode ----------------------------------------------------
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date; use YYYY-MM-DD or "today"']);
    exit;
}

$logFile = $logDir . '/' . $date . '.jsonl';
if (!file_exists($logFile)) {
    echo json_encode([
        'schema_version' => 1,
        'date'           => $date,
        'event_count'    => 0,
        'events'         => [],
        'summary'        => null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$events = [];
$fh = @fopen($logFile, 'r');
if ($fh) {
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $e = json_decode($line, true);
        if (is_array($e)) $events[] = $e;
    }
    fclose($fh);
}

// ---- Summary ------------------------------------------------------------
$summary = [
    'final_score'   => null,
    'series_winner' => null,
    'ggs'           => 0,
    'match_ggs'     => 0,
    'scenes_shown'  => [],
    'moods_played'  => [],
    'intros_played' => [],
];
$introsSeen = [];
foreach ($events as $e) {
    $type = $e['type'] ?? '';
    $data = $e['data'] ?? [];
    if ($type === 'gg') {
        if (($data['kind'] ?? '') === 'match_gg') $summary['match_ggs']++;
        else $summary['ggs']++;
    } elseif ($type === 'scene') {
        $to = $data['to'] ?? null;
        if ($to) $summary['scenes_shown'][$to] = ($summary['scenes_shown'][$to] ?? 0) + 1;
    } elseif ($type === 'music') {
        $mood = $data['mood'] ?? null;
        if ($mood) $summary['moods_played'][$mood] = ($summary['moods_played'][$mood] ?? 0) + 1;
    } elseif ($type === 'intro') {
        $p = $data['player'] ?? null;
        if ($p && !isset($introsSeen[$p])) { $introsSeen[$p] = true; $summary['intros_played'][] = $p; }
    } elseif ($type === 'score') {
        if (isset($data['team_a']) && isset($data['team_b'])) {
            $summary['final_score'] = ['team_a' => $data['team_a'], 'team_b' => $data['team_b']];
        }
    } elseif ($type === 'winner') {
        $summary['series_winner'] = $data['name'] ?? null;
    }
}

$out = [
    'schema_version' => 1,
    'date'           => $date,
    'first_event'    => isset($events[0]['at']) ? $events[0]['at'] : null,
    'last_event'     => count($events) ? $events[count($events) - 1]['at'] : null,
    'event_count'    => count($events),
    'summary'        => $summary,
    'events'         => $events,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
