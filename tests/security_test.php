<?php
// No live credentials or database are used by these regression checks.
putenv('APP_ENV=development');
putenv('APP_URL=http://localhost');
putenv('SESSION_DRIVER=files');
putenv('SESSION_ENCRYPTION_KEY=' . bin2hex(random_bytes(32)));
require_once __DIR__ . '/../php/Authentication.php';
require_once __DIR__ . '/../php/Security.php';
require_once __DIR__ . '/../php/SessionCipher.php';
require_once __DIR__ . '/../php/SessionHandler.php';
require_once __DIR__ . '/../php/AvatarImage.php';
require_once __DIR__ . '/../php/Permissions.php';
require_once __DIR__ . '/../php/CRMUtils.php';

$count = 0;
function check($condition, $message) {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $count++;
}
$user = array('result' => 'success', 'active' => 'Y', 'user_id' => '42', 'user' => 'agent42',
    'full_name' => 'Test Agent', 'user_level' => 1, 'pass' => 'test-password', 'bcrypt' => 0,
    'user_group' => 'AGENTS', 'phone_login' => '1042');
$normalize = function ($fixture, $password = 'test-password', $identity = 'agent42') {
    return \creamy\Authentication::userFromResponse(json_encode($fixture), $password, $identity);
};
check($normalize($user)['role'] === 3, 'Valid agent login');
check($normalize($user, 'wrong') === null, 'Wrong password rejected');
check(\creamy\Authentication::userFromResponse(null, 'anything', 'unknown') === null, 'Backend outage rejected');
check(\creamy\Authentication::userFromResponse('<html>error</html>', 'anything', 'unknown') === null, 'Non-JSON response rejected');
foreach (array(array('result' => 'error'), array('active' => 'N'), array('user_level' => 0),
    array('user_level' => 99), array('user_level' => '9.9'), array('user_level' => array(9)), array('active' => array('Y')),
    array('user_id' => array('42')), array('pass' => array('test-password'))) as $override) {
    check($normalize(array_replace($user, $override)) === null, 'Malformed or disabled backend user rejected');
}
check($normalize(array_replace($user, array('user_level' => 9)))['role'] === 0, 'Backend administrator mapped');
check($normalize($user, 'test-password', 'test@example.com')['user'] === 'agent42', 'Email resolves canonical account');
$upstreamUser = $user; unset($upstreamUser['user']); $upstreamUser['userno'] = 'agent42';
check($normalize($upstreamUser, 'test-password', 'test@example.com')['user'] === 'agent42', 'Upstream userno account field supported');
$withoutName = $user;
unset($withoutName['user']);
check($normalize($withoutName, 'test-password', 'test@example.com') === null, 'Email without canonical account rejected');

$salt = 'DIapgKfF5fQWEYMY';
$hash = \creamy\LegacyPassword::hash('bcrypt-test-password', 4, $salt);
$fullHash = crypt('bcrypt-test-password', '$2y$04$' . substr(base64_encode($salt), 0, 22));
check(substr($fullHash, 29) === $hash, 'Legacy truncated hash matches bcrypt');
check(password_verify('bcrypt-test-password', $fullHash), 'Legacy hash compatible with standard bcrypt verification');
$bcryptUser = array_replace($user, array('pass' => $hash, 'bcrypt' => 1, 'cost' => 4, 'salt' => $salt));
check($normalize($bcryptUser, 'bcrypt-test-password') !== null, 'Salted backend login works on PHP 8');
check($normalize($bcryptUser, 'wrong-password') === null, 'Salted backend wrong password rejected');
check($normalize(array_replace($bcryptUser, array('cost' => 0)), 'bcrypt-test-password') === null, 'Invalid cost denied');

$cipher = new \creamy\SessionCipher();
$encoded = $cipher->encrypt('user|s:7:"agent42";');
check($cipher->decrypt($encoded) === 'user|s:7:"agent42";', 'Session round trip');
check($cipher->encrypt('same') !== $cipher->encrypt('same'), 'Session encryption uses fresh nonces');
$raw = base64_decode(substr($encoded, 3));
$raw[20] = chr(ord($raw[20]) ^ 1);
check($cipher->decrypt('v2:' . base64_encode($raw)) === '', 'Modified session authentication tag rejected');
check($cipher->decrypt('old-dev-session') === '', 'Old session invalidated');
check($cipher->decrypt('v2:invalid') === '', 'Malformed encrypted session rejected');

$session = array('user' => 'agent42', 'userid' => '42', 'userrole' => 3, 'csrf_token' => str_repeat('a', 64));
check(\creamy\Security::authenticated($session), 'Authenticated session recognized');
check(!\creamy\Security::authenticated(array('userrole' => 0)), 'Anonymous admin role is not authentication');
check(!\creamy\Security::authenticated(array_replace($session, array('userrole' => '0'))), 'Invalid role representation rejected');
check(\creamy\Security::validCsrf($session, str_repeat('a', 64)), 'Valid CSRF token');
check(!\creamy\Security::validCsrf($session, null), 'Missing CSRF token rejected');
check(!\creamy\Security::validCsrf($session, array('bad')), 'Array token rejected');
check(!\creamy\Security::validCsrf($session, str_repeat('b', 64)), 'Wrong token rejected');
check(!in_array(3, \creamy\Security::allowedRoles('php/AddUser.php'), true), 'Agents cannot create users');
check(!in_array(1, \creamy\Security::allowedRoles('php/ModifySettings.php'), true), 'Supervisors cannot change system settings');
check(in_array(3, \creamy\Security::allowedRoles('php/AgentAPI.php'), true), 'Agents can use agent API');
check(!in_array(3, \creamy\Security::allowedRoles('php/MonitorAPI.php'), true), 'Agents cannot monitor others');
$permissionResponse = (object) array('result' => 'success', 'data' => (object) array('permissions' =>
    '{"dashboard":{"dashboard_display":"N"},"servers":{"servers_read":"N"}}'));
