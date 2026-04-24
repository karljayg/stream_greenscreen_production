<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

function resolve_media_root(string $rootKey): array {
    $rootKey = strtolower(trim($rootKey));
    if ($rootKey === '2026') {
        $dir = realpath(__DIR__ . '/2026');
        if ($dir === false) {
            return ['ok' => false, 'error' => '2026 folder missing'];
        }
        return ['ok' => true, 'prefix' => '2026', 'dir' => $dir];
    }
    if ($rootKey === 'production_files' || $rootKey === 'pf' || $rootKey === 'prod') {
        $rootKey = 'production_files';
    }
    $dir = realpath(__DIR__ . '/production_files');
    if ($dir === false) {
        return ['ok' => false, 'error' => 'production_files folder missing'];
    }
    return ['ok' => true, 'prefix' => 'production_files', 'dir' => $dir];
}

function is_path_safe(string $path): bool {
    return strpos($path, '..') === false && strpos($path, "\0") === false;
}

function rel_from_base(string $base, string $full): string {
    $base = rtrim(str_replace('\\', '/', $base), '/');
    $full = str_replace('\\', '/', $full);
    if (strpos($full, $base . '/') === 0) {
        return ltrim(substr($full, strlen($base)), '/');
    }
    return '';
}

function as_project_relative(string $rel, string $prefix): string {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') {
        return $prefix;
    }
    return $prefix . '/' . $rel;
}

function collect_folders(string $baseDir): array {
    $skip = ['.git', '.idea', '.vscode', 'node_modules', 'vendor', 'terminals', 'agent-transcripts', 'mcps'];
    $folders = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $name = $item->getFilename();
        if ($item->isDir()) {
            if ($name !== '' && $name[0] === '.') continue;
            if (in_array($name, $skip, true)) {
                $it->next();
                continue;
            }
            $rel = rel_from_base($baseDir, $item->getPathname());
            if ($rel !== '') $folders[] = $rel;
        }
    }
    sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
    return $folders;
}

function collect_scene_media_files(string $scanDir, string $baseDir, string $prefix): array {
    $allowed = ['mp4', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    $skip = ['.git', '.idea', '.vscode', 'node_modules', 'vendor', 'terminals', 'agent-transcripts', 'mcps'];
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $name = $item->getFilename();
        if ($item->isDir()) {
            if ($name !== '' && $name[0] === '.') continue;
            if (in_array($name, $skip, true)) {
                $it->next();
                continue;
            }
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;
        $rel = rel_from_base($baseDir, $item->getPathname());
        if ($rel !== '') $files[] = as_project_relative($rel, $prefix);
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rootKey = isset($_GET['root']) ? (string)$_GET['root'] : 'production_files';
    $root = resolve_media_root($rootKey);
    if (!$root['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $root['error']]);
        exit;
    }
    $baseDir = $root['dir'];
    $prefix = $root['prefix'];

    $folderRaw = isset($_GET['folder']) ? trim((string)$_GET['folder']) : '';
    $folderRaw = str_replace('\\', '/', $folderRaw);
    $folderRaw = trim($folderRaw, '/');
    if (!is_path_safe($folderRaw)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid folder path']);
        exit;
    }

    $scanDir = $baseDir;
    if ($folderRaw !== '') {
        $candidate = realpath($baseDir . DIRECTORY_SEPARATOR . $folderRaw);
        if ($candidate === false || strpos(str_replace('\\', '/', $candidate), str_replace('\\', '/', $baseDir) . '/') !== 0 || !is_dir($candidate)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Folder not found']);
            exit;
        }
        $scanDir = $candidate;
    }

    echo json_encode([
        'ok' => true,
        'root' => $prefix,
        'folders' => collect_folders($baseDir),
        'files' => collect_scene_media_files($scanDir, $baseDir, $prefix)
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['video_file']['error'] ?? -1;
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload error code ' . $code]);
    exit;
}

$rootKeyPost = isset($_POST['media_root']) ? (string)$_POST['media_root'] : 'production_files';
$rootPost = resolve_media_root($rootKeyPost);
if (!$rootPost['ok']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $rootPost['error']]);
    exit;
}
$baseDir = $rootPost['dir'];
$prefixPost = $rootPost['prefix'];

$targetFolder = isset($_POST['target_folder']) ? trim((string)$_POST['target_folder']) : '';
$targetFolder = str_replace('\\', '/', $targetFolder);
$targetFolder = trim($targetFolder, '/');
if (!is_path_safe($targetFolder)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid target folder']);
    exit;
}

$targetDir = $baseDir;
if ($targetFolder !== '') {
    $candidate = realpath($baseDir . DIRECTORY_SEPARATOR . $targetFolder);
    if ($candidate === false || strpos(str_replace('\\', '/', $candidate), str_replace('\\', '/', $baseDir) . '/') !== 0 || !is_dir($candidate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Target folder not found']);
        exit;
    }
    $targetDir = $candidate;
}

$origName = basename((string)$_FILES['video_file']['name']);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedUpload = ['mp4', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
if (!in_array($ext, $allowedUpload, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Allowed types: mp4, png, jpg, jpeg, webp, gif']);
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9 .\-_]/', '', $origName);
$safeName = trim((string)$safeName);
if ($safeName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid filename']);
    exit;
}

$dest = $targetDir . DIRECTORY_SEPARATOR . $safeName;
if (file_exists($dest)) {
    $base = pathinfo($safeName, PATHINFO_FILENAME);
    $xext = pathinfo($safeName, PATHINFO_EXTENSION);
    $i = 1;
    while (file_exists($targetDir . DIRECTORY_SEPARATOR . $base . '_' . $i . '.' . $xext)) $i++;
    $safeName = $base . '_' . $i . '.' . $xext;
    $dest = $targetDir . DIRECTORY_SEPARATOR . $safeName;
}

if (!move_uploaded_file($_FILES['video_file']['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save uploaded file']);
    exit;
}

echo json_encode([
    'ok' => true,
    'filename' => $safeName,
    'path' => as_project_relative(rel_from_base($baseDir, $dest), $prefixPost)
], JSON_UNESCAPED_SLASHES);
