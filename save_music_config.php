<?php
// Saves music config.
// Normal POST:   writes to data/{which}_{username}.json  (per-user override).
// POST ?promote: copies the per-user override to the global data/{which}.json.
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$which   = $_GET['which'] ?? '';
$allowed = ['mood_songs', 'scene_mood_map', 'scene_stages'];
if (!in_array($which, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown config']);
    exit;
}

$body   = file_get_contents('php://input');
$parsed = json_decode($body, true);
if ($parsed === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$safeUser  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username']);
$userPath  = __DIR__ . '/data/' . $which . '_' . $safeUser . '.json';
$encoded   = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Always write the per-user file
$written = file_put_contents($userPath, $encoded);
if ($written === false) {
    echo json_encode(['error' => 'Write failed']);
    exit;
}

// ?promote=1 — also overwrite the global default
if (!empty($_GET['promote'])) {
    $globalPath    = __DIR__ . '/data/' . $which . '.json';
    $globalWritten = file_put_contents($globalPath, $encoded);
    echo json_encode($globalWritten !== false
        ? ['ok' => true, 'promoted' => true]
        : ['ok' => true, 'promoted' => false, 'warn' => 'Global write failed']);
    exit;
}

echo json_encode(['ok' => true]);
