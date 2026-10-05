<?php
// Two local fixture servers are required (8097 frontend; 8098 dummy backend).
if (PHP_SAPI !== 'cli') exit(1);
$cookie = tempnam(sys_get_temp_dir(), 'calling-test-');
$checks = 0;
function request($path, $fields = null, $token = true, $authenticated = true) {
    global $cookie;
    $curl = curl_init('http://127.0.0.1:8097' . $path);
    $options = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8);
    if ($authenticated) { $options[CURLOPT_COOKIEFILE] = $cookie; $options[CURLOPT_COOKIEJAR] = $cookie; }
    if ($fields !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = http_build_query($fields); }
    if ($token) $options[CURLOPT_HTTPHEADER] = array('X-CSRF-Token: ' . str_repeat('a', 64));
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl); return array($status, $body, json_decode($body, true));
}
function check($condition, $label) { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
try {
    check(request('/php/AgentAPI.php', array('goAction' => 'goLoginUser'), true, false)[0] === 401, 'Unauthenticated agent request');
    check(request('/_fixture/login')[0] === 200, 'Fixture login');
    foreach (array('goGetAllowedCampaigns', 'goLoginUser', 'goManualDialOnly', 'goHangupCall', 'goUpdateDispo', 'goXFERSendRedirect', 'goAutodialResumePause', 'goLogoutUser') as $action) {
        $result = request('/php/AgentAPI.php', array('goAction' => $action, 'goUser' => 'spoof', 'goPass' => 'spoof',
            'session_user' => 'spoof', 'log_user' => 'spoof', 'value' => "a&b+c='\""));
        $fields = $result[2]['fields'] ?? array();
        check($result[0] === 200 && ($fields['goAction'] ?? '') === $action, $action . ' forwarding');
        check(($fields['goUser'] ?? '') === 'fixture-agent' && ($fields['goPass'] ?? '') === 'fixture-account-password'
            && ($fields['session_user'] ?? '') === 'fixture-agent' && ($fields['log_user'] ?? '') === 'fixture-agent', $action . ' session identity');
        check(($fields['value'] ?? '') === "a&b+c='\"", $action . ' payload encoding');
    }
    check(request('/php/AgentAPI.php')[0] === 405, 'Agent GET rejected');
    check(request('/php/AgentAPI.php', array(), true)[0] === 400, 'Missing action rejected');
    check(request('/php/AgentAPI.php', array('goAction' => array('invalid')), true)[0] === 400, 'Array action rejected');
    check(request('/php/AgentAPI.php', array('goAction' => 'goLoginUser'), false)[0] === 403, 'CSRF enforced');
    foreach (array('http_failure', 'invalid_json') as $mode) check(request('/php/AgentAPI.php', array('goAction' => 'goLoginUser', 'fixture_mode' => $mode))[0] === 502, $mode . ' rejected');
    check(request('/php/MonitorAPI.php', array('goAction' => 'fixture-monitor'))[0] === 403, 'Agent monitoring denied');
    $sip = request('/modules/GOagent/jsSIP.php');
    check($sip[0] === 200 && str_contains($sip[1], 'sip:1001@sip.example.test') && str_contains(str_replace('\\/', '/', $sip[1]), 'wss://ws.example.test:8089'), 'Standalone SIP settings render');
    check(str_contains($sip[1], 'fixture-phone-password') && !str_contains($sip[1], 'fixture-account-hash'), 'SIP phone credential selected');
    file_put_contents(__DIR__ . '/../tmp/calling-sip.html', $sip[1]);
    check(request('/_fixture/login?role=2')[0] === 200, 'Supervisor fixture login');
    check(request('/php/MonitorAPI.php', array('goAction' => 'fixture-monitor'))[0] === 200, 'Supervisor monitoring forwarding');
    check(request('/php/MonitorAPI.php', array('goAction' => 'fixture-monitor'), false)[0] === 403, 'Monitoring CSRF enforced');
    check(request('/php/MonitorAPI.php', array('fixture_mode' => 'invalid_json'))[0] === 502, 'Monitoring invalid response rejected');
    echo "$checks calling HTTP checks passed using dummy backend responses. No calls placed.\n";
} finally { unlink($cookie); }
