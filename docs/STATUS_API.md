# Stream Production — Status API

Read-only JSON feed describing the live state of the FSL stream production
tool, plus a per-day activity log. Intended for a separate program to poll and
react to (overlays, bots, logging, analytics).

All endpoints are **public, read-only, `GET`**, send `Cache-Control: no-store`
and `Access-Control-Allow-Origin: *`. Timestamps are ISO-8601 with timezone
offset. The producer's browser pushes state to the server; if that browser is
closed the data goes stale (see `stream_alive`).

## How it works

The production tool is a single-page browser app — scene, music, and GG state
live only in the browser. A small reporter (`js/status-reporter.js`) derives
that state and `POST`s it to `save_status.php` on a ~2-second heartbeat and
immediately on key events. The server also reads the authoritative match score
from `2026/scoreboard.csv` and diffs it, so score changes are captured no matter
how the producer edits them. `save_status.php` keeps a live snapshot in
`data/status.json` and appends meaningful events to `data/status_log/<date>.jsonl`.

## Endpoints

| URL | Purpose |
|-----|---------|
| `GET /status`                       | Current snapshot + recent events |
| `GET /status?since=<seq>`           | Cheap unchanged check / incremental events |
| `GET /status/log`                   | Index of available days |
| `GET /status/log?date=<YYYY-MM-DD>` | Full event log + summary for one day |
| `GET /status/log?date=today`        | Shortcut for the current day |

If `mod_rewrite` is unavailable, use the backing files directly:
`status.php`, `status.php?since=...`, `status_log.php`, `status_log.php?date=...`.

## Detecting change (the `seq` counter)

`seq` is a global, monotonically increasing integer that goes up by 1 for every
logged event (scene change, music change, GG, score, winner, intro, connect).
Poll `/status` every 1–2s and compare `seq` to what you saw last:

- `seq` unchanged → nothing happened.
- `seq` increased → read the snapshot and/or `recent_events`.

To never miss a transient event (a GG flash, a quick intro), call
`/status?since=<your_last_seq>`:

- If `seq <= since`, you get a tiny `{ "seq", "changed": false, ... }`.
- Otherwise you get the full snapshot with `recent_events` already filtered to
  `id > since`. Process those events in order and advance your `since` to `seq`.

> `recent_events` is a rolling buffer of the last 50 events. If your poller falls
> more than 50 events behind, pull the full history from `/status/log?date=today`.

## Liveness

- `stream_alive` is `false` when no heartbeat arrived within the last 10s.
- `heartbeat_age_ms` is the age of the last push.
- Treat a stale feed as "stream not actively produced," not as "match over."

## Snapshot fields (`/status`)

```json
{
  "schema_version": 1,
  "seq": 1487,
  "date": "2026-06-20",
  "updated_at": "2026-06-20T20:14:33-04:00",
  "stream_alive": true,
  "heartbeat_age_ms": 1200,
  "changed": true,

  "scene":  { "active": "sc2", "label": "SC2 (animated)", "since": "2026-06-20T20:13:01-04:00", "previous": "scoreboard" },
  "music":  { "playing": true, "mood": "combat_high", "mood_label": "Combat+", "track": "FSL_combat_03.mp3", "random": false, "driven_by_scene": "sc2" },
  "match":  {
    "team_a": { "name": "PulledTheBoys", "score": 4 },
    "team_b": { "name": "PSIOP Gaming",  "score": 6 },
    "last_change": { "at": "2026-06-20T20:12:55-04:00", "team": "b", "from": 5, "to": 6 },
    "series_winner": null
  },
  "player_intro": { "last_played": "LittleReaper", "at": "2026-06-20T20:12:40-04:00" },
  "gg": { "state": "match_gg", "last_event": { "type": "match_gg", "at": "2026-06-20T20:12:38-04:00" } },
  "recent_events": [ /* see below */ ]
}
```

- `scene.active` — scene key (see table); `scene.label` is human text;
  `scene.previous` is the prior scene; `scene.since` is when it became active.
  When several overlays stack (BG + Logos behind SC2/Scoreboard), the
  most-specific foreground scene is reported.
