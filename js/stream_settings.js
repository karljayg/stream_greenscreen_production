/**
 * Show broadcast defaults (server) + per-user layout prefs + session-only overrides.
 * Broadcast keys: fresh tab loads server defaults; local edits stay in sessionStorage until tab closes.
 */
(function (global) {
    'use strict';

    var SESSION_PREFIX = 'stream_session_bc_';
    var _userSaveTimer = null;

    var BROADCAST_SECTIONS = [
        'eventTitle', 'ytIframeVideos', 'breakSettings', 'ytCrop',
        'vdoDirectorUrl', 'sc2ButtonEffects', 'playerIntros'
    ];

    function sessionKey(section) {
        return SESSION_PREFIX + section;
    }

    function getSessionOverride(section) {
        try {
            var raw = sessionStorage.getItem(sessionKey(section));
            if (!raw) return undefined;
            return JSON.parse(raw);
        } catch (e) {
            return undefined;
        }
    }

    function setSessionOverride(section, data) {
        try {
            if (data === undefined || data === null) {
                sessionStorage.removeItem(sessionKey(section));
                return;
            }
            sessionStorage.setItem(sessionKey(section), JSON.stringify(data));
        } catch (e) {}
    }

    function clearAllSessionBroadcastOverrides() {
        BROADCAST_SECTIONS.forEach(function (s) {
            try { sessionStorage.removeItem(sessionKey(s)); } catch (e) {}
        });
    }

    function publishBroadcastSection(section, data) {
        return fetch('stream_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ section: section, data: data })
        }).then(function (r) { return r.json(); });
    }

    function publishAllBroadcastFromUi() {
        if (typeof global.exportBroadcastSnapshot !== 'function') {
            return Promise.resolve({ ok: false, error: 'exportBroadcastSnapshot missing' });
        }
        var snap = global.exportBroadcastSnapshot();
        return fetch('stream_broadcast.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ section: '_all', broadcast: snap })
        }).then(function (r) { return r.json(); });
    }

    function applyBroadcastSection(section, data) {
        if (data === undefined || data === null) return;
        switch (section) {
            case 'eventTitle':
                if (typeof global.setEventTitle === 'function') global.setEventTitle(data);
                break;
            case 'ytIframeVideos':
                if (typeof global.setYtIframeVideos === 'function') {
                    global.setYtIframeVideos(data);
                    if (typeof global.saveYtIframeVideos === 'function') global.saveYtIframeVideos();
                }
                break;
            case 'breakSettings':
                if (typeof global.setBreakSettings === 'function') {
                    global.setBreakSettings(data);
                    if (typeof global.saveBreakSettings === 'function') global.saveBreakSettings();
                }
                break;
            case 'ytCrop':
                if (typeof global.setYtCrop === 'function') {
                    global.setYtCrop(data);
                    if (typeof global.saveYtCrop === 'function') global.saveYtCrop();
                    if (typeof global.applyYtCropToVideo === 'function') global.applyYtCropToVideo();
                }
                break;
            case 'vdoDirectorUrl':
                if (typeof global.setVdoDirectorUrl === 'function') {
                    global.setVdoDirectorUrl(data);
                    if (typeof global.saveVdoDirectorUrl === 'function') global.saveVdoDirectorUrl();
                }
                break;
            case 'sc2ButtonEffects':
                if (typeof global.setSc2ButtonEffects === 'function') global.setSc2ButtonEffects(data);
                break;
            case 'playerIntros':
                if (Array.isArray(data)) {
                    data.forEach(function (val, i) {
                        var f = document.getElementById('media-form-' + (i + 1));
                        var inp = f ? f.querySelector('.player-name-input') : null;
                        if (inp) inp.value = val != null ? String(val) : '';
                    });
                }
                break;
            default:
                break;
        }
    }

    function applyBroadcastWithSessionOverrides(broadcast) {
        if (!broadcast || typeof broadcast !== 'object') return;
        BROADCAST_SECTIONS.forEach(function (section) {
            var base = broadcast[section];
            var over = getSessionOverride(section);
            var val = over !== undefined ? over : base;
            applyBroadcastSection(section, val);
        });
    }

    function loadStreamBroadcast() {
        return fetch('stream_broadcast.php', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.broadcast) {
                    applyBroadcastWithSessionOverrides(data.broadcast);
                }
            })
            .catch(function () {});
    }

    function importUserPreferences(parsed) {
        if (!parsed || typeof parsed !== 'object') return;
        if (typeof global.importAllSettings === 'function') {
            var stub = Object.assign({}, parsed);
            BROADCAST_SECTIONS.forEach(function (k) { delete stub[k]; });
            delete stub.status;
            delete stub.playerRatings;
            delete stub.scenes;
            delete stub.eventTitle;
            delete stub.ytIframeVideos;
            delete stub.breakSettings;
            delete stub.ytCrop;
            delete stub.vdoDirectorUrl;
            delete stub.sc2ButtonEffects;
            delete stub.playerIntros;
            global.importAllSettings(stub);
        }
    }

    function exportUserPreferences() {
        if (typeof global.exportAllSettings !== 'function') return { version: 1 };
        var all = global.exportAllSettings();
        var out = { version: 1 };
        var keys = [
            'volume', 'layerOrder', 'logos', 'tlsScorePos', 'musicMode', 'musicVol', 'musicFade',
            'productionFiles', 'chromaKey', 'sceneMoodMap', 'moodSongs', 'sceneStages'
        ];
        keys.forEach(function (k) {
            if (all[k] !== undefined) out[k] = all[k];
        });
        if (typeof global.tlsScoreEnabled !== 'undefined') {
            out.tlsScoreEnabled = !!global.tlsScoreEnabled;
        }
        return out;
    }

    function saveUserPreferencesQuiet() {
        clearTimeout(_userSaveTimer);
        _userSaveTimer = setTimeout(function () {
            var prefs = exportUserPreferences();
            fetch('stream_user_preferences.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ preferences: prefs })
            }).catch(function () {});
        }, 400);
    }

    function loadUserPreferences() {
        return fetch('stream_user_preferences.php', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok && data.preferences) {
                    importUserPreferences(data.preferences);
                }
            })
            .catch(function () {});
    }

    function bootstrap(afterUserLayoutFn) {
        return loadUserPreferences()
            .then(function () {
                if (typeof afterUserLayoutFn === 'function') afterUserLayoutFn();
                return loadStreamBroadcast();
            });
    }

    function wireSessionBroadcastTracking() {
        var titleEl = document.getElementById('event-title-input');
        if (titleEl) {
            titleEl.addEventListener('input', function () {
                if (typeof global.saveEventTitle === 'function') global.saveEventTitle();
                setSessionOverride('eventTitle', global.getEventTitle ? global.getEventTitle() : titleEl.value);
            });
        }
        ['yt-video-1-label', 'yt-video-1-url', 'yt-video-2-label', 'yt-video-2-url',
            'yt-video-3-label', 'yt-video-3-url', 'yt-video-1-vol', 'yt-video-2-vol', 'yt-video-3-vol'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function () {
                if (typeof global.getYtIframeVideos === 'function') {
                    setSessionOverride('ytIframeVideos', global.getYtIframeVideos());
                }
            });
        });
        ['break-timer-min', 'break-timer-sec', 'break-timer-msg'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function () {
                if (typeof global.getBreakSettings === 'function') {
                    setSessionOverride('breakSettings', global.getBreakSettings());
                }
            });
        });
    }

    function wirePublishUi() {
        var resetBtn = document.getElementById('stream-reset-session-broadcast-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                clearAllSessionBroadcastOverrides();
                loadStreamBroadcast().then(function () {
                    var st = document.getElementById('stream-reset-session-broadcast-status');
                    if (st) {
                        st.textContent = 'Reloaded show defaults for this tab.';
                        setTimeout(function () { st.textContent = ''; }, 3500);
                    }
                });
            });
        }
        var pubAll = document.getElementById('stream-publish-broadcast-all-btn');
        if (pubAll) {
            pubAll.addEventListener('click', function () {
                pubAll.disabled = true;
                var st = document.getElementById('stream-publish-broadcast-status');
                if (st) st.textContent = 'Publishing…';
                publishAllBroadcastFromUi().then(function (res) {
                    if (st) st.textContent = (res && res.ok) ? 'Show defaults published.' : ((res && res.error) || 'Publish failed.');
                }).catch(function () {
                    if (st) st.textContent = 'Publish failed.';
                }).then(function () {
                    pubAll.disabled = false;
                    if (st) setTimeout(function () { st.textContent = ''; }, 4000);
                });
            });
        }
        var pubYt = document.getElementById('yt-videos-save-global-btn');
        if (pubYt) {
            pubYt.addEventListener('click', function () {
                if (typeof global.getYtIframeVideos !== 'function') return;
                pubYt.disabled = true;
                var globalSt = document.getElementById('yt-videos-save-global-status');
                if (globalSt) globalSt.textContent = 'Publishing…';
                publishBroadcastSection('ytIframeVideos', global.getYtIframeVideos()).then(function (res) {
                    if (globalSt) globalSt.textContent = (res && res.ok) ? 'Published for all users (new sessions).' : ((res && res.error) || 'Failed.');
                    if (res && res.ok) setSessionOverride('ytIframeVideos', undefined);
                }).catch(function () {
                    if (globalSt) globalSt.textContent = 'Failed.';
                }).then(function () {
                    pubYt.disabled = false;
                    if (globalSt) setTimeout(function () { globalSt.textContent = ''; }, 3500);
                });
            });
        }
    }

    global.StreamSettings = {
        bootstrap: bootstrap,
        loadStreamBroadcast: loadStreamBroadcast,
        loadUserPreferences: loadUserPreferences,
        saveUserPreferencesQuiet: saveUserPreferencesQuiet,
        publishBroadcastSection: publishBroadcastSection,
        publishAllBroadcastFromUi: publishAllBroadcastFromUi,
        setSessionOverride: setSessionOverride,
        getSessionOverride: getSessionOverride,
        clearAllSessionBroadcastOverrides: clearAllSessionBroadcastOverrides,
        applyBroadcastWithSessionOverrides: applyBroadcastWithSessionOverrides,
        wireSessionBroadcastTracking: wireSessionBroadcastTracking,
        wirePublishUi: wirePublishUi,
        exportUserPreferences: exportUserPreferences,
        importUserPreferences: importUserPreferences
    };

    global.saveSettingsToServerQuiet = saveUserPreferencesQuiet;
})(typeof window !== 'undefined' ? window : this);
