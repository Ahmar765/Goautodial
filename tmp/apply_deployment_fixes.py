from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
changed = set()
def write(name, text):
    file = root / name
    if not file.exists() or file.read_text(encoding='utf-8-sig') != text:
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text(text, encoding='utf-8', newline='\n')
        changed.add(name)
def edit(name, transform):
    file = root / name
    write(name, transform(file.read_text(encoding='utf-8-sig')))

write('php/SessionHandler.php', r'''<?php
namespace creamy;
require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/SessionCipher.php';

/** Encrypted database sessions with strict ID validation and checked persistence. */
class SessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public $table = 'go_sessions';
    public $lifeTime = 7200;
    private $db;
    private $cipher;

    public function __construct($table = null, $lifeTime = 0, $encrypt = true)
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $this->table = $table ?? 'go_sessions';
        $this->lifeTime = $lifeTime ?: CRM_SESSION_EXPIRATION;
        if (CRM_SESSION_DRIVER === 'files') {
            if (RuntimeConfig::production()) {
                throw new \RuntimeException('File sessions are only available in development.');
            }
        } elseif (CRM_SESSION_DRIVER === 'database') {
            require_once __DIR__ . '/DatabaseConnectorFactory.php';
            $this->cipher = new SessionCipher();
            $this->db = DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
            $this->db->getOne($this->table, 'session_id');
            if ($this->db->getLastError() !== '') {
                throw new \RuntimeException('Session storage is unavailable.');
            }
            session_set_save_handler($this, true);
        } else {
            throw new \RuntimeException('Unknown session driver.');
        }
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_name(CRM_SESSION_COOKIE_NAME);
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/',
            'secure' => RuntimeConfig::production() || RuntimeConfig::secureRequest(),
            'httponly' => true, 'samesite' => 'Lax'));
        if (!session_start()) throw new \RuntimeException('Session could not be started.');
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    private function record($id)
    {
        $this->db->where('session_id', md5($id));
        $this->db->where('last_activity', time(), '>');
        if (CRM_SESSION_MATCH_IP) $this->db->where('ip_address', $this->getUserIP());
        $row = $this->db->getOne($this->table);
        if ($this->db->getLastError() !== '') throw new \RuntimeException('Session storage read failed.');
        return $row;
    }

    public function read(string $id): string|false
    {
        $row = $this->record($id);
        return $row ? $this->cipher->decrypt($row['user_data']) : '';
    }

    public function validateId(string $id): bool
    {
        $row = $this->record($id);
        return $row && $this->cipher->decrypt($row['user_data']) !== '';
    }

    public function write(string $id, string $data): bool
    {
        try {
            $values = array('session_id' => md5($id), 'user_agent' => substr($this->getUserAgent(), 0, 255),
                'last_activity' => time() + $this->lifeTime, 'user_data' => $this->cipher->encrypt($data),
                'ip_address' => $this->getUserIP());
            if ($this->record($id)) {
                unset($values['session_id']);
                $this->db->where('session_id', md5($id));
                $ok = $this->db->update($this->table, $values);
            } else {
                $ok = $this->db->insert($this->table, $values);
                if (!$ok && strpos($this->db->getLastError(), 'Duplicate entry') === 0) {
                    unset($values['session_id']);
                    $this->db->where('session_id', md5($id));
                    $ok = $this->db->update($this->table, $values);
                }
            }
            return (bool) $ok && $this->db->getLastError() === '';
        } catch (\Throwable $exception) {
            error_log('GOautodial session storage write failed.');
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
    public function destroy(string $id): bool
    {
        $this->db->where('session_id', md5($id));
        return (bool) $this->db->delete($this->table);
    }
    public function gc(int $max_lifetime): int|false
    {
        $this->db->where('last_activity', time(), '<');
        return $this->db->delete($this->table) ? $this->db->getRowCount() : false;
    }
    public function getUserIP() { return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }
    public function getUserAgent() { return (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''); }
}
''')

def fix_login(s):
    if '// Standalone' not in s: return s
    start = s.index('\t\t\tif ($result == NULL) {\n\t\t\t\t// Standalone')
    end = s.index('\n\t\t\tif ($result == NULL) { // login failed', start)
    s = s[:start] + s[end:]
    start = s.index('\t\t\t// To protect MySQL injection')
    end = s.index('\t\t\t// Check password', start)
    s = s[:start] + s[end:]
    s = s.replace("if (empty($_POST['username']) || empty($_POST['password']))", "if (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null) || $_POST['username'] === '' || $_POST['password'] === '' || strlen($_POST['password']) > 1024)")
    s = s.replace('$_SESSION["user"] = $username;', 'if (!session_regenerate_id(true)) { \\creamy\\Security::deny(503, "Session could not be renewed."); }\n\t\t\t\t$_SESSION = array("csrf_token" => bin2hex(random_bytes(32)));\n\t\t\t\t$_SESSION["user"] = $result["user"];\n\t\t\t\t$_SESSION["level"] = $result["level"];')
    s = s.replace('$_SESSION["password_hash"] = $result["password_hash"];', '$_SESSION["password_hash"] = $result["password_hash"];\n\t\t\t\tif (!session_write_close()) { \\creamy\\Security::deny(503, "Session could not be saved."); }')
    s = s.replace('header("location: index.php");', 'header("location: index.php");\n                    exit;')
    s = s.replace('header("location: agent.php");', 'header("location: agent.php");\n                    exit;')
    s = re.sub(r"\$upass = \(isset\(\$_GET\['password'\]\)\).*?;", "$upass = '';", s)
    return s
