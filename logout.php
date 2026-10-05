<?php
require_once __DIR__ . '/php/RequestGuard.php';
require_once __DIR__ . '/php/goCRMAPISettings.php';
require_once __DIR__ . '/php/ApiClient.php';

$account = $_SESSION;
$_SESSION = array();
if (!session_destroy()) \creamy\Security::deny(503, 'Session could not be closed.');
$cookie = session_get_cookie_params();
setcookie(session_name(), '', array('expires' => time() - 3600, 'path' => $cookie['path'],
    'domain' => $cookie['domain'], 'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax'));

// Logging or an optional chat outage must not prevent local sign-out.
try {
    \creamy\ApiClient::post(gourl . '/goAdminLogs/goAPI.php', array('goUser' => goUser, 'goPass' => goPass,
        'goAction' => 'goLogActions', 'action' => 'LOGOUT', 'user' => $account['user'],
        'user_group' => $account['usergroup'] ?? '', 'details' => 'User logged out',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''), 2);
} catch (Throwable $exception) { error_log('GOautodial logout logging unavailable.'); }

if (ROCKETCHAT_ENABLE === 'y' && !empty($account['gad_authToken']) && !empty($account['gad_userID'])) {
    try {
        $parts = parse_url(ROCKETCHAT_URL);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) throw new RuntimeException();
        $curl = curl_init(rtrim(ROCKETCHAT_URL, '/') . '/api/v1/logout');
        curl_setopt_array($curl, array(CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array('X-Auth-Token: ' . $account['gad_authToken'], 'X-User-Id: ' . $account['gad_userID'])));
        curl_exec($curl);
        curl_close($curl);
    } catch (Throwable $exception) { error_log('GOautodial chat logout unavailable.'); }
}
header('Location: /login.php', true, 303);
exit;
