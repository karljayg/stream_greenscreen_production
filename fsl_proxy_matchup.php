<?php
/**
 * Same-origin proxy for FSL matchup spider HTML (avoids browser CORS on fetch() to psistorm).
 * GET: name, division (S|A|B) — returns upstream HTML body for client-side error sniffing.
 * Iframe src should still point at STREAM_FSL_SPIDER_MATCHUP_URL directly (display only).
 *
 * Abuse / load: authenticated users only (auth-gate). Per-session rate limit + max response
 * bytes + short upstream timeout. For volumetric DDoS use reverse proxy / WAF / mod_evasive.
 *
 * Optional defines in config.local.php (before this script is hit, e.g. from index bootstrap path):
 *   define('FSL_PROXY_MAX_REQUESTS_PER_MINUTE', 40);
 *   define('FSL_PROXY_MAX_RESPONSE_BYTES', 2097152); // 2 MiB
 */
$pathPrefix = '';
require_once __DIR__ . '/partials/auth-gate.php';

require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/partials/production-files-bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$rlMax = (defined('FSL_PROXY_MAX_REQUESTS_PER_MINUTE') && (int) FSL_PROXY_MAX_REQUESTS_PER_MINUTE > 0)
    ? (int) FSL_PROXY_MAX_REQUESTS_PER_MINUTE
    : 40;
$maxBytes = (defined('FSL_PROXY_MAX_RESPONSE_BYTES') && (int) FSL_PROXY_MAX_RESPONSE_BYTES > 0)
    ? (int) FSL_PROXY_MAX_RESPONSE_BYTES
    : 2097152;

$now   = time();
$win   = (int) floor($now / 60);
$rlKey = '_fsl_proxy_rl';
if (!isset($_SESSION[$rlKey]) || !is_array($_SESSION[$rlKey]) || (int) ($_SESSION[$rlKey]['w'] ?? 0) !== $win) {
    $_SESSION[$rlKey] = ['w' => $win, 'n' => 0];
}
$_SESSION[$rlKey]['n'] = (int) $_SESSION[$rlKey]['n'] + 1;
if ($_SESSION[$rlKey]['n'] > $rlMax) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 60');
    echo 'Rate limit exceeded';
    exit;
}

$name     = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$division = isset($_GET['division']) ? strtoupper(trim((string) $_GET['division'])) : '';

if ($name === '' || strlen($name) > 80 || strpos($name, "\0") !== false) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid name';
    exit;
}
if (!preg_match('/^[SAB]$/', $division)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid division';
    exit;
}

$base = isset($streamFslSpiderMatchupUrl) ? (string) $streamFslSpiderMatchupUrl : '';
if ($base === '') {
    $base = 'https://psistorm.com/fsl/view_spider_chart_player_matchup.php';
}
$target = $base . '?name=' . rawurlencode($name) . '&division=' . rawurlencode($division);

$baseHost = @parse_url($base, PHP_URL_HOST);
$tHost    = @parse_url($target, PHP_URL_HOST);
if (!$baseHost || !$tHost || strcasecmp((string) $baseHost, (string) $tHost) !== 0) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Upstream misconfiguration';
    exit;
}

$body = '';
$code = 0;
$truncated = false;

if (function_exists('curl_init')) {
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_NOPROGRESS       => false,
        CURLOPT_FOLLOWLOCATION   => true,
        CURLOPT_MAXREDIRS        => 5,
        CURLOPT_TIMEOUT          => 25,
        CURLOPT_CONNECTTIMEOUT   => 8,
        CURLOPT_SSL_VERIFYPEER   => true,
        CURLOPT_USERAGENT        => 'StreamProductionTool/1.0 (matchup proxy)',
        CURLOPT_ENCODING         => '',
        CURLOPT_WRITEFUNCTION    => static function ($ch, $chunk) use (&$body, $maxBytes, &$truncated) {
            $len = strlen($chunk);
            if ($len === 0) {
                return 0;
            }
            if (strlen($body) + $len > $maxBytes) {
                $truncated = true;
                return 0;
            }
            $body .= $chunk;
            return $len;
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($ok === false && $body === '' && !$truncated) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Upstream fetch failed';
        exit;
    }
    if ($truncated) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Upstream response too large';
        exit;
    }
} else {
    $ctx = stream_context_create([
        'http' => [
            'timeout'         => 25,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'header'          => "User-Agent: StreamProductionTool/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($target, false, $ctx);
    $code = 200;
    if ($raw === false) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Upstream fetch failed';
        exit;
    }
    if (strlen($raw) > $maxBytes) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Upstream response too large';
        exit;
    }
    $body = $raw;
}

if ($body === '') {
    http_response_code(502);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body>database connection failed</body></html>';
    exit;
}

http_response_code($code >= 100 && $code < 600 ? $code : 200);
header('Content-Type: text/html; charset=utf-8');
echo $body;
