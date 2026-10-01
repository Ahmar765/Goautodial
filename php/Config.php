<?php
/**
 * Env-driven CRM config. Safe to ship in git — secrets live in /.env only.
 * Copy .env.example -> .env on the server (or run linux/02-deploy-crm.sh).
 */
require_once __DIR__ . '/loadEnv.php';
creamy_load_dotenv(dirname(__DIR__));

$appEnv = creamy_env('APP_ENV', 'local');

define('DB_USERNAME', creamy_env('DB_USER', 'goautodialu'));
define('DB_PASSWORD', creamy_env('DB_PASS', 'goautodialu1234'));
define('DB_HOST', creamy_env('DB_HOST', '127.0.0.1'));
define('DB_NAME', creamy_env('DB_NAME', 'goautodial'));
define('DB_PORT', creamy_env('DB_PORT', '3306'));
define('DB_NAME_ASTERISK', creamy_env('DB_NAME_ASTERISK', 'asterisk'));
define('DB_USERNAME_KAMAILIO', creamy_env('DB_USERNAME_KAMAILIO', creamy_env('DB_USER', 'kamailiou')));
define('DB_PASSWORD_KAMAILIO', creamy_env('DB_PASSWORD_KAMAILIO', creamy_env('DB_PASS', 'kamailiou1234')));
define('DB_HOST_KAMAILIO', creamy_env('DB_HOST_KAMAILIO', '127.0.0.1'));
define('DB_NAME_KAMAILIO', creamy_env('DB_NAME_KAMAILIO', 'kamailio'));
define('DB_PORT_KAMAILIO', creamy_env('DB_PORT_KAMAILIO', '3306'));

define('CRM_ADMIN_EMAIL', creamy_env('CRM_ADMIN_EMAIL', 'admin@localhost.com'));

$goApiBase = creamy_env('GO_API_BASE_URL', creamy_env('GO_API_URL', ''));
if ($goApiBase !== '') {
	define('GO_API_BASE_URL', rtrim(trim($goApiBase), '/'));
}

if (!defined('APP_ENV')) {
	define('APP_ENV', $appEnv);
}

if (!defined('CRM_LOGIN_OFFLINE_FALLBACK')) {
	define('CRM_LOGIN_OFFLINE_FALLBACK', creamy_env_bool('CRM_LOGIN_OFFLINE_FALLBACK', false));
}
if (!defined('CRM_LOGIN_LOCAL_DB_FALLBACK')) {
	$localFallbackDefault = ($appEnv === 'local');
	define('CRM_LOGIN_LOCAL_DB_FALLBACK', creamy_env_bool('CRM_LOGIN_LOCAL_DB_FALLBACK', $localFallbackDefault));
}
