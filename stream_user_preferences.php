<?php
/**
 * Per-user layout / machine preferences (layer order, logo positions, volume, etc.).
 * Does not include show-wide broadcast defaults (see stream_broadcast.php).
 *
 * GET  -> { ok, preferences: { ... } }
 * POST -> replaces allowed keys only (partial merge).
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
$username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['username']);
$file = $dir . '/stream_user_preferences_' . $username . '.json';
$legacyFile = $dir . '/settings_' . $username . '.json';

function user_pref_allowed_keys(): array
{
    return [
        'version', 'volume', 'layerOrder', 'logos', 'tlsScorePos', 'tlsScoreEnabled',
        'musicMode', 'musicVol', 'musicFade', 'productionFiles', 'chromaKey',
        'sceneMoodMap', 'moodSongs', 'sceneStages',
    ];
}

function extract_user_prefs_from_legacy(array $legacy): array
{
    $out = ['version' => 1];
    $map = [
        'volume' => 'volume',
        'layerOrder' => 'layerOrder',
        'logos' => 'logos',
        'tlsScorePos' => 'tlsScorePos',
        'musicMode' => 'musicMode',
        'musicVol' => 'musicVol',
        'musicFade' => 'musicFade',
        'productionFiles' => 'productionFiles',
        'chromaKey' => 'chromaKey',
        'sceneMoodMap' => 'sceneMoodMap',
        'moodSongs' => 'moodSongs',
        'sceneStages' => 'sceneStages',
    ];
    foreach ($map as $from => $to) {
        if (array_key_exists($from, $legacy)) {
            $out[$to] = $legacy[$from];
        }
    }
    if (isset($legacy['order']) && !isset($out['layerOrder'])) {
        $out['layerOrder'] = $legacy['order'];
    }
    return $out;
}

function merge_user_prefs(array $existing, array $incoming): array
{
    $allowed = array_flip(user_pref_allowed_keys());
    $out = $existing;
    foreach ($incoming as $k => $v) {
        if (!isset($allowed[$k])) {
            continue;
        }
        $out[$k] = $v;
    }
    if (!isset($out['version'])) {
        $out['version'] = 1;
    }
    return $out;
}

function load_user_prefs(string $file, string $legacyFile): array
{
    if (is_file($file) && is_readable($file)) {
        $raw = file_get_contents($file);
        $parsed = json_decode((string) $raw, true);
        if (is_array($parsed)) {
            return merge_user_prefs(['version' => 1], $parsed);
        }
    }
    if (is_file($legacyFile) && is_readable($legacyFile)) {
        $raw = file_get_contents($legacyFile);
        $parsed = json_decode((string) $raw, true);
        if (is_array($parsed)) {
            return extract_user_prefs_from_legacy($parsed);
        }
    }
    return ['version' => 1];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'preferences' => load_user_prefs($file, $legacyFile)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $incoming = isset($data['preferences']) && is_array($data['preferences']) ? $data['preferences'] : $data;
    $existing = load_user_prefs($file, $legacyFile);
    $merged = merge_user_prefs($existing, $incoming);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create data directory']);
        exit;
    }
    $written = @file_put_contents(
        $file,
        json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write file']);
        exit;
    }
    echo json_encode(['ok' => true, 'preferences' => $merged], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
