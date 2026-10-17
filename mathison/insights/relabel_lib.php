<?php
/**
 * Mathison Insights relabeler (shared library)
 *
 * Used by CLI (relabel.php) and the dashboard API (api/insights.php).
 * See relabel.php header for design notes.
 */

require_once __DIR__ . '/../db.php';

/**
 * Full rebuild of insight_tags / insight_tactics.
 *
 * @param array{
 *   dry_run?: bool,
 *   on_progress?: callable(int, string, array): void
 * } $options
 * @return array{ok:bool, dry_run:bool, run_id:?int, tags:int, tactics:int, unmatched:int,
 *               replays_scanned:int, patterns_scanned:int, top_tactics:array, error?:string}
 */
function run_relabel(PDO $pdo, array $options = []): array
{
    $dryRun = !empty($options['dry_run']);
    $onProgress = $options['on_progress'] ?? null;
    $progress = static function (int $pct, string $message, array $extra = []) use ($onProgress): void {
        if (is_callable($onProgress)) {
            $onProgress($pct, $message, $extra);
        }
    };

    $lock = null;
    try {
        $lock = relabel_acquire_lock();
        if ($lock === null) {
            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'run_id' => null,
                'tags' => 0,
                'tactics' => 0,
                'unmatched' => 0,
                'replays_scanned' => 0,
                'patterns_scanned' => 0,
                'top_tactics' => [],
                'error' => 'A relabel is already running. Wait for it to finish.',
            ];
        }

        $config = require __DIR__ . '/rules.php';

        $progress(2, 'Ensuring insight schema…');
        ensure_schema($pdo);

        $progress(8, 'Loading commented replays…');
        $replaySources = fetch_replay_sources($pdo, $config);
        $progress(25, 'Loaded ' . count($replaySources) . ' replay comment(s). Loading pattern comments…');

        $patternSources = fetch_pattern_sources($pdo, $config, static function (int $done, int $total) use ($progress): void {
            // Patterns are the slow part — map into 25–55%.
            $pct = 25 + (int) round(30 * ($total > 0 ? $done / $total : 1));
            $progress(min(55, $pct), "Loading pattern comments… {$done}/{$total}");
        });

        $sources = array_merge($replaySources, $patternSources);
        $totalSources = count($sources);
        $progress(58, "Classifying {$totalSources} comment(s)…");

        $tagRows = [];
        $tacticRows = [];
        $unmatched = [];
        foreach ($sources as $i => $src) {
            $classified = classify_comment($src['comment'], $config);
            if (!$classified['tags'] && !$classified['tactics']) {
                $unmatched[] = $src;
            } else {
                foreach ($classified['tags'] as $t) {
                    $tagRows[] = array_merge($src, $t);
                }
                foreach ($classified['tactics'] as $tac) {
                    $tacticRows[] = array_merge($src, $tac);
                }
            }
            if (($i + 1) % 50 === 0 || ($i + 1) === $totalSources) {
                $pct = 58 + (int) round(17 * (($i + 1) / max(1, $totalSources)));
                $progress(min(75, $pct), 'Classifying… ' . ($i + 1) . '/' . $totalSources);
            }
        }

        $replaysScanned = count($replaySources);
        $patternsScanned = count($patternSources);

        $tacticFreq = [];
        foreach ($tacticRows as $t) {
            $tacticFreq[$t['tactic_label']] = ($tacticFreq[$t['tactic_label']] ?? 0) + 1;
        }
        arsort($tacticFreq);
        $topTactics = [];
        foreach (array_slice($tacticFreq, 0, 15, true) as $label => $n) {
            $topTactics[] = ['label' => $label, 'count' => $n];
        }

        if ($dryRun) {
            $progress(100, 'Dry run complete — nothing written.', [
                'tags' => count($tagRows),
                'tactics' => count($tacticRows),
            ]);
            return [
                'ok' => true,
                'dry_run' => true,
                'run_id' => null,
                'tags' => count($tagRows),
                'tactics' => count($tacticRows),
                'unmatched' => count($unmatched),
                'replays_scanned' => $replaysScanned,
                'patterns_scanned' => $patternsScanned,
                'top_tactics' => $topTactics,
            ];
        }

        $progress(78, 'Writing insight tables…');
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
            $tagTotal = count($tagRows);
            foreach ($tagRows as $i => $r) {
                $insTag->execute([
                    $runId, $r['source'], $r['source_id'], $r['replay_id'],
                    $r['opponent_name'], $r['opponent_race'], $r['self_result'],
                    $r['date_played'], $r['comment'], $r['tag'], $r['category'], $r['phase'],
                    $r['start_workers'],
                ]);
                if (($i + 1) % 100 === 0 || ($i + 1) === $tagTotal) {
                    $pct = 78 + (int) round(8 * (($i + 1) / max(1, $tagTotal)));
                    $progress(min(86, $pct), 'Writing tags… ' . ($i + 1) . '/' . $tagTotal);
                }
            }

            $insTac = $pdo->prepare(
                'INSERT INTO insight_tactics
                 (run_id, source, source_id, replay_id, opponent_name, opponent_race,
                  self_result, date_played, comment, phase, tactic_key, tactic_label,
                  parts_json, parts_count, start_workers)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $tacTotal = count($tacticRows);
            foreach ($tacticRows as $i => $r) {
                $insTac->execute([
                    $runId, $r['source'], $r['source_id'], $r['replay_id'],
                    $r['opponent_name'], $r['opponent_race'], $r['self_result'],
                    $r['date_played'], $r['comment'], $r['phase'],
                    $r['tactic_key'], $r['tactic_label'],
                    json_encode($r['parts'], JSON_UNESCAPED_UNICODE),
                    $r['parts_count'],
                    $r['start_workers'],
                ]);
                if (($i + 1) % 100 === 0 || ($i + 1) === $tacTotal) {
                    $pct = 86 + (int) round(10 * (($i + 1) / max(1, $tacTotal)));
                    $progress(min(96, $pct), 'Writing tactics… ' . ($i + 1) . '/' . $tacTotal);
                }
            }

            finish_run($pdo, $runId, count($tagRows), count($tacticRows), rules_fingerprint());
            $pdo->commit();

            $progress(100, "Run #{$runId} committed: " . count($tagRows) . ' tags, ' . count($tacticRows) . ' tactics.', [
                'run_id' => $runId,
                'tags' => count($tagRows),
                'tactics' => count($tacticRows),
            ]);

            return [
                'ok' => true,
                'dry_run' => false,
                'run_id' => $runId,
                'tags' => count($tagRows),
                'tactics' => count($tacticRows),
                'unmatched' => count($unmatched),
                'replays_scanned' => $replaysScanned,
                'patterns_scanned' => $patternsScanned,
                'top_tactics' => $topTactics,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Throwable $e) {
        $progress(0, 'FAILED: ' . $e->getMessage());
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'run_id' => null,
            'tags' => 0,
            'tactics' => 0,
            'unmatched' => 0,
            'replays_scanned' => 0,
            'patterns_scanned' => 0,
            'top_tactics' => [],
            'error' => $e->getMessage(),
        ];
    } finally {
        relabel_release_lock($lock);
    }
}

