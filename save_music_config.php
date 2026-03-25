<?php
// Saves user-specific music config overrides.
// Writes to data/{which}_{username}.json, never touching global defaults.
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
$allowed = ['mood_songs', 'scene_mood_map'];
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

$safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username']);
$path     = __DIR__ . '/data/' . $which . '_' . $safeUser . '.json';
$written  = file_put_contents($path, json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo json_encode($written !== false ? ['ok' => true] : ['error' => 'Write failed']);
