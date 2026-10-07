<?php
require_once __DIR__ . '/RuntimeConfig.php';
require_once __DIR__ . '/Security.php';
define('gourl', \creamy\RuntimeConfig::url('GO_API_URL'));

$apiAccount = PHP_SAPI === 'cli' ? array(
    \creamy\RuntimeConfig::required('GO_API_USER'), \creamy\RuntimeConfig::required('GO_API_PASSWORD'))
    : (\creamy\Security::authenticated($_SESSION ?? array()) ? array($_SESSION['user'], $_SESSION['phone_this'] ?? '') : array('', ''));
define('goUser', $apiAccount[0]);
define('goPass', $apiAccount[1]);
unset($apiAccount);
define('responsetype', 'json');
