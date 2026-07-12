<?php
/**
 * Mathison external API v1 handlers (read-only).
 * Loaded by v1.php after token auth.
 */

function v1_health(PDO $pdo): array
{
    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $counts = $pdo->query(
        "SELECT
           (SELECT COUNT(*) FROM $R) AS replays,
           (SELECT COUNT(*) FROM $P) AS players"
    )->fetch();
    $hasInsights = (bool) $pdo->query("SHOW TABLES LIKE 'insight_tactics'")->fetchColumn();
    return [
        'service' => 'mathison-api',
        'version' => 1,
        'time' => gmdate('c'),
        'replays' => (int) ($counts['replays'] ?? 0),
        'players' => (int) ($counts['players'] ?? 0),
        'insights_ready' => $hasInsights,
    ];
}

function v1_players_search(PDO $pdo, string $q, int $limit): array
{
    $q = trim($q);
    if (strlen($q) < 2) {
        mathison_api_error('q must be at least 2 characters', 400);
    }
    $P = mathison_table('Players');
    $R = mathison_table('Replays');
    $stmt = $pdo->prepare(
        "SELECT p.Id AS id, p.SC2_UserId AS name,
                (
                  SELECT COUNT(*) FROM $R r
                  WHERE r.Player1_Id = p.Id OR r.Player2_Id = p.Id
                ) AS games,
                (
                  SELECT MAX(r.Date_Played) FROM $R r
                  WHERE r.Player1_Id = p.Id OR r.Player2_Id = p.Id
                ) AS last_played
         FROM $P p
         WHERE p.SC2_UserId LIKE ?
         ORDER BY games DESC, p.SC2_UserId ASC
         LIMIT {$limit}"
    );
    $stmt->execute(['%' . $q . '%']);
    return ['query' => $q, 'players' => $stmt->fetchAll()];
}

