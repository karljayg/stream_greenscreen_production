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
    <title>Player Scoreboard Editor</title>
    <link rel="icon" href="production_files/images/favicon.ico?v=<?php echo $v; ?>" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: #121212; color: #e0e0e0; }
        .wrap { width: 420px; margin: 0 auto; padding: 14px; }
        h1 { margin: 0 0 4px; font-size: 19px; color: #eee; }
        .hint { margin: 0 0 14px; font-size: 12px; color: #888; }
        .toprow { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .toprow label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ccc; }
        .player { border: 1px solid #2a2a2a; border-radius: 6px; padding: 10px; margin-bottom: 10px; background: #111; }
        .player.a .tag { color: #8f8; }
        .player.b .tag { color: #88f; }
        .tag { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .grid { display: grid; grid-template-columns: auto 1fr; gap: 7px 10px; align-items: center; }
        .grid label { font-size: 12px; color: #aaa; }
        input[type="text"], input[type="number"], select { border-radius: 3px; padding: 5px 7px; font-size: 13px; border: 1px solid #444; }
        input[type="text"], input[type="number"] { background: #1a1a1a; color: #eee; }
        input[type="number"] { width: 70px; text-align: center; }
        select { background: #fff; color: #111; border: 1px solid #888; }
        .name-wrap { position: relative; }
        .name-wrap input { width: 100%; }
        .ac { position: absolute; top: 100%; left: 0; right: 0; background: #fff; color: #111; border: 1px solid #888; z-index: 50; list-style: none; padding: 0; margin: 2px 0 0; max-height: 220px; overflow-y: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.4); }
        .ac li { padding: 4px 8px; cursor: pointer; font-size: 13px; }
        .ac li:hover { background: #e6e6e6; }
        details { margin-bottom: 10px; }
        summary { cursor: pointer; font-size: 12px; color: #888; user-select: none; }
        .team-row { display: flex; gap: 6px; align-items: center; margin-top: 5px; }
        .team-row input.tname { flex: 1; min-width: 0; }
        .team-row input.tacr { width: 70px; }
        .btn-del { width: 26px; height: 26px; line-height: 1; padding: 0; font-size: 15px; background: #2a1a1a; color: #f88; border: 1px solid #844; border-radius: 3px; cursor: pointer; flex-shrink: 0; }
        button { cursor: pointer; padding: 6px 12px; border-radius: 4px; border: 1px solid #444; background: #2a2a2a; color: #ddd; font-size: 13px; }
        button.primary { background: #1a3a1a; color: #8f8; border-color: #484; }
        .toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 12px; }
        #save-status { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Player Scoreboard</h1>
        <p class="hint">Edits apply to the overlay live. Move/resize the panel from the main page (Settings &rarr; Player Scoreboard &rarr; Edit and Move).</p>

        <div class="toprow">
            <label><input type="checkbox" id="psb-show"> Show on overlay (SC2 scene)</label>
            <label><input type="checkbox" id="psb-show-race"> Show race</label>
            <label>Best of <input type="number" id="psb-best-of" min="1" max="99" value="1"></label>
            <button type="button" id="psb-swap-btn" title="Swap top and bottom players">&#8645; Swap A/B</button>
        </div>

        <div class="player a" data-idx="0">
            <div class="tag">Player A (top)</div>
            <div class="grid">
                <label>Name:</label>
                <div class="name-wrap"><input type="text" class="psb-name" placeholder="Player name" autocomplete="off"></div>
                <label>Score:</label>
                <input type="number" class="psb-score" min="0" max="99" value="0">
                <label>Color:</label>
                <select class="psb-color"></select>
                <label>Team:</label>
                <select class="psb-team"></select>
                <label>Race:</label>
                <select class="psb-race">
                    <option value="">Auto (from rankings)</option>
                    <option value="Z">Zerg</option>
                    <option value="T">Terran</option>
                    <option value="P">Protoss</option>
                    <option value="R">Random</option>
                </select>
            </div>
        </div>

        <div class="player b" data-idx="1">
            <div class="tag">Player B (bottom)</div>
            <div class="grid">
                <label>Name:</label>
                <div class="name-wrap"><input type="text" class="psb-name" placeholder="Player name" autocomplete="off"></div>
                <label>Score:</label>
                <input type="number" class="psb-score" min="0" max="99" value="0">
                <label>Color:</label>
                <select class="psb-color"></select>
                <label>Team:</label>
                <select class="psb-team"></select>
                <label>Race:</label>
                <select class="psb-race">
                    <option value="">Auto (from rankings)</option>
                    <option value="Z">Zerg</option>
                    <option value="T">Terran</option>
                    <option value="P">Protoss</option>
                    <option value="R">Random</option>
                </select>
            </div>
        </div>

        <details>
            <summary>Team list (name &rarr; acronym)</summary>
            <div id="psb-teams-rows"></div>
            <button type="button" id="psb-team-add-btn" style="margin-top:6px; font-size:12px;">+ Add team</button>
        </details>

        <div class="toolbar">
            <button type="button" id="psb-save-btn" class="primary">Save</button>
            <span id="save-status"></span>
        </div>
    </div>

    <script type="module">
    import playerList from './js/playerlist.js?v=<?php echo $v; ?>';

    var PSB_COLORS = [
        { id: 'default', label: 'Default', hex: '#3f8f3f' }, { id: 'white', label: 'White', hex: '#f0f0f0' },
        { id: 'red', label: 'Red', hex: '#c0282d' }, { id: 'blue', label: 'Blue', hex: '#2a4fd6' },
        { id: 'teal', label: 'Teal', hex: '#1f8a8a' }, { id: 'purple', label: 'Purple', hex: '#7d3fb5' },
        { id: 'yellow', label: 'Yellow', hex: '#e8d33a' }, { id: 'orange', label: 'Orange', hex: '#e8852a' },
        { id: 'green', label: 'Green', hex: '#3fae3f' }, { id: 'lightpink', label: 'Light Pink', hex: '#e0a8e0' },
        { id: 'violet', label: 'Violet', hex: '#8a7fb5' }, { id: 'lightgrey', label: 'Light Grey', hex: '#aab2ba' },
        { id: 'darkgreen', label: 'Dark Green', hex: '#2e6b2e' }, { id: 'brown', label: 'Brown', hex: '#7a5230' },
        { id: 'lightgreen', label: 'Light Green', hex: '#7be07b' }, { id: 'darkgrey', label: 'Dark Grey', hex: '#4a4a4a' },
        { id: 'pink', label: 'Pink', hex: '#e84fb5' }
    ];
    var DEFAULT_TEAMS = [
        { name: 'PulledTheBoys', acr: 'PTB' }, { name: 'Angry Space Hares', acr: 'ASH' },
        { name: 'Special Tactics', acr: 'ST' }, { name: 'PSIOP Gaming', acr: 'POG' }
    ];

    var _data = null;
    var rankings = [];
    var saveTimer = null;
    var acDropdown = null;

    function defaultData() {
        return {
            show: false, showRace: false, bestOf: 1, pos: { left: 24, top: 24, width: 320, height: 92 },
            players: [
                { name: '', score: 0, color: 'purple', team: '', race: '' },
                { name: '', score: 0, color: 'blue', team: '', race: '' }
            ],
            teams: DEFAULT_TEAMS.map(function(t) { return { name: t.name, acr: t.acr }; })
        };
    }
    function normalize(d) {
        var out = defaultData();
        if (d && typeof d === 'object') {
            if (typeof d.show === 'boolean') out.show = d.show;
            if (typeof d.showRace === 'boolean') out.showRace = d.showRace;
            if (d.bestOf != null) out.bestOf = Math.max(1, parseInt(d.bestOf, 10) || 1);
            if (d.pos && typeof d.pos === 'object') out.pos = Object.assign(out.pos, d.pos);
            if (Array.isArray(d.players)) for (var i = 0; i < 2; i++) if (d.players[i]) out.players[i] = Object.assign(out.players[i], d.players[i]);
            if (Array.isArray(d.teams) && d.teams.length) out.teams = d.teams.map(function(t) { return { name: (t && t.name) || '', acr: (t && t.acr) || '' }; });
        }
        return out;
    }

    function rankFor(name) {
        if (!name || !rankings.length) return null;
        var n = String(name).trim().toLowerCase();
        return rankings.find(function(p) { return p.name && String(p.name).toLowerCase() === n; }) || null;
    }
    function normTeam(s) { return String(s == null ? '' : s).toLowerCase().replace(/[^a-z0-9]/g, ''); }
    function teamAcr(teamName) {
        if (!teamName) return '';
        var norm = normTeam(teamName);
        var teams = (_data && _data.teams) || [];
        for (var i = 0; i < teams.length; i++) if (normTeam(teams[i].name) === norm) return teams[i].acr || '';
        return '';
    }
    function isRealAcr(a) { if (!a) return false; var u = String(a).trim().toUpperCase(); return u !== '' && u !== 'NULL'; }
    function resolveTeamAcr(player) {
        var acr = '';
        if (player.team) acr = teamAcr(player.team);
        else { var rk = rankFor(player.name); if (rk && rk.team) acr = String(rk.team).trim(); }
        return isRealAcr(acr) ? acr : '';
    }
    function resolveRace(player) {
        if (player.race) return player.race;
        var rk = rankFor(player.name);
        if (rk && rk.race) return String(rk.race).toUpperCase().charAt(0);
        return '';
    }

    function populateColorSelect(sel, val) {
        sel.innerHTML = '';
        PSB_COLORS.forEach(function(c) {
            var o = document.createElement('option'); o.value = c.id; o.textContent = c.label; sel.appendChild(o);
        });
        sel.value = val || 'default';
    }
    function populateTeamSelect(sel, val) {
        sel.innerHTML = '';
        var none = document.createElement('option'); none.value = ''; none.textContent = 'Auto'; sel.appendChild(none);
        ((_data && _data.teams) || []).forEach(function(t) {
            if (!t.name) return;
            var o = document.createElement('option'); o.value = t.name; o.textContent = t.name + (t.acr ? ' (' + t.acr + ')' : ''); sel.appendChild(o);
        });
        sel.value = val || '';
    }
    function updateAutoLabels() {
        document.querySelectorAll('.player').forEach(function(box) {
            var i = parseInt(box.getAttribute('data-idx'), 10) || 0;
            var p = (_data && _data.players[i]) || { name: '', team: '', race: '' };
            var nm = box.querySelector('.psb-name').value;
            var teamSel = box.querySelector('.psb-team');
            var raceSel = box.querySelector('.psb-race');
            var acr = nm ? resolveTeamAcr({ name: nm, team: '' }) : '';
            if (teamSel.options.length) teamSel.options[0].textContent = acr ? ('Auto (' + acr + ')') : 'Auto';
            var rc = nm ? resolveRace({ name: nm, race: '' }) : '';
            if (raceSel.options.length) raceSel.options[0].textContent = rc ? ('Auto (' + rc + ')') : 'Auto (from rankings)';
        });
    }

    function renderTeamRows() {
        var wrap = document.getElementById('psb-teams-rows');
        wrap.innerHTML = '';
        ((_data && _data.teams) || []).forEach(function(t, i) {
            var row = document.createElement('div');
            row.className = 'team-row';
            row.innerHTML =
                '<input type="text" class="tname" data-i="' + i + '" placeholder="Team name">' +
                '<input type="text" class="tacr" data-i="' + i + '" placeholder="ACR">' +
                '<button type="button" class="btn-del" data-i="' + i + '">&times;</button>';
            row.querySelector('.tname').value = t.name || '';
            row.querySelector('.tacr').value = t.acr || '';
            wrap.appendChild(row);
        });
        wrap.querySelectorAll('.tname, .tacr').forEach(function(inp) {
            inp.addEventListener('input', function() {
                var i = parseInt(inp.getAttribute('data-i'), 10);
                if (!_data.teams[i]) return;
                if (inp.classList.contains('tname')) _data.teams[i].name = inp.value;
                else _data.teams[i].acr = inp.value;
                document.querySelectorAll('.player').forEach(function(box) {
                    var idx = parseInt(box.getAttribute('data-idx'), 10) || 0;
                    populateTeamSelect(box.querySelector('.psb-team'), _data.players[idx].team);
                });
                updateAutoLabels();
                scheduleSave();
            });
        });
        wrap.querySelectorAll('.btn-del').forEach(function(btn) {
            btn.addEventListener('click', function() {
                _data.teams.splice(parseInt(btn.getAttribute('data-i'), 10), 1);
                renderTeamRows();
                document.querySelectorAll('.player').forEach(function(box) {
                    var idx = parseInt(box.getAttribute('data-idx'), 10) || 0;
                    populateTeamSelect(box.querySelector('.psb-team'), _data.players[idx].team);
                });
                updateAutoLabels();
                scheduleSave();
                if (typeof fitWindow === 'function') setTimeout(fitWindow, 0);
            });
        });
    }

    function renderAll() {
        document.getElementById('psb-show').checked = !!_data.show;
        document.getElementById('psb-show-race').checked = !!_data.showRace;
        document.getElementById('psb-best-of').value = parseInt(_data.bestOf, 10) || 1;
        document.querySelectorAll('.player').forEach(function(box) {
            var i = parseInt(box.getAttribute('data-idx'), 10) || 0;
            var p = _data.players[i] || { name: '', score: 0, color: 'default', team: '', race: '' };
            box.querySelector('.psb-name').value = p.name || '';
            box.querySelector('.psb-score').value = parseInt(p.score, 10) || 0;
            populateColorSelect(box.querySelector('.psb-color'), p.color);
            populateTeamSelect(box.querySelector('.psb-team'), p.team);
            box.querySelector('.psb-race').value = p.race || '';
        });
        renderTeamRows();
        updateAutoLabels();
    }

    function collect() {
        if (!_data) return;
        _data.show = document.getElementById('psb-show').checked;
        _data.showRace = document.getElementById('psb-show-race').checked;
        _data.bestOf = Math.max(1, parseInt(document.getElementById('psb-best-of').value, 10) || 1);
        document.querySelectorAll('.player').forEach(function(box) {
            var i = parseInt(box.getAttribute('data-idx'), 10) || 0;
            if (!_data.players[i]) _data.players[i] = { name: '', score: 0, color: 'default', team: '', race: '' };
            var p = _data.players[i];
            p.name = box.querySelector('.psb-name').value;
            p.score = parseInt(box.querySelector('.psb-score').value, 10) || 0;
            p.color = box.querySelector('.psb-color').value;
            p.team = box.querySelector('.psb-team').value;
            p.race = box.querySelector('.psb-race').value;
        });
    }

    function notifyOpener() {
        if (window.opener && !window.opener.closed) {
            try { window.opener.postMessage({ type: 'psb-editor-saved' }, window.location.origin); } catch (e) {}
        }
    }
    function save(showStatus) {
        collect();
        var status = document.getElementById('save-status');
        var btn = document.getElementById('psb-save-btn');
        if (showStatus) { if (btn) btn.disabled = true; if (status) status.textContent = 'Saving…'; }
        return fetch('save_player_scoreboard.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(_data)
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (showStatus && status) status.textContent = (res && res.ok) ? 'Saved!' : 'Error saving.';
            notifyOpener();
            if (showStatus) setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2000);
        }).catch(function() {
            if (showStatus && status) { status.textContent = 'Network error.'; setTimeout(function() { status.textContent = ''; if (btn) btn.disabled = false; }, 2000); }
        });
    }
    function scheduleSave() {
        collect();
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function() { save(false); }, 350);
    }

    /* ---- Autocomplete ---- */
    function clearAc() { if (acDropdown && acDropdown.parentNode) acDropdown.parentNode.removeChild(acDropdown); acDropdown = null; }
    function showAc(input, matches) {
        clearAc();
        var ul = document.createElement('ul'); ul.className = 'ac';
        matches.slice(0, 50).forEach(function(p) {
            var li = document.createElement('li'); li.textContent = p[0];
            li.addEventListener('mousedown', function(e) { e.preventDefault(); });
            li.addEventListener('click', function() {
                input.value = p[0]; clearAc(); updateAutoLabels(); scheduleSave();
            });
            ul.appendChild(li);
        });
        input.parentNode.appendChild(ul); acDropdown = ul;
    }
    function attachAc(input) {
        input.addEventListener('input', function() {
            updateAutoLabels(); scheduleSave();
            var v = input.value.trim().toLowerCase();
            var list = playerList || [];
            if (v.length < 3 || !list.length) { clearAc(); return; }
            var matches = list.filter(function(p) { return p[0] && p[0].toLowerCase().indexOf(v) !== -1; });
            if (matches.length) showAc(input, matches); else clearAc();
        });
        input.addEventListener('blur', function() { setTimeout(clearAc, 150); });
    }

    /* ---- Wire up ---- */
    document.getElementById('psb-show').addEventListener('change', scheduleSave);
    document.getElementById('psb-show-race').addEventListener('change', scheduleSave);
    document.getElementById('psb-best-of').addEventListener('input', scheduleSave);
    document.querySelectorAll('.player').forEach(function(box) {
        attachAc(box.querySelector('.psb-name'));
        box.querySelector('.psb-score').addEventListener('input', scheduleSave);
        box.querySelector('.psb-color').addEventListener('change', scheduleSave);
        box.querySelector('.psb-team').addEventListener('change', scheduleSave);
        box.querySelector('.psb-race').addEventListener('change', function() { updateAutoLabels(); scheduleSave(); });
    });
    document.getElementById('psb-swap-btn').addEventListener('click', function() {
        collect();
        var t = _data.players[0]; _data.players[0] = _data.players[1]; _data.players[1] = t;
        renderAll(); scheduleSave();
    });
    document.getElementById('psb-team-add-btn').addEventListener('click', function() {
        _data.teams.push({ name: '', acr: '' });
        renderTeamRows();
        document.querySelectorAll('.player').forEach(function(box) {
            var idx = parseInt(box.getAttribute('data-idx'), 10) || 0;
            populateTeamSelect(box.querySelector('.psb-team'), _data.players[idx].team);
        });
        if (typeof fitWindow === 'function') setTimeout(fitWindow, 0);
    });
    document.getElementById('psb-save-btn').addEventListener('click', function() { save(true); });

    /* ---- Load ---- */
    function loadRankings() {
        return fetch('rankings.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(d) { rankings = Array.isArray(d) ? d : []; })
            .catch(function() { rankings = []; });
    }
    function loadData() {
        return fetch('save_player_scoreboard.php?_t=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(d) { _data = normalize(d); })
            .catch(function() { _data = defaultData(); });
    }
    /* Resize the popup to fit its content (no extra empty space). */
    function fitWindow() {
        try {
            var wrap = document.querySelector('.wrap');
            var w = Math.ceil(wrap.getBoundingClientRect().width);
            var h = Math.ceil(document.body.scrollHeight);
            var chromeW = Math.max(0, window.outerWidth - window.innerWidth);
            var chromeH = Math.max(0, window.outerHeight - window.innerHeight);
            window.resizeTo(w + chromeW + 2, h + chromeH + 2);
        } catch (e) {}
    }
    /* Re-fit when the team list expands/collapses (changes height). */
    var teamDetails = document.querySelector('details');
    if (teamDetails) teamDetails.addEventListener('toggle', function() { setTimeout(fitWindow, 0); });

    Promise.all([loadData(), loadRankings()]).then(function() {
        renderAll();
        setTimeout(fitWindow, 0);
    });
    </script>
</body>
</html>
