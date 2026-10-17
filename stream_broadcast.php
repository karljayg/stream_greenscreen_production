<?php
/**
 * Show-wide broadcast defaults (all casters get these on a fresh session).
 * POST merges one section at a time — does not touch per-user layout files.
 *
 * GET  -> { ok, broadcast: { ... } }
 * POST -> { section: "ytIframeVideos"|..., data: <section payload> }
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
$file = $dir . '/stream_broadcast.json';

function stream_broadcast_defaults(): array
{
    return [
        'version' => 1,
        'eventTitle' => 'SEASON 11',
        'ytIframeVideos' => [
            ['label' => 'INTRO', 'url' => 'https://www.youtube.com/watch?v=vt04Xbq57Dk', 'vol' => 100],
            ['label' => 'FEATURE', 'url' => 'https://www.youtube.com/watch?v=vt04Xbq57Dk', 'vol' => 100],
            ['label' => 'BREAK', 'url' => 'https://youtu.be/O9lNetcn9Y8?si=FaqwLX5I9KkoJecK', 'vol' => 100],
        ],
        'breakSettings' => ['min' => 5, 'sec' => 0, 'msg' => 'be right back...'],
        'ytCrop' => ['top' => 150, 'left' => 10, 'right' => 20, 'bottom' => 100],
        'vdoDirectorUrl' => 'https://vdo.ninja/?director=KJNinjaRoom123&password=FSL&showdirector&push=cBnXZEM',
        'sc2ButtonEffects' => ['effect1' => 'Random Music', 'effect2' => 'FSL intro'],
        'playerIntros' => ['DarkMenace', 'LittleReaper', 'PulledTheBoys', 'HyperTurtle', 'Harouz', 'InfiniteCyclists', 'Random Music', 'FSL intro', 'GG', 'Match GG'],
    ];
}

function normalize_yt_videos($in, array $defaults): array
{
    $out = $defaults;
    if (!is_array($in)) {
        return $out;
    }
    for ($i = 0; $i < 3; $i++) {
        $slot = $in[$i] ?? null;
        if (!is_array($slot)) {
            continue;
        }
        if (isset($slot['label']) && is_string($slot['label']) && trim($slot['label']) !== '') {
            $out[$i]['label'] = substr(trim($slot['label']), 0, 32);
        }
        if (isset($slot['url']) && is_string($slot['url']) && trim($slot['url']) !== '' && strlen($slot['url']) <= 2048) {
            $out[$i]['url'] = trim($slot['url']);
        }
        if (array_key_exists('vol', $slot)) {
            $out[$i]['vol'] = max(5, min(100, (int) $slot['vol']));
        }
    }
    return $out;
}

function normalize_broadcast(array $in, array $defaults): array
{
    $out = $defaults;
    if (isset($in['eventTitle']) && is_string($in['eventTitle'])) {
        $t = trim($in['eventTitle']);
        if ($t !== '') {
            $out['eventTitle'] = substr($t, 0, 120);
        }
    }
    if (isset($in['ytIframeVideos'])) {
        $out['ytIframeVideos'] = normalize_yt_videos($in['ytIframeVideos'], $defaults['ytIframeVideos']);
    }
    if (isset($in['breakSettings']) && is_array($in['breakSettings'])) {
        $b = $in['breakSettings'];
        $out['breakSettings'] = [
            'min' => max(0, min(999, (int) ($b['min'] ?? $defaults['breakSettings']['min']))),
            'sec' => max(0, min(59, (int) ($b['sec'] ?? $defaults['breakSettings']['sec']))),
            'msg' => substr(trim((string) ($b['msg'] ?? $defaults['breakSettings']['msg'])), 0, 200),
        ];
    }
    if (isset($in['ytCrop']) && is_array($in['ytCrop'])) {
        $c = $in['ytCrop'];
        foreach (['top', 'left', 'right', 'bottom'] as $k) {
            if (array_key_exists($k, $c)) {
                $out['ytCrop'][$k] = max(0, min(2000, (int) $c[$k]));
            }
        }
    }
    if (isset($in['vdoDirectorUrl']) && is_string($in['vdoDirectorUrl'])) {
        $u = trim($in['vdoDirectorUrl']);
        if ($u !== '' && strlen($u) <= 2048) {
            $out['vdoDirectorUrl'] = $u;
        }
    }
    if (isset($in['sc2ButtonEffects']) && is_array($in['sc2ButtonEffects'])) {
        $fx = $in['sc2ButtonEffects'];
        if (isset($fx['effect1']) && is_string($fx['effect1'])) {
            $out['sc2ButtonEffects']['effect1'] = substr(trim($fx['effect1']), 0, 80);
        }
        if (isset($fx['effect2']) && is_string($fx['effect2'])) {
            $out['sc2ButtonEffects']['effect2'] = substr(trim($fx['effect2']), 0, 80);
        }
    }
    if (isset($in['playerIntros']) && is_array($in['playerIntros'])) {
        $pi = [];
        for ($i = 0; $i < 10; $i++) {
            $v = $in['playerIntros'][$i] ?? '';
            $pi[] = substr(trim((string) $v), 0, 80);
        }
        $out['playerIntros'] = $pi;
    }
    return $out;
}

function load_broadcast_file(string $file, array $defaults): array
{
    if (!is_file($file) || !is_readable($file)) {
        return $defaults;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return $defaults;
    }
    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        return $defaults;
    }
    if (isset($parsed['broadcast']) && is_array($parsed['broadcast'])) {
        $parsed = $parsed['broadcast'];
    }
    return normalize_broadcast($parsed, $defaults);
}

function save_broadcast_file(string $dir, string $file, array $broadcast): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    $payload = ['version' => 1, 'broadcast' => $broadcast];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

$defaults = stream_broadcast_defaults();
$allowedSections = [
    'eventTitle', 'ytIframeVideos', 'breakSettings', 'ytCrop',
    'vdoDirectorUrl', 'sc2ButtonEffects', 'playerIntros', '_all',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $broadcast = load_broadcast_file($file, $defaults);
    // Legacy: merge yt_iframe_video_settings.json if stream_broadcast has no file yet
    if (!is_file($file)) {
        $legacy = $dir . '/yt_iframe_video_settings.json';
        if (is_readable($legacy)) {
            $leg = json_decode((string) file_get_contents($legacy), true);
            if (is_array($leg) && !empty($leg['videos'])) {
                $broadcast['ytIframeVideos'] = normalize_yt_videos($leg['videos'], $defaults['ytIframeVideos']);
            }
        }
    }
    echo json_encode(['ok' => true, 'broadcast' => $broadcast], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if ($input === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No body']);
        exit;
    }
    $body = json_decode($input, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $section = (string) ($body['section'] ?? '_all');
    if (!in_array($section, $allowedSections, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown section']);
        exit;
    }

    $existing = load_broadcast_file($file, $defaults);
    if ($section === '_all') {
        $data = $body['broadcast'] ?? $body['data'] ?? null;
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing broadcast object']);
            exit;
        }
        $merged = normalize_broadcast(array_merge($existing, $data), $defaults);
    } else {
        $merged = $existing;
        $patch = [$section => $body['data'] ?? null];
        if ($section === 'eventTitle' && isset($body['data']) && is_string($body['data'])) {
            $patch = ['eventTitle' => $body['data']];
        }
        $merged = normalize_broadcast(array_merge($merged, $patch), $defaults);
    }

    if (!save_broadcast_file($dir, $file, $merged)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write file']);
        exit;
    }
    echo json_encode(['ok' => true, 'broadcast' => $merged], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
