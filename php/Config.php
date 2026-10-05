<?php
require_once __DIR__ . '/RuntimeConfig.php';
// Credentials are supplied by environment or protected external configuration.
define('DB_HOST', \creamy\RuntimeConfig::value('DB_HOST', '127.0.0.1'));
define('DB_PORT', \creamy\RuntimeConfig::value('DB_PORT', '3306'));
define('DB_NAME', \creamy\RuntimeConfig::value('DB_NAME', 'goautodial'));
define('DB_USERNAME', \creamy\RuntimeConfig::required('DB_USER'));
define('DB_PASSWORD', \creamy\RuntimeConfig::required('DB_PASS'));
define('DB_HOST_ASTERISK', \creamy\RuntimeConfig::value('DB_HOST_ASTERISK', DB_HOST));
define('DB_PORT_ASTERISK', \creamy\RuntimeConfig::value('DB_PORT_ASTERISK', DB_PORT));
define('DB_NAME_ASTERISK', \creamy\RuntimeConfig::value('DB_NAME_ASTERISK', 'asterisk'));
define('DB_USERNAME_ASTERISK', \creamy\RuntimeConfig::value('DB_USER_ASTERISK', DB_USERNAME));
define('DB_PASSWORD_ASTERISK', \creamy\RuntimeConfig::value('DB_PASS_ASTERISK', DB_PASSWORD));
define('DB_HOST_KAMAILIO', \creamy\RuntimeConfig::value('DB_HOST_KAMAILIO', DB_HOST));
define('DB_PORT_KAMAILIO', \creamy\RuntimeConfig::value('DB_PORT_KAMAILIO', DB_PORT));
define('DB_NAME_KAMAILIO', \creamy\RuntimeConfig::value('DB_NAME_KAMAILIO', 'kamailio'));
define('DB_USERNAME_KAMAILIO', \creamy\RuntimeConfig::required('DB_USERNAME_KAMAILIO'));
define('DB_PASSWORD_KAMAILIO', \creamy\RuntimeConfig::required('DB_PASSWORD_KAMAILIO'));
define('CRM_ADMIN_EMAIL', \creamy\RuntimeConfig::value('CRM_ADMIN_EMAIL'));
