<?php

declare(strict_types=1);

header('Content-Type: application/json');

$config = require __DIR__ . '/../config/config.php';
$apiKey = $config['openai']['api_key'] ?? '';
$maxBytes = $config['upload']['max_bytes'] ?? (20 * 1024 * 1024);
$allowedExt = $config['upload']['allowed_extensions'] ?? ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'rtf'];
$totalTimeout = 10;

function jsonResponse(array $data): void {
    echo json_encode($data);
    exit;
}

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
            'timeout' => 90,
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
            . "Content-Type: application/octet-stream\r\n\r\n"
            . $content . "\r\n"
            . "--$boundary--\r\n";
    }
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['error' => error_get_last()['message'] ?? 'Request failed', 'code' => 0];
    }
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return ['body' => json_decode($response, true) ?: $response, 'code' => $code];
}

if ($apiKey === '') {
    jsonResponse(['success' => false, 'error' => 'OpenAI API key not configured.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['document_file']['name']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse(['success' => false, 'error' => 'No file uploaded.']);
}

$file = $_FILES['document_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'Upload failed.']);
}
if ($file['size'] > $maxBytes) {
    jsonResponse(['success' => false, 'error' => 'File too large.']);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    jsonResponse(['success' => false, 'error' => 'File type not supported.']);
}

$tmpPath = $file['tmp_name'];
$startTime = time();

// 1. Upload file to OpenAI (OpenAI will read and summarize it)
$upload = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/files', null, null, $tmpPath, $file['name'], false);
if (isset($upload['error'])) {
    if (strpos($upload['error'], 'timed out') !== false) {
        jsonResponse(['success' => false, 'timeout' => true]);
    }
    jsonResponse(['success' => false, 'error' => 'Failed to upload file to AI. Please try again.']);
}
$uploadBody = $upload['body'] ?? [];
if (!is_array($uploadBody) || empty($uploadBody['id'])) {
    $err = $uploadBody['error']['message'] ?? 'Invalid upload response.';
    jsonResponse(['success' => false, 'error' => $err]);
}
$fileId = $uploadBody['id'];

try {
    // 2. Create vector store with the uploaded file (Assistants API v2)
    $vectorStoreBody = ['file_ids' => [$fileId], 'name' => 'Meeting minutes'];
    $vectorStore = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/vector_stores', json_encode($vectorStoreBody), 'application/json', null, null, true);
    if (isset($vectorStore['error']) || !is_array($vectorStore['body'] ?? null) || empty($vectorStore['body']['id'])) {
        $err = is_array($vectorStore['body'] ?? null) && isset($vectorStore['body']['error']['message'])
            ? $vectorStore['body']['error']['message'] : 'Failed to create vector store.';
        throw new RuntimeException($err);
    }
    $vectorStoreId = $vectorStore['body']['id'];
    $vsStatus = $vectorStore['body']['status'] ?? 'in_progress';
    $deadline = time() + 60;
    while ($vsStatus === 'in_progress' && time() < $deadline) {
        usleep(500000);
        $vsGet = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/vector_stores/' . $vectorStoreId, null, null, null, null, true);
        $vsStatus = $vsGet['body']['status'] ?? $vsStatus;
        if ($vsStatus === 'completed') {
            break;
        }
        if (($vsGet['body']['file_counts']['failed'] ?? 0) > 0) {
            throw new RuntimeException('File could not be processed for summarization.');
        }
    }

    // 3. Create assistant with vector store (file_search reads from it)
    $assistantBody = [
        'model' => 'gpt-4o-mini',
        'name' => 'Meeting summarizer',
        'instructions' => 'You summarize meeting minutes. Output exactly 3-5 bullet points in English only. The document may be in Chinese or English; always respond in English. Output only the bullet points, no heading or extra text.',
        'tools' => [['type' => 'file_search']],
        'tool_resources' => ['file_search' => ['vector_store_ids' => [$vectorStoreId]]],
    ];
    $assistant = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/assistants', json_encode($assistantBody), 'application/json', null, null, true);
    if (isset($assistant['error']) || !is_array($assistant['body'] ?? null) || empty($assistant['body']['id'])) {
        $err = is_array($assistant['body'] ?? null) && isset($assistant['body']['error']['message'])
            ? $assistant['body']['error']['message'] : 'Failed to create assistant.';
        throw new RuntimeException($err);
    }
    $assistantId = $assistant['body']['id'];

    // 4. Create thread
    $thread = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads', '{}', 'application/json', null, null, true);
    if (isset($thread['error']) || !is_array($thread['body'] ?? null) || empty($thread['body']['id'])) {
        throw new RuntimeException('Failed to create thread.');
    }
    $threadId = $thread['body']['id'];

    // 5. Add message (document is in assistant's vector store; no attachment needed)
    $messageBody = [
        'role' => 'user',
        'content' => 'Summarize the meeting minutes document in 3-5 bullet points in English. The document may be in Chinese or English; output in English only. Output only the bullet points.',
    ];
    $msg = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads/' . $threadId . '/messages', json_encode($messageBody), 'application/json', null, null, true);
    if (isset($msg['error'])) {
        throw new RuntimeException('Failed to add message.');
    }
    if (is_array($msg['body'] ?? null) && !empty($msg['body']['error']['message'])) {
        throw new RuntimeException($msg['body']['error']['message']);
    }

    // 6. Create run
    $runBody = ['assistant_id' => $assistantId];
    $run = openaiRequest($apiKey, 'POST', 'https://api.openai.com/v1/threads/' . $threadId . '/runs', json_encode($runBody), 'application/json', null, null, true);
    if (isset($run['error']) || !is_array($run['body'] ?? null) || empty($run['body']['id'])) {
        throw new RuntimeException($run['body']['error']['message'] ?? 'Failed to create run.');
    }
    $runId = $run['body']['id'];

    // 7. Poll until completed (max $totalTimeout seconds)
    $summary = null;
    while ((time() - $startTime) < $totalTimeout) {
        $status = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/threads/' . $threadId . '/runs/' . $runId, null, null, null, null, true);
        if (isset($status['error'])) {
            break;
        }
        $runData = $status['body'] ?? [];
        $runStatus = $runData['status'] ?? '';
        if ($runStatus === 'completed') {
            $list = openaiRequest($apiKey, 'GET', 'https://api.openai.com/v1/threads/' . $threadId . '/messages?order=desc&limit=1', null, null, null, null, true);
            if (!isset($list['error']) && is_array($list['body']['data'][0] ?? null)) {
                $first = $list['body']['data'][0];
                $content = $first['content'] ?? [];
                foreach ($content as $block) {
                    if (isset($block['text']['value'])) {
                        $summary = trim($block['text']['value']);
                        break;
                    }
                }
            }
            break;
        }
        if (in_array($runStatus, ['failed', 'cancelled', 'expired'], true)) {
            $lastError = $runData['last_error']['message'] ?? 'Run ' . $runStatus;
            throw new RuntimeException($lastError);
        }
        usleep(800000);
    }

    if ($summary === null || $summary === '') {
        if ((time() - $startTime) >= $totalTimeout) {
            jsonResponse(['success' => false, 'timeout' => true]);
        }
        jsonResponse(['success' => false, 'error' => 'AI did not return a summary. Please try again or enter the description manually.']);
    }

    jsonResponse(['success' => true, 'summary' => $summary]);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'timed out') !== false || (time() - $startTime) >= $totalTimeout) {
        jsonResponse(['success' => false, 'timeout' => true]);
    }
    jsonResponse(['success' => false, 'error' => $msg]);
} finally {
    @openaiRequest($apiKey, 'DELETE', 'https://api.openai.com/v1/files/' . $fileId);
}
