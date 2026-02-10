<?php
session_start();
require_once __DIR__ . '/lib/user_panel.php';

$page = trim((string)($_POST['page'] ?? ''));
$page = preg_replace('/[^a-zA-Z0-9._ -]/', '', $page);

if ($page === '') {
    http_response_code(400);
    echo 'missing page';
    exit;
}

panelTouchUser('', $page . ' ( out )');
http_response_code(204);
