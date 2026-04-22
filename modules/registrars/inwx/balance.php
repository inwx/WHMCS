<?php

use WHMCS\Authentication\CurrentUser;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/inwx.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUser = new CurrentUser();
$admin = $currentUser->admin();
if (!$admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$config = inwx_getModuleConfig();
$config['TestMode'] = !empty($config['TestMode']) && $config['TestMode'] !== '0' && $config['TestMode'] !== 'off';
if (empty($config['CookieFilePath'])) {
    $config['CookieFilePath'] = '/tmp/inwx_whmcs_cookiefile';
}

echo json_encode(inwx_GetAccountBalance($config));
