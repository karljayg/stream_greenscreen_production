<?php
$pathPrefix = '../../';
require_once __DIR__ . '/../../partials/auth-gate.php';
require_once __DIR__ . '/../../asset_version.php';
require_once __DIR__ . '/queue_lib.php';
header('Cache-Control: no-cache, no-store, must-revalidate');

$snap = om_board_snapshot();
$session = is_array($snap['session'] ?? null) ? $snap['session'] : om_default_session();
$queue = is_array($snap['queue'] ?? null) ? $snap['queue'] : [];
$attention = is_array($snap['attention'] ?? null) ? $snap['attention'] : [];
$history = is_array($snap['history'] ?? null) ? $snap['history'] : [];
$undoInfo = $snap['undo'] ?? null;
$archives = is_array($snap['archives'] ?? null) ? $snap['archives'] : (om_use_remote_data() ? [] : om_list_archives());
$dataSource = (string) ($snap['data_source'] ?? 'local');
$remoteBase = (string) ($snap['remote_base'] ?? '');
$snapError = (string) ($snap['error'] ?? '');
$guestCount = 0;
$longest = '—';
$longestMins = -1;
foreach ($queue as $p) {
    if (in_array((string) ($p['is_guest'] ?? ''), ['1', 'true', 'True'], true)) {
        $guestCount++;
    }
    $m = om_wait_minutes((string) ($p['joined_at'] ?? ''));
    if ($m > $longestMins) {
        $longestMins = $m;
        $longest = om_wait_label((string) ($p['joined_at'] ?? ''));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="FSL Open Matchups — stream browser source (16:9)">
<title>FSL Open Matchups</title>
<link rel="icon" href="<?= $pathPrefix ?>production_files/images/favicon.ico?v=<?= (int) $v ?>" type="image/x-icon">
<style>
:root{
  --bg:#07101d; --panel:#0e1b2b; --border:#263b52; --text:#edf5ff;
  --muted:#8ea5bd; --blue:#38a9ff; --live:#4eb8ff; --yellow:#f6c85f;
  --orange:#ff9a4d; --red:#ff6464; --purple:#b996ff;
  /* --live is cyan/sky — never use chroma-key greens on this board */
}
*{box-sizing:border-box}
html,body{height:100%; margin:0; overflow:hidden}
body{
  color:var(--text);
  font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
  background:
    radial-gradient(circle at 18% 0%,#102b45 0,transparent 32%),
    linear-gradient(180deg,#07101d,#091421);
  display:flex; align-items:center; justify-content:center;
  /* Safe inset so OBS/browser crops don't clip text or glows */
  padding:22px 28px;
  box-sizing:border-box;
}
/* Stream browser source: 16:9, inset so edges stay visible */
.page{
  width:min(100%, calc((100vh - 44px) * 16 / 9));
  height:min(100%, calc((100vw - 56px) * 9 / 16));
  max-width:100%;
  max-height:100%;
  aspect-ratio:16 / 9;
  margin:0 auto;
  padding:12px 18px 14px;
  display:flex; flex-direction:column; gap:8px; min-height:0;
  overflow:hidden;
}
.header{display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-shrink:0; min-width:0}
.header > div:first-child{min-width:0; flex:1 1 auto; overflow:visible}
.header h1{margin:0; font-size:20px; letter-spacing:.2px; line-height:1.1; display:flex; align-items:center; gap:8px}
.header h1 img{height:28px; width:auto; flex-shrink:0}
.header .sub{color:var(--muted); font-size:11px; margin-top:2px; padding-left:2px; white-space:nowrap}
.header-actions{display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end; flex:0 1 auto}
.stats{display:flex; gap:8px; flex-shrink:0}
.stat{
  flex:1; min-width:0; padding:6px 10px; border-radius:8px;
  background:rgba(14,27,43,.92); border:1px solid var(--border);
  display:flex; justify-content:space-between; align-items:baseline;
}
.stat b{font-size:18px}
.stat span{color:var(--muted); font-size:9px; text-transform:uppercase; letter-spacing:.5px}
.board{
  flex:1; min-height:0;
  display:grid; grid-template-columns:1.05fr .95fr; gap:10px;
}
.col-left,.col-right,.stack{
  display:flex; flex-direction:column; gap:10px; min-height:0;
}
.col-left{min-height:0}
.col-left .panel-waiting{flex:1.45; min-height:0}
.col-left .panel-results{flex:1; min-height:0}
.col-right .panel-attn{flex:0 0 auto; max-height:28%; min-height:0}
.panel{
  background:rgba(14,27,43,.94); border:1px solid var(--border);
  border-radius:10px; overflow:hidden; min-height:0;
  display:flex; flex-direction:column;
}
.panel-h{
  display:flex; justify-content:space-between; align-items:center;
  padding:8px 12px; border-bottom:1px solid var(--border); flex-shrink:0;
}
.panel-h h2{margin:0; font-size:12px; font-weight:800; letter-spacing:.4px; text-transform:uppercase}
.panel-h .hint{color:var(--muted); font-size:10px}
.panel-scroll{
  flex:1; min-height:0; overflow-y:auto; overflow-x:hidden;
  scrollbar-width:thin; scrollbar-color:rgba(56,169,255,.45) transparent;
}
.panel-scroll::-webkit-scrollbar{width:8px}
.panel-scroll::-webkit-scrollbar-thumb{
  background:rgba(56,169,255,.35); border-radius:8px;
}
.q-row{
  display:grid; grid-template-columns:36px 44px 1fr 88px 48px;
  gap:8px; align-items:center; padding:7px 12px;
  border-bottom:1px solid rgba(38,59,82,.55); cursor:grab;
}
.q-row:last-child{border-bottom:0}
.q-row:hover{background:rgba(56,169,255,.05)}
.q-row.guest{background:linear-gradient(90deg,rgba(185,150,255,.1),transparent)}
.q-row.dragging{opacity:.4}
.q-thumb,.chip-thumb,.avatar-fallback{
  width:36px; height:36px; border-radius:9px; object-fit:cover;
  border:1px solid rgba(255,255,255,.14); background:#071019;
  box-shadow:0 4px 12px rgba(0,0,0,.25);
}
.avatar-wrap{
  width:36px; height:36px; position:relative; flex:0 0 36px;
}
.avatar-wrap img,.avatar-wrap .avatar-fallback{
  position:absolute; inset:0; width:100%; height:100%;
}
.avatar-fallback{
  display:flex; align-items:center; justify-content:center;
  font-size:11px; font-weight:800; color:var(--muted); letter-spacing:.3px;
}
.q-team-logo,.chip-team{
  width:22px; height:22px; border-radius:6px; object-fit:cover;
  border:1px solid rgba(255,255,255,.12); vertical-align:middle;
  background:#071019;
}
.name-row{display:flex; align-items:center; gap:6px; min-width:0}
.name{font-size:14px; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.meta{color:var(--muted); font-size:10px; margin-top:1px; display:flex; align-items:center; gap:5px; min-width:0}
.meta .twitch{overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.badge{
  display:inline-block; margin-left:6px; padding:2px 7px; border-radius:999px;
  font-size:9px; font-weight:800; letter-spacing:.4px;
  background:rgba(185,150,255,.16); border:1px solid rgba(185,150,255,.35); color:#d9c7ff;
}
.mode{
  text-align:center; font-size:10px; font-weight:900; padding:4px 0; border-radius:6px;
}
.mode-1v1{color:#74c6ff; background:rgba(56,169,255,.12)}
.mode-2v2{color:#9ad4ff; background:rgba(78,184,255,.12)}
.mode-3v3{color:#c3a8ff; background:rgba(185,150,255,.1)}
.mode-custom{color:#f7d578; background:rgba(246,200,95,.1)}
.rank{font-size:12px; font-weight:700}
.rank small{display:block; color:var(--muted); font-size:9px; font-weight:500}
.wait{font-size:14px; font-weight:900; text-align:right}
.wait-long{color:var(--red)} .wait-medium{color:var(--orange)} .wait-normal{color:#d6e1eb}
/* Playing Now = primary cyan/sky focus (chroma-safe). Next = quieter yellow. */
.match{
  padding:0; border:2px solid var(--border); border-left-width:6px;
  transition:border-color .15s, box-shadow .15s, background .15s; flex-shrink:0;
  position:relative;
}
.match > .status{padding:8px 12px 0}
.match > h3{margin:4px 12px 8px; font-size:16px}
.match > .vs-grid,.match > .match-meta{margin-left:12px; margin-right:12px}
.match > .match-meta{margin-bottom:10px; padding-bottom:2px}

/* Playing Now panel — cyan/navy, never chroma green */
.match.state-playing{
  border-color:#1a6a9a;
  border-left-color:#4eb8ff;
  background:
    linear-gradient(165deg, #1a5a8a 0%, #0d3558 42%, #082438 100%);
  box-shadow:
    inset 0 0 0 1px rgba(78,184,255,.3),
    0 6px 18px rgba(20,80,140,.28);
}
.match.state-playing::before{
  content:''; position:absolute; top:0; left:0; right:0; height:5px;
  background:linear-gradient(90deg,#4eb8ff,#38a9ff,#4eb8ff);
}
.match.state-playing h3{color:#c8e8ff; font-size:18px}
.match.state-playing .timer{color:#9ad4ff}
.match.state-playing .side{
  background:rgba(4,28,48,.7); border-color:rgba(78,184,255,.4);
}
.match.state-playing .chip{
  background:rgba(78,184,255,.2); border-color:rgba(78,184,255,.45);
}
/* Extra punch when actually live */
.match.state-playing.state-live{
  border-color:#2a8fd4;
  border-left-color:#6ec8ff;
  background:
    linear-gradient(165deg, #2280c0 0%, #1268a0 35%, #0a4570 100%);
  box-shadow:
    inset 0 0 0 1px rgba(140,210,255,.4),
    0 0 24px rgba(56,169,255,.22),
    0 8px 22px rgba(20,90,150,.3);
}
.match.state-playing.state-live .timer{
  color:#c4e8ff; text-shadow:0 0 10px rgba(78,184,255,.35);
  font-size:28px;
}

/* Next Match = secondary yellow (quieter than Playing) */
.match.state-deck{
  border-color:#8a7020;
  border-left-color:#e6b84d;
  background:
    linear-gradient(180deg, rgba(120,95,25,.28) 0%, rgba(22,20,12,.94) 55%);
  box-shadow:inset 0 0 0 1px rgba(230,184,77,.2);
}
.match.state-deck::before{
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
  background:rgba(230,184,77,.7);
}
.match.state-deck h3{color:#f0d78a; font-size:15px}
.match.state-deck .side{
  background:rgba(28,24,10,.75); border-color:rgba(230,184,77,.28);
}
.match.state-deck .chip{
  background:rgba(230,184,77,.12); border-color:rgba(230,184,77,.3);
}
.match.state-deck.state-deck-empty{
  opacity:.85;
  background:
    linear-gradient(180deg, rgba(100,80,20,.16) 0%, rgba(18,18,12,.92) 55%);
}

.status.live{color:#9ad4ff} .status.deck{color:#e6c86a} .status.idle{color:rgba(180,220,255,.65)}
.status.ended{color:#ff8a8a}
.pill{
  display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px;
  font-size:10px; font-weight:900; letter-spacing:.5px; text-transform:uppercase;
}
.pill-live{
  background:rgba(78,184,255,.28); color:#e8f6ff;
  border:1px solid rgba(78,184,255,.65);
  box-shadow:0 0 10px rgba(56,169,255,.25);
}
.pill-deck{
  background:rgba(230,184,77,.16); color:#f0d78a;
  border:1px solid rgba(230,184,77,.4);
}
.pill-ended{
  background:rgba(255,100,100,.2); color:#ffb0b0;
  border:1px solid rgba(255,100,100,.45);
}
.pill-idle{
  background:rgba(180,220,255,.1); color:#b8d8f0;
  border:1px solid rgba(56,169,255,.3);
}
.pill-ready{
  background:rgba(78,184,255,.18); color:#c8e8ff;
  border:1px solid rgba(78,184,255,.45);
}

/* Side lanes: accent only — don't compete with Playing cyan */
.panel-waiting{
  border:1px solid #2a4a68;
  border-left:5px solid #38a9ff;
  background:rgba(14,27,43,.94);
}
.panel-waiting .panel-h{
  background:rgba(56,169,255,.08);
  border-bottom-color:rgba(56,169,255,.2);
}
.panel-waiting .panel-h h2{color:#9fd6ff}
.panel-results{
  border:1px solid #5a3030;
  border-left:5px solid #ff6464;
  background:rgba(14,27,43,.94);
}
.panel-results .panel-h{
  background:rgba(255,100,100,.08);
  border-bottom-color:rgba(255,100,100,.2);
}
.panel-results .panel-h h2{color:#ffb0b0}
.panel-attn{
  border:1px solid #5a4030;
  border-left:5px solid #ff9a4d;
  background:rgba(14,27,43,.94);
}
.panel-attn .panel-h{
  background:rgba(255,154,77,.08);
  border-bottom-color:rgba(255,154,77,.2);
}
.panel-attn .panel-h h2{color:#ffc08a}
.hist-row{
  display:grid; grid-template-columns:56px 1fr auto auto; gap:8px; align-items:center;
  padding:8px 12px; border-bottom:1px solid rgba(38,59,82,.55);
}
.hist-row:last-child{border-bottom:0}
.hist-score{font-size:15px; font-weight:900; color:var(--text)}
.hist-names{font-size:12px; font-weight:700}
.hist-meta{color:var(--muted); font-size:10px; margin-top:1px}
.hist-actions{display:flex; gap:2px; align-items:center}
.hist-del,.hist-restore{
  border:0; background:transparent; cursor:pointer;
  font-size:13px; padding:4px 6px; border-radius:6px; line-height:1;
}
.hist-del{color:#ff9a9a}
.hist-del:hover{background:rgba(255,100,100,.15)}
.hist-restore{color:#9ad4ff; font-weight:800}
.hist-restore:hover{background:rgba(78,184,255,.15)}
.hist-vs{
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}
.hist-side{
  display:flex; align-items:center; gap:5px; min-width:0;
}
.hist-side .avatar-wrap{width:28px; height:28px; flex:0 0 28px}
.hist-side .q-team-logo,.hist-side .chip-team{
  width:18px; height:18px; border-radius:5px;
}
.hist-pname{font-size:12px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:110px}
.hist-sep{color:var(--muted); font-size:10px; font-weight:800}
.vs-grid{display:grid; grid-template-columns:1fr 32px 1fr; gap:6px; align-items:stretch}
.side{
  min-height:64px; max-height:120px; overflow-y:auto; padding:6px; border-radius:8px; background:#0a1725;
  border:1px solid var(--border); scrollbar-width:thin;
}
.side.drop-hover,.drop-zone.drop-hover{outline:2px dashed var(--blue); outline-offset:-2px; background:rgba(56,169,255,.07)}
.vs{align-self:center; text-align:center; color:var(--muted); font-weight:900; font-size:11px}
.chip{
  display:flex; align-items:center; gap:8px; margin:3px 0; padding:6px 8px;
  border-radius:7px; cursor:grab;
  background:rgba(56,169,255,.1); border:1px solid rgba(56,169,255,.22);
}
.chip-body{flex:1; min-width:0}
.chip strong{display:block; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.chip span{color:var(--muted); font-size:10px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
.chip-team{width:26px; height:26px; border-radius:7px; flex:0 0 26px}
.match-meta{display:flex; justify-content:space-between; align-items:center; margin-top:8px; gap:8px}
.timer{font-size:24px; font-weight:900; letter-spacing:1px}
.scoreline{font-size:13px; color:var(--muted)}
.scoreline strong{color:var(--text); font-size:16px}
.attn{padding:6px 12px}
.attn-row{display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border); font-size:12px}
.attn-row:last-child{border-bottom:0}
.attn-row .why{color:var(--muted); font-size:10px}
.empty{padding:20px 12px; color:var(--muted); text-align:center; font-size:12px}
.btn{
  border:1px solid #316c95; background:rgba(56,169,255,.12); color:#9fd6ff;
  border-radius:7px; padding:6px 9px; font-size:11px; font-weight:700; cursor:pointer;
}
.btn:hover{background:rgba(56,169,255,.22)}
.btn-green{border-color:#2a6a9a; background:rgba(78,184,255,.14); color:#9ad4ff}
.btn-danger{border-color:#8a3a3a; background:rgba(255,100,100,.12); color:#ffb0b0}
.btn-ghost{border-color:transparent; background:transparent; color:var(--muted)}
.btn:disabled{opacity:.45; cursor:not-allowed}
#btn-undo:not(:disabled){
  border-color:#8a7020; background:rgba(230,184,77,.14); color:#f0d78a;
}
.hub{color:var(--muted); font-size:11px; text-decoration:none}
.hub:hover{color:var(--blue)}

/* Modal */
.modal-back{
  position:fixed; inset:0; background:rgba(0,0,0,.55); display:none;
  align-items:center; justify-content:center; z-index:40; padding:16px;
}
.modal-back.open{display:flex}
.modal{
  width:min(520px,100%); background:#0e1b2b; border:1px solid var(--border);
  border-radius:12px; padding:16px; box-shadow:0 20px 60px rgba(0,0,0,.45);
}
.modal h3{margin:0 0 12px; font-size:16px}
.form-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px}
.form-grid label{display:flex; flex-direction:column; gap:4px; font-size:11px; color:var(--muted)}
.form-grid .full{grid-column:1/-1}
.form-grid input,.form-grid select,.form-grid textarea{
  background:#07101d; border:1px solid var(--border); color:var(--text);
  border-radius:6px; padding:8px 10px; font-size:13px;
}
.modal-actions{display:flex; justify-content:flex-end; gap:8px; margin-top:14px}
.check{flex-direction:row !important; align-items:center; gap:8px; color:var(--text) !important}

/* Context menu */
.ctx{
  position:fixed; z-index:50; min-width:180px; display:none;
  background:#0a1725; border:1px solid var(--border); border-radius:8px;
  padding:4px; box-shadow:0 12px 30px rgba(0,0,0,.4);
}
.ctx.open{display:block}
.ctx button{
  display:block; width:100%; text-align:left; border:0; background:transparent;
  color:var(--text); padding:8px 10px; font-size:12px; border-radius:6px; cursor:pointer;
}
.ctx button:hover{background:rgba(56,169,255,.12)}
.ctx .sep{height:1px; background:var(--border); margin:4px 0}
.ctx .danger{color:#ffb0b0}

.toast{
  position:fixed; right:16px; bottom:16px; display:none; z-index:60;
  background:#123; border:1px solid var(--border); border-radius:8px; padding:10px 14px; font-size:12px;
}
.data-source{
  flex-shrink:0; font-size:10px; padding:4px 8px; border-radius:6px;
  border:1px solid var(--border); color:var(--muted);
}
.data-source.remote{border-color:#8a7020; color:#f0d78a; background:rgba(230,184,77,.1)}
.data-source.error{border-color:#a04040; color:#ffb0b0; background:rgba(255,80,80,.1)}
.archive-select{
  max-width:11rem; background:#07101d; color:var(--text); border:1px solid var(--border);
  border-radius:7px; padding:5px 7px; font-size:11px;
}
.archive-banner{
  flex-shrink:0; font-size:11px; padding:6px 10px; border-radius:8px;
  border:1px solid rgba(185,150,255,.4); background:rgba(185,150,255,.1); color:#d9c7ff;
}
</style>
</head>
<body>
<main class="page">
  <div class="header">
    <div>
      <h1><img src="https://psistorm.com/fsl/images/fsl_sc2_logo_small_40px_93px.png" alt="FSL Logo"> Open Matchups</h1>
      <div class="sub">Broadcast board · drag to move · right-click to edit</div>
      <?php if ($dataSource === 'remote'): ?>
        <div class="data-source remote" title="<?= htmlspecialchars($remoteBase, ENT_QUOTES, 'UTF-8') ?>">
          Data: live server<?= $snapError !== '' ? ' · ' . htmlspecialchars($snapError, ENT_QUOTES, 'UTF-8') : '' ?>
        </div>
      <?php elseif ($snapError !== ''): ?>
        <div class="data-source error"><?= htmlspecialchars($snapError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </div>
    <div class="header-actions">
      <a class="hub" href="../index.php">Mathison</a>
      <button type="button" class="btn" id="btn-add">+ Add player</button>
      <button type="button" class="btn btn-green" id="btn-live">Mark live</button>
      <button type="button" class="btn btn-ghost" id="btn-undo-live" style="display:none">Undo live</button>
      <button type="button" class="btn" id="btn-undo" disabled title="Nothing to undo">Undo</button>
      <button type="button" class="btn" id="btn-promote">On Deck → Playing</button>
      <button type="button" class="btn btn-danger" id="btn-end">End match</button>
      <button type="button" class="btn btn-ghost" id="btn-clear">Clear queue</button>
      <button type="button" class="btn" id="btn-archive-night" title="Save tonight to archives/YYYY-MM-DD_label and reset the board">End &amp; archive night</button>
      <select id="archive-select" class="archive-select" title="Previous archived nights">
        <option value="">Load archive…</option>
      </select>
      <button type="button" class="btn btn-ghost" id="btn-load-archive" disabled>Load</button>
    </div>
  </div>
  <div id="archive-banner" class="archive-banner" hidden></div>

  <div class="stats">
    <div class="stat"><span>Waiting</span><b id="stat-waiting"><?= count($queue) ?></b></div>
    <div class="stat"><span>Guests</span><b id="stat-guests"><?= (int)$guestCount ?></b></div>
    <div class="stat"><span>Longest</span><b id="stat-longest"><?= htmlspecialchars($longest, ENT_QUOTES, 'UTF-8') ?></b></div>
    <div class="stat"><span>Games</span><b id="stat-games"><?= (int)($session['games_tonight'] ?? 0) ?></b></div>
  </div>

  <div class="board">
    <div class="col-left">
      <section class="panel panel-waiting">
        <div class="panel-h">
          <h2>Waiting</h2>
          <div class="hint" id="queue-count"><?= count($queue) ?> players</div>
        </div>
        <div id="queue-body" class="panel-scroll drop-zone" data-dest="waiting"></div>
      </section>

      <section class="panel panel-results">
        <div class="panel-h">
          <h2>Results</h2>
          <div class="hint">
            <button type="button" class="btn btn-ghost" id="btn-clear-history" style="padding:2px 6px;font-size:10px">Clear all</button>
            · ↩ restores · newest first
          </div>
        </div>
        <div id="history-body" class="panel-scroll"></div>
      </section>
    </div>

    <div class="col-right">
      <section class="panel match state-playing" id="playing-panel" data-match="playing">
        <div class="status live" id="playing-status"><span class="pill pill-idle" id="playing-pill">Playing</span></div>
        <h3>Playing Now</h3>
        <div class="vs-grid">
          <div class="side drop-zone" data-dest="playing_a" id="playing-a"></div>
          <div class="vs">VS</div>
          <div class="side drop-zone" data-dest="playing_b" id="playing-b"></div>
        </div>
        <div class="match-meta">
          <div class="timer" id="gameTimer">00:00</div>
          <div class="scoreline" id="playing-summary">Bo3 · 0–0</div>
        </div>
      </section>

      <section class="panel match state-deck" id="ondeck-panel" data-match="on_deck">
        <div class="status deck"><span class="pill pill-deck" id="ondeck-pill">Next</span></div>
        <h3>Next Match</h3>
        <div class="vs-grid">
          <div class="side drop-zone" data-dest="ondeck_a" id="ondeck-a"></div>
          <div class="vs">VS</div>
          <div class="side drop-zone" data-dest="ondeck_b" id="ondeck-b"></div>
        </div>
        <div class="match-meta">
          <div class="scoreline" id="ondeck-summary">Bo3</div>
          <div class="hint" id="ondeck-hint"></div>
        </div>
      </section>

      <section class="panel panel-attn">
        <div class="panel-h"><h2>Don't overlook</h2></div>
        <div class="attn panel-scroll" id="attention-body"></div>
      </section>
    </div>
  </div>
</main>

<!-- Add / Edit player modal -->
<div class="modal-back" id="modal-player">
  <div class="modal">
    <h3 id="player-modal-title">Add player</h3>
    <form id="player-form" class="form-grid" autocomplete="off">
      <input type="hidden" id="pf-mode" value="add">
      <label class="full">Twitch name *
        <input id="pf-twitch" required placeholder="login (no @)">
      </label>
      <label class="full">FSL / display name
        <input id="pf-fsl" placeholder="optional">
      </label>
      <label>Race
        <select id="pf-race">
          <option value="">—</option>
          <option>Protoss</option><option>Terran</option><option>Zerg</option><option>Random</option>
        </select>
      </label>
      <label>Mode
        <select id="pf-match-mode">
          <option>1v1</option><option>2v2</option><option>3v3</option><option>custom</option>
        </select>
      </label>
      <label>Rank # <input id="pf-rank" placeholder="27"></label>
      <label>Group <input id="pf-group" placeholder="7"></label>
      <label class="full">Skill / guest estimate <input id="pf-skill" placeholder="Masters Tier 2"></label>
      <label class="full">Custom type <input id="pf-custom" placeholder="if custom mode"></label>
      <label class="check full"><input type="checkbox" id="pf-guest"> NEW GUEST</label>
      <div class="modal-actions full">
        <button type="button" class="btn btn-ghost" data-close="modal-player">Cancel</button>
        <button type="submit" class="btn btn-green" id="pf-submit">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit match modal -->
<div class="modal-back" id="modal-match">
  <div class="modal">
    <h3 id="match-modal-title">Edit match</h3>
    <form id="match-form" class="form-grid">
      <input type="hidden" id="mf-which" value="playing">
      <label>Best of
        <input type="number" id="mf-bo" min="1" max="9" value="3">
      </label>
      <label>Mode
        <select id="mf-mode">
          <option>1v1</option><option>2v2</option><option>3v3</option><option>custom</option>
        </select>
      </label>
      <label>Score A <input type="number" id="mf-sa" min="0" value="0"></label>
      <label>Score B <input type="number" id="mf-sb" min="0" value="0"></label>
      <div class="modal-actions full">
        <button type="button" class="btn btn-ghost" data-close="modal-match">Cancel</button>
        <button type="submit" class="btn btn-green">Save</button>
      </div>
    </form>
  </div>
</div>

<nav class="ctx" id="ctx"></nav>
<div class="toast" id="toast"></div>

<script>
const API = 'api.php';
let state = {
  queue: <?= json_encode($queue, JSON_UNESCAPED_UNICODE) ?>,
  session: <?= json_encode($session, JSON_UNESCAPED_UNICODE) ?>,
  attention: <?= json_encode($attention, JSON_UNESCAPED_UNICODE) ?>,
  history: <?= json_encode($history, JSON_UNESCAPED_UNICODE) ?>,
  undo: <?= json_encode($undoInfo, JSON_UNESCAPED_UNICODE) ?>,
  archives: <?= json_encode($archives, JSON_UNESCAPED_UNICODE) ?>
};
let timerLive = false, timerStartedAt = null;
let ctxTwitch = null, ctxMatch = null;

function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.style.display='block';setTimeout(()=>el.style.display='none',2600)}
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function isGuest(p){return ['1','true','True'].includes(String(p.is_guest||''))}
function displayName(p){return p.fsl_name || (isGuest(p)?'NEW GUEST':(p.twitch_name||''))}
function modeClass(m){m=(m||'1v1').toLowerCase();if(m.includes('2'))return'mode-2v2';if(m.includes('3'))return'mode-3v3';if(m.includes('custom'))return'mode-custom';return'mode-1v1'}
function waitLabel(joined){
  if(!joined)return'—';
  const mins=Math.max(0,Math.floor((Date.now()-Date.parse(joined))/60000));
  if(mins<1)return'<1m'; if(mins<60)return mins+'m'; return Math.floor(mins/60)+'h '+(mins%60)+'m';
}
function waitClass(w){
  if(String(w).includes('h'))return'wait-long';
  if(String(w).endsWith('m')&&!String(w).startsWith('<')){const n=parseInt(w,10);if(n>=30)return'wait-long';if(n>=15)return'wait-medium'}
  return'wait-normal';
}
function findPlayer(twitch){
  const key=String(twitch||'').toLowerCase();
  for(const p of state.queue||[]) if(String(p.twitch_name||'').toLowerCase()===key) return p;
  for(const mk of ['playing','on_deck']){
    const m=state.session[mk]||{};
    for(const side of ['side_a','side_b']) for(const p of (m[side]||[])) if(String(p.twitch_name||'').toLowerCase()===key) return p;
  }
  return null;
}

function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>closeModal(b.dataset.close));
document.querySelectorAll('.modal-back').forEach(b=>b.addEventListener('click',e=>{if(e.target===b)b.classList.remove('open')}));

function openAddModal(){
  document.getElementById('player-modal-title').textContent='Add player';
  document.getElementById('pf-mode').value='add';
  document.getElementById('player-form').reset();
  document.getElementById('pf-twitch').disabled=false;
  openModal('modal-player');
  document.getElementById('pf-twitch').focus();
}
function openEditPlayer(twitch){
  const p=findPlayer(twitch); if(!p) return;
  document.getElementById('player-modal-title').textContent='Edit player';
  document.getElementById('pf-mode').value='edit';
  document.getElementById('pf-twitch').value=p.twitch_name||'';
  document.getElementById('pf-twitch').disabled=true;
  document.getElementById('pf-fsl').value=p.fsl_name||'';
  document.getElementById('pf-race').value=p.race||'';
  document.getElementById('pf-match-mode').value=p.match_mode||'1v1';
  document.getElementById('pf-rank').value=p.fsl_rank||'';
  document.getElementById('pf-group').value=p.fsl_group||'';
  document.getElementById('pf-skill').value=p.skill||'';
  document.getElementById('pf-custom').value=p.custom_type||'';
  document.getElementById('pf-guest').checked=isGuest(p);
  openModal('modal-player');
}
function openEditMatch(which){
  const m=state.session[which]||{};
  document.getElementById('match-modal-title').textContent=which==='playing'?'Edit current match':'Edit on deck';
  document.getElementById('mf-which').value=which;
  document.getElementById('mf-bo').value=m.best_of||3;
  document.getElementById('mf-mode').value=m.mode||'1v1';
  document.getElementById('mf-sa').value=m.score_a||0;
  document.getElementById('mf-sb').value=m.score_b||0;
  const scoreFields=which==='playing';
  document.getElementById('mf-sa').closest('label').style.display=scoreFields?'':'none';
  document.getElementById('mf-sb').closest('label').style.display=scoreFields?'':'none';
  openModal('modal-match');
}

function hideCtx(){document.getElementById('ctx').classList.remove('open')}
function showCtx(x,y,html){
  const el=document.getElementById('ctx');
  el.innerHTML=html; el.classList.add('open');
  const pad=8; let left=x, top=y;
  requestAnimationFrame(()=>{
    const r=el.getBoundingClientRect();
    if(left+r.width>window.innerWidth-pad) left=window.innerWidth-r.width-pad;
    if(top+r.height>window.innerHeight-pad) top=window.innerHeight-r.height-pad;
    el.style.left=Math.max(pad,left)+'px';
    el.style.top=Math.max(pad,top)+'px';
  });
}

function playerCtxHtml(twitch){
  return `
    <button data-act="edit">Edit…</button>
    <button data-act="select">Select → On Deck</button>
    <div class="sep"></div>
    <button data-act="move" data-dest="ondeck_a">Move → On Deck A</button>
    <button data-act="move" data-dest="ondeck_b">Move → On Deck B</button>
    <button data-act="move" data-dest="playing_a">Move → Playing A</button>
    <button data-act="move" data-dest="playing_b">Move → Playing B</button>
    <button data-act="move" data-dest="waiting">Move → Waiting</button>
    <div class="sep"></div>
    <button class="danger" data-act="delete-player">Delete player</button>`;
}
function matchCtxHtml(which){
  const live = !!(state.session.playing && state.session.playing.live);
  return `
    <button data-act="edit-match">Edit match…</button>
    ${which==='playing' ? (
      live
        ? `<button data-act="undo-live">Undo live</button><button data-act="end">End match</button>`
        : `<button data-act="live">Mark live</button><button data-act="end">End match</button>`
    ) : ''}
    ${which==='on_deck'?`<button data-act="promote">Pull to Playing</button>`:''}
    <div class="sep"></div>
    <button class="danger" data-act="clear-match">Clear / delete match slot</button>`;
}

document.addEventListener('click', hideCtx);
document.addEventListener('contextmenu', e=>{
  const playerEl=e.target.closest('[data-twitch]');
  const matchEl=e.target.closest('[data-match]');
  if(playerEl){
    e.preventDefault();
    ctxTwitch=playerEl.dataset.twitch; ctxMatch=null;
    showCtx(e.clientX,e.clientY,playerCtxHtml(ctxTwitch));
    return;
  }
  if(matchEl){
    e.preventDefault();
    ctxMatch=matchEl.dataset.match; ctxTwitch=null;
    showCtx(e.clientX,e.clientY,matchCtxHtml(ctxMatch));
  }
});
document.getElementById('ctx').addEventListener('click', async e=>{
  const btn=e.target.closest('button'); if(!btn) return;
  e.stopPropagation(); hideCtx();
  const act=btn.dataset.act;
  if(act==='edit'&&ctxTwitch) openEditPlayer(ctxTwitch);
  if(act==='select'&&ctxTwitch) api({action:'select',twitch_name:ctxTwitch});
  if(act==='move'&&ctxTwitch) api({action:'move',twitch_name:ctxTwitch,dest:btn.dataset.dest});
  if(act==='delete-player'&&ctxTwitch){
    if(confirm('Delete @'+ctxTwitch+' from the board (Waiting / Playing / On Deck)?'))
      api({action:'delete_player',twitch_name:ctxTwitch});
  }
  if(act==='edit-match'&&ctxMatch) openEditMatch(ctxMatch);
  if(act==='live') api({action:'go_live'});
  if(act==='undo-live') api({action:'undo_live'});
  if(act==='end'){ if(confirm('End match and save scores? Players leave (not requeued).')) api({action:'end_match'}); }
  if(act==='promote') api({action:'promote'});
  if(act==='clear-match'&&ctxMatch){
    if(confirm('Clear this match slot? Players are removed (not sent to Waiting).'))
      api({action:'clear_match',which:ctxMatch});
  }
});

async function api(payload){
  try{
    const res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await res.json();
    if(!data.ok){ toast(data.error||'Failed'); return false; }
    if(data.queue) state.queue=data.queue;
    if(data.session) state.session=data.session;
    if(data.attention) state.attention=data.attention;
    if(data.history) state.history=data.history;
    if(data.undo!==undefined) state.undo=data.undo;
    if(data.archives) state.archives=data.archives;
    renderAll();
    if(data.public) toast(data.public);
    return true;
  }catch(e){ toast('Request failed'); return false; }
}

function initials(p){
  const n=displayName(p)||'?';
  const parts=String(n).trim().split(/\s+/);
  if(parts.length>=2) return (parts[0][0]+parts[1][0]).toUpperCase();
  return String(n).slice(0,2).toUpperCase();
}
function thumbHtml(p, cls){
  const url=p.thumb_url||'';
  const ini=esc(initials(p));
  if(url){
    return `<div class="avatar-wrap"><img class="${cls}" src="${esc(url)}" alt="" loading="lazy" decoding="async" onerror="this.style.display='none';var f=this.parentNode.querySelector('.avatar-fallback');if(f)f.style.display='flex'"><div class="avatar-fallback" style="display:none">${ini}</div></div>`;
  }
  return `<div class="avatar-wrap"><div class="avatar-fallback">${ini}</div></div>`;
}
function teamLogoHtml(p, cls){
  const url=p.team_logo_url||'';
  if(!url) return '';
  return `<img class="${cls}" src="${esc(url)}" alt="" title="${esc(p.team||'')}" loading="lazy" decoding="async" onerror="this.style.display='none'">`;
}
function chip(p){
  const sub=[p.race,p.fsl_rank?'#'+p.fsl_rank:'',p.fsl_group?'G'+p.fsl_group:''].filter(Boolean).join(' · ');
  return `<div class="chip" draggable="true" data-twitch="${esc(p.twitch_name)}">
    ${thumbHtml(p,'chip-thumb')}
    <div class="chip-body"><strong>${esc(displayName(p))}</strong><span>${esc(sub||'@'+p.twitch_name)}</span></div>
    ${teamLogoHtml(p,'chip-team')}
  </div>`;
}
function renderSide(el, players){
  el.innerHTML=(players&&players.length)?players.map(chip).join(''):'<div class="empty" style="padding:18px 6px;font-size:12px">Drop here</div>';
  bindDrag(el);
}
function renderQueue(){
  const q=state.queue||[];
  let guests=0, longest='—', longestMins=-1;
  q.forEach(p=>{
    if(isGuest(p)) guests++;
    const w=waitLabel(p.joined_at);
    const mins=w.includes('h')?999:(parseInt(w,10)||0);
    if(mins>longestMins){longestMins=mins;longest=w}
  });
  document.getElementById('stat-waiting').textContent=q.length;
  document.getElementById('stat-guests').textContent=guests;
  document.getElementById('stat-longest').textContent=longest;
  document.getElementById('stat-games').textContent=state.session.games_tonight||0;
  document.getElementById('queue-count').textContent=q.length+' players';
  const body=document.getElementById('queue-body');
  if(!q.length){ body.innerHTML='<div class="empty">No one waiting</div>'; return; }
  body.innerHTML=q.map(p=>{
    const w=waitLabel(p.joined_at);
    const rk=isGuest(p)
      ? `<div class="rank">${esc(p.skill||'Guest')}<small>estimate</small></div>`
      : `<div class="rank">${esc([p.fsl_rank?'#'+p.fsl_rank:'',p.fsl_group?'G'+p.fsl_group:''].filter(Boolean).join(' · ')||(p.skill||'FSL'))}<small>${esc(p.race||'')}</small></div>`;
    return `<div class="q-row ${isGuest(p)?'guest':''}" draggable="true" data-twitch="${esc(p.twitch_name)}">
      ${thumbHtml(p,'q-thumb')}
      <div class="mode ${modeClass(p.match_mode)}">${esc(p.match_mode||'1v1')}</div>
      <div>
        <div class="name-row">
          <div class="name">${esc(displayName(p))}${isGuest(p)?'<span class="badge">GUEST</span>':''}</div>
        </div>
        <div class="meta">${teamLogoHtml(p,'q-team-logo')}<span class="twitch">@${esc(p.twitch_name)}${p.team?' · '+esc(p.team):''}</span></div>
      </div>
      ${rk}
      <div class="wait ${waitClass(w)}">${esc(w)}</div>
    </div>`;
  }).join('');
  bindDrag(body);
}
function renderMatches(){
  const play=state.session.playing||{}, deck=state.session.on_deck||{};
  renderSide(document.getElementById('playing-a'), play.side_a||[]);
  renderSide(document.getElementById('playing-b'), play.side_b||[]);
  renderSide(document.getElementById('ondeck-a'), deck.side_a||[]);
  renderSide(document.getElementById('ondeck-b'), deck.side_b||[]);
  document.getElementById('playing-summary').innerHTML=`Bo${esc(play.best_of||3)} · ${esc(play.mode||'1v1')} · <strong>${esc(play.score_a||0)}–${esc(play.score_b||0)}</strong>`;
  document.getElementById('ondeck-summary').textContent=`Bo${deck.best_of||3} · ${deck.mode||'1v1'}`;

  const playPanel=document.getElementById('playing-panel');
  const pill=document.getElementById('playing-pill');
  const btnLive=document.getElementById('btn-live');
  const btnUndo=document.getElementById('btn-undo-live');
  const aN=(play.side_a||[]).length, bN=(play.side_b||[]).length;
  const hasBoth=aN>=1 && bN>=1;

  const playStatus=document.getElementById('playing-status');
  // Playing Now stays cyan always — live just adds extra glow
  if(play.live){
    playPanel.className='panel match state-playing state-live';
    if(playStatus) playStatus.className='status live';
    pill.className='pill pill-live';
    pill.textContent='● Live';
    btnLive.style.display='none';
    btnUndo.style.display='';
    timerLive=true;
    timerStartedAt=play.started_at?Date.parse(play.started_at):Date.now();
  } else {
    btnLive.style.display='';
    btnUndo.style.display='none';
    timerLive=false;
    playPanel.className='panel match state-playing';
    if(playStatus) playStatus.className='status live';
    if(aN+bN===0){
      pill.className='pill pill-idle';
      pill.textContent='Empty';
    } else if(hasBoth){
      pill.className='pill pill-ready';
      pill.textContent='Ready — Mark live';
    } else {
      pill.className='pill pill-idle';
      pill.textContent='Need both sides';
    }
  }

  const deckA=(deck.side_a||[]).length, deckB=(deck.side_b||[]).length;
  const deckPanel=document.getElementById('ondeck-panel');
  const deckPill=document.getElementById('ondeck-pill');
  if(deckA+deckB===0){
    deckPanel.className='panel match state-deck state-deck-empty';
    if(deckPill){ deckPill.className='pill pill-deck'; deckPill.textContent='Next'; }
  } else {
    deckPanel.className='panel match state-deck';
    if(deckPill){ deckPill.className='pill pill-deck'; deckPill.textContent='Next up'; }
  }

  const ranks=[];
  [...(deck.side_a||[]),...(deck.side_b||[])].forEach(p=>{if(p.fsl_group) ranks.push(+p.fsl_group)});
  document.getElementById('ondeck-hint').textContent=ranks.length>=2?('group Δ '+(Math.max(...ranks)-Math.min(...ranks))):'';
}

function histSideHtml(players, fallbackNames){
  const list=(players&&players.length)?players:null;
  if(list){
    return list.map(p=>{
      return `<span class="hist-side">${thumbHtml(p,'q-thumb')}${teamLogoHtml(p,'q-team-logo')}<span class="hist-pname" title="${esc(displayName(p))}">${esc(displayName(p))}</span></span>`;
    }).join('');
  }
  const names=String(fallbackNames||'').split(/\s*\+\s*|\s*,\s*/).map(s=>s.trim()).filter(Boolean);
  if(!names.length) return `<span class="hist-pname">?</span>`;
  return names.map(n=>{
    const fake={fsl_name:n, thumb_url:'', team_logo_url:'', team:''};
    return `<span class="hist-side">${thumbHtml(fake,'q-thumb')}<span class="hist-pname">${esc(n)}</span></span>`;
  }).join('');
}
function renderHistory(){
  const body=document.getElementById('history-body');
  const rows=state.history||[];
  if(!rows.length){
    body.innerHTML='<div class="empty" style="padding:16px">No finished matches yet</div>';
    return;
  }
  body.innerHTML=rows.map(r=>{
    const score=`${r.score_a||0}–${r.score_b||0}`;
    let when='';
    try{
      const d=new Date(r.ended_at);
      when=d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }catch(e){ when=''; }
    const winner=r.winner_side==='A'?'A won':(r.winner_side==='B'?'B won':(r.winner_side==='draw'?'Draw':''));
    const sideA=histSideHtml(r.side_a_players, r.side_a_names);
    const sideB=histSideHtml(r.side_b_players, r.side_b_names);
    return `<div class="hist-row" data-ended="${esc(r.ended_at||'')}" data-a="${esc(r.side_a_twitch||'')}" data-b="${esc(r.side_b_twitch||'')}">
      <div><span class="pill pill-ended">Ended</span></div>
      <div>
        <div class="hist-vs">${sideA}<span class="hist-sep">VS</span>${sideB}</div>
        <div class="hist-meta">${esc(r.mode||'')} · Bo${esc(r.best_of||3)}${winner?' · '+winner:''}${when?' · '+when:''}</div>
      </div>
      <div class="hist-score">${esc(score)}</div>
      <div class="hist-actions">
        <button type="button" class="hist-restore" title="Restore players to Waiting" data-act="restore-hist">↩</button>
        <button type="button" class="hist-del" title="Delete result" data-act="del-hist">×</button>
      </div>
    </div>`;
  }).join('');
}

function renderAttention(){
  const items=state.attention||[];
  const body=document.getElementById('attention-body');
  if(!items.length){ body.innerHTML='<div class="empty" style="padding:12px 0">All clear</div>'; return; }
  body.innerHTML=items.map(it=>`<div class="attn-row"><div><div>${esc(it.name)}</div><div class="why">${esc(it.reason)}</div></div><div class="wait ${esc(it.wait_class||'')}">${esc(it.wait)}</div></div>`).join('');
}

function renderAll(){ renderQueue(); renderMatches(); renderHistory(); renderAttention(); renderUndo(); renderArchives(); }

function renderArchives(){
  const sel=document.getElementById('archive-select');
  const loadBtn=document.getElementById('btn-load-archive');
  const banner=document.getElementById('archive-banner');
  if(!sel) return;
  const keep=sel.value;
  const list=state.archives||[];
  sel.innerHTML='<option value="">Load archive…</option>'+list.map(a=>{
    const games=a.games||a.history_rows||0;
    const title=(a.label&&a.label!==a.id)?a.label:a.id;
    const extra=(a.label&&a.label!==a.id)?(' · '+a.id):'';
    const label=esc(title)+extra+(games?(' · '+games+'g'):'');
    return `<option value="${esc(a.id)}">${label}</option>`;
  }).join('');
  if(keep && [...sel.options].some(o=>o.value===keep)) sel.value=keep;
  if(loadBtn) loadBtn.disabled=!sel.value;
  const loaded=state.session&&state.session.loaded_archive;
  if(banner){
    if(loaded){
      banner.hidden=false;
      banner.textContent='Viewing archive '+loaded+' — End & archive night will save a new snapshot from this board, or keep editing then archive when done.';
    } else {
      banner.hidden=true;
      banner.textContent='';
    }
  }
}

function renderUndo(){
  const btn=document.getElementById('btn-undo');
  if(!btn) return;
  const u=state.undo;
  if(u && u.label){
    btn.disabled=false;
    const short=String(u.label).length>36 ? String(u.label).slice(0,34)+'…' : u.label;
    btn.textContent='Undo: '+short;
    btn.title=u.label+(u.depth>1?' ('+u.depth+' steps)':'');
  } else {
    btn.disabled=true;
    btn.textContent='Undo';
    btn.title='Nothing to undo';
  }
}

function bindDrag(root){
  root.querySelectorAll('[draggable=true]').forEach(el=>{
    el.addEventListener('dragstart',ev=>{
      el.classList.add('dragging');
      ev.dataTransfer.setData('text/plain', el.dataset.twitch||'');
      ev.dataTransfer.effectAllowed='move';
    });
    el.addEventListener('dragend',()=>el.classList.remove('dragging'));
  });
}
document.querySelectorAll('.drop-zone').forEach(zone=>{
  zone.addEventListener('dragover',ev=>{ev.preventDefault(); zone.classList.add('drop-hover')});
  zone.addEventListener('dragleave',()=>zone.classList.remove('drop-hover'));
  zone.addEventListener('drop',ev=>{
    ev.preventDefault(); zone.classList.remove('drop-hover');
    const twitch=ev.dataTransfer.getData('text/plain');
    if(twitch&&zone.dataset.dest) api({action:'move',twitch_name:twitch,dest:zone.dataset.dest});
  });
});

document.getElementById('btn-add').onclick=openAddModal;
document.getElementById('btn-live').onclick=()=>api({action:'go_live'});
document.getElementById('btn-undo-live').onclick=()=>api({action:'undo_live'});
document.getElementById('btn-undo').onclick=()=>{
  const label=(state.undo&&state.undo.label)||'last action';
  if(confirm('Undo: '+label+'?')) api({action:'undo'});
};
document.getElementById('btn-promote').onclick=()=>api({action:'promote'});
document.getElementById('btn-end').onclick=()=>{if(confirm('End match and save to CSV? Players leave (not requeued).\n\nYou can Undo if this was a mistake.')) api({action:'end_match'})};
document.getElementById('btn-clear').onclick=()=>{if(confirm('Clear waiting queue?')) api({action:'clear_queue'})};
document.getElementById('btn-clear-history').onclick=()=>{
  if(confirm('Delete ALL results history?')) api({action:'clear_history'});
};
document.getElementById('btn-archive-night').onclick=()=>{
  const games=(state.session&&state.session.games_tonight)||0;
  const hist=(state.history||[]).length;
  const label=prompt(
    'Label this archive (optional).\n\nFolder becomes e.g. 2026-07-24_open-cast\nSame day + same label gets a time suffix.\n\nGames tonight: '+games+' · Results: '+hist,
    ''
  );
  if(label===null) return; // cancelled
  if(!confirm('End & archive this night'+(label.trim()?' as “'+label.trim()+'”':'')+'?\n\nResets the live board after saving.')) return;
  api({action:'archive_night', label:label.trim()});
};
document.getElementById('archive-select').onchange=function(){
  const btn=document.getElementById('btn-load-archive');
  if(btn) btn.disabled=!this.value;
};
document.getElementById('btn-load-archive').onclick=()=>{
  const id=document.getElementById('archive-select').value;
  if(!id) return;
  if(!confirm('Load archive '+id+'?\n\nThis replaces the current live board. Archive the current night first if you still need it.')) return;
  api({action:'load_archive', archive_id:id});
};
document.getElementById('history-body').addEventListener('click',e=>{
  const restoreBtn=e.target.closest('[data-act="restore-hist"]');
  const delBtn=e.target.closest('[data-act="del-hist"]');
  const row=(restoreBtn||delBtn)?.closest('.hist-row');
  if(!row) return;
  e.stopPropagation();
  const payload={
    ended_at:row.dataset.ended||'',
    side_a_twitch:row.dataset.a||'',
    side_b_twitch:row.dataset.b||'',
  };
  if(restoreBtn){
    if(!confirm('Restore these players to Waiting?\n(Result is removed — you can drag them into Playing / Next after.)')) return;
    api({action:'restore_history', dest:'waiting', ...payload});
    return;
  }
  if(delBtn){
    if(!confirm('Delete this result?')) return;
    api({action:'delete_history', ...payload});
  }
});
document.getElementById('history-body').addEventListener('contextmenu',e=>{
  const row=e.target.closest('.hist-row');
  if(!row) return;
  e.preventDefault();
  ctxTwitch=null; ctxMatch=null;
  const ended=esc(row.dataset.ended||'');
  const a=esc(row.dataset.a||'');
  const b=esc(row.dataset.b||'');
  showCtx(e.clientX,e.clientY,`
    <button data-act="restore-hist-ctx" data-dest="waiting" data-ended="${ended}" data-a="${a}" data-b="${b}">↩ Restore → Waiting</button>
    <button data-act="restore-hist-ctx" data-dest="playing" data-ended="${ended}" data-a="${a}" data-b="${b}">↩ Restore → Playing Now</button>
    <button data-act="restore-hist-ctx" data-dest="on_deck" data-ended="${ended}" data-a="${a}" data-b="${b}">↩ Restore → Next Match</button>
    <button class="danger" data-act="del-hist-ctx" data-ended="${ended}" data-a="${a}" data-b="${b}">Delete result</button>
  `);
});
// Extend ctx handler for history delete / restore from context menu
document.getElementById('ctx').addEventListener('click', e=>{
  const restore=e.target.closest('[data-act="restore-hist-ctx"]');
  if(restore){
    e.stopPropagation(); hideCtx();
    const dest=restore.dataset.dest||'waiting';
    const labels={waiting:'Waiting', playing:'Playing Now', on_deck:'Next Match'};
    if(!confirm('Restore this result → '+(labels[dest]||dest)+'?')) return;
    api({
      action:'restore_history',
      dest,
      ended_at:restore.dataset.ended||'',
      side_a_twitch:restore.dataset.a||'',
      side_b_twitch:restore.dataset.b||'',
    });
    return;
  }
  const btn=e.target.closest('[data-act="del-hist-ctx"]');
  if(!btn) return;
  e.stopPropagation(); hideCtx();
  if(!confirm('Delete this result?')) return;
  api({
    action:'delete_history',
    ended_at:btn.dataset.ended||'',
    side_a_twitch:btn.dataset.a||'',
    side_b_twitch:btn.dataset.b||'',
  });
});

document.getElementById('player-form').onsubmit=async ev=>{
  ev.preventDefault();
  const mode=document.getElementById('pf-mode').value;
  const twitch=document.getElementById('pf-twitch').value.trim().replace(/^@/,'');
  if(!twitch){ toast('Twitch name required'); return; }
  const payload={
    action: mode==='edit'?'update_player':'add_player',
    twitch_name:twitch,
    fsl_name:document.getElementById('pf-fsl').value.trim(),
    race:document.getElementById('pf-race').value,
    match_mode:document.getElementById('pf-match-mode').value,
    fsl_rank:document.getElementById('pf-rank').value.trim(),
    fsl_group:document.getElementById('pf-group').value.trim(),
    skill:document.getElementById('pf-skill').value.trim(),
    custom_type:document.getElementById('pf-custom').value.trim(),
    is_guest:document.getElementById('pf-guest').checked?'1':'0',
  };
  if(await api(payload)){ closeModal('modal-player'); toast(mode==='edit'?'Updated':'Added @'+twitch); }
};
document.getElementById('match-form').onsubmit=async ev=>{
  ev.preventDefault();
  const which=document.getElementById('mf-which').value;
  const payload={
    action:'meta', which,
    best_of:document.getElementById('mf-bo').value,
    mode:document.getElementById('mf-mode').value,
    score_a:document.getElementById('mf-sa').value,
    score_b:document.getElementById('mf-sb').value,
  };
  if(await api(payload)){ closeModal('modal-match'); toast('Match updated'); }
};

// Double-click player = edit
document.addEventListener('dblclick',e=>{
  const el=e.target.closest('[data-twitch]');
  if(el) openEditPlayer(el.dataset.twitch);
});

function tickTimer(){
  const el=document.getElementById('gameTimer');
  if(!timerLive||!timerStartedAt){ if(!timerLive) el.textContent='00:00'; return; }
  const sec=Math.max(0,Math.floor((Date.now()-timerStartedAt)/1000));
  el.textContent=String(Math.floor(sec/60)).padStart(2,'0')+':'+String(sec%60).padStart(2,'0');
}
setInterval(tickTimer,1000);
async function refresh(){
  try{
    const res=await fetch(API); const data=await res.json();
    if(data.ok){
      state.queue=data.queue;
      state.session=data.session;
      state.attention=data.attention;
      if(data.history) state.history=data.history;
      if(data.undo!==undefined) state.undo=data.undo;
      if(data.archives) state.archives=data.archives;
      renderAll();
    }
  }catch(e){}
}
setInterval(refresh,8000);
renderAll();
</script>
</body>
</html>
