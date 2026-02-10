<?php

declare(strict_types=1);

/**
 * Example safe admin-only notification endpoint.
 *
 * Configure TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in your server environment.
 * This endpoint never sends passwords or form contents.
 */

require_once __DIR__ . '/telegram_notifier.php';

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$chatId = getenv('TELEGRAM_CHAT_ID') ?: '';

$event = trim((string) ($_GET['event'] ?? 'manual_admin_check'));
if ($event === '') {
    $event = 'manual_admin_check';
}

$result = sendAdminTelegramNotification(
    $botToken,
    $chatId,
    $event,
    [
        'source' => 'admin_notify.php',
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 180),
    ]
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
