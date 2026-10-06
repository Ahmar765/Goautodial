from pathlib import Path

root = Path(__file__).resolve().parent.parent
path = root / 'php/APIHandler.php'
text = path.read_text(encoding='utf-8')
start = text.index('\t\tpublic function API_Upload(')
end = text.index('        public function API_StarwoodTestUpload', start)
text = text[:start] + '''        public function API_Upload($folder, $postfields, $return_data = NULL) {
            if (PHP_SAPI !== 'cli' && !Security::authenticated($_SESSION ?? array())) return null;
            if (!preg_match('/^[A-Za-z0-9]+$/', $folder)) throw new \\InvalidArgumentException('Invalid API resource.');
            $url = gourl . '/' . $folder . '/goAPI.php';
            $entries = array('goUser' => PHP_SAPI === 'cli' ? goUser : session_user,
                'goPass' => PHP_SAPI === 'cli' ? goPass : session_password, 'responsetype' => 'json',
                'session_user' => session_user, 'log_user' => session_user, 'log_group' => session_usergroup,
                'log_pass' => log_pass, 'log_ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'hostname' => $_SERVER['REMOTE_ADDR'] ?? '');
            $data = ApiClient::post($url, array_merge($postfields, $entries), 120, true);
            $output = $data === null ? null : json_decode($data);
            return !empty($return_data) ? array('output' => $output, 'data' => $data, 'URL' => $url, 'CONNECTION' => array()) : $output;
        }

''' + text[end:]
start = text.index('\t\tpublic function goGetPermissions(')
end = text.index('\t\tpublic function API_getLoginInfo(', start)
text = text[:start] + '''        public function goGetPermissions($type = 'dashboard') {
            require_once __DIR__ . '/Permissions.php';
            return Permissions::forResponse($this->API_goGetGroupPermission(), $type);
        }

''' + text[end:]
start = text.index('\t\tpublic function API_getLoginInfo(')
end = text.index('\t\tpublic function API_getAllPauseCodes(', start)
text = text[:start] + '''        public function API_getLoginInfo($user) {
            $result = $this->API_Request('goAgent', array('goAction' => 'goGetLoginInfo',
                'goUserID' => $user, 'goCampaign' => $_SESSION['campaign_id'] ?? '', 'isPBP' => 0, 'bcrypt' => 0));
            return is_object($result) ? ($result->data ?? null) : null;
        }

''' + text[end:]
path.write_text(text, encoding='utf-8')
path = root / 'php/UIHandler.php'
text = path.read_text(encoding='utf-8')
start = text.index("\tpublic function goGetPermissions($type = 'dashboard', $group)")
end = text.index('\tpublic function API_ListsStatuses(', start)
text = text[:start] + '''    public function goGetPermissions($type = 'dashboard', $group = null) {
        return $this->api->goGetPermissions($type);
    }

''' + text[end:]
path.write_text(text, encoding='utf-8')
path = root / 'astguiclient.conf-sample'
text = path.read_text(encoding='utf-8')
import re
text = re.sub(r'(?m)^(VAR(?:DB\w*|FTP|REPORT)_?(?:custom_)?pass) => .*$', r'\1 => REPLACE_WITH_GENERATED_SECRET', text)
text = text.replace('VARHTTP_path => http://HOSTNAME/RECORDINGS', 'VARHTTP_path => https://HOSTNAME/RECORDINGS')
text = text.replace('VARasterisk_version => 13.X', 'VARasterisk_version => REPLACE_WITH_INSTALLED_VERSION')
path.write_text(text, encoding='utf-8')
print('Updated API uploads, permission parsing, login-info requests and backend sample settings.')
