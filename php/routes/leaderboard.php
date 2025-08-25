<?php
require_once __DIR__ . '/../database.php';

$pdo = Database::connection();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/api/leaderboard' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT u.username, l.total_value, l.updated_at FROM leaderboard l JOIN users u ON u.id = l.user_id ORDER BY l.total_value DESC LIMIT 100');
    echo json_encode(['items' => $stmt->fetchAll()]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
?>


