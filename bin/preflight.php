<?php
// Read-only deployment checks. Never prints setting values or remote response bodies.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../php/RuntimeConfig.php';
require_once __DIR__ . '/../php/SessionCipher.php';
require_once __DIR__ . '/../php/ApiClient.php';

$failures = 0;
function reportCheck($label, $check) {
    global $failures;
    try { if (!$check()) throw new RuntimeException(); echo 'PASS ' . $label . PHP_EOL; }
    catch (Throwable $exception) { $failures++; echo 'FAIL ' . $label . PHP_EOL; }
}
reportCheck('PHP 8.3 or newer', function () { return PHP_VERSION_ID >= 80300; });
reportCheck('Production environment', function () { return \creamy\RuntimeConfig::production(); });
foreach (array('mysqli', 'curl', 'openssl', 'gd', 'mbstring', 'intl', 'xml', 'zip', 'bcmath') as $extension) {
    reportCheck('Extension ' . $extension, function () use ($extension) { return extension_loaded($extension); });
}
reportCheck('HTTPS application URL at domain root', function () {
    $url = \creamy\RuntimeConfig::url('APP_URL');
    return (parse_url($url, PHP_URL_PATH) ?? '') === '';
});
reportCheck('HTTPS backend API URL', function () { return \creamy\RuntimeConfig::url('GO_API_URL') !== ''; });
foreach (array('GO_API_USER', 'GO_API_PASSWORD', 'DB_USER', 'DB_PASS', 'DB_USER_ASTERISK',
    'DB_PASS_ASTERISK', 'DB_USERNAME_KAMAILIO', 'DB_PASSWORD_KAMAILIO') as $key) {
    reportCheck('Setting ' . $key, function () use ($key) { return \creamy\RuntimeConfig::required($key) !== ''; });
}
reportCheck('Database session driver', function () { return \creamy\RuntimeConfig::value('SESSION_DRIVER', 'database') === 'database'; });
reportCheck('Installed Composer mail dependency', function () {
    require __DIR__ . '/../php/MailerBootstrap.php'; return true;
});
reportCheck('Session encryption key and round trip', function () {
    $cipher = new \creamy\SessionCipher(); return $cipher->decrypt($cipher->encrypt('preflight')) === 'preflight';
});
foreach (array('uploads', 'img/avatars') as $directory) {
    reportCheck('Writable ' . $directory, function () use ($directory) { return is_writable(__DIR__ . '/../' . $directory); });
}

$offline = in_array('--offline', $argv, true);
if (!$offline) {
    $databases = array(
        array('GOautodial', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'goautodial',
            array('go_sessions', 'settings', 'go_avatars', 'messages_inbox', 'messages_outbox')),
        array('Asterisk', 'DB_HOST_ASTERISK', 'DB_PORT_ASTERISK', 'DB_NAME_ASTERISK', 'DB_USER_ASTERISK', 'DB_PASS_ASTERISK', 'asterisk',
            array('vicidial_users', 'vicidial_campaigns', 'vicidial_list', 'system_settings', 'phones')),
        array('Kamailio', 'DB_HOST_KAMAILIO', 'DB_PORT_KAMAILIO', 'DB_NAME_KAMAILIO', 'DB_USERNAME_KAMAILIO', 'DB_PASSWORD_KAMAILIO', 'kamailio',
            array('subscriber', 'location'))
    );
    foreach ($databases as $entry) {
        reportCheck($entry[0] . ' connection and core tables', function () use ($entry) {
            $db = mysqli_init(); $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $host = \creamy\RuntimeConfig::value($entry[1], \creamy\RuntimeConfig::value('DB_HOST', '127.0.0.1'));
            $db->real_connect($host, \creamy\RuntimeConfig::required($entry[4]), \creamy\RuntimeConfig::required($entry[5]),
                \creamy\RuntimeConfig::value($entry[3], $entry[6]), (int) \creamy\RuntimeConfig::value($entry[2], '3306'));
            foreach ($entry[7] as $table) $db->query('SELECT 1 FROM `' . $table . '` LIMIT 0')->free();
            $db->close(); return true;
        });
    }
    reportCheck('Backend HTTPS transport and read-only API request', function () {
        $body = \creamy\ApiClient::post(\creamy\RuntimeConfig::url('GO_API_URL') . '/goUsers/goAPI.php',
            array('goUser' => \creamy\RuntimeConfig::required('GO_API_USER'), 'goPass' => \creamy\RuntimeConfig::required('GO_API_PASSWORD'),
                'goAction' => 'goGetAllUsers', 'responsetype' => 'json'), 15);
        $response = is_string($body) ? json_decode($body) : null;
        return is_object($response) && ($response->result ?? '') === 'success';
    });
} else {
    echo 'SKIP database/backend connections (--offline); not a launch readiness check.' . PHP_EOL;
}
echo ($failures ? $failures . ' failed checks.' : 'Preflight checks passed; complete staging call and permission tests before launch.') . PHP_EOL;
exit($failures ? 1 : 0);
