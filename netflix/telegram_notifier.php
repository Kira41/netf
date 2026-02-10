<?php

declare(strict_types=1);

/**
 * Send safe admin notifications to Telegram.
 *
 * This helper intentionally avoids collecting or sending sensitive user data.
 */
function sendAdminTelegramNotification(
    string $botToken,
    string $chatId,
    string $event,
    array $context = []
): array {
    if ($botToken === '' || $chatId === '') {
        return [
            'ok' => false,
            'error' => 'Missing Telegram bot token or chat id.',
        ];
    }

    $safeContext = [];
    foreach ($context as $key => $value) {
        $normalizedKey = trim((string) $key);

        // Strict deny list for sensitive keys.
        if (preg_match('/pass|password|pwd|token|secret|cookie|session|otp|card|cvv|pin/i', $normalizedKey)) {
            continue;
        }

        $safeContext[$normalizedKey] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    $lines = [
        "🔔 Admin Notification",
        "Event: {$event}",
        "Time (UTC): " . gmdate('Y-m-d H:i:s'),
    ];

    foreach ($safeContext as $key => $value) {
        $cleanValue = trim($value);
        if ($cleanValue === '') {
            continue;
        }
        $lines[] = "{$key}: {$cleanValue}";
    }

    $message = implode("\n", $lines);
    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    $ch = curl_init($apiUrl);
    if ($ch === false) {
        return [
            'ok' => false,
            'error' => 'Unable to initialize cURL.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'error' => $curlError !== '' ? $curlError : 'Unknown cURL error.',
        ];
    }

    $decoded = json_decode($response, true);
    $telegramOk = is_array($decoded) && !empty($decoded['ok']);

    return [
        'ok' => $telegramOk,
        'status_code' => $statusCode,
        'response' => $decoded ?? $response,
    ];
}
