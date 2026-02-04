<?php

declare(strict_types=1);

/**
 * Append a line to the project log file (config['log']).
 * Call after config is loaded. All app logging goes to logs/ folder.
 */
function app_log(string $message, array $config = null): void
{
    if ($config === null) {
        $config = require __DIR__ . '/config.php'; // already loaded by caller in most cases
    }
    $logDir = $config['log']['dir'] ?? (dirname(__DIR__) . '/logs');
    $logFile = $config['log']['file'] ?? 'app.log';
    $path = $logDir . '/' . $logFile;

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}
