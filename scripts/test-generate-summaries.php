#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test script: run summary generation on the last N meetings (by id).
 * Useful to verify the worker logic on recent data without waiting for cron.
 *
 * Usage: php scripts/test-generate-summaries.php [--limit=N]
 * Default limit: 3. Example: php scripts/test-generate-summaries.php --limit=5
 */

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

$projectRoot = dirname(__DIR__);
$config = require $projectRoot . '/config/config.php';
require_once $projectRoot . '/config/db.php';
require_once $projectRoot . '/config/logger.php';
require_once $projectRoot . '/src/SummaryWorker.php';

$limit = 3;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
        break;
    }
}

if (($config['openai']['api_key'] ?? '') === '') {
    echo "OPENAI_API_KEY is not set. Exiting.\n";
    exit(1);
}
if (!($config['use_ai_for_minutes_summary'] ?? false)) {
    echo "USE_AI_FOR_MINUTES_SUMMARY is off. Exiting.\n";
    exit(0);
}

$log = function (string $message) use ($config): void {
    app_log('[test-generate-summaries] ' . $message, $config);
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
};

try {
    $pdo = get_db($config);
} catch (Throwable $e) {
    $log('DB connection failed: ' . $e->getMessage());
    exit(1);
}

$stmt = $pdo->prepare(
    'SELECT id, document_type, file_path, document_url, pasted_text FROM meetings ORDER BY id DESC LIMIT :limit'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    $log('No meetings in table.');
    exit(0);
}

$log('Testing last ' . count($rows) . ' meeting(s) (limit=' . $limit . ').');
$processed = 0;
$updateStmt = $pdo->prepare('UPDATE meetings SET minutes_md = :minutes_md, ai_summary = :ai_summary WHERE id = :id');
foreach ($rows as $row) {
    $id = (int) $row['id'];
    $result = process_meeting($id, $row, $config, $log);
    if ($result === null) {
        continue;
    }
    $updateStmt->execute([
        'id' => $result->id,
        'minutes_md' => $result->minutes_md,
        'ai_summary' => $result->ai_summary,
    ]);
    $log("Meeting {$result->id}: ai_summary and minutes_md updated");
    $processed++;
}
$log('Done. Updated ' . $processed . ' of ' . count($rows) . ' meeting(s).');
exit(0);
