<?php
// 简易路由入口
header('Content-Type: application/json; charset=utf-8');

// CORS
$config = require __DIR__ . '/config.php';
header('Access-Control-Allow-Origin: ' . $config['app']['cors_origin']);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/utils.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 将 /api/* 分发到对应控制器
try {
    if (str_starts_with($uri, '/api/register') && $method === 'POST') {
        require_once __DIR__ . '/routes/user.php';
        exit;
    }
    if (str_starts_with($uri, '/api/login') && $method === 'POST') {
        require_once __DIR__ . '/routes/user.php';
        exit;
    }
    if (str_starts_with($uri, '/api/assets') || str_starts_with($uri, '/api/deposit') || str_starts_with($uri, '/api/withdraw')) {
        require_once __DIR__ . '/routes/assets.php';
        exit;
    }
    if (str_starts_with($uri, '/api/transaction')) {
        require_once __DIR__ . '/routes/trade.php';
        exit;
    }
    if (str_starts_with($uri, '/api/positions') || str_starts_with($uri, '/api/trades')) {
        require_once __DIR__ . '/routes/trade_query.php';
        exit;
    }
    if (str_starts_with($uri, '/api/stock_data') || str_starts_with($uri, '/api/kline')) {
        require_once __DIR__ . '/routes/stock.php';
        exit;
    }
    if (str_starts_with($uri, '/api/leaderboard')) {
        require_once __DIR__ . '/routes/leaderboard.php';
        exit;
    }
    if (str_starts_with($uri, '/api/p2p')) {
        require_once __DIR__ . '/routes/p2p.php';
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'path' => $uri]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Error', 'message' => $e->getMessage()]);
}
?>


