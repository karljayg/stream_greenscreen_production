<?php
/**
 * Isolated Open Matchups flow tests (temp data dir).
 * Run: php test_om_flow.php > test_om_flow_out.txt 2>&1
 */
require_once __DIR__ . '/queue_lib.php';

$tmp = sys_get_temp_dir() . '/om_test_' . bin2hex(random_bytes(4));
@mkdir($tmp, 0775, true);
om_set_data_dir($tmp);

$failed = 0;
function assert_true(bool $cond, string $msg): void
{
    global $failed;
    if ($cond) {
        echo "PASS  $msg\n";
    } else {
        echo "FAIL  $msg\n";
        $failed++;
    }
}

function waiting_names(): array
{
    return array_map(
        static fn($p) => strtolower((string) ($p['twitch_name'] ?? '')),
        om_list_waiting()
    );
}

function playing_names(): array
{
    $s = om_load_session();
    $out = [];
    foreach (array_merge($s['playing']['side_a'] ?? [], $s['playing']['side_b'] ?? []) as $p) {
        $out[] = strtolower((string) ($p['twitch_name'] ?? ''));
    }
    return $out;
}

function seed_player(string $twitch, string $fsl = ''): void
{
    $r = om_add_player_manual([
        'twitch_name' => $twitch,
        'fsl_name' => $fsl !== '' ? $fsl : $twitch,
        'race' => 'Zerg',
        'match_mode' => '1v1',
        'is_guest' => '0',
    ]);
    assert_true(!empty($r['ok']), "seed $twitch");
}

echo "=== data dir: $tmp ===\n";

// Fresh session
om_save_session([
    'best_of_default' => 3,
    'games_tonight' => 0,
    'playing' => om_empty_match(3),
    'on_deck' => om_empty_match(3),
    'played_counts' => [],
]);
om_write_csv(om_queue_path(), om_queue_fields(), []);
om_write_csv(om_history_path(), om_history_fields(), []);

seed_player('alice', 'AliceFSL');
seed_player('bob', 'BobFSL');
seed_player('carol', 'CarolFSL');
assert_true(count(waiting_names()) === 3, '3 waiting after seed');

// Move to playing
$r = om_move_player('alice', 'playing_a');
assert_true(!empty($r['ok']), 'move alice → playing_a');
$r = om_move_player('bob', 'playing_b');
assert_true(!empty($r['ok']), 'move bob → playing_b');

$w = waiting_names();
assert_true(!in_array('alice', $w, true) && !in_array('bob', $w, true), 'alice/bob gone from waiting after move');
assert_true(in_array('carol', $w, true), 'carol still waiting');
$p = playing_names();
assert_true(in_array('alice', $p, true) && in_array('bob', $p, true), 'alice/bob in playing');

// Mark live also scrubs
$r = om_go_live();
assert_true(!empty($r['ok']), 'go live');
assert_true(!empty(om_load_session()['playing']['live']), 'session live flag');
assert_true(!in_array('alice', waiting_names(), true), 'alice not in waiting while live');

// End match must NOT requeue
$r = om_set_match_meta('playing', ['score_a' => 2, 'score_b' => 1]);
assert_true(!empty($r['ok']), 'set scores');
$r = om_end_match();
assert_true(!empty($r['ok']), 'end match');
$s = om_load_session();
assert_true(empty($s['playing']['side_a']) && empty($s['playing']['side_b']), 'playing cleared after end');
assert_true(empty($s['playing']['live']), 'not live after end');
$w = waiting_names();
assert_true(!in_array('alice', $w, true) && !in_array('bob', $w, true), 'alice/bob NOT requeued after end');
assert_true(in_array('carol', $w, true), 'carol still waiting after end');
assert_true(count(om_list_history(10)) === 1, 'one history row');
assert_true((int) $s['games_tonight'] === 1, 'games_tonight = 1');

// Delete player from waiting
$r = om_delete_player('carol');
assert_true(!empty($r['ok']), 'delete carol from waiting');
assert_true(!in_array('carol', waiting_names(), true), 'carol gone');