edit('login.php', fix_login)

def fix_db(s):
    if 'private function authenticateBackend(' in s: return s
    start = s.index('    public function checkLoginByName(')
    end = s.index('    /**', s.index('    public function checkLoginByEmail(', start))
    methods = r'''    public function checkLoginByName($name, $password, $ip_address) {
        return $this->authenticateBackend('user_name', $name, $password, $ip_address);
    }

    public function checkLoginByEmail($email, $password, $ip_address) {
        return $this->authenticateBackend('user_email', $email, $password, $ip_address);
    }

    private function authenticateBackend($field, $identity, $password, $ip_address) {
        require_once __DIR__ . '/ApiClient.php';
        require_once __DIR__ . '/Authentication.php';
        if (!is_string($identity) || !is_string($password) || $identity === '' || $password === '') return null;
        try {
            $body = \creamy\ApiClient::post(gourl . '/goUsers/goAPI.php', array(
                'goUser' => goUser, 'goPass' => goPass, 'responsetype' => 'json',
                'goAction' => 'goUserLogin', $field => $identity, 'user_pass' => $password,
                'ip_address' => $ip_address
            ));
            return \creamy\Authentication::userFromResponse($body, $password, $identity);
        } catch (\Throwable $exception) {
            error_log('GOautodial authentication backend unavailable.');
            return null;
        }
    }

'''
    s = s[:start] + methods + s[end:]
    start = s.index('    private function encrypt_passwd(')
    end = s.index('\n}', start)
    s = s[:start] + r'''    private function encrypt_passwd($password, $cost, $salt) {
        require_once __DIR__ . '/LegacyPassword.php';
        return \creamy\LegacyPassword::hash($password, $cost, $salt);
    }
''' + s[end:]
    s = s.replace('if ($password_hash == $oldpassword)', 'if (is_string($password_hash) && hash_equals($password_hash, $oldpassword))')
    return s
edit('php/DbHandler.php', fix_db)

def fix_api(s):
    if '\tif(isset($_SESSION["user"]))' not in s: return s
    start = s.index('\tif(isset($_SESSION["user"]))')
    end = s.index('\t/**\n\t*  APIHandler.', start)
    s = s[:start] + '''    // Anonymous includes receive no identity or privileges.
    define('session_user', $_SESSION['user'] ?? '');
    define('session_usergroup', $_SESSION['usergroup'] ?? '');
    define('session_password', $_SESSION['phone_this'] ?? '');
    define('log_pass', $_SESSION['password_hash'] ?? '');

''' + s[end:]
    s = s.replace("\tini_set('memory_limit','2048M');\n\tini_set('upload_max_filesize', '600M');\n\tini_set('post_max_size', '600M');\n\tini_set('max_execution_time', 0);", '')
    s = s.replace("require_once('goCRMAPISettings.php');", "require_once('goCRMAPISettings.php');\n    require_once __DIR__ . '/Security.php';\n    require_once __DIR__ . '/ApiClient.php';")
    start = s.index('\t\t\t$url = gourl.', s.index('public function API_Request('))
    end = s.index('\n\t\t/*', start)
    s = s[:start] + r'''            if (PHP_SAPI !== 'cli' && !Security::authenticated($_SESSION ?? array())) return null;
            if (!preg_match('/^[A-Za-z0-9]+$/', $folder)) throw new \InvalidArgumentException('Invalid API resource.');
            $identity = PHP_SAPI === 'cli' ? array('goUser' => goUser, 'goPass' => goPass)
                : array('goUser' => session_user, 'goPass' => session_password);
            $entries = array_merge($identity, array('responsetype' => 'json',
                'session_user' => session_user, 'log_user' => session_user, 'log_group' => session_usergroup,
                'log_pass' => log_pass, 'log_ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'hostname' => $_SERVER['REMOTE_ADDR'] ?? ''));
            $body = ApiClient::post(gourl . '/' . $folder . '/goAPI.php', array_merge($postfields, $entries));
            return $request_data ? $body : ($body === null ? null : json_decode($body));
        }
''' + s[end:]
    s = s.replace('array_merge($default_entries, $postfields)', 'array_merge($postfields, $default_entries)')
    s = s.replace('public function API_Upload($folder, $postfields, $return_data = NULL){', "public function API_Upload($folder, $postfields, $return_data = NULL){\n            if (PHP_SAPI !== 'cli' && !Security::authenticated($_SESSION ?? array())) return null;")
    s = s.replace('"CONNECTION" => $postdata', '"CONNECTION" => array()')
    start = s.index('\t\tpublic function API_StarwoodTestUpload(')
    end = s.index('\t\tpublic function API_getGOPackage()', start)
    s = s[:start] + '''        public function API_StarwoodTestUpload($return_data = NULL) {
            throw new \\RuntimeException('The development-only lead upload has been removed.');
        }

''' + s[end:]
    for endpoint, setting in [('sendMessage', 'WHATSAPP_SEND_URL'), ('webhook', 'WHATSAPP_WEBHOOK_URL')]:
        s = re.sub(r'"https://us-central1-whatsapp-center\.cloudfunctions\.net/api/' + endpoint + r'\?[^"\n]+"', "RuntimeConfig::url('" + setting + "')", s)
    s = s.replace('CURLOPT_TIMEOUT => 0,', 'CURLOPT_TIMEOUT => 30,').replace('CURLOPT_FOLLOWLOCATION => true,', 'CURLOPT_FOLLOWLOCATION => false,')
    s = s.replace('CURLOPT_POSTFIELDS =>"{\\r\\n  \\"phone\\": $phone,\\r\\n  \\"body\\": \\"$body\\"\\r\\n}",', "CURLOPT_POSTFIELDS => json_encode(array('phone' => $phone, 'body' => $body)),")
    s = s.replace('CURLOPT_POSTFIELDS =>"{\\r\\n  \\"webhookUrl\\": \\"$callbackURL\\"\\r\\n}",', "CURLOPT_POSTFIELDS => json_encode(array('webhookUrl' => $callbackURL)),")
    return s
