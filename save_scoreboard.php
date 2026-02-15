<?php
/**
 * POST: save raw body as 2026/scoreboard.csv (overwrite).
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
if ($input === false) {
    $input = '';
}

$dir = __DIR__ . '/2026';
$file = $dir . '/scoreboard.csv';

if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create 2026 directory']);
        exit;
    }
}

$written = @file_put_contents($file, $input, LOCK_EX);
if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write scoreboard.csv']);
    exit;
}

echo json_encode(['ok' => true]);
