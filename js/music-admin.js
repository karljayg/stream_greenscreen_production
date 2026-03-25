// Music Admin — visual editor for MX_TRACKS (mood→songs) and MX_SCENE_MAP (scene→moods).
// Opens as an overlay modal. Requires jQuery UI sortable (already loaded on page).
// Globals consumed: window.MX_TRACKS, window.MX_SCENE_MAP, window.MX_MUSIC_FILES
// Globals called:   window.mxBuildGrid  (exposed by music-player.js)

(function () {
    'use strict';

    // ─── Helpers ───────────────────────────────────────────────────────────────
    function deepClone(obj) { return JSON.parse(JSON.stringify(obj)); }

    function labelFromKey(key) {
        return String(key).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function escH(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Working-copy state ────────────────────────────────────────────────────
    var workTracks   = {};
    var workSceneMap = {};
    var musicFiles   = [];   // filenames present in music/ (from window.MX_MUSIC_FILES)

    // ─── Inject CSS (once) ─────────────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('mx-admin-styles')) return;
        var s = document.createElement('style');
        s.id = 'mx-admin-styles';
        s.textContent = [
            /* overlay + modal shell */
            '#mx-admin-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);z-index:200000;display:none;justify-content:center;align-items:flex-start;padding:40px 8px 20px;box-sizing:border-box;overflow-y:auto}',
            '#mx-admin-modal{background:#f2f4f8;border-radius:8px;width:760px;max-width:100%;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,.45)}',
            /* header */
            '.mx-ah{background:#2d3748;color:#f0f4ff;padding:10px 14px;display:flex;align-items:center;gap:8px;border-radius:8px 8px 0 0;flex-shrink:0}',
            '.mx-ah h2{margin:0;font-size:0.9rem;font-weight:800;letter-spacing:.06em;flex:1}',
            '.mx-ah-close{background:transparent;border:none;color:#f0f4ff;font-size:1.25rem;cursor:pointer;padding:1px 7px;line-height:1;border-radius:3px}',
            '.mx-ah-close:hover{background:rgba(255,255,255,.15)}',
            /* tabs */
            '.mx-tabs{display:flex;background:#e2e8f0;border-bottom:2px solid #b8c4d4;flex-shrink:0}',
            '.mx-tab{background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;padding:8px 20px;font-size:0.82rem;font-weight:700;color:#475569;cursor:pointer}',
            '.mx-tab.active{color:#1e3a8a;border-bottom-color:#3b82f6;background:#f2f4f8}',
            /* body */
            '.mx-body{flex:1;overflow-y:auto;padding:12px 14px;min-height:200px;max-height:62vh}',
            /* footer */
            '.mx-foot{background:#e2e8f0;border-top:1px solid #b8c4d4;padding:8px 14px;display:flex;align-items:center;gap:8px;flex-shrink:0;border-radius:0 0 8px 8px}',
            '.mx-foot button{font-size:0.8rem;padding:5px 13px}',
            '#mx-admin-status{font-size:0.78rem;margin-left:4px}',
            /* shared small buttons */
            '.mx-btn{font-size:0.72rem;padding:3px 8px;border-radius:3px;cursor:pointer;border:1px solid #b8c4d4;background:#e2e8f0;color:#1e293b}',
            '.mx-btn:hover{background:#d1d9e8}',
            '.mx-btn-add{background:#dcfce7!important;border-color:#86efac!important;color:#166534!important}',
            '.mx-btn-add:hover{background:#bbf7d0!important}',
            '.mx-btn-del{background:#fee2e2!important;border-color:#fca5a5!important;color:#b91c1c!important}',
            '.mx-btn-del:hover{background:#fecaca!important}',
            '.mx-add-row{margin-bottom:10px;display:flex;align-items:center;gap:6px}',
            /* Scene tab */
            '.mx-scene-row{background:#fff;border:1px solid #b8c4d4;border-radius:6px;margin-bottom:8px;padding:8px 10px}',
            '.mx-scene-top{display:flex;align-items:center;gap:6px;margin-bottom:6px}',
            '.mx-scene-key{font-size:0.78rem;font-family:Consolas,monospace;border:1px solid #b8c4d4;border-radius:3px;padding:2px 6px;background:#f8fafc;width:140px}',
            '.mx-chips{display:flex;flex-wrap:wrap;gap:4px;min-height:28px;border:1px dashed #b8c4d4;border-radius:4px;padding:4px 5px;background:#f8fafc}',
            '.mx-chip{display:inline-flex;align-items:center;gap:3px;background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;border-radius:3px;padding:2px 7px;font-size:0.71rem;font-weight:600;cursor:grab;user-select:none}',
            '.mx-chip:active{cursor:grabbing}',
            '.mx-chip-x{background:none;border:none;color:#6b7280;cursor:pointer;font-size:0.82rem;padding:0 1px;line-height:1}',
            '.mx-chip-x:hover{color:#dc2626}',
            '.mx-scene-act{display:flex;align-items:center;gap:6px;margin-top:6px}',
            '.mx-scene-act select{font-size:0.75rem;padding:2px 5px;border:1px solid #b8c4d4;border-radius:3px;background:#fff;max-width:180px}',
            /* Mood tab */
            '.mx-mood-card{background:#fff;border:1px solid #b8c4d4;border-radius:6px;margin-bottom:7px}',
            '.mx-mood-hdr{display:flex;align-items:center;gap:6px;padding:7px 10px;border-bottom:1px solid transparent;cursor:pointer;user-select:none;border-radius:6px}',
            '.mx-mood-hdr.open{border-bottom-color:#e2e8f0;border-radius:6px 6px 0 0}',
            '.mx-mood-hdr:hover{background:#f1f5fb}',
            '.mx-caret{font-size:0.65rem;color:#94a3b8;transition:transform .15s;flex-shrink:0}',
            '.mx-caret.open{transform:rotate(90deg)}',
            '.mx-mood-key{font-size:0.78rem;font-family:Consolas,monospace;border:1px solid #b8c4d4;border-radius:3px;padding:2px 6px;background:#f8fafc;width:160px}',
            '.mx-mood-body{padding:8px 10px;display:none}',
            '.mx-mood-body.open{display:block}',
            '.mx-song-list{list-style:none;margin:0 0 7px;padding:0}',
            '.mx-song-item{display:flex;align-items:center;gap:6px;padding:3px 5px;border-radius:3px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:3px;cursor:grab;font-size:0.71rem;color:#334155}',
            '.mx-song-item:active{cursor:grabbing}',
            '.mx-grip{color:#b0bcc8;flex-shrink:0;font-size:0.68rem;letter-spacing:-.5px}',
            '.mx-song-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
            '.mx-song-x{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:0.82rem;padding:0 2px;flex-shrink:0;line-height:1}',
            '.mx-song-x:hover{color:#dc2626}',
            '.mx-song-act{display:flex;align-items:center;gap:6px;flex-wrap:wrap}',
            '.mx-song-act select{font-size:0.72rem;padding:2px 5px;border:1px solid #b8c4d4;border-radius:3px;background:#fff;min-width:0;flex:1}',
            '.mx-upload-inp{display:none}',
        ].join('');
        document.head.appendChild(s);
    }

    // ─── Build modal DOM (once) ────────────────────────────────────────────────
    function buildDOM() {
        if (document.getElementById('mx-admin-overlay')) return;

        var overlay = document.createElement('div');
        overlay.id = 'mx-admin-overlay';
        overlay.innerHTML = [
            '<div id="mx-admin-modal">',
            '  <div class="mx-ah">',
            '    <h2>&#9836; Music Admin</h2>',
            '    <button class="mx-ah-close" id="mx-admin-close" title="Close">&times;</button>',
            '  </div>',
            '  <div class="mx-tabs">',
            '    <button class="mx-tab active" data-tab="scenes">Scene &#8594; Moods</button>',
            '    <button class="mx-tab" data-tab="moods">Mood &#8594; Songs</button>',
            '  </div>',
            '  <div class="mx-body">',
            '    <div id="mx-tab-scenes"></div>',
            '    <div id="mx-tab-moods" style="display:none"></div>',
            '  </div>',
            '  <div class="mx-foot">',
            '    <button id="mx-admin-apply">Apply</button>',
            '    <button id="mx-admin-save">Save to Server</button>',
            '    <button id="mx-admin-promote" title="Save your config AND overwrite the shared global defaults that other users see" style="margin-left:8px">&#x2B06; Promote to Global</button>',
            '    <span id="mx-admin-status"></span>',
            '  </div>',
            '</div>',
        ].join('');
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
        document.getElementById('mx-admin-close').addEventListener('click', closeModal);

        // Tab switching
        overlay.querySelectorAll('.mx-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                overlay.querySelectorAll('.mx-tab').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                var which = tab.dataset.tab;
                document.getElementById('mx-tab-scenes').style.display = (which === 'scenes') ? '' : 'none';
                document.getElementById('mx-tab-moods').style.display  = (which === 'moods')  ? '' : 'none';
                // When returning to Scenes tab, refresh mood dropdowns in case moods were added
                if (which === 'scenes') refreshSceneMoodDropdowns();
            });
        });

        // Apply
        document.getElementById('mx-admin-apply').addEventListener('click', function () {
            applyChanges();
            setStatus('Applied!', false, 2000);
        });

        // Save to Server (per-user)
        document.getElementById('mx-admin-save').addEventListener('click', function () {
            applyChanges();
            saveConfig('mood_songs', window.MX_TRACKS, false, function (ok, err) {
                if (!ok) { setStatus('Mood save error: ' + err, true); return; }
                saveConfig('scene_mood_map', window.MX_SCENE_MAP, false, function (ok2, err2) {
                    setStatus(ok2 ? 'Saved to server!' : 'Scene save error: ' + err2, !ok2, 3000);
                });
            });
        });

        // Promote to Global — saves per-user AND overwrites the shared global defaults
        document.getElementById('mx-admin-promote').addEventListener('click', function () {
            if (!confirm('Overwrite the global defaults with your current config?\nAll users without a personal override will see your version.')) return;
            applyChanges();
            saveConfig('mood_songs', window.MX_TRACKS, true, function (ok, err) {
                if (!ok) { setStatus('Mood promote error: ' + err, true); return; }
                saveConfig('scene_mood_map', window.MX_SCENE_MAP, true, function (ok2, err2) {
                    setStatus(ok2 ? 'Promoted to global!' : 'Scene promote error: ' + err2, !ok2, 3000);
                });
            });
        });
    }

    // ─── Open ──────────────────────────────────────────────────────────────────
    function openModal() {
        injectStyles();
        buildDOM();
        workTracks   = deepClone(window.MX_TRACKS   || {});
        workSceneMap = deepClone(window.MX_SCENE_MAP || {});
        musicFiles   = (window.MX_MUSIC_FILES || []).slice();
        renderScenes();
        renderMoods();
        document.getElementById('mx-admin-overlay').style.display = 'flex';
        // Default to Scenes tab active
        document.querySelectorAll('.mx-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelector('.mx-tab[data-tab="scenes"]').classList.add('active');
        document.getElementById('mx-tab-scenes').style.display = '';
        document.getElementById('mx-tab-moods').style.display  = 'none';
    }

    function closeModal() {
        var overlay = document.getElementById('mx-admin-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    // ─── Collect current UI state → workTracks / workSceneMap ─────────────────
    function collectState() {
        var newSceneMap = {};
        document.querySelectorAll('#mx-tab-scenes .mx-scene-row').forEach(function (row) {
            var key = row.querySelector('.mx-scene-key').value.trim();
            if (!key) return;
            var moods = [];
            row.querySelectorAll('.mx-chip').forEach(function (chip) { moods.push(chip.dataset.mood); });
            newSceneMap[key] = moods;
        });
        workSceneMap = newSceneMap;

        var newTracks = {};
        document.querySelectorAll('#mx-tab-moods .mx-mood-card').forEach(function (card) {
            var key = card.querySelector('.mx-mood-key').value.trim();
            if (!key) return;
            var songs = [];
            card.querySelectorAll('.mx-song-item').forEach(function (item) { songs.push(item.dataset.file); });
            newTracks[key] = songs;
        });
        workTracks = newTracks;
    }

    function applyChanges() {
        collectState();
        window.MX_TRACKS   = deepClone(workTracks);
        window.MX_SCENE_MAP = deepClone(workSceneMap);
        if (typeof window.mxBuildGrid === 'function') window.mxBuildGrid();
    }

    // ─── Status line ───────────────────────────────────────────────────────────
    function setStatus(msg, isError, clearMs) {
        var el = document.getElementById('mx-admin-status');
        if (!el) return;
        el.textContent = msg;
        el.style.color = isError ? '#b91c1c' : '#166534';
        if (clearMs) {
            setTimeout(function () { if (el.textContent === msg) el.textContent = ''; }, clearMs);
        }
    }

    // ─── Save to server ────────────────────────────────────────────────────────
    function saveConfig(which, data, promote, cb) {
        var url = 'save_music_config.php?which=' + which + (promote ? '&promote=1' : '');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        }).then(function (r) { return r.json(); })
          .then(function (res) { cb(!!res.ok, res.error || res.warn || ''); })
          .catch(function (e) { cb(false, e.message); });
    }

    // ─── SCENE TAB ─────────────────────────────────────────────────────────────
    function renderScenes() {
        var container = document.getElementById('mx-tab-scenes');
        container.innerHTML = '';

        var addRow = document.createElement('div');
        addRow.className = 'mx-add-row';
        var addBtn = document.createElement('button');
        addBtn.className = 'mx-btn mx-btn-add';
        addBtn.textContent = '+ Add Scene';
        addBtn.addEventListener('click', function () { addSceneRow('new_scene', [], true); });
        addRow.appendChild(addBtn);
        container.appendChild(addRow);

        Object.keys(workSceneMap).forEach(function (k) { addSceneRow(k, workSceneMap[k]); });
    }

    function addSceneRow(sceneKey, moods, prepend) {
        var container = document.getElementById('mx-tab-scenes');
        var allMoodKeys = Object.keys(workTracks);

        var row = document.createElement('div');
        row.className = 'mx-scene-row';

        // Top bar: key input + delete
        var top = document.createElement('div');
        top.className = 'mx-scene-top';
        top.innerHTML = [
            '<span style="font-size:0.7rem;color:#94a3b8;flex-shrink:0">Key:</span>',
            '<input class="mx-scene-key" type="text" value="' + escH(sceneKey) + '" placeholder="scene_key">',
            '<button class="mx-btn mx-btn-del" style="margin-left:auto">&#x2715; Delete Scene</button>',
        ].join('');
        top.querySelector('.mx-btn-del').addEventListener('click', function () { row.remove(); });

        // Chip area
        var chipsEl = document.createElement('div');
        chipsEl.className = 'mx-chips';
        moods.forEach(function (m) { addChip(chipsEl, m); });

        // Add-mood actions
        var act = document.createElement('div');
        act.className = 'mx-scene-act';
        var sel = document.createElement('select');
        sel.innerHTML = '<option value="">— add mood —</option>' +
            allMoodKeys.map(function (m) {
                return '<option value="' + escH(m) + '">' + escH(labelFromKey(m)) + ' (' + escH(m) + ')</option>';
            }).join('');
        var addMoodBtn = document.createElement('button');
        addMoodBtn.className = 'mx-btn mx-btn-add';
        addMoodBtn.textContent = '+ Add';
        addMoodBtn.addEventListener('click', function () {
            if (!sel.value) return;
            addChip(chipsEl, sel.value);
            sel.value = '';
        });
        act.appendChild(sel);
        act.appendChild(addMoodBtn);

        row.appendChild(top);
        row.appendChild(chipsEl);
        row.appendChild(act);

        $(chipsEl).sortable({ items: '.mx-chip', tolerance: 'pointer' });

        if (prepend) {
            // Insert after the add-row button (index 0), before the first existing scene
            var addRow = container.querySelector('.mx-add-row');
            container.insertBefore(row, addRow ? addRow.nextSibling : null);
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var keyInput = row.querySelector('.mx-scene-key');
            keyInput.select();
            keyInput.focus();
        } else {
            container.appendChild(row);
        }
    }

    // Update all scene-tab mood dropdowns to reflect current moods in the Mood tab
    function refreshSceneMoodDropdowns() {
        var moodKeys = [];
        document.querySelectorAll('#mx-tab-moods .mx-mood-key').forEach(function (inp) {
            var k = inp.value.trim();
            if (k) moodKeys.push(k);
        });
        document.querySelectorAll('#mx-tab-scenes .mx-scene-act select').forEach(function (sel) {
            var prev = sel.value;
            sel.innerHTML = '<option value="">— add mood —</option>' +
                moodKeys.map(function (m) {
                    return '<option value="' + escH(m) + '">' + escH(labelFromKey(m)) + ' (' + escH(m) + ')</option>';
                }).join('');
            sel.value = prev;
        });
    }

    function addChip(container, moodKey) {
        var chip = document.createElement('span');
        chip.className = 'mx-chip';
        chip.dataset.mood = moodKey;
        chip.innerHTML = escH(labelFromKey(moodKey)) + ' <button class="mx-chip-x" title="Remove">&times;</button>';
        chip.querySelector('.mx-chip-x').addEventListener('click', function () { chip.remove(); });
        container.appendChild(chip);
    }

    // ─── MOOD TAB ──────────────────────────────────────────────────────────────
    function renderMoods() {
        var container = document.getElementById('mx-tab-moods');
        container.innerHTML = '';

        var addRow = document.createElement('div');
        addRow.className = 'mx-add-row';
        var addBtn = document.createElement('button');
        addBtn.className = 'mx-btn mx-btn-add';
        addBtn.textContent = '+ Add Mood';
        addBtn.addEventListener('click', function () { addMoodCard('new_mood', [], true); });
        addRow.appendChild(addBtn);
        container.appendChild(addRow);

        Object.keys(workTracks).forEach(function (k) { addMoodCard(k, workTracks[k]); });
    }

    function addMoodCard(moodKey, songs, prepend) {
        var container = document.getElementById('mx-tab-moods');

        var card = document.createElement('div');
        card.className = 'mx-mood-card';

        // Header
        var hdr = document.createElement('div');
        hdr.className = 'mx-mood-hdr';
        hdr.innerHTML = [
            '<span class="mx-caret">&#9658;</span>',
            '<input class="mx-mood-key" type="text" value="' + escH(moodKey) + '" placeholder="mood_key" title="Mood key (used in scene map)">',
            '<span class="mx-song-count" style="font-size:0.7rem;color:#94a3b8;margin-left:2px">' + songs.length + ' song' + (songs.length !== 1 ? 's' : '') + '</span>',
            '<button class="mx-btn mx-btn-del" style="margin-left:auto;flex-shrink:0">&#x2715;</button>',
        ].join('');

        // Body
        var body = document.createElement('div');
        body.className = 'mx-mood-body';

        var songList = document.createElement('ul');
        songList.className = 'mx-song-list';
        songs.forEach(function (f) { addSongItem(songList, hdr, f); });
        body.appendChild(songList);

        // Song actions row
        var actRow = document.createElement('div');
        actRow.className = 'mx-song-act';
        var fileOptions = musicFiles.map(function (f) {
            return '<option value="' + escH(f) + '">' + escH(f) + '</option>';
        }).join('');
        actRow.innerHTML = [
            '<select class="mx-song-sel">',
            '  <option value="">— add existing —</option>',
            fileOptions,
            '</select>',
            '<button class="mx-btn mx-btn-add mx-add-song-btn">+</button>',
            '<button class="mx-btn mx-upload-btn" title="Upload new audio file">&#x2191; Upload</button>',
            '<input type="file" class="mx-upload-inp" accept=".mp3,.wav,.ogg,.flac,.m4a">',
        ].join('');
        body.appendChild(actRow);

        card.appendChild(hdr);
        card.appendChild(body);

        // Toggle collapse/expand (ignore clicks on inputs and buttons)
        hdr.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
            var open = body.classList.toggle('open');
            hdr.classList.toggle('open', open);
            hdr.querySelector('.mx-caret').classList.toggle('open', open);
        });

        // Delete mood
        hdr.querySelector('.mx-btn-del').addEventListener('click', function () { card.remove(); });

        // Add existing song from dropdown
        actRow.querySelector('.mx-add-song-btn').addEventListener('click', function () {
            var sel = actRow.querySelector('.mx-song-sel');
            if (!sel.value) return;
            addSongItem(songList, hdr, sel.value);
            sel.value = '';
        });

        // Upload
        var uploadBtn = actRow.querySelector('.mx-upload-btn');
        var uploadInp = actRow.querySelector('.mx-upload-inp');
        uploadBtn.addEventListener('click', function () { uploadInp.click(); });
        uploadInp.addEventListener('change', function () {
            if (!uploadInp.files.length) return;
            var fd = new FormData();
            fd.append('audio_file', uploadInp.files[0]);
            uploadBtn.textContent = 'Uploading\u2026';
            uploadBtn.disabled = true;
            fetch('upload_music.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    uploadBtn.textContent = '\u2191 Upload';
                    uploadBtn.disabled = false;
                    if (res.ok) {
                        var fname = res.filename;
                        // Register in the in-memory file list and all dropdowns
                        if (musicFiles.indexOf(fname) === -1) {
                            musicFiles.push(fname);
                            document.querySelectorAll('.mx-song-sel').forEach(function (sel) {
                                var opt = document.createElement('option');
                                opt.value = fname;
                                opt.textContent = fname;
                                sel.appendChild(opt);
                            });
                        }
                        addSongItem(songList, hdr, fname);
                        setStatus('Uploaded: ' + fname, false, 3000);
                    } else {
                        setStatus('Upload failed: ' + (res.error || 'unknown'), true, 4000);
                    }
                })
                .catch(function (e) {
                    uploadBtn.textContent = '\u2191 Upload';
                    uploadBtn.disabled = false;
                    setStatus('Upload error: ' + e.message, true, 4000);
                });
            uploadInp.value = '';
        });

        $(songList).sortable({ items: '.mx-song-item', handle: '.mx-grip', tolerance: 'pointer' });

        if (prepend) {
            // Insert after the add-row button, before the first existing mood card
            var addRow = container.querySelector('.mx-add-row');
            container.insertBefore(card, addRow ? addRow.nextSibling : null);
            // Auto-expand so the user can immediately edit it
            body.classList.add('open');
            hdr.classList.add('open');
            hdr.querySelector('.mx-caret').classList.add('open');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            var keyInput = hdr.querySelector('.mx-mood-key');
            keyInput.select();
            keyInput.focus();
        } else {
            container.appendChild(card);
        }
    }

    function addSongItem(list, hdr, filename) {
        var li = document.createElement('li');
        li.className = 'mx-song-item';
        li.dataset.file = filename;
        li.innerHTML = [
            '<span class="mx-grip">&#x2630;</span>',
            '<span class="mx-song-name" title="' + escH(filename) + '">' + escH(filename) + '</span>',
            '<button class="mx-song-x" title="Remove">&times;</button>',
        ].join('');
        li.querySelector('.mx-song-x').addEventListener('click', function () {
            var parentList = li.parentElement;
            li.remove();
            if (hdr && parentList) updateSongCount(hdr, parentList);
        });
        list.appendChild(li);
        updateSongCount(hdr, list);
    }

    function updateSongCount(hdr, songList) {
        var n = songList ? songList.querySelectorAll('.mx-song-item').length : 0;
        var span = hdr.querySelector('.mx-song-count');
        if (span) span.textContent = n + ' song' + (n !== 1 ? 's' : '');
    }

    // ─── Public ────────────────────────────────────────────────────────────────
    window.mxAdminOpen = openModal;
})();
