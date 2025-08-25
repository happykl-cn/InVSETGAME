<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

$pdo = Database::connection();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$data = json_input();

if ($path === '/api/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => '用户名与密码必填']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);
        $userId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO assets (user_id, cash, total_value) VALUES (?, 100000.00, 100000.00)')->execute([$userId]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => '注册失败或用户已存在']);
    }
    exit;
}

if ($path === '/api/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => '用户名或密码错误']);
        exit;
    }

    $token = generate_token();
    $expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?,?,?)')->execute([$user['id'], $token, $expires]);
    echo json_encode(['token' => $token]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


