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
     * All 1v1 CSV rows matching the two PSB names (top → bottom).
     * occurrence = 0-based index among matches for this pair (stable if other rows insert above).
     */
    function findAllMatchedRows(rows, nameA, nameB) {
        var n0 = normName(nameA);
        var n1 = normName(nameB);
        var out = [];
        if (!n0 || !n1 || n0 === n1 || !rows || !rows.length) return out;
        var occurrence = 0;
        for (var i = 2; i < rows.length; i++) {
            var row = rows[i];
            if (!is1v1Row(row)) continue;
            var a = normName(row[2]);
            var b = normName(row[6]);
            if (a === n0 && b === n1) {
                out.push({ rowIndex: i, orientation: 'ab', occurrence: occurrence });
                occurrence++;
            } else if (a === n1 && b === n0) {
                out.push({ rowIndex: i, orientation: 'ba', occurrence: occurrence });
                occurrence++;
            }
        }
        return out;
    }

    function matchScoreDistance(row, orientation, scoreA, scoreB) {
        var sA = scoreInt(scoreA);
        var sB = scoreInt(scoreB);
        if (orientation === 'ab') {
            return Math.abs(scoreInt(row[4]) - sA) + Math.abs(scoreInt(row[8]) - sB);
        }
        return Math.abs(scoreInt(row[8]) - sA) + Math.abs(scoreInt(row[4]) - sB);
    }

    /**
     * Pick which matching row to sync.
     * opts: { scoreA, scoreB, pinOccurrence }
     * 1) Valid pinOccurrence → that match
     * 2) Else closest scores to PSB; ties → last match (ACE rematches)
     */
    function findMatchedRow(rows, nameA, nameB, opts) {
        opts = opts || {};
        var all = findAllMatchedRows(rows, nameA, nameB);
        if (!all.length) return null;
        if (all.length === 1) return all[0];

        var pin = opts.pinOccurrence;
        if (pin != null && pin !== '') {
            pin = parseInt(pin, 10);
            if (!isNaN(pin)) {
                for (var i = 0; i < all.length; i++) {
                    if (all[i].occurrence === pin) return all[i];
                }
            }
        }

        var best = null;
        var bestDist = Infinity;
        for (var j = 0; j < all.length; j++) {
            var m = all[j];
            var dist = matchScoreDistance(rows[m.rowIndex], m.orientation, opts.scoreA, opts.scoreB);
            if (dist < bestDist || (dist === bestDist && (!best || m.occurrence > best.occurrence))) {
                bestDist = dist;
                best = m;
            }
        }
        return best;
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
     * pinOccurrence optional — forces which duplicate pair row to update.
     */
    function applyPsbToRows(rows, nameA, nameB, scoreA, scoreB, pinOccurrence) {
        var match = findMatchedRow(rows, nameA, nameB, {
            scoreA: scoreA,
            scoreB: scoreB,
            pinOccurrence: pinOccurrence
        });
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

    function matchOptsFromPsb(psb) {
        var p = playersFromPsb(psb);
        var pin = (psb && psb.tlsPinOccurrence != null && psb.tlsPinOccurrence !== '')
            ? parseInt(psb.tlsPinOccurrence, 10)
            : null;
        return {
            scoreA: p.scoreA,
            scoreB: p.scoreB,
            pinOccurrence: (pin != null && !isNaN(pin)) ? pin : null
        };
    }

    function isTeamLeague(psb) {
        /* Default true for back-compat with older saved JSON. */
        return !psb || psb.teamLeague !== false;
    }

    function scoreSnapshot(psb) {
        var p = playersFromPsb(psb);
        return {
            nameA: p.nameA,
            nameB: p.nameB,
            scoreA: p.scoreA,
            scoreB: p.scoreB
        };
    }

    function samePair(a1, b1, a2, b2) {
        var x0 = normName(a1);
        var x1 = normName(b1);
        var y0 = normName(a2);
        var y1 = normName(b2);
        return (x0 === y0 && x1 === y1) || (x0 === y1 && x1 === y0);
    }

    /**
     * Non-team-league 1v1 score change: update custom_scoreboard.json and emit
     * activity burst gg + intro(winner) + score{mode:"1v1"} to save_status.php.
     * Does NOT touch 2026/scoreboard.csv.
     */
    function report1v1Score(psb, prev) {
        if (isTeamLeague(psb)) {
            return Promise.resolve({ skipped: true, reason: 'team_league' });
        }
        var cur = scoreSnapshot(psb);
        if (!normName(cur.nameA) || !normName(cur.nameB)) {
            return Promise.resolve({ skipped: true, reason: 'names' });
        }
        prev = prev || {};
        var fromA = scoreInt(prev.scoreA);
        var fromB = scoreInt(prev.scoreB);
        var toA = cur.scoreA;
        var toB = cur.scoreB;
        if (fromA === toA && fromB === toB &&
            normName(prev.nameA) === normName(cur.nameA) &&
            normName(prev.nameB) === normName(cur.nameB)) {
            return Promise.resolve({ skipped: true, reason: 'unchanged' });
        }
        /* Only emit when a score value changed (not rename-only). */
        if (fromA === toA && fromB === toB) {
            return Promise.resolve({ skipped: true, reason: 'names_only' });
        }

        var winner = null;
        if (toA > fromA && toB === fromB) winner = cur.nameA;
        else if (toB > fromB && toA === fromA) winner = cur.nameB;
        else if (toA > toB) winner = cur.nameA;
        else if (toB > toA) winner = cur.nameB;

        var scoreData = {
            mode: '1v1',
            a: cur.nameA,
            b: cur.nameB,
            fromA: fromA,
            toA: toA,
            fromB: fromB,
            toB: toB
        };
        if (winner) scoreData.winner = winner;

        var events = [
            { type: 'gg', data: { kind: 'gg' } }
        ];
        if (winner) events.push({ type: 'intro', data: { player: winner } });
        events.push({ type: 'score', data: scoreData });

        var base = pageBaseUrl();
        var statusPayload = {
            user: (typeof window !== 'undefined' && window.CURRENT_USER) ? window.CURRENT_USER : '',
            events: events
        };

        var statusPromise = fetch(base + 'save_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(statusPayload)
        }).then(function (r) { return r.json(); }).catch(function () {
            return { ok: false };
        });

        var customPromise = fetch(base + 'save_custom_scoreboard.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : { matches: [] }; })
            .catch(function () { return { matches: [] }; })
            .then(function (data) {
                if (!data || typeof data !== 'object') data = { matches: [] };
                if (!Array.isArray(data.matches)) data.matches = [];
                var entry = { a: cur.nameA, b: cur.nameB, scoreA: toA, scoreB: toB };
                var found = -1;
                for (var i = 0; i < data.matches.length; i++) {
                    var m = data.matches[i];
                    if (samePair(m.a, m.b, cur.nameA, cur.nameB)) {
                        found = i;
                        if (m.desc) entry.desc = m.desc;
                        break;
                    }
                }
                if (found >= 0) {
                    data.matches[found] = Object.assign({}, data.matches[found], entry);
                } else {
                    data.matches.unshift(entry);
                }
                return fetch(base + 'save_custom_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(function (r) { return r.json(); });
            })
            .catch(function () { return { ok: false }; });

        return Promise.all([statusPromise, customPromise]).then(function (results) {
            return {
                skipped: false,
                scoreData: scoreData,
                winner: winner,
                statusOk: !!(results[0] && results[0].ok),
                customOk: !!(results[1] && results[1].ok)
            };
        });
    }

    /**
     * Push PSB scores into the matching TLS CSV row (if any).
     * @returns {Promise<{matched:boolean, changed:boolean, match:object|null}>}
     */
    function syncPsbToCsv(psb) {
        if (!isTeamLeague(psb)) {
            return Promise.resolve({ matched: false, changed: false, match: null, skipped: true, reason: 'not_team_league' });
        }
        var p = playersFromPsb(psb);
        if (!normName(p.nameA) || !normName(p.nameB)) {
            return Promise.resolve({ matched: false, changed: false, match: null });
        }
        if (_lock) return Promise.resolve({ matched: false, changed: false, match: null, skipped: true });
        var base = pageBaseUrl();
        var opts = matchOptsFromPsb(psb);
        _lock = true;
        return fetch(base + '2026/scoreboard.csv?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.text() : ''; })
            .then(function (text) {
                var rows = parseCSV(text);
                var result = applyPsbToRows(rows, p.nameA, p.nameB, p.scoreA, p.scoreB, opts.pinOccurrence);
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
                if (!isTeamLeague(psb)) {
                    return { matched: false, changed: false, match: null, skipped: true, reason: 'not_team_league' };
                }
                var p = playersFromPsb(psb);
                var opts = matchOptsFromPsb(psb);
                var match = findMatchedRow(rows, p.nameA, p.nameB, opts);
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
     * Load PSB names/scores/pin for CURRENT MATCH highlighting.
     */
    function loadPsbNames() {
        var base = pageBaseUrl();
        return fetch(base + 'save_player_scoreboard.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (psb) {
                var p = playersFromPsb(psb);
                var opts = matchOptsFromPsb(psb);
                return {
                    nameA: p.nameA,
                    nameB: p.nameB,
                    scoreA: p.scoreA,
                    scoreB: p.scoreB,
                    pinOccurrence: opts.pinOccurrence,
                    psb: psb
                };
            })
            .catch(function () {
                return { nameA: '', nameB: '', scoreA: 0, scoreB: 0, pinOccurrence: null, psb: null };
            });
    }

    /** Persist tlsPinOccurrence on the player scoreboard (null clears). */
    function setTlsPinOccurrence(occurrence) {
        var base = pageBaseUrl();
        return fetch(base + 'save_player_scoreboard.php?_t=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (psb) {
                if (!psb || typeof psb !== 'object') psb = {};
                if (occurrence == null || occurrence === '') {
                    delete psb.tlsPinOccurrence;
                } else {
                    psb.tlsPinOccurrence = parseInt(occurrence, 10);
                }
                return fetch(base + 'save_player_scoreboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(psb)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || !res.ok) throw new Error('psb pin save failed');
                    return { ok: true, tlsPinOccurrence: psb.tlsPinOccurrence != null ? psb.tlsPinOccurrence : null, psb: psb };
                });
            });
    }

    global.ScoreboardSync = {
        stripPlayerCell: stripPlayerCell,
        normName: normName,
        is1v1Row: is1v1Row,
        findAllMatchedRows: findAllMatchedRows,
        findMatchedRow: findMatchedRow,
        applyPsbToRows: applyPsbToRows,
        scoresFromRowForPsb: scoresFromRowForPsb,
        rowsToCsv: rowsToCsv,
        parseCSV: parseCSV,
        syncPsbToCsv: syncPsbToCsv,
        syncCsvToPsb: syncCsvToPsb,
        report1v1Score: report1v1Score,
        scoreSnapshot: scoreSnapshot,
        isTeamLeague: isTeamLeague,
        loadPsbNames: loadPsbNames,
        setTlsPinOccurrence: setTlsPinOccurrence,
        playersFromPsb: playersFromPsb,
        matchOptsFromPsb: matchOptsFromPsb
    };
})(typeof window !== 'undefined' ? window : this);
