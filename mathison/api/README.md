# Mathison External API (v1)

Read-only JSON API for another tool to pull scouting / replay data.
Uses a **shared secret token** (not the stream-tool login session).

**Base URL (server):**  
`https://psistorm.com/stream_production/mathison/api/v1.php`

**Base URL (local):**  
`http://localhost/psistorm.com/tools/stream_greenscreen_production/mathison/api/v1.php`

---

## Auth

Set `api_token` in `mathison/config.local.php` (see `config.local.example.php`).

Send the token one of these ways (prefer a header):

```http
Authorization: Bearer YOUR_TOKEN
```

```http
X-Api-Token: YOUR_TOKEN
```

```text
?token=YOUR_TOKEN
```

If `api_token` is empty, the API returns `503`. Bad/missing token → `401`.

---

## Response shape

Success:

```json
{ "ok": true, "data": { ... } }
```

Error:

```json
{ "ok": false, "error": "message" }
```

All endpoints are **GET** only.

---

## Endpoints

Call with `?resource=...` (alias: `?action=...`).  
Slash form works too: `resource=player/games` → `player.games`.

| resource | Params | Returns |
| --- | --- | --- |
| `health` | — | Service ping + table counts |
| `players` | `q`, `limit` | Player name search |
| `player` | `name` | Profile summary |
| `player.last` | `name` | Last played + last game |
| `player.games` | `name`, `limit`, `vs?` | Recent games (optional filter vs one opponent) |
| `player.strategies` | `name`, `startWorkers?` | Labeled compound tactics |
| `player.likely` | `name`, `startWorkers?` | Likely strategies (lazy / heavier) |
| `player.timings` | `name` | Key building timings (earliest → latest) |
| `matchup` | `a`, `b`, `limit?` | Head-to-head record + recent games |
| `replay` | `id`, `summary?` | One replay (`summary=1` includes Replay_Summary) |

### health

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=health"
```

### players (search)

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=players&q=Little&limit=10"
```

### player (profile)

Games, commented/tagged counts, last played, race mix, top maps, **our** W-L vs them (self accounts from `insights/rules.php`), top tactics.

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=player&name=Bulmyeolja"
```

### player.last

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=player.last&name=MedicJR"
```

### player.games

Recent games for a player. Optional `vs=OtherName` for matchup history only.

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=player.games&name=LittleReaper&limit=25"

curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=player.games&name=KJ&vs=LittleReaper&limit=20"
```

### player.strategies

Labeled compound tactics from `insight_tactics` (needs relabeler).

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=player.strategies&name=Link&startWorkers=8"
```

`startWorkers` optional: `6`, `8`, or `12`.

### player.likely

Timing-inferred strategies for unlabeled games. Prefer calling only when needed (heavier than the others). Same logic as the Insights dashboard.

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=player.likely&name=LittleReaper"
```

### player.timings

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=player.timings&name=Bulmyeolja"
```

### matchup (head-to-head)

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE?resource=matchup&a=KJ&b=LittleReaper&limit=20"
```

Aliases: `playerA` / `playerB` instead of `a` / `b`.

### replay

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=replay&id=26589"
curl -H "Authorization: Bearer $TOKEN" "$BASE?resource=replay&id=26589&summary=1"
```

---

## Suggested composition for a scouting bot

Don't ship one giant “report” endpoint. Compose:

1. `player` — who they are / our record / last played  
2. `player.strategies` — what comments already say  
3. `player.likely` — only if strategies are thin  
4. `player.timings` — structure clocks  
5. `player.games&vs=UsName` or `matchup` — recent meetings  

---

## Files

| File | Role |
| --- | --- |
| `v1.php` | Router |
| `v1_handlers.php` | Endpoint logic |
| `bootstrap.php` | Token auth + JSON helpers |
| `README.md` | This guide |
| `replays.php` | Separate **session-auth** CRUD UI API (not for external tools) |

---

## Security notes

- Keep `api_token` out of git (`config.local.php`).
- Prefer `Authorization: Bearer` over `?token=` (tokens in URLs hit logs).
- API is read-only; it never writes Replays / PatternLearning / insight tables.
- Rotate the token if it leaks.
