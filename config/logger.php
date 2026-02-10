<?php

declare(strict_types=1);

/**
 * Append a line to the project log file (config['log']).
 * Call after config is loaded. All app logging goes to logs/ folder.
 */
function app_log(string $message, ?array $config = null): void
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

/**
 * Append a structured debug line to logs/ai_thinking.log for AI-related flows.
 * This is intended for temporary introspection and can be tailed while testing.
 */
function ai_thinking_log(string $message, ?array $context = null, ?array $config = null): void
{
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $logDir = $config['log']['dir'] ?? (dirname(__DIR__) . '/logs');
    $path = $logDir . '/ai_thinking.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message;
    if ($context !== null) {
        // Keep context small and JSON-encode for readability.
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}
