<?php
/**
 * FSL rankings: synced from https://psistorm.com/fsl/rankings/rankings.json
 * GET: returns local rankings JSON (array).
 * POST: saves JSON body to data/rankings.json (overwrites).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$dir = __DIR__ . '/data';
$file = $dir . '/rankings.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (is_file($file) && is_readable($file)) {
        $raw = file_get_contents($file);
        if ($raw !== false) {
            echo $raw;
            exit;
        }
    }
    echo '[]';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = null;
    if ($input !== false && $input !== '') {
        $data = json_decode($input, true);
    }
    // Refresh: fetch from psistorm (server-side, no CORS) and save
    if (is_array($data) && isset($data['refresh']) && $data['refresh'] === true) {
        $url = 'https://psistorm.com/fsl/rankings/rankings.json';
        $remote = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false, // Windows/XAMPP often has no CA bundle
            ]);
            $remote = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($remote === false) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Fetch failed: ' . ($curlErr ?: 'unknown')]);
                exit;
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 15], 'ssl' => ['verify_peer' => false]]);
            $remote = @file_get_contents($url, false, $ctx);
        }
        if ($remote === false || $remote === '') {
            // Apache PHP often has no cURL / allow_url_fopen; run CLI PHP which does
            $cliScript = __DIR__ . DIRECTORY_SEPARATOR . 'fetch_rankings_remote.php';
            $php = 'php';
            if (defined('PHP_BINARY')) {
                $php = PHP_BINARY;
            } elseif (DIRECTORY_SEPARATOR === '\\' && is_file('C:\\xampp\\php\\php.exe')) {
                $php = 'C:\\xampp\\php\\php.exe';
            }
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($cliScript);
            $ret = -1;
            if (is_file($cliScript)) {
                @exec($cmd . ' 2>NUL', $_, $ret);
            }
            if ($ret !== 0 || !is_file($file) || !is_readable($file)) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Could not fetch remote rankings']);
                exit;
            }
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Remote rankings invalid JSON']);
                exit;
            }
        } else {
            $data = json_decode($remote, true);
            if (!is_array($data)) {
                http_response_code(502);
                echo json_encode(['ok' => false, 'error' => 'Remote rankings invalid JSON']);
                exit;
            }
        }
    }
    if ($data === null && ($input === false || $input === '')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No body']);
        exit;
    }
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Expected JSON array']);
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
