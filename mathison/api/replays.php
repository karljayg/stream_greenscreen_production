<?php
/**
 * Mathison replays API — list / get / create / update / delete
 *
 * GET    ?action=list|meta|get
 * POST   JSON body with action create|update|delete  (or HTTP method override)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pathPrefix = '../../';
require_once __DIR__ . '/../../partials/auth-gate.php';
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    $pdo = mathison_pdo();

    if ($method === 'GET') {
        if ($action === 'meta' || isset($_GET['meta'])) {
            echo json_encode(['ok' => true, 'data' => meta($pdo)]);
            exit;
        }
        if ($action === 'get' || isset($_GET['id'])) {
            $id = (int) ($_GET['id'] ?? 0);
            if ($id < 1) {
                json_error('Missing id', 400);
            }
            $row = fetch_one($pdo, $id, true);
            if (!$row) {
                json_error('Replay not found', 404);
            }
            echo json_encode(['ok' => true, 'data' => $row]);
            exit;
        }
        // default: list
        echo json_encode(['ok' => true, 'data' => list_replays($pdo)]);
        exit;
    }

    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($body)) {
        json_error('Invalid JSON body', 400);
    }
    $action = $body['action'] ?? $action;

    if ($method === 'POST' && $action === 'create') {
        $id = create_replay($pdo, $body);
        echo json_encode(['ok' => true, 'data' => fetch_one($pdo, $id, true)]);
        exit;
    }
    if (($method === 'POST' || $method === 'PUT' || $method === 'PATCH') && ($action === 'update' || isset($body['ReplayId']))) {
        $id = (int) ($body['ReplayId'] ?? $body['id'] ?? 0);
        if ($id < 1) {
            json_error('Missing ReplayId', 400);
        }
        update_replay($pdo, $id, $body);
        echo json_encode(['ok' => true, 'data' => fetch_one($pdo, $id, true)]);
        exit;
    }
    if (($method === 'POST' || $method === 'DELETE') && $action === 'delete') {
        $id = (int) ($body['ReplayId'] ?? $body['id'] ?? $_GET['id'] ?? 0);
        if ($id < 1) {
            json_error('Missing ReplayId', 400);
        }
        $stmt = $pdo->prepare('DELETE FROM ' . mathison_table('Replays') . ' WHERE ReplayId = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'deleted' => $id, 'affected' => $stmt->rowCount()]);
        exit;
    }

    json_error('Unknown action', 400);
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}

function json_error(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function list_columns(): array
{
    return [
        'r.ReplayId',
        'r.UnixTimestamp',
        'r.Player1_Id',
        'r.Player2_Id',
        'p1.SC2_UserId AS Player1_Name',
        'p2.SC2_UserId AS Player2_Name',
        'r.Player1_PickRace',
        'r.Player2_PickRace',
        'r.Player1_Race',
        'r.Player2_Race',
        'r.Player1_Result',
        'r.Player2_Result',
        'r.Date_Uploaded',
        'r.Date_Played',
        'r.Map',
        'r.Region',
        'r.GameType',
        'r.GameDuration',
        '(r.Replay_Summary IS NOT NULL AND r.Replay_Summary <> \'\') AS HasSummary',
        '(r.Player_Comments IS NOT NULL AND r.Player_Comments <> \'\') AS HasComments',
    ];
}

function sortable_columns(): array
{
    return [
        'ReplayId' => 'r.ReplayId',
        'UnixTimestamp' => 'r.UnixTimestamp',
        'Date_Played' => 'r.Date_Played',
        'Date_Uploaded' => 'r.Date_Uploaded',
        'Map' => 'r.Map',
        'Region' => 'r.Region',
        'GameType' => 'r.GameType',
        'GameDuration' => 'r.GameDuration',
        'Player1_Race' => 'r.Player1_Race',
        'Player2_Race' => 'r.Player2_Race',
        'Player1_Result' => 'r.Player1_Result',
        'Player1_Name' => 'p1.SC2_UserId',
        'Player2_Name' => 'p2.SC2_UserId',
    ];
}

function build_filters(array $src): array
{
    $where = [];
    $params = [];

    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = '(r.ReplayId = ? OR r.Player1_Id = ? OR r.Player2_Id = ? OR p1.SC2_UserId LIKE ? OR p2.SC2_UserId LIKE ? OR r.Map LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = (int) $q;
            $params[] = (int) $q;
            $params[] = (int) $q;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(p1.SC2_UserId LIKE ? OR p2.SC2_UserId LIKE ? OR r.Map LIKE ? OR r.Replay_Summary LIKE ? OR r.Player_Comments LIKE ? OR r.GameDuration LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
    }

    foreach (['Map' => 'r.Map', 'Region' => 'r.Region', 'GameType' => 'r.GameType'] as $key => $col) {
        $val = trim((string) ($src[$key] ?? $src[strtolower($key)] ?? ''));
        if ($val !== '') {
            $where[] = "$col = ?";
            $params[] = $val;
        }
    }

    $race = trim((string) ($src['race'] ?? ''));
    if ($race !== '') {
        $where[] = '(r.Player1_Race = ? OR r.Player2_Race = ?)';
        $params[] = $race;
        $params[] = $race;
    }

    $result = trim((string) ($src['result'] ?? ''));
    if ($result !== '') {
        $where[] = '(r.Player1_Result = ? OR r.Player2_Result = ?)';
        $params[] = $result;
        $params[] = $result;
    }

    $playerId = trim((string) ($src['playerId'] ?? ''));
    if ($playerId !== '' && ctype_digit($playerId)) {
        $where[] = '(r.Player1_Id = ? OR r.Player2_Id = ?)';
        $params[] = (int) $playerId;
        $params[] = (int) $playerId;
    }

    // Matchup: playerA vs playerB (order-independent). Accepts name or numeric player Id.
    $playerA = trim((string) ($src['playerA'] ?? ''));
    $playerB = trim((string) ($src['playerB'] ?? ''));
    if ($playerA !== '' && $playerB !== '') {
        [$c1a, $p1a] = player_side_match('p1.SC2_UserId', 'r.Player1_Id', $playerA);
        [$c2b, $p2b] = player_side_match('p2.SC2_UserId', 'r.Player2_Id', $playerB);
        [$c1b, $p1b] = player_side_match('p1.SC2_UserId', 'r.Player1_Id', $playerB);
        [$c2a, $p2a] = player_side_match('p2.SC2_UserId', 'r.Player2_Id', $playerA);
        $where[] = "(($c1a AND $c2b) OR ($c1b AND $c2a))";
        array_push($params, ...$p1a, ...$p2b, ...$p1b, ...$p2a);
    } elseif ($playerA !== '' || $playerB !== '') {
        $one = $playerA !== '' ? $playerA : $playerB;
        [$c1, $p1] = player_side_match('p1.SC2_UserId', 'r.Player1_Id', $one);
        [$c2, $p2] = player_side_match('p2.SC2_UserId', 'r.Player2_Id', $one);
        $where[] = "($c1 OR $c2)";
        array_push($params, ...$p1, ...$p2);
    }

    $idFrom = trim((string) ($src['idFrom'] ?? ''));
    if ($idFrom !== '' && ctype_digit($idFrom)) {
        $where[] = 'r.ReplayId >= ?';
        $params[] = (int) $idFrom;
    }
    $idTo = trim((string) ($src['idTo'] ?? ''));
    if ($idTo !== '' && ctype_digit($idTo)) {
        $where[] = 'r.ReplayId <= ?';
        $params[] = (int) $idTo;
    }

    $dateFrom = trim((string) ($src['dateFrom'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'r.Date_Played >= ?';
        $params[] = $dateFrom . (strlen($dateFrom) <= 10 ? ' 00:00:00' : '');
    }
    $dateTo = trim((string) ($src['dateTo'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'r.Date_Played <= ?';
        $params[] = $dateTo . (strlen($dateTo) <= 10 ? ' 23:59:59' : '');
    }

    // Starting-worker era filter. Uses Date_Played windows from
    // insights/rules.php (same eras as the insights dashboard). Timings
    // are not comparable across 6 / 12 / 8 worker starts.
    $startWorkers = trim((string) ($src['startWorkers'] ?? ''));
    if ($startWorkers !== '' && in_array($startWorkers, ['6', '8', '12'], true)) {
        $eraSql = start_workers_era_sql((int) $startWorkers);
        if ($eraSql !== null) {
            $where[] = $eraSql['sql'];
            array_push($params, ...$eraSql['params']);
        }
    }

    return [$where, $params];
}

/**
 * Build a Date_Played predicate for one starting-worker era.
 * Eras come from insights/rules.php worker_eras (until exclusive).
 */
