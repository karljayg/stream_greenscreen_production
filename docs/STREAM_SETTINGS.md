# Stream Production — settings design

How the tool separates **show-wide defaults** (same FSL broadcast standard for every caster) from **personal layout** (monitor size, panel positions, layer order) and **one-night tweaks** (temporary overrides in a single browser tab).

Introduced in commit `8821a36` (`js/stream_settings.js`, `stream_broadcast.php`, `stream_user_preferences.php`).

---

## Why three layers?

Multiple producers share one production URL. They need:

- The same **INTRO / FEATURE / BREAK** URLs, **event title**, break timer, intro name list, etc. most of the time.
- Freedom to fix a typo or run a special segment **for this session** without overwriting the shared standard.
- Their own **logo positions**, **SC2 panel** size, **layer order**, and volume without clobbering someone else’s layout.

Putting everything in one “Save to server” blob (`settings.php` → `data/settings_<user>.json`) mixed these concerns and did not enforce show defaults across users.

---

## The three layers

| Layer | Where it lives | When it applies | Who it affects |
| ----- | -------------- | --------------- | -------------- |
| **1. Show broadcast defaults** | Server: `data/stream_broadcast.json` | Every **new tab** / fresh load | All logged-in users |
| **2. Per-user layout** | Server: `data/stream_user_preferences_<user>.json` | Every load for that username | That user only |
| **3. Tab session overrides** | Browser: `sessionStorage` keys `stream_session_bc_*` | Until tab closes or **Reset show defaults in this tab** | That tab only |

### Load order (what wins)

On page load (`StreamSettings.bootstrap()` at the bottom of `index.php`):

1. Apply **user layout** from `stream_user_preferences.php` (or migrate layout fields from legacy `data/settings_<user>.json` if the new file does not exist yet).
2. Apply **show broadcast defaults** from `stream_broadcast.php`.
3. For each broadcast field, if a **session override** exists in `sessionStorage`, use it instead of step 2.

So: **session > server broadcast > HTML defaults** for show fields; layout comes from step 1 only.

`localStorage` still caches some values (e.g. video buttons) for convenience; a full reload after publish follows the order above.

---

## Layer 1 — Show broadcast defaults

**API:** `stream_broadcast.php`  
**File:** `data/stream_broadcast.json` (gitignored on dev; lives on production server)

**GET** — `{ ok, broadcast: { … } }`  
**POST** — merge one section or full object:

```json
{ "section": "eventTitle", "data": "SEASON 12" }
```

```json
{ "section": "ytIframeVideos", "data": [ { "label", "url", "vol" }, … ] }
```

```json
{ "section": "_all", "broadcast": { … all sections … } }
```

### Fields in `broadcast`

| Key | UI location | Notes |
| --- | ----------- | ----- |
| `eventTitle` | Event title input / S10 banner | Shared “SEASON 12” style copy |
| `ytIframeVideos` | Settings → Video Buttons | INTRO / FEATURE / BREAK |
| `breakSettings` | Break timer settings | min, sec, message |
| `ytCrop` | YT Video crop | Shared window crop pixels |
| `vdoDirectorUrl` | VDO director URL | Ninja room link |
| `sc2ButtonEffects` | SC2 effect dropdowns | Quick button mappings |
| `playerIntros` | Player intros list (10 slots) | Default names in forms |

**Not** in this file: layer order, logo coordinates, which scene is **on air**, scoreboard data, music maps (see below).

### Publishing (admin / lead producer)

| Control | Effect |
| ------- | ------ |
| **Publish show defaults** | Save/Load section — writes all current show fields to `stream_broadcast.json` |
| **Save video buttons for all users** | Video Buttons section — POST `section: ytIframeVideos` only |

Publishing does **not** write per-user layout files.

### Legacy merge

If `stream_broadcast.json` does not exist yet, GET may still read video slots from old `data/yt_iframe_video_settings.json` once when building the response.

---

## Layer 2 — Per-user layout

**API:** `stream_user_preferences.php`  
**File:** `data/stream_user_preferences_<username>.json`

**GET** — `{ ok, preferences: { … } }`  
**POST** — partial merge; only allowed keys are stored.

### Allowed preference keys

- `volume`, `layerOrder`, `logos` (checkboxes + positions, SC2/VDO panel rects)
- `tlsScorePos`, `tlsScoreEnabled`
- `musicMode`, `musicVol`, `musicFade`
- `productionFiles` (local vs remote base URL)
- `chromaKey`
- `sceneMoodMap`, `moodSongs`, `sceneStages` (when saved from the main page export path)

### Saving

