<?php
/**
	The MIT License (MIT)
	
	Copyright (c) 2015 Ignacio Nieto Carvajal
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

namespace creamy;


// Start session if not already started.
require_once('CRMDefaults.php');
require_once('SessionHandler.php');
$session_class = new \creamy\SessionHandler();
/*if (version_compare(phpversion(), '5.4.0', '<')) {
     if(session_id() == '') {
		session_start();
     }
} else {
	if (session_status() == PHP_SESSION_NONE) {
		if (CRM_SESSION_DRIVER == 'database') {
			require_once('SessionHandler.php');
			$session_class = new \creamy\SessionHandler();
		} else {
			session_start(); // Starting Session
		}
	}
}*/

// force https protocol — disabled for local dev
// if(empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on") {
//     header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
//     exit();
// }

$realPath = '';
$isGoAgentJs = isset($isAgentUI) && preg_match("/GOagentJS/", (string) $isAgentUI);
if ($isGoAgentJs) {
	// Stay on same host/scheme; never force https://host/ (breaks XAMPP subfolder installs)
	$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
	// GOagentJS lives in /…/modules/GOagent → CRM root is three levels up
	$crmBase = preg_replace('#/modules/GOagent/?$#', '/', $scriptDir);
	$realPath = rtrim($crmBase, '/') . '/';
}

$isAjaxAction = $isGoAgentJs && (
	isset($_REQUEST['action']) || isset($_REQUEST['module_name'])
	|| (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

if (!isset($_SESSION["username"]) || !isset($_SESSION["userid"])) {
	if ($isAjaxAction) {
		header('Content-Type: application/json; charset=utf-8');
		http_response_code(401);
		echo json_encode(array(
			'result' => 'error',
			'message' => 'Session expired. Reload the page and log in again.',
		));
		exit;
	}
	$loginUrl = ($realPath !== '' ? $realPath : '') . 'login.php';
	header('Location: ' . $loginUrl);
	exit;
}
if (!isset($_SESSION["userrole"])) {
	$_SESSION["userrole"] = CRM_DEFAULTS_USER_ROLE_GUEST; // no privileged account by default.
}
if (!isset($_SESSION["avatar"])) {
	$_SESSION["avatar"] = CRM_DEFAULTS_USER_AVATAR;
}

include_once('CreamyUser.php');
?>
