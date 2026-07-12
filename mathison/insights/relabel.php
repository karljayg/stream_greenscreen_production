<?php
/**
 * Mathison Insights relabeler
 * ===========================
 *
 * Reads human strategy comments from two sources and converts them into
 * structured rows:
 *
 *   insight_tactics  PRIMARY unit — compound chains like
 *                    "3 hatch · ling bane · all in"
 *   insight_tags     atomic building blocks for secondary filters
 *
 * Sources:
 *   1. Replays.Player_Comments
 *   2. PatternLearning.metadata JSON ($.comment)
 *
 * Usage (CLI only):
 *   php relabel.php             full rebuild
 *   php relabel.php --dry-run   classify + print summary, write nothing
 *
 * Design decisions (read this before changing things)
 * ----------------------------------------------------
 * COMPOUND TACTICS ARE THE POINT. A comment is a plan, not a bag of
 * words. "3 hatch ling bane all in" means one aggressive ZvZ plan —
 * not three unrelated labels. Atomic tags are still written so you can
 * filter ("any all-in"), but dashboards and scouting reports lead with
 * tactic_label chains.
 *
 * FULL REBUILD, NOT INCREMENTAL. Every run wipes insight_tags /
 * insight_tactics and re-classifies everything. Editing rules.php
 * therefore rewrites ALL historical tactics on the next run.
 *
 * SOURCES ARE NEVER WRITTEN TO. Replays and PatternLearning are
 * read-only. Output goes only to insight_* tables.
 *
 * VOCABULARY LIVES IN rules.php. This file only: fetch comments, split
 * "X to Y" phases, detect atomic tags, chain them into ordered tactics.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "relabel.php is a CLI tool.\n");
    exit(1);
}

require __DIR__ . '/../db.php';

$config = require __DIR__ . '/rules.php';
$dryRun = in_array('--dry-run', $argv, true);

$pdo = mathison_pdo();

ensure_schema($pdo);

// ---------------------------------------------------------------------------
// 1. Collect source rows (comment + context) from both tables
// ---------------------------------------------------------------------------

$sources = array_merge(
    fetch_replay_sources($pdo, $config),
    fetch_pattern_sources($pdo, $config)
);

// ---------------------------------------------------------------------------
// 2. Classify every comment: atomic tags + compound tactics
// ---------------------------------------------------------------------------

$tagRows = [];
$tacticRows = [];
$unmatched = [];
foreach ($sources as $src) {
    $classified = classify_comment($src['comment'], $config);
    if (!$classified['tags'] && !$classified['tactics']) {
        $unmatched[] = $src;
        continue;
    }
    foreach ($classified['tags'] as $t) {
        $tagRows[] = array_merge($src, $t);
    }
    foreach ($classified['tactics'] as $tac) {
        $tacticRows[] = array_merge($src, $tac);
    }
}

// ---------------------------------------------------------------------------
// 3. Report / write
// ---------------------------------------------------------------------------

$replaysScanned = count(array_filter($sources, fn($s) => $s['source'] === 'replay'));
$patternsScanned = count(array_filter($sources, fn($s) => $s['source'] === 'pattern'));

echo "Sources: {$replaysScanned} replay comments, {$patternsScanned} pattern comments\n";
echo "Atomic tag rows: " . count($tagRows) . "\n";
echo "Compound tactics: " . count($tacticRows) . "\n";
echo "Comments with no match: " . count($unmatched) . "\n";

$workerFreq = [];
foreach ($tacticRows as $t) {
    $w = $t['start_workers'] ?? 'null';
    $workerFreq[$w] = ($workerFreq[$w] ?? 0) + 1;
}
ksort($workerFreq);
echo "--- start workers on tactics ---\n";
foreach ($workerFreq as $w => $n) {
    echo "  {$w}-worker: {$n}\n";
}

// Show the most common compound tactics so the operator can review
// whether chains look right (e.g. "3 hatch · ling bane · all in").
$tacticFreq = [];
foreach ($tacticRows as $t) {
    $tacticFreq[$t['tactic_label']] = ($tacticFreq[$t['tactic_label']] ?? 0) + 1;
}
arsort($tacticFreq);
echo "--- top compound tactics ---\n";
foreach (array_slice($tacticFreq, 0, 15, true) as $label => $n) {
    echo "  {$n}x  {$label}\n";
}

if ($unmatched) {
    echo "--- first 15 unmatched (candidates for new rules) ---\n";
    foreach (array_slice($unmatched, 0, 15) as $u) {
        echo "  [{$u['source']} {$u['source_id']}] {$u['comment']}\n";
    }
}

if ($dryRun) {
    echo "DRY RUN - nothing written.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $runId = start_run($pdo, $replaysScanned, $patternsScanned);

    $pdo->exec('DELETE FROM insight_tags');
    $pdo->exec('DELETE FROM insight_tactics');

    $insTag = $pdo->prepare(
        'INSERT INTO insight_tags
         (run_id, source, source_id, replay_id, opponent_name, opponent_race,
          self_result, date_played, comment, tag, category, phase, start_workers)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    foreach ($tagRows as $r) {
        $insTag->execute([
            $runId, $r['source'], $r['source_id'], $r['replay_id'],
            $r['opponent_name'], $r['opponent_race'], $r['self_result'],
            $r['date_played'], $r['comment'], $r['tag'], $r['category'], $r['phase'],
            $r['start_workers'],
        ]);
    }

    $insTac = $pdo->prepare(
        'INSERT INTO insight_tactics
         (run_id, source, source_id, replay_id, opponent_name, opponent_race,
          self_result, date_played, comment, phase, tactic_key, tactic_label,
          parts_json, parts_count, start_workers)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    foreach ($tacticRows as $r) {
        $insTac->execute([
            $runId, $r['source'], $r['source_id'], $r['replay_id'],
            $r['opponent_name'], $r['opponent_race'], $r['self_result'],
            $r['date_played'], $r['comment'], $r['phase'],
            $r['tactic_key'], $r['tactic_label'],
            json_encode($r['parts'], JSON_UNESCAPED_UNICODE),
            $r['parts_count'],
            $r['start_workers'],
        ]);
    }

    finish_run($pdo, $runId, count($tagRows), count($tacticRows));
    $pdo->commit();
    echo "Run #{$runId} committed: " . count($tagRows) . " tags, " . count($tacticRows) . " tactics.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}

exit(0);

// ===========================================================================
// Classification
// ===========================================================================

/**
 * Classify one comment into atomic tags AND compound tactics.
 *
 * Returns:
 *   tags:    [{tag, category, phase}, ...]
 *   tactics: [{phase, tactic_key, tactic_label, parts, parts_count}, ...]
 *
 * Chaining rule: within each phase segment, collect every atomic match,
 * order by category_order (economy → opening → composition → intent),
 * and if parts_count >= min_chain_parts, emit one compound tactic.
 *
 * Example: "3 hatch ling bane all in"
 *   tags    = 3_hatch, ling_bane, all_in
 *   tactic  = "3 hatch · ling bane · all in"
 *
 * Example: "reaper to speed banshee BC"
 *   early tactic = (maybe single-part → skipped if min_chain_parts=2)
 *   late tactic  = "banshee · battlecruiser" (if both match)
 */
