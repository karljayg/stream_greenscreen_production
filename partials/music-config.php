<?php
// Music config loader.
// Requires: $_SESSION['username'] (run after auth-gate.php).
// Defines:  $safeUser, $moodSongs, $sceneMoodMap, $musicFiles.

$safeUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_SESSION['username'] ?? '');
$_mcDataDir  = __DIR__ . '/../data/';
$_mcMusicDir = __DIR__ . '/../music/';

// mood→songs: per-user override takes priority over global default
$moodSongsFile     = $_mcDataDir . 'mood_songs.json';
$moodSongsUserFile = $_mcDataDir . 'mood_songs_' . $safeUser . '.json';
if ($safeUser && file_exists($moodSongsUserFile)) $moodSongsFile = $moodSongsUserFile;
$moodSongs = [];
if (file_exists($moodSongsFile)) {
    $raw = @file_get_contents($moodSongsFile);
    if ($raw) $moodSongs = json_decode($raw, true) ?: [];
}

// scene→moods: per-user override takes priority over global default
$sceneMoodMapFile     = $_mcDataDir . 'scene_mood_map.json';
$sceneMoodMapUserFile = $_mcDataDir . 'scene_mood_map_' . $safeUser . '.json';
if ($safeUser && file_exists($sceneMoodMapUserFile)) $sceneMoodMapFile = $sceneMoodMapUserFile;
$sceneMoodMap = [];
if (file_exists($sceneMoodMapFile)) {
    $raw = @file_get_contents($sceneMoodMapFile);
    if ($raw) $sceneMoodMap = json_decode($raw, true) ?: [];
}

// list of audio files present in music/ (for the admin UI dropdowns)
$musicFiles = [];
if (is_dir($_mcMusicDir)) {
    foreach (scandir($_mcMusicDir) ?: [] as $mf) {
        if (preg_match('/\.(mp3|wav|ogg|flac|m4a)$/i', $mf) && is_file($_mcMusicDir . $mf)) {
            $musicFiles[] = $mf;
        }
    }
    sort($musicFiles);
}

unset($_mcDataDir, $_mcMusicDir);
