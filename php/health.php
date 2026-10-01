<?php
/**
 * Lightweight health check for deploy/smoke tests.
 * Returns JSON: db, goapi, env flags. Does not print secrets.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/loadEnv.php';
creamy_load_dotenv(dirname(__DIR__));
@include_once __DIR__ . '/Config.php';
@include_once __DIR__ . '/goCRMAPISettings.php';

$result = array(
	'ok' => true,
	'app_env' => defined('APP_ENV') ? APP_ENV : creamy_env('APP_ENV', 'unknown'),
	'time' => gmdate('c'),
	'checks' => array(),
);

// DB
$dbOk = false;
$dbErr = '';
try {
	$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
	$user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
	$pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
	$name = defined('DB_NAME') ? DB_NAME : 'goautodial';
	$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
	$mysqli = @new mysqli($host, $user, $pass, $name, $port);
	if ($mysqli->connect_errno) {
		$dbErr = $mysqli->connect_error;
	} else {
		$dbOk = true;
		$mysqli->close();
	}
} catch (Throwable $e) {
	$dbErr = $e->getMessage();
}
$result['checks']['database'] = array('ok' => $dbOk, 'error' => $dbOk ? null : $dbErr);
if (!$dbOk) {
	$result['ok'] = false;
}

// goAPI
$apiOk = false;
$apiPreview = '';
$gourl = defined('gourl') ? gourl : creamy_env('GO_API_BASE_URL', 'http://127.0.0.1/goAPIv2');
$goUser = defined('goUser') ? goUser : 'goAPI';
$goPass = defined('goPass') ? goPass : '';
$url = rtrim($gourl, '/') . '/goCampaigns/goAPI.php';
$post = http_build_query(array(
	'goUser' => $goUser,
	'goPass' => $goPass,
	'responsetype' => 'json',
	'goAction' => 'goGetAllCampaigns',
));
$ctx = stream_context_create(array(
	'http' => array(
		'method' => 'POST',
		'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
		'content' => $post,
		'timeout' => 8,
		'ignore_errors' => true,
	),
));
$body = @file_get_contents($url, false, $ctx);
if (is_string($body) && $body !== '') {
	$apiPreview = substr($body, 0, 120);
	if (stripos($body, 'success') !== false || stripos($body, 'campaign') !== false || stripos($body, 'result') !== false) {
		$apiOk = true;
	}
}
$result['checks']['goapi'] = array(
	'ok' => $apiOk,
	'url' => $url,
	'preview' => $apiPreview,
);
if (!$apiOk) {
	$result['ok'] = false;
}

$localFallback = defined('CRM_LOGIN_LOCAL_DB_FALLBACK') ? (bool) CRM_LOGIN_LOCAL_DB_FALLBACK : false;
$result['checks']['production_flags'] = array(
	'ok' => !($result['app_env'] === 'production' && $localFallback),
	'crm_login_local_db_fallback' => $localFallback,
);
if ($result['app_env'] === 'production' && $localFallback) {
	$result['ok'] = false;
}

http_response_code($result['ok'] ? 200 : 503);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