function classify_comment(string $comment, array $config): array
{
    $comment = trim($comment);
    if ($comment === '') {
        return ['tags' => [], 'tactics' => []];
    }

    $tw = $config['transition_word'];
    $pos = stripos($comment, $tw);
    $segments = [];
    if ($pos !== false) {
        $segments[] = ['text' => substr($comment, 0, $pos), 'phase' => 'early'];
        $segments[] = ['text' => substr($comment, $pos + strlen($tw)), 'phase' => 'late'];
    } else {
        $segments[] = ['text' => $comment, 'phase' => 'any'];
    }

    $allTags = [];
    $tactics = [];
    $minParts = (int) ($config['min_chain_parts'] ?? 2);

    foreach ($segments as $seg) {
        $matched = match_atomic_tags($seg['text'], $config['rules']);
        foreach ($matched as $m) {
            $allTags[$m['tag']] = [
                'tag' => $m['tag'],
                'category' => $m['category'],
                'phase' => $seg['phase'],
            ];
        }

        $ordered = order_parts($matched, $config['category_order']);
        if (count($ordered) >= $minParts) {
            $tactics[] = build_tactic($ordered, $seg['phase']);
        }
    }

    // If the comment had a transition but neither phase reached min_parts,
    // try chaining the whole comment once so short "X to Y" notes still
    // produce a tactic when both sides contribute a tag.
    if (!$tactics && count($segments) > 1) {
        $matched = match_atomic_tags($comment, $config['rules']);
        $ordered = order_parts($matched, $config['category_order']);
        if (count($ordered) >= $minParts) {
            $tactics[] = build_tactic($ordered, 'any');
            foreach ($matched as $m) {
                if (!isset($allTags[$m['tag']])) {
                    $allTags[$m['tag']] = [
                        'tag' => $m['tag'],
                        'category' => $m['category'],
                        'phase' => 'any',
                    ];
                }
            }
        }
    }

    return [
        'tags' => array_values($allTags),
        'tactics' => $tactics,
    ];
}

