<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

$pdo = Database::connection();
$user = require_auth();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/api/positions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT symbol, quantity, avg_price FROM positions WHERE user_id = ? ORDER BY symbol');
    $stmt->execute([$user['id']]);
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

if ($path === '/api/trades' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT symbol, side, price, quantity, created_at FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 200');
    $stmt->execute([$user['id']]);
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