function v1_player(PDO $pdo, string $name): array
{
    $player = mathison_api_resolve_player($pdo, $name);
    if (!$player) {
        mathison_api_error('Player not found', 404);
    }

    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $self = mathison_api_self_accounts();
    $id = $player['id'];
    $canonical = $player['name'];

    $stats = $pdo->prepare(
        "SELECT COUNT(*) AS games,
                SUM(r.Player_Comments IS NOT NULL AND TRIM(r.Player_Comments) <> '') AS commented,
                MAX(r.Date_Played) AS last_played
         FROM $R r
         WHERE r.Player1_Id = ? OR r.Player2_Id = ?"
    );
    $stats->execute([$id, $id]);
    $st = $stats->fetch() ?: ['games' => 0, 'commented' => 0, 'last_played' => null];

    $raceStmt = $pdo->prepare(
        "SELECT race, COUNT(*) AS c FROM (
            SELECT LOWER(r.Player1_Race) AS race FROM $R r WHERE r.Player1_Id = ?
            UNION ALL
            SELECT LOWER(r.Player2_Race) AS race FROM $R r WHERE r.Player2_Id = ?
         ) x
         WHERE race IS NOT NULL AND race <> ''
         GROUP BY race ORDER BY c DESC"
    );
    $raceStmt->execute([$id, $id]);
    $races = [];
    foreach ($raceStmt as $row) {
        $nr = mathison_api_norm_race($row['race']);
        if ($nr) {
            $races[$nr] = ($races[$nr] ?? 0) + (int) $row['c'];
        }
    }
    arsort($races);

    $mapStmt = $pdo->prepare(
        "SELECT r.Map AS map, COUNT(*) AS c FROM $R r
         WHERE (r.Player1_Id = ? OR r.Player2_Id = ?) AND r.Map IS NOT NULL AND r.Map <> ''
         GROUP BY r.Map ORDER BY c DESC LIMIT 8"
    );
    $mapStmt->execute([$id, $id]);
    $maps = [];
    foreach ($mapStmt as $row) {
        $maps[$row['map']] = (int) $row['c'];
    }

    // Our (self accounts) record vs this player.
    $vsSelf = ['wins' => 0, 'losses' => 0, 'games' => 0];
    if ($self) {
        $placeholders = implode(',', array_fill(0, count($self), '?'));
        $vsSql = "SELECT
                    SUM(CASE
                      WHEN LOWER(p1.SC2_UserId) IN ($placeholders) AND r.Player2_Id = ? AND r.Player1_Result = 'Win' THEN 1
                      WHEN LOWER(p2.SC2_UserId) IN ($placeholders) AND r.Player1_Id = ? AND r.Player2_Result = 'Win' THEN 1
                      ELSE 0 END) AS wins,
                    SUM(CASE
                      WHEN LOWER(p1.SC2_UserId) IN ($placeholders) AND r.Player2_Id = ? AND r.Player1_Result = 'Lose' THEN 1
                      WHEN LOWER(p2.SC2_UserId) IN ($placeholders) AND r.Player1_Id = ? AND r.Player2_Result = 'Lose' THEN 1
                      ELSE 0 END) AS losses,
                    SUM(CASE
                      WHEN (LOWER(p1.SC2_UserId) IN ($placeholders) AND r.Player2_Id = ?)
                        OR (LOWER(p2.SC2_UserId) IN ($placeholders) AND r.Player1_Id = ?) THEN 1
                      ELSE 0 END) AS games
                  FROM $R r
                  LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
                  LEFT JOIN $P p2 ON p2.Id = r.Player2_Id";
        $selfLower = array_map('strtolower', $self);
        $params = array_merge(
            $selfLower, [$id],
            $selfLower, [$id],
            $selfLower, [$id],
            $selfLower, [$id],
            $selfLower, [$id],
            $selfLower, [$id]
        );
        $vsStmt = $pdo->prepare($vsSql);
        $vsStmt->execute($params);
        $vs = $vsStmt->fetch() ?: [];
        $vsSelf = [
            'wins' => (int) ($vs['wins'] ?? 0),
            'losses' => (int) ($vs['losses'] ?? 0),
            'games' => (int) ($vs['games'] ?? 0),
        ];
    }

    $tagged = 0;
    $tactics = [];
    if ($pdo->query("SHOW TABLES LIKE 'insight_tactics'")->fetchColumn()) {
        $t = $pdo->prepare(
            "SELECT COUNT(DISTINCT source, source_id) FROM insight_tactics WHERE opponent_name = ?"
        );
        $t->execute([$canonical]);
        $tagged = (int) $t->fetchColumn();

        $t = $pdo->prepare(
            "SELECT tactic_label, COUNT(*) AS games
             FROM insight_tactics WHERE opponent_name = ?
             GROUP BY tactic_label ORDER BY games DESC LIMIT 5"
        );
        $t->execute([$canonical]);
        $tactics = $t->fetchAll();
    }

    return [
        'name' => $canonical,
        'id' => $id,
        'games' => (int) $st['games'],
        'commented_games' => (int) $st['commented'],
        'tagged_games' => $tagged,
        'last_played' => $st['last_played'],
        'dominant_race' => $races ? array_key_first($races) : null,
        'races' => $races,
        'top_maps' => $maps,
        'our_record' => $vsSelf,
        'top_tactics' => $tactics,
    ];
}

