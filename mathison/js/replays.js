(function () {
    'use strict';

    var API = 'api/replays.php';
    var state = {
        page: 1,
        limit: 50,
        sort: 'ReplayId',
        dir: 'DESC',
        total: 0,
        pages: 0,
        loading: false,
        mode: 'page', // 'page' | 'append' for infinite scroll
        filters: {},
        rowsById: {}
    };

    var els = {
        form: document.getElementById('filter-form'),
        body: document.getElementById('replays-body'),
        pageInfo: document.getElementById('page-info'),
        filterStatus: document.getElementById('filter-status'),
        totalBadge: document.getElementById('total-badge'),
        prev: document.getElementById('btn-prev'),
        next: document.getElementById('btn-next'),
        lazyStatus: document.getElementById('lazy-status'),
        sentinel: document.getElementById('lazy-sentinel'),
        modalTitle: document.getElementById('replay-modal-title'),
        modalNames: document.getElementById('modal-names'),
        modalError: document.getElementById('modal-error'),
        btnSave: document.getElementById('btn-save'),
        btnDelete: document.getElementById('btn-delete'),
        btnCreate: document.getElementById('btn-create'),
        btnReset: document.getElementById('btn-reset')
    };

    var modal = new bootstrap.Modal(document.getElementById('replay-modal'));
    var editingId = null;

    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function raceClass(race) {
        var r = (race || '').toLowerCase();
        if (r === 'protoss') return 'is-protoss';
        if (r === 'terran') return 'is-terran';
        if (r === 'zerg') return 'is-zerg';
        return '';
    }

    function resultClass(res) {
        var r = (res || '').toLowerCase();
        if (r === 'win') return 'result-win';
        if (r === 'lose') return 'result-lose';
        return '';
    }

    function fmtDate(v) {
        if (!v) return '—';
        return String(v).replace('T', ' ').slice(0, 16);
    }

    function toLocalInput(v) {
        if (!v) return '';
        var s = String(v).replace(' ', 'T');
        return s.length >= 16 ? s.slice(0, 16) : s;
    }

    function fromLocalInput(v) {
        if (!v) return '';
        return String(v).replace('T', ' ') + (v.length === 16 ? ':00' : '');
    }

    function readFilters() {
        var fd = new FormData(els.form);
        var out = {};
        fd.forEach(function (val, key) {
            val = String(val).trim();
            if (val) out[key] = val;
        });
        state.limit = parseInt(out.limit || '50', 10) || 50;
        delete out.limit;
        state.filters = out;
    }

    function queryString(page) {
        var p = new URLSearchParams();
        p.set('page', String(page));
        p.set('limit', String(state.limit));
        p.set('sort', state.sort);
        p.set('dir', state.dir);
        Object.keys(state.filters).forEach(function (k) {
            p.set(k, state.filters[k]);
        });
        return p.toString();
    }

    function updateSortHeaders() {
        document.querySelectorAll('#replays-table thead th[data-sort]').forEach(function (th) {
            var key = th.getAttribute('data-sort');
            var active = key === state.sort;
            th.classList.toggle('is-sorted', active);
            th.setAttribute('data-dir', active ? (state.dir === 'ASC' ? '▲' : '▼') : '');
        });
    }

    function playerLink(name, playerId) {
        var label = name || (playerId ? ('#' + playerId) : '—');
        // Only real names deep-link to insights scouting; bare #ids stay plain text.
        if (!name || String(name).charAt(0) === '#') {
            return esc(label);
        }
        var href = 'insights/index.php?opponent=' + encodeURIComponent(name);
        return '<a class="player-link" href="' + href + '" title="Scout ' + esc(name) + ' — insights &amp; past games">' +
            esc(label) + '</a>';
    }

    function renderRows(rows, append) {
        if (!append) {
            els.body.innerHTML = '';
            state.rowsById = {};
        }
        if (!rows.length && !append) {
            els.body.innerHTML = '<tr class="row-loading"><td colspan="13">No replays match these filters.</td></tr>';
            return;
        }
        var html = rows.map(function (r) {
            state.rowsById[r.ReplayId] = r;
            var p1 = r.Player1_Name || '';
            var p2 = r.Player2_Name || '';
            return (
                '<tr data-id="' + esc(r.ReplayId) + '">' +
                '<td class="id-cell">' + esc(r.ReplayId) + '</td>' +
                '<td>' + esc(fmtDate(r.Date_Played)) + '</td>' +
                '<td class="player-cell" title="' + esc(p1 || ('#' + (r.Player1_Id || '?'))) + '">' +
                    playerLink(p1, r.Player1_Id) + '</td>' +
                '<td><span class="race-pill ' + raceClass(r.Player1_Race) + '">' + esc(r.Player1_Race || '—') + '</span></td>' +
                '<td class="' + resultClass(r.Player1_Result) + '">' + esc(r.Player1_Result || '—') + '</td>' +
                '<td class="player-cell" title="' + esc(p2 || ('#' + (r.Player2_Id || '?'))) + '">' +
                    playerLink(p2, r.Player2_Id) + '</td>' +
                '<td><span class="race-pill ' + raceClass(r.Player2_Race) + '">' + esc(r.Player2_Race || '—') + '</span></td>' +
                '<td class="' + resultClass(r.Player2_Result) + '">' + esc(r.Player2_Result || '—') + '</td>' +
                '<td class="map-cell" title="' + esc(r.Map || '') + '">' + esc(r.Map || '—') + '</td>' +
                '<td>' + esc(r.Region || '—') + '</td>' +
                '<td>' + esc(r.GameType || '—') + '</td>' +
                '<td>' + esc(r.GameDuration || '—') + '</td>' +
                '<td class="text-end"><div class="mathison-actions">' +
                '<button type="button" class="btn btn-outline-info btn-view">View</button>' +
                '<button type="button" class="btn btn-outline-light btn-edit">Edit</button>' +
                '</div></td>' +
                '</tr>'
            );
        }).join('');
        els.body.insertAdjacentHTML('beforeend', html);
    }

    function updatePager(data) {
        state.page = data.page;
        state.pages = data.pages;
        state.total = data.total;
        var from = data.total === 0 ? 0 : (data.page - 1) * data.limit + 1;
        var to = Math.min(data.page * data.limit, data.total);
        var loaded = els.body.querySelectorAll('tr[data-id]').length;
        els.pageInfo.textContent =
            'Showing ' + from + '–' + to + ' of ' + data.total.toLocaleString() +
            (loaded > to - from + 1 && loaded > 0 ? ' · ' + loaded + ' rows loaded' : '') +
            ' · sorted by ' + data.sort + ' ' + data.dir;
        els.filterStatus.textContent = data.total.toLocaleString() + ' matches';
        els.totalBadge.textContent = data.total.toLocaleString() + ' rows';
        els.prev.disabled = data.page <= 1 || state.loading;
        els.next.disabled = data.page >= data.pages || data.pages === 0 || state.loading;
        if (data.page >= data.pages || data.pages === 0) {
            els.lazyStatus.textContent = data.total ? 'End of results' : '';
        } else {
            els.lazyStatus.textContent = 'Scroll for more…';
        }
        updateSortHeaders();
    }

    function loadPage(page, opts) {
        opts = opts || {};
        if (state.loading) return Promise.resolve();
        state.loading = true;
        state.mode = opts.append ? 'append' : 'page';
        if (!opts.append) {
            els.pageInfo.textContent = 'Loading…';
            els.lazyStatus.textContent = 'Loading…';
        } else {
            els.lazyStatus.textContent = 'Loading more…';
        }
        els.prev.disabled = true;
        els.next.disabled = true;

        return fetch(API + '?' + queryString(page))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Load failed');
                renderRows(res.data.rows, !!opts.append);
                updatePager(res.data);
            })
            .catch(function (err) {
                els.pageInfo.textContent = 'Error: ' + err.message;
                els.lazyStatus.textContent = '';
                if (!opts.append) {
                    els.body.innerHTML = '<tr class="row-loading"><td colspan="13">' + esc(err.message) + '</td></tr>';
                }
            })
            .finally(function () {
                state.loading = false;
                els.prev.disabled = state.page <= 1;
                els.next.disabled = state.page >= state.pages || state.pages === 0;
            });
    }

    function fillSelect(sel, values, valueKey, labelFn) {
        var keep = sel.value;
        while (sel.options.length > 1) sel.remove(1);
        values.forEach(function (item) {
            var opt = document.createElement('option');
            if (typeof item === 'object') {
                opt.value = item[valueKey];
                opt.textContent = labelFn ? labelFn(item) : item[valueKey];
            } else {
                opt.value = item;
                opt.textContent = item;
            }
            sel.appendChild(opt);
        });
        if (keep) sel.value = keep;
    }

    function loadMeta() {
        return fetch(API + '?action=meta')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) return;
                var d = res.data;
                fillSelect(document.getElementById('Region'), d.regions || []);
                fillSelect(document.getElementById('GameType'), d.gameTypes || []);
                fillSelect(document.getElementById('race'), d.races || []);
                fillSelect(document.getElementById('Map'), d.maps || [], 'Map', function (m) {
                    return m.Map + ' (' + m.c + ')';
                });
                var sw = document.getElementById('startWorkers');
                // Options are baked into the HTML; refresh labels from API when present.
                if (sw && d.worker_eras && d.worker_eras.length) {
                    var keep = sw.value;
                    while (sw.options.length > 1) sw.remove(1);
                    d.worker_eras.forEach(function (era) {
                        var opt = document.createElement('option');
                        opt.value = String(era.workers);
                        opt.textContent = era.label;
                        sw.appendChild(opt);
                    });
                    if (keep) sw.value = keep;
                }
                if (d.total != null) {
                    els.totalBadge.textContent = Number(d.total).toLocaleString() + ' rows';
                }
            });
    }

    function setForm(row) {
        var fields = [
            'ReplayId', 'UnixTimestamp', 'Player1_Id', 'Player2_Id',
            'Player1_PickRace', 'Player2_PickRace', 'Player1_Race', 'Player2_Race',
            'Player1_Result', 'Player2_Result', 'Map', 'Region', 'GameType',
            'GameDuration', 'Player_Comments', 'Replay_Summary'
        ];
        fields.forEach(function (f) {
            var el = document.getElementById('f-' + f);
            if (!el) return;
            el.value = row && row[f] != null ? row[f] : '';
        });
        document.getElementById('f-Date_Played').value = toLocalInput(row && row.Date_Played);
        document.getElementById('f-Date_Uploaded').value = toLocalInput(row && row.Date_Uploaded);
        var names = '';
        if (row) {
            var p1n = row.Player1_Name || '';
            var p2n = row.Player2_Name || '';
            names = 'Players: ' +
                playerLink(p1n, row.Player1_Id) + ' (#' + esc(row.Player1_Id || '?') + ') vs ' +
                playerLink(p2n, row.Player2_Id) + ' (#' + esc(row.Player2_Id || '?') + ')';
            if (p1n || p2n) {
                names += ' <span class="stat-sub">— click a name for scouting insights</span>';
            }
        }
        els.modalNames.innerHTML = names;
        els.modalError.hidden = true;
        els.modalError.textContent = '';
    }

    function collectForm() {
        var form = document.getElementById('replay-form');
        var data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.disabled) return;
            if (el.name === 'Date_Played' || el.name === 'Date_Uploaded') {
                data[el.name] = fromLocalInput(el.value);
                return;
            }
            data[el.name] = el.value;
        });
        if (data.UnixTimestamp === '') data.UnixTimestamp = null;
        if (data.Player1_Id === '') data.Player1_Id = null;
        if (data.Player2_Id === '') data.Player2_Id = null;
        return data;
    }

    function setFormReadonly(ro) {
        var form = document.getElementById('replay-form');
        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
                el.readOnly = !!ro;
                el.disabled = false;
            }
        });
    }

    function openCreate() {
        editingId = null;
        els.modalTitle.textContent = 'New replay';
        setForm({
            UnixTimestamp: Math.floor(Date.now() / 1000),
            GameType: '1v1',
            Region: 'us'
        });
        setFormReadonly(false);
        els.btnDelete.hidden = true;
        els.btnSave.hidden = false;
        modal.show();
    }

    function openEdit(id, readOnly) {
        editingId = id;
        els.modalTitle.textContent = (readOnly ? 'View' : 'Edit') + ' replay #' + id;
        els.btnDelete.hidden = !!readOnly;
        els.btnSave.hidden = !!readOnly;
        setFormReadonly(!!readOnly);
        setForm(state.rowsById[id] || { ReplayId: id });
        modal.show();

        fetch(API + '?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Failed to load');
                setForm(res.data);
                setFormReadonly(!!readOnly);
                state.rowsById[id] = Object.assign({}, state.rowsById[id] || {}, res.data);
            })
            .catch(function (err) {
                els.modalError.hidden = false;
                els.modalError.textContent = err.message;
            });
    }

    els.form.addEventListener('submit', function (e) {
        e.preventDefault();
        readFilters();
        state.page = 1;
        loadPage(1);
    });

    // Auto-apply when era (or other selects) change — don't require Apply click.
    ['startWorkers', 'Map', 'Region', 'GameType', 'race', 'result', 'limit'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', function () {
            readFilters();
            state.page = 1;
            loadPage(1);
        });
    });

    els.btnReset.addEventListener('click', function () {
        els.form.reset();
        document.getElementById('limit').value = '50';
        readFilters();
        state.sort = 'ReplayId';
        state.dir = 'DESC';
        loadPage(1);
    });

    els.prev.addEventListener('click', function () {
        if (state.page > 1) loadPage(state.page - 1);
    });

    els.next.addEventListener('click', function () {
        if (state.page < state.pages) loadPage(state.page + 1);
    });

    els.btnCreate.addEventListener('click', openCreate);

    document.querySelectorAll('#replays-table thead th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var key = th.getAttribute('data-sort');
            if (state.sort === key) {
                state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.sort = key;
                state.dir = 'DESC';
            }
            loadPage(1);
        });
    });

    els.body.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        var id = parseInt(tr.getAttribute('data-id'), 10);
        if (btn.classList.contains('btn-view')) openEdit(id, true);
        if (btn.classList.contains('btn-edit')) openEdit(id, false);
    });

    // Double-click row to view
    els.body.addEventListener('dblclick', function (e) {
        var tr = e.target.closest('tr[data-id]');
        if (!tr) return;
        openEdit(parseInt(tr.getAttribute('data-id'), 10), true);
    });

    els.btnSave.addEventListener('click', function () {
        var data = collectForm();
        var isCreate = !editingId;
        if (isCreate) {
            data.action = 'create';
            delete data.ReplayId;
        } else {
            data.action = 'update';
            data.ReplayId = editingId;
        }
        els.btnSave.disabled = true;
        els.modalError.hidden = true;

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Save failed');
                modal.hide();
                readFilters();
                loadPage(isCreate ? 1 : state.page);
            })
            .catch(function (err) {
                els.modalError.hidden = false;
                els.modalError.textContent = err.message;
            })
            .finally(function () {
                els.btnSave.disabled = false;
            });
    });

    els.btnDelete.addEventListener('click', function () {
        if (!editingId) return;
        if (!window.confirm('Delete replay #' + editingId + '? This cannot be undone.')) return;
        els.btnDelete.disabled = true;
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ReplayId: editingId })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Delete failed');
                modal.hide();
                loadPage(state.page);
            })
            .catch(function (err) {
                els.modalError.hidden = false;
                els.modalError.textContent = err.message;
            })
            .finally(function () {
                els.btnDelete.disabled = false;
            });
    });

    // Infinite scroll: append next page when sentinel enters the table viewport
    if ('IntersectionObserver' in window && els.sentinel) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting || state.loading) return;
                if (state.page >= state.pages || state.pages === 0) return;
                loadPage(state.page + 1, { append: true });
            });
        }, { root: document.querySelector('.mathison-table-wrap'), rootMargin: '160px', threshold: 0 });
        io.observe(els.sentinel);
    }

    // Debounced search on Enter already via form; also live debounce for q
    var qTimer = null;
    document.getElementById('q').addEventListener('input', function () {
        clearTimeout(qTimer);
        qTimer = setTimeout(function () {
            readFilters();
            loadPage(1);
        }, 400);
    });

    readFilters();
    // Deep-link: ?id=123 opens that replay; ?playerA=Name / ?q= / ?startWorkers= apply filters.
    var boot = new URLSearchParams(window.location.search);
    if (boot.get('q')) document.getElementById('q').value = boot.get('q');
    if (boot.get('playerA')) document.getElementById('playerA').value = boot.get('playerA');
    if (boot.get('playerB')) document.getElementById('playerB').value = boot.get('playerB');
    if (boot.get('startWorkers')) document.getElementById('startWorkers').value = boot.get('startWorkers');
    readFilters();

    var openId = parseInt(boot.get('id') || boot.get('ReplayId') || '0', 10);

    loadMeta().finally(function () {
        loadPage(1).then(function () {
            if (openId > 0) openEdit(openId, true);
        });
    });
})();
