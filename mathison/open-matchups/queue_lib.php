<?php
/**
 * Open Matchups — queue CSV + live session JSON + match archive + bot notify.
 */

/** App root = stream_production / stream_greenscreen_production. */
function om_app_root(): string
{
    return str_replace('\\', '/', dirname(__DIR__, 2));
}

/** Resolve a config path: relative → under app root; absolute → as-is. */
function om_resolve_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '';
    }
    if ($path[0] === '/' || preg_match('#^[A-Za-z]:/#', $path)) {
        return rtrim($path, '/');
    }
    return rtrim(om_app_root() . '/' . ltrim($path, '/'), '/');
}

/** Ignore leftover machine-specific Windows paths when running on Linux. */
function om_is_foreign_windows_path(string $path): bool
{
    return PHP_OS_FAMILY !== 'Windows' && (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
}

function om_default_data_dir(): string
{
    return om_resolve_path('data/open_matchups');
}

function om_config(?array $override = null): array
{
    static $cfg = null;
    if ($override !== null) {
        $cfg = $override;
        return $cfg;
    }
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = require __DIR__ . '/../config.php';
    $dir = trim((string) ($cfg['open_matchups_data_dir'] ?? ''));
    if ($dir === '' || om_is_foreign_windows_path($dir)) {
        $dir = 'data/open_matchups';
    }
    $cfg['open_matchups_data_dir'] = om_resolve_path($dir);
    return $cfg;
}

/** Test / CLI helper — point Open Matchups at an isolated data directory. */
function om_set_data_dir(string $dir): void
{
    $cfg = om_config();
    $cfg['open_matchups_data_dir'] = om_resolve_path($dir) ?: $dir;
    om_config($cfg);
}

function om_data_dir(): string
{
    $dir = rtrim(str_replace('\\', '/', (string) om_config()['open_matchups_data_dir']), '/');
    if ($dir === '') {
        $dir = om_default_data_dir();
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** True when this PHP request is on a local/dev host. */
function om_is_local_http_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }
    // Common local XAMPP / LAN hostnames
    if (substr($host, -6) === '.local' || substr($host, -4) === '.lan') {
        return true;
    }
    return false;
}

function om_remote_base(): string
{
    $base = trim((string) (om_config()['open_matchups_remote_base'] ?? ''));
    return rtrim($base, '/');
}

/**
 * local  = always read/write data/open_matchups on this machine
 * remote = always proxy to open_matchups_remote_base/api.php
 * auto   = localhost → remote (if base set); otherwise local
 *
 * Same idea as Stream Production /status: no API token required.
 */
function om_data_mode(): string
{
    $mode = strtolower(trim((string) (om_config()['open_matchups_data_mode'] ?? 'auto')));
    if (!in_array($mode, ['auto', 'local', 'remote'], true)) {
        $mode = 'auto';
    }
    return $mode;
}

function om_use_remote_data(): bool
{
    $mode = om_data_mode();
    if ($mode === 'local') {
        return false;
    }
    if ($mode === 'remote') {
        return om_remote_base() !== '';
    }
    // auto
    return om_is_local_http_host() && om_remote_base() !== '';
}

/**
 * Call the live-server Open Matchups API (GET snapshot or POST action).
 * Public JSON API — no Bearer token (mirrors /status).
 *
 * @return array{ok:bool, error?:string, http?:int, ...}
 */
function om_remote_api_request(string $method = 'GET', ?array $body = null): array
{
    $base = om_remote_base();
    if ($base === '') {
        return ['ok' => false, 'error' => 'open_matchups_remote_base is not configured'];
    }
    $url = $base . '/api.php';

    $headers = [
        'Accept: application/json',
    ];
    $opts = [
        'http' => [
            'method' => strtoupper($method) === 'POST' ? 'POST' : 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ];
    if (strtoupper($method) === 'POST') {
        $payload = json_encode($body ?? [], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['ok' => false, 'error' => 'Failed to encode remote payload'];
        }
        $opts['http']['header'] .= "Content-Type: application/json\r\n";
        $opts['http']['content'] = $payload;
    }

    $raw = @file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }
    if ($raw === false) {
        return ['ok' => false, 'error' => 'Remote Open Matchups unreachable: ' . $url, 'http' => $status];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [
            'ok' => false,
            'error' => 'Remote Open Matchups returned non-JSON (HTTP ' . $status . ')',
            'http' => $status,
            'raw' => substr($raw, 0, 200),
        ];
    }
    if ($status >= 400 && empty($data['ok'])) {
        $data['http'] = $status;
        if (empty($data['error'])) {
            $data['error'] = 'Remote API HTTP ' . $status;
        }
    }
    $data['data_source'] = 'remote';
    $data['remote_base'] = $base;
    return $data;
}

/** Proxy the current request to the remote API and exit. */
function om_proxy_remote_api_and_exit(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode(om_remote_api_request('GET'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = file_get_contents('php://input');
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    if (!is_array($body)) {
        $body = [];
    }
    echo json_encode(om_remote_api_request('POST', $body), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Board snapshot for index.php (local files or remote GET).
 *
 * @return array{ok:bool, queue:array, session:array, attention:array, history:array, undo:?array, data_source:string, error?:string}
 */
function om_board_snapshot(): array
{
    if (om_use_remote_data()) {
        $remote = om_remote_api_request('GET');
        if (empty($remote['ok'])) {
            return [
                'ok' => false,
                'queue' => [],
                'session' => om_default_session(),
                'attention' => [],
                'history' => [],
                'undo' => null,
                'data_source' => 'remote',
                'remote_base' => om_remote_base(),
                'error' => (string) ($remote['error'] ?? 'Remote snapshot failed'),
            ];
        }
        return [
            'ok' => true,
            'queue' => is_array($remote['queue'] ?? null) ? $remote['queue'] : [],
            'session' => is_array($remote['session'] ?? null) ? $remote['session'] : om_default_session(),
            'attention' => is_array($remote['attention'] ?? null) ? $remote['attention'] : [],
            'history' => is_array($remote['history'] ?? null) ? $remote['history'] : [],
            'undo' => $remote['undo'] ?? null,
            'data_source' => 'remote',
            'remote_base' => om_remote_base(),
            'data_dir' => (string) ($remote['data_dir'] ?? ''),
            'fsl_base' => (string) ($remote['fsl_base'] ?? om_fsl_web_base()),
        ];
    }

    $session = om_enrich_session(om_load_session());
    $queue = om_enrich_players(om_list_waiting($session));
    return [
        'ok' => true,
        'queue' => $queue,
        'session' => $session,
        'attention' => om_attention_items($queue, $session),
        'history' => om_list_history(50),
        'undo' => om_undo_info(),
        'archives' => om_list_archives(),
        'data_source' => 'local',
        'data_dir' => om_data_dir(),
        'fsl_base' => om_fsl_web_base(),
    ];
}

function om_queue_path(): string { return om_data_dir() . '/queue.csv'; }
function om_requests_path(): string { return om_data_dir() . '/requests.csv'; }
function om_session_path(): string { return om_data_dir() . '/session.json'; }
function om_history_path(): string { return om_data_dir() . '/matches_history.csv'; }
function om_notify_path(): string { return om_data_dir() . '/notify.jsonl'; }

function om_queue_fields(): array
{
    return [
        'twitch_name', 'fsl_name', 'player_id', 'is_guest', 'skill',
        'fsl_rank', 'fsl_group', 'race', 'team', 'match_mode', 'custom_type',
        'joined_at', 'status',
    ];
}

function om_request_fields(): array
{
    return [
        'timestamp', 'event', 'twitch_name', 'fsl_name', 'player_id', 'is_guest',
        'skill', 'fsl_rank', 'fsl_group', 'race', 'team', 'match_mode',
        'custom_type', 'notes',
    ];
}

function om_history_fields(): array
{
    return [
        'ended_at', 'started_at', 'mode', 'best_of', 'score_a', 'score_b',
        'winner_side', 'side_a_names', 'side_b_names', 'side_a_twitch',
        'side_b_twitch', 'side_a_json', 'side_b_json', 'notes',
    ];
}

function om_read_csv(string $path, array $fields): array
{
    if (!is_readable($path)) {
        return [];
    }
    $fh = fopen($path, 'r');
    if ($fh === false) {
        return [];
    }
    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        return [];
    }
    $header = array_map('strval', $header);
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        $row = [];
        foreach ($fields as $key) {
            $idx = array_search($key, $header, true);
            $row[$key] = ($idx !== false && isset($data[$idx])) ? (string) $data[$idx] : '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function om_write_csv(string $path, array $fields, array $rows): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = $path . '.tmp';
    $fh = fopen($tmp, 'w');
    if ($fh === false) {
        return false;
    }
    fputcsv($fh, $fields);
    foreach ($rows as $row) {
        $line = [];
        foreach ($fields as $key) {
            $line[] = isset($row[$key]) ? (string) $row[$key] : '';
        }
        fputcsv($fh, $line);
    }
    fclose($fh);
    return rename($tmp, $path);
}

function om_empty_match(int $bestOf = 3): array
{
    return [
        'live' => false,
        'started_at' => null,
        'best_of' => $bestOf,
        'mode' => '1v1',
        'score_a' => 0,
        'score_b' => 0,
        'side_a' => [],
        'side_b' => [],
    ];
}

function om_default_session(): array
{
    return [
        'best_of_default' => 3,
        'games_tonight' => 0,
        'playing' => om_empty_match(3),
        'on_deck' => om_empty_match(3),
        'played_counts' => [],
    ];
}

function om_load_session(): array
{
    $path = om_session_path();
    if (!is_readable($path)) {
        $s = om_default_session();
        om_save_session($s);
        return $s;
    }
    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return om_default_session();
    }
    $base = om_default_session();
    $out = array_merge($base, $data);
    $out['playing'] = array_merge(om_empty_match(3), is_array($data['playing'] ?? null) ? $data['playing'] : []);
    $out['on_deck'] = array_merge(om_empty_match(3), is_array($data['on_deck'] ?? null) ? $data['on_deck'] : []);
    if (!is_array($out['played_counts'])) {
        $out['played_counts'] = [];
    }
    return $out;
}

function om_save_session(array $session): bool
{
    $path = om_session_path();
    $tmp = $path . '.tmp';
    $json = json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    if (file_put_contents($tmp, $json) === false) {
        return false;
    }
    return rename($tmp, $path);
}

/** Undo snapshots live next to session data (host can reverse last N actions). */
function om_undo_path(): string
{
    return om_data_dir() . '/undo_stack.json';
}

function om_load_undo_stack(): array
{
    $path = om_undo_path();
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function om_save_undo_stack(array $stack): bool
{
    $path = om_undo_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = $path . '.tmp';
    $json = json_encode(array_values($stack), JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    if (file_put_contents($tmp, $json) === false) {
        return false;
    }
    return rename($tmp, $path);
}

/**
 * Snapshot board state before a destructive / reversible host action.
 * Restores session.json + waiting queue + results history.
 */
function om_push_undo(string $action, string $label = ''): void
{
    $stack = om_load_undo_stack();
    array_unshift($stack, [
        'id' => bin2hex(random_bytes(6)),
        'at' => gmdate('c'),
        'action' => $action,
        'label' => $label !== '' ? $label : $action,
        'session' => om_load_session(),
        'queue' => om_list_queue(),
        'history' => om_read_csv(om_history_path(), om_history_fields()),
    ]);
    // Keep last 20 — enough for a stream night without huge files
    $stack = array_slice($stack, 0, 20);
    om_save_undo_stack($stack);
}

function om_undo_info(): ?array
{
    $stack = om_load_undo_stack();
    if (!$stack) {
        return null;
    }
    $top = $stack[0];
    if (!is_array($top)) {
        return null;
    }
    return [
        'action' => (string) ($top['action'] ?? ''),
        'label' => (string) ($top['label'] ?? 'last action'),
        'at' => (string) ($top['at'] ?? ''),
        'depth' => count($stack),
    ];
}

/**
 * Restore the most recent undo snapshot (session + queue + history).
 */
function om_undo_last(): array
{
    $stack = om_load_undo_stack();
    if (!$stack) {
        return ['ok' => false, 'error' => 'Nothing to undo'];
    }
    $entry = array_shift($stack);
    if (!is_array($entry)) {
        om_save_undo_stack($stack);
        return ['ok' => false, 'error' => 'Corrupt undo entry'];
    }
    $session = $entry['session'] ?? null;
    if (!is_array($session)) {
        return ['ok' => false, 'error' => 'Undo snapshot missing session'];
    }
    $queue = is_array($entry['queue'] ?? null) ? $entry['queue'] : [];
    $history = is_array($entry['history'] ?? null) ? $entry['history'] : [];

    om_save_session($session);
    om_write_csv(om_queue_path(), om_queue_fields(), $queue);
    om_write_csv(om_history_path(), om_history_fields(), $history);
    om_save_undo_stack($stack);

    $label = (string) ($entry['label'] ?? $entry['action'] ?? 'action');
    om_log_request('undo', ['notes' => 'restored: ' . $label]);
    return [
        'ok' => true,
        'session' => om_load_session(),
        'undid' => $label,
        'public' => 'Undid: ' . $label,
        'undo' => om_undo_info(),
    ];
}

/**
 * Drop the top undo entry when a specialized undo already applied the change
 * (e.g. Undo live after go_live pushed a snapshot).
 */
function om_pop_undo_if_action(string $action): void
{
    $stack = om_load_undo_stack();
    if (!$stack || !is_array($stack[0] ?? null)) {
        return;
    }
    if ((string) ($stack[0]['action'] ?? '') === $action) {
        array_shift($stack);
        om_save_undo_stack($stack);
    }
}

function om_list_queue(): array
{
    $rows = om_read_csv(om_queue_path(), om_queue_fields());
    usort($rows, static function ($a, $b) {
        return strcmp((string) ($a['joined_at'] ?? ''), (string) ($b['joined_at'] ?? ''));
    });
    return $rows;
}

/** Twitch logins currently in Playing or On Deck (lowercase). */
function om_session_busy_keys(array $session): array
{
    $keys = [];
    foreach (['playing', 'on_deck'] as $matchKey) {
        foreach (['side_a', 'side_b'] as $side) {
            foreach ($session[$matchKey][$side] ?? [] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $k = strtolower(trim((string) ($p['twitch_name'] ?? '')));
                if ($k !== '') {
                    $keys[$k] = true;
                }
            }
        }
    }
    return $keys;
}

/**
 * Waiting list for the dashboard — never includes anyone already in Playing / On Deck.
 * Also heals incomplete FSL rows (wrong casing / missing team+id) via case-insensitive resolve.
 */
function om_list_waiting(?array $session = null): array
{
    $session = $session ?? om_load_session();
    om_heal_queue_fsl_profiles();
    $busy = om_session_busy_keys($session);
    $out = [];
    foreach (om_list_queue() as $p) {
        $k = strtolower(trim((string) ($p['twitch_name'] ?? '')));
        if ($k !== '' && isset($busy[$k])) {
            continue;
        }
        $out[] = $p;
    }
    return $out;
}

/**
 * Fix waiting-queue rows that have an FSL name but missing/wrong-cased identity fields.
 */
function om_heal_queue_fsl_profiles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $rows = om_list_queue();
    if (!$rows) {
        return;
    }
    $changed = false;
    $out = [];
    foreach ($rows as $p) {
        if (!is_array($p)) {
            continue;
        }
        $isGuest = in_array((string) ($p['is_guest'] ?? ''), ['1', 'true', 'True'], true);
        $hint = trim((string) ($p['fsl_name'] ?? ''));
        if ($hint === '') {
            $hint = trim((string) ($p['twitch_name'] ?? ''));
        }
        if (!$isGuest && $hint !== '' && (
            trim((string) ($p['player_id'] ?? '')) === ''
            || trim((string) ($p['team'] ?? '')) === ''
        )) {
            $before = $p;
            $p = om_refresh_player_from_fsl($p, true);
            if (
                (string) ($p['fsl_name'] ?? '') !== (string) ($before['fsl_name'] ?? '')
                || (string) ($p['player_id'] ?? '') !== (string) ($before['player_id'] ?? '')
                || (string) ($p['team'] ?? '') !== (string) ($before['team'] ?? '')
                || (string) ($p['skill'] ?? '') !== (string) ($before['skill'] ?? '')
            ) {
                $changed = true;
            }
        } elseif (!$isGuest && $hint !== '') {
            // Casing-only heal via rankings/API even when id present
            $resolved = om_fsl_lookup_player($hint);
            if ($resolved && isset($resolved['fsl_name'])
                && strcasecmp((string) ($p['fsl_name'] ?? ''), $resolved['fsl_name']) === 0
                && (string) ($p['fsl_name'] ?? '') !== $resolved['fsl_name']
            ) {
                $p = om_refresh_player_from_fsl($p, true);
                $changed = true;
            }
        }
        $out[] = $p;
    }
    if ($changed) {
        om_write_csv(om_queue_path(), om_queue_fields(), $out);
    }
}

/** Drop these twitch names from queue.csv (idempotent). */
function om_scrub_from_queue(array $twitchNames): void
{
    $drop = [];
    foreach ($twitchNames as $n) {
        $k = strtolower(trim((string) $n));
        if ($k !== '') {
            $drop[$k] = true;
        }
    }
    if (!$drop) {
        return;
    }
    $kept = [];
    foreach (om_list_queue() as $r) {
        $k = strtolower(trim((string) ($r['twitch_name'] ?? '')));
        if (isset($drop[$k])) {
            continue;
        }
        $kept[] = $r;
    }
    om_write_csv(om_queue_path(), om_queue_fields(), $kept);
}

function om_log_request(string $event, array $payload = []): void
{
    $fields = om_request_fields();
    $rows = om_read_csv(om_requests_path(), $fields);
    $row = [];
    foreach ($fields as $k) {
        $row[$k] = isset($payload[$k]) ? (string) $payload[$k] : '';
    }
    $row['timestamp'] = gmdate('c');
    $row['event'] = $event;
    $rows[] = $row;
    om_write_csv(om_requests_path(), $fields, $rows);
}

function om_clear_queue(): int
{
    $rows = om_list_queue();
    $n = count($rows);
    if ($n > 0) {
        om_push_undo('clear_queue', 'Clear waiting queue (' . $n . ')');
    }
    om_write_csv(om_queue_path(), om_queue_fields(), []);
    om_log_request('clear_queue', ['notes' => 'cleared ' . $n . ' via dashboard']);
    return $n;
}

function om_take_from_queue(string $twitchName): ?array
{
    $key = strtolower(trim($twitchName));
    $rows = om_list_queue();
    $kept = [];
    $found = null;
    foreach ($rows as $r) {
        if (strtolower(trim((string) ($r['twitch_name'] ?? ''))) === $key) {
            $found = $r;
            continue;
        }
        $kept[] = $r;
    }
    if ($found !== null) {
        om_write_csv(om_queue_path(), om_queue_fields(), $kept);
    }
    return $found;
}

function om_return_to_queue(array $player): void
{
    $key = strtolower(trim((string) ($player['twitch_name'] ?? '')));
    if ($key === '') {
        return;
    }
    $rows = om_list_queue();
    $kept = [];
    foreach ($rows as $r) {
        if (strtolower(trim((string) ($r['twitch_name'] ?? ''))) === $key) {
            continue; // replace existing waiting row
        }
        $kept[] = $r;
    }
    if (empty($player['joined_at'])) {
        $player['joined_at'] = gmdate('c');
    }
    $player['status'] = 'waiting';
    $row = [];
    foreach (om_queue_fields() as $f) {
        $row[$f] = (string) ($player[$f] ?? '');
    }
    $kept[] = $row;
    om_write_csv(om_queue_path(), om_queue_fields(), $kept);
}

function om_remove_from_queue(string $twitchName): bool
{
    $p = om_take_from_queue($twitchName);
    if ($p !== null) {
        om_log_request('removed', ['twitch_name' => $twitchName, 'notes' => 'via dashboard']);
        return true;
    }
    return false;
}

/**
 * Manually add a player to the waiting queue from the host dashboard.
 */
function om_add_player_manual(array $fields): array
{
    $twitch = trim((string) ($fields['twitch_name'] ?? ''));
    if ($twitch === '') {
        return ['ok' => false, 'error' => 'Twitch name is required'];
    }

    $loc = om_find_player_location(om_load_session(), $twitch);
    if ($loc !== null) {
        $where = $loc['match'] === 'waiting' ? 'waiting queue' : (string) $loc['match'];
        return ['ok' => false, 'error' => "@{$twitch} is already in {$where}"];
    }

    $isGuest = !empty($fields['is_guest']) && (string) $fields['is_guest'] !== '0';
    $fslName = trim((string) ($fields['fsl_name'] ?? ''));
    $skill = trim((string) ($fields['skill'] ?? ''));
    $rank = trim((string) ($fields['fsl_rank'] ?? ''));
    $group = trim((string) ($fields['fsl_group'] ?? ''));
    $race = trim((string) ($fields['race'] ?? ''));
    $mode = trim((string) ($fields['match_mode'] ?? '1v1'));
    $custom = trim((string) ($fields['custom_type'] ?? ''));
    $team = trim((string) ($fields['team'] ?? ''));
    $playerId = trim((string) ($fields['player_id'] ?? ''));

    if ($mode === '') {
        $mode = '1v1';
    }
    if ($isGuest && $skill === '') {
        $skill = 'Est. —';
    }
    if (!$isGuest && $fslName === '') {
        $fslName = $twitch; // try resolve from twitch login
    }

    $player = [
        'twitch_name' => $twitch,
        'fsl_name' => $isGuest ? $fslName : ($fslName !== '' ? $fslName : $twitch),
        'player_id' => $playerId,
        'is_guest' => $isGuest ? '1' : '0',
        'skill' => $skill,
        'fsl_rank' => $isGuest ? '' : ltrim($rank, '#'),
        'fsl_group' => $isGuest ? '' : preg_replace('/^G/i', '', $group),
        'race' => $race !== '' ? $race : '',
        'team' => $team,
        'match_mode' => $mode,
        'custom_type' => $mode === 'custom' ? $custom : '',
        'joined_at' => gmdate('c'),
        'status' => 'waiting',
    ];

    // Case-insensitive FSL resolve → canonical name + team + id + rank/group/skill
    if (!$isGuest) {
        $player = om_refresh_player_from_fsl($player, true);
    } elseif ($skill === '' && ($rank !== '' || $group !== '')) {
        $bits = [];
        if ($rank !== '') {
            $bits[] = '#' . ltrim($rank, '#');
        }
        if ($group !== '') {
            $bits[] = 'Group ' . preg_replace('/^G/i', '', $group);
        }
        $player['skill'] = 'FSL ' . implode(' · ', $bits);
    }

    om_return_to_queue($player);
    om_log_request('manual_add', [
        'twitch_name' => $twitch,
        'fsl_name' => $player['fsl_name'],
        'player_id' => $player['player_id'],
        'is_guest' => $player['is_guest'],
        'skill' => $player['skill'],
        'race' => $player['race'],
        'team' => $player['team'],
        'match_mode' => $mode,
        'notes' => 'added via dashboard',
    ]);

    $who = om_display_name($player);
    om_notify(
        $twitch,
        "You've been added to the Open Matchups queue as {$who} ({$mode}). The host will pick matchups.",
        ''
    );

    return ['ok' => true, 'player' => $player, 'session' => om_load_session()];
}

/**
 * Update a player already in waiting / on deck / playing.
 */
function om_update_player(array $fields): array
{
    $twitch = trim((string) ($fields['twitch_name'] ?? ''));
    if ($twitch === '') {
        return ['ok' => false, 'error' => 'Twitch name is required'];
    }
    $session = om_load_session();
    $loc = om_find_player_location($session, $twitch);
    if ($loc === null) {
        return ['ok' => false, 'error' => 'Player not found'];
    }
    $player = $loc['player'];
    $keys = [
        'fsl_name', 'player_id', 'skill', 'fsl_rank', 'fsl_group',
        'race', 'team', 'match_mode', 'custom_type',
    ];
    foreach ($keys as $k) {
        if (array_key_exists($k, $fields)) {
            $player[$k] = trim((string) $fields[$k]);
        }
    }
    if (array_key_exists('is_guest', $fields)) {
        $player['is_guest'] = (!empty($fields['is_guest']) && (string) $fields['is_guest'] !== '0') ? '1' : '0';
    }
    if (isset($player['fsl_rank'])) {
        $player['fsl_rank'] = ltrim((string) $player['fsl_rank'], '#');
    }
    if (isset($player['fsl_group'])) {
        $player['fsl_group'] = preg_replace('/^G/i', '', (string) $player['fsl_group']);
    }

    $isGuest = in_array((string) ($player['is_guest'] ?? ''), ['1', 'true', 'True'], true);
    if (!$isGuest) {
        // Re-resolve whenever FSL name / id / team may have changed
        $player = om_refresh_player_from_fsl($player, true);
    }

    // Remove from current location and re-place
    $extracted = om_extract_player($session, $twitch);
    if ($extracted === null && $loc['match'] === 'waiting') {
        // already taken from queue by extract when on deck/playing; for waiting extract uses take
    }
    $dest = 'waiting';
    if ($loc['match'] === 'playing') {
        $dest = ($loc['side'] === 'side_b') ? 'playing_b' : 'playing_a';
    } elseif ($loc['match'] === 'on_deck') {
        $dest = ($loc['side'] === 'side_b') ? 'ondeck_b' : 'ondeck_a';
    }
    // Prefer updated player payload
    $player['twitch_name'] = $twitch;
    if ($dest === 'waiting') {
        // ensure not duplicated: extract already removed from queue if waiting
        om_return_to_queue($player);
        om_save_session($session);
    } else {
        om_place_player($session, $player, $dest);
        om_save_session($session);
    }
    om_log_request('update_player', [
        'twitch_name' => $twitch,
        'fsl_name' => $player['fsl_name'] ?? '',
        'player_id' => $player['player_id'] ?? '',
        'team' => $player['team'] ?? '',
        'notes' => 'dashboard edit + FSL refresh',
    ]);
    return ['ok' => true, 'player' => $player, 'session' => om_load_session()];
}

/**
 * Look up an FSL player case-insensitively (by-name, then search).
 * Returns canonical Real_Name, Player_ID, Team_Name, plus rank/group/skill from rankings.json.
 *
 * @return array<string,string>|null
 */
function om_fsl_lookup_player(string $hint): ?array
{
    $hint = trim($hint);
    if ($hint === '') {
        return null;
    }
    static $cache = [];
    $ck = strtolower($hint);
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $row = om_fsl_api_by_name($hint);
    if ($row === null) {
        $row = om_fsl_api_search_exact($hint);
    }
    if ($row === null) {
        // rankings.json only — still fix casing for thumbs/rank when API unavailable
        $rankRow = om_rankings_lookup($hint);
        if ($rankRow === null) {
            $cache[$ck] = null;
            return null;
        }
        $out = om_profile_from_rankings($rankRow);
        $cache[$ck] = $out;
        $cache[strtolower($out['fsl_name'])] = $out;
        return $out;
    }

    $real = trim((string) ($row['Real_Name'] ?? $row['real_name'] ?? ''));
    $out = [
        'fsl_name' => $real,
        'player_id' => (string) ($row['Player_ID'] ?? $row['player_id'] ?? ''),
        'team' => trim((string) ($row['Team_Name'] ?? $row['team_name'] ?? '')),
        'fsl_rank' => '',
        'fsl_group' => '',
        'skill' => '',
        'race' => '',
    ];
    $rankRow = om_rankings_lookup($real !== '' ? $real : $hint);
    if ($rankRow !== null) {
        $out = array_merge($out, om_profile_from_rankings($rankRow));
        // Keep API identity fields authoritative
        $out['fsl_name'] = $real !== '' ? $real : $out['fsl_name'];
        $out['player_id'] = (string) ($row['Player_ID'] ?? $row['player_id'] ?? $out['player_id']);
        $out['team'] = trim((string) ($row['Team_Name'] ?? $row['team_name'] ?? $out['team']));
    } elseif ($out['fsl_name'] !== '') {
        $out['skill'] = 'FSL';
    }

    $cache[$ck] = $out;
    if ($out['fsl_name'] !== '') {
        $cache[strtolower($out['fsl_name'])] = $out;
    }
    return $out;
}

/**
 * Merge FSL lookup into a queue/session player. Overwrites identity fields when found.
 * Host-supplied race/mode are kept unless empty and rankings provide race.
 */
function om_refresh_player_from_fsl(array $player, bool $overwriteIdentity = true): array
{
    $isGuest = in_array((string) ($player['is_guest'] ?? ''), ['1', 'true', 'True'], true);
    if ($isGuest) {
        return $player;
    }
    $hint = trim((string) ($player['fsl_name'] ?? ''));
    if ($hint === '') {
        $hint = trim((string) ($player['twitch_name'] ?? ''));
    }
    $resolved = om_fsl_lookup_player($hint);
    if ($resolved === null) {
        return $player;
    }
    if ($overwriteIdentity) {
        $player['fsl_name'] = $resolved['fsl_name'];
        $player['player_id'] = $resolved['player_id'];
        $player['team'] = $resolved['team'];
        $player['fsl_rank'] = $resolved['fsl_rank'];
        $player['fsl_group'] = $resolved['fsl_group'];
        if ($resolved['skill'] !== '') {
            $player['skill'] = $resolved['skill'];
        }
        if (trim((string) ($player['race'] ?? '')) === '' && $resolved['race'] !== '') {
            $player['race'] = $resolved['race'];
        }
    }
    return $player;
}

function om_fsl_api_headers(): array
{
    $cfg = om_config();
    $key = trim((string) ($cfg['fsl_api_key'] ?? $cfg['api_server_key'] ?? ''));
    $headers = ['Accept: application/json'];
    if ($key !== '') {
        $headers[] = 'Authorization: Bearer ' . $key;
    }
    return $headers;
}

function om_fsl_api_base(): string
{
    $cfg = om_config();
    $base = (string) ($cfg['fsl_api_base'] ?? $cfg['api_server_base'] ?? 'https://psistorm.com/api-server/public');
    return rtrim($base, '/');
}

function om_fsl_api_get(string $path, array $query = []): ?array
{
    $url = om_fsl_api_base() . $path;
    if ($query) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", om_fsl_api_headers()),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function om_fsl_api_by_name(string $name): ?array
{
    $data = om_fsl_api_get('/api/v1/fsl/players/by-name', ['name' => $name]);
    if (!is_array($data)) {
        return null;
    }
    $player = $data['player'] ?? null;
    return is_array($player) ? $player : null;
}

/** Search then keep case-insensitive exact Real_Name hit. */
function om_fsl_api_search_exact(string $name): ?array
{
    $data = om_fsl_api_get('/api/v1/fsl/players/search', ['q' => $name, 'limit' => 40]);
    if (!is_array($data)) {
        return null;
    }
    $rows = $data['players'] ?? $data['rows'] ?? [];
    if (!is_array($rows)) {
        return null;
    }
    $needle = strtolower(trim($name));
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $rn = strtolower(trim((string) ($r['Real_Name'] ?? $r['real_name'] ?? '')));
        if ($rn === $needle) {
            return $r;
        }
    }
    return null;
}

/** Likely FSL roots relative to this app (server vs local tools/ layout). */
function om_fsl_root_candidates(): array
{
    $app = om_app_root();
    return [
        dirname($app) . '/fsl',      // .../psistorm/fsl  (prod)
        dirname($app, 2) . '/fsl',   // .../psistorm.com/fsl  (local under tools/)
    ];
}

function om_rankings_path(): string
{
    $cfg = om_config();
    $configured = trim((string) ($cfg['fsl_rankings_json'] ?? ''));
    $paths = [];
    if ($configured !== '' && !om_is_foreign_windows_path($configured)) {
        $paths[] = om_resolve_path($configured);
    }
    foreach (om_fsl_root_candidates() as $root) {
        $paths[] = rtrim(str_replace('\\', '/', $root), '/') . '/rankings/rankings.json';
    }
    foreach ($paths as $p) {
        if ($p !== '' && is_readable($p)) {
            return $p;
        }
    }
    return '';
}

function om_rankings_lookup(string $name): ?array
{
    static $byName = null;
    if ($byName === null) {
        $byName = [];
        $path = om_rankings_path();
        if ($path !== '') {
            $raw = @file_get_contents($path);
            $rows = $raw ? json_decode($raw, true) : null;
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $n = trim((string) ($r['name'] ?? ''));
                    if ($n !== '') {
                        $byName[strtolower($n)] = $r;
                    }
                }
            }
        }
    }
    $key = strtolower(trim($name));
    return $byName[$key] ?? null;
}

function om_profile_from_rankings(array $rankRow): array
{
    $name = trim((string) ($rankRow['name'] ?? ''));
    $rank = isset($rankRow['rank']) ? (int) $rankRow['rank'] : 0;
    $group = isset($rankRow['group']) ? (int) $rankRow['group'] : ($rank > 0 ? (int) ceil($rank / 4) : 0);
    $code = '';
    if ($rank >= 1 && $rank <= 20) {
        $code = 'S';
    } elseif ($rank >= 21 && $rank <= 38) {
        $code = 'A';
    } elseif ($rank >= 39 && $rank <= 46) {
        $code = 'B';
    }
    $skill = 'FSL';
    if ($code !== '' && $rank > 0) {
        $skill = "FSL Code {$code} · Rank {$rank}, Group {$group}";
    } elseif ($rank > 0) {
        $skill = "FSL Rank {$rank}, Group {$group}";
    }
    $raceMap = ['P' => 'Protoss', 'T' => 'Terran', 'Z' => 'Zerg', 'R' => 'Random'];
    $raceLetter = strtoupper(trim((string) ($rankRow['race'] ?? '')));
    return [
        'fsl_name' => $name,
        'player_id' => '',
        'team' => '',
        'fsl_rank' => $rank > 0 ? (string) $rank : '',
        'fsl_group' => $group > 0 ? (string) $group : '',
        'skill' => $skill,
        'race' => $raceMap[$raceLetter] ?? '',
    ];
}

/**
 * Public FSL asset base (same host/paths as season review articles).
 * Override in mathison config.local.php: 'fsl_public_base' => 'https://psistorm.com/fsl'
 */
function om_fsl_web_base(): string
{
    $cfg = om_config();
    $base = (string) ($cfg['fsl_public_base'] ?? 'https://psistorm.com/fsl');
    return rtrim($base, '/') . '/';
}

/**
 * Team square logo URL — mirrors fsl/includes/team_logo.php map + article paths.
 * Example: images/FSL_team_square_logo_PSIOP_Gaming_256px.png
 */
function om_team_logo_url(?string $teamName): string
{
    $teamName = trim(preg_replace('/\s+/', ' ', (string) $teamName));
    if ($teamName === '' || strcasecmp($teamName, 'TBD') === 0) {
        return '';
    }
    $logoMap = [
        'Infinite Cyclists' => 'FSL_team_square_logo_Infinite_Cyclists',
        'Rages Raiders' => 'FSL_team_square_logo_Rages_Raiders',
        "Rage's Raiders" => 'FSL_team_square_logo_Rages_Raiders',
        'Angry Space Hares' => 'FSL_team_square_logo_Angry_Space_Hares',
        'PulledTheBoys' => 'FSL_team_square_logo_PulledTheBoys',
        'Pulled The Boys' => 'FSL_team_square_logo_PulledTheBoys',
        'CheesyNachos' => 'FSL_team_square_logo_CheesyNachos',
        'Cheesy Nachos' => 'FSL_team_square_logo_CheesyNachos',
        'PSIOP Gaming' => 'FSL_team_square_logo_PSISOP_Gaming',
        'Special Tactics' => 'FSL_team_square_logo_SpecialTactics',
    ];
    $baseName = $logoMap[$teamName] ?? null;
    if ($baseName === null) {
        foreach ($logoMap as $key => $val) {
            if (strcasecmp($key, $teamName) === 0) {
                $baseName = $val;
                break;
            }
        }
    }
    if ($baseName === null) {
        return '';
    }
    return om_fsl_web_base() . 'images/' . $baseName . '_256px.png';
}

/**
 * Player thumbnail URL — mirrors fsl/includes/player_thumbnail.php + articles.
 * Example: images/player_thumbnails/NuKLeO.png
 * Resolves local filenames case-insensitively so "sopuli" still finds "Sopuli.png".
 */
function om_player_thumb_url(?string $fslName): string
{
    $name = trim((string) $fslName);
    if ($name === '' || strcasecmp($name, 'NEW GUEST') === 0) {
        return '';
    }
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace(' ', '_', $name));
    if ($safe === '' || $safe === 'unknown') {
        return '';
    }
    $file = om_resolve_thumb_basename($safe);
    return om_fsl_web_base() . 'images/player_thumbnails/' . $file;
}

/** Case-insensitive resolve of thumbnail basename under fsl/images/player_thumbnails. */
function om_resolve_thumb_basename(string $safe): string
{
    static $index = null;
    $fallback = $safe . '.png';
    $dir = '';
    foreach (om_fsl_root_candidates() as $root) {
        $try = rtrim(str_replace('\\', '/', $root), '/') . '/images/player_thumbnails';
        if (is_dir($try)) {
            $dir = $try;
            break;
        }
    }
    if ($dir === '') {
        return $fallback;
    }
    if ($index === null) {
        $index = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $index[strtolower($f)] = $f;
        }
    }
    foreach ([$safe . '.png', $safe . '.jpg', $safe . '.jpeg', $safe . '.webp'] as $try) {
        $k = strtolower($try);
        if (isset($index[$k])) {
            return $index[$k];
        }
    }
    return $fallback;
}

function om_enrich_player(array $p): array
{
    $p['thumb_url'] = om_player_thumb_url($p['fsl_name'] ?? '');
    $p['team_logo_url'] = om_team_logo_url($p['team'] ?? '');
    return $p;
}

function om_enrich_players(array $rows): array
{
    $out = [];
    foreach ($rows as $p) {
        $out[] = is_array($p) ? om_enrich_player($p) : $p;
    }
    return $out;
}

function om_enrich_session(array $session): array
{
    foreach (['playing', 'on_deck'] as $mk) {
        if (!isset($session[$mk]) || !is_array($session[$mk])) {
            continue;
        }
        foreach (['side_a', 'side_b'] as $side) {
            $session[$mk][$side] = om_enrich_players($session[$mk][$side] ?? []);
        }
    }
    return $session;
}

function om_display_name(array $p): string
{
    $fsl = trim((string) ($p['fsl_name'] ?? ''));
    if ($fsl !== '') {
        return $fsl;
    }
    $isGuest = in_array((string) ($p['is_guest'] ?? ''), ['1', 'true', 'True'], true);
    if ($isGuest) {
        return 'NEW GUEST';
    }
    return (string) ($p['twitch_name'] ?? '');
}

function om_wait_minutes(string $joinedAt): int
{
    if ($joinedAt === '') {
        return 0;
    }
    try {
        $dt = new DateTimeImmutable($joinedAt);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return max(0, (int) floor(($now->getTimestamp() - $dt->getTimestamp()) / 60));
    } catch (Throwable $e) {
        return 0;
    }
}

function om_wait_label(string $joinedAt): string
{
    $mins = om_wait_minutes($joinedAt);
    if ($joinedAt === '') {
        return '—';
    }
    if ($mins < 1) {
        return '<1m';
    }
    if ($mins < 60) {
        return $mins . 'm';
    }
    return intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm';
}

function om_wait_class(string $wait): string
{
    if (str_contains($wait, 'h')) {
        return 'wait-long';
    }
    if (str_ends_with($wait, 'm') && !str_starts_with($wait, '<')) {
        $n = (int) substr($wait, 0, -1);
        if ($n >= 30) {
            return 'wait-long';
        }
        if ($n >= 15) {
            return 'wait-medium';
        }
    }
    return 'wait-normal';
}

function om_notify(string $whisperTo, string $whisper, string $public = ''): void
{
    $line = json_encode([
        'ts' => gmdate('c'),
        'whisper_to' => $whisperTo,
        'whisper' => $whisper,
        'public' => $public,
    ], JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    file_put_contents(om_notify_path(), $line . "\n", FILE_APPEND | LOCK_EX);
}

/** Atomically read + clear notify.jsonl for the Twitch bot whisper loop. */
function om_poll_notify(): array
{
    $path = om_notify_path();
    $rows = [];
    if (is_readable($path)) {
        $raw = (string) file_get_contents($path);
        // Clear first so a second poll doesn't double-send
        file_put_contents($path, '');
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
    }
    return ['ok' => true, 'notifies' => $rows, 'session' => om_load_session()];
}

function om_player_names_list(array $side): string
{
    $names = [];
    foreach ($side as $p) {
        if (!is_array($p)) {
            continue;
        }
        $names[] = om_display_name($p);
    }
    return implode(' + ', $names);
}

function om_infer_mode(array $match): string
{
    $n = count($match['side_a'] ?? []) + count($match['side_b'] ?? []);
    if ($n <= 2) {
        return '1v1';
    }
    if ($n <= 4) {
        return '2v2';
    }
    if ($n <= 6) {
        return '3v3';
    }
    return 'custom';
}

function om_find_player_location(array $session, string $twitchName): ?array
{
    $key = strtolower(trim($twitchName));
    foreach (['playing', 'on_deck'] as $matchKey) {
        foreach (['side_a', 'side_b'] as $side) {
            foreach ($session[$matchKey][$side] as $i => $p) {
                if (strtolower(trim((string) ($p['twitch_name'] ?? ''))) === $key) {
                    return ['match' => $matchKey, 'side' => $side, 'index' => $i, 'player' => $p];
                }
            }
        }
    }
    foreach (om_list_queue() as $p) {
        if (strtolower(trim((string) ($p['twitch_name'] ?? ''))) === $key) {
            return ['match' => 'waiting', 'side' => null, 'index' => null, 'player' => $p];
        }
    }
    return null;
}

function om_extract_player(array &$session, string $twitchName): ?array
{
    $key = strtolower(trim($twitchName));
    foreach (['playing', 'on_deck'] as $matchKey) {
        foreach (['side_a', 'side_b'] as $side) {
            $kept = [];
            $found = null;
            foreach ($session[$matchKey][$side] as $p) {
                if (strtolower(trim((string) ($p['twitch_name'] ?? ''))) === $key) {
                    $found = $p;
                    continue;
                }
                $kept[] = $p;
            }
            if ($found !== null) {
                $session[$matchKey][$side] = $kept;
                return $found;
            }
        }
    }
    return om_take_from_queue($twitchName);
}

function om_place_player(array &$session, array $player, string $dest): void
{
    // dest: waiting | playing_a | playing_b | ondeck_a | ondeck_b
    if ($dest === 'waiting') {
        om_return_to_queue($player);
        return;
    }
    $map = [
        'playing_a' => ['playing', 'side_a'],
        'playing_b' => ['playing', 'side_b'],
        'ondeck_a' => ['on_deck', 'side_a'],
        'ondeck_b' => ['on_deck', 'side_b'],
    ];
    if (!isset($map[$dest])) {
        om_return_to_queue($player);
        return;
    }
    [$mk, $side] = $map[$dest];
    $session[$mk][$side][] = $player;
    $session[$mk]['mode'] = om_infer_mode($session[$mk]);
    // Never leave staged players sitting in Waiting
    om_scrub_from_queue([(string) ($player['twitch_name'] ?? '')]);
}

/**
 * Select: put player onto On Deck. Prefer filling empty sides for "next match".
 */
function om_select_player(string $twitchName): array
{
    $session = om_load_session();
    $loc = om_find_player_location($session, $twitchName);
    if ($loc === null) {
        return ['ok' => false, 'error' => 'Player not found'];
    }
    $player = om_extract_player($session, $twitchName);
    if ($player === null) {
        return ['ok' => false, 'error' => 'Could not move player'];
    }

    $a = count($session['on_deck']['side_a']);
    $b = count($session['on_deck']['side_b']);
    if ($a === 0 && $b === 0) {
        $dest = 'ondeck_a';
    } elseif ($a <= $b) {
        $dest = 'ondeck_a';
    } else {
        $dest = 'ondeck_b';
    }
    // If one side empty and other has players, fill the empty side first (1v1 style)
    if ($a === 0 && $b > 0) {
        $dest = 'ondeck_a';
    } elseif ($b === 0 && $a > 0) {
        $dest = 'ondeck_b';
    }

    om_place_player($session, $player, $dest);
    om_save_session($session);

    $name = om_display_name($player);
    om_notify(
        (string) $player['twitch_name'],
        "You're on deck for the next Open Matchup ({$name}). Stay ready.",
        ''
    );
    om_log_request('select', ['twitch_name' => $twitchName, 'notes' => $dest]);
    return ['ok' => true, 'dest' => $dest, 'session' => $session];
}

function om_move_player(string $twitchName, string $dest): array
{
    $session = om_load_session();
    if (om_find_player_location($session, $twitchName) === null) {
        return ['ok' => false, 'error' => 'Player not found'];
    }
    $destLabel = [
        'waiting' => 'Waiting',
        'playing_a' => 'Playing A',
        'playing_b' => 'Playing B',
        'ondeck_a' => 'On Deck A',
        'ondeck_b' => 'On Deck B',
    ][$dest] ?? $dest;
    om_push_undo('move', 'Move @' . $twitchName . ' → ' . $destLabel);

    $player = om_extract_player($session, $twitchName);
    if ($player === null) {
        return ['ok' => false, 'error' => 'Player not found'];
    }
    om_place_player($session, $player, $dest);
    om_save_session($session);

    $labels = [
        'waiting' => 'back in the waiting queue',
        'playing_a' => 'in the current match (side A)',
        'playing_b' => 'in the current match (side B)',
        'ondeck_a' => 'on deck (side A)',
        'ondeck_b' => 'on deck (side B)',
    ];
    $label = $labels[$dest] ?? $dest;
    om_notify(
        (string) $player['twitch_name'],
        "Open Matchups update: you are now {$label}.",
        ''
    );
    om_log_request('move', ['twitch_name' => $twitchName, 'notes' => $dest]);
    return ['ok' => true, 'session' => $session];
}

function om_set_match_meta(string $which, array $fields): array
{
    $session = om_load_session();
    if (!in_array($which, ['playing', 'on_deck'], true)) {
        return ['ok' => false, 'error' => 'bad match'];
    }
    $labelWhich = $which === 'playing' ? 'Playing Now' : 'Next Match';
    om_push_undo('meta', 'Edit ' . $labelWhich . ' (scores/mode)');
    if (isset($fields['best_of'])) {
        $session[$which]['best_of'] = max(1, (int) $fields['best_of']);
    }
    if (isset($fields['mode'])) {
        $session[$which]['mode'] = (string) $fields['mode'];
    }
    if (isset($fields['score_a'])) {
        $session[$which]['score_a'] = max(0, (int) $fields['score_a']);
    }
    if (isset($fields['score_b'])) {
        $session[$which]['score_b'] = max(0, (int) $fields['score_b']);
    }
    om_save_session($session);
    return ['ok' => true, 'session' => $session];
}

function om_go_live(): array
{
    $session = om_load_session();
    $m = $session['playing'];
    $aCount = count($m['side_a'] ?? []);
    $bCount = count($m['side_b'] ?? []);

    if (!empty($m['live'])) {
        return ['ok' => false, 'error' => 'Match is already live. Use Undo live if that was a mistake.'];
    }
    if ($aCount < 1 || $bCount < 1) {
        return [
            'ok' => false,
            'error' => 'Need at least 1 player on each side before Mark live (Side A and Side B).',
        ];
    }
    $total = $aCount + $bCount;
    if ($total < 2) {
        return ['ok' => false, 'error' => 'Need at least 2 players to mark a match live.'];
    }

    om_push_undo('go_live', 'Mark live');

    $session['playing']['live'] = true;
    $session['playing']['started_at'] = gmdate('c');
    om_save_session($session);

    // Live players must not remain listed under Waiting
    $liveNames = [];
    foreach (array_merge($session['playing']['side_a'], $session['playing']['side_b']) as $p) {
        if (is_array($p)) {
            $liveNames[] = (string) ($p['twitch_name'] ?? '');
        }
    }
    om_scrub_from_queue($liveNames);

    $a = om_player_names_list($session['playing']['side_a']);
    $b = om_player_names_list($session['playing']['side_b']);
    $bo = (int) ($session['playing']['best_of'] ?? 3);
    $public = "Open Matchup live: {$a} vs {$b} (Bo{$bo}).";
    foreach (array_merge($session['playing']['side_a'], $session['playing']['side_b']) as $p) {
        om_notify((string) $p['twitch_name'], "You're live in an Open Matchup now. Good luck!", '');
    }
    om_notify('', '', $public);
    om_log_request('go_live', ['notes' => $public]);
    return ['ok' => true, 'session' => $session];
}

/** Undo Mark live — keeps players in the current match, stops the live timer. */
function om_undo_live(): array
{
    $session = om_load_session();
    if (empty($session['playing']['live'])) {
        return ['ok' => false, 'error' => 'Current match is not live — nothing to undo.'];
    }
    // Prefer full snapshot restore if Mark live is still the top undo entry
    $info = om_undo_info();
    if ($info && ($info['action'] ?? '') === 'go_live') {
        return om_undo_last();
    }
    $session['playing']['live'] = false;
    $session['playing']['started_at'] = null;
    om_save_session($session);
    om_pop_undo_if_action('go_live');

    foreach (array_merge($session['playing']['side_a'], $session['playing']['side_b']) as $p) {
        om_notify(
            (string) $p['twitch_name'],
            "Heads up: the Open Matchup is no longer marked live. Stand by.",
            ''
        );
    }
    om_log_request('undo_live', ['notes' => 'host undid mark live']);
    return ['ok' => true, 'session' => $session];
}

function om_list_history(int $limit = 20): array
{
    $rows = om_read_csv(om_history_path(), om_history_fields());
    // Newest ended first
    usort($rows, static function ($a, $b) {
        return strcmp((string) ($b['ended_at'] ?? ''), (string) ($a['ended_at'] ?? ''));
    });
    if ($limit > 0) {
        $rows = array_slice($rows, 0, $limit);
    }
    return om_enrich_history($rows);
}

/** Attach thumb/logo URLs for Results UI from archived side_*_json. */
function om_enrich_history(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $sideA = json_decode((string) ($r['side_a_json'] ?? '[]'), true);
        $sideB = json_decode((string) ($r['side_b_json'] ?? '[]'), true);
        if (!is_array($sideA)) {
            $sideA = [];
        }
        if (!is_array($sideB)) {
            $sideB = [];
        }
        $r['side_a_players'] = om_enrich_players($sideA);
        $r['side_b_players'] = om_enrich_players($sideB);
        $out[] = $r;
    }
    return $out;
}

function om_end_match(string $notes = ''): array
{
    $session = om_load_session();
    $m = $session['playing'];
    $aCount = count($m['side_a'] ?? []);
    $bCount = count($m['side_b'] ?? []);
    if ($aCount + $bCount < 1) {
        return ['ok' => false, 'error' => 'No players in current match'];
    }
    if ($aCount < 1 || $bCount < 1) {
        return [
            'ok' => false,
            'error' => 'Both sides need a player before ending (or move extras off and undo).',
        ];
    }

    $aNames = om_player_names_list($m['side_a']);
    $bNames = om_player_names_list($m['side_b']);
    om_push_undo('end_match', 'End match (' . $aNames . ' vs ' . $bNames . ')');

    $scoreA = (int) ($m['score_a'] ?? 0);
    $scoreB = (int) ($m['score_b'] ?? 0);
    $winner = '';
    if ($scoreA > $scoreB) {
        $winner = 'A';
    } elseif ($scoreB > $scoreA) {
        $winner = 'B';
    } else {
        $winner = 'draw';
    }

    $sideATwitch = [];
    $sideBTwitch = [];
    foreach ($m['side_a'] as $p) {
        $t = (string) ($p['twitch_name'] ?? '');
        $sideATwitch[] = $t;
        if ($t !== '') {
            $session['played_counts'][strtolower($t)] = (int) ($session['played_counts'][strtolower($t)] ?? 0) + 1;
        }
    }
    foreach ($m['side_b'] as $p) {
        $t = (string) ($p['twitch_name'] ?? '');
        $sideBTwitch[] = $t;
        if ($t !== '') {
            $session['played_counts'][strtolower($t)] = (int) ($session['played_counts'][strtolower($t)] ?? 0) + 1;
        }
    }

    $hist = om_read_csv(om_history_path(), om_history_fields());
    $hist[] = [
        'ended_at' => gmdate('c'),
        'started_at' => (string) ($m['started_at'] ?? ''),
        'mode' => (string) ($m['mode'] ?? ''),
        'best_of' => (string) ($m['best_of'] ?? '3'),
        'score_a' => (string) $scoreA,
        'score_b' => (string) $scoreB,
        'winner_side' => $winner,
        'side_a_names' => om_player_names_list($m['side_a']),
        'side_b_names' => om_player_names_list($m['side_b']),
        'side_a_twitch' => implode('|', $sideATwitch),
        'side_b_twitch' => implode('|', $sideBTwitch),
        'side_a_json' => json_encode($m['side_a'], JSON_UNESCAPED_UNICODE) ?: '[]',
        'side_b_json' => json_encode($m['side_b'], JSON_UNESCAPED_UNICODE) ?: '[]',
        'notes' => $notes,
    ];
    om_write_csv(om_history_path(), om_history_fields(), $hist);

    $session['games_tonight'] = (int) ($session['games_tonight'] ?? 0) + 1;

    $scoreLine = "{$scoreA}-{$scoreB}";

    // Clear playing only — do NOT auto-requeue (players leave unless host moves them back)
    $finishedPlayers = array_merge($m['side_a'], $m['side_b']);
    $session['playing'] = om_empty_match((int) ($session['best_of_default'] ?? 3));
    om_save_session($session);

    foreach ($finishedPlayers as $p) {
        if (!is_array($p)) {
            continue;
        }
        om_notify(
            (string) ($p['twitch_name'] ?? ''),
            "Your Open Matchup is over ({$scoreLine}). Thanks for playing!",
            ''
        );
    }

    $nextA = om_player_names_list($session['on_deck']['side_a']);
    $nextB = om_player_names_list($session['on_deck']['side_b']);
    $nextLine = '';
    if ($nextA !== '' || $nextB !== '') {
        $nextLine = " Next up: {$nextA} vs {$nextB}.";
    }
    $public = "Open match between {$aNames} and {$bNames} is over ({$scoreLine}).{$nextLine}";

    foreach (array_merge($session['on_deck']['side_a'], $session['on_deck']['side_b']) as $p) {
        om_notify(
            (string) $p['twitch_name'],
            "You're next — on deck for the upcoming Open Matchup. Get ready.",
            ''
        );
    }
    om_notify('', '', $public);

    om_log_request('end_match', ['notes' => $public]);
    return ['ok' => true, 'session' => om_load_session(), 'public' => $public];
}

/**
 * Delete a player from Waiting, Playing, or On Deck (anywhere on the board).
 */
function om_delete_player(string $twitchName): array
{
    $twitchName = trim($twitchName);
    if ($twitchName === '') {
        return ['ok' => false, 'error' => 'Twitch name required'];
    }
    $session = om_load_session();
    $loc = om_find_player_location($session, $twitchName);
    if ($loc === null) {
        return ['ok' => false, 'error' => 'Player not found'];
    }
    om_push_undo('delete_player', 'Delete @' . $twitchName);
    $player = om_extract_player($session, $twitchName);
    // extract already removes from waiting CSV when found there; if only in session, scrub anyway
    om_scrub_from_queue([$twitchName]);
    om_save_session($session);
    om_log_request('delete_player', [
        'twitch_name' => $twitchName,
        'notes' => 'from ' . (string) ($loc['match'] ?? '?'),
    ]);
    if ($player) {
        om_notify(
            $twitchName,
            'You were removed from the Open Matchups board by the host.',
            ''
        );
    }
    return ['ok' => true, 'session' => $session, 'removed_from' => $loc['match']];
}

/**
 * Clear Playing or On Deck without writing history (discard / cancel matchup).
 */
function om_clear_match(string $which): array
{
    $session = om_load_session();
    if (!in_array($which, ['playing', 'on_deck'], true)) {
        return ['ok' => false, 'error' => 'bad match'];
    }
    $players = array_merge(
        $session[$which]['side_a'] ?? [],
        $session[$which]['side_b'] ?? []
    );
    if (!$players && empty($session[$which]['live'])) {
        return ['ok' => false, 'error' => 'That match slot is already empty'];
    }
    $label = $which === 'playing' ? 'Clear Playing Now' : 'Clear Next Match';
    om_push_undo('clear_match', $label);
    $names = [];
    foreach ($players as $p) {
        if (is_array($p)) {
            $names[] = (string) ($p['twitch_name'] ?? '');
        }
    }
    $session[$which] = om_empty_match((int) ($session['best_of_default'] ?? 3));
    om_save_session($session);
    // Do not put them back in waiting — host can re-add if needed
    foreach ($names as $n) {
        if ($n === '') {
            continue;
        }
        om_notify($n, 'Your Open Matchup slot was cleared by the host.', '');
    }
    om_log_request('clear_match', ['notes' => $which . ' cleared']);
    return ['ok' => true, 'session' => $session];
}

/**
 * Delete one finished match from Results history (match on ended_at + side twitch keys).
 */
function om_delete_history(string $endedAt, string $sideATwitch = '', string $sideBTwitch = ''): array
{
    $endedAt = trim($endedAt);
    if ($endedAt === '') {
        return ['ok' => false, 'error' => 'ended_at required'];
    }
    $rows = om_read_csv(om_history_path(), om_history_fields());
    $kept = [];
    $removed = 0;
    $removedRow = null;
    foreach ($rows as $r) {
        $matchEnded = (string) ($r['ended_at'] ?? '') === $endedAt;
        $matchA = $sideATwitch === '' || (string) ($r['side_a_twitch'] ?? '') === $sideATwitch;
        $matchB = $sideBTwitch === '' || (string) ($r['side_b_twitch'] ?? '') === $sideBTwitch;
        if ($matchEnded && $matchA && $matchB && $removed === 0) {
            $removed = 1;
            $removedRow = $r;
            continue;
        }
        $kept[] = $r;
    }
    if ($removed === 0) {
        return ['ok' => false, 'error' => 'History row not found'];
    }
    $names = trim(
        ((string) ($removedRow['side_a_names'] ?? '')) . ' vs ' . ((string) ($removedRow['side_b_names'] ?? ''))
    );
    om_push_undo('delete_history', 'Delete result (' . $names . ')');
    // Re-read after push so snapshot includes the row we're about to delete
    // (push already captured current history with the row — good)
    om_write_csv(om_history_path(), om_history_fields(), $kept);
    om_log_request('delete_history', ['notes' => $endedAt]);
    return ['ok' => true, 'session' => om_load_session()];
}

/**
 * Players archived on a Results row (from side_*_json).
 *
 * @return array{side_a: array, side_b: array}
 */
function om_players_from_history_row(array $row): array
{
    $sideA = json_decode((string) ($row['side_a_json'] ?? '[]'), true);
    $sideB = json_decode((string) ($row['side_b_json'] ?? '[]'), true);
    if (!is_array($sideA)) {
        $sideA = [];
    }
    if (!is_array($sideB)) {
        $sideB = [];
    }
    return ['side_a' => $sideA, 'side_b' => $sideB];
}

/**
 * Find + remove one Results row (same match keys as delete_history).
 *
 * @return array{0: ?array, 1: array} [removedRow|null, remainingRows]
 */
function om_take_history_row(string $endedAt, string $sideATwitch = '', string $sideBTwitch = ''): array
{
    $rows = om_read_csv(om_history_path(), om_history_fields());
    $kept = [];
    $removedRow = null;
    foreach ($rows as $r) {
        $matchEnded = (string) ($r['ended_at'] ?? '') === $endedAt;
        $matchA = $sideATwitch === '' || (string) ($r['side_a_twitch'] ?? '') === $sideATwitch;
        $matchB = $sideBTwitch === '' || (string) ($r['side_b_twitch'] ?? '') === $sideBTwitch;
        if ($matchEnded && $matchA && $matchB && $removedRow === null) {
            $removedRow = $r;
            continue;
        }
        $kept[] = $r;
    }
    return [$removedRow, $kept];
}

/**
 * Restore an ended Results match so the host can re-queue / re-match.
 *
 * dest:
 *  - waiting  → all players back in Waiting (default; host rearranges)
 *  - playing  → restore sides into Playing Now (must be empty / not live)
 *  - on_deck  → restore sides into Next Match (must be empty)
 */
function om_restore_history(
    string $endedAt,
    string $sideATwitch = '',
    string $sideBTwitch = '',
    string $dest = 'waiting'
): array {
    $endedAt = trim($endedAt);
    $dest = strtolower(trim($dest));
    if ($endedAt === '') {
        return ['ok' => false, 'error' => 'ended_at required'];
    }
    if (!in_array($dest, ['waiting', 'playing', 'on_deck'], true)) {
        return ['ok' => false, 'error' => 'dest must be waiting, playing, or on_deck'];
    }

    [$row, $kept] = om_take_history_row($endedAt, $sideATwitch, $sideBTwitch);
    if ($row === null) {
        return ['ok' => false, 'error' => 'History row not found'];
    }

    $sides = om_players_from_history_row($row);
    $allPlayers = array_merge($sides['side_a'], $sides['side_b']);
    if (!$allPlayers) {
        return ['ok' => false, 'error' => 'That result has no player data to restore'];
    }

    $session = om_load_session();
    $labelNames = trim(
        ((string) ($row['side_a_names'] ?? '')) . ' vs ' . ((string) ($row['side_b_names'] ?? ''))
    );
    $destLabel = [
        'waiting' => 'Waiting',
        'playing' => 'Playing Now',
        'on_deck' => 'Next Match',
    ][$dest];

    if ($dest === 'playing') {
        if (!empty($session['playing']['live'])) {
            return ['ok' => false, 'error' => 'Playing is live — Undo live / End first, or restore to Waiting.'];
        }
        if (count($session['playing']['side_a']) + count($session['playing']['side_b']) > 0) {
            return ['ok' => false, 'error' => 'Playing Now is not empty — clear it or restore to Waiting.'];
        }
    }
    if ($dest === 'on_deck') {
        if (count($session['on_deck']['side_a']) + count($session['on_deck']['side_b']) > 0) {
            return ['ok' => false, 'error' => 'Next Match is not empty — clear it or restore to Waiting.'];
        }
    }

    om_push_undo('restore_history', 'Restore result → ' . $destLabel . ' (' . $labelNames . ')');

    // Pull anyone already on the board so we don't duplicate, then place.
    foreach ($allPlayers as $p) {
        if (!is_array($p)) {
            continue;
        }
        $tw = trim((string) ($p['twitch_name'] ?? ''));
        if ($tw === '') {
            continue;
        }
        if (om_find_player_location($session, $tw) !== null) {
            om_extract_player($session, $tw);
        }
        om_scrub_from_queue([$tw]);
    }

    if ($dest === 'waiting') {
        foreach ($allPlayers as $p) {
            if (!is_array($p)) {
                continue;
            }
            $tw = trim((string) ($p['twitch_name'] ?? ''));
            if ($tw === '') {
                continue;
            }
            $p['joined_at'] = gmdate('c');
            $p['status'] = 'waiting';
            om_return_to_queue($p);
            om_notify(
                $tw,
                'Open Matchups: a finished match was undone — you are back in the waiting queue.',
                ''
            );
        }
    } else {
        $bo = max(1, (int) ($row['best_of'] ?? ($session['best_of_default'] ?? 3)));
        $match = om_empty_match($bo);
        $match['mode'] = (string) ($row['mode'] ?? '1v1') ?: '1v1';
        $match['score_a'] = max(0, (int) ($row['score_a'] ?? 0));
        $match['score_b'] = max(0, (int) ($row['score_b'] ?? 0));
        $match['live'] = false;
        $match['started_at'] = null;
        $match['side_a'] = array_values(array_filter($sides['side_a'], 'is_array'));
        $match['side_b'] = array_values(array_filter($sides['side_b'], 'is_array'));
        $session[$dest] = $match;
        foreach ($allPlayers as $p) {
            if (!is_array($p)) {
                continue;
            }
            $tw = trim((string) ($p['twitch_name'] ?? ''));
            if ($tw === '') {
                continue;
            }
            $where = $dest === 'playing' ? 'Playing Now' : 'Next Match';
            om_notify(
                $tw,
                "Open Matchups: a finished match was restored to {$where}. Stand by.",
                ''
            );
        }
    }

    // Soft-correct tonight counters (host undid an "ended" game)
    $session['games_tonight'] = max(0, (int) ($session['games_tonight'] ?? 0) - 1);
    foreach ($allPlayers as $p) {
        if (!is_array($p)) {
            continue;
        }
        $k = strtolower(trim((string) ($p['twitch_name'] ?? '')));
        if ($k === '' || !isset($session['played_counts'][$k])) {
            continue;
        }
        $session['played_counts'][$k] = max(0, (int) $session['played_counts'][$k] - 1);
        if ($session['played_counts'][$k] === 0) {
            unset($session['played_counts'][$k]);
        }
    }

    om_write_csv(om_history_path(), om_history_fields(), $kept);
    om_save_session($session);
    om_log_request('restore_history', [
        'notes' => $dest . ' · ' . $endedAt . ' · ' . $labelNames,
    ]);

    return [
        'ok' => true,
        'session' => $session,
        'restored_to' => $dest,
        'public' => 'Restored ' . $labelNames . ' → ' . $destLabel,
    ];
}

/**
 * Wipe all Results history rows.
 */
function om_clear_history(): array
{
    $rows = om_read_csv(om_history_path(), om_history_fields());
    if ($rows) {
        om_push_undo('clear_history', 'Clear all results (' . count($rows) . ')');
    }
    om_write_csv(om_history_path(), om_history_fields(), []);
    om_log_request('clear_history', ['notes' => 'all results cleared']);
    return ['ok' => true, 'session' => om_load_session()];
}

/** Nightly snapshots under data/open_matchups/archives/YYYY-MM-DD[_HHMMSS][_label] */
function om_archives_dir(): string
{
    $dir = om_data_dir() . '/archives';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Live files that get snapshotted into each archive folder. */
function om_archive_file_names(): array
{
    return [
        'session.json',
        'queue.csv',
        'matches_history.csv',
        'requests.csv',
        'undo_stack.json',
    ];
}

/**
 * Sanitize a human label into a folder-safe slug (must start with a letter).
 * Empty / invalid → ''.
 */
function om_archive_slug(string $label): string
{
    $label = strtolower(trim($label));
    $label = preg_replace('/[^a-z0-9]+/', '-', $label) ?? '';
    $label = trim($label, '-_');
    if ($label === '' || !preg_match('/^[a-z]/', $label)) {
        return '';
    }
    return substr($label, 0, 40);
}

/**
 * Valid archive folder ids:
 *   2026-07-24
 *   2026-07-24_214530
 *   2026-07-24_open-cast
 *   2026-07-24_214530_open-cast
 * Label slug (if present) must start with a letter so it can't collide with HHMMSS.
 */
function om_archive_id_valid(string $id): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(?:_\d{6})?(?:_[a-z][a-z0-9_-]{0,39})?$/', $id);
}

/**
 * Pick a free archive folder id for "today" (America/New_York).
 * Optional label → 2026-07-24_open-cast (or …_HHMMSS_open-cast if taken).
 * No label → 2026-07-24 (or …_HHMMSS if that date already exists).
 */
function om_next_archive_id(?string $when = null, string $slug = ''): string
{
    try {
        $tz = new DateTimeZone('America/New_York');
        $dt = $when ? new DateTimeImmutable($when, $tz) : new DateTimeImmutable('now', $tz);
    } catch (Throwable $e) {
        $dt = new DateTimeImmutable('now');
    }
    $day = $dt->format('Y-m-d');
    $slug = om_archive_slug($slug);
    $root = om_archives_dir();

    if ($slug !== '') {
        $candidate = $day . '_' . $slug;
        if (!is_dir($root . '/' . $candidate)) {
            return $candidate;
        }
        return $day . '_' . $dt->format('His') . '_' . $slug;
    }

    if (!is_dir($root . '/' . $day)) {
        return $day;
    }
    return $day . '_' . $dt->format('His');
}

/**
 * @return list<array{id:string, archived_at:string, games:int, history_rows:int, waiting:int, label:string}>
 */
function om_list_archives(): array
{
    $root = om_archives_dir();
    $out = [];
    foreach (scandir($root) ?: [] as $name) {
        if ($name === '.' || $name === '..' || !om_archive_id_valid($name)) {
            continue;
        }
        $dir = $root . '/' . $name;
        if (!is_dir($dir)) {
            continue;
        }
        $meta = [
            'id' => $name,
            'archived_at' => '',
            'games' => 0,
            'history_rows' => 0,
            'waiting' => 0,
            'label' => $name,
        ];
        $manifestPath = $dir . '/manifest.json';
        if (is_readable($manifestPath)) {
            $raw = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($raw)) {
                $meta['archived_at'] = (string) ($raw['archived_at'] ?? '');
                $meta['games'] = (int) ($raw['games_tonight'] ?? 0);
                $meta['history_rows'] = (int) ($raw['history_rows'] ?? 0);
                $meta['waiting'] = (int) ($raw['waiting_at_end'] ?? 0);
                if (!empty($raw['label'])) {
                    $meta['label'] = (string) $raw['label'];
                }
            }
        }
        if ($meta['archived_at'] === '') {
            $meta['archived_at'] = date('c', (int) (@filemtime($dir) ?: time()));
        }
        if ($meta['history_rows'] === 0 && is_readable($dir . '/matches_history.csv')) {
            $meta['history_rows'] = count(om_read_csv($dir . '/matches_history.csv', om_history_fields()));
        }
        $out[] = $meta;
    }
    usort($out, static function ($a, $b) {
        return strcmp((string) $b['id'], (string) $a['id']);
    });
    return $out;
}

