<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $config['app_url'] . '/');
    exit;
}

$isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$baseUrl = $config['app_url'];

function sendError(string $msg, string $baseUrl, bool $isXhr): void {
    if ($isXhr) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    header('Location: ' . $baseUrl . '/?error=' . urlencode($msg));
    exit;
}

/**
 * Check if a cloud document link (Google Doc, Dropbox, etc.) appears to be publicly accessible.
 * Returns true if we get a 2xx and don't land on sign-in; false if 403 or redirected to login.
 */
function is_cloud_doc_link_publicly_accessible(string $url, int $timeoutSeconds = 5): bool {
    $isGoogleDoc = preg_match('#^https?://docs\.google\.com/document/#i', $url);
    $isDropbox = preg_match('#^https?://(?:www\.)?dropbox\.com/#i', $url)
        || preg_match('#^https?://dl\.dropboxusercontent\.com/#i', $url);
    if (!$isGoogleDoc && !$isDropbox) {
        return true; // not a checked provider, skip
    }
    if (!function_exists('curl_init')) {
        return true;
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return true;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MeetingMinutes/1.0)',
    ]);
    curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($httpCode === 403 || $httpCode === 404) {
        return false;
    }
    if ($isGoogleDoc && preg_match('#^https?://(?:accounts\.google\.com|www\.google\.com)(?:/|$)#i', $effectiveUrl)) {
        return false;
    }
    if ($isDropbox && preg_match('#^https?://(?:www\.)?dropbox\.com/(?:login|session)#i', $effectiveUrl)) {
        return false;
    }
    return $httpCode >= 200 && $httpCode < 400;
}

$uploadDir = $config['upload']['dir'];
$maxBytes = $config['upload']['max_bytes'];
$allowedExt = $config['upload']['allowed_extensions'];

// Validate required metadata (from config); skip in development
$required = ($config['env'] === 'development') ? [] : ($config['required_fields'] ?? []);
$errors = [];
foreach ($required as $field) {
    $v = trim((string) ($_POST[$field] ?? ''));
    if ($v === '') {
        $errors[] = 'Missing or empty: ' . str_replace('_', ' ', $field);
    }
}

// When ministry is "Others", require ministry_other and use it as stored ministry value
$ministryValue = trim((string) ($_POST['ministry'] ?? ''));
if ($ministryValue === 'Others') {
    $ministryOther = trim((string) ($_POST['ministry_other'] ?? ''));
    if ($ministryOther === '') {
        $errors[] = 'Please specify the ministry name when selecting Others.';
    } else {
        $ministryValue = $ministryOther;
    }
}
if (!empty($errors)) {
    sendError(implode('. ', $errors), $baseUrl, $isXhr);
}

$documentUrl = trim((string) ($_POST['document_url'] ?? ''));
$documentPaste = trim((string) ($_POST['document_paste'] ?? ''));
$hasFile = !empty($_FILES['document_file']['name']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE;
$hasUrl = $documentUrl !== '';
$hasPaste = $documentPaste !== '';

if (!$hasFile && !$hasUrl && !$hasPaste) {
    $errors[] = 'Please upload a file, provide a web link, or paste your document content.';
}

if (!empty($errors)) {
    sendError(implode('. ', $errors), $baseUrl, $isXhr);
}

$documentType = null;
$filePath = null;
$storedUrl = null;
$storedPaste = null;

if ($hasFile) {
    $file = $_FILES['document_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'File too large.'
            : 'Upload failed. Please try again.';
        sendError($msg, $baseUrl, $isXhr);
    }
    if ($file['size'] > $maxBytes) {
        sendError('File exceeds maximum size.', $baseUrl, $isXhr);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        sendError('File type not allowed. Allowed: ' . implode(', ', $allowedExt), $baseUrl, $isXhr);
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $targetPath = $uploadDir . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        sendError('Could not save file.', $baseUrl, $isXhr);
    }
    $documentType = 'file';
    $filePath = $uploadDir . '/' . $safeName;
}

if ($hasUrl && !$documentType) {
    if (!preg_match('#^https?://#i', $documentUrl)) {
        sendError('Please enter a valid URL.', $baseUrl, $isXhr);
    }
    if (!is_cloud_doc_link_publicly_accessible($documentUrl)) {
        sendError('This link does not appear to be publicly accessible. For Google Doc or Dropbox, set sharing to "Anyone with the link" (viewer) and try again.', $baseUrl, $isXhr);
    }
    $documentType = 'url';
    $storedUrl = $documentUrl;
}

if ($hasPaste && !$documentType) {
    $documentType = 'paste';
    $storedPaste = strip_tags($documentPaste, '<p><br><b><i><u><strong><em><ul><ol><li><h1><h2><h3><span><div>');
}

try {
    $pdo = get_db($config);
    $sql = <<<'SQL'
INSERT INTO meetings (
    chair_first_name, chair_last_name, chair_email, campus_name, ministry,
    pastor_in_charge, attendees, meeting_type, description, comments,
    document_type, file_path, document_url, pasted_text
) VALUES (
    :chair_first_name, :chair_last_name, :chair_email, :campus_name, :ministry,
    :pastor_in_charge, :attendees, :meeting_type, :description, :comments,
    :document_type, :file_path, :document_url, :pasted_text
)
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'chair_first_name' => trim($_POST['chair_first_name'] ?? ''),
        'chair_last_name' => trim($_POST['chair_last_name'] ?? ''),
        'chair_email' => trim($_POST['chair_email'] ?? ''),
        'campus_name' => trim($_POST['campus_name'] ?? ''),
        'ministry' => $ministryValue,
        'pastor_in_charge' => trim($_POST['pastor_in_charge'] ?? ''),
        'attendees' => trim($_POST['attendees'] ?? '') ?: null,
        'meeting_type' => trim($_POST['meeting_type'] ?? '') ?: null,
        'description' => trim($_POST['description'] ?? ''),
        'comments' => substr(trim($_POST['comments'] ?? ''), 0, 1000) ?: null,
        'document_type' => $documentType,
        'file_path' => $filePath,
        'document_url' => $storedUrl,
        'pasted_text' => $storedPaste,
    ]);
} catch (Throwable $e) {
    if ($filePath && is_file($uploadDir . '/' . basename($filePath))) {
        @unlink($uploadDir . '/' . basename($filePath));
    }
    app_log('[submit] ' . $e->getMessage(), $config);
    app_log('[submit] ' . $e->getTraceAsString(), $config);
    app_log('[submit] ' . $storedPaste, $config);
    sendError('Could not save. Please try again.', $baseUrl, $isXhr);
}

if ($isXhr) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => $baseUrl . '/?success=1']);
    exit;
}
header('Location: ' . $baseUrl . '/?success=1');

exit;
