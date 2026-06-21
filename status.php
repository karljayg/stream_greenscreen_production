<?php
/**
 * /status — public read-only live snapshot of the stream production tool.
 *
 *   GET /status            → full snapshot + recent events
 *   GET /status?since=<n>  → if seq unchanged, a tiny {changed:false}; otherwise
 *                            the snapshot with recent_events filtered to id > n.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$statusFile = __DIR__ . '/data/status.json';

// Stale after this long without a heartbeat (reporter beats every ~2s).
$ALIVE_WINDOW_MS = 10000;

if (!file_exists($statusFile)) {
    echo json_encode([
        'schema_version' => 1,
        'seq'            => 0,
        'stream_alive'   => false,
        'heartbeat_age_ms' => null,
        'updated_at'     => null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$status = json_decode(@file_get_contents($statusFile), true);
if (!is_array($status)) $status = [];

$seq = (int) ($status['seq'] ?? 0);

// Liveness from heartbeat timestamp.
$heartbeatAge = null;
$alive = false;
if (!empty($status['heartbeat_at'])) {
    $ts = strtotime($status['heartbeat_at']);
    if ($ts !== false) {
        $heartbeatAge = max(0, (int) round((microtime(true) - $ts) * 1000));
        $alive = $heartbeatAge <= $ALIVE_WINDOW_MS;
    }
}

$since = isset($_GET['since']) ? (int) $_GET['since'] : null;

// Cheap unchanged response for pollers.
if ($since !== null && $seq <= $since) {
    echo json_encode([
        'seq'              => $seq,
        'changed'          => false,
        'stream_alive'     => $alive,
        'heartbeat_age_ms' => $heartbeatAge,
        'updated_at'       => $status['updated_at'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Drop internal bookkeeping fields (prefixed with "_").
$out = [];
foreach ($status as $k => $v) {
    if ($k !== '' && $k[0] === '_') continue;
    $out[$k] = $v;
}

// Filter recent events when polling incrementally.
if ($since !== null && isset($out['recent_events']) && is_array($out['recent_events'])) {
    $out['recent_events'] = array_values(array_filter($out['recent_events'], function ($e) use ($since) {
        return isset($e['id']) && (int) $e['id'] > $since;
    }));
}

$out['changed']          = true;
$out['stream_alive']     = $alive;
$out['heartbeat_age_ms'] = $heartbeatAge;

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
