<?php
function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function auth_user(): ?array {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    if (!$auth) {
        // 兼容部分 Nginx/PHP-FPM 环境未自动注入的情况
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    }
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT user_id, token, expires_at FROM auth_tokens WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row) {
            $userStmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
            $userStmt->execute([$row['user_id']]);
            $user = $userStmt->fetch();
            return $user ?: null;
        }
    }
    return null;
}

function require_auth(): array {
    $user = auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $user;
}

function generate_token(): string {
    return bin2hex(random_bytes(32));
}
?>