function start_workers_era_sql(int $workers): ?array
{
    $rulesFile = __DIR__ . '/../insights/rules.php';
    if (!is_readable($rulesFile)) {
        return null;
    }
    $rules = require $rulesFile;
    $eras = $rules['worker_eras'] ?? [];
    $from = null;
    foreach ($eras as $era) {
        if ((int) $era['workers'] === $workers) {
            $until = $era['until'] ?? null;
            $parts = [];
            $params = [];
            if ($from !== null) {
                $parts[] = 'r.Date_Played >= ?';
                $params[] = $from . ' 00:00:00';
            }
            if ($until !== null) {
                $parts[] = 'r.Date_Played < ?';
                $params[] = $until . ' 00:00:00';
            }
            if (!$parts) {
                return null;
            }
            return ['sql' => '(' . implode(' AND ', $parts) . ')', 'params' => $params];
        }
        $from = $era['until'] ?? $from;
    }
    return null;
}

/**
 * Match one side of a replay by SC2 name (LIKE) or exact player Id when numeric.
 * @return array{0:string,1:array}
 */
function player_side_match(string $nameCol, string $idCol, string $val): array
{
    if (ctype_digit($val)) {
        return ["($idCol = ?)", [(int) $val]];
    }
    return ["($nameCol LIKE ?)", ['%' . $val . '%']];
}

