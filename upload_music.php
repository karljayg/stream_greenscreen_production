<?php
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$musicDir    = __DIR__ . '/music/';
$allowedExt  = ['mp3', 'wav', 'ogg', 'flac', 'm4a'];

// GET ?list=1  — return list of audio files
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['list'])) {
    $files = [];
    foreach (scandir($musicDir) ?: [] as $f) {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt) && is_file($musicDir . $f)) {
            $files[] = $f;
        }
    }
    sort($files);
    echo json_encode(['files' => $files]);
    exit;
}

// POST — upload a new audio file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['audio_file']['error'] ?? -1;
    http_response_code(400);
    echo json_encode(['error' => 'Upload error code ' . $code]);
    exit;
}

$origName = basename($_FILES['audio_file']['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed: ' . $ext]);
    exit;
}

// Sanitize: allow letters, digits, spaces, dots, hyphens, underscores
$safeName = preg_replace('/[^a-zA-Z0-9 .\-_]/', '', $origName);
$safeName = trim($safeName);
if (!$safeName) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename after sanitization']);
    exit;
}

// Avoid overwriting — append _1, _2 … if file already exists
$dest = $musicDir . $safeName;
if (file_exists($dest)) {
    $base  = pathinfo($safeName, PATHINFO_FILENAME);
    $xext  = pathinfo($safeName, PATHINFO_EXTENSION);
    $i = 1;
    while (file_exists($musicDir . $base . '_' . $i . '.' . $xext)) $i++;
    $safeName = $base . '_' . $i . '.' . $xext;
    $dest     = $musicDir . $safeName;
}

if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dest)) {
    echo json_encode(['ok' => true, 'filename' => $safeName]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file to music/']);
}
