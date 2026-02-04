<?php

declare(strict_types=1);

session_start();
$config = require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/db.php';

$baseUrl = $config['app_url'];
$isProduction = $config['env'] === 'production';
$clientId = $config['google']['client_id'] ?? null;

// Auth: in production require Google OAuth; in development skip
if ($isProduction && empty($_SESSION['admin_logged_in'])) {
    if ($clientId) {
        $redirectUri = $baseUrl . '/admin/oauth-callback.php';
        $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
        ]);
        header('Location: ' . $authUrl);
        exit;
    }
    $error = $_SESSION['admin_error'] ?? 'Google OAuth not configured.';
    unset($_SESSION['admin_error']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 max-w-sm w-full text-center">
            <h1 class="text-xl font-semibold text-slate-800 mb-4">Admin</h1>
            <p class="text-red-600 text-sm"><?= htmlspecialchars($error) ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Pagination: 10 per page
$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

try {
    $pdo = get_db($config);
    $countStmt = $pdo->query("SELECT COUNT(*) FROM meetings");
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT * FROM meetings ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $meetings = [];
    $total = 0;
    $totalPages = 1;
    $page = 1;
}

$projectRoot = dirname(__DIR__, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Minutes — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-slate-800">Meeting Minutes — Admin</h1>
            <?php if ($isProduction && !empty($_SESSION['admin_logged_in'])): ?>
                <a href="<?= htmlspecialchars($baseUrl) ?>/admin/logout.php" class="text-sm text-slate-600 hover:text-slate-800">Sign out</a>
            <?php endif; ?>
        </div>

        <?php if (empty($meetings)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
                No meeting minutes submitted yet.
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($meetings as $m): ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col sm:flex-row sm:items-start gap-4">
                        <div class="flex-1 min-w-0 space-y-2">
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <span class="font-medium text-slate-800"><?= htmlspecialchars(trim($m['chair_first_name'] . ' ' . $m['chair_last_name'])) ?></span>
                                <?php if (!empty($m['chair_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($m['chair_email']) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($m['chair_email']) ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm text-slate-600 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                                <span><strong>Campus:</strong> <?= htmlspecialchars($m['campus_name'] ?? '—') ?></span>
                                <span><strong>Ministry:</strong> <?= htmlspecialchars($m['ministry'] ?? '—') ?></span>
                                <span><strong>Pastor-in-charge:</strong> <?= htmlspecialchars($m['pastor_in_charge'] ?? '—') ?></span>
                                <span><strong>Type:</strong> <?= $m['meeting_type'] === 'online' ? 'Online' : 'In person' ?></span>
                            </div>
                            <?php if (!empty($m['attendees'])): ?>
                                <p class="text-sm text-slate-600"><strong>Attendees:</strong> <?= htmlspecialchars($m['attendees']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($m['description'])): ?>
                                <p class="text-sm text-slate-700"><?= nl2br(htmlspecialchars($m['description'])) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($m['created_at'] ?? '') ?></p>
                        </div>
                        <div class="flex-shrink-0">
                            <?php if ($m['document_type'] === 'file' && !empty($m['file_path'])): ?>
                                <?php
                                $fullPath = $projectRoot . '/' . $m['file_path'];
                                $exists = is_file($fullPath);
                                ?>
                                <?php if ($exists): ?>
                                    <a href="<?= htmlspecialchars($baseUrl . '/admin/view-file.php?id=' . (int)$m['id']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-700 text-sm font-medium">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        View file
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-400 text-sm">File missing</span>
                                <?php endif; ?>
                            <?php elseif ($m['document_type'] === 'url' && !empty($m['document_url'])): ?>
                                <a href="<?= htmlspecialchars($m['document_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg text-blue-800 text-sm font-medium">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                    Open link
                                </a>
                            <?php else: ?>
                                <span class="text-slate-400 text-sm">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-6 flex items-center justify-center gap-2 flex-wrap">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium">Previous</a>
                    <?php endif; ?>
                    <span class="px-3 py-2 text-slate-600 text-sm">Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> total)</span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
