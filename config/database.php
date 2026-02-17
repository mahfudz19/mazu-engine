<?php

return [
  'default' => 'mysql',
  'connections' => [
    'mysql' => [
      'driver' => 'mysql',
      'host' => env('DB_HOST', '127.0.0.1'),
      'port' => env('DB_PORT', '8889'),
      'database' => env('DB_NAME', 'campus_agenda'),
      'username' => env('DB_USER', 'root'),
      'password' => env('DB_PASS', 'root'),
      'unix_socket' => env('DB_SOCKET', $isDev ? '/Applications/MAMP/tmp/mysql/mysql.sock' : null),
      'charset' => 'utf8mb4',
      'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_TIMEOUT => 5,
      ],
    ],

    'redis_default' => [
      'driver' => 'redis',
      'host' => env('REDIS_HOST', '127.0.0.1'),
      'password' => env('REDIS_PASSWORD', null),
      'port' => env('REDIS_PORT', 6379),
      'database' => 0,
    ],

    'redis_queue' => [
      'driver' => 'redis',
      'host' => env('REDIS_HOST', '127.0.0.1'),
      'password' => env('REDIS_PASSWORD', null),
      'port' => env('REDIS_PORT', 6379),
      'database' => 1,
    ],
  ],
];
