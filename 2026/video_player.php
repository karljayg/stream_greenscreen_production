<?php
/**
 * Reusable fullscreen video player (iframe).
 *   ?v=path under project (e.g. 2026/foo.mp4) or bare filename.mp4
 *   ?stream=1  – stream file bytes (HEAD supported) for same-origin embed
 *   ?front=true – optional; parent uses for z-index (ignored here)
 */
$pathPrefix = '../';
if (is_readable(__DIR__ . '/../config.local.php')) {
    require_once __DIR__ . '/../config.local.php';
}
session_start();
require_once __DIR__ . '/../partials/production-files-bootstrap.php';

$raw = isset($_GET['v']) ? (string) $_GET['v'] : '';
$raw = str_replace('\\', '/', trim($raw));
$raw = ltrim($raw, '/');

/**
 * Resolve $rel (relative to project root) to an absolute file path under $projectRoot.
 */
function stream_resolve_media_path(string $rel, ?string $projectRoot): ?string
{
    if (!$projectRoot || $rel === '') {
        return null;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $allowedExt = ['mp4', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    if (strpos($rel, '..') !== false || strpos($rel, "\0") !== false || !in_array($ext, $allowedExt, true)) {
        return null;
    }
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $rpFile = @realpath($candidate);
    if ($rpFile && is_file($rpFile)) {
        return $rpFile;
    }
    $dir = dirname($candidate);
    $base = basename($candidate);
    $rpDir = @realpath($dir);
    if ($rpDir && $base !== '' && $base !== '.' && $base !== '..') {
        $fallback = $rpDir . DIRECTORY_SEPARATOR . $base;
        if (is_file($fallback)) {
            return $fallback;
        }
    }
    return null;
}

$projectRoot = realpath(__DIR__ . '/..');

// --- stream=1: same-origin media bytes (iframe src + HEAD existence checks) ---
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
    $path = stream_resolve_media_path($raw, $projectRoot);
    $pathNorm = $path ? str_replace('\\', '/', $path) : '';
    $rootNorm = $projectRoot ? rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' : '';
    $isValidFile = $path && $rootNorm && strpos($pathNorm, $rootNorm) === 0 && is_file($path);
    if (!$isValidFile) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = [
        'mp4' => 'video/mp4',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif'
    ];
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Accept-Ranges: bytes');
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'HEAD') {
        exit;
    }
    readfile($path);
    exit;
}

// --- Local project file: stream embed (images + nested paths under repo root) ---
$pathLocal = stream_resolve_media_path($raw, $projectRoot);
$pathNormLocal = $pathLocal ? str_replace('\\', '/', $pathLocal) : '';
$rootNorm = $projectRoot ? rtrim(str_replace('\\', '/', $projectRoot), '/') . '/' : '';
$isValidLocal = $pathLocal && $rootNorm && strpos($pathNormLocal, $rootNorm) === 0 && is_file($pathLocal);
$extLocal = strtolower(pathinfo($raw, PATHINFO_EXTENSION));
$isVideo = $extLocal === 'mp4';

if ($isValidLocal) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; }
video, img { width: 100%; height: 100%; object-fit: cover; }</style>
</head>
<body>
<?php if ($isVideo) { ?>
<video id="bg-video" src="?stream=1&amp;v=<?php echo rawurlencode($raw); ?>" autoplay loop muted playsinline></video>
<script>
(function() {
  function tryPlay() {
    var v = document.getElementById('bg-video');
    if (v) v.play().catch(function() {});
  }
  tryPlay();
  window.addEventListener('load', tryPlay);
  window.addEventListener('pageshow', function(ev) { if (ev.persisted) tryPlay(); });
  document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'visible') tryPlay(); });
})();
</script>
<?php } else { ?>
<img src="?stream=1&amp;v=<?php echo rawurlencode($raw); ?>" alt="">
<?php } ?>
</body>
</html>
<?php
    exit;
}

// --- Basename + bootstrap: remote scene mirror / production_files video (display-only URL) ---
$file = $raw ? basename($raw) : '';
$path2026 = $file ? (__DIR__ . DIRECTORY_SEPARATOR . $file) : '';
$pathPfVideo = $file ? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'production_files' . DIRECTORY_SEPARATOR . 'video' . DIRECTORY_SEPARATOR . $file) : '';

$pfIsRemote = (($streamPfBootstrap['mode'] ?? '') === 'remote');
$pfRb       = isset($streamPfBootstrap['remoteBaseUrl']) ? rtrim((string) $streamPfBootstrap['remoteBaseUrl'], "/\\ \t") : '';
$pfRoot     = ($pfIsRemote && $pfRb !== '') ? ($pfRb . '/') : '';

$remote2026Url = '';
if ($file && isset($streamSceneAssetsBase) && is_string($streamSceneAssetsBase) && $streamSceneAssetsBase !== '') {
    $remote2026Url = rtrim($streamSceneAssetsBase, "/\\ \t") . '/2026/' . rawurlencode($file);
}

$videoSrc = '';
if ($file && $path2026 && is_file($path2026)) {
    $videoSrc = $file;
} elseif ($file && $remote2026Url !== '') {
    $videoSrc = $remote2026Url;
} elseif ($file && $pathPfVideo && is_file($pathPfVideo)) {
    $videoSrc = '../production_files/video/' . rawurlencode($file);
} elseif ($file && $pfRoot !== '') {
    $videoSrc = $pfRoot . 'video/' . rawurlencode($file);
}

if ($file && $videoSrc !== '') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; }
video { width: 100%; height: 100%; object-fit: cover; }</style>
</head>
<body>
<?php /* No crossorigin: display-only embed; anonymous would require CORS on /2026/ which differs from production_files. */ ?>
<video id="bg-video" src="<?php echo htmlspecialchars($videoSrc, ENT_QUOTES, 'UTF-8'); ?>" autoplay loop muted playsinline></video>
<script>
(function() {
  function tryPlay() {
    var v = document.getElementById('bg-video');
    if (v) v.play().catch(function() {});
  }
  tryPlay();
  window.addEventListener('load', tryPlay);
  window.addEventListener('pageshow', function(ev) { if (ev.persisted) tryPlay(); });
  document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'visible') tryPlay(); });
})();
</script>
</body>
</html>
<?php
} else {
    header('Content-Type: text/html; charset=utf-8');
    $reported = $file !== '' ? $file : ($raw !== '' ? $raw : 'none');
    ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body>
<script>
(function() {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'video-error', file: '<?php echo addslashes($reported); ?>' }, '*');
    }
  } catch (e) {}
})();
</script>
<p>error: <?php echo htmlspecialchars($reported); ?> not found</p>
</body>
</html>
<?php
}
