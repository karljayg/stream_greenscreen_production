<?php
// Music playback stats — no auth required (public player also contributes).
// GET  → returns current stats JSON
// POST → merges a delta object into stats, returns updated totals

$statsFile = __DIR__ . '/data/music_stats.json';

$empty = ['songs' => [], 'moods' => [], 'totals' => ['plays' => 0, 'skips' => 0, 'seconds' => 0.0]];

function loadStats($file, $empty) {
    if (!file_exists($file)) return $empty;
    $raw = @file_get_contents($file);
    if (!$raw) return $empty;
    $d = json_decode($raw, true);
    return is_array($d) ? array_merge($empty, $d) : $empty;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo file_exists($statsFile) ? file_get_contents($statsFile) : json_encode($empty);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$delta = json_decode(file_get_contents('php://input'), true);
if (!is_array($delta)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit;
}

$stats = loadStats($statsFile, $empty);

// Merge songs
foreach (($delta['songs'] ?? []) as $file => $d) {
    if (!isset($stats['songs'][$file]))
        $stats['songs'][$file] = ['plays' => 0, 'skips' => 0, 'seconds' => 0.0];
    $stats['songs'][$file]['plays']   += (int)   ($d['plays']   ?? 0);
    $stats['songs'][$file]['skips']   += (int)   ($d['skips']   ?? 0);
    $stats['songs'][$file]['seconds'] += (float) ($d['seconds'] ?? 0);
}

// Merge moods
foreach (($delta['moods'] ?? []) as $mood => $d) {
    if (!isset($stats['moods'][$mood]))
        $stats['moods'][$mood] = ['plays' => 0, 'seconds' => 0.0];
    $stats['moods'][$mood]['plays']   += (int)   ($d['plays']   ?? 0);
    $stats['moods'][$mood]['seconds'] += (float) ($d['seconds'] ?? 0);
}

// Merge totals
$stats['totals']['plays']   = ($stats['totals']['plays']   ?? 0) + (int)   ($delta['totals']['plays']   ?? 0);
$stats['totals']['skips']   = ($stats['totals']['skips']   ?? 0) + (int)   ($delta['totals']['skips']   ?? 0);
$stats['totals']['seconds'] = ($stats['totals']['seconds'] ?? 0) + (float) ($delta['totals']['seconds'] ?? 0);

$stats['updated'] = date('c');

file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode(['ok' => true, 'totals' => $stats['totals']]);
