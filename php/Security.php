<?php
namespace creamy;
require_once __DIR__ . '/RuntimeConfig.php';

final class Security
{
    private static $guarded = false;

    public static function authenticated($session)
    {
        return is_array($session) && !empty($session['user']) && !empty($session['userid'])
            && isset($session['userrole']) && in_array($session['userrole'], array(0, 1, 2, 3), true);
    }

    public static function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validCsrf($session, $token)
    {
        return is_string($token) && isset($session['csrf_token']) && is_string($session['csrf_token'])
            && strlen($session['csrf_token']) === 64 && hash_equals($session['csrf_token'], $token);
    }

    public static function allowedRoles($route)
    {
        $route = strtolower(str_replace('\\', '/', $route));
        $name = basename($route, '.php');
        // User accounts, server configuration and executable plugins are administrator-only.
        if (preg_match('~^(admin|settings|editsettings|modulesettings)~', $name)
            || preg_match('~^(add|modify|delete|create|set|activate|configure|update|copy|change).*(user|server|carrier|phone|smtp|module|settings|customfield)~', $name)
            || in_array($name, array('adminloginrocketchat', 'changepasswordadmin'), true)) {
            return array(0);
        }
        if (strpos($route, 'php/dashboard/') === 0 || strpos($route, 'php/reports/') === 0
            || preg_match('~^(telephony|edittelephony|audiofiles|callreports|reports|loadleads|export|barge|emergency)~', $name)) {
            return array(0, 1, 2);
        }
        if (strpos($route, 'php/') === 0) {
            $agentActions = array('agentapi', 'csrf', 'changepassword', 'saveimage', 'createcustomer',
                'modifycustomer', 'modifycontact', 'deletecustomer', 'customerlistjson', 'createevent',
                'editevent', 'modifyevent', 'deleteevent', 'createtask', 'modifytask', 'deletetask',
                'completetask', 'sendmessage', 'send_mail', 'deletemessages', 'markmessagesasfavorite',
                'markmessagesasread', 'markmessagesasunread', 'junkmessages', 'unjunkmessages',
                'loginrocketchat', 'logoutrocketchat', 'viewimage', 'viewcontact', 'modalpassworddialogs');
            if (in_array($name, $agentActions, true) || strpos($route, 'php/crm/') === 0) {
                return array(0, 1, 2, 3);
            }
            // Other management endpoints retain staff access; unknown operations fail closed for agents.
            return array(0, 1, 2);
        }
        return array(0, 1, 2, 3);
    }

    public static function deny($status, $message)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(array('error' => $message));
        exit;
    }

    public static function guard()
    {
        if (PHP_SAPI === 'cli' || self::$guarded) {
            return;
        }
        self::$guarded = true;
        $root = str_replace('\\', '/', dirname(__DIR__));
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $route = ltrim(substr($script, strlen($root)), '/');
        $name = basename($route);
        if (in_array($name, array('install.php', 'init_db.php', 'setup_xampp_db.php', 'update.php', 'job-scheduler.php'), true)
            || preg_match('~^(bin|jobs|scratch|skel|tests|config|tmp|uploads|img/avatars)/~i', $route)) {
            self::deny(403, 'This operation is not available over HTTP.');
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        if (RuntimeConfig::production() && !RuntimeConfig::secureRequest()) {
            self::deny(426, 'HTTPS is required.');
        }
        if (RuntimeConfig::production()) {
            $origin = parse_url(RuntimeConfig::url('APP_URL'));
            $expected = strtolower($origin['host']) . (isset($origin['port']) ? ':' . $origin['port'] : '');
            if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== $expected) {
                self::deny(400, 'Unexpected application hostname.');
            }
        }
        $public = in_array($name, array('login.php', 'accountactivation.php', 'lostpassword.php', 'passwordrecovery.php'), true)
            && strpos($route, '/') === false;
        require_once __DIR__ . '/SessionHandler.php';
        new SessionHandler();
        if (!$public && !self::authenticated($_SESSION)) {
            if (strpos($route, '/') === false && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                header('Location: /login.php', true, 302);
                exit;
            }
            self::deny(401, 'Authentication required.');
        }
        if (!$public && !in_array($_SESSION['userrole'], self::allowedRoles($route), true)) {
            self::deny(403, 'You do not have permission for this operation.');
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, array('GET', 'HEAD', 'POST'), true)) {
            header('Allow: GET, HEAD, POST');
            self::deny(405, 'Unsupported request method.');
        }
        // Legacy action handlers use POST. Do not allow query strings to mutate state.
        if ($method !== 'POST' && strpos($route, 'php/') === 0
            && preg_match('/^(Add|Modify|Delete|Create|Change|Save|Set|Activate|Configure|Update|Copy|Complete|Send|Junk|Unjunk|Mark|Emergency|Action)/i', $name)) {
            header('Allow: POST');
            self::deny(405, 'POST is required.');
        }
        if ($method !== 'POST' && strpos($route, 'modules/') === 0 && isset($_REQUEST['action'])) {
            header('Allow: POST');
            self::deny(405, 'POST is required for module actions.');
        }
        if ($method === 'POST' && !self::validCsrf($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null))) {
            if ($public) {
                header('Location: /login.php', true, 303);
                exit;
            }
            self::deny(403, 'Invalid request token. Refresh the page and try again.');
        }
        if (in_array($name, array('SendMessage.php', 'send_mail.php'), true)) {
            $_POST['fromuserid'] = $_SESSION['userid'];
        }
        if ($name === 'ChangePassword.php' && (string) ($_POST['userid'] ?? '') !== (string) $_SESSION['userid']) {
            self::deny(403, 'You can only change your own password.');
        }
        if ($name === 'SaveImage.php' && $_SESSION['userrole'] !== 0
            && (string) ($_POST['user_id'] ?? '') !== (string) $_SESSION['userid']) {
            self::deny(403, 'You can only change your own avatar.');
        }
        if ($name === 'logout.php' && $method !== 'POST') {
            header('Content-Type: text/html; charset=utf-8');
            echo '<form method="post" action="/logout.php"><input type="hidden" name="_csrf" value="'
                . self::csrfToken() . '"><button type="submit">Sign out</button></form>';
            exit;
        }
        $token = self::csrfToken();
        // Add the client integration to full HTML documents without changing JSON or script responses.
        ob_start(function ($output) use ($token) {
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) return $output;
            }
            if (preg_match('/<html(?:\s[^>]*)?>/i', $output) && preg_match('/<head(?:\s[^>]*)?>/i', $output)) {
                $tag = '<script src="/js/security.js" data-csrf="' . $token . '"></script>';
                return preg_replace('/(<head(?:\s[^>]*)?>)/i', '$1' . $tag, $output, 1);
            }
            return $output;
        });
    }
}
