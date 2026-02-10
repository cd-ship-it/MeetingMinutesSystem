<?php

declare(strict_types=1);

/**
 * Load .env from project root (one level up from config/).
 */
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

$config = [
    'env' => getenv('APP_ENV') ?: 'development',
    'app_url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8888/MeetingMinutesSystem/public', '/'),
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int) (getenv('DB_PORT') ?: '8889'),
        'name' => getenv('DB_NAME') ?: 'crossp11_db1',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'root',
        'socket' => (($s = getenv('DB_SOCKET')) !== false && $s !== '') ? $s : null,
    ],
    'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'credentials_path' => getenv('GOOGLE_CREDENTIALS_PATH'),
    ],
    'admin_allowed_email_domain' => getenv('ADMIN_ALLOWED_EMAIL_DOMAIN') ?: 'crosspointchurchsv.org',
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
        'summary_prompt' => getenv('OPENAI_SUMMARY_PROMPT') ?: 'Summarize the following meeting minutes in exactly 3 bullet points in English. The content may be in Chinese or English; output only the 3 bullet points, no heading or extra text.',
    ],
    'use_ai_for_minutes_summary' => filter_var(
        (($v = getenv('USE_AI_FOR_MINUTES_SUMMARY')) !== false && $v !== '' ? $v : '0'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'upload' => [
        'dir' => (function () {
            $v = getenv('UPLOAD_DIR');
            if ($v !== false && $v !== '') {
                return $v[0] === '/' ? $v : dirname(__DIR__) . '/' . $v;
            }
            return dirname(__DIR__) . '/uploads';
        })(),
        'max_bytes' => 20 * 1024 * 1024, // 20 MB
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'rtf'],
    ],
    'log' => [
        'dir' => dirname(__DIR__) . '/logs',
        'file' => 'app.log',
    ],
    'required_fields' => [
        'chair_first_name',
        'chair_last_name',
        'campus_name',
        'ministry'
    ],
    'campus_names' => [
        'Church-wide',  
        'Milpitas',
        'Pleasanton',
        'San Leandro',
        'Peninsula',
        'Tracy'
    ],
    'ministry_names' => [
        'Worship',
        'Go',
        'Grow',
        'Life Group',
        "Children's Ministry",
        'Youth',
        'Church Staff Meeting',
        'Others',
    ],
];

// Load Google credentials from JSON file if path set and client_id not in env
if (!empty($config['google']['credentials_path']) && is_file($config['google']['credentials_path'])) {
    $json = json_decode(file_get_contents($config['google']['credentials_path']), true);
    if (isset($json['web']['client_id'])) {
        $config['google']['client_id'] = $config['google']['client_id'] ?? $json['web']['client_id'];
        $config['google']['client_secret'] = $config['google']['client_secret'] ?? $json['web']['client_secret'];
    }
}

// Default to project root client_secret JSON if exists
if (empty($config['google']['client_id'])) {
    $defaultCreds = dirname(__DIR__) . '/client_secret_206685728493-f2bmsbvritupbt5tj2rjdhkbbsb9ufp9.apps.googleusercontent.com.json';
    if (is_file($defaultCreds)) {
        $json = json_decode(file_get_contents($defaultCreds), true);
        if (isset($json['web']['client_id'])) {
            $config['google']['client_id'] = $json['web']['client_id'];
            $config['google']['client_secret'] = $json['web']['client_secret'];
        }
    }
}

return $config;
