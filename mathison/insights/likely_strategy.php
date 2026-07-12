<?php
/**
 * Likely-strategy inference (single-opponent lookup only)
 * ======================================================
 *
 * For unlabeled Replays (empty Player_Comments) vs one opponent:
 *   1. Prefer THIS opponent's own labeled comments as the match corpus
 *      (insight_tactics + timings from PatternLearning or Replay_Summary).
 *   2. Fall back to global same-race PatternLearning corpus only if they
 *      have no usable local fingerprints.
 *   3. Lock to their dominant race (e.g. Protoss) so rare/miscoded race
 *      rows don't produce Zerg labels for a Protoss main.
 *   4. Return at most TOP_N matches (highest confidence).
 *
 * Called only from api/insights.php?action=opponent. Never writes DB.
 */

const LIKELY_STRATEGY_MAX_UNLABELED = 40;
const LIKELY_STRATEGY_TOP_N = 5;
const LIKELY_STRATEGY_MIN_CONFIDENCE = 0.62;
const LIKELY_STRATEGY_MAX_MEAN_DELTA = 45; // seconds
const LIKELY_STRATEGY_MIN_SHARED = 3;

/** Canonical SC2_UserId casing from Players (case-insensitive). */
function likely_strategy_resolve_name(PDO $pdo, string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return $name;
    }
    $P = mathison_table('Players');
    $stmt = $pdo->prepare("SELECT SC2_UserId FROM $P WHERE SC2_UserId = ? LIMIT 1");
    $stmt->execute([$name]);
    $exact = $stmt->fetchColumn();
    if ($exact) {
        return (string) $exact;
    }
    $stmt = $pdo->prepare("SELECT SC2_UserId FROM $P WHERE LOWER(SC2_UserId) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $ci = $stmt->fetchColumn();
    return $ci ? (string) $ci : $name;
}

/**
 * @return array{games: list<array>, summary: list<array>, meta: array}
 */
function likely_strategies_for_opponent(PDO $pdo, string $opponentName, ?int $startWorkers = null): array
{
    $opponentName = likely_strategy_resolve_name($pdo, $opponentName);
    $empty = static function (array $meta) {
        return ['games' => [], 'summary' => [], 'meta' => $meta];
    };

    $dominantRace = likely_strategy_dominant_race($pdo, $opponentName);
    $local = likely_strategy_local_corpus($pdo, $opponentName, $startWorkers, $dominantRace);
    $corpusSource = 'local';
    $corpus = $local;
    if (count($corpus) < 1) {
        $corpus = likely_strategy_global_corpus($pdo, $startWorkers, $dominantRace);
        $corpusSource = 'global';
    }
    if (!$corpus) {
        return $empty([
            'opponent' => $opponentName,
            'dominant_race' => $dominantRace,
            'corpus_source' => $corpusSource,
            'corpus_size' => 0,
            'unlabeled_scanned' => 0,
            'matched' => 0,
            'start_workers' => $startWorkers,
            'top_n' => LIKELY_STRATEGY_TOP_N,
        ]);
    }

    $unlabeled = likely_strategy_unlabeled_replays($pdo, $opponentName, $startWorkers, $dominantRace);
    if (!$unlabeled) {
        return $empty([
            'opponent' => $opponentName,
            'dominant_race' => $dominantRace,
            'corpus_source' => $corpusSource,
            'corpus_size' => count($corpus),
            'unlabeled_scanned' => 0,
            'matched' => 0,
            'start_workers' => $startWorkers,
            'top_n' => LIKELY_STRATEGY_TOP_N,
        ]);
    }

    $out = [];
    foreach ($unlabeled as $game) {
        $best = likely_strategy_best_match($game, $corpus);
        if ($best === null) {
            continue;
        }
        unset($game['timings']);
        $out[] = array_merge($game, $best);
    }

    // One best game per tactic label, then top N by confidence.
    $byLabel = [];
    foreach ($out as $row) {
        $k = $row['likely_label'];
        if (!isset($byLabel[$k]) || $row['confidence'] > $byLabel[$k]['confidence']
            || ($row['confidence'] === $byLabel[$k]['confidence']
                && ($row['timing_delta_sec'] ?? 999) < ($byLabel[$k]['timing_delta_sec'] ?? 999))) {
            $byLabel[$k] = $row;
        }
    }
    $out = array_values($byLabel);
    usort($out, static function ($a, $b) {
        $c = $b['confidence'] <=> $a['confidence'];
        if ($c !== 0) {
            return $c;
        }
        return ($a['timing_delta_sec'] ?? 999) <=> ($b['timing_delta_sec'] ?? 999);
    });
    $matchedTotal = count($out);
    $out = array_slice($out, 0, LIKELY_STRATEGY_TOP_N);

    $agg = [];
    foreach ($out as $row) {
        $agg[] = [
            'likely_label' => $row['likely_label'],
            'games' => 1,
            'avg_confidence' => $row['confidence'],
            'matched_comment' => $row['matched_comment'] ?? null,
        ];
    }

    return [
        'games' => $out,
        'summary' => $agg,
        'meta' => [
            'opponent' => $opponentName,
            'dominant_race' => $dominantRace,
            'corpus_source' => $corpusSource,
            'corpus_size' => count($corpus),
            'unlabeled_scanned' => count($unlabeled),
            'matched' => count($out),
            'unique_labels_before_cap' => $matchedTotal,
            'start_workers' => $startWorkers,
            'top_n' => LIKELY_STRATEGY_TOP_N,
        ],
    ];
}

