<?php
// Real GOagentJS renderer and real API client; only the unused UI database constructor is substituted.
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/calling_fixture_env.php';
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..');
$_SERVER['PHP_SELF'] = '/modules/GOagent/GOagentJS.php';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8097';
require_once __DIR__ . '/../php/SessionHandler.php';
new \creamy\SessionHandler();
$_SESSION = array('user' => 'fixture-agent', 'userid' => '42', 'username' => 'Fixture agent', 'userrole' => 3,
    'phone_login' => '1001', 'phone_pass' => "fixture'phone", 'phone_this' => 'fixture-account-password',
    'campaign_id' => '', 'use_webrtc' => 1, 'SIPserver' => 'kamailio');
require_once __DIR__ . '/../php/UIHandler.php';
class CallingFixtureUI extends \creamy\UIHandler { protected function __construct() {} }
$fixture = CallingFixtureUI::getInstance();
if (\creamy\UIHandler::getInstance() !== $fixture) throw new RuntimeException('UI fixture injection failed.');
\creamy\LanguageHandler::getInstance('en_US');
try {
    ob_start();
    require __DIR__ . '/../modules/GOagent/GOagentJS.php';
    $script = ob_get_clean();
    file_put_contents(__DIR__ . '/../tmp/calling-main.js', $script);
    echo "Rendered actual GOagentJS.php with dummy backend settings and real English translations.\n";
} catch (Throwable $exception) {
    if (ob_get_level()) ob_end_clean();
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . " at " . $exception->getFile() . ':' . $exception->getLine() . "\n");
    exit(1);
}
