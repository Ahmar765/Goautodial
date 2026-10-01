<?php
/**
 * goAPIv2 settings. Safe to ship in git — override via /.env (GO_API_*).
 */
require_once __DIR__ . '/loadEnv.php';
creamy_load_dotenv(dirname(__DIR__));
@include_once __DIR__ . '/Config.php';

$goApiBase = '';
if (defined('GO_API_BASE_URL') && trim((string) GO_API_BASE_URL) !== '') {
	$goApiBase = GO_API_BASE_URL;
}
if ($goApiBase === '') {
	$fromEnv = creamy_env('GO_API_BASE_URL', creamy_env('GO_API_URL', ''));
	if ($fromEnv !== '') {
		$goApiBase = $fromEnv;
	}
}
if ($goApiBase === '') {
	$goApiBase = 'http://127.0.0.1/goAPIv2';
}
define('gourl', rtrim(trim($goApiBase), '/'));
define('goUser', creamy_env('GO_API_USER', 'goAPI'));
define('goPass', creamy_env('GO_API_PASS', 'KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK'));
define('responsetype', 'json');
