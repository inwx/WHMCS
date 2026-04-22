<?php

use WHMCS\Authentication\CurrentUser;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/inwx.php';

header('Content-Type: application/json');

$currentUser = new CurrentUser();
$admin = $currentUser->admin();
if (!$admin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Admin session required.',
    ]);
    exit;
}

$config = inwx_getModuleConfig();

if (isset($_POST['username']) && $_POST['username'] !== '') {
    $config['Username'] = (string) $_POST['username'];
}
if (isset($_POST['password']) && $_POST['password'] !== '') {
    $config['Password'] = (string) $_POST['password'];
}
if (isset($_POST['testmode'])) {
    $testRaw = strtolower(trim((string) $_POST['testmode']));
    $config['TestMode'] = in_array($testRaw, ['1', 'on', 'yes', 'true'], true);
} else {
    $config['TestMode'] = !empty($config['TestMode']) && $config['TestMode'] !== '0' && $config['TestMode'] !== 'off';
}

if (empty($config['CookieFilePath'])) {
    $config['CookieFilePath'] = '/tmp/inwx_whmcs_cookiefile';
}

echo json_encode(inwx_HealthCheck($config));