function v1_player_games(PDO $pdo, string $name, int $limit, ?string $vs = null): array
{
    $player = mathison_api_resolve_player($pdo, $name);
    if (!$player) {
        mathison_api_error('Player not found', 404);
    }
    $vsPlayer = null;
    if ($vs !== null && $vs !== '') {
        $vsPlayer = mathison_api_resolve_player($pdo, $vs);
        if (!$vsPlayer) {
            mathison_api_error('vs player not found', 404);
        }
    }

    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $self = array_map('strtolower', mathison_api_self_accounts());
    $id = $player['id'];

    $sql = "SELECT r.ReplayId, r.Date_Played, r.Map, r.Region, r.GameDuration,
                   r.Player1_Race, r.Player2_Race,
                   r.Player1_Result, r.Player2_Result,
                   r.Player_Comments,
                   p1.SC2_UserId AS p1, p2.SC2_UserId AS p2,
                   r.Player1_Id, r.Player2_Id
            FROM $R r
            LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
            LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
            WHERE (r.Player1_Id = ? OR r.Player2_Id = ?)";
    $params = [$id, $id];
    if ($vsPlayer) {
        $sql .= ' AND (r.Player1_Id = ? OR r.Player2_Id = ?)';
        $params[] = $vsPlayer['id'];
        $params[] = $vsPlayer['id'];
    }
    $sql .= " ORDER BY r.Date_Played DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $games = [];
    foreach ($stmt as $row) {
        $isP1 = ((int) $row['Player1_Id'] === $id);
        $opponent = $isP1 ? $row['p2'] : $row['p1'];
        $race = mathison_api_norm_race($isP1 ? $row['Player1_Race'] : $row['Player2_Race']);
        $oppRace = mathison_api_norm_race($isP1 ? $row['Player2_Race'] : $row['Player1_Race']);
        $result = $isP1 ? $row['Player1_Result'] : $row['Player2_Result'];

        // If one side is self, also expose our_result from self's view.
        $p1self = in_array(strtolower((string) $row['p1']), $self, true);
        $p2self = in_array(strtolower((string) $row['p2']), $self, true);
        $ourResult = null;
        if ($p1self && !$p2self) {
            $ourResult = $row['Player1_Result'];
        } elseif ($p2self && !$p1self) {
            $ourResult = $row['Player2_Result'];
        }

        $games[] = [
            'replay_id' => (int) $row['ReplayId'],
            'date_played' => $row['Date_Played'],
            'map' => $row['Map'],
            'region' => $row['Region'],
            'duration' => $row['GameDuration'],
            'player_race' => $race,
            'opponent' => $opponent,
            'opponent_race' => $oppRace,
            'player_result' => $result,
            'our_result' => $ourResult,
            'comment' => trim((string) ($row['Player_Comments'] ?? '')) ?: null,
        ];
    }

    return [
        'name' => $player['name'],
        'vs' => $vsPlayer['name'] ?? null,
        'limit' => $limit,
        'games' => $games,
    ];
}

function v1_player_strategies(PDO $pdo, string $name, ?int $startWorkers): array
{
    $player = mathison_api_resolve_player($pdo, $name);
    if (!$player) {
        mathison_api_error('Player not found', 404);
    }
    if (!$pdo->query("SHOW TABLES LIKE 'insight_tactics'")->fetchColumn()) {
        mathison_api_error('insight_tactics missing — run relabel.php', 503);
    }

    $wSql = '';
    $params = [$player['name']];
    if ($startWorkers !== null) {
        $wSql = ' AND start_workers = ?';
        $params[] = $startWorkers;
    }

    $stmt = $pdo->prepare(
        "SELECT tactic_key, tactic_label, parts_count, phase,
                COUNT(*) AS games,
                SUM(self_result = 'Win') AS our_wins,
                SUM(self_result = 'Lose') AS our_losses,
                MAX(date_played) AS last_seen
         FROM insight_tactics
         WHERE opponent_name = ? {$wSql}
         GROUP BY tactic_key, tactic_label, parts_count, phase
         ORDER BY games DESC, parts_count DESC"
    );
    $stmt->execute($params);

    $games = $pdo->prepare(
        "SELECT replay_id, date_played, self_result, opponent_race, comment,
                phase, tactic_label, parts_count, start_workers, source
         FROM insight_tactics
         WHERE opponent_name = ? {$wSql}
         ORDER BY date_played DESC
         LIMIT 50"
    );
    $games->execute($params);

    return [
        'name' => $player['name'],
        'start_workers' => $startWorkers,
        'tactics' => $stmt->fetchAll(),
        'recent_labeled' => $games->fetchAll(),
    ];
}

