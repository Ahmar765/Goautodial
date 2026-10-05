<?php
// Uses the local fixture server, with dummy credentials and no database.
putenv('APP_ENV=development'); putenv('APP_URL=http://localhost'); putenv('SESSION_DRIVER=files');
putenv('DB_USER=fixture'); putenv('DB_PASS=fixture'); putenv('DB_USERNAME_KAMAILIO=fixture'); putenv('DB_PASSWORD_KAMAILIO=fixture');
putenv('GO_API_URL=http://127.0.0.1:8097/_fixture/backend'); putenv('GO_API_USER=fixture-service'); putenv('GO_API_PASSWORD=fixture-password');
require_once __DIR__ . '/../php/APIHandler.php';
$_SESSION = array('user' => 'fixture', 'userid' => '42', 'userrole' => 3, 'phone_this' => 'fixture-password');
$api = \creamy\APIHandler::getInstance();
$fields = array('goAction' => 'fixture', 'goUser' => 'spoof', 'goPass' => 'spoof',
    'session_user' => 'spoof', 'log_user' => 'spoof', 'responsetype' => 'xml', 'value' => "a&b+c=\"'\\");
$output = $api->API_Request('goAgent', $fields);
if (!is_object($output) || $output->goUser !== 'fixture-service' || $output->goPass !== 'fixture-password'
    || $output->session_user === 'spoof' || $output->log_user === 'spoof' || $output->responsetype !== 'json'
    || $output->value !== $fields['value']) throw new RuntimeException('API identity or form encoding regression.');
$output = $api->API_Upload('goAgent', $fields);
if (!is_object($output) || $output->goUser !== 'fixture-service' || $output->value !== $fields['value']) throw new RuntimeException('Multipart request regression.');
try { $api->API_Request('../admin', array()); throw new RuntimeException('Unsafe API path accepted.'); }
catch (InvalidArgumentException $exception) {}
if (\creamy\ApiClient::post('http://127.0.0.1:8097/missing-fixture', array()) !== null) throw new RuntimeException('HTTP failure accepted.');
putenv('APP_ENV=production');
try { \creamy\ApiClient::post('http://127.0.0.1:8097/', array()); throw new LogicException('HTTP accepted in production.'); }
catch (RuntimeException $exception) {}
session_write_close();
echo "API identity, encoding, upload and transport checks passed.\n";
