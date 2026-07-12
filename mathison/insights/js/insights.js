/**
 * Insights dashboard — compound tactics are the primary unit.
 *
 * A tactic is a chained plan like "3 hatch · ling bane · all in",
 * not a single word. Atomic tags still exist in the DB for filters
 * but are not what these charts show.
 */
(function () {
    'use strict';

    var API = 'api/insights.php';
    var charts = {};
    var workerEras = [];
    var currentWorkers = '';

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function apiUrl(action, extra) {
        var q = 'action=' + encodeURIComponent(action);
        if (currentWorkers) q += '&startWorkers=' + encodeURIComponent(currentWorkers);
        if (extra) q += '&' + extra;
        return API + '?' + q;
    }

    function getJSON(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.text().then(function (text) {
                var res;
                try {
                    res = JSON.parse(text);
                } catch (e) {
                    throw new Error('Bad JSON from API (' + r.status + '): ' + text.slice(0, 180));
                }
                if (!res.ok) throw new Error(res.error || 'Request failed');
                return res.data;
            });
        });
    }

    function makeChart(id, config) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), config);
    }

    Chart.defaults.color = '#8b9bb0';
    Chart.defaults.borderColor = 'rgba(42, 53, 69, 0.7)';
    Chart.defaults.font.family = 'Outfit, system-ui, sans-serif';

    function renderStatus(st) {
        var el = document.getElementById('status-banner');
        el.hidden = false;

        var sel = document.getElementById('startWorkers');
        if (sel.options.length <= 1 && st.worker_eras) {
            workerEras = st.worker_eras;
            var counts = {};
            (st.worker_counts || []).forEach(function (r) {
                counts[String(r.start_workers)] = Number(r.c);
            });
            workerEras.forEach(function (era) {
                var opt = document.createElement('option');
                opt.value = String(era.workers);
                var n = counts[String(era.workers)];
                opt.textContent = era.label + (n != null ? ' (' + n + ' tactics)' : '');
                sel.appendChild(opt);
            });
        }

        if (st.never_run) {
            el.className = 'stale-banner mb-3';
            el.innerHTML = 'The relabeler has never run. Run <code>php mathison/insights/relabel.php</code> to build the insight tables.';
            return;
        }
        document.getElementById('st-run').textContent = st.last_run.finished_at || '—';
        document.getElementById('st-run-sub').textContent =
            (st.last_run.tactics_written || 0) + ' tactics / ' +
            (st.last_run.tags_written || 0) + ' atomic tags';

        if (st.stale) {
            el.className = 'stale-banner mb-3';
            el.innerHTML = '<strong>Rerun suggested:</strong> ' +
                st.new_commented_replays + ' new commented replay(s) and ' +
                st.new_patterns + ' new pattern(s) since the last relabel. ' +
                'Run <code>php mathison/insights/relabel.php</code> to refresh.';
        } else {
            el.className = 'fresh-banner mb-3';
            el.textContent = 'Insight tables are up to date with Replays and PatternLearning.';
        }
    }

    function renderOverview(d) {
        document.getElementById('st-games').textContent = Number(d.totals.tactic_rows || 0).toLocaleString();
        document.getElementById('st-games-sub').textContent =
            Number(d.totals.tagged_sources || 0).toLocaleString() + ' games with compound plans';
        document.getElementById('st-tags').textContent = Number(d.totals.full_chains || 0).toLocaleString();
        document.getElementById('st-opps').textContent = Number(d.totals.opponents || 0).toLocaleString();

        var top = (d.top_tactics || []).slice(0, 15);
        makeChart('chart-tags', {
            type: 'bar',
            data: {
                labels: top.map(function (t) { return t.tactic_label; }),
                datasets: [{
                    data: top.map(function (t) { return Number(t.games); }),
                    backgroundColor: '#3d9cf0'
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { title: { display: true, text: 'games' } },
                    y: { ticks: { font: { size: 11 } } }
                }
            }
        });

        var wr = (d.tactic_results || []).map(function (t) {
            var w = Number(t.our_wins), l = Number(t.our_losses);
            return {
                label: t.tactic_label,
                games: w + l,
                rate: (w + l) ? (100 * w / (w + l)) : 0
            };
        }).filter(function (t) { return t.games >= 3; })
          .sort(function (a, b) { return a.rate - b.rate; })
          .slice(0, 15);

        makeChart('chart-winrate', {
            type: 'bar',
            data: {
                labels: wr.map(function (t) { return t.label + ' (' + t.games + ')'; }),
                datasets: [{
                    data: wr.map(function (t) { return t.rate.toFixed(1); }),
                    backgroundColor: wr.map(function (t) { return t.rate >= 50 ? '#3ecf8e' : '#f07178'; })
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { min: 0, max: 100, title: { display: true, text: 'our win %' } },
                    y: { ticks: { font: { size: 11 } } }
                }
            }
        });

        var tbody = document.getElementById('top-opponents');
        tbody.innerHTML = (d.top_opponents || []).map(function (o) {
            return '<tr data-name="' + esc(o.opponent_name) + '" style="cursor:pointer;">' +
                '<td>' + esc(o.opponent_name) + '</td>' +
                '<td>' + esc(o.opponent_race || '—') + '</td>' +
                '<td>' + esc(o.tagged_games) + '</td>' +
                '<td><span class="result-win">' + Number(o.our_wins) + '</span>–<span class="result-lose">' + Number(o.our_losses) + '</span></td>' +
                '</tr>';
        }).join('');
        tbody.onclick = function (e) {
            var tr = e.target.closest('tr[data-name]');
            if (!tr) return;
            var sel = document.getElementById('opponent-select');
            sel.value = tr.getAttribute('data-name');
            loadOpponent(sel.value);
            sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
    }

    function loadDashboard() {
        getJSON(apiUrl('overview')).then(renderOverview).catch(function () {
            document.getElementById('st-games').textContent = '—';
            document.getElementById('st-tags').textContent = '—';
            document.getElementById('st-opps').textContent = '—';
        });

        var sel = document.getElementById('opponent-select');
        var keep = sel.value || pendingOpponent;
        while (sel.options.length > 1) sel.remove(1);
        return getJSON(apiUrl('opponents')).then(function (list) {
            list.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.opponent_name;
                opt.textContent = o.opponent_name + ' (' + o.tagged_games + ' plans, ' + (o.opponent_race || '?') + ')';
                sel.appendChild(opt);
            });
            if (keep) {
                var found = false;
                var keepLower = String(keep).toLowerCase();
                Array.prototype.forEach.call(sel.options, function (o) {
                    if (o.value.toLowerCase() === keepLower) {
                        found = true;
                        keep = o.value;
                    }
                });
                if (!found) {
                    var opt = document.createElement('option');
                    opt.value = keep;
                    opt.textContent = keep + ' (lookup)';
                    sel.appendChild(opt);
                }
                sel.value = keep;
                var lookup = document.getElementById('opponent-lookup');
                if (lookup) lookup.value = keep;
                if (sel.value === keep) loadOpponent(keep);
                else {
                    document.getElementById('opp-panels').hidden = true;
                    document.getElementById('opp-record').textContent = '';
                }
            }
        }).catch(function () {});
    }

    function renderOpponents(list) {
        // kept for compatibility; loadDashboard owns the select now
    }

    function loadOpponent(name) {
        getJSON(apiUrl('opponent', 'name=' + encodeURIComponent(name))).then(function (d) {
            document.getElementById('opp-panels').hidden = false;

            var w = Number((d.record && d.record.our_wins) || 0);
            var l = Number((d.record && d.record.our_losses) || 0);
            document.getElementById('opp-record').innerHTML =
                'Our record vs <strong>' + esc(d.name) + '</strong>: ' +
                '<span class="result-win">' + w + 'W</span> – <span class="result-lose">' + l + 'L</span>' +
                (w + l ? ' (' + (100 * w / (w + l)).toFixed(0) + '%)' : '');

            var replaysLink = document.getElementById('opp-replays-link');
            if (replaysLink) {
                replaysLink.hidden = false;
                replaysLink.href = '../replays.php?playerA=' + encodeURIComponent(d.name) +
                    (currentWorkers ? '&startWorkers=' + encodeURIComponent(currentWorkers) : '');
                replaysLink.textContent = 'All replays vs ' + d.name;
            }

            var tactics = (d.tactics || []).filter(function (t) { return Number(t.parts_count) >= 3; });
            if (!tactics.length) tactics = d.tactics || [];
            tactics = tactics.slice(0, 12);

            if (tactics.length) {
                document.getElementById('opp-tactics-empty').hidden = true;
                makeChart('chart-opp-tags', {
                    type: 'bar',
                    data: {
                        labels: tactics.map(function (t) { return t.tactic_label; }),
                        datasets: [{
                            data: tactics.map(function (t) { return Number(t.games); }),
                            backgroundColor: '#b86bdb'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { precision: 0 }, title: { display: true, text: 'times seen' } },
                            y: { ticks: { font: { size: 11 } } }
                        }
                    }
                });
            } else {
                document.getElementById('opp-tactics-empty').hidden = false;
                if (charts['chart-opp-tags']) {
                    charts['chart-opp-tags'].destroy();
                    delete charts['chart-opp-tags'];
                }
            }

            var timings = d.key_timings || [];
            document.getElementById('opp-timings').innerHTML = timings.length
                ? timings.map(function (t) {
                    return '<tr><td>' + esc(t.building) + '</td><td>' + esc(t.earliest) + '</td><td>' + esc(t.avg) + '</td><td>' + esc(t.games) + '</td></tr>';
                }).join('')
                : '<tr><td colspan="4" class="stat-sub">No PatternLearning timing data for this opponent.</td></tr>';

            var likely = d.likely_strategies || { games: [], summary: [] };
            var meta = likely.meta || {};
            var likelyMeta = document.getElementById('likely-meta');
            var eraNote = currentWorkers
                ? (' Worker filter: ' + currentWorkers + ' (switch to All eras if empty).')
                : '';
            if (likelyMeta) {
                var src = meta.corpus_source === 'local'
                    ? 'Matched against this player’s own labeled comments'
                    : 'Matched against global same-race labeled games (no local fingerprints)';
                var race = meta.dominant_race ? (' · race lock: ' + meta.dominant_race) : '';
                likelyMeta.textContent =
                    src + race + '. Showing top ' + (meta.top_n || 5) +
                    ' by match %. Scanned ' +
                    (meta.unlabeled_scanned != null ? meta.unlabeled_scanned : 0) +
                    ' unlabeled replay(s).' + eraNote;
            }

            // One row per matched game (clearer than collapsed chips).
            var likelyBody = document.getElementById('likely-body');
            if (likelyBody) {
                if ((likely.games || []).length) {
                    likelyBody.innerHTML = likely.games.map(function (g) {
                        var pct = Math.round(100 * Number(g.confidence || 0));
                        var date = g.date_played ? String(g.date_played).slice(0, 10) : '—';
                        var dateCell = g.replay_id
                            ? '<a class="player-link" href="../replays.php?id=' + encodeURIComponent(g.replay_id) + '">' + esc(date) + '</a>'
                            : esc(date);
                        var localBadge = g.corpus_local
                            ? ' <span class="tag-chip" title="Matched to this player’s own comment">their comment</span>'
                            : '';
                        return '<tr>' +
                            '<td><strong>' + esc(g.likely_label) + '</strong>' + localBadge +
                            '<div class="stat-sub">' + dateCell +
                            (g.timing_delta_sec != null ? ' · Δ' + esc(String(g.timing_delta_sec)) + 's' : '') +
                            (g.shared_buildings && g.shared_buildings.length
                                ? ' · ' + esc(g.shared_buildings.join(', '))
                                : '') +
                            '</div></td>' +
                            '<td><strong>' + pct + '%</strong></td>' +
                            '<td>1</td>' +
                            '<td class="stat-sub">' + esc(g.matched_comment || '') + '</td>' +
                            '</tr>';
                    }).join('');
                } else if ((likely.summary || []).length) {
                    likelyBody.innerHTML = likely.summary.map(function (s) {
                        var pct = Math.round(100 * Number(s.avg_confidence || 0));
                        return '<tr><td><strong>' + esc(s.likely_label) + '</strong></td>' +
                            '<td><strong>' + pct + '%</strong></td>' +
                            '<td>' + esc(String(s.games)) + '</td>' +
                            '<td class="stat-sub">' + esc(s.matched_comment || '—') + '</td></tr>';
                    }).join('');
                } else {
                    likelyBody.innerHTML =
                        '<tr><td colspan="4" class="stat-sub">No likely matches' +
                        (meta.unlabeled_scanned ? ' from ' + meta.unlabeled_scanned + ' unlabeled replay(s)' : '') +
                        (meta.dominant_race ? ' (locked to ' + esc(meta.dominant_race) + ')' : '') +
                        '. Try Starting workers → All eras.</td></tr>';
                }
            }

            var labeled = (d.games || []).map(function (g) {
                return {
                    date_played: g.date_played,
                    replay_id: g.replay_id,
                    self_result: g.self_result,
                    label: g.tactic_label,
                    phase: g.phase,
                    sub: g.comment,
                    inferred: false,
                    confidence: null
                };
            });
            var inferred = (likely.games || []).map(function (g) {
                var conf = Math.round(100 * Number(g.confidence || 0));
                return {
                    date_played: g.date_played,
                    replay_id: g.replay_id,
                    self_result: g.self_result,
                    label: g.likely_label,
                    phase: null,
                    sub: conf + '% match' +
                        (g.matched_comment ? ' · like “' + g.matched_comment + '”' : ''),
                    inferred: true,
                    confidence: conf
                };
            });
            var rows = labeled.concat(inferred).sort(function (a, b) {
                return String(b.date_played || '').localeCompare(String(a.date_played || ''));
            });

            document.getElementById('opp-games').innerHTML = rows.length
                ? rows.map(function (g) {
                    var res = g.self_result === 'Win' ? '<span class="result-win">W</span>'
                        : g.self_result === 'Lose' ? '<span class="result-lose">L</span>' : '—';
                    var date = g.date_played ? String(g.date_played).slice(0, 10) : '—';
                    var phase = g.phase && g.phase !== 'any' ? ' <span class="tag-chip">' + esc(g.phase) + '</span>' : '';
                    var likelyBadge = g.inferred
                        ? ' <span class="tag-chip" title="Inferred from timing match">' +
                          (g.confidence != null ? g.confidence + '% match' : 'likely') + '</span>'
                        : '';
                    var dateCell = g.replay_id
                        ? '<a class="player-link" href="../replays.php?id=' + encodeURIComponent(g.replay_id) +
                          '" title="Open replay #' + esc(g.replay_id) + '">' + esc(date) + '</a>'
                        : esc(date);
                    return '<tr' + (g.inferred ? ' class="likely-row"' : '') + '><td>' + dateCell + '</td><td>' + res + '</td>' +
                        '<td class="comment-cell">' +
                        '<div><strong>' + esc(g.label) + '</strong>' + phase + likelyBadge + '</div>' +
                        '<div class="stat-sub">' + esc(g.sub || '') + '</div></td></tr>';
                }).join('')
                : '<tr><td colspan="3" class="stat-sub">No tagged or inferred games. Use “All replays vs…” for full history.</td></tr>';
        }).catch(function (err) {
            document.getElementById('opp-panels').hidden = false;
            document.getElementById('opp-record').textContent = 'Error: ' + err.message;
            var likelyBody = document.getElementById('likely-body');
            if (likelyBody) {
                likelyBody.innerHTML = '<tr><td colspan="4" class="stat-sub">API error: ' + esc(err.message) + '</td></tr>';
            }
        });
    }

    function applyOpponentFromUrl() {
        var boot = new URLSearchParams(window.location.search);
        if (boot.get('startWorkers')) {
            currentWorkers = boot.get('startWorkers');
            var sw = document.getElementById('startWorkers');
            if (sw) sw.value = currentWorkers;
        }
        return boot.get('opponent') || boot.get('name') || '';
    }

    var pendingOpponent = applyOpponentFromUrl();

    getJSON(apiUrl('status')).then(function (st) {
        renderStatus(st);
        return loadDashboard();
    }).then(function () {
        if (pendingOpponent) {
            setTimeout(function () {
                var panels = document.getElementById('opp-panels');
                if (panels && !panels.hidden) {
                    panels.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 250);
        }
    }).catch(function (err) {
        var el = document.getElementById('status-banner');
        el.hidden = false;
        el.className = 'stale-banner mb-3';
        el.textContent = 'Status check failed: ' + err.message;
    });

    document.getElementById('startWorkers').addEventListener('change', function () {
        currentWorkers = this.value;
        loadDashboard();
        var sel = document.getElementById('opponent-select');
        var u = new URL(window.location.href);
        if (currentWorkers) u.searchParams.set('startWorkers', currentWorkers);
        else u.searchParams.delete('startWorkers');
        if (sel.value) u.searchParams.set('opponent', sel.value);
        history.replaceState(null, '', u.toString());
    });

    function selectOpponent(name) {
        name = String(name || '').trim();
        if (!name) return;
        var sel = document.getElementById('opponent-select');
        var found = false;
        Array.prototype.forEach.call(sel.options, function (o) {
            if (o.value.toLowerCase() === name.toLowerCase()) {
                sel.value = o.value;
                name = o.value;
                found = true;
            }
        });
        if (!found) {
            var opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name + ' (lookup)';
            sel.appendChild(opt);
            sel.value = name;
        }
        var lookup = document.getElementById('opponent-lookup');
        if (lookup) lookup.value = name;
        loadOpponent(name);
        var u = new URL(window.location.href);
        u.searchParams.set('opponent', name);
        history.replaceState(null, '', u.toString());
    }

    document.getElementById('opponent-select').addEventListener('change', function () {
        if (!this.value) return;
        selectOpponent(this.value);
    });

    var lookupBtn = document.getElementById('opponent-lookup-btn');
    var lookupInput = document.getElementById('opponent-lookup');
    if (lookupBtn && lookupInput) {
        lookupBtn.addEventListener('click', function () { selectOpponent(lookupInput.value); });
        lookupInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                selectOpponent(lookupInput.value);
            }
        });
    }
})();
