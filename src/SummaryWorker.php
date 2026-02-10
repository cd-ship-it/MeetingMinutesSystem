<?php

declare(strict_types=1);

/**
 * Shared logic for the periodic AI summary worker.
 * Populates minutes_md from file/url/paste and requests summary from OpenAI.
 */

require_once __DIR__ . '/TextExtractor.php';
require_once __DIR__ . '/GoogleDocFetcher.php';

/** Minimum length for minutes_md to call OpenAI (avoid empty/short content). */
const SUMMARY_MIN_MINUTES_LENGTH = 20;

/**
 * Get markdown content for a meeting row (file, URL, or paste).
 * Returns null if content could not be obtained (e.g. PDF placeholder, fetch failed).
 */
function get_minutes_md_for_row(array $row, array $config, callable $log): ?string
{
    $documentType = $row['document_type'] ?? '';
    $filePath = $row['file_path'] ?? null;
    $documentUrl = $row['document_url'] ?? null;
    $pastedText = $row['pasted_text'] ?? null;

    if ($documentType === 'file' && $filePath !== null && $filePath !== '') {
        return get_minutes_md_from_file($filePath, $config, $log);
    }
    if ($documentType === 'url' && $documentUrl !== null && $documentUrl !== '') {
        return get_minutes_md_from_url($documentUrl, $config, $log);
    }
    if ($documentType === 'paste' && $pastedText !== null) {
        return get_minutes_md_from_paste($pastedText);
    }

    $log("Unknown or empty source for document_type={$documentType}");
    return null;
}

/**
 * File (Word/txt/rtf): extract text and convert to simple markdown.
 * PDF: placeholder only, returns null and logs.
 */
function get_minutes_md_from_file(string $filePath, array $config, callable $log): ?string
{
    $projectRoot = dirname(__DIR__);
    $uploadDir = $config['upload']['dir'] ?? ($projectRoot . '/uploads');
    $fullPath = $filePath;
    if ($fullPath !== '' && $fullPath[0] !== '/' && !preg_match('#^[A-Za-z]:[\\\\/]#', $fullPath)) {
        $fullPath = $projectRoot . '/' . ltrim($filePath, '/');
    }
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        $log("File not found or not readable: {$fullPath}");
        return null;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    // Only allow non-PDF types here; PDFs are intentionally skipped for now.
    $allowedForExtract = ['docx', 'doc', 'txt', 'rtf'];
    if (!in_array($ext, $allowedForExtract, true)) {
        $log("Unsupported file extension for extraction: {$ext}");
        return null;
    }

    $text = extract_text_from_file($fullPath, $ext);
    if ($text === null || trim($text) === '') {
        $log("No text extracted from file: {$fullPath}");
        return null;
    }

    return plain_text_to_markdown($text);
}

/**
 * Try to fetch document content from a URL using known provider-specific export endpoints.
 * Returns content as plain text, or null if URL is not supported or fetch failed.
 */
function fetch_content_from_export_url(string $url, array $config, callable $log): ?string
{
    if (is_google_doc_url($url)) {
        $docId = get_google_doc_id($url);
        if ($docId !== null) {
            $content = fetch_google_doc_content($docId, $config, $log);
            if ($content !== null) {
                return $content;
            }
        }
        $log('Export fetch failed; document may need to be shared as "Anyone with the link can view".');
        return null;
    }
    return null;
}

/**
 * Fetch URL content and convert to markdown (strip HTML, normalize).
 * Uses provider-specific export endpoints when available (e.g. Google Docs); otherwise fetches the URL directly.
 */
