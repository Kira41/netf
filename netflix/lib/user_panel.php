<?php

function panelStorageDir()
{
    return __DIR__ . '/../data';
}

function panelDefaults()
{
    return [
        'mode' => 'default',
        'custom_url' => '',
        'instruction' => 'stay_wait',
        'instruction_token' => time(),
        'chat_enabled' => false
    ];
}

function ensurePanelStorage()
{
    $base = panelStorageDir();
    $dirs = [$base, $base . '/chats', $base . '/states'];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

function panelCurrentUserId()
{
    if (empty($_SESSION['panel_user_id'])) {
        $_SESSION['panel_user_id'] = bin2hex(random_bytes(4));
    }

    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_SESSION['panel_user_id']);
}

function panelUsersFile()
{
    return panelStorageDir() . '/users.txt';
}

function panelStateFile($userId)
{
    return panelStorageDir() . '/states/' . $userId . '.txt';
}

function panelChatFile($userId)
{
    return panelStorageDir() . '/chats/' . $userId . '.txt';
}

function panelReadUsers()
{
    ensurePanelStorage();
    $file = panelUsersFile();
    if (!file_exists($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $users = [];

    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        if (count($parts) < 6) {
            continue;
        }

        [$id, $createdAt, $lastSeen, $ip, $name, $page] = $parts;
        $users[$id] = [
            'id' => $id,
            'created_at' => (int) $createdAt,
            'last_seen' => (int) $lastSeen,
            'ip' => $ip,
            'name' => $name,
            'page' => $page,
        ];
    }

    uasort($users, function ($a, $b) {
        return $b['last_seen'] <=> $a['last_seen'];
    });

    return $users;
}

function panelWriteUsers($users)
{
    ensurePanelStorage();
    $rows = [];
    foreach ($users as $user) {
        $rows[] = implode("\t", [
            $user['id'],
            (int) $user['created_at'],
            (int) $user['last_seen'],
            str_replace(["\n", "\r", "\t"], ' ', (string) $user['ip']),
            str_replace(["\n", "\r", "\t"], ' ', (string) $user['name']),
            str_replace(["\n", "\r", "\t"], ' ', (string) $user['page']),
        ]);
    }

    file_put_contents(panelUsersFile(), implode("\n", $rows) . (count($rows) ? "\n" : ''));
}

function panelTouchUser($name = '', $page = '')
{
    ensurePanelStorage();
    $id = panelCurrentUserId();
    $users = panelReadUsers();

    $existing = $users[$id] ?? [
        'id' => $id,
        'created_at' => time(),
        'last_seen' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'name' => '',
        'page' => '',
    ];

    $existing['last_seen'] = time();
    $existing['ip'] = $_SERVER['REMOTE_ADDR'] ?? $existing['ip'];

    if ($name !== '') {
        $existing['name'] = $name;
    }

    if ($page !== '') {
        $existing['page'] = $page;
    }

    $users[$id] = $existing;
    panelWriteUsers($users);
}

function panelLoadState($userId)
{
    $defaults = panelDefaults();
    $file = panelStateFile($userId);

    if (!file_exists($file)) {
        return $defaults;
    }

    $state = $defaults;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === 'chat_enabled') {
            $state[$key] = $value === '1';
        } elseif ($key === 'instruction_token') {
            $state[$key] = (int) $value;
        } else {
            $state[$key] = $value;
        }
    }

    return $state;
}

function panelSaveState($userId, $mode, $customUrl, $instruction, $chatEnabled)
{
    ensurePanelStorage();
    $payload = [
        'mode' => $mode,
        'custom_url' => $mode === 'redirect_custom' ? trim($customUrl) : '',
        'instruction' => $instruction,
        'instruction_token' => time(),
        'chat_enabled' => $chatEnabled ? '1' : '0',
    ];

    $lines = [];
    foreach ($payload as $key => $value) {
        $lines[] = $key . '=' . str_replace(["\n", "\r"], ' ', (string) $value);
    }

    file_put_contents(panelStateFile($userId), implode("\n", $lines) . "\n");
}

function panelLoadChat($userId)
{
    $file = panelChatFile($userId);
    if (!file_exists($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $messages = [];

    foreach ($lines as $line) {
        $parts = explode("\t", $line, 3);
        if (count($parts) < 3) {
            continue;
        }

        [$timestamp, $sender, $encoded] = $parts;
        $messages[] = [
            'timestamp' => (int) $timestamp,
            'sender' => $sender === 'admin' ? 'admin' : 'user',
            'message' => base64_decode($encoded) ?: '',
        ];
    }

    return $messages;
}

function panelAppendChat($userId, $sender, $message)
{
    ensurePanelStorage();
    $senderValue = $sender === 'admin' ? 'admin' : 'user';
    $line = implode("\t", [time(), $senderValue, base64_encode($message)]);
    file_put_contents(panelChatFile($userId), $line . "\n", FILE_APPEND);
}
