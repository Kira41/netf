<?php
session_start();
require_once __DIR__ . '/lib/user_panel.php';

$userId = panelCurrentUserId();
$state = panelLoadState($userId);

header('Content-Type: application/json');
echo json_encode([
    'instruction' => $state['instruction'],
    'instruction_token' => $state['instruction_token'],
    'mode' => $state['mode'],
    'chat_enabled' => !empty($state['chat_enabled']),
    'custom_url' => $state['custom_url'] ?? ''
]);