function v1_player_likely(PDO $pdo, string $name, ?int $startWorkers): array
{
    $player = mathison_api_resolve_player($pdo, $name);
    if (!$player) {
        mathison_api_error('Player not found', 404);
    }
    require_once __DIR__ . '/../insights/likely_strategy.php';
    $likely = likely_strategies_for_opponent($pdo, $player['name'], $startWorkers);
    return [
        'name' => $player['name'],
        'start_workers' => $startWorkers,
        'likely_strategies' => $likely,
    ];
}

function v1_player_timings(PDO $pdo, string $name): array
{
    $player = mathison_api_resolve_player($pdo, $name);
    if (!$player) {
        mathison_api_error('Player not found', 404);
    }

    return [
        'name' => $player['name'],
        'key_timings' => v1_compute_key_timings($pdo, $player['name']),
    ];
}

function v1_compute_key_timings(PDO $pdo, string $name): array
{
    $PL = mathison_table('PatternLearning');
    $stmt = $pdo->prepare(
        "SELECT JSON_EXTRACT(signature, '$.key_timings') AS kt
         FROM $PL
         WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.game_data.opponent_name')) = ?"
    );
    $stmt->execute([$name]);

    $acc = [];
    foreach ($stmt as $row) {
        $kt = json_decode((string) $row['kt'], true);
        if (!is_array($kt)) {
            continue;
        }
        foreach ($kt as $building => $time) {
            $secs = v1_clock_to_seconds_flexible($time);
            if ($secs !== null && $secs >= 0) {
                $acc[$building][] = $secs;
            }
        }
    }

    $out = [];
    foreach ($acc as $building => $times) {
        $avgSec = (int) round(array_sum($times) / count($times));
        $out[] = [
            'building' => $building,
            'games' => count($times),
            'avg_sec' => $avgSec,
            'earliest_sec' => (int) min($times),
            'avg' => v1_seconds_to_clock($avgSec),
            'earliest' => v1_seconds_to_clock((int) min($times)),
            'latest' => v1_seconds_to_clock((int) max($times)),
        ];
    }
    usort($out, static function ($a, $b) {
        $c = $a['avg_sec'] <=> $b['avg_sec'];
        return $c !== 0 ? $c : ($a['earliest_sec'] <=> $b['earliest_sec']);
    });
    foreach ($out as &$row) {
        unset($row['avg_sec'], $row['earliest_sec']);
    }
    unset($row);
    return $out;
}

function v1_clock_to_seconds_flexible($time): ?int
{
    if (is_int($time) || is_float($time)) {
        return (int) $time;
    }
    $time = trim((string) $time);
    if ($time !== '' && ctype_digit($time)) {
        return (int) $time;
    }
    if (!preg_match('/^(\d+):(\d{2})$/', $time, $m)) {
        return null;
    }
    return (int) $m[1] * 60 + (int) $m[2];
}

function v1_seconds_to_clock(int $secs): string
{
    return floor($secs / 60) . ':' . str_pad((string) ($secs % 60), 2, '0', STR_PAD_LEFT);
}

/**
 * Head-to-head between two players (order-independent).
 * our_* fields are filled when one side is a self account.
 */