function list_replays(PDO $pdo): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = (int) ($_GET['limit'] ?? 50);
    if ($limit < 10) {
        $limit = 10;
    }
    if ($limit > 200) {
        $limit = 200;
    }
    $offset = ($page - 1) * $limit;

    $sortKey = (string) ($_GET['sort'] ?? 'ReplayId');
    $sortable = sortable_columns();
    if (!isset($sortable[$sortKey])) {
        $sortKey = 'ReplayId';
    }
    $dir = strtoupper((string) ($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    [$where, $params] = build_filters($_GET);
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $from = 'FROM ' . mathison_table('Replays') . ' r
        LEFT JOIN ' . mathison_table('Players') . ' p1 ON p1.Id = r.Player1_Id
        LEFT JOIN ' . mathison_table('Players') . ' p2 ON p2.Id = r.Player2_Id';

    $countStmt = $pdo->prepare("SELECT COUNT(*) $from $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $cols = implode(",\n        ", list_columns());
    $orderCol = $sortable[$sortKey];
    $sql = "SELECT $cols $from $whereSql ORDER BY $orderCol $dir, r.ReplayId DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['HasSummary'] = (bool) ($row['HasSummary'] ?? false);
        $row['HasComments'] = (bool) ($row['HasComments'] ?? false);
    }
    unset($row);

    return [
        'rows' => $rows,
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        'sort' => $sortKey,
        'dir' => $dir,
    ];
}

function fetch_one(PDO $pdo, int $id, bool $full = false): ?array
{
    $cols = $full
        ? 'r.*, p1.SC2_UserId AS Player1_Name, p2.SC2_UserId AS Player2_Name'
        : implode(', ', list_columns());

    $stmt = $pdo->prepare(
        "SELECT $cols
         FROM " . mathison_table('Replays') . " r
         LEFT JOIN " . mathison_table('Players') . " p1 ON p1.Id = r.Player1_Id
         LEFT JOIN " . mathison_table('Players') . " p2 ON p2.Id = r.Player2_Id
         WHERE r.ReplayId = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function meta(PDO $pdo): array
{
    $T = mathison_table('Replays');
    $regions = $pdo->query("SELECT DISTINCT Region FROM $T WHERE Region IS NOT NULL AND Region <> '' ORDER BY Region")->fetchAll(PDO::FETCH_COLUMN);
    $types = $pdo->query("SELECT DISTINCT GameType FROM $T WHERE GameType IS NOT NULL AND GameType <> '' ORDER BY GameType")->fetchAll(PDO::FETCH_COLUMN);
    $races = $pdo->query("SELECT DISTINCT Player1_Race AS race FROM $T WHERE Player1_Race IS NOT NULL AND Player1_Race <> ''
        UNION SELECT DISTINCT Player2_Race FROM $T WHERE Player2_Race IS NOT NULL AND Player2_Race <> ''
        ORDER BY race")->fetchAll(PDO::FETCH_COLUMN);
    $maps = $pdo->query("SELECT Map, COUNT(*) AS c FROM $T WHERE Map IS NOT NULL AND Map <> '' GROUP BY Map ORDER BY c DESC LIMIT 80")->fetchAll();
    $total = (int) $pdo->query("SELECT COUNT(*) FROM $T")->fetchColumn();
    $rulesFile = __DIR__ . '/../insights/rules.php';
    $workerEras = is_readable($rulesFile) ? ((require $rulesFile)['worker_eras'] ?? []) : [];

    return [
        'regions' => $regions,
        'gameTypes' => $types,
        'races' => $races,
        'maps' => $maps,
        'total' => $total,
        'worker_eras' => $workerEras,
    ];
}

function writable_fields(): array
{
    return [
        'UnixTimestamp',
        'Player1_Id',
        'Player2_Id',
        'Player1_PickRace',
        'Player2_PickRace',
        'Player1_Race',
        'Player2_Race',
        'Player1_Result',
        'Player2_Result',
        'Date_Uploaded',
        'Date_Played',
        'Replay_Summary',
        'Player_Comments',
        'Map',
        'Region',
        'GameType',
        'GameDuration',
    ];
}

function normalize_payload(array $body): array
{
    $out = [];
    foreach (writable_fields() as $field) {
        if (!array_key_exists($field, $body)) {
            continue;
        }
        $val = $body[$field];
        if ($val === '') {
            $out[$field] = null;
            continue;
        }
        if (in_array($field, ['UnixTimestamp', 'Player1_Id', 'Player2_Id'], true)) {
            $out[$field] = ($val === null) ? null : (int) $val;
            continue;
        }
        $out[$field] = $val;
    }
    return $out;
}

function create_replay(PDO $pdo, array $body): int
{
    $data = normalize_payload($body);
    if (!isset($data['UnixTimestamp'])) {
        $data['UnixTimestamp'] = time();
    }
    if (!$data) {
        json_error('No fields to insert', 400);
    }
    $cols = array_keys($data);
    $placeholders = array_fill(0, count($cols), '?');
    $quotedCols = array_map(static function ($c) {
        return '`' . str_replace('`', '``', $c) . '`';
    }, $cols);
    $sql = 'INSERT INTO ' . mathison_table('Replays') . ' (' . implode(', ', $quotedCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

function update_replay(PDO $pdo, int $id, array $body): void
{
    $data = normalize_payload($body);
    if (!$data) {
        json_error('No fields to update', 400);
    }
    $sets = [];
    $params = [];
    foreach ($data as $col => $val) {
        $sets[] = '`' . str_replace('`', '``', $col) . '` = ?';
        $params[] = $val;
    }
    $params[] = $id;
    $sql = 'UPDATE ' . mathison_table('Replays') . ' SET ' . implode(', ', $sets) . ' WHERE ReplayId = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        // still ok if values unchanged — verify exists
        $check = $pdo->prepare('SELECT 1 FROM ' . mathison_table('Replays') . ' WHERE ReplayId = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            json_error('Replay not found', 404);
        }
    }
}
