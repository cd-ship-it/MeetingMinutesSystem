<?php

declare(strict_types=1);

session_start();
$config = require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/db.php';

// Require login (development and production)
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . $config['app_url'] . '/admin/');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request');
}

$pdo = get_db($config);
$stmt = $pdo->prepare("SELECT file_path, document_type FROM meetings WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['document_type'] !== 'file' || empty($row['file_path'])) {
    http_response_code(404);
    exit('File not found');
}

$uploadDir = $config['upload']['dir'];
$fileName = basename($row['file_path']);
$fullPath = $fileName;

// Prevent directory traversal
$realRoot = realpath($uploadDir);
$realPath = realpath($fullPath);
if ($realRoot === false || $realPath === false || strpos($realPath, $realRoot) !== 0) {
    http_response_code(404);
    exit('File not found');
}

if (!is_file($realPath)) {
    http_response_code(404);
    exit('File not found');
}

$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'txt' => 'text/plain',
    'rtf' => 'application/rtf',
];
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';
$name = $fileName;

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($name) . '"');
header('Content-Length: ' . filesize($realPath));
readfile($realPath);
exit;
