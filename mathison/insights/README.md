# Mathison Insights

Turns human strategy notes in the Mathison DB into **compound scouting
tactics** (not single-word labels), then serves a dashboard for opponent
tendencies, win rates, and key build timings.

**Primary unit of analysis:** a chained plan like  
`3 hatch  /  ling bane  /  all in`  
- not three separate chips (`3_hatch`, `ling_bane`, `all_in`).

---

## Quick start

```bash
# From the stream_production (or stream_greenscreen_production) root:

# Preview only - prints top tactics + unmatched comments, writes nothing
php mathison/insights/relabel.php --dry-run

# Full rebuild of insight_* tables
php mathison/insights/relabel.php
```

Then open:

- Local: `.../mathison/insights/`
- Server: `https://psistorm.com/stream_production/mathison/insights/`

Auth is the same login gate as the rest of the stream tool.

---

## What problem this solves

`Replays.Player_Comments` and `PatternLearning.metadata.comment` are short
human labels for what happened early/mid game, e.g.:

- `3 hatch ling bane all in`
- `reaper to speed banshee BC`
- `cannon rush into fast expand blink stalkers`

Those notes are the intelligence. Insights:

1. Parses them into structured rows.
2. Keeps the **multi-part plan** together as one tactic.
3. Joins opponent, race, our W/L, date, and (when possible) ReplayId.
4. Surfaces PatternLearning `key_timings` (e.g. average Gateway / Pool time)
   on the opponent scouting report.

---

## Data flow

```
  Replays.Player_Comments          (~1.4k commented games)
               |
  PatternLearning.metadata
    $.comment
    $.game_data.opponent_name
    $.signature.key_timings        (read live by the API for scouting)
               |
               v
        relabel.php  (CLI, full rebuild)
               |
      +--------+---------+
      |                  |
      v                  v
 insight_tactics    insight_tags      insight_runs
 (PRIMARY)          (atomic only)     (watermarks)
      |
      v
 api/insights.php  -->  index.php dashboard
```

**Sources are never written to.**  
`insight_tags`, `insight_tactics`, and `insight_runs` are derived tables.
You can drop them and rerun the relabeler anytime without losing Replays or
PatternLearning data.

---

## Files

| File | Role |
| --- | --- |
| `README.md` | This document |
| `rules.php` | **Edit this** - vocabulary, self accounts, chain rules |
| `relabel.php` | CLI relabeler (idempotent full rebuild) |
| `likely_strategy.php` | Timing-match "likely strategy" for unlabeled games |
| `schema_changes.sql` | DDL for derived tables (auto-applied if missing) |
| `api/insights.php` | Read-only JSON API |
| `index.php` | Scouting dashboard UI |
| `js/insights.js` | Charts + staleness banner |

DB connection is shared with the rest of Mathison via `../db.php` and
`../config.php` (or `config.local.php`).

---

## The relabeler (`relabel.php`)

### What it does

1. Loads config from `rules.php`.
2. Ensures derived tables exist (`schema_changes.sql`).
3. Reads every usable comment from:
   - **Replays** - non-empty `Player_Comments`
   - **PatternLearning** - non-null `metadata.comment`
4. For each comment: detect atomic tags -> chain into compound tactics.
5. **Deletes** all rows in `insight_tags` / `insight_tactics`, then inserts
   the new set (full rebuild).
6. Records a row in `insight_runs` with watermarks used for "rerun?" checks.

### Why full rebuild (not incremental)

Editing one regex in `rules.php` should reclassify **all** historical
comments. A wipe-and-rebuild keeps that simple and correct. The dataset is
small (~2k comments); a run finishes in a few seconds.

### Commands

```bash
php mathison/insights/relabel.php            # write tables
php mathison/insights/relabel.php --dry-run  # classify only
```

Dry-run output includes:

- Source counts
- Atomic tag count / compound tactic count
- **Top compound tactics** (sanity-check chains look right)
- First unmatched comments (candidates for new rules)

### Opponent attribution

