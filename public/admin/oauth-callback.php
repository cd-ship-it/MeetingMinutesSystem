<?php

declare(strict_types=1);

session_start();
$config = require __DIR__ . '/../../config/config.php';

if ($config['env'] !== 'production') {
    header('Location: ' . $config['app_url'] . '/admin/');
    exit;
}

$clientId = $config['google']['client_id'] ?? null;
$clientSecret = $config['google']['client_secret'] ?? null;
$baseUrl = $config['app_url'];

if (!$clientId || !$clientSecret) {
    $_SESSION['admin_error'] = 'Google OAuth not configured.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    $_SESSION['admin_error'] = 'Login was cancelled or failed.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

if (!$code) {
    $_SESSION['admin_error'] = 'Missing authorization code.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

$redirectUri = $baseUrl . '/admin/oauth-callback.php';
$tokenUrl = 'https://oauth2.googleapis.com/token';
$body = http_build_query([
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
]);

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $body,
    ],
]);
$response = @file_get_contents($tokenUrl, false, $ctx);
if ($response === false) {
    $_SESSION['admin_error'] = 'Could not complete login.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

$data = json_decode($response, true);
if (empty($data['access_token'])) {
    $_SESSION['admin_error'] = 'Invalid response from Google.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_oauth_token'] = $data['access_token'];
unset($_SESSION['admin_error']);
header('Location: ' . $baseUrl . '/admin/');
exit;
