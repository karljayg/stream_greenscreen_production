<?php
/**
 * CLI only: fetch FSL rankings from psistorm and write to data/rankings.json.
 * Called by rankings.php when in-request fetch fails (e.g. Apache PHP has no cURL).
 */
$dir = __DIR__ . '/data';
$url = 'https://psistorm.com/fsl/rankings/rankings.json';

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$remote = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $remote = curl_exec($ch);
    curl_close($ch);
}
if ($remote === false || $remote === '') {
    exit(1);
}
$data = json_decode($remote, true);
if (!is_array($data)) {
    exit(1);
}
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$written = @file_put_contents($dir . '/rankings.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
exit($written === false ? 1 : 0);
