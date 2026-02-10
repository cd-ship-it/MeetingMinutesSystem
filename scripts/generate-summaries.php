#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Periodic AI summary worker (CLI).
 * Selects meetings (by default only where ai_summary IS NULL), populates minutes_md
 * from file/url/paste, calls OpenAI (model and prompt from .env), updates ai_summary and minutes_md.
 *
 * Usage: php scripts/generate-summaries.php [--limit=N|all] [--force-ai-refresh]
 *   --limit=N           Max meetings per run (default: 10).
 *   --limit=all or --all  Process all matching records (no limit).
 *   --force-ai-refresh  Process meetings even when ai_summary is set (overwrite).
 *   --help              Show this help and exit.
 */

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

$projectRoot = dirname(__DIR__);

$help = <<<'HELP'
generate-summaries.php - AI summary worker for meeting minutes

Usage:
  php generate-summaries.php [OPTIONS]

Options:
  --limit=N           Max meetings to process per run (default: 10).
  --limit=all, --all  Process all matching records (no limit).
  --force-ai-refresh  Process meetings even when ai_summary is set (overwrite).
  --help              Show this help and exit.

Default: only processes rows where ai_summary IS NULL, limit 10 per run.
Use --all (or --limit=all) with or without --force-ai-refresh to process all matching records.

HELP;

foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo $help;
        exit(0);
    }
}

$config = require $projectRoot . '/config/config.php';
require_once $projectRoot . '/config/db.php';
require_once $projectRoot . '/config/logger.php';
require_once $projectRoot . '/src/SummaryWorker.php';

$limit = 10;
$forceRefresh = false;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
    }
    if ($arg === '--limit=all' || $arg === '--all') {
        $limit = 0; // 0 = no limit
    }
    if ($arg === '--force-ai-refresh') {
        $forceRefresh = true;
    }
}

if (($config['openai']['api_key'] ?? '') === '') {
    echo "OPENAI_API_KEY is not set. Exiting.\n";
    exit(0);
}
if (!($config['use_ai_for_minutes_summary'] ?? false)) {
    echo "USE_AI_FOR_MINUTES_SUMMARY is off. Exiting.\n";
    exit(0);
}

$log = function (string $message) use ($config): void {
    app_log('[generate-summaries] ' . $message, $config);
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
};

try {
    $pdo = get_db($config);
} catch (Throwable $e) {
    $log('DB connection failed: ' . $e->getMessage());
    exit(1);
}

$baseSql = 'SELECT id, document_type, file_path, document_url, pasted_text FROM meetings WHERE ';
$baseSql .= $forceRefresh
    ? "document_type IN ('file', 'url', 'paste')"
    : "ai_summary IS NULL AND document_type IN ('file', 'url', 'paste')";
$baseSql .= ' ORDER BY id DESC';
if ($limit > 0) {
    $baseSql .= ' LIMIT :limit';
}
$stmt = $pdo->prepare($baseSql);
if ($limit > 0) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    $log($forceRefresh ? 'No meetings to process (document_type file/url/paste).' : 'No meetings to process (ai_summary IS NULL).');
    exit(0);
}

if ($forceRefresh) {
    $log('Force refresh: processing ' . count($rows) . ' meeting(s) (may overwrite existing ai_summary).');
}

$log('Processing ' . count($rows) . ' meeting(s).');
$processed = 0;
foreach ($rows as $row) {
    $id = (int) $row['id'];
    if (process_meeting($id, $row, $pdo, $config, $log)) {
        $processed++;
    }
}
$log('Done. Updated ' . $processed . ' of ' . count($rows) . ' meeting(s).');
exit(0);
