<?php
/**
 * Server-side settings for Stream Production Tool.
 * GET: returns current settings JSON (anyone opening the tool loads this).
 * POST: saves JSON body to server (overwrites current settings).
 */
header('Content-Type: application/json; charset=utf-8');

$dir = __DIR__ . '/data';
$file = $dir . '/stream_production_settings.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (is_file($file) && is_readable($file)) {
        $raw = file_get_contents($file);
        if ($raw !== false) {
            echo $raw;
            exit;
        }
    }
    echo '{}';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if ($input === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No body']);
        exit;
    }
    $data = json_decode($input, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not create data directory']);
            exit;
        }
    }
    $written = @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write file']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