edit('php/APIHandler.php', fix_api)

# Stable include paths on Linux and when a handler is invoked from another directory.
for name in ['php/DatabaseConnectorFactory.php', 'php/LanguageHandler.php']:
    edit(name, lambda s: re.sub(r'@include_once\([\'"]Config\.php[\'"]\);', "require_once __DIR__ . '/Config.php';", s))
edit('php/DatabaseConnectorFactory.php', lambda s: s.replace('return null;', 'throw $e;') if False else s.replace('return null;\n\t\t    }', 'throw $e;\n\t\t    }'))

# Browser agent operations now use authenticated same-origin proxies.
edit('modules/GOagent/GOagentJS.php', lambda s: s.replace('<?=$goAPI?>/goAgent/goAPI.php', '/php/AgentAPI.php'))
edit('index.php', lambda s: s.replace('<?=$goAPI?>/goBarging/goAPI.php', '/php/MonitorAPI.php'))
for name in ['modules/GOagent/GOagentJS.php', 'index.php']:
    edit(name, lambda s: re.sub(r'\$goAPI = .*?;', '$goAPI = gourl;', s))

# Upgrade TLS settings consistently across first-party PHP, including direct dashboard handlers.
files = list(root.glob('*.php')) + list((root / 'php').rglob('*.php')) + list((root / 'modules').rglob('*.php'))
for file in files:
    name = file.relative_to(root).as_posix()
    s = file.read_text(encoding='utf-8-sig')
    s = re.sub(r'(CURLOPT_SSL_VERIFYPEER\s*(?:=>|,)\s*)(?:false|0)\b', r'\g<1>true', s)
    s = re.sub(r'(CURLOPT_SSL_VERIFYHOST\s*(?:=>|,)\s*)(?:false|0|true|1)\b', r'\g<1>2', s)
    s = re.sub(r"ini_set\(\s*['\"]display_(?:startup_)?errors['\"]\s*,\s*(?:1|'1'|true)\s*\)", "ini_set('display_errors', '0')", s)
    write(name, s)

# Cover every first-party executable entry point before its legacy includes execute.
libraries = {'Config.php', 'goCRMAPISettings.php', 'CRMDefaults.php', 'CurlCompat.php', 'smtp_settings.php'}
for file in files:
    name = file.relative_to(root).as_posix()
    s = file.read_text(encoding='utf-8-sig')
    if file.name in libraries or re.search(r'(?m)^\s*(?:abstract\s+|final\s+)?(?:class|interface)\s+', s):
        continue
    if 'RequestGuard.php' in s: continue
    if '<?php' not in s: continue
    relative = Path(__import__('os').path.relpath(root/'php'/'RequestGuard.php', file.parent)).as_posix()
    insertion = "\nrequire_once __DIR__ . '/" + relative + "';\n"
    ns = re.search(r'\bnamespace\s+[A-Za-z_\\]+\s*;', s)
    pos = ns.end() if ns else s.index('<?php') + len('<?php')
    write(name, s[:pos] + insertion + s[pos:])

# Session redirects must terminate execution, rather than continuing protected page code.
edit('php/Session.php', lambda s: re.sub(r"header\('Location: '\.\$realPath\.'login\.php'\);", "header('Location: /login.php');\n    exit;", s))

# Only CLI use is permitted for destructive local setup helpers.
for name in ['init_db.php', 'setup_xampp_db.php']:
    edit(name, lambda s: s.replace("ini_set('display_errors', 1);", "ini_set('display_errors', 0);"))

print('Updated ' + str(len(changed)) + ' first-party files.')
write('tmp/deployment_changed_files.txt', '\n'.join(sorted(changed))+'\n')
