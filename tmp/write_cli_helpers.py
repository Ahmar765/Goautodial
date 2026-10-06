from pathlib import Path
root = Path(__file__).resolve().parent.parent
(root / 'setup_xampp_db.php').write_text('''<?php
require_once __DIR__ . '/php/RequestGuard.php';
// The former Windows helper used root without a password and reset tables.
// The guarded initializer now reads explicit DB_* settings and requires opt-in.
require __DIR__ . '/init_db.php';
''', encoding='utf-8')
(root / 'bin/pass_hasher.php').write_text('''<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../php/LegacyPassword.php';
$options = getopt('', array('pass:', 'cost:', 'salt:', 'help'));
if (isset($options['help']) || !isset($options['salt'])) {
    echo "Usage: php bin/pass_hasher.php --salt=<backend pass_key> --cost=<backend pass_cost>\\n";
    echo "Read the password from standard input (recommended), or supply --pass=<password>.\\n";
    exit(isset($options['help']) ? 0 : 1);
}
$password = $options['pass'] ?? rtrim((string) fgets(STDIN), "\\r\\n");
try {
    if ($password === '') throw new InvalidArgumentException();
    echo \\creamy\\LegacyPassword::hash($password, $options['cost'] ?? 12, $options['salt']) . "\\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Invalid password, cost or backend salt.\\n");
    exit(1);
}
''', encoding='utf-8')
(root / 'logout.php').write_text('''<?php
require_once __DIR__ . '/php/RequestGuard.php';
require_once __DIR__ . '/php/goCRMAPISettings.php';
require_once __DIR__ . '/php/ApiClient.php';

$account = $_SESSION;
$_SESSION = array();
if (!session_destroy()) \\creamy\\Security::deny(503, 'Session could not be closed.');
$cookie = session_get_cookie_params();
setcookie(session_name(), '', array('expires' => time() - 3600, 'path' => $cookie['path'],
    'domain' => $cookie['domain'], 'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax'));

// Logging or an optional chat outage must not prevent local sign-out.
try {
    \\creamy\\ApiClient::post(gourl . '/goAdminLogs/goAPI.php', array('goUser' => goUser, 'goPass' => goPass,
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
''', encoding='utf-8')
print('Updated development DB helper, password hasher and logout.')