- `music` — `mood`, `mood_label`, `track`, `playing` (bool), `random` (bool),
  `driven_by_scene` (scene key that auto-selected the mood, or `null` if manual).
- `match` — `team_a`/`team_b` `{name, score}` (authoritative from the
  scoreboard), `last_change` (most recent score increment), `series_winner`
  (name or `null`).
- `player_intro.last_played` — last intro video triggered.
- `gg.state` — `null` | `"gg"` | `"match_gg"`.

## Events

Each event: `{ "id": <int>, "at": <iso>, "type": <string>, "data": {...} }`.
`id` equals the `seq` value assigned when the event was logged, so event ids are
globally monotonic and align with the `since` parameter.

| type     | data |
|----------|------|
| `connect`| `{ user }` — producer browser loaded/reconnected |
| `scene`  | `{ to, from }` |
| `music`  | `{ mood, mood_label, track, playing, random }` |
| `intro`  | `{ player }` |
| `gg`     | `{ kind: "gg" \| "match_gg" }` |
| `score`  | `{ team: "a"\|"b", from, to, team_a: {name,score}, team_b: {name,score} }` |
| `winner` | `{ team: "a"\|"b", name }` — series decided |

## Winner semantics

`series_winner` and the `winner` event come from the **authoritative score**
(the scoreboard the producer maintains): when a score change occurs while
`gg.state` is `"match_gg"`, the leading team is recorded as the series winner.
Per-game winners are **not** reported (they can't be reliably inferred). Until a
series is decided, `series_winner` is `null`.

## Day log (`/status/log`)

Index response:

```json
{
  "schema_version": 1,
  "generated_at": "2026-06-20T20:14:40-04:00",
  "days": [
    { "date": "2026-06-20", "event_count": 214, "first_event": "...", "last_event": "..." }
  ]
}
```

Single-day response (`?date=2026-06-20`) adds a computed `summary` plus the full
chronological `events` array:

```json
{
  "date": "2026-06-20",
  "first_event": "2026-06-20T19:30:11-04:00",
  "last_event":  "2026-06-20T20:14:33-04:00",
  "event_count": 214,
  "summary": {
    "final_score": { "team_a": { "name": "PulledTheBoys", "score": 4 }, "team_b": { "name": "PSIOP Gaming", "score": 6 } },
    "series_winner": "PSIOP Gaming",
    "ggs": 3,
    "match_ggs": 1,
    "scenes_shown": { "sc2": 9, "scoreboard": 6, "bracket": 2 },
    "moods_played": { "combat_high": 5, "analysis": 3, "victory": 1 },
    "intros_played": ["DarkMenace", "LittleReaper", "PulledTheBoys"]
  },
  "events": [ /* every event for the day, oldest first */ ]
}
```

The raw file `data/status_log/<date>.jsonl` holds one event per line (JSON
Lines), append-only, e.g.:

```
{"id":1,"at":"2026-06-20T19:30:11-04:00","type":"connect","data":{"user":"kj"}}
{"id":2,"at":"2026-06-20T19:30:14-04:00","type":"scene","data":{"to":"logos","from":null}}
{"id":3,"at":"2026-06-20T19:31:02-04:00","type":"music","data":{"mood":"opening_high","track":"FSL_open_01.mp3","playing":true}}
```

Files are kept indefinitely (a 3-hour stream is ~50–100 KB). Delete old files
manually if ever needed.

## Scene keys

`all-vdo` (BG), `vdo-full`, `logos`, `sc2`, `sc2-quick`, `schedule`, `bracket`,
`scoreboard`, `custom-scoreboard`, `ash`, `pog`, `ptb`, `st`, `shared-window`,
`full-shared`, `yt`.

## Example poll loop (pseudocode)

```js
let lastSeq = 0;
setInterval(async () => {
  const s = await fetch('/status?since=' + lastSeq).then(r => r.json());
  if (!s.changed) return;            // seq unchanged
  for (const ev of s.recent_events)  // already filtered to id > lastSeq
    handleEvent(ev);
  updateUi(s);
  lastSeq = s.seq;
}, 1500);
```
