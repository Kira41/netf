<?php
session_start();
require_once __DIR__ . '/lib/user_panel.php';

header('Content-Type: application/json');

function sanitizeUserId($value)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value);
}

$action = $_GET['action'] ?? 'fetch';
$isAdmin = !empty($_SESSION['is_admin']);
$currentUserId = panelCurrentUserId();
$requestedUserId = sanitizeUserId($_GET['user_id'] ?? $_POST['user_id'] ?? '');
$targetUserId = $isAdmin ? ($requestedUserId ?: '') : $currentUserId;

if ($isAdmin && $targetUserId === '') {
    echo json_encode([
        'chat_enabled' => false,
        'messages' => [],
        'error' => 'Missing user_id.'
    ]);
    exit;
}

if ($targetUserId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user id.']);
    exit;
}

$state = panelLoadState($targetUserId);
$chatEnabled = !empty($state['chat_enabled']);

if ($action === 'send') {
    $sender = $_POST['sender'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if ($sender === 'admin' && !$isAdmin) {
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

    panelAppendChat($targetUserId, $sender === 'admin' ? 'admin' : 'user', $message);

    echo json_encode(['success' => true]);
    exit;
}

$messages = panelLoadChat($targetUserId);
$formatted = array_map(function ($entry) {
    $entry['formatted'] = date('Y-m-d H:i:s', $entry['timestamp']);
    return $entry;
}, $messages);

echo json_encode([
    'chat_enabled' => $chatEnabled,
    'messages' => $formatted,
    'user_id' => $targetUserId,
]);
