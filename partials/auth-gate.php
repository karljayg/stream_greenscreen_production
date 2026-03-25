<?php
// Auth gate — call session_start() and block unauthenticated requests.
// Include this before any output from the root or a subdirectory.
//
// Requires: $pathPrefix  — '' when included from root, '../' from music/, etc.
// Defines:  $currentUser — safe HTML-encoded username, available after this file runs.

session_start();

if (empty($_SESSION['username'])) {
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Production Tool – Login</title>
    <link rel="icon" href="<?= $pathPrefix ?>production_files/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= $pathPrefix ?>styles/login.css">
</head>
<body>
    <div class="login-box">
        <h1>Stream Production Tool</h1>
        <label for="l-user">Username</label>
        <input type="text" id="l-user" autocomplete="username" autofocus>
        <label for="l-pass">Password</label>
        <input type="password" id="l-pass" autocomplete="current-password">
        <button id="l-btn">Sign In</button>
        <div id="login-error"></div>
    </div>
    <script>
        function doLogin() {
            var user = document.getElementById('l-user').value.trim();
            var pass = document.getElementById('l-pass').value;
            var err  = document.getElementById('login-error');
            var btn  = document.getElementById('l-btn');
            if (!user || !pass) { err.textContent = 'Enter username and password.'; return; }
            btn.disabled = true;
            btn.textContent = 'Signing in\u2026';
            var fd = new FormData();
            fd.append('action', 'login');
            fd.append('username', user);
            fd.append('password', pass);
            fetch('<?= $pathPrefix ?>auth.php', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        err.textContent = res.error || 'Login failed.';
                        btn.disabled = false;
                        btn.textContent = 'Sign In';
                    }
                })
                .catch(function(){ err.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; });
        }
        document.getElementById('l-btn').addEventListener('click', doLogin);
        document.getElementById('l-pass').addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });
        document.getElementById('l-user').addEventListener('keydown', function(e){ if (e.key === 'Enter') document.getElementById('l-pass').focus(); });
    </script>
</body>
</html>
<?php
    exit;
}

$currentUser = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
