<?php
/**
 * Quick check: can this CRM reach goAPIv2? Open in browser while logged in is not required.
 */
require_once __DIR__ . '/GoHttpClient.php';
require_once __DIR__ . '/goCRMAPISettings.php';

header('Content-Type: text/html; charset=UTF-8');

$testUrl = gourl . '/goCampaigns/goAPI.php';
$probe = \creamy\GoHttpClient::post($testUrl, array(
	'goUser' => goUser,
	'goPass' => goPass,
	'responsetype' => 'json',
	'goAction' => 'goGetAllCampaigns',
	'session_user' => goUser,
	'log_user' => goUser,
	'log_group' => 'ADMIN',
	'log_ip' => '127.0.0.1',
), 8);

$isHtml404 = is_string($probe) && stripos($probe, '404 Not Found') !== false;
$looksJson = is_string($probe) && strlen($probe) > 0 && ($probe[0] === '{' || $probe[0] === '[');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>goAPIv2 connection check</title>
	<style>
		body { font-family: sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
		.ok { color: #0a0; }
		.bad { color: #c00; }
		code, pre { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
		pre { padding: 12px; overflow: auto; white-space: pre-wrap; word-break: break-word; }
	</style>
</head>
<body>
	<h1>goAPIv2 connection check</h1>
	<p><strong>APP_ENV:</strong> <code><?php echo htmlspecialchars(defined('APP_ENV') ? APP_ENV : 'unknown', ENT_QUOTES, 'UTF-8'); ?></code></p>
	<p><strong>Configured API base:</strong> <code><?php echo htmlspecialchars(gourl, ENT_QUOTES, 'UTF-8'); ?></code> (from <code>.env</code> / <code>GO_API_BASE_URL</code>)</p>
	<p><strong>Test URL:</strong> <code><?php echo htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8'); ?></code></p>
	<ul>
		<li>cURL extension: <?php echo extension_loaded('curl') ? '<span class="ok">loaded</span>' : '<span class="bad">not loaded (streams may still work)</span>'; ?></li>
		<li>allow_url_fopen: <?php echo filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) ? '<span class="ok">On</span>' : '<span class="bad">Off</span>'; ?></li>
		<li>HTTP POST from PHP: <?php echo \creamy\GoHttpClient::canPost() ? '<span class="ok">available</span>' : '<span class="bad">blocked</span>'; ?></li>
	</ul>
	<?php if ($probe === false || $probe === '') { ?>
		<p class="bad"><strong>Result:</strong> No response from the API (connection failed or timed out).</p>
	<?php } elseif ($isHtml404) { ?>
		<p class="bad"><strong>Result:</strong> Apache returned 404 — goAPIv2 is not installed at this URL on this machine.</p>
	<?php } elseif ($looksJson) { ?>
		<p class="ok"><strong>Result:</strong> API responded with JSON (goAPIv2 is reachable).</p>
	<?php } else { ?>
		<p class="bad"><strong>Result:</strong> Unexpected response (not JSON). API may be misconfigured.</p>
	<?php } ?>
	<?php if (is_string($probe) && $probe !== '') { ?>
		<h2>Response preview</h2>
		<pre><?php echo htmlspecialchars(substr($probe, 0, 1200), ENT_QUOTES, 'UTF-8'); ?></pre>
	<?php } ?>
	<h2>What to do</h2>
	<ol>
		<li>Install GOautodial goAPIv2 on this server so <code><?php echo htmlspecialchars(gourl, ENT_QUOTES, 'UTF-8'); ?></code> exists, <strong>or</strong></li>
		<li>Copy <code>.env.example</code> to <code>.env</code> and set <code>GO_API_BASE_URL=https://your-pbx/goAPIv2</code> (restart Apache after changes)</li>
	</ol>
	<p>The campaign wizard UI in <code>v4.0-master</code> cannot create campaigns without that backend.</p>
</body>
</html>