/** Match every atomic rule against a text segment. */
function match_atomic_tags(string $text, array $rules): array
{
    $found = [];
    foreach ($rules as $rule) {
        foreach ($rule['patterns'] as $pattern) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $found[$rule['tag']] = [
                    'tag' => $rule['tag'],
                    'label' => $rule['label'] ?? str_replace('_', ' ', $rule['tag']),
                    'category' => $rule['category'],
                ];
                break;
            }
        }
    }
    return array_values($found);
}

/** Sort matched parts into a readable SC2 phrase order. */
function order_parts(array $matched, array $categoryOrder): array
{
    $rank = array_flip($categoryOrder);
    usort($matched, function ($a, $b) use ($rank) {
        $ra = $rank[$a['category']] ?? 99;
        $rb = $rank[$b['category']] ?? 99;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return strcmp($a['tag'], $b['tag']);
    });
    return $matched;
}

function build_tactic(array $orderedParts, string $phase): array
{
    $keys = array_column($orderedParts, 'tag');
    $labels = array_column($orderedParts, 'label');
    return [
        'phase' => $phase,
        'tactic_key' => implode('|', $keys),
        'tactic_label' => implode(' · ', $labels),
        'parts' => $orderedParts,
        'parts_count' => count($orderedParts),
    ];
}

// ===========================================================================
// Source readers - each returns rows shaped as:
// [source, source_id, replay_id, opponent_name, opponent_race,
//  self_result, date_played, comment, start_workers]
// ===========================================================================

/**
 * Commented replays. Opponent = the player whose name is not one of
 * our self accounts (see file docblock).
 */
function fetch_replay_sources(PDO $pdo, array $config): array
{
    $selfAccounts = $config['self_accounts'];
    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $stmt = $pdo->query(
        "SELECT r.ReplayId, r.Player_Comments, r.Date_Played, r.Replay_Summary,
                r.Player1_Result, r.Player2_Result, r.Player1_Race, r.Player2_Race,
                p1.SC2_UserId AS p1name, p2.SC2_UserId AS p2name
         FROM $R r
         LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
         LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
         WHERE r.Player_Comments IS NOT NULL AND r.Player_Comments <> ''"
    );

    $selfLower = array_map('strtolower', $selfAccounts);
    $out = [];
    foreach ($stmt as $row) {
        $p1self = in_array(strtolower((string) $row['p1name']), $selfLower, true);
        $p2self = in_array(strtolower((string) $row['p2name']), $selfLower, true);

        if ($p1self && !$p2self) {
            $opponent = $row['p2name'];
            $oppRace = $row['Player2_Race'];
            $selfResult = $row['Player1_Result'];
        } elseif ($p2self && !$p1self) {
            $opponent = $row['p1name'];
            $oppRace = $row['Player1_Race'];
            $selfResult = $row['Player2_Result'];
        } else {
            $opponent = null;
            $oppRace = null;
            $selfResult = null;
        }

        $out[] = [
            'source' => 'replay',
            'source_id' => (string) $row['ReplayId'],
            'replay_id' => (int) $row['ReplayId'],
            'opponent_name' => $opponent,
            'opponent_race' => $oppRace ? strtolower($oppRace) : null,
            'self_result' => $selfResult,
            'date_played' => $row['Date_Played'],
            'comment' => $row['Player_Comments'],
            'start_workers' => detect_start_workers(
                (string) ($row['Replay_Summary'] ?? ''),
                $row['Date_Played'],
                $config
            ),
        ];
    }
    return $out;
}

