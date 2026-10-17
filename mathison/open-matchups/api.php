<?php
/**
 * Open Matchups JSON API — public like /status (no Bearer token).
 * Localhost Mathison proxies here so it shares the live server's data/open_matchups.
 */
require_once __DIR__ . '/queue_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Localhost Mathison → forward to live server API (shared bot data)
if (om_use_remote_data()) {
    om_proxy_remote_api_and_exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $snap = om_board_snapshot();
    echo json_encode($snap, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    $body = $_POST;
}
$action = (string) ($body['action'] ?? '');

$result = ['ok' => false, 'error' => 'Unknown action'];

try {
    switch ($action) {
        case 'select':
            $result = om_select_player((string) ($body['twitch_name'] ?? ''));
            break;
        case 'move':
            $result = om_move_player(
                (string) ($body['twitch_name'] ?? ''),
                (string) ($body['dest'] ?? '')
            );
            break;
        case 'meta':
            $result = om_set_match_meta((string) ($body['which'] ?? ''), $body);
            break;
        case 'go_live':
            $result = om_go_live();
            break;
        case 'undo_live':
            $result = om_undo_live();
            break;
        case 'undo':
            $result = om_undo_last();
            break;
        case 'end_match':
            $result = om_end_match((string) ($body['notes'] ?? ''));
            break;
        case 'promote':
            $result = om_promote_ondeck_to_playing();
            break;
        case 'clear_queue':
            $n = om_clear_queue();
            $result = ['ok' => true, 'cleared' => $n, 'session' => om_load_session()];
            break;
        case 'remove_waiting':
        case 'delete_player':
            $result = om_delete_player((string) ($body['twitch_name'] ?? ''));
            break;
        case 'clear_match':
            $result = om_clear_match((string) ($body['which'] ?? ''));
            break;
        case 'delete_history':
            $result = om_delete_history(
                (string) ($body['ended_at'] ?? ''),
                (string) ($body['side_a_twitch'] ?? ''),
                (string) ($body['side_b_twitch'] ?? '')
            );
            break;
        case 'restore_history':
            $result = om_restore_history(
                (string) ($body['ended_at'] ?? ''),
                (string) ($body['side_a_twitch'] ?? ''),
                (string) ($body['side_b_twitch'] ?? ''),
                (string) ($body['dest'] ?? 'waiting')
            );
            break;
        case 'clear_history':
            $result = om_clear_history();
            break;
        case 'archive_night':
            $result = om_archive_night(
                isset($body['archive_id']) ? (string) $body['archive_id'] : null,
                isset($body['label']) ? (string) $body['label'] : null
            );
            break;
        case 'list_archives':
            $result = ['ok' => true, 'archives' => om_list_archives(), 'session' => om_load_session()];
            break;
        case 'load_archive':
            $result = om_load_archive((string) ($body['archive_id'] ?? ''));
            break;
        case 'add_player':
            $result = om_add_player_manual($body);
            break;
        case 'update_player':
            $result = om_update_player($body);
            break;
        case 'poll_notify':
            // Bot (local) drains server notify.jsonl — same role as reading the file locally
            $result = om_poll_notify();
            break;
        default:
            $result = ['ok' => false, 'error' => 'Unknown action'];
    }
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}

if (!empty($result['ok'])) {
    $session = om_enrich_session($result['session'] ?? om_load_session());
    $queue = om_enrich_players(om_list_waiting($session));
    $result['queue'] = $queue;
    $result['session'] = $session;
    $result['attention'] = om_attention_items($queue, $session);
    $result['history'] = om_list_history(50);
    $result['undo'] = om_undo_info();
    $result['archives'] = $result['archives'] ?? om_list_archives();
    $result['fsl_base'] = om_fsl_web_base();
}
$result['data_source'] = 'local';
$result['data_dir'] = om_data_dir();

echo json_encode($result, JSON_UNESCAPED_UNICODE);
