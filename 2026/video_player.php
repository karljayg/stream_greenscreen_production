<?php
/**
 * Reusable fullscreen video player.
 *   ?v=filename.mp4  – video file in this directory (2026/). If not found, posts error to parent.
 *   ?front=true      – optional; parent page uses this to show the overlay on top of all layers (this script ignores it).
 */
$raw = isset($_GET['v']) ? $_GET['v'] : '';
$file = $raw ? basename($raw) : '';
$path = $file ? __DIR__ . DIRECTORY_SEPARATOR . $file : '';

if ($file && is_file($path)) {
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
<video id="bg-video" src="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>" autoplay loop muted playsinline></video>
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