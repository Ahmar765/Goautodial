<?php
// Start: php -S 127.0.0.1:8097 tests/http_guard_router.php
if (PHP_SAPI !== 'cli' || !extension_loaded('curl')) exit(1);
$cookie = tempnam(sys_get_temp_dir(), 'go-guard-');
$checks = 0;
function requestGuard($path, $method = 'GET', $token = null, $post = array(), $expected = 200) {
    global $cookie, $checks;
    $curl = curl_init('http://127.0.0.1:8097' . $path);
    $options = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_CUSTOMREQUEST => $method);
    if ($method === 'POST') $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    if ($token !== null) $options[CURLOPT_HTTPHEADER] = array('X-CSRF-Token: ' . $token);
    curl_setopt_array($curl, $options); $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    if ($status !== $expected) throw new RuntimeException($method . ' ' . $path . ': expected ' . $expected . ', got ' . $status);
    $checks++; return $body;
}
try {
    requestGuard('/php/AddUser.php', 'POST', null, array(), 401);
    requestGuard('/php/ViewContact.php', 'GET', null, array(), 401);
    requestGuard('/modules/GOagent/GOagentJS.php', 'GET', null, array(), 401);
    requestGuard('/index.php', 'GET', null, array(), 302);
    requestGuard('/init_db.php', 'GET', null, array(), 403);
    requestGuard('/setup_xampp_db.php', 'POST', null, array(), 403);
    requestGuard('/_fixture/production', 'GET', null, array(), 426);
    requestGuard('/_fixture/production?https=1', 'GET', null, array(), 400);
    requestGuard('/login.php', 'POST', null, array('submit' => '1'), 403);
    $page = requestGuard('/login.php?html=1');
    if (strpos($page, '/js/security.js') === false) throw new RuntimeException('HTML token script missing'); $checks++;
    requestGuard('/_fixture/login?role=3'); $token = str_repeat('a', 64);
    requestGuard('/php/AddUser.php', 'POST', $token, array(), 403);
    requestGuard('/php/MonitorAPI.php', 'POST', $token, array(), 403);
    requestGuard('/php/AgentAPI.php', 'POST', null, array(), 403);
    requestGuard('/php/AgentAPI.php', 'POST', str_repeat('b', 64), array(), 403);
    requestGuard('/php/AgentAPI.php', 'POST', $token);
    requestGuard('/php/ChangePassword.php', 'POST', $token, array('userid' => '99'), 403);
    requestGuard('/php/ChangePassword.php', 'POST', $token, array('userid' => '42'));
    requestGuard('/php/SaveImage.php', 'POST', $token, array('user_id' => '99'), 403);
    requestGuard('/logout.php', 'GET');
    requestGuard('/logout.php', 'POST', null, array(), 403);
    requestGuard('/_fixture/login?role=1');
    requestGuard('/php/ModifySettings.php', 'POST', $token, array(), 403);
    requestGuard('/_fixture/login?role=0');
    requestGuard('/php/AddUser.php', 'GET', null, array(), 405);
    requestGuard('/php/AddUser.php', 'POST', $token);
    requestGuard('/php/AgentAPI.php', 'DELETE', $token, array(), 405);
    requestGuard('/php/AddUser.php', 'POST', null, array('_csrf' => array('bad')), 403);
    requestGuard('/php/AddUser.php', 'POST', null, array('_csrf' => $token));
    requestGuard('/modules/GOagent/GOagentJS.php?action=SessioN', 'GET', null, array(), 405);
    $body = requestGuard('/php/SendMessage.php', 'POST', $token, array('fromuserid' => '99'));
    if (json_decode($body)->sender !== '42') throw new RuntimeException('Message sender spoof allowed'); $checks++;
    echo $checks . " HTTP guard regression checks passed.\n";
} finally { unlink($cookie); }
