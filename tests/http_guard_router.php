<?php
// Test fixture only: use the PHP built-in server bound to 127.0.0.1.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') { http_response_code(403); exit; }
putenv('APP_ENV=development'); putenv('APP_URL=http://127.0.0.1:8097'); putenv('SESSION_DRIVER=files');
require_once __DIR__ . '/../php/SessionHandler.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/_fixture/login') {
    new \creamy\SessionHandler();
    $role = (int) ($_GET['role'] ?? 3);
    $_SESSION = array('user' => 'fixture', 'userid' => '42', 'userrole' => $role, 'phone_this' => 'fixture-password',
        'csrf_token' => str_repeat('a', 64));
    header('Content-Type: application/json'); echo json_encode(array('token' => $_SESSION['csrf_token'])); exit;
}
if ($path === '/_fixture/backend/goAgent/goAPI.php') {
    header('Content-Type: application/json'); echo json_encode($_POST); exit;
}
if ($path === '/_fixture/production') {
    putenv('APP_ENV=production'); putenv('APP_URL=https://crm.example.com');
    if (isset($_GET['https'])) $_SERVER['HTTPS'] = 'on';
    $path = '/login.php';
}
$root = realpath(__DIR__ . '/..');
$target = realpath($root . $path);
if (!$target || stripos($target, $root . DIRECTORY_SEPARATOR) !== 0 || !str_ends_with($target, '.php')) { http_response_code(404); exit; }
$_SERVER['SCRIPT_FILENAME'] = $target;
require __DIR__ . '/../php/RequestGuard.php';
if (isset($_GET['html'])) {
    header('Content-Type: text/html'); echo '<html><head><title>Fixture</title></head><body><form method="post"></form></body></html>';
} else {
    header('Content-Type: application/json'); echo json_encode(array('accepted' => true, 'sender' => $_POST['fromuserid'] ?? null));
}