/**
 * Copy current live night into archives/{id}/, then reset live board for next broadcast.
 *
 * @return array{ok:bool, archive_id?:string, archives?:array, session?:array, error?:string, public?:string}
 */
function om_archive_night(?string $preferredId = null, ?string $label = null): array
{
    $session = om_load_session();
    $history = om_read_csv(om_history_path(), om_history_fields());
    $queue = om_read_csv(om_queue_path(), om_queue_fields());
    $games = (int) ($session['games_tonight'] ?? 0);
    $playingOpen = count($session['playing']['side_a'] ?? []) + count($session['playing']['side_b'] ?? []);
    if (!empty($session['playing']['live'])) {
        return ['ok' => false, 'error' => 'Match is live — End match (or Undo live) before archiving the night.'];
    }
    if ($playingOpen > 0) {
        return ['ok' => false, 'error' => 'Playing Now still has players — End match or Clear Playing first.'];
    }
    if ($games < 1 && !$history && !$queue) {
        return ['ok' => false, 'error' => 'Nothing to archive — no games, results, or waiting players.'];
    }

    $humanLabel = trim((string) $label);
    $slug = om_archive_slug($humanLabel);
    $id = trim((string) $preferredId);
    if ($id === '' || !om_archive_id_valid($id)) {
        $id = om_next_archive_id(null, $slug);
    }
    $dest = om_archives_dir() . '/' . $id;
    if (is_dir($dest)) {
        // Same label twice in one second — bump with time.
        $id = om_next_archive_id(null, $slug !== '' ? $slug : 'archive');
        $dest = om_archives_dir() . '/' . $id;
        if (is_dir($dest)) {
            return ['ok' => false, 'error' => 'Archive already exists: ' . $id];
        }
    }
    if (!@mkdir($dest, 0775, true) && !is_dir($dest)) {
        return ['ok' => false, 'error' => 'Could not create archive folder.'];
    }

    $live = om_data_dir();
    foreach (om_archive_file_names() as $file) {
        $src = $live . '/' . $file;
        if (is_readable($src)) {
            if (!@copy($src, $dest . '/' . $file)) {
                return ['ok' => false, 'error' => 'Failed copying ' . $file];
            }
        }
    }

    $display = $humanLabel !== '' ? $humanLabel : $id;
    $manifest = [
        'id' => $id,
        'label' => $display,
        'slug' => $slug,
        'archived_at' => date('c'),
        'games_tonight' => $games,
        'history_rows' => count($history),
        'waiting_at_end' => count($queue),
    ];
    file_put_contents(
        $dest . '/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    // Fresh live night
    om_write_csv(om_queue_path(), om_queue_fields(), []);
    om_write_csv(om_history_path(), om_history_fields(), []);
    om_save_session(om_default_session());
    @file_put_contents(om_undo_path(), "[]\n");

    om_log_request('archive_night', [
        'notes' => $id . ' · label=' . $display . ' · games=' . $games . ' · results=' . count($history),
    ]);

    return [
        'ok' => true,
        'archive_id' => $id,
        'archives' => om_list_archives(),
        'session' => om_load_session(),
        'public' => 'Archived as ' . $id . ($humanLabel !== '' ? ' (“' . $humanLabel . '”)' : '') . ' · board reset',
    ];
}

/**
 * Replace live board with a dated archive snapshot.
 *
 * @return array{ok:bool, archive_id?:string, archives?:array, session?:array, error?:string, public?:string}
 */
function om_load_archive(string $id): array
{
    $id = trim($id);
    if (!om_archive_id_valid($id)) {
        return ['ok' => false, 'error' => 'Invalid archive id.'];
    }
    $srcDir = om_archives_dir() . '/' . $id;
    if (!is_dir($srcDir)) {
        return ['ok' => false, 'error' => 'Archive not found: ' . $id];
    }

    $live = om_data_dir();
    foreach (om_archive_file_names() as $file) {
        $src = $srcDir . '/' . $file;
        $dst = $live . '/' . $file;
        if (is_readable($src)) {
            if (!@copy($src, $dst)) {
                return ['ok' => false, 'error' => 'Failed restoring ' . $file];
            }
        } elseif ($file === 'session.json') {
            om_save_session(om_default_session());
        } elseif ($file === 'queue.csv') {
            om_write_csv(om_queue_path(), om_queue_fields(), []);
        } elseif ($file === 'matches_history.csv') {
            om_write_csv(om_history_path(), om_history_fields(), []);
        } elseif ($file === 'undo_stack.json') {
            @file_put_contents(om_undo_path(), "[]\n");
        }
    }

    $session = om_load_session();
    $session['loaded_archive'] = $id;
    om_save_session($session);

    om_log_request('load_archive', ['notes' => $id]);

    return [
        'ok' => true,
        'archive_id' => $id,
        'archives' => om_list_archives(),
        'session' => $session,
        'public' => 'Loaded archive ' . $id,
    ];
}

function om_promote_ondeck_to_playing(): array
{
    $session = om_load_session();
    if (!empty($session['playing']['live'])) {
        return ['ok' => false, 'error' => 'Current match is live — Undo live or End match first.'];
    }
    if (count($session['playing']['side_a']) + count($session['playing']['side_b']) > 0) {
        return ['ok' => false, 'error' => 'Current match still has players — end it first.'];
    }
    $a = count($session['on_deck']['side_a'] ?? []);
    $b = count($session['on_deck']['side_b'] ?? []);
    if ($a < 1 || $b < 1) {
        return [
            'ok' => false,
            'error' => 'On Deck needs at least 1 player on each side before pulling to Playing.',
        ];
    }
    om_push_undo('promote', 'On Deck → Playing');
    $session['playing'] = $session['on_deck'];
    $session['playing']['live'] = false;
    $session['playing']['started_at'] = null;
    $session['playing']['score_a'] = 0;
    $session['playing']['score_b'] = 0;
    $session['on_deck'] = om_empty_match((int) ($session['best_of_default'] ?? 3));
    om_save_session($session);

    $names = [];
    foreach (array_merge($session['playing']['side_a'], $session['playing']['side_b']) as $p) {
        if (is_array($p)) {
            $names[] = (string) ($p['twitch_name'] ?? '');
        }
    }
    om_scrub_from_queue($names);

    $a = om_player_names_list($session['playing']['side_a']);
    $b = om_player_names_list($session['playing']['side_b']);
    $public = "Next Open Matchup ready: {$a} vs {$b}. Waiting for host to mark live.";
    foreach (array_merge($session['playing']['side_a'], $session['playing']['side_b']) as $p) {
        if (!is_array($p)) {
            continue;
        }
        om_notify(
            (string) ($p['twitch_name'] ?? ''),
            "You're up — pulled into the current Open Matchup slot. Stand by for live.",
            ''
        );
    }
    om_notify('', '', $public);

    om_log_request('promote_ondeck', ['notes' => $public]);
    return ['ok' => true, 'session' => $session];
}

function om_attention_items(array $queue, array $session): array
{
    $items = [];
    if (!$queue) {
        return $items;
    }
    $longest = null;
    $longestMins = -1;
    foreach ($queue as $p) {
        $mins = om_wait_minutes((string) ($p['joined_at'] ?? ''));
        if ($mins > $longestMins) {
            $longestMins = $mins;
            $longest = $p;
        }
    }
    if ($longest) {
        $items[] = [
            'name' => om_display_name($longest),
            'reason' => 'Longest wait · no game yet',
            'wait' => om_wait_label((string) ($longest['joined_at'] ?? '')),
            'wait_class' => 'wait-long',
        ];
    }
    foreach ($queue as $p) {
        $isGuest = in_array((string) ($p['is_guest'] ?? ''), ['1', 'true', 'True'], true);
        if (!$isGuest) {
            continue;
        }
        $tw = strtolower((string) ($p['twitch_name'] ?? ''));
        $played = (int) ($session['played_counts'][$tw] ?? 0);
        if ($played > 0) {
            continue;
        }
        $items[] = [
            'name' => om_display_name($p) . ' ★',
            'reason' => 'New guest · first FSL appearance',
            'wait' => om_wait_label((string) ($p['joined_at'] ?? '')),
            'wait_class' => om_wait_class(om_wait_label((string) ($p['joined_at'] ?? ''))),
        ];
        if (count($items) >= 4) {
            break;
        }
    }
    return $items;
}
