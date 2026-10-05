<?php
// Executes the real GOagent view methods with fake database and translation objects.
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/calling_fixture_env.php';
putenv('APP_URL=https://crm.example.test');
putenv('TRUSTED_PROXIES=127.0.0.1');
$_SERVER = array_merge($_SERVER, array('HTTP_HOST' => 'crm.example.test', 'REQUEST_URI' => '/agent.php',
    'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'));
unset($_SERVER['HTTPS']); // TLS terminates at the trusted reverse proxy.
require_once __DIR__ . '/../php/SessionHandler.php';
new \creamy\SessionHandler();
$_SESSION = array('user' => 'fixture-agent', 'userid' => '42', 'username' => 'Fixture agent', 'userrole' => 3,
    'phone_login' => $argv[2] ?? '1001', 'phone_pass' => "fixture'phone", 'phone_this' => 'fixture-account-password', 'campaign_id' => '');
require_once __DIR__ . '/../php/Module.php';
require_once __DIR__ . '/../modules/GOagent/module.php';
class CallingFixtureDatabase {
    private $setting;
    public function where($key, $value) { $this->setting = $value; return $this; }
    public function getOne($table, $fields = null) {
        if ($table === 'vicidial_users') return array('pass' => "fixture'account", 'pass_hash' => 'fixture-hash');
        $settings = array('GO_agent_use_wss' => '1', 'GO_agent_wss' => 'ws.example.test', 'GO_agent_wss_port' => '8089',
            'GO_agent_wss_sip' => ($GLOBALS['argv'][1] ?? '') === 'fallback' ? '' : 'sip.example.test',
            'GO_agent_wss_sip_port' => '5070', 'GO_agent_domain' => 'example.test', 'GO_show_phones' => '1');
        return array('value' => $settings[$this->setting] ?? '');
    }
}
class CallingFixtureTranslations { public function translationFor($key) { return $key; } }
$reflection = new ReflectionClass(\creamy\GOagent::class);
$module = $reflection->newInstanceWithoutConstructor();
foreach (array('languageHandler' => new CallingFixtureTranslations(), 'userrole' => 3, 'astDB' => new CallingFixtureDatabase()) as $key => $value) $reflection->getProperty($key)->setValue($module, $value);
$reflection->getProperty('goDB')->setValue($module, new CallingFixtureDatabase());
foreach (array('agent' => 'getGOagentContent', 'admin' => 'getGOadminContent') as $view => $method) {
    $html = $reflection->getMethod($method)->invoke($module);
    file_put_contents(__DIR__ . '/../tmp/calling-' . $view . '-' . ($argv[1] ?? 'configured') . '.html', $html);
}
echo "Rendered actual agent/admin view methods with fixture settings.\n";
