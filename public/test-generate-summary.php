<?php

declare(strict_types=1);

/**
 * Test script for generate-summary flow.
 * Uses the file "meeting minutes.docx" in the project root.
 *
 * Run from browser: http://localhost:8888/test-generate-summary.php
 * Or CLI: php public/test-generate-summary.php
 */

$isCli = (php_sapi_name() === 'cli');

function out(string $s, bool $isCli): void {
    if ($isCli) {
        echo $s . "\n";
    } else {
        echo '<pre>' . htmlspecialchars($s) . '</pre>' . "\n";
    }
}

function outJson($data, bool $isCli): void {
    out(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $isCli);
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Test Generate Summary</title></head><body>\n";
}

$projectRoot = dirname(__DIR__);
$testFile = $projectRoot . '/meeting minutes.docx';

if (!is_file($testFile)) {
    out("ERROR: Test file not found: " . $testFile, $isCli);
    out("Expected: meeting minutes.docx in project root.", $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}

out("Test file: " . $testFile . " (" . filesize($testFile) . " bytes)", $isCli);
out("", $isCli);

$config = require $projectRoot . '/config/config.php';
$apiKey = $config['openai']['api_key'] ?? '';
if ($apiKey === '') {
    out("ERROR: OPENAI_API_KEY not set in .env", $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
out("API key: " . substr($apiKey, 0, 12) . "...", $isCli);
out("", $isCli);

// Reuse the same openaiRequest logic as generate-summary.php
function openaiRequest(string $apiKey, string $method, string $url, ?string $body = null, ?string $contentType = 'application/json', ?string $filePath = null, ?string $uploadFilename = null, bool $assistantsV2 = false): array {
    $headers = [
        'Authorization: Bearer ' . $apiKey,
    ];
    if ($assistantsV2) {
        $headers[] = 'OpenAI-Beta: assistants=v2';
    }
    if ($filePath === null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 120,
            'ignore_errors' => true,
        ],
    ];
    if ($body !== null && $filePath === null) {
        $opts['http']['content'] = $body;
    }
    if ($filePath !== null) {
        $boundary = '----' . bin2hex(random_bytes(8));
        $opts['http']['header'] = implode("\r\n", [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
        ]);
        $name = $uploadFilename !== null ? $uploadFilename : basename($filePath);
        $content = file_get_contents($filePath);
        $opts['http']['content'] = "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"purpose\"\r\n\r\nassistants\r\n"
            . "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"" . $name . "\"\r\n"
            . "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document\r\n\r\n"
            . $content . "\r\n"
            . "--$boundary--\r\n";
    }
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if ($response === false) {
        return ['error' => error_get_last()['message'] ?? 'Request failed', 'code' => $code, 'body' => null];
    }
    $decoded = json_decode($response, true);
    return ['body' => $decoded !== null ? $decoded : $response, 'code' => $code];
}

// Step 1: Upload file
out("=== Step 1: Upload file to OpenAI ===", $isCli);
$upload = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/files', null, null, $testFile, 'meeting minutes.docx');
out("HTTP code: " . ($upload['code'] ?? 'N/A'), $isCli);
if (isset($upload['error'])) {
    out("ERROR: " . $upload['error'], $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
outJson($upload['body'], $isCli);
$fileId = $upload['body']['id'] ?? null;
if (!$fileId) {
    out("ERROR: No file id in response. Check API key and file format.", $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
out("File ID: " . $fileId, $isCli);
out("", $isCli);

// Step 2: Create vector store (Assistants API v2)
out("=== Step 2: Create vector store (v2) ===", $isCli);
$vectorStoreBody = ['file_ids' => [$fileId], 'name' => 'Meeting minutes'];
out("Request body:", $isCli);
outJson($vectorStoreBody, $isCli);
$vectorStore = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/vector_stores', json_encode($vectorStoreBody), 'application/json', null, null, true);
out("HTTP code: " . ($vectorStore['code'] ?? 'N/A'), $isCli);
out("Response:", $isCli);
outJson($vectorStore['body'], $isCli);
if (isset($vectorStore['error']) || !is_array($vectorStore['body'] ?? null) || empty($vectorStore['body']['id'])) {
    $err = $vectorStore['body']['error']['message'] ?? 'Unknown error';
    out("ERROR: " . $err, $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
$vectorStoreId = $vectorStore['body']['id'];
out("Vector store ID: " . $vectorStoreId . " (waiting for completed...)", $isCli);
$vsStatus = $vectorStore['body']['status'] ?? 'in_progress';
for ($i = 0; $i < 30 && $vsStatus === 'in_progress'; $i++) {
    sleep(1);
    $vsGet = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/vector_stores/' . $vectorStoreId, null, null, null, null, true);
    $vsStatus = $vsGet['body']['status'] ?? $vsStatus;
}
out("Vector store status: " . $vsStatus, $isCli);
out("", $isCli);

// Step 3: Create assistant with vector store
out("=== Step 3: Create assistant (with vector_store_ids) ===", $isCli);
$assistantBody = [
    'model' => 'gpt-4o-mini',
    'name' => 'Meeting summarizer',
    'instructions' => 'You summarize meeting minutes. Output exactly 3-5 bullet points in English only.',
    'tools' => [['type' => 'file_search']],
    'tool_resources' => ['file_search' => ['vector_store_ids' => [$vectorStoreId]]],
];
out("Request body:", $isCli);
outJson($assistantBody, $isCli);
$assistant = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/assistants', json_encode($assistantBody), 'application/json', null, null, true);
out("HTTP code: " . ($assistant['code'] ?? 'N/A'), $isCli);
out("Response:", $isCli);
outJson($assistant['body'], $isCli);
if (isset($assistant['error']) || !is_array($assistant['body'] ?? null) || empty($assistant['body']['id'])) {
    $err = $assistant['body']['error']['message'] ?? 'Unknown error';
    out("ERROR: " . $err, $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
$assistantId = $assistant['body']['id'];
out("Assistant ID: " . $assistantId, $isCli);
out("", $isCli);

// Step 4: Create thread
out("=== Step 4: Create thread ===", $isCli);
$thread = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads', '{}', 'application/json', null, null, true);
out("HTTP code: " . ($thread['code'] ?? 'N/A'), $isCli);
outJson($thread['body'], $isCli);
if (isset($thread['error']) || !is_array($thread['body'] ?? null) || empty($thread['body']['id'])) {
    out("ERROR: Failed to create thread.", $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
$threadId = $thread['body']['id'];
out("Thread ID: " . $threadId, $isCli);
out("", $isCli);

// Step 5: Add message (no file attachment; doc is in assistant's vector store)
out("=== Step 5: Add message ===", $isCli);
$messageBody = [
    'role' => 'user',
    'content' => 'Summarize the meeting minutes document in 3-5 bullet points in English. Output only the bullet points.',
];
$msg = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads/' . $threadId . '/messages', json_encode($messageBody), 'application/json', null, null, true);
out("HTTP code: " . ($msg['code'] ?? 'N/A'), $isCli);
outJson($msg['body'], $isCli);
if (isset($msg['error']) || (is_array($msg['body'] ?? null) && !empty($msg['body']['error']))) {
    out("ERROR: Failed to add message.", $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
out("", $isCli);

// Step 6: Create run
out("=== Step 6: Create run ===", $isCli);
$runBody = ['assistant_id' => $assistantId];
$run = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads/' . $threadId . '/runs', json_encode($runBody), 'application/json', null, null, true);
out("HTTP code: " . ($run['code'] ?? 'N/A'), $isCli);
outJson($run['body'], $isCli);
if (isset($run['error'])) {
    out("ERROR: " . $run['error'], $isCli);
    if (function_exists('error_get_last') && error_get_last()) {
        outJson(error_get_last(), $isCli);
    }
    if (!$isCli) echo '</body></html>';
    exit(1);
}
if (!is_array($run['body'] ?? null) || empty($run['body']['id'])) {
    out("ERROR: Failed to create run. Response:", $isCli);
    outJson($run['body'], $isCli);
    if (!$isCli) echo '</body></html>';
    exit(1);
}
$runId = $run['body']['id'];
out("Run ID: " . $runId . " (polling for completion...) ", $isCli);
out("", $isCli);

// Step 7: Poll
$deadline = time() + 30;
while (time() < $deadline) {
    $status = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/threads/' . $threadId . '/runs/' . $runId, null, null, null, null, true);
    $runData = $status['body'] ?? [];
    $runStatus = $runData['status'] ?? '';
    out("Run status: " . $runStatus, $isCli);
    if ($runStatus === 'completed') {
        $list = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/threads/' . $threadId . '/messages?order=desc&limit=1', null, null, null, null, true);
        $first = $list['body']['data'][0] ?? null;
        if (is_array($first)) {
            foreach ($first['content'] ?? [] as $block) {
                if (isset($block['text']['value'])) {
                    out("=== Summary ===", $isCli);
                    out($block['text']['value'], $isCli);
                    break;
                }
            }
        }
        break;
    }
    if (in_array($runStatus, ['failed', 'cancelled', 'expired'], true)) {
        out("ERROR: Run " . $runStatus . " - " . ($runData['last_error']['message'] ?? ''), $isCli);
        break;
    }
    sleep(1);
}

// Cleanup
openaiRequest($apiKey, 'DELETE', 'https://api.openai.com/v1/files/' . $fileId);
out("", $isCli);
out("Done. (Uploaded file deleted from OpenAI.)", $isCli);

if (!$isCli) echo '</body></html>';
exit(0);
