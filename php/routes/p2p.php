<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

$pdo = Database::connection();
$user = require_auth();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$data = json_input();

if ($path === '/api/p2p/create' && $method === 'POST') {
    $to = (int)($data['to_user_id'] ?? 0);
    $symbol = strtoupper(trim($data['symbol'] ?? ''));
    $qty = (int)($data['quantity'] ?? 0);
    if ($to <= 0 || !$symbol || $qty <= 0) { http_response_code(400); echo json_encode(['error' => '参数无效']); exit; }

    $pos = $pdo->prepare('SELECT id, quantity FROM positions WHERE user_id = ? AND symbol = ?');
    $pos->execute([$user['id'], $symbol]);
    $row = $pos->fetch();
    if (!$row || (int)$row['quantity'] < $qty) { http_response_code(400); echo json_encode(['error' => '持仓不足']); exit; }

    $pdo->prepare('INSERT INTO p2p_transfers (from_user, to_user, symbol, quantity) VALUES (?,?,?,?)')
        ->execute([$user['id'], $to, $symbol, $qty]);
    echo json_encode(['success' => true]);
    exit;
}

if ($path === '/api/p2p/accept' && $method === 'POST') {
    $id = (int)($data['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM p2p_transfers WHERE id = ? AND to_user = ? AND status = "PENDING" FOR UPDATE');
    try {
        $pdo->beginTransaction();
        $stmt->execute([$id, $user['id']]);
        $t = $stmt->fetch();
        if (!$t) { throw new Exception('请求不存在或已处理'); }

        // 扣减转出方持仓
        $posFrom = $pdo->prepare('SELECT id, quantity FROM positions WHERE user_id = ? AND symbol = ? FOR UPDATE');
        $posFrom->execute([$t['from_user'], $t['symbol']]);
        $pf = $posFrom->fetch();
        if (!$pf || (int)$pf['quantity'] < (int)$t['quantity']) { throw new Exception('对方持仓不足'); }
        $newQtyFrom = (int)$pf['quantity'] - (int)$t['quantity'];
        if ($newQtyFrom > 0) {
            $pdo->prepare('UPDATE positions SET quantity = ? WHERE id = ?')->execute([$newQtyFrom, $pf['id']]);
        } else {
            $pdo->prepare('DELETE FROM positions WHERE id = ?')->execute([$pf['id']]);
        }

        // 增加接收方持仓（均价以现价计入简单处理）
        $priceRow = $pdo->prepare('SELECT price FROM stock_prices WHERE symbol = ?');
        $priceRow->execute([$t['symbol']]);
        $price = (float)($priceRow->fetch()['price'] ?? 0);

        $posTo = $pdo->prepare('SELECT id, quantity, avg_price FROM positions WHERE user_id = ? AND symbol = ? FOR UPDATE');
        $posTo->execute([$user['id'], $t['symbol']]);
        if ($pt = $posTo->fetch()) {
            $newQtyTo = (int)$pt['quantity'] + (int)$t['quantity'];
            $newAvg = ($pt['quantity'] * (float)$pt['avg_price'] + (int)$t['quantity'] * $price) / $newQtyTo;
            $pdo->prepare('UPDATE positions SET quantity = ?, avg_price = ? WHERE id = ?')->execute([$newQtyTo, $newAvg, $pt['id']]);
        } else {
            $pdo->prepare('INSERT INTO positions (user_id, symbol, quantity, avg_price) VALUES (?,?,?,?)')
                ->execute([$user['id'], $t['symbol'], $t['quantity'], $price]);
        }

        $pdo->prepare('UPDATE p2p_transfers SET status = "COMPLETED" WHERE id = ?')->execute([$t['id']]);
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


