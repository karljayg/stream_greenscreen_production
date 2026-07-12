<?php
/**
 * Shared bootstrap for token-authenticated Mathison JSON APIs.
 * Session/login gate is NOT used — callers send an API token.
 */
ini_set('display_errors', '0');

require_once __DIR__ . '/../db.php';

function mathison_api_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
    }
    return $cfg;
}

function mathison_api_json_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function mathison_api_ok($data): void
{
    $json = json_encode(['ok' => true, 'data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        mathison_api_error('JSON encode failed: ' . json_last_error_msg(), 500);
    }
    echo $json;
    exit;
}

function mathison_api_error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Require a configured API token via:
 *   Authorization: Bearer <token>
 *   X-Api-Token: <token>
 *   ?token=<token>   (convenient for quick curls; prefer header in production)
 */
function mathison_api_require_token(): void
{
    $expected = (string) (mathison_api_config()['api_token'] ?? '');
    if ($expected === '') {
        mathison_api_error('API token not configured (set api_token in mathison/config.local.php)', 503);
    }

    $got = mathison_api_extract_token();
    if ($got === '' || !hash_equals($expected, $got)) {
        mathison_api_error('Unauthorized', 401);
    }
}

function mathison_api_extract_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $header = $v;
                break;
            }
        }
    }
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
        return $m[1];
    }

    $x = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($x !== '') {
        return trim((string) $x);
    }

    return trim((string) ($_GET['token'] ?? ''));
}

function mathison_api_self_accounts(): array
{
    static $accounts = null;
    if ($accounts === null) {
        $rules = require __DIR__ . '/../insights/rules.php';
        $accounts = array_values($rules['self_accounts'] ?? ['KJ']);
    }
    return $accounts;
}

function mathison_api_resolve_player(PDO $pdo, string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $P = mathison_table('Players');
    $stmt = $pdo->prepare("SELECT Id, SC2_UserId FROM $P WHERE SC2_UserId = ? LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return ['id' => (int) $row['Id'], 'name' => $row['SC2_UserId']];
    }
    $stmt = $pdo->prepare("SELECT Id, SC2_UserId FROM $P WHERE LOWER(SC2_UserId) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return ['id' => (int) $row['Id'], 'name' => $row['SC2_UserId']];
    }
    return null;
}

function mathison_api_norm_race(?string $race): ?string
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

function mathison_api_clamp_int($raw, int $default, int $min, int $max): int
{
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        return $default;
    }
    $n = (int) $raw;
    return max($min, min($max, $n));
}

function mathison_api_parse_workers($raw): ?int
{
    $raw = trim((string) $raw);
    if ($raw === '' || !ctype_digit($raw)) {
        return null;
    }
    $n = (int) $raw;
    return in_array($n, [6, 8, 12], true) ? $n : null;
}
