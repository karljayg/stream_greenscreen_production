/**
 * Bidirectional score sync between Player Scoreboard (casted 1v1) and
 * Team League Scoreboard CSV. Sync only when both PSB names match a 1v1 row.
 */
(function (global) {
    'use strict';

    var _lock = false;

    function stripPlayerCell(s) {
        return String(s == null ? '' : s).replace(/^\s*\([A-Za-z]\)\s*/, '').replace(/\[\d+\]/g, '').trim();
    }

    function normName(s) {
        return stripPlayerCell(s).toLowerCase();
    }

    function isDataRowEmpty(row) {
        if (!row) return true;
        return !row[1] && !row[2] && !row[3] && !row[4] && !row[6] && !row[7] && !row[8] && !row[10] && !row[11];
    }

    function is1v1Row(row) {
        if (!row || isDataRowEmpty(row)) return false;
        var type = String(row[1] || '').trim().toLowerCase();
        if (type && type !== '1v1') return false;
        var a1 = stripPlayerCell(row[2]);
        var a2 = stripPlayerCell(row[3]);
        var b1 = stripPlayerCell(row[6]);
        var b2 = stripPlayerCell(row[7]);
        if (!a1 || !b1) return false;
        if (a2 || b2) return false;
        return true;
    }

    /**
     * Find a 1v1 CSV row matching the two player-scoreboard names.
     * @returns {{ rowIndex: number, orientation: 'ab'|'ba' }|null}
     *   orientation 'ab' = CSV A is PSB[0], CSV B is PSB[1]
     *   orientation 'ba' = sides swapped
     */
    function findMatchedRow(rows, nameA, nameB) {
        var n0 = normName(nameA);
        var n1 = normName(nameB);
        if (!n0 || !n1 || n0 === n1 || !rows || !rows.length) return null;
        for (var i = 2; i < rows.length; i++) {
            var row = rows[i];
            if (!is1v1Row(row)) continue;
            var a = normName(row[2]);
            var b = normName(row[6]);
            if (a === n0 && b === n1) return { rowIndex: i, orientation: 'ab' };
            if (a === n1 && b === n0) return { rowIndex: i, orientation: 'ba' };
        }
        return null;
    }

    function scoreInt(v) {
        return parseInt(v, 10) || 0;
    }

    function recalcHeaderTotals(rows) {
        var totalA = 0;
        var totalB = 0;
        for (var i = 2; i < rows.length; i++) {
            if (isDataRowEmpty(rows[i])) continue;
            totalA += scoreInt(rows[i][4]);
            totalB += scoreInt(rows[i][8]);
        }
        if (!rows[0]) rows[0] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        rows[0][4] = totalA;
        rows[0][8] = totalB;
    }

    function cloneRows(rows) {
        return rows.map(function (r) { return (r || []).slice(); });
    }

    /**
     * Apply PSB scores onto a matching CSV row. Returns { matched, changed, rows, match }.
     */
    function applyPsbToRows(rows, nameA, nameB, scoreA, scoreB) {
        var match = findMatchedRow(rows, nameA, nameB);
        if (!match) return { matched: false, changed: false, rows: rows, match: null };
        var newA;
        var newB;
        if (match.orientation === 'ab') {
            newA = scoreInt(scoreA);
            newB = scoreInt(scoreB);
        } else {
            newA = scoreInt(scoreB);
            newB = scoreInt(scoreA);
        }
        var row = rows[match.rowIndex];
        if (scoreInt(row[4]) === newA && scoreInt(row[8]) === newB) {
            return { matched: true, changed: false, rows: rows, match: match };
        }
        var out = cloneRows(rows);
        out[match.rowIndex][4] = newA;
        out[match.rowIndex][8] = newB;
        recalcHeaderTotals(out);
        return { matched: true, changed: true, rows: out, match: match };
    }

    /**
     * Map a matched CSV row's scores onto PSB player order [0]=nameA, [1]=nameB.
     */
    function scoresFromRowForPsb(row, nameA, nameB) {
        var n0 = normName(nameA);
        var n1 = normName(nameB);
        var a = normName(row[2]);
        var b = normName(row[6]);
        if (a === n0 && b === n1) {
            return { score0: scoreInt(row[4]), score1: scoreInt(row[8]) };
        }
        if (a === n1 && b === n0) {
            return { score0: scoreInt(row[8]), score1: scoreInt(row[4]) };
        }
        return null;
    }

    function rowsToCsv(rows) {
        return rows.map(function (row) {
            return row.map(function (cell) {
                var s = String(cell == null ? '' : cell);
                if (s === '') return '""';
                if (/^\d+(\.\d+)?$/.test(s)) return s;
                return '"' + s.replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\n');
    }

    function parseCSVLine(line) {
        line = String(line || '').replace(/^\uFEFF/, '');
        var out = [];
        var i = 0;
        while (i < line.length) {
            if (line[i] === '"') {
                var cell = '';
                i++;
                while (i < line.length) {
                    if (line[i] === '"') {
                        if (line[i + 1] === '"') { cell += '"'; i += 2; continue; }
                        break;
                    }
                    cell += line[i];
                    i++;
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
        if (!text || !String(text).trim()) return [];
        text = String(text).replace(/^\uFEFF/, '');
        var numCols = 12;
        return text.split(/\r?\n/).map(function (l) {
            var row = parseCSVLine(l.replace(/\r/g, ''));
            var out = row.map(function (c) { return String(c).replace(/\r/g, '').trim(); });
            if (out.length > numCols) out = out.slice(0, numCols);
            while (out.length < numCols) out.push('');
            return out;
        });
    }

    function pageBaseUrl() {
        var base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
        return base ? base + '/' : './';
    }

    function playersFromPsb(psb) {
        var players = (psb && psb.players) || [];
        return {
            nameA: (players[0] && players[0].name) || '',
            nameB: (players[1] && players[1].name) || '',
            scoreA: players[0] ? scoreInt(players[0].score) : 0,
            scoreB: players[1] ? scoreInt(players[1].score) : 0
        };
    }

    /**
     * Push PSB scores into the matching TLS CSV row (if any).
     * @returns {Promise<{matched:boolean, changed:boolean, match:object|null}>}
     */
    function syncPsbToCsv(psb) {
        var p = playersFromPsb(psb);
        if (!normName(p.nameA) || !normName(p.nameB)) {
            return Promise.resolve({ matched: false, changed: false, match: null });
        }
        if (_lock) return Promise.resolve({ matched: false, changed: false, match: null, skipped: true });
        var base = pageBaseUrl();
        _lock = true;
        return fetch(base + '2026/scoreboard.csv?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.text() : ''; })
            .then(function (text) {
                var rows = parseCSV(text);
                var result = applyPsbToRows(rows, p.nameA, p.nameB, p.scoreA, p.scoreB);
                if (!result.changed) return result;
                return fetch(base + 'save_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'text/csv; charset=utf-8' },
                    body: rowsToCsv(result.rows)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || !res.ok) throw new Error('csv save failed');
                    return result;
                });
            })
            .catch(function () {
                return { matched: false, changed: false, match: null, error: true };
            })
            .then(function (result) {
                _lock = false;
                return result;
            });
    }

    /**
     * Pull matching TLS row scores into player_scoreboard.json (if pair matches).
     * @returns {Promise<{matched:boolean, changed:boolean, match:object|null}>}
     */
    function syncCsvToPsb(rows) {
        if (!rows || !rows.length) {
            return Promise.resolve({ matched: false, changed: false, match: null });
        }
        if (_lock) return Promise.resolve({ matched: false, changed: false, match: null, skipped: true });
        var base = pageBaseUrl();
        _lock = true;
        return fetch(base + 'save_player_scoreboard.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (psb) {
                if (!psb || typeof psb !== 'object') {
                    return { matched: false, changed: false, match: null };
                }
                var p = playersFromPsb(psb);
                var match = findMatchedRow(rows, p.nameA, p.nameB);
                if (!match) return { matched: false, changed: false, match: null };
                var mapped = scoresFromRowForPsb(rows[match.rowIndex], p.nameA, p.nameB);
                if (!mapped) return { matched: false, changed: false, match: null };
                var cur0 = scoreInt(psb.players[0].score);
                var cur1 = scoreInt(psb.players[1].score);
                if (cur0 === mapped.score0 && cur1 === mapped.score1) {
                    return { matched: true, changed: false, match: match };
                }
                psb.players[0].score = mapped.score0;
                psb.players[1].score = mapped.score1;
                return fetch(base + 'save_player_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(psb)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || !res.ok) throw new Error('psb save failed');
                    return { matched: true, changed: true, match: match, psb: psb };
                });
            })
            .catch(function () {
                return { matched: false, changed: false, match: null, error: true };
            })
            .then(function (result) {
                _lock = false;
                return result;
            });
    }

    /**
     * Load PSB names for CURRENT MATCH highlighting.
     * @returns {Promise<{nameA:string, nameB:string}>}
     */
    function loadPsbNames() {
        var base = pageBaseUrl();
        return fetch(base + 'save_player_scoreboard.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (psb) {
                var p = playersFromPsb(psb);
                return { nameA: p.nameA, nameB: p.nameB };
            })
            .catch(function () {
                return { nameA: '', nameB: '' };
            });
    }

    global.ScoreboardSync = {
        stripPlayerCell: stripPlayerCell,
        normName: normName,
        is1v1Row: is1v1Row,
        findMatchedRow: findMatchedRow,
        applyPsbToRows: applyPsbToRows,
        scoresFromRowForPsb: scoresFromRowForPsb,
        rowsToCsv: rowsToCsv,
        parseCSV: parseCSV,
        syncPsbToCsv: syncPsbToCsv,
        syncCsvToPsb: syncCsvToPsb,
        loadPsbNames: loadPsbNames,
        playersFromPsb: playersFromPsb
    };
})(typeof window !== 'undefined' ? window : this);