| Source | How opponent is set |
| --- | --- |
| PatternLearning | `metadata.game_data.opponent_name` |
| Replays | The player whose name is **not** in `rules.php` -> `self_accounts` |

If both sides (or neither) look like a self account, opponent is left `NULL`.
Tags/tactics still count toward global charts; they just won't appear under
a named scouting profile.

### Linking patterns back to Replays

Pattern rows store `game_data.date` as a unix timestamp (or older datetime
string). The relabeler matches that to `Replays.UnixTimestamp` when possible
and stores `replay_id` on the insight row.

### Likely strategy (unlabeled games)

PatternLearning per-game rows already carry comments, so the missing-label
set is **Replays vs this opponent with empty `Player_Comments`**. On
**single-opponent lookup only** (`api/insights.php?action=opponent`),
`likely_strategy.php` lazily:

1. Loads up to 40 recent uncommented replays (skips ones already in
   `insight_tactics`).
2. Parses opponent key-building timings from `Replay_Summary`.
3. Matches against labeled PatternLearning `key_timings` + compound tactics
   (same race when known, >=2 shared buildings <=10:00, mean abs delta <=90s,
   confidence >=0.35).

Never runs on overview/list. Never writes the DB. Dashboard shows a chip
summary plus `likely` badges in game history.

### "Our" result

Normalized to `Win` / `Lose` / `Observed` from the self account's perspective
(Replay result columns, or PatternLearning Victory/Defeat/Observed).

---

## How a comment becomes a tactic

This is the important design choice.

### Bad (what we avoid as the primary answer)

Comment: `3 hatch ling bane all in`  
-> three independent labels: "3 hatch", "ling bane", "all in"  
That loses the plan (aggressive 3-hatch ling/bane, not defensive macro).

### Good (what we store and chart)

Comment: `3 hatch ling bane all in`  

1. **Atomic detection** (building blocks only):  
   `3_hatch`, `ling_bane`, `all_in`
2. **Chain** in category order  
   `economy -> opening -> composition -> intent`  
   -> label: **`3 hatch  /  ling bane  /  all in`**
3. Write **one** row to `insight_tactics` (`parts_count = 3`).
4. Also write three rows to `insight_tags` for secondary filters
   ("any game that involved an all-in somewhere").

### Transitions (`" to "`)

Comment: `reaper to speed banshee BC`

- Text before ` to ` -> phase `early`
- Text after -> phase `late`
- Each phase that reaches `min_chain_parts` gets its own tactic row.

Example: `3 hatch ling bane to roach all in`  
-> early: `3 hatch  /  ling bane`  
-> late: `roach  /  all in`

If neither half alone is long enough, the whole comment may still form one
`phase=any` tactic when enough atoms match overall.

### Config knobs (`rules.php`)

| Key | Meaning |
| --- | --- |
| `self_accounts` | Names treated as "us" for opponent/W-L inference |
| `transition_word` | Default `" to "` |
| `min_chain_parts` | Min atoms to emit a tactic (default **2**) |
| `category_order` | Sort order when building the readable chain |
| `rules[]` | Atomic detectors: `tag`, `label`, `category`, `patterns`, `desc` |

Dashboard charts emphasize tactics with **`parts_count >= 3`** (true
multi-part plans). 2-part chains still exist in the table and appear in
opponent profiles when 3+ data is thin.

### Adding or changing vocabulary

1. Edit `rules.php` (add a rule, fix a regex, change a label).
2. Prefer `--dry-run` first; check top tactics and unmatched list.
3. Run `php mathison/insights/relabel.php`.

No migration of old tags is needed - the rebuild replaces everything.

---

## Derived tables

Defined in `schema_changes.sql`, created automatically by the relabeler.

### `insight_tactics` - primary scouting unit

| Column | Purpose |
| --- | --- |
| `tactic_key` | Stable id, e.g. `3_hatch\|ling_bane\|all_in` |
| `tactic_label` | Human chain, e.g. `3 hatch  /  ling bane  /  all in` |
| `parts_json` | Ordered `[{tag,label,category}, ...]` |
| `parts_count` | Length of the chain |
| `phase` | `early` / `late` / `any` |
| `opponent_name`, `opponent_race`, `self_result`, `date_played` | Context |
| `comment` | Raw source text (denormalized for easy queries) |
| `source` / `source_id` / `replay_id` | Provenance |

