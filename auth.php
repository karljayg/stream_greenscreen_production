<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$usersDir = __DIR__ . '/data/users';

function getUserFile($username) {
    global $usersDir;
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    if ($safe === '') return null;
    return $usersDir . '/' . $safe . '.json';
}

function loadUser($username) {
    $file = getUserFile($username);
    if (!$file || !is_file($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing fields']);
        exit;
    }
    $user = loadUser($username);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid username or password']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['username'] = $user['username'];
    echo json_encode(['ok' => true, 'username' => $user['username']]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'change_password') {
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not logged in']);
        exit;
    }
    $current = $_POST['current_password'] ?? '';
    $newPw   = $_POST['new_password'] ?? '';
    if (strlen($newPw) < 2) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'New password too short']);
        exit;
    }
    $user = loadUser($_SESSION['username']);
    if (!$user || !password_verify($current, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Current password incorrect']);
        exit;
    }
    $user['password_hash'] = password_hash($newPw, PASSWORD_DEFAULT);
    $file = getUserFile($_SESSION['username']);
    file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'check') {
    if (!empty($_SESSION['username'])) {
        echo json_encode(['ok' => true, 'username' => $_SESSION['username']]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