/** @return resource|null Lock handle, or null if already locked. */
function relabel_acquire_lock()
{
    $path = __DIR__ . '/.relabel.lock';
    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        throw new RuntimeException('Could not open relabel lock file.');
    }
    if (!flock($fh, LOCK_EX | LOCK_NB)) {
        fclose($fh);
        return null;
    }
    ftruncate($fh, 0);
    fwrite($fh, (string) getmypid() . ' ' . date('c'));
    fflush($fh);
    return $fh;
}

/** @param resource|null $fh */
function relabel_release_lock($fh): void
{
    if ($fh === null || $fh === false) {
        return;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
}

// ===========================================================================
// Classification
// ===========================================================================

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
// Source readers
// ===========================================================================

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
 * @param callable(int,int):void|null $onRow Optional progress: ($done, $totalGuess)
 */
function fetch_pattern_sources(PDO $pdo, array $config, ?callable $onRow = null): array
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

    $summaryStmt = $pdo->prepare("SELECT Replay_Summary FROM $R WHERE ReplayId = ?");
    $resultMap = ['victory' => 'Win', 'defeat' => 'Lose', 'observed' => 'Observed'];

    $rows = $stmt->fetchAll();
    $total = count($rows);
    $out = [];
    foreach ($rows as $i => $row) {
        $comment = trim((string) $row['comment']);
        if ($comment === '' || strtolower($comment) === 'null') {
            if ($onRow) {
                $onRow($i + 1, $total);
            }
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

        if ($onRow && (($i + 1) % 25 === 0 || ($i + 1) === $total)) {
            $onRow($i + 1, $total);
        }
    }
    return $out;
}

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
    return $byDate;
}

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

function ensure_schema(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/schema_changes.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (preg_match('/^(--|$)/', strtok($statement, "\n")) && !preg_match('/CREATE TABLE/i', $statement)) {
            continue;
        }
        $pdo->exec($statement);
    }

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

/** md5 of rules.php — used to detect “need relabel after rules change”. */
function rules_fingerprint(): string
{
    $path = __DIR__ . '/rules.php';
    $hash = @md5_file($path);
    return is_string($hash) ? $hash : '';
}

function finish_run(PDO $pdo, int $runId, int $tagsWritten, int $tacticsWritten, ?string $rulesHash = null): void
{
    if ($rulesHash === null) {
        $rulesHash = rules_fingerprint();
    }
    $notes = $rulesHash !== '' ? ('rules_md5:' . $rulesHash) : null;
    $stmt = $pdo->prepare(
        'UPDATE insight_runs SET finished_at = NOW(), tags_written = ?, tactics_written = ?, notes = ? WHERE run_id = ?'
    );
    $stmt->execute([$tagsWritten, $tacticsWritten, $notes, $runId]);
}
