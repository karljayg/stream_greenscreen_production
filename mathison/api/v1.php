<?php
/**
 * Mathison external API v1 (token auth, read-only)
 *
 * Base:  /mathison/api/v1.php
 * Auth:  Authorization: Bearer <api_token>
 *        or X-Api-Token / ?token=
 *
 * See README.md in this folder for the full guide.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/v1_handlers.php';

mathison_api_json_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    mathison_api_error('Only GET is supported', 405);
}

mathison_api_require_token();

try {
    $pdo = mathison_pdo();
    $resource = trim((string) ($_GET['resource'] ?? $_GET['action'] ?? 'health'));
    // Allow slash form: player/games → player.games
    $resource = str_replace('/', '.', $resource);

    switch ($resource) {
        case 'health':
            mathison_api_ok(v1_health($pdo));
            break;

        case 'players':
            mathison_api_ok(v1_players_search(
                $pdo,
                (string) ($_GET['q'] ?? ''),
                mathison_api_clamp_int($_GET['limit'] ?? 20, 20, 1, 50)
            ));
            break;

        case 'player':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            mathison_api_ok(v1_player($pdo, $name));
            break;

        case 'player.last':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            mathison_api_ok(v1_player_last($pdo, $name));
            break;

        case 'player.games':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            $vs = trim((string) ($_GET['vs'] ?? ''));
            mathison_api_ok(v1_player_games(
                $pdo,
                $name,
                mathison_api_clamp_int($_GET['limit'] ?? 25, 25, 1, 100),
                $vs !== '' ? $vs : null
            ));
            break;

        case 'player.strategies':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            mathison_api_ok(v1_player_strategies(
                $pdo,
                $name,
                mathison_api_parse_workers($_GET['startWorkers'] ?? '')
            ));
            break;

        case 'player.likely':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            mathison_api_ok(v1_player_likely(
                $pdo,
                $name,
                mathison_api_parse_workers($_GET['startWorkers'] ?? '')
            ));
            break;

        case 'player.timings':
            $name = trim((string) ($_GET['name'] ?? ''));
            if ($name === '') {
                mathison_api_error('Missing name', 400);
            }
            mathison_api_ok(v1_player_timings($pdo, $name));
            break;

        case 'matchup':
            $a = trim((string) ($_GET['a'] ?? $_GET['playerA'] ?? ''));
            $b = trim((string) ($_GET['b'] ?? $_GET['playerB'] ?? ''));
            if ($a === '' || $b === '') {
                mathison_api_error('Missing a and b player names', 400);
            }
            mathison_api_ok(v1_matchup(
                $pdo,
                $a,
                $b,
                mathison_api_clamp_int($_GET['limit'] ?? 20, 20, 1, 100)
            ));
            break;

        case 'replay':
            mathison_api_ok(v1_replay(
                $pdo,
                (int) ($_GET['id'] ?? 0),
                isset($_GET['summary']) && ($_GET['summary'] === '1' || $_GET['summary'] === 'true')
            ));
            break;

        default:
            mathison_api_error('Unknown resource. See mathison/api/README.md', 404);
    }
} catch (Throwable $e) {
    mathison_api_error($e->getMessage(), 500);
}
