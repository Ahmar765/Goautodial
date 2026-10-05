<?php
// Deliberately simulates responses; it does not implement or place calls.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') { http_response_code(403); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!in_array($path, array('/goAgent/goAPI.php', '/goBarging/goAPI.php'), true)) { http_response_code(404); exit; }
header('Content-Type: application/json');
if (($_POST['fixture_mode'] ?? '') === 'http_failure') { http_response_code(500); echo '{"error":"fixture failure"}'; exit; }
if (($_POST['fixture_mode'] ?? '') === 'invalid_json') { echo '<html>fixture error</html>'; exit; }
if (($_POST['goAction'] ?? '') === 'goGetLabels') { echo '{"labels":{"label_first_name":"First name"},"disable_alter_custphone":"N"}'; exit; }
if (($_POST['goAction'] ?? '') === 'goGetLoginInfo') {
    echo json_encode(array('result' => 'success', 'data' => array(
        'default_settings' => array('timezone' => 'UTC', 'statuses' => array(), 'pause_codes' => array()),
        'user_info' => array('user' => 'fixture-agent', 'phone_login' => '1001', 'phone_pass' => "fixture'phone", 'pass' => 'fixture-account-password'),
        'phone_info' => array('server_ip' => 'pbx.example.test'), 'system_info' => array('default_local_gmt' => '0'),
        'country_codes' => array(), 'is_logged_in' => false)));
    exit;
}
echo json_encode(array('result' => 'success', 'fixture' => true, 'fields' => $_POST));