| Control | Effect |
| ------- | ------ |
| **Save my layout** | Save/Load section — POST preferences only |
| Quiet save | After dragging logos / SC2 / VDO panels (`saveSettingsToServerQuiet` → `StreamSettings.saveUserPreferencesQuiet`) |

Dragging panels no longer POSTs the full old `settings.php` blob.

### Legacy migration

If `stream_user_preferences_<user>.json` is missing, GET extracts layout-related keys from old `data/settings_<user>.json` (ignores event title, video buttons, etc. in that file for the new split).

---

## Layer 3 — Tab session overrides

Implemented in `js/stream_settings.js`.

When you edit a **show default** field (event title, video button URL, break timer, …), the new value is stored in `sessionStorage` under `stream_session_bc_<section>`.

- Other users are unaffected.
- A **new tab** on the same machine loads server defaults again (no session override until you edit).
- **Reset show defaults in this tab** clears all `stream_session_bc_*` keys and reloads from `stream_broadcast.php`.

Use this for “tonight’s title” or a one-off FEATURE URL without publishing.

---

## Related global settings (separate files)

These were already “show wide” before the split; they stay on dedicated endpoints:

| Feature | API | File |
| ------- | --- | ---- |
| Schedule / bracket / team scene MP4 paths | `scene_video_settings.php` | `data/scene_video_settings.json` |
| Music mood maps & staged SC2 playlists | `save_music_config.php` | `data/mood_songs.json`, `scene_mood_map.json`, `scene_stages.json` (+ optional per-user `_*_<user>.json`; **promote** copies to global) |

Player scoreboard **content** (`data/player_scoreboard.json`), team CSV, `/status`, etc. are **live show data**, not layout/settings — see `docs/STATUS_API.md`.

---

## UI map (Settings)

| Section | Layer |
| ------- | ----- |
| Save/Load → Publish show defaults | Layer 1 |
| Save/Load → Reset show defaults in this tab | Layer 3 clear + Layer 1 reload |
| Save/Load → Save my layout | Layer 2 |
| Save/Load → Export all / Import all | Full backup file (migration / disaster recovery); import still runs `importAllSettings` |
| Video Buttons → Save video buttons for all users | Layer 1 (`ytIframeVideos` only) |
| Scene Videos (schedule paths) | `scene_video_settings.php` |
| Layer order, Logos edit/move, TLS banner drag | Layer 2 |

---

## Producer workflow (cheat sheet)

1. **Lead producer** sets Season title, video URLs, break message, intros → **Publish show defaults**.
2. **Each caster** arranges logos/panels/layers on their monitor → **Save my layout** (or rely on quiet save after drag).
3. **Special segment** in one show → edit title or URL in the UI (session override); optionally **Reset show defaults in this tab** when back to normal.
4. **New season** → update fields → **Publish show defaults** again; casters keep their layout files.

---

## Files on the server (production)

```
data/stream_broadcast.json              # show defaults (shared)
data/stream_user_preferences_KJ.json    # example per-user layout
data/settings_KJ.json                   # legacy; layout may be migrated from here once
data/scene_video_settings.json          # schedule/bracket/team videos
data/backups/<timestamp>/               # JSON snapshots from deploy script
```

Deploy script (backs up then SCPs code): `scripts/deploy_with_data_backup.ps1`

---

## Code map

| File | Role |
| ---- | ---- |
| `js/stream_settings.js` | Bootstrap, session overrides, publish/save helpers |
| `stream_broadcast.php` | Read/write `stream_broadcast.json` |
| `stream_user_preferences.php` | Read/write per-user layout |
| `settings.php` | Legacy full JSON; still used by Export/Import all, not primary load path |
| `index.php` | `exportBroadcastSnapshot()`, UI buttons, loads `stream_settings.js` at end of body |

---

## Revert / recovery

**Code:** `git revert 8821a36` (or restore pre-split `index.php` and remove `stream_settings.js` from the page).

**Server data:** Copy JSON from `data/backups/<timestamp>/` back to `data/` (created by deploy script).

**Do not** delete user layout files when rolling back code unless intentional; old `settings_*.json` may still exist.

---

## FAQ

**If I publish video buttons, does it change my logo positions?**  
No. Only `stream_broadcast.json` is updated.

**If I change the event title but don’t publish, what do others see?**  
Only your tab (session override). Others still get the last published title on new load.

**If someone never clicks “Save my layout”, do they keep defaults?**  
They get published show defaults + in-memory layout from dragging (quiet save may persist layout to their user file after moves).

**Does Export all still work?**  
Yes. It is a full snapshot for backup. Prefer **Publish** / **Save my layout** for normal operations.

**Enforced vs not enforced**  
Show defaults are **strong defaults** (every new tab), not a lock: session overrides allow change without publish. Layout is **never** overwritten by publish.
