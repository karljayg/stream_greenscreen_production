<?php
/**
 * Shared overlay media settings (global for all users):
 * - schedule / bracket (production_files/…)
 * - team scene buttons: ash / pog / ptb / st (production_files/… or 2026/…)
 *
 * GET  -> returns merged settings
 * POST -> merges into existing file (partial updates allowed)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$dir = __DIR__ . '/data';
$file = $dir . '/scene_video_settings.json';

$defaults = [
    'schedule' => 'production_files/2026_FSL_schedule_now.mp4',
    'bracket' => 'production_files/2026_FSL_schedule_now.mp4',
    'teams' => [
        'ash' => '2026/ASH.mp4',
        'pog' => '2026/POG.mp4',
        'ptb' => '2026/PTB.mp4',
        'st' => '2026/ST.mp4',
    ],
];

function normalize_media_path($p): string {
    if (!is_string($p)) {
        return '';
    }
    $p = trim(str_replace('\\', '/', $p));
    if ($p === '' || strpos($p, '..') !== false) {
        return '';
    }
    if (strpos($p, 'production_files/') === 0) {
        return $p;
    }
    if (strpos($p, '2026/') === 0) {
        return $p;
    }
    return '';
}

function normalize_scene_settings($in, array $defaults): array {
    $out = $defaults;
    if (!is_array($in)) {
        return $out;
    }

    foreach (['schedule', 'bracket'] as $k) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $v = normalize_media_path($in[$k]);
        if ($v !== '') {
            $out[$k] = $v;
        }
    }

    if (isset($in['teams']) && is_array($in['teams'])) {
        $teamOut = is_array($out['teams']) ? $out['teams'] : $defaults['teams'];
        foreach (['ash', 'pog', 'ptb', 'st'] as $tk) {
            if (!array_key_exists($tk, $in['teams'])) {
                continue;
            }
            $tv = normalize_media_path($in['teams'][$tk]);
            if ($tv !== '') {
                $teamOut[$tk] = $tv;
            }
        }
        $out['teams'] = $teamOut;
    }

    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = $defaults;
    if (is_file($file) && is_readable($file)) {
        $raw = file_get_contents($file);
        if ($raw !== false) {
            $parsed = json_decode($raw, true);
            $settings = normalize_scene_settings($parsed, $defaults);
        }
    }
    echo json_encode(['ok' => true, 'settings' => $settings], JSON_UNESCAPED_SLASHES);
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

    $existing = $defaults;
    if (is_file($file) && is_readable($file)) {
        $rawExisting = file_get_contents($file);
        if ($rawExisting !== false) {
            $parsedExisting = json_decode($rawExisting, true);
            $existing = normalize_scene_settings($parsedExisting, $defaults);
        }
    }

    $settings = normalize_scene_settings($data, $existing);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create data directory']);
        exit;
    }
    $written = @file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write file']);
        exit;
    }

    echo json_encode(['ok' => true, 'settings' => $settings], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
