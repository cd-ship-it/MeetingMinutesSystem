<?php

declare(strict_types=1);

/**
 * Return PDO instance. Call after config is loaded.
 * Set config['db']['socket'] to use a Unix socket instead of host/port (e.g. MAMP).
 */
function get_db(array $config): PDO
{
    $host = $config['db']['host'] ?? 'localhost';
    $port = (int) ($config['db']['port'] ?? 3306);
    $name = $config['db']['name'] ?? 'crossp11_db1';
    $user = $config['db']['user'] ?? 'root';
    $password = $config['db']['password'] ?? 'root';
    $socket = $config['db']['socket'] ?? null;

    if (!empty($socket)) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $name);
        $attempted = "unix_socket={$socket}";
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $attempted = "{$host}:{$port}";
    }

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        $hint = ' Check that MySQL is running and that DB_HOST/DB_PORT (or DB_SOCKET) in .env match your server.';
        if (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'Operation not permitted') !== false) {
            $hint .= ' If using MAMP/socket, set DB_SOCKET=/path/to/mysql.sock in .env.';
        }
        throw new PDOException('DB connection failed: ' . $e->getMessage() . ' (tried ' . $attempted . ').' . $hint, (int) $e->getCode(), $e);
    }

    return $pdo;
}