// Delete from playing / on deck
seed_player('dave');
seed_player('erin');
om_move_player('dave', 'ondeck_a');
om_move_player('erin', 'ondeck_b');
assert_true(!in_array('dave', waiting_names(), true), 'dave not in waiting on deck');
$r = om_delete_player('dave');
assert_true(!empty($r['ok']), 'delete dave from on deck');
$s = om_load_session();
$deckNames = array_map(
    static fn($p) => strtolower((string) ($p['twitch_name'] ?? '')),
    array_merge($s['on_deck']['side_a'], $s['on_deck']['side_b'])
);
assert_true(!in_array('dave', $deckNames, true), 'dave removed from on deck');
assert_true(in_array('erin', $deckNames, true), 'erin still on deck');

// Clear match slot
$r = om_clear_match('on_deck');
assert_true(!empty($r['ok']), 'clear on_deck');
$s = om_load_session();
assert_true(
    count($s['on_deck']['side_a']) + count($s['on_deck']['side_b']) === 0,
    'on_deck empty after clear'
);
assert_true(!in_array('erin', waiting_names(), true), 'erin not dumped into waiting after clear');

// Rebuild a match and delete history
seed_player('frank');
seed_player('gina');
om_move_player('frank', 'playing_a');
om_move_player('gina', 'playing_b');
om_go_live();
om_set_match_meta('playing', ['score_a' => 0, 'score_b' => 2]);
om_end_match();
$hist = om_list_history(5);
assert_true(count($hist) >= 1, 'history has rows before delete');
$row = $hist[0];
$r = om_delete_history(
    (string) $row['ended_at'],
    (string) $row['side_a_twitch'],
    (string) $row['side_b_twitch']
);
assert_true(!empty($r['ok']), 'delete history row');
$still = false;
foreach (om_list_history(20) as $h) {
    if (($h['ended_at'] ?? '') === ($row['ended_at'] ?? '')
        && ($h['side_a_twitch'] ?? '') === ($row['side_a_twitch'] ?? '')) {
        $still = true;
    }
}
assert_true(!$still, 'deleted history row gone');

// Clear all history
seed_player('hank');
seed_player('ivy');
om_move_player('hank', 'playing_a');
om_move_player('ivy', 'playing_b');
om_go_live();
om_end_match();
assert_true(count(om_list_history(20)) >= 1, 'history before clear_all');
$r = om_clear_history();
assert_true(!empty($r['ok']), 'clear_history');
assert_true(count(om_list_history(20)) === 0, 'history empty after clear_all');

// Busy filter: if CSV still has a player who is also in playing, waiting list hides them
seed_player('jack');
om_move_player('jack', 'playing_a');
// Force-duplicate into CSV (simulate stale row)
om_return_to_queue([
    'twitch_name' => 'jack',
    'fsl_name' => 'Jack',
    'joined_at' => gmdate('c'),
    'status' => 'waiting',
    'match_mode' => '1v1',
]);
assert_true(!in_array('jack', waiting_names(), true), 'stale CSV jack hidden while in playing');
om_scrub_from_queue(['jack']);
om_go_live(); // need two sides — skip, just clear
om_clear_match('playing');

// --- Undo stack ---
om_save_undo_stack([]);
seed_player('undo_a');
seed_player('undo_b');
om_move_player('undo_a', 'playing_a');
om_move_player('undo_b', 'playing_b');
om_set_match_meta('playing', ['score_a' => 2, 'score_b' => 0]);
om_go_live();
assert_true(!empty(om_load_session()['playing']['live']), 'live before end');
$histBeforeEnd = count(om_list_history(50));
$r = om_end_match();
assert_true(!empty($r['ok']), 'end match for undo test');
assert_true(count(om_list_history(50)) === $histBeforeEnd + 1, 'history grew after end');
assert_true(empty(playing_names()), 'playing empty after end');
$info = om_undo_info();
assert_true($info && ($info['action'] ?? '') === 'end_match', 'undo top is end_match');
$r = om_undo_last();
assert_true(!empty($r['ok']), 'undo end_match');
$s = om_load_session();
assert_true(!empty($s['playing']['live']), 'live restored after undo end');
$pn = playing_names();
assert_true(in_array('undo_a', $pn, true) && in_array('undo_b', $pn, true), 'players restored to playing');
assert_true((int) ($s['playing']['score_a'] ?? 0) === 2, 'score restored');
assert_true(count(om_list_history(50)) === $histBeforeEnd, 'history rolled back after undo end');

