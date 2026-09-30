<?php
/**
 * @file        AddVoicemail.php
 * @brief       Handles add voicemail (local DB + optional goAPI)
 * @copyright   Copyright (c) 2018 GOautodial Inc.
 */

require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/LanguageHandler.php';
require_once __DIR__ . '/APIHandler.php';
require_once __DIR__ . '/GoHttpClient.php';
require_once __DIR__ . '/Session.php';

header('Content-Type: application/json; charset=utf-8');

$lh = \creamy\LanguageHandler::getInstance();

function voicemail_json_response($ok, $message = '') {
	$payload = array('ok' => $ok ? 1 : 0, 'status' => $ok ? 1 : 0);
	if ($message !== '') {
		$payload['message'] = $message;
	}
	echo json_encode($payload);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	voicemail_json_response(false, 'Invalid request.');
}

$voicemailId = isset($_POST['voicemail_id']) ? trim((string) $_POST['voicemail_id']) : '';
$password = isset($_POST['password']) ? trim((string) $_POST['password']) : '';
if ($password === '' && isset($_POST['pass'])) {
	$password = trim((string) $_POST['pass']);
}
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
if ($name === '' && isset($_POST['fullname'])) {
	$name = trim((string) $_POST['fullname']);
}
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$userGroup = isset($_POST['user_group']) ? trim((string) $_POST['user_group']) : '';
if ($userGroup === '') {
	$userGroup = isset($_SESSION['usergroup']) ? (string) $_SESSION['usergroup'] : 'ADMIN';
}
$active = isset($_POST['active']) ? strtoupper(trim((string) $_POST['active'])) : 'Y';
if ($active !== 'Y' && $active !== 'N') {
	$active = 'Y';
}

if ($voicemailId === '' || !ctype_digit($voicemailId)) {
	voicemail_json_response(false, 'Error: Set a value for Voicemail ID (numbers only, e.g. 1001).');
}
if (strlen($voicemailId) < 2 || strlen($voicemailId) > 10) {
	voicemail_json_response(false, 'Error: Voicemail ID must be 2–10 digits.');
}
if ($password === '') {
	voicemail_json_response(false, 'Error: Set a password for this voicemail.');
}
if ($name === '') {
	voicemail_json_response(false, 'Error: Set a name for this voicemail.');
}

$localFallback = defined('CRM_LOGIN_LOCAL_DB_FALLBACK') && CRM_LOGIN_LOCAL_DB_FALLBACK === true;

/**
 * Create/update voicemail in asterisk.vicidial_voicemail
 */
function add_voicemail_local($voicemailId, $password, $name, $email, $userGroup, $active) {
	$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
	$user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
	$pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
	$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
	$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';

	$mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
	if ($mysqli->connect_errno) {
		return array('ok' => false, 'message' => 'DB connect failed: ' . $mysqli->connect_error);
	}
	$mysqli->set_charset('utf8mb4');

	$mysqli->query("CREATE TABLE IF NOT EXISTS vicidial_voicemail (
		voicemail_id VARCHAR(10) NOT NULL,
		pass VARCHAR(100) NOT NULL DEFAULT '',
		fullname VARCHAR(100) NOT NULL DEFAULT '',
		messages INT NOT NULL DEFAULT 0,
		old_messages INT NOT NULL DEFAULT 0,
		email VARCHAR(100) NOT NULL DEFAULT '',
		delete_vm_after_email ENUM('Y','N') NOT NULL DEFAULT 'N',
		user_group VARCHAR(20) NOT NULL DEFAULT 'ADMIN',
		active ENUM('Y','N') NOT NULL DEFAULT 'Y',
		PRIMARY KEY (voicemail_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	$stmt = $mysqli->prepare(
		"INSERT INTO vicidial_voicemail (voicemail_id, pass, fullname, email, user_group, active, messages, old_messages)
		 VALUES (?, ?, ?, ?, ?, ?, 0, 0)
		 ON DUPLICATE KEY UPDATE
		   pass = VALUES(pass),
		   fullname = VALUES(fullname),
		   email = VALUES(email),
		   user_group = VALUES(user_group),
		   active = VALUES(active)"
	);
	if (!$stmt) {
		$err = $mysqli->error;
		$mysqli->close();
		return array('ok' => false, 'message' => 'Prepare failed: ' . $err);
	}
	$stmt->bind_param('ssssss', $voicemailId, $password, $name, $email, $userGroup, $active);
	$ok = $stmt->execute();
	$err = $stmt->error;
	$stmt->close();
	$mysqli->close();

	if (!$ok) {
		return array('ok' => false, 'message' => 'Insert failed: ' . $err);
	}
	return array('ok' => true, 'message' => "Voicemail {$voicemailId} saved.");
}

if ($localFallback) {
	$local = add_voicemail_local($voicemailId, $password, $name, $email, $userGroup, $active);
	if ($local['ok']) {
		voicemail_json_response(true, $local['message']);
	}
	voicemail_json_response(false, $local['message']);
}

if (!\creamy\GoHttpClient::canPost()) {
	$local = add_voicemail_local($voicemailId, $password, $name, $email, $userGroup, $active);
	if ($local['ok']) {
		voicemail_json_response(true, $local['message']);
	}
	voicemail_json_response(false, 'Cannot contact goAPI and local save failed: ' . $local['message']);
}

$api = \creamy\APIHandler::getInstance();
$postfields = array(
	'goAction' => 'goAddVoicemail',
	'voicemail_id' => $voicemailId,
	'pass' => $password,
	'fullname' => $name,
	'email' => $email,
	'user_group' => $userGroup,
	'active' => $active,
);

$output = $api->API_addVoicemail($postfields);

if (is_object($output) && isset($output->result) && $output->result === 'success') {
	voicemail_json_response(true);
}

// goAPI failed — try local
$local = add_voicemail_local($voicemailId, $password, $name, $email, $userGroup, $active);
if ($local['ok']) {
	voicemail_json_response(true, $local['message']);
}

$err = 'unknown error';
if (is_object($output)) {
	if (isset($output->message) && is_string($output->message)) {
		$err = $output->message;
	} elseif (isset($output->result) && is_string($output->result)) {
		$err = $output->result;
	}
}
voicemail_json_response(false, $lh->translationFor('something_went_wrong') . ' ' . $err);