function get_minutes_md_from_url(string $url, array $config, callable $log): ?string
{
    $timeout = 30;
    $maxBytes = 512 * 1024; // 500 KB

    $content = fetch_content_from_export_url($url, $config, $log);
    if ($content !== null) {
        return $content;
    }
    if (is_google_doc_url($url)) {
        return null; // export already attempted and failed
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'Mozilla/5.0 (compatible; MeetingMinutesSummary/1.0)',
            'follow_location' => 1,
        ],
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            $log('cURL init failed');
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MeetingMinutesSummary/1.0)',
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 400) {
            $log("URL returned HTTP {$httpCode}");
            return null;
        }
        $body = is_string($body) ? $body : '';
    } else {
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $log("Failed to fetch URL: {$url}");
            return null;
        }
    }

    if (strlen($body) > $maxBytes) {
        $body = substr($body, 0, $maxBytes);
    }

    $md = html_to_markdown($body);
    return $md !== '' ? $md : null;
}

/**
 * Paste: convert HTML block elements to newlines, then strip tags and normalize.
 */
function get_minutes_md_from_paste(string $pastedText): ?string
{
    $text = $pastedText;
    // Convert block-level HTML to newlines before stripping tags so structure is preserved
    $text = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $text);
    $text = preg_replace('#<br\s*/?>#i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n|\r/", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", trim($text));
    return $text !== '' ? $text : null;
}

/**
 * Convert plain text to simple markdown (paragraphs = double newline).
 */
function plain_text_to_markdown(string $text): string
{
    $text = trim($text);
    $text = preg_replace("/\r\n/", "\n", $text);
    $text = preg_replace("/\r/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return $text;
}

/**
 * Convert HTML to simple markdown (strip tags, normalize whitespace).
 */
function html_to_markdown(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    return $text;
}

/**
 * Call OpenAI Chat Completions API for summary. Uses OPENAI_MODEL and OPENAI_SUMMARY_PROMPT from config.
 *
 * @param string $minutesMd   The markdown minutes content to summarize.
 * @param array  $config      App configuration (must contain 'openai' settings).
 * @param int    $timeoutSec  Network timeout in seconds (default 90; can be lowered for web previews).
 */
function request_openai_summary(string $minutesMd, array $config, int $timeoutSec = 90): ?string
{
    $apiKey = $config['openai']['api_key'] ?? '';
    $model = $config['openai']['model'] ?? 'gpt-4o-mini';
    $promptTemplate = $config['openai']['summary_prompt'] ?? 'Summarize the following meeting minutes in exactly 3 bullet points in English.';

    if ($apiKey === '') {
        return null;
    }

    $userContent = $promptTemplate . "\n\n---\n\n" . $minutesMd;

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $userContent],
        ],
        'max_tokens' => 1500,
        'temperature' => 0.3,
    ];

    $json = json_encode($payload);
    if ($json === false) {
        return null;
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeoutSec > 0 ? $timeoutSec : 90,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response)) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
        return null;
    }

    $summary = trim((string) $data['choices'][0]['message']['content']);
    return $summary !== '' ? $summary : null;
}

/**
 * Process one meeting: populate minutes_md, call OpenAI, UPDATE row.
 * Returns true if updated, false if skipped or failed.
 */
function process_meeting(int $id, array $row, PDO $pdo, array $config, callable $log): bool
{
    $logWithId = function (string $msg) use ($log, $id): void {
        $log("Meeting {$id}: " . $msg);
    };

    $minutesMd = get_minutes_md_for_row($row, $config, $logWithId);
    if ($minutesMd === null) {
        return false;
    }
    if (strlen($minutesMd) < SUMMARY_MIN_MINUTES_LENGTH) {
        $logWithId('minutes_md too short, skipping');
        return false;
    }

    $summary = request_openai_summary($minutesMd, $config);
    if ($summary === null) {
        $logWithId('OpenAI summary request failed');
        return false;
    }

    $stmt = $pdo->prepare('UPDATE meetings SET minutes_md = :minutes_md, ai_summary = :ai_summary WHERE id = :id');
    $stmt->execute([
        'id' => $id,
        'minutes_md' => $minutesMd,
        'ai_summary' => $summary,
    ]);

    $logWithId('ai_summary and minutes_md updated');
    return true;
}