/**
 * Majority race for this player across Replays (+ labeled tactics as tie-break).
 * Returns null only if completely unknown.
 */
function likely_strategy_dominant_race(PDO $pdo, string $opponentName): ?string
{
    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $stmt = $pdo->prepare(
        "SELECT
            CASE
              WHEN LOWER(p1.SC2_UserId) = LOWER(?) THEN LOWER(r.Player1_Race)
              WHEN LOWER(p2.SC2_UserId) = LOWER(?) THEN LOWER(r.Player2_Race)
            END AS race,
            COUNT(*) AS c
         FROM $R r
         LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
         LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
         WHERE LOWER(p1.SC2_UserId) = LOWER(?) OR LOWER(p2.SC2_UserId) = LOWER(?)
         GROUP BY race
         HAVING race IS NOT NULL AND race <> ''
         ORDER BY c DESC"
    );
    $stmt->execute([$opponentName, $opponentName, $opponentName, $opponentName]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $top = $rows[0];
        $total = 0;
        foreach ($rows as $r) {
            $total += (int) $r['c'];
        }
        // Lock if clear majority (>= 60%).
        if ($total > 0 && ((int) $top['c'] / $total) >= 0.60) {
            return likely_strategy_norm_race($top['race']);
        }
        return likely_strategy_norm_race($top['race']);
    }

    $st = $pdo->prepare(
        "SELECT LOWER(opponent_race) AS race, COUNT(*) c FROM insight_tactics
         WHERE opponent_name = ? AND opponent_race IS NOT NULL
         GROUP BY race ORDER BY c DESC LIMIT 1"
    );
    $st->execute([$opponentName]);
    $race = $st->fetchColumn();
    return $race ? likely_strategy_norm_race($race) : null;
}

function likely_strategy_norm_race(?string $race): ?string
{
    if ($race === null || $race === '') {
        return null;
    }
    $r = strtolower(trim($race));
    if (strpos($r, 'p') === 0) {
        return 'protoss';
    }
    if (strpos($r, 't') === 0) {
        return 'terran';
    }
    if (strpos($r, 'z') === 0) {
        return 'zerg';
    }
    return $r;
}

