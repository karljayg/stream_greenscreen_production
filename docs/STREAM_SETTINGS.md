# Stream settings layers

Three layers (load order):

1. **Show broadcast defaults** — `data/stream_broadcast.json` via `stream_broadcast.php`  
   Event title, video buttons, break timer, YT crop, VDO director URL, SC2 effects, player intro names.  
   **Publish show defaults** (Save/Load section) or per-section publish on Video Buttons.

2. **Per-user layout** — `data/stream_user_preferences_<user>.json` via `stream_user_preferences.php`  
   Layer order, logo/SC2/VDO positions, volume, TLS banner position, chroma, production files mode, music editor JSON.  
   **Save my layout** or automatic quiet save after dragging panels.

3. **This-tab overrides** — `sessionStorage` (`stream_session_bc_*`)  
   Edits to show-default fields apply only until the tab closes or **Reset show defaults in this tab**.

Legacy `data/settings_<user>.json` is still read once when migrating layout into user preferences.

## Revert

- Code: `git revert` the settings-layer commit or checkout previous `index.php` / remove `js/stream_settings.js`.
- Server data: restore from `data/backups/<timestamp>/` created by deploy script.
- Old behavior: delete `stream_broadcast.json` and use `settings.php` only (not recommended).
