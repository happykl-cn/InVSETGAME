<?php
require_once __DIR__ . '/../database.php';

$pdo = Database::connection();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/api/stock_data' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT sp.symbol, sp.price, sp.change_pct, sp.updated_at, s.name FROM stock_prices sp LEFT JOIN stocks s ON s.symbol = sp.symbol ORDER BY sp.symbol LIMIT 500');
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

if ($path === '/api/kline' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));
    $limit = max(10, min(365, intval($_GET['limit'] ?? 120)));
    if (!$symbol) { http_response_code(400); echo json_encode(['error' => 'symbol required']); exit; }
    $stmt = $pdo->prepare('SELECT ts, `open`, high, low, `close`, volume FROM stock_ohlc WHERE symbol = ? ORDER BY ts DESC LIMIT ?');
    $stmt->bindValue(1, $symbol);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    // 逆序返回（时间从旧到新）
    $rows = array_reverse($rows);
    echo json_encode(['items' => $rows]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