/** Infer race from key buildings present in a timings map. */
function likely_strategy_race_from_timings(array $timings): ?string
{
    $z = ['SpawningPool', 'RoachWarren', 'BanelingNest', 'Spire', 'HydraliskDen', 'InfestationPit', 'NydusNetwork', 'UltraliskCavern', 'Lair', 'Hive'];
    $t = ['Barracks', 'Factory', 'Starport', 'OrbitalCommand', 'EngineeringBay', 'GhostAcademy', 'FusionCore'];
    $p = ['Gateway', 'Forge', 'CyberneticsCore', 'TwilightCouncil', 'RoboticsFacility', 'Stargate', 'DarkShrine', 'TemplarArchives', 'FleetBeacon', 'RoboticsBay'];
    $zs = $ts = $ps = 0;
    foreach ($timings as $b => $_) {
        if (in_array($b, $z, true)) {
            $zs++;
        } elseif (in_array($b, $t, true)) {
            $ts++;
        } elseif (in_array($b, $p, true)) {
            $ps++;
        }
    }
    if ($zs === 0 && $ts === 0 && $ps === 0) {
        return null;
    }
    if ($zs >= $ts && $zs >= $ps) {
        return 'zerg';
    }
    if ($ts >= $ps) {
        return 'terran';
    }
    return 'protoss';
}

/**
 * This opponent's own labeled fingerprints (preferred corpus).
 * Sources: pattern rows with timings, or replay rows with Replay_Summary.
 */
function likely_strategy_local_corpus(PDO $pdo, string $opponentName, ?int $startWorkers, ?string $dominantRace): array
{
    [$wSql, $wParams] = likely_strategy_workers_where($startWorkers);
    $sql = "SELECT source, source_id, replay_id, tactic_label, parts_count, comment,
                   opponent_race
            FROM insight_tactics
            WHERE opponent_name = ? AND parts_count >= 2 {$wSql}
            ORDER BY parts_count DESC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$opponentName], $wParams));

    $PL = mathison_table('PatternLearning');
    $R = mathison_table('Replays');
    $patternStmt = $pdo->prepare(
        "SELECT JSON_EXTRACT(signature, '$.key_timings') FROM $PL WHERE pattern_id = ?"
    );
    $summaryStmt = $pdo->prepare("SELECT Replay_Summary FROM $R WHERE ReplayId = ?");

    $out = [];
    $seen = [];
    foreach ($stmt as $row) {
        $race = likely_strategy_norm_race($row['opponent_race']);
        if ($dominantRace && $race && $race !== $dominantRace) {
            continue;
        }

        $secs = [];
        if ($row['source'] === 'pattern') {
            $patternStmt->execute([$row['source_id']]);
            $kt = $patternStmt->fetchColumn();
            $secs = likely_strategy_timings_map($kt);
        } elseif ($row['replay_id']) {
            $summaryStmt->execute([(int) $row['replay_id']]);
            $summary = (string) ($summaryStmt->fetchColumn() ?: '');
            if ($summary !== '') {
                $secs = likely_strategy_timings_from_summary($summary, $opponentName);
            }
        }
        if (count($secs) < 2) {
            continue;
        }

        $timingRace = likely_strategy_race_from_timings($secs);
        if ($dominantRace && $timingRace && $timingRace !== $dominantRace) {
            continue;
        }
        if ($race && $timingRace && $race !== $timingRace) {
            continue;
        }

        $key = $row['source'] . ':' . $row['source_id'] . ':' . $row['tactic_label'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $out[] = [
            'pattern_id' => $row['source'] === 'pattern' ? $row['source_id'] : ('replay_' . $row['replay_id']),
            'tactic_label' => $row['tactic_label'],
            'parts_count' => (int) $row['parts_count'],
            'matched_comment' => $row['comment'],
            'opponent_race' => $race ?: $timingRace ?: $dominantRace,
            'timings' => $secs,
            'local' => true,
        ];
    }
    return $out;
}

