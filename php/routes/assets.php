<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

$pdo = Database::connection();
$user = require_auth();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$data = json_input();

if ($path === '/api/assets' && $method === 'GET') {
    $stmt = $pdo->prepare('SELECT cash, total_value FROM assets WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $assets = $stmt->fetch() ?: ['cash' => 0, 'total_value' => 0];
    echo json_encode($assets);
    exit;
}

if ($path === '/api/deposit' && $method === 'POST') {
    $amount = (float)($data['amount'] ?? 0);
    if ($amount <= 0) { http_response_code(400); echo json_encode(['error' => '金额无效']); exit; }
    $pdo->prepare('UPDATE assets SET cash = cash + ?, total_value = total_value + ? WHERE user_id = ?')
        ->execute([$amount, $amount, $user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($path === '/api/withdraw' && $method === 'POST') {
    $amount = (float)($data['amount'] ?? 0);
    $stmt = $pdo->prepare('SELECT cash FROM assets WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $cash = (float)($stmt->fetch()['cash'] ?? 0);
    if ($amount <= 0 || $amount > $cash) { http_response_code(400); echo json_encode(['error' => '余额不足或金额无效']); exit; }
    $pdo->prepare('UPDATE assets SET cash = cash - ?, total_value = total_value - ? WHERE user_id = ?')
        ->execute([$amount, $amount, $user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


