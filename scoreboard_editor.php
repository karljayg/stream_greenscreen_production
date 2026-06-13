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
    <title>Scoreboard Editor</title>
    <link rel="icon" href="production_files/images/favicon.ico?v=<?php echo $v; ?>" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            background: #121212;
            color: #e0e0e0;
            min-height: 100vh;
        }
        .wrap { max-width: 960px; margin: 0 auto; padding: 16px; }
        h1 { margin: 0 0 4px; font-size: 20px; color: #eee; }
        .hint { margin: 0 0 14px; font-size: 12px; color: #888; }
        .panel { background: #111; border: 1px solid #2a2a2a; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
        .team-header { display: flex; align-items: center; gap: 10px; }
        .team-header input[type="text"] { flex: 1; min-width: 0; text-align: center; font-weight: bold; font-size: 14px; padding: 8px; border-radius: 4px; }
        .team-a-inp { background: #1a2a1a; color: #8f8; border: 1px solid #484; }
        .team-b-inp { background: #1a1a2a; color: #88f; border: 1px solid #448; }
        .totals { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .total-a, .total-b {
            display: inline-block; width: 52px; text-align: center; font-size: 20px; font-weight: bold;
            border-radius: 4px; padding: 4px;
        }
        .total-a { color: #8f8; border: 1px solid #484; background: #1a2a1a; }
        .total-b { color: #88f; border: 1px solid #448; background: #1a1a2a; }
        .map-labels { display: flex; gap: 8px; align-items: center; font-size: 12px; color: #888; margin-bottom: 10px; }
        .map-labels input { flex: 1; min-width: 0; background: #1a1a1a; color: #a8f; border: 1px solid #446; border-radius: 3px; padding: 5px 8px; font-size: 12px; }
        .match-row { padding: 10px 12px; background: #111; border: 1px solid #2a2a2a; border-radius: 4px; margin-bottom: 6px; }
        .match-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap; }
        .match-players { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .inp { border-radius: 3px; padding: 5px 7px; font-size: 12px; min-width: 0; border: 1px solid #333; }
        .inp-a { background: #1a2a1a; color: #8f8; border-color: #484; flex: 1; }
        .inp-b { background: #1a1a2a; color: #88f; border-color: #448; flex: 1; }
        .inp-map { background: #1a1a1a; color: #ccc; flex: 1; }
        .inp-type { width: 56px; background: #1a1a1a; color: #0b8; border-color: #383; flex-shrink: 0; }
        .inp-score { width: 48px; text-align: center; flex-shrink: 0; padding: 4px; }
        .inp-score-a { background: #1a2a1a; color: #8f8; border: 1px solid #484; }
        .inp-score-b { background: #1a1a2a; color: #88f; border: 1px solid #448; }
        .btn-del { width: 28px; height: 28px; line-height: 1; padding: 0; font-size: 16px; background: #2a1a1a; color: #f88; border: 1px solid #844; border-radius: 3px; cursor: pointer; flex-shrink: 0; }
        .toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 12px; }
        button { cursor: pointer; padding: 6px 12px; border-radius: 4px; border: 1px solid #444; background: #2a2a2a; color: #ddd; font-size: 13px; }
        button:disabled { opacity: 0.6; cursor: default; }
        button.primary { background: #1a3a1a; color: #8f8; border-color: #484; }
        #load-status, #save-status { font-size: 12px; color: #aaa; }
        .row-num { font-size: 11px; color: #666; min-width: 24px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Scoreboard Editor</h1>
        <p class="hint">Edit teams, players, maps, and match rows. Totals update automatically from row scores.</p>

        <div class="panel team-header">
            <input type="text" id="sb-team-a" class="team-a-inp" placeholder="Team A">
            <div class="totals">
                <span id="sb-total-a" class="total-a">0</span>
                <span style="color:#888;">–</span>
                <span id="sb-total-b" class="total-b">0</span>
            </div>
            <input type="text" id="sb-team-b" class="team-b-inp" placeholder="Team B">
        </div>

        <datalist id="sb-type-suggestions">
            <option value="1v1"></option>
            <option value="2v2"></option>
            <option value="3v3"></option>
            <option value="4v4"></option>
        </datalist>

        <div class="map-labels">
            <span>Map column headers:</span>
            <input type="text" id="sb-map1-label" placeholder="Map 1">
            <input type="text" id="sb-map2-label" placeholder="Map 2">
        </div>

        <div id="sb-match-rows"></div>
        <button type="button" id="sb-add-btn">+ Add Match</button>

        <div class="toolbar">
            <button type="button" id="sb-reload-btn">Reload from CSV</button>
            <button type="button" id="sb-save-btn" class="primary">Save Scoreboard</button>
            <span id="load-status"></span>
            <span id="save-status"></span>
        </div>
    </div>

    <script>
    (function() {
        var _rows = null;

        function esc(s) {
            if (s == null) return '';
            var d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }
        function attr(s) { return esc(s).replace(/"/g, '&quot;'); }

        function parseCSVLine(line) {
            line = line.replace(/^\uFEFF/, '');
            var out = [], i = 0;
            while (i < line.length) {
                if (line[i] === '"') {
                    var cell = '';
                    i++;
                    while (i < line.length) {
                        if (line[i] === '"') {
                            if (line[i + 1] === '"') { cell += '"'; i += 2; continue; }
                            break;
                        }
                        cell += line[i]; i++;
                    }
                    i++;
                    out.push(cell);
                    if (i < line.length && line[i] === ',') i++;
                } else {
                    var start = i;
                    while (i < line.length && line[i] !== ',') i++;
                    out.push(line.slice(start, i).replace(/^[\s\uFEFF]+|[\s\uFEFF]+$/g, ''));
                    if (i < line.length && line[i] === ',') i++;
                }
            }
            return out;
        }

        function parseCSV(text) {
            if (!text || !text.trim()) return [];
            text = text.replace(/^\uFEFF/, '');
            var lines = text.split(/\r?\n/);
            var numCols = 12;
            return lines.map(function(l) {
                var row = parseCSVLine(l.replace(/\r/g, ''));
                var out = row.map(function(c) { return String(c).replace(/\r/g, '').trim(); });
                if (out.length > numCols) out = out.slice(0, numCols);
                while (out.length < numCols) out.push('');
                return out;
            });
        }

        function emptyRow() { return ['', '', '', '', '', '', '', '', '', '', '', '']; }

        function isDataRowEmpty(row) {
            if (!row) return true;
            return !row[1] && !row[2] && !row[3] && !row[4] && !row[6] && !row[7] && !row[8] && !row[10] && !row[11];
        }

        function getDataRows(rows) {
            var data = [];
            for (var i = 2; i < rows.length; i++) {
                if (!isDataRowEmpty(rows[i])) data.push(rows[i].slice());
            }
            return data;
        }

        function rebuildRows(headerRow, mapRow, dataRows) {
            var rows = [headerRow.slice(), mapRow.slice()];
            dataRows.forEach(function(row) { rows.push(row.slice()); });
            rows.push(emptyRow());
            return rows;
        }

        function rowsToCsv(rows) {
            return rows.map(function(row) {
                return row.map(function(cell) {
                    var s = String(cell == null ? '' : cell);
                    if (s === '') return '""';
                    if (/^\d+(\.\d+)?$/.test(s)) return s;
                    return '"' + s.replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\n');
        }

        function recalcTotals() {
            var totalA = 0, totalB = 0;
            document.querySelectorAll('#sb-match-rows .sb-score-a').forEach(function(el) { totalA += parseInt(el.value, 10) || 0; });
            document.querySelectorAll('#sb-match-rows .sb-score-b').forEach(function(el) { totalB += parseInt(el.value, 10) || 0; });
            var elA = document.getElementById('sb-total-a');
            var elB = document.getElementById('sb-total-b');
            if (elA) elA.textContent = totalA;
            if (elB) elB.textContent = totalB;
        }

        function collectFromDom() {
            if (!_rows || _rows.length === 0) return null;
            var headerRow = (_rows[0] || emptyRow()).slice();
            var mapRow = (_rows[1] || emptyRow()).slice();
            var teamA = document.getElementById('sb-team-a');
            var teamB = document.getElementById('sb-team-b');
            var map1 = document.getElementById('sb-map1-label');
            var map2 = document.getElementById('sb-map2-label');
            if (teamA) headerRow[2] = teamA.value.trim();
            if (teamB) headerRow[6] = teamB.value.trim();
            if (map1) mapRow[10] = map1.value.trim();
            if (map2) mapRow[11] = map2.value.trim();
            var dataRows = [];
            document.querySelectorAll('#sb-match-rows .sb-match-row').forEach(function(el) {
                function val(sel) {
                    var inp = el.querySelector(sel);
                    return inp ? String(inp.value || '').trim() : '';
                }
                dataRows.push([
                    '', val('.sb-type'), val('.sb-pa1'), val('.sb-pa2'), val('.sb-score-a'),
                    '', val('.sb-pb1'), val('.sb-pb2'), val('.sb-score-b'),
                    '', val('.sb-map1'), val('.sb-map2')
                ]);
            });
            recalcTotals();
            headerRow[4] = (document.getElementById('sb-total-a') || {}).textContent || '';
            headerRow[8] = (document.getElementById('sb-total-b') || {}).textContent || '';
            return rebuildRows(headerRow, mapRow, dataRows);
        }

        function makeRowEl(row, idx) {
            var div = document.createElement('div');
            div.className = 'sb-match-row';
            div.innerHTML =
                '<div class="match-top">' +
                    '<span class="row-num">#' + (idx + 1) + '</span>' +
                    '<input type="text" class="inp inp-type sb-type" value="' + attr((row[1] || '').trim()) + '" placeholder="1v1" list="sb-type-suggestions">' +
                    '<span style="font-size:11px;color:#777;">Maps</span>' +
                    '<input type="text" class="inp inp-map sb-map1" value="' + attr((row[10] || '').trim()) + '" placeholder="Map 1">' +
                    '<input type="text" class="inp inp-map sb-map2" value="' + attr((row[11] || '').trim()) + '" placeholder="Map 2">' +
                    '<button type="button" class="btn-del sb-del" data-idx="' + idx + '" title="Delete match">×</button>' +
                '</div>' +
                '<div class="match-players">' +
                    '<input type="text" class="inp inp-a sb-pa1" value="' + attr((row[2] || '').trim()) + '" placeholder="Team A player 1">' +
                    '<input type="text" class="inp inp-a sb-pa2" value="' + attr((row[3] || '').trim()) + '" placeholder="A player 2">' +
                    '<input type="number" class="inp inp-score inp-score-a sb-score-a" min="0" max="99" value="' + attr(String(row[4] != null ? row[4] : '')) + '">' +
                    '<span style="color:#555;">–</span>' +
                    '<input type="number" class="inp inp-score inp-score-b sb-score-b" min="0" max="99" value="' + attr(String(row[8] != null ? row[8] : '')) + '">' +
                    '<input type="text" class="inp inp-b sb-pb1" value="' + attr((row[6] || '').trim()) + '" placeholder="Team B player 1">' +
                    '<input type="text" class="inp inp-b sb-pb2" value="' + attr((row[7] || '').trim()) + '" placeholder="B player 2">' +
                '</div>';
            div.querySelector('.sb-del').addEventListener('click', function() {
                deleteRow(parseInt(this.getAttribute('data-idx'), 10));
            });
            div.querySelector('.sb-score-a').addEventListener('input', recalcTotals);
            div.querySelector('.sb-score-b').addEventListener('input', recalcTotals);
            return div;
        }

        function render(rows) {
            if (!rows || rows.length === 0) return;
            var r0 = rows[0] || [];
            var r1 = rows[1] || [];
            var teamA = document.getElementById('sb-team-a');
            var teamB = document.getElementById('sb-team-b');
            var map1 = document.getElementById('sb-map1-label');
            var map2 = document.getElementById('sb-map2-label');
            if (teamA) teamA.value = (r0[2] || '').trim();
            if (teamB) teamB.value = (r0[6] || '').trim();
            if (map1) map1.value = (r1[10] || '').trim();
            if (map2) map2.value = (r1[11] || '').trim();
            var container = document.getElementById('sb-match-rows');
            container.innerHTML = '';
            getDataRows(rows).forEach(function(row, idx) {
                container.appendChild(makeRowEl(row, idx));
            });
            recalcTotals();
        }

        function loadFromCsv(showStatus) {
            var status = document.getElementById('load-status');
            if (showStatus && status) status.textContent = 'Loading…';
            var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
            var baseUrl = base ? base + '/' : './';
            return fetch(baseUrl + '2026/scoreboard.csv?_t=' + Date.now())
                .then(function(r) { if (!r.ok) throw new Error(r.status); return r.text(); })
                .then(function(text) {
                    _rows = parseCSV(text);
                    render(_rows);
                    if (showStatus && status) status.textContent = 'Loaded.';
                    setTimeout(function() { if (status) status.textContent = ''; }, 2000);
                })
                .catch(function(err) {
                    if (status) status.textContent = 'Error: ' + err.message;
                });
        }

        function bootstrapFromSession() {
            try {
                var raw = sessionStorage.getItem('sb_editor_bootstrap');
                if (!raw) return false;
                sessionStorage.removeItem('sb_editor_bootstrap');
                var rows = JSON.parse(raw);
                if (!Array.isArray(rows) || rows.length === 0) return false;
                _rows = rows;
                render(_rows);
                return true;
            } catch (e) {
                return false;
            }
        }

        function addRow() {
            if (!_rows) return;
            var rows = collectFromDom();
            if (!rows) return;
            var dataRows = getDataRows(rows);
            var newRow = emptyRow();
            newRow[1] = '1v1';
            dataRows.push(newRow);
            _rows = rebuildRows(rows[0], rows[1], dataRows);
            render(_rows);
        }

        function deleteRow(idx) {
            if (!_rows) return;
            var rows = collectFromDom();
            if (!rows) return;
            var dataRows = getDataRows(rows);
            if (idx < 0 || idx >= dataRows.length) return;
            dataRows.splice(idx, 1);
            _rows = rebuildRows(rows[0], rows[1], dataRows);
            render(_rows);
        }

        function save() {
            if (!_rows) return;
            var status = document.getElementById('save-status');
            var btn = document.getElementById('sb-save-btn');
            if (btn) btn.disabled = true;
            if (status) status.textContent = 'Saving…';
            var rows = collectFromDom();
            if (!rows) { if (btn) btn.disabled = false; return; }
            _rows = rows;
            var csv = rowsToCsv(rows);
            fetch('save_scoreboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'text/csv; charset=utf-8' },
                body: csv
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res && res.ok) {
                    if (status) status.textContent = 'Saved!';
                    if (window.opener && !window.opener.closed) {
                        try {
                            window.opener.postMessage({ type: 'scoreboard-editor-saved' }, window.location.origin);
                        } catch (e) {}
                    }
                } else {
                    if (status) status.textContent = 'Error saving.';
                }
                setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
            }).catch(function() {
                if (status) status.textContent = 'Network error.';
                setTimeout(function() { if (status) status.textContent = ''; if (btn) btn.disabled = false; }, 2500);
            });
        }

        document.getElementById('sb-add-btn').addEventListener('click', addRow);
        document.getElementById('sb-save-btn').addEventListener('click', save);
        document.getElementById('sb-reload-btn').addEventListener('click', function() { loadFromCsv(true); });

        if (!bootstrapFromSession()) {
            loadFromCsv(false);
        }
    })();
    </script>
</body>
</html>