/** Global PatternLearning corpus, optionally locked to one race. */
function likely_strategy_global_corpus(PDO $pdo, ?int $startWorkers, ?string $race): array
{
    static $cache = [];
    $key = ($startWorkers === null ? 'all' : (string) $startWorkers) . '|' . ($race ?? 'any');
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $PL = mathison_table('PatternLearning');
    $sql = "SELECT it.source_id AS pattern_id,
                   it.tactic_label,
                   it.parts_count,
                   it.comment AS matched_comment,
                   it.opponent_race,
                   JSON_EXTRACT(pl.signature, '$.key_timings') AS key_timings
            FROM insight_tactics it
            INNER JOIN $PL pl ON pl.pattern_id = it.source_id
            WHERE it.source = 'pattern'
              AND it.parts_count >= 2
              AND JSON_EXTRACT(pl.signature, '$.key_timings') IS NOT NULL";
    $params = [];
    if ($startWorkers !== null) {
        $sql .= ' AND it.start_workers = ?';
        $params[] = $startWorkers;
    }
    if ($race !== null) {
        $sql .= ' AND LOWER(it.opponent_race) = ?';
        $params[] = $race;
    }
    $sql .= ' ORDER BY it.parts_count DESC, it.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $byPattern = [];
    foreach ($stmt as $row) {
        $pid = $row['pattern_id'];
        if (isset($byPattern[$pid])) {
            continue;
        }
        $secs = likely_strategy_timings_map($row['key_timings']);
        if (count($secs) < LIKELY_STRATEGY_MIN_SHARED) {
            continue;
        }
        $refRace = likely_strategy_norm_race($row['opponent_race'])
            ?: likely_strategy_race_from_timings($secs);
        if ($race && $refRace && $refRace !== $race) {
            continue;
        }
        $byPattern[$pid] = [
            'pattern_id' => $pid,
            'tactic_label' => $row['tactic_label'],
            'parts_count' => (int) $row['parts_count'],
            'matched_comment' => $row['matched_comment'],
            'opponent_race' => $refRace,
            'timings' => $secs,
            'local' => false,
        ];
    }

    $cache[$key] = array_values($byPattern);
    return $cache[$key];
}

function likely_strategy_workers_where(?int $startWorkers): array
{
    if ($startWorkers === null) {
        return ['', []];
    }
    return [' AND start_workers = ?', [$startWorkers]];
}

/**
 * Unlabeled replays vs opponent. Skips already-tagged replay_ids.
 * Locked to dominant race when known; also skips BO that look like another race.
 */
function likely_strategy_unlabeled_replays(
    PDO $pdo,
    string $opponentName,
    ?int $startWorkers,
    ?string $dominantRace
): array {
    $rules = require __DIR__ . '/rules.php';
    $selfLower = array_map('strtolower', $rules['self_accounts'] ?? []);
    $eras = $rules['worker_eras'] ?? [];

    $R = mathison_table('Replays');
    $P = mathison_table('Players');

    $tagged = $pdo->prepare(
        "SELECT DISTINCT replay_id FROM insight_tactics
         WHERE opponent_name = ? AND replay_id IS NOT NULL"
    );
    $tagged->execute([$opponentName]);
    $taggedIds = [];
    foreach ($tagged as $t) {
        $taggedIds[(int) $t['replay_id']] = true;
    }

    $stmt = $pdo->prepare(
        "SELECT r.ReplayId, r.Date_Played, r.Replay_Summary,
                r.Player1_Result, r.Player2_Result,
                r.Player1_Race, r.Player2_Race,
                p1.SC2_UserId AS p1name, p2.SC2_UserId AS p2name
         FROM $R r
         LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
         LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
         WHERE (LOWER(p1.SC2_UserId) = LOWER(?) OR LOWER(p2.SC2_UserId) = LOWER(?))
           AND (r.Player_Comments IS NULL OR TRIM(r.Player_Comments) = '')
           AND r.Replay_Summary IS NOT NULL AND TRIM(r.Replay_Summary) <> ''
         ORDER BY r.Date_Played DESC
         LIMIT " . (int) LIKELY_STRATEGY_MAX_UNLABELED
    );
    $stmt->execute([$opponentName, $opponentName]);

    $out = [];
    foreach ($stmt as $row) {
        $rid = (int) $row['ReplayId'];
        if (isset($taggedIds[$rid])) {
            continue;
        }

        $p1 = (string) $row['p1name'];
        $p2 = (string) $row['p2name'];
        $p1self = in_array(strtolower($p1), $selfLower, true);
        $p2self = in_array(strtolower($p2), $selfLower, true);

        if (strcasecmp($p1, $opponentName) === 0 && strcasecmp($p2, $opponentName) !== 0) {
            $oppName = $p1;
            $oppRace = likely_strategy_norm_race($row['Player1_Race']);
            $selfResult = $p2self ? $row['Player2_Result'] : null;
        } elseif (strcasecmp($p2, $opponentName) === 0 && strcasecmp($p1, $opponentName) !== 0) {
            $oppName = $p2;
            $oppRace = likely_strategy_norm_race($row['Player2_Race']);
            $selfResult = $p1self ? $row['Player1_Result'] : null;
        } else {
            continue;
        }

        if ($dominantRace && $oppRace && $oppRace !== $dominantRace) {
            continue;
        }

        $datePlayed = $row['Date_Played'];
        if ($startWorkers !== null && $eras) {
            $era = likely_strategy_era_from_date($datePlayed, $eras);
            if ($era !== $startWorkers) {
                continue;
            }
        }

        $secs = likely_strategy_timings_from_summary((string) $row['Replay_Summary'], $oppName);
        if (count($secs) < 2) {
            continue;
        }

        $timingRace = likely_strategy_race_from_timings($secs);
        if ($dominantRace && $timingRace && $timingRace !== $dominantRace) {
            // BO parse looks like another race — skip (bad race field / wrong section).
            continue;
        }
        if ($oppRace && $timingRace && $oppRace !== $timingRace) {
            continue;
        }

        $out[] = [
            'replay_id' => $rid,
            'pattern_id' => null,
            'date_played' => $datePlayed,
            'opponent_race' => $oppRace ?: $timingRace ?: $dominantRace,
            'self_result' => $selfResult,
            'timings' => $secs,
        ];
    }
    return $out;
}

