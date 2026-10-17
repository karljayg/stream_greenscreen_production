<?php
$pathPrefix = '';
require_once __DIR__ . '/partials/auth-gate.php';
require_once __DIR__ . '/asset_version.php';
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share a window</title>
    <link rel="icon" href="production_files/images/favicon.ico?v=<?php echo $v; ?>" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, Segoe UI, sans-serif;
            background: #fff;
            color: #1e293b;
            padding: 1.5rem;
            min-width: 360px;
        }
        h1 { margin: 0 0 0.75rem; font-size: 1rem; font-weight: 600; }
        p { margin: 0 0 1rem; font-size: 0.9rem; color: #555; line-height: 1.45; }
        .choices { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .choices button {
            font: inherit;
            cursor: pointer;
            border-radius: 4px;
            padding: 10px 12px;
            text-align: left;
            background: #2d3748;
            color: #f8fafc;
            border: none;
        }
        .choices button:hover { background: #1e293b; }
        .choices button:disabled { opacity: 0.55; cursor: wait; }
        .row { display: flex; gap: 0.5rem; justify-content: flex-end; }
        #cancel, #stop {
            background: #fff;
            border: 1px solid #94a3b8;
            color: #1e293b;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font: inherit;
        }
        #err { color: #b91c1c; font-size: 12px; min-height: 1.2em; margin-top: 8px; }
        #status-panel { display: none; }
    </style>
</head>
<body>
    <div id="pick-panel">
        <h1>Share a window</h1>
        <p>The browser share prompt opens in <strong>this</strong> window (not on the stream). Pick a display mode, then choose the tab or window to share.</p>
        <div class="choices">
            <button type="button" id="mode-full">Full screen – shared content fills the frame</button>
            <button type="button" id="mode-partial">With overlays – shared panel + BG + logos + mini VDO</button>
        </div>
        <div class="row">
            <button type="button" id="cancel">Cancel</button>
        </div>
        <div id="err"></div>
    </div>
    <div id="status-panel">
        <h1>Sharing</h1>
        <p>Stream is live on the production panel. Close this window to stop sharing and reset the scene buttons.</p>
        <div class="row">
            <button type="button" id="stop">Stop sharing</button>
        </div>
    </div>
    <script>
        var sharedStream = null;
        var teardownSent = false;

        function showError(msg) {
            document.getElementById('err').textContent = msg || '';
        }

        function setPickBusy(busy) {
            document.getElementById('mode-full').disabled = busy;
            document.getElementById('mode-partial').disabled = busy;
            document.getElementById('cancel').disabled = busy;
        }

        function notifyMainTeardown() {
            if (teardownSent) return;
            teardownSent = true;
            if (window.opener && !window.opener.closed && typeof window.opener.endSharedWindowCapture === 'function') {
                try { window.opener.endSharedWindowCapture({ fromPickerClose: true }); } catch (e) {}
            }
        }

        function stopSharing() {
            if (sharedStream) {
                sharedStream.getTracks().forEach(function(t) { t.stop(); });
                sharedStream = null;
            }
            notifyMainTeardown();
            window.close();
        }

        function showSharingStatus() {
            document.getElementById('pick-panel').style.display = 'none';
            document.getElementById('status-panel').style.display = 'block';
            document.title = 'Sharing';
        }

        function pick(showPartial) {
            showError('');
            if (!window.opener || window.opener.closed) {
                showError('Production window is not open.');
                return;
            }
            if (typeof window.opener.applySharedWindowStream !== 'function') {
                showError('Refresh the production page, then try again.');
                return;
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                showError('This browser cannot share a window.');
                return;
            }
            setPickBusy(true);
            navigator.mediaDevices.getDisplayMedia({ video: true, audio: false }).then(function(stream) {
                sharedStream = stream;
                try {
                    window.opener.applySharedWindowStream(stream, showPartial);
                } catch (err) {
                    stream.getTracks().forEach(function(t) { t.stop(); });
                    sharedStream = null;
                    setPickBusy(false);
                    showError(err && err.message ? err.message : 'Could not start sharing.');
                    return;
                }
                showSharingStatus();
                var track = stream.getVideoTracks()[0];
                if (track) {
                    track.addEventListener('ended', function() {
                        sharedStream = null;
                        notifyMainTeardown();
                        window.close();
                    });
                }
            }).catch(function(err) {
                setPickBusy(false);
                if (!err || err.name === 'NotAllowedError') return;
                showError(err.message || 'Share failed');
            });
        }

        document.getElementById('mode-full').onclick = function() { pick(false); };
        document.getElementById('mode-partial').onclick = function() { pick(true); };
        document.getElementById('cancel').onclick = function() { window.close(); };
        document.getElementById('stop').onclick = stopSharing;
        window.addEventListener('pagehide', function() {
            if (sharedStream) {
                sharedStream.getTracks().forEach(function(t) { t.stop(); });
                sharedStream = null;
            }
            notifyMainTeardown();
        });
    </script>
</body>
</html>
