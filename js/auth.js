// Auth / User bar — logout, username click, and change-password handlers.
// Requires: window.CURRENT_USER (set in PHP config block in index.php)

document.getElementById('user-bar-logout').addEventListener('click', function() {
    var fd = new FormData();
    fd.append('action', 'logout');
    fetch('auth.php', { method: 'POST', body: fd })
        .then(function(){ window.location.reload(); })
        .catch(function(){ window.location.reload(); });
});

// Username button: open Settings > Account section
document.getElementById('user-bar-name').addEventListener('click', function() {
    var settingsSection = document.getElementById('settings-section');
    var settingsBtn     = document.getElementById('btn-settings');
    var accountSection  = document.getElementById('account-section');
    var accountBtn      = document.getElementById('btn-account');
    if (settingsSection.style.display !== 'block') {
        settingsSection.style.display = 'block';
        if (settingsBtn) settingsBtn.textContent = 'Hide Settings';
    }
    if (accountSection.style.display !== 'block') {
        accountSection.style.display = 'block';
        if (accountBtn) accountBtn.textContent = 'Hide Change Password';
    }
    accountSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    document.getElementById('chpw-current').focus();
});

// Change password toggle (called from HTML onclick)
window.toggleAccount = function toggleAccount(btn) {
    var el = document.getElementById('account-section');
    el.style.display = (el.style.display === 'block') ? 'none' : 'block';
    if (btn) btn.classList.toggle('open', el.style.display === 'block');
    if (el.style.display === 'block') {
        document.getElementById('chpw-current').focus();
    }
};

document.getElementById('chpw-save').addEventListener('click', function() {
    var cur  = document.getElementById('chpw-current').value;
    var nw   = document.getElementById('chpw-new').value;
    var conf = document.getElementById('chpw-confirm').value;
    var err  = document.getElementById('chpw-error');
    var ok   = document.getElementById('chpw-ok');
    var btn  = this;
    ok.style.display = 'none';
    if (!cur) { err.textContent = 'Enter your current password.'; return; }
    if (!nw)  { err.textContent = 'Enter a new password.'; return; }
    if (nw !== conf) { err.textContent = 'New passwords do not match.'; return; }
    err.textContent = '';
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('current_password', cur);
    fd.append('new_password', nw);
    fetch('auth.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (res.ok) {
                document.getElementById('chpw-current').value = '';
                document.getElementById('chpw-new').value = '';
                document.getElementById('chpw-confirm').value = '';
                ok.style.display = 'block';
                err.textContent = '';
            } else {
                err.textContent = res.error || 'Error saving password.';
            }
            btn.disabled = false;
        })
        .catch(function(){ err.textContent = 'Network error.'; btn.disabled = false; });
});

// Session keep-alive: ping the read-only `check` action so the server session's
// last-access time stays fresh while the tab is open, preventing idle GC from
// expiring it (which caused silent 401s on save). Only runs when logged in,
// since this file is only loaded by the authenticated page.
setInterval(function() {
    var fd = new FormData();
    fd.append('action', 'check');
    fetch('auth.php', { method: 'POST', body: fd }).catch(function(){});
}, 5 * 60 * 1000);