/**
 * First-seen times for key buildings in the opponent's build-order section.
 */
function likely_strategy_timings_from_summary(string $summary, string $opponentName): array
{
    $keys = likely_strategy_key_buildings();
    $namePat = preg_quote($opponentName, '/');
    $namePat = str_replace(' ', '\\s+', $namePat);

    if (!preg_match(
        '/' . $namePat . '\'s\\s+Build\\s+Order.*?(?=\\n\\S[^\\n]*\'s\\s+Build\\s+Order|\\z)/is',
        $summary,
        $block
    )) {
        return [];
    }

    $secs = [];
    if (!preg_match_all(
        '/Time:\s*(\d+:\d{2}),\s*Name:\s*([A-Za-z][A-Za-z0-9_]*)\s*,/i',
        $block[0],
        $m,
        PREG_SET_ORDER
    )) {
        return [];
    }

    foreach ($m as $hit) {
        $building = $hit[2];
        if (!isset($keys[$building]) || isset($secs[$building])) {
            continue;
        }
        $s = likely_strategy_clock_to_seconds($hit[1]);
        if ($s !== null && $s <= 600) {
            $secs[$building] = $s;
        }
    }
    return $secs;
}

function likely_strategy_key_buildings(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $names = [
        'Gateway', 'Forge', 'CyberneticsCore', 'TwilightCouncil', 'RoboticsFacility',
        'Stargate', 'DarkShrine', 'TemplarArchives', 'FleetBeacon', 'RoboticsBay',
        'Nexus',
        'Barracks', 'Factory', 'Starport', 'CommandCenter', 'OrbitalCommand',
        'PlanetaryFortress', 'EngineeringBay', 'Armory', 'GhostAcademy', 'FusionCore',
        'BarracksTechLab', 'FactoryTechLab', 'StarportTechLab', 'BarracksReactor',
        'FactoryReactor', 'StarportReactor',
        'Hatchery', 'Lair', 'Hive', 'SpawningPool', 'RoachWarren', 'BanelingNest',
        'Spire', 'GreaterSpire', 'HydraliskDen', 'InfestationPit', 'NydusNetwork',
        'UltraliskCavern', 'EvolutionChamber',
    ];
    $set = array_fill_keys($names, true);
    return $set;
}

