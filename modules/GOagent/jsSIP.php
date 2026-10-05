<?php
require_once __DIR__ . '/../../php/RequestGuard.php';

$display_name = $_SESSION['username'];
$phone_login = $_SESSION['phone_login'];
$phone_pass = $_SESSION['phone_pass'] ?: ($_SESSION['password_hash'] ?? $_SESSION['phone_this']);
$websocketSIP = \creamy\RuntimeConfig::required('SIP_DOMAIN');
$websocketURL = \creamy\RuntimeConfig::required('SIP_WS_HOST');
$websocketPORT = \creamy\RuntimeConfig::value('SIP_WS_PORT', '8089');
if (!is_string($websocketSIP) || !is_string($websocketURL)
    || !preg_match('/^[A-Za-z0-9.-]+$/', $websocketSIP)
    || !preg_match('/^[A-Za-z0-9.-]+$/', $websocketURL)
    || !is_scalar($websocketPORT) || !ctype_digit((string) $websocketPORT)
    || (int) $websocketPORT < 1 || (int) $websocketPORT > 65535) {
    \creamy\Security::deny(400, 'Invalid SIP WebSocket configuration.');
}
$registrarServer = $websocketSIP;
$websocketSIP = "sip:{$phone_login}@{$websocketSIP}";
$websocketURI = "wss://{$websocketURL}:{$websocketPORT}";
$moduleURL = '/';
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>

<html>
	<head>
		<title>tryit-jssip</title>
		<meta charset='UTF-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no'>

		<link rel='stylesheet' href='<?=$moduleURL?>modules/GOagent/css/jssip.GOautodial.css'>

		<script>
			// Set debug
			//window.localStorage.setItem('debug', '* -engine* -socket* *ERROR* *WARN*');

			// Set antiglobal
			//window.antiglobal('___browserSync___oldSocketIo', 'JSON3', 'io', '___browserSync___', '__core-js_shared__', 'MediaStream', 'RTCPeerConnection');
			//setInterval(window.antiglobal, 5000);
		</script>

		<script>
			window.SETTINGS =
			{
				display_name        : <?=json_encode($display_name, $jsonFlags)?>,
				uri                 : <?=json_encode($websocketSIP, $jsonFlags)?>,
				password			: <?=json_encode($phone_pass, $jsonFlags)?>,
				socket              :
				{
					uri           : <?=json_encode($websocketURI, $jsonFlags)?>,
					via_transport : 'auto',
				},
				registrar_server    : <?=json_encode($registrarServer, $jsonFlags)?>,
				contact_uri         : null,
				authorization_user  : null,
				instance_id         : null,
				session_timers      : false,
				use_preloaded_route : false,
				pcConfig            :
				{
					rtcpMuxPolicy : 'negotiate',
					iceServers    :
					[
						{ urls : [ 'stun:stun.l.google.com:19302' ] }
					]
				},
				callstats           :
				{
					enabled   : false,
					AppID     : null,
					AppSecret : null
				}
			};
		</script>

		<script src='<?=$moduleURL?>modules/GOagent/js/jssip.GOautodial.js'></script>
	</head>

	<body>
		<div id='GOautodial-jssip-container'></div>
		<div id='GOautodial-jssip-media-query-detector'></div>
	</body>
</html>