### `insight_tags` - atomic building blocks

One row per (comment, matched tag). Use for "contains X" style queries, not
as the main scouting story.

### `insight_runs` - run history + watermarks

Each finished run stores:

- `replays_scanned`, `patterns_scanned`, `tags_written`, `tactics_written`
- Watermarks: commented-replay count, max commented ReplayId,
  PatternLearning row count, max PatternLearning `updated_at`

---

## When to rerun the relabeler

### Automatic suggestion (dashboard)

`GET api/insights.php?action=status` compares the latest finished run's
watermarks to live source counts.

| Banner | Meaning |
| --- | --- |
| Yellow "Rerun suggested" | New commented replays and/or new/updated PatternLearning rows since last run |
| Green "up to date" | Watermarks still match |
| Yellow "never run" | No finished `insight_runs` row yet |

### Manual / automation

Rerun after:

- Adding a batch of commented replays
- Mathison writing new PatternLearning rows
- Changing `rules.php`

Safe to cron daily:

```bash
php /var/www/html/psistorm/stream_production/mathison/insights/relabel.php
```

---

## Dashboard & API

### UI (`index.php`)

- Status / rerun banner
- Totals: compound tactics, 3+ part chains, opponents, last run
- Chart: most common **compound** tactics (3+ parts)
- Chart: our win % vs those tactics
- Opponent picker -> scouting report:
  - Compound plans they've used
  - Key structure timings from PatternLearning signatures
  - Game history (tactic label + raw comment + our result)
- Top scouted opponents table

### API (`api/insights.php`)

| Action | Returns |
| --- | --- |
| `?action=status` | Staleness / watermarks / last run |
| `?action=overview` | Global tactics, win rates, top opponents |
| `?action=opponents` | Opponent list with plan counts |
| `?action=opponent&name=X` | Full scouting profile for one opponent |

All endpoints are read-only (auth required). Timings are computed live from
PatternLearning JSON; tags/tactics come from the derived tables.

---

## Example SQL (after a relabel)

```sql
-- Most common 3+ part plans
SELECT tactic_label, COUNT(*) AS games
FROM insight_tactics
WHERE parts_count >= 3
GROUP BY tactic_key, tactic_label
ORDER BY games DESC
LIMIT 20;

-- One opponent's plans
SELECT date_played, self_result, tactic_label, comment
FROM insight_tactics
WHERE opponent_name = 'llllllllllll'
ORDER BY date_played DESC;

-- Secondary: every game that involved an all-in atom
SELECT DISTINCT comment, opponent_name
FROM insight_tags
WHERE tag = 'all_in';
```

---

## Starting-worker eras (timing filter)

SC2 changed how many workers you start with, which shifts every early timing:

| Workers | Era |
| --- | --- |
| 6 | WoL / HotS (until LotV, 2015-11-10) |
| 12 | LotV through patch 5.0.15 (until ~2026-06-22) |
| 8 | Patch 5.0.16+ |

Configured in `rules.php` -> `worker_eras`. The relabeler stores `start_workers`
on each insight row (parsed from the first build-order `Supply: 6|8|12`, with
date-era fallback). The insights dashboard and replays table both expose a
**Starting workers** filter - use it before comparing timings or tactics.

---

## Extending later

- **New atomic concept** - add a rule in `rules.php`, rerun.
- **New comment source** - add `fetch_*_sources()` in `relabel.php`
  returning the same row shape, merge into `$sources`.
- **True build clustering** (merge PatternLearning signatures by timing
  similarity into archetypes) is not built yet; compound comment tactics
  cover the current scouting dashboard.

---

## Safety notes

- Relabeler is **CLI-only** (`PHP_SAPI === 'cli'`).
- Never modifies `Replays` or `PatternLearning`.
- Keep DB credentials in `mathison/config.php` / `config.local.php`
  (gitignored local override preferred for secrets).