check(\creamy\Permissions::forResponse($permissionResponse, 'dashboard')->dashboard_display === 'N', 'Single group permission respected');
check(\creamy\Permissions::forResponse($permissionResponse, 'dashboard,servers')->servers->servers_read === 'N', 'Multiple permission groups respected');
foreach (array(null, (object) array('result' => 'error'), (object) array('result' => 'success', 'data' => (object) array('permissions' => 'bad'))) as $badPermissions) {
    try { \creamy\Permissions::forResponse($badPermissions, 'dashboard'); check(false, 'Permissions outage cannot grant access'); }
    catch (RuntimeException $exception) { check(true, 'Unavailable permissions fail closed'); }
}
try { \creamy\Permissions::forResponse($permissionResponse, 'campaign'); check(false, 'Missing permission cannot grant access'); }
catch (RuntimeException $exception) { check(true, 'Missing group fails closed'); }

$_SERVER = array('REMOTE_ADDR' => '203.0.113.1', 'HTTP_X_FORWARDED_PROTO' => 'https');
check(!\creamy\RuntimeConfig::secureRequest(), 'Untrusted forwarded HTTPS ignored');
putenv('TRUSTED_PROXIES=203.0.113.1');
check(\creamy\RuntimeConfig::secureRequest(), 'Explicitly trusted HTTPS proxy recognized');
putenv('TRUSTED_PROXIES=');

check(\creamy\AvatarImage::decode(base64_encode('<svg onload="alert(1)"></svg>')) === null, 'SVG avatar rejected');
check(\creamy\AvatarImage::decode(base64_encode('<?php echo "bad";')) === null, 'Executable avatar rejected');
check(\creamy\AvatarImage::decode(str_repeat('a', 2800001)) === null, 'Oversized avatar rejected');
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aKX0AAAAASUVORK5CYII=';
check(\creamy\AvatarImage::decode($png)['type'] === 'image/png', 'PNG detected without trusting client MIME');
$safePath = \creamy\CRMUtils::generateUploadRelativePath('../../php/Config.php', false);
check(preg_match('~^uploads/\d{4}/\d{2}/[a-f0-9]{40}\.dat$~', $safePath) === 1, 'Traversal and executable upload names neutralized');
$safePath = \creamy\CRMUtils::generateUploadRelativePath('C:\\fakepath\\report.pdf', false);
check(preg_match('~^uploads/\d{4}/\d{2}/[a-f0-9]{40}\.pdf$~', $safePath) === 1, 'Allowed attachment extension preserved without supplied path');

class SessionStoreFixture {
    public $row = null;
    public $fail = false;
    public function where() { return $this; }
    public function getOne() { return $this->row; }
    public function getLastError() { return $this->fail ? 'Storage unavailable' : ''; }
    public function insert($table, $values) { $this->row = $values; return !$this->fail; }
    public function update($table, $values) { $this->row = array_merge($this->row ?? array(), $values); return !$this->fail; }
    public function delete() { $this->row = null; return !$this->fail; }
    public function getRowCount() { return 1; }
}
$reflection = new ReflectionClass(\creamy\SessionHandler::class);
$handler = $reflection->newInstanceWithoutConstructor();
$store = new SessionStoreFixture();
foreach (array('db' => $store, 'cipher' => $cipher) as $property => $value) {
    $reflection->getProperty($property)->setValue($handler, $value);
}
check($handler->write('test-id', 'user|s:7:"agent42";'), 'Session storage write');
check($handler->read('test-id') === 'user|s:7:"agent42";', 'Session storage read');
check($handler->validateId('test-id'), 'Existing encrypted session accepted');
$store->fail = true;
check(!$handler->write('test-id', 'anything'), 'Session storage failure reported');
check(!\creamy\SessionHandler::lastWriteSucceeded(), 'Login can detect failed session persistence');
try { $handler->read('test-id'); check(false, 'Storage outage must throw'); }
catch (RuntimeException $exception) { check(true, 'Session read fails closed'); }
putenv('APP_ENV=production');
try { \creamy\RuntimeConfig::url('APP_URL'); check(false, 'Production HTTP URL must fail'); }
catch (RuntimeException $exception) { check(true, 'Production HTTP URL rejected'); }
try { new \creamy\SessionHandler(); check(false, 'Production file driver must fail'); }
catch (RuntimeException $exception) { check(true, 'Production cannot use development file sessions'); }
echo $count . " security regression checks passed.\n";
