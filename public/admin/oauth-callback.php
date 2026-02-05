<?php

declare(strict_types=1);

session_start();
$config = require __DIR__ . '/../../config/config.php';

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

// Fetch user email and restrict to allowed domain (e.g. @crosspointchurchsv.org)
$allowedDomain = $config['admin_allowed_email_domain'] ?? 'crosspointchurchsv.org';
$userinfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$userinfoCtx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer " . $data['access_token'] . "\r\n",
    ],
]);
$userinfoResponse = @file_get_contents($userinfoUrl, false, $userinfoCtx);
$userEmail = null;
if ($userinfoResponse !== false) {
    $userinfo = json_decode($userinfoResponse, true);
    $userEmail = isset($userinfo['email']) ? strtolower(trim($userinfo['email'])) : null;
}
$domainSuffix = '@' . strtolower($allowedDomain);
if ($userEmail === null || substr($userEmail, -strlen($domainSuffix)) !== $domainSuffix) {
    $_SESSION['admin_error'] = 'Access restricted to @' . $allowedDomain . ' only.';
    header('Location: ' . $baseUrl . '/admin/');
    exit;
}

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_oauth_token'] = $data['access_token'];
unset($_SESSION['admin_error']);
header('Location: ' . $baseUrl . '/admin/');
exit;