/**
 * PatternLearning rows that carry a comment in metadata JSON.
 *
 * game_data.date is a unix timestamp in newer rows and a "Y-m-d H:i:s"
 * string in older ones; both are normalized to unix time and used to
 * link back to Replays.UnixTimestamp when possible.
 *
 * Starting workers: prefer first supply in metadata.build_order; else
 * linked Replay_Summary; else date era.
 */
function fetch_pattern_sources(PDO $pdo, array $config): array
{
    $PL = mathison_table('PatternLearning');
    $stmt = $pdo->query(
        "SELECT pattern_id,
                opponent_race,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.comment')) AS comment,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.game_data.opponent_name')) AS opponent_name,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.game_data.result')) AS result,
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.game_data.date')) AS gdate,
                JSON_EXTRACT(metadata, '$.game_data.build_order') AS build_order
         FROM $PL
         WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.comment')) IS NOT NULL"
    );

    $R = mathison_table('Replays');
    $tsMap = $pdo->query("SELECT UnixTimestamp, ReplayId FROM $R")
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    // Lazy summary lookup - do NOT load all Replay_Summary blobs into memory.
    $summaryStmt = $pdo->prepare("SELECT Replay_Summary FROM $R WHERE ReplayId = ?");

    $resultMap = ['victory' => 'Win', 'defeat' => 'Lose', 'observed' => 'Observed'];

    $out = [];
    foreach ($stmt as $row) {
        $comment = trim((string) $row['comment']);
        if ($comment === '' || strtolower($comment) === 'null') {
            continue;
        }

        $ts = null;
        $gdate = (string) $row['gdate'];
        if ($gdate !== '' && $gdate !== 'null') {
            $ts = ctype_digit($gdate) ? (int) $gdate : (strtotime($gdate) ?: null);
        }

        $opponent = $row['opponent_name'];
        if ($opponent === 'null' || $opponent === 'Unknown' || $opponent === '') {
            $opponent = null;
        }

        $replayId = ($ts !== null && isset($tsMap[$ts])) ? (int) $tsMap[$ts] : null;
        $datePlayed = $ts !== null ? date('Y-m-d H:i:s', $ts) : null;

        $fromBo = detect_start_workers_from_build_json($row['build_order']);
        $summary = '';
        if ($fromBo === null && $replayId !== null) {
            $summaryStmt->execute([$replayId]);
            $summary = (string) ($summaryStmt->fetchColumn() ?: '');
        }
        $startWorkers = resolve_start_workers($fromBo, $summary, $datePlayed, $config);

        $out[] = [
            'source' => 'pattern',
            'source_id' => $row['pattern_id'],
            'replay_id' => $replayId,
            'opponent_name' => $opponent,
            'opponent_race' => $row['opponent_race'] !== '' ? strtolower((string) $row['opponent_race']) : null,
            'self_result' => $resultMap[strtolower((string) $row['result'])] ?? null,
            'date_played' => $datePlayed,
            'comment' => $comment,
            'start_workers' => $startWorkers,
        ];
    }
    return $out;
}

/**
 * Resolve starting worker count for a game.
 *
 * Prefer parsed supply (6/8/12) when it agrees with the date-era expectation,
 * or when no date is available. Odd parses (e.g. Supply: 6 in 2026) fall
 * back to the date era so timings stay in the correct bucket.
 */
function detect_start_workers(string $summary, ?string $datePlayed, array $config): ?int
{
    $parsed = detect_start_workers_from_summary($summary);
    return resolve_start_workers($parsed, $summary, $datePlayed, $config);
}

function resolve_start_workers(?int $parsed, string $summary, ?string $datePlayed, array $config): ?int
{
    $byDate = start_workers_from_date($datePlayed, $config['worker_eras'] ?? []);
    if ($parsed === null) {
        return $byDate;
    }
    if ($byDate === null || $parsed === $byDate) {
        return $parsed;
    }
    // Parsed disagrees with era (bad/corrupt first line) -> trust the era.
    return $byDate;
}

