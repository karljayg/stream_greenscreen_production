# Layer order (stacking) – back to front

**In the app:** Use **Settings → Layer order** and drag items to reorder. Top of the list = back (drawn first), bottom = front (on top). Z-index is set automatically from that order (1, 2, 3, …) so layers never conflict.

**Reading the table below:** Layer 1 = back (furthest). Higher number = on top.

---

## Default layer order and z-index

| Layer | z-index | data-layer-id | What it is |
| ----- | ------- | ------------- | ---------- |
| 1 | 1 | `scene-overlay-all-vdo` | BG – All VDO fullscreen background (iframe) |
| 2 | 2 | `scene-overlay-vdo-full` | VDO full – full-size overlay panel (draggable/resizable) |
| 3 | 3 | `logos-overlay` | Logos – S10, FSL small, etc. (editable positions) |
| 4 | 4 | `sc2-overlay` | SC2 – smaller VDO panel (draggable/resizable) |
| 5 | 5 | `container` | Main layout – whole page (left + right column) |
| 6 | 6 | `gif-container` | Player intros – GIF (meme/greenscreen GIF) |
| 7 | 7 | `chart-container` | Player ratings – spider chart (internal) |
| 8 | 8 | `video-container` | Player intros – video (main intro video) |
| 9 | 9 | `right-column-result` | Status / Matchup text |
| 10 | 10 | `external-chart-overlay` | Player ratings – external chart overlay (spider iframe) |
| 11 | 11 | `matchup-suggestions` | Matchup – autocomplete dropdown (when open) |
| 12 | 12 | `player-name-box` | Player name (text under video) |

---

## All layer entities (data-layer-id)

| data-layer-id | Element / selector | Notes |
| ------------- | ------------------ | ----- |
| `scene-overlay-all-vdo` | `#scene-overlay-all-vdo` | BG scenes iframe; inside stream-frame |
| `scene-overlay-vdo-full` | `#scene-overlay-vdo-full` | VDO full panel; editable in “Edit and Move” |
| `logos-overlay` | `#logos-overlay` | Logos (S10, FSL small); editable positions |
| `sc2-overlay` | `#sc2-overlay` | SC2 panel; editable in “Edit and Move” |
| `container` | `.container` | Main layout (left + right column) |
| `video-container` | `#video-container` | Player intro video |
| `right-column-result` | `#right-column-result` | Status / Matchup text block |
| `gif-container` | `#gif-container` | Player intro GIF |
| `chart-container` | `#chart-container` | Player ratings internal chart |
| `external-chart-overlay` | `#external-chart-overlay` | Player ratings external iframe |
| `matchup-suggestions` | `.matchup-suggestions` | Dynamically created by JS when typing matchup |
| `player-name-box` | `.player-name-box` | Player name under video |

---

## Special cases

- **Editable panels** (`scene-overlay-vdo-full`, `logos-overlay`, `sc2-overlay`): In “Edit and Move” mode, their z-index is temporarily raised (e.g. 99999–100001) so they appear on top for dragging/resizing. On exit, the stored layer order is reapplied.
- **matchup-suggestions**: Created by JS when the matchup autocomplete opens. Gets `data-layer-id="matchup-suggestions"` and participates in layer order; `reapplyLayerOrder` is called when the list is shown.
- **left-column, right-column**: Inside container; no own z-index, not in layer order.

---

## Same z-index (when order applies)

With the default order, each layer has a unique z-index (1–12). Reordering in Settings changes these values so the new order is respected. No two layers share the same z-index in normal operation.
