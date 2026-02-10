<?php

declare(strict_types=1);

/**
 * Fetch document content from Google Docs URLs via the export endpoint.
 * The normal view URL returns a JavaScript app; export?format=txt returns the actual text.
 * Doc must be shared "Anyone with the link can view" for export to work without sign-in.
 */

/**
 * Whether the URL is a Google Docs document (view/edit URL).
 */
function is_google_doc_url(string $url): bool
{
    return (bool) preg_match('#^https?://docs\.google\.com/document/d/[a-zA-Z0-9_-]+#i', $url);
}

/**
 * Extract Google Docs document ID from a docs.google.com URL.
 */
function get_google_doc_id(string $url): ?string
{
    if (preg_match('#docs\.google\.com/document/d/([a-zA-Z0-9_-]+)#i', $url, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Strip HTML to plain text (for use when export returns HTML).
 */
function _google_doc_html_to_text(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/**
 * Normalize plain text (newlines).
 */
function _google_doc_normalize_text(string $text): string
{
    $text = trim($text);
    $text = preg_replace("/\r\n/", "\n", $text);
    $text = preg_replace("/\r/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return $text;
}

/**
 * Fetch Google Doc content via the export endpoint (plain text or HTML).
 * Returns document content as plain text, or null on failure.
 */
function fetch_google_doc_content(string $docId, array $config, callable $log): ?string
{
    $timeout = 30;
    $maxBytes = 512 * 1024; // 500 KB
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    $exportUrl = "https://docs.google.com/document/d/{$docId}/export?format=txt";

    if (!function_exists('curl_init')) {
        $log('Google Doc export requires cURL');
        return null;
    }

    $ch = curl_init($exportUrl);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => $userAgent,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($body)) {
        $log("Google Doc export (txt) returned HTTP {$httpCode}");
        return null;
    }

    $body = strlen($body) > $maxBytes ? substr($body, 0, $maxBytes) : $body;

    // If response looks like HTML (login page, "JavaScript isn't enabled", etc.), try HTML export and strip
    $isLikelyHtml = (stripos($body, '<html') !== false || stripos($body, '<!DOCTYPE') !== false)
        || (stripos($body, 'JavaScript isn\'t enabled') !== false)
        || (stripos($body, 'Sign in') !== false && stripos($body, 'docs.google.com') !== false);

    if ($isLikelyHtml) {
        $exportUrlHtml = "https://docs.google.com/document/d/{$docId}/export?format=html";
        $ch = curl_init($exportUrlHtml);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $userAgent,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200 || !is_string($body)) {
            $log("Google Doc export (html) returned HTTP {$httpCode}");
            return null;
        }
        $body = strlen($body) > $maxBytes ? substr($body, 0, $maxBytes) : $body;
        $text = _google_doc_html_to_text($body);
        return $text !== '' ? $text : null;
    }

    $text = _google_doc_normalize_text($body);
    return $text !== '' ? $text : null;
}