function v1_matchup(PDO $pdo, string $a, string $b, int $limit): array
{
    $pa = mathison_api_resolve_player($pdo, $a);
    $pb = mathison_api_resolve_player($pdo, $b);
    if (!$pa || !$pb) {
        mathison_api_error('One or both players not found', 404);
    }
    if ($pa['id'] === $pb['id']) {
        mathison_api_error('Players must be different', 400);
    }

    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $self = array_map('strtolower', mathison_api_self_accounts());

    $stmt = $pdo->prepare(
        "SELECT r.ReplayId, r.Date_Played, r.Map, r.GameDuration,
                r.Player1_Race, r.Player2_Race,
                r.Player1_Result, r.Player2_Result,
                r.Player_Comments,
                p1.SC2_UserId AS p1, p2.SC2_UserId AS p2,
                r.Player1_Id, r.Player2_Id
         FROM $R r
         LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
         LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
         WHERE (r.Player1_Id = ? AND r.Player2_Id = ?)
            OR (r.Player1_Id = ? AND r.Player2_Id = ?)
         ORDER BY r.Date_Played DESC"
    );
    $stmt->execute([$pa['id'], $pb['id'], $pb['id'], $pa['id']]);

    $aWins = 0;
    $bWins = 0;
    $games = [];
    $total = 0;
    foreach ($stmt as $row) {
        $total++;
        $aIsP1 = ((int) $row['Player1_Id'] === $pa['id']);
        $aResult = $aIsP1 ? $row['Player1_Result'] : $row['Player2_Result'];
        $bResult = $aIsP1 ? $row['Player2_Result'] : $row['Player1_Result'];
        if ($aResult === 'Win') {
            $aWins++;
        }
        if ($bResult === 'Win') {
            $bWins++;
        }

        if (count($games) < $limit) {
            $games[] = [
                'replay_id' => (int) $row['ReplayId'],
                'date_played' => $row['Date_Played'],
                'map' => $row['Map'],
                'duration' => $row['GameDuration'],
                'a_race' => mathison_api_norm_race($aIsP1 ? $row['Player1_Race'] : $row['Player2_Race']),
                'b_race' => mathison_api_norm_race($aIsP1 ? $row['Player2_Race'] : $row['Player1_Race']),
                'a_result' => $aResult,
                'b_result' => $bResult,
                'comment' => trim((string) ($row['Player_Comments'] ?? '')) ?: null,
            ];
        }
    }

    $aIsSelf = in_array(strtolower($pa['name']), $self, true);
    $bIsSelf = in_array(strtolower($pb['name']), $self, true);

    return [
        'a' => $pa,
        'b' => $pb,
        'games_total' => $total,
        'record' => [
            'a_wins' => $aWins,
            'b_wins' => $bWins,
            'draws_or_other' => max(0, $total - $aWins - $bWins),
        ],
        'self_side' => $aIsSelf ? 'a' : ($bIsSelf ? 'b' : null),
        'recent' => $games,
    ];
}

function v1_replay(PDO $pdo, int $id, bool $includeSummary): array
{
    if ($id < 1) {
        mathison_api_error('Invalid id', 400);
    }
    $R = mathison_table('Replays');
    $P = mathison_table('Players');
    $cols = "r.ReplayId, r.UnixTimestamp, r.Date_Played, r.Date_Uploaded,
             r.Map, r.Region, r.GameType, r.GameDuration,
             r.Player1_Id, r.Player2_Id,
             r.Player1_Race, r.Player2_Race,
             r.Player1_PickRace, r.Player2_PickRace,
             r.Player1_Result, r.Player2_Result,
             r.Player_Comments,
             p1.SC2_UserId AS Player1_Name,
             p2.SC2_UserId AS Player2_Name";
    if ($includeSummary) {
        $cols .= ', r.Replay_Summary';
    }
    $stmt = $pdo->prepare(
        "SELECT $cols FROM $R r
         LEFT JOIN $P p1 ON p1.Id = r.Player1_Id
         LEFT JOIN $P p2 ON p2.Id = r.Player2_Id
         WHERE r.ReplayId = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        mathison_api_error('Replay not found', 404);
    }
    return $row;
}

function v1_player_last(PDO $pdo, string $name): array
{
    $data = v1_player_games($pdo, $name, 1, null);
    $game = $data['games'][0] ?? null;
    return [
        'name' => $data['name'],
        'last_played' => $game['date_played'] ?? null,
        'last_game' => $game,
    ];
}
