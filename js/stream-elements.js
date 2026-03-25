// StreamElements live alerts via Socket.IO.
// Requires: window.SE_TOKEN (set in PHP config block), socket.io loaded from CDN before this file.

(function() {
    var SE_TOKEN = window.SE_TOKEN || '';

    var TYPE_CFG = {
        'follow':     { label: 'New Follower', icon: '💜', color: '#9147ff' },
        'subscriber': { label: 'New Sub',      icon: '⭐', color: '#f0a500' },
        'cheer':      { label: 'Cheer',        icon: '💎', color: '#1db954' },
        'tip':        { label: 'Donation',     icon: '💰', color: '#e91e8c' },
        'raid':       { label: 'Raid!',        icon: '⚔️', color: '#ff4500' },
        'host':       { label: 'Host',         icon: '📡', color: '#1da1f2' },
    };

    function playChime() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            [523, 659, 784, 1047].forEach(function(freq, i) {
                var osc = ctx.createOscillator(), gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.type = 'sine'; osc.frequency.value = freq;
                var t = ctx.currentTime + i * 0.13;
                gain.gain.setValueAtTime(0, t);
                gain.gain.linearRampToValueAtTime(0.25, t + 0.04);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
                osc.start(t); osc.stop(t + 0.5);
            });
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
        playChime();
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
