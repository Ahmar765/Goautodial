<?php
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') { http_response_code(403); exit; }
require __DIR__ . '/calling_fixture_env.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/_fixture/login') {
    require_once __DIR__ . '/../php/SessionHandler.php';
    new \creamy\SessionHandler();
    $_SESSION = array('user' => 'fixture-agent', 'userid' => '42', 'userrole' => (int) ($_GET['role'] ?? 3),
        'username' => 'Fixture agent', 'phone_this' => 'fixture-account-password', 'phone_login' => '1001',
        'phone_pass' => 'fixture-phone-password', 'password_hash' => 'fixture-account-hash',
        'campaign_id' => '', 'csrf_token' => str_repeat('a', 64));
    header('Content-Type: application/json'); echo json_encode(array('token' => $_SESSION['csrf_token'])); exit;
}
if (!in_array($path, array('/php/AgentAPI.php', '/php/MonitorAPI.php', '/modules/GOagent/jsSIP.php'), true)) { http_response_code(404); exit; }
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/..' . $path);
require $_SERVER['SCRIPT_FILENAME'];
