// StreamElements live alerts via Socket.IO.
// Requires: window.SE_TOKEN (set in PHP config block), socket.io loaded from CDN before this file.

(function() {
    var SE_TOKEN = window.SE_TOKEN || '';

    var TYPE_CFG = {
        'follow':     { label: 'New Follower', icon: '💜', color: '#9147ff', sound: 'production_files/audio/FSL_british_follow.mp3' },
        'subscriber': { label: 'New Sub',      icon: '⭐', color: '#f0a500', sound: 'production_files/audio/FSL_british_subscribe.mp3' },
        'cheer':      { label: 'Cheer',        icon: '💎', color: '#1db954', sound: 'production_files/audio/FSL_british_cheer.mp3' },
        'tip':        { label: 'Donation',     icon: '💰', color: '#e91e8c', sound: 'production_files/audio/FSL_british_donation.mp3' },
        'raid':       { label: 'Raid!',        icon: '⚔️', color: '#ff4500', sound: 'production_files/audio/FSL_british_raid.mp3' },
        'host':       { label: 'Host',         icon: '📡', color: '#1da1f2', sound: 'production_files/audio/FSL_british_raid.mp3' },
    };

    function playAlertSound(src) {
        try {
            var audio = new Audio(src);
            audio.play();
        } catch(e) {}
    }

    function showAlert(type, username, extra) {
        var cfg = TYPE_CFG[type] || { label: type, icon: '🔔', color: '#9147ff' };
        var overlay = document.getElementById('se-alert-overlay');
        overlay.innerHTML = '';
        var card = document.createElement('div');
        card.className = 'se-card';
        card.style.setProperty('--ac', cfg.color);
        card.innerHTML =
            '<span class="se-icon">' + cfg.icon + '</span>' +
            '<div class="se-type">' + cfg.label + '</div>' +
            '<div class="se-user">' + (username || 'Someone') + '</div>' +
            (extra ? '<div class="se-extra">' + extra + '</div>' : '') +
            '<div class="se-bar"></div>';
        overlay.appendChild(card);
        playAlertSound(cfg.sound);
        setTimeout(function() {
            card.classList.add('se-out');
            setTimeout(function() { overlay.innerHTML = ''; }, 600);
        }, 8000);
    }

    var socket = io('https://realtime.streamelements.com', { transports: ['websocket'] });
    socket.on('connect', function() {
        console.log('[SE] Connected, authenticating...');
        socket.emit('authenticate', { method: 'jwt', token: SE_TOKEN });
    });
    socket.on('authenticated', function() { console.log('[SE] Authenticated'); });
    socket.on('unauthorized',  function(e) { console.warn('[SE] Unauthorized:', e); });
    socket.on('event', function(data) {
        var type = data.type || '';
        var username = (data.data && (data.data.username || data.data.displayName || data.data.name)) || '';
        var extra = '';
        if (data.data) {
            if (data.data.amount)  extra = data.data.amount + (data.data.currency ? ' ' + data.data.currency : '');
            if (data.data.viewers) extra = data.data.viewers + ' viewers';
            if (data.data.message) extra = data.data.message;
        }
        showAlert(type, username, extra);
    });
    socket.on('disconnect', function() { console.log('[SE] Disconnected'); });
})();
