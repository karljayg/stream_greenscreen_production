<?php
/**
 * Reusable fullscreen video player (iframe).
 *   ?v=filename.mp4  – prefers this directory (2026/), then same layout on remote ({app}/2026/ when production_files are remote), then ../production_files/video/ for local-only intros.
 *   ?front=true      – optional; parent uses it for z-index (ignored here).
 */
$pathPrefix = '../';
if (is_readable(__DIR__ . '/../config.local.php')) {
    require_once __DIR__ . '/../config.local.php';
}
session_start();
require_once __DIR__ . '/../partials/production-files-bootstrap.php';

$raw = isset($_GET['v']) ? $_GET['v'] : '';
$file = $raw ? basename($raw) : '';
$path2026 = $file ? (__DIR__ . DIRECTORY_SEPARATOR . $file) : '';
$pathPfVideo = $file ? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'production_files' . DIRECTORY_SEPARATOR . 'video' . DIRECTORY_SEPARATOR . $file) : '';

$pfIsRemote = (($streamPfBootstrap['mode'] ?? '') === 'remote');
$pfRb       = isset($streamPfBootstrap['remoteBaseUrl']) ? rtrim((string) $streamPfBootstrap['remoteBaseUrl'], "/\\ \t") : '';
$pfRoot     = ($pfIsRemote && $pfRb !== '') ? ($pfRb . '/') : '';

// Remote 2026/ mirror (e.g. https://psistorm.com/stream_production/2026/POG.mp4) — same paths as local app, not under production_files/video/
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
    $reported = $file ?: 'none';
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