function likely_strategy_best_match(array $game, array $corpus): ?array
{
    $best = null;
    $bestScore = PHP_FLOAT_MAX;
    $gameRace = $game['opponent_race'] ?? likely_strategy_race_from_timings($game['timings']);

    foreach ($corpus as $ref) {
        $refRace = $ref['opponent_race'] ?? likely_strategy_race_from_timings($ref['timings']);
        // Strict race lock — never cross-race.
        if ($gameRace && $refRace && $gameRace !== $refRace) {
            continue;
        }
        if ($gameRace && !$refRace) {
            continue;
        }
        if (!$gameRace && $refRace) {
            continue;
        }

        $shared = array_intersect_key($game['timings'], $ref['timings']);
        $minShared = !empty($ref['local']) ? 2 : LIKELY_STRATEGY_MIN_SHARED;
        if (count($shared) < $minShared) {
            continue;
        }

        // Ignore ubiquitous early pairs that don't discriminate (e.g. Gateway+Nexus).
        if (!likely_strategy_shared_is_discriminative($shared, $gameRace)) {
            continue;
        }

        $sum = 0;
        foreach ($shared as $building => $_) {
            $sum += abs($game['timings'][$building] - $ref['timings'][$building]);
        }
        $mean = $sum / count($shared);
        if ($mean > LIKELY_STRATEGY_MAX_MEAN_DELTA) {
            continue;
        }

        // Prefer more shared buildings, then tighter timing.
        $score = $mean - (count($shared) * 5);
        if (!empty($ref['local'])) {
            $score -= 15; // strong preference for this player's own comments
        }

        if ($score < $bestScore) {
            $bestScore = $score;
            $confidence = 1 / (1 + $mean / 25);
            $confidence = min(1, $confidence + min(0.12, 0.03 * max(0, count($shared) - 2)));
            if (!empty($ref['local'])) {
                $confidence = min(1, $confidence + 0.1);
            }
            $confidence = min(1, $confidence + min(0.08, 0.02 * max(0, $ref['parts_count'] - 2)));

            $best = [
                'likely_label' => $ref['tactic_label'],
                'confidence' => round($confidence, 2),
                'matched_comment' => $ref['matched_comment'],
                'matched_from' => $ref['pattern_id'],
                'shared_buildings' => array_keys($shared),
                'timing_delta_sec' => (int) round($mean),
                'corpus_local' => !empty($ref['local']),
                'inferred' => true,
            ];
        }
    }

    if ($best === null || $best['confidence'] < LIKELY_STRATEGY_MIN_CONFIDENCE) {
        return null;
    }
    return $best;
}

/** Drop weak shared sets that almost every build has. */
function likely_strategy_shared_is_discriminative(array $shared, ?string $race): bool
{
    $keys = array_keys($shared);
    sort($keys);
    $weak = [
        ['Gateway', 'Nexus'],
        ['CommandCenter', 'Barracks'],
        ['Hatchery', 'SpawningPool'],
    ];
    foreach ($weak as $pair) {
        sort($pair);
        if ($keys === $pair) {
            return false;
        }
    }
    // Gateway + RoboticsFacility / Gateway + Forge alone are too common.
    if ($keys === ['Gateway', 'RoboticsFacility'] || $keys === ['Gateway', 'Forge']) {
        return false;
    }
    return true;
}

function likely_strategy_timings_map($json): array
{
    $kt = is_array($json) ? $json : json_decode((string) $json, true);
    if (!is_array($kt)) {
        return [];
    }
    $secs = [];
    foreach ($kt as $building => $clock) {
        if (is_int($clock) || is_float($clock) || (is_string($clock) && ctype_digit($clock))) {
            $s = (int) $clock;
        } else {
            $s = likely_strategy_clock_to_seconds((string) $clock);
        }
        if ($s !== null && $s <= 600) {
            $secs[$building] = $s;
        }
    }
    return $secs;
}

function likely_strategy_clock_to_seconds(string $clock): ?int
{
    if (!preg_match('/^(\d+):(\d{2})$/', trim($clock), $m)) {
        return null;
    }
    return (int) $m[1] * 60 + (int) $m[2];
}

function likely_strategy_era_from_date(?string $datePlayed, array $eras): ?int
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
