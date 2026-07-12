<?php
/**
 * PDO connection to the mathison database.
 * Table names are resolved from information_schema so case-sensitive
 * Linux MySQL (Replays / Players) and local Docker (replays / players) both work.
 */
function mathison_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/config.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        (int) $cfg['port'],
        $cfg['dbname'],
        $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/**
 * Return a backtick-quoted table identifier matching the live schema case.
 * Pass logical names like "Replays" or "Players" (case of the argument does not matter).
 */
function mathison_table(string $logicalName): string
{
    static $cache = [];
    $key = strtolower($logicalName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $pdo = mathison_pdo();
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND LOWER(TABLE_NAME) = LOWER(?)
         LIMIT 1'
    );
    $stmt->execute([$logicalName]);
    $real = $stmt->fetchColumn();
    if (!$real) {
        // Fall back to the preferred server casing so errors stay readable.
        $fallback = [
            'replays' => 'Replays',
            'players' => 'Players',
            'core_values' => 'CORE_VALUES',
            'goals' => 'GOALS',
            'learningstats' => 'LearningStats',
            'major_traits' => 'MAJOR_TRAITS',
            'memory' => 'MEMORY',
            'moods' => 'MOODS',
            'mood_type' => 'MOOD_TYPE',
            'motivations' => 'MOTIVATIONS',
            'personality' => 'PERSONALITY',
            'patternlearning' => 'PatternLearning',
            'playercomments' => 'PlayerComments',
            'user' => 'USER',
        ];
        $real = $fallback[$key] ?? $logicalName;
    }

    $cache[$key] = '`' . str_replace('`', '``', $real) . '`';
    return $cache[$key];
}