/** First Probe/SCV/Drone Supply in a Replay_Summary build order. */
function detect_start_workers_from_summary(string $summary): ?int
{
    if ($summary === '') {
        return null;
    }
    if (!preg_match(
        '/Build Order.*?\nTime:\s*\d+:\d+,\s*Name:\s*(?:Probe|SCV|Drone),\s*Supply:\s*(6|8|12)\b/is',
        $summary,
        $m
    )) {
        return null;
    }
    return (int) $m[1];
}

/** First worker supply from PatternLearning metadata.build_order JSON. */
function detect_start_workers_from_build_json($json): ?int
{
    if ($json === null || $json === '' || $json === 'null') {
        return null;
    }
    $steps = is_string($json) ? json_decode($json, true) : $json;
    if (!is_array($steps)) {
        return null;
    }
    foreach ($steps as $step) {
        $name = $step['name'] ?? '';
        if (!in_array($name, ['Probe', 'SCV', 'Drone'], true)) {
            continue;
        }
        $sup = isset($step['supply']) ? (int) $step['supply'] : 0;
        if (in_array($sup, [6, 8, 12], true)) {
            return $sup;
        }
        break;
    }
    return null;
}

/** Map Date_Played onto rules.php worker_eras (until is exclusive). */
function start_workers_from_date(?string $datePlayed, array $eras): ?int
{
    if ($datePlayed === null || $datePlayed === '' || !$eras) {
        return null;
    }
    $day = substr($datePlayed, 0, 10);
    foreach ($eras as $era) {
        $until = $era['until'] ?? null;
        if ($until === null || $day < $until) {
            return (int) $era['workers'];
        }
    }
    return null;
}

// ===========================================================================
// Schema + run bookkeeping
// ===========================================================================

/** Apply schema_changes.sql and ensure upgrade columns exist. */
function ensure_schema(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/schema_changes.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (preg_match('/^(--|$)/', strtok($statement, "\n")) && !preg_match('/CREATE TABLE/i', $statement)) {
            continue;
        }
        $pdo->exec($statement);
    }

    // Upgrade path: first schema lacked tactics_written / insight_tactics / start_workers.
    $cols = $pdo->query("SHOW COLUMNS FROM insight_runs LIKE 'tactics_written'")->fetch();
    if (!$cols) {
        $pdo->exec('ALTER TABLE insight_runs ADD COLUMN tactics_written INT NOT NULL DEFAULT 0 AFTER tags_written');
    }
    foreach (['insight_tags', 'insight_tactics'] as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
        if (!$exists) {
            continue;
        }
        $col = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'start_workers'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN start_workers TINYINT NULL AFTER " .
                ($table === 'insight_tags' ? 'phase' : 'parts_count'));
        }
    }
}

/** Insert the run row up-front so a crash leaves finished_at NULL (visible). */
function start_run(PDO $pdo, int $replaysScanned, int $patternsScanned): int
{
    $R = mathison_table('Replays');
    $PL = mathison_table('PatternLearning');

    $wm = $pdo->query(
        "SELECT
           (SELECT COUNT(*) FROM $R WHERE Player_Comments IS NOT NULL AND Player_Comments <> '') AS rc,
           (SELECT COALESCE(MAX(ReplayId),0) FROM $R WHERE Player_Comments IS NOT NULL AND Player_Comments <> '') AS rmax,
           (SELECT COUNT(*) FROM $PL) AS pc,
           (SELECT MAX(updated_at) FROM $PL) AS pmax"
    )->fetch();

    $stmt = $pdo->prepare(
        'INSERT INTO insight_runs
         (started_at, replays_scanned, patterns_scanned,
          watermark_replay_comment_count, watermark_max_replay_id,
          watermark_pattern_count, watermark_pattern_updated_at)
         VALUES (NOW(), ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $replaysScanned, $patternsScanned,
        (int) $wm['rc'], (int) $wm['rmax'], (int) $wm['pc'], $wm['pmax'],
    ]);
    return (int) $pdo->lastInsertId();
}

function finish_run(PDO $pdo, int $runId, int $tagsWritten, int $tacticsWritten): void
{
    $stmt = $pdo->prepare(
        'UPDATE insight_runs SET finished_at = NOW(), tags_written = ?, tactics_written = ? WHERE run_id = ?'
    );
    $stmt->execute([$tagsWritten, $tacticsWritten, $runId]);
}
