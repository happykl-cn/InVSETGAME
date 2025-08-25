<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

$pdo = Database::connection();
$user = require_auth();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$data = json_input();

if ($path === '/api/transaction' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $symbol = strtoupper(trim($data['symbol'] ?? ''));
    $side = strtoupper(trim($data['side'] ?? ''));
    $qty = (int)($data['quantity'] ?? 0);
    if (!$symbol || !in_array($side, ['BUY','SELL']) || $qty <= 0) {
        http_response_code(400); echo json_encode(['error' => '参数无效']); exit;
    }

    $priceRow = $pdo->prepare('SELECT price FROM stock_prices WHERE symbol = ?');
    $priceRow->execute([$symbol]);
    $price = (float)($priceRow->fetch()['price'] ?? 0);
    if ($price <= 0) { http_response_code(400); echo json_encode(['error' => '无价格数据']); exit; }

    try {
        $pdo->beginTransaction();

        if ($side === 'BUY') {
            $cost = $price * $qty;
            $cashRow = $pdo->prepare('SELECT cash FROM assets WHERE user_id = ? FOR UPDATE');
            $cashRow->execute([$user['id']]);
            $cash = (float)($cashRow->fetch()['cash'] ?? 0);
            if ($cash < $cost) { throw new Exception('余额不足'); }

            // 资金与持仓
            $pdo->prepare('UPDATE assets SET cash = cash - ?, total_value = total_value WHERE user_id = ?')
                ->execute([$cost, $user['id']]);

            $pos = $pdo->prepare('SELECT id, quantity, avg_price FROM positions WHERE user_id = ? AND symbol = ? FOR UPDATE');
            $pos->execute([$user['id'], $symbol]);
            if ($row = $pos->fetch()) {
                $newQty = (int)$row['quantity'] + $qty;
                $newAvg = (($row['quantity'] * (float)$row['avg_price']) + $cost) / $newQty;
                $pdo->prepare('UPDATE positions SET quantity = ?, avg_price = ? WHERE id = ?')
                    ->execute([$newQty, $newAvg, $row['id']]);
            } else {
                $pdo->prepare('INSERT INTO positions (user_id, symbol, quantity, avg_price) VALUES (?,?,?,?)')
                    ->execute([$user['id'], $symbol, $qty, $price]);
            }
        } else { // SELL
            $pos = $pdo->prepare('SELECT id, quantity, avg_price FROM positions WHERE user_id = ? AND symbol = ? FOR UPDATE');
            $pos->execute([$user['id'], $symbol]);
            $row = $pos->fetch();
            if (!$row || (int)$row['quantity'] < $qty) { throw new Exception('持仓不足'); }
            $revenue = $price * $qty;
            $newQty = (int)$row['quantity'] - $qty;
            if ($newQty > 0) {
                $pdo->prepare('UPDATE positions SET quantity = ? WHERE id = ?')->execute([$newQty, $row['id']]);
            } else {
                $pdo->prepare('DELETE FROM positions WHERE id = ?')->execute([$row['id']]);
            }
            $pdo->prepare('UPDATE assets SET cash = cash + ? WHERE user_id = ?')->execute([$revenue, $user['id']]);
        }

        // 记录交易
        $pdo->prepare('INSERT INTO trades (user_id, symbol, side, price, quantity) VALUES (?,?,?,?,?)')
            ->execute([$user['id'], $symbol, $side, $price, $qty]);

        // 更新总资产 = 现金 + Σ(持仓数量 * 最新价)
        $sumStmt = $pdo->prepare('SELECT SUM(quantity * ?) AS pos_value FROM positions WHERE user_id = ?');
        $sumStmt->execute([$price, $user['id']]);
        $posValue = (float)($sumStmt->fetch()['pos_value'] ?? 0);
        $cashStmt = $pdo->prepare('SELECT cash FROM assets WHERE user_id = ?');
        $cashStmt->execute([$user['id']]);
        $cash = (float)($cashStmt->fetch()['cash'] ?? 0);
        $total = $cash + $posValue;
        $pdo->prepare('UPDATE assets SET total_value = ? WHERE user_id = ?')->execute([$total, $user['id']]);
        $pdo->prepare('INSERT INTO leaderboard (user_id, total_value) VALUES (?,?) ON DUPLICATE KEY UPDATE total_value = VALUES(total_value)')
            ->execute([$user['id'], $total]);

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