// Undo promote
om_undo_live(); // stop live so we can end cleanly or clear
om_clear_match('playing');
seed_player('prom_a');
seed_player('prom_b');
om_move_player('prom_a', 'ondeck_a');
om_move_player('prom_b', 'ondeck_b');
$r = om_promote_ondeck_to_playing();
assert_true(!empty($r['ok']), 'promote for undo');
assert_true(in_array('prom_a', playing_names(), true), 'promoted to playing');
$r = om_undo_last();
assert_true(!empty($r['ok']), 'undo promote');
$s = om_load_session();
$deck = array_map(
    static fn($p) => strtolower((string) ($p['twitch_name'] ?? '')),
    array_merge($s['on_deck']['side_a'], $s['on_deck']['side_b'])
);
assert_true(in_array('prom_a', $deck, true) && in_array('prom_b', $deck, true), 'on deck restored after undo promote');
assert_true(empty(playing_names()), 'playing empty after undo promote');

// Undo clear queue
seed_player('q1');
seed_player('q2');
assert_true(count(waiting_names()) >= 2, 'waiting before clear');
om_clear_queue();
assert_true(count(waiting_names()) === 0, 'waiting cleared');
$r = om_undo_last();
assert_true(!empty($r['ok']), 'undo clear_queue');
assert_true(in_array('q1', waiting_names(), true) && in_array('q2', waiting_names(), true), 'queue restored');

// Restore ended match from Results → Waiting
om_save_undo_stack([]);
om_write_csv(om_history_path(), om_history_fields(), []);
om_clear_queue();
om_save_session(array_merge(om_default_session(), [
    'games_tonight' => 0,
    'playing' => om_empty_match(3),
    'on_deck' => om_empty_match(3),
    'played_counts' => [],
]));
seed_player('rest_a');
seed_player('rest_b');
om_move_player('rest_a', 'playing_a');
om_move_player('rest_b', 'playing_b');
om_set_match_meta('playing', ['score_a' => 1, 'score_b' => 2]);
om_end_match();
assert_true(empty(playing_names()), 'playing empty after end (restore test)');
assert_true(!in_array('rest_a', waiting_names(), true), 'rest_a not waiting after end');
$hist = om_list_history(5);
assert_true(count($hist) >= 1, 'history has row to restore');
$row = $hist[0];
$r = om_restore_history(
    (string) $row['ended_at'],
    (string) $row['side_a_twitch'],
    (string) $row['side_b_twitch'],
    'waiting'
);
assert_true(!empty($r['ok']), 'restore_history → waiting');
assert_true(in_array('rest_a', waiting_names(), true) && in_array('rest_b', waiting_names(), true), 'players back in waiting');
assert_true(count(om_list_history(5)) === 0, 'history row removed after restore');

// Restore → Playing
seed_player('rp_a');
seed_player('rp_b');
om_move_player('rp_a', 'playing_a');
om_move_player('rp_b', 'playing_b');
om_end_match();
$hist = om_list_history(5);
$row = $hist[0];
$r = om_restore_history(
    (string) $row['ended_at'],
    (string) $row['side_a_twitch'],
    (string) $row['side_b_twitch'],
    'playing'
);
assert_true(!empty($r['ok']), 'restore_history → playing');
$pn = playing_names();
assert_true(in_array('rp_a', $pn, true) && in_array('rp_b', $pn, true), 'players restored to playing');
assert_true(empty(om_load_session()['playing']['live']), 'restored playing is not live');

echo "\n=== SUMMARY: " . ($failed === 0 ? 'ALL PASS' : "$failed FAILED") . " ===\n";

// cleanup
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

exit($failed === 0 ? 0 : 1);
