<?php
// 基础配置
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'stock_simulator',
        'user' => getenv('DB_USER') ?: 'stock_simulator',
        'pass' => getenv('DB_PASS') ?: 'xEJmPrxdxW8PdPMs',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'debug' => (bool)(getenv('APP_DEBUG') ?: true),
        'jwt_secret' => getenv('JWT_SECRET') ?: 'CHANGE_ME_TO_A_RANDOM_SECRET',
        'cors_origin' => getenv('CORS_ORIGIN') ?: '*',
    ],
];
?>


