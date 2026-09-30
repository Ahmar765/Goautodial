<?php
/**
 * @file        AddList.php
 * @brief       Handles Add List Request
 * @copyright   Copyright (C) GOautodial Inc.
 */

require_once('CRMDefaults.php');
require_once('LanguageHandler.php');
require_once('APIHandler.php');
require_once('GoHttpClient.php');
require('Session.php');

header('Content-Type: application/json; charset=utf-8');

$lh = \creamy\LanguageHandler::getInstance();

function add_list_json_response($ok, $message = '', $extra = null) {
	$payload = array('ok' => $ok ? 1 : 0, 'status' => $ok ? 1 : 0);
	if ($message !== '') {
		$payload['message'] = $message;
	}
	if ($extra !== null) {
		$payload['detail'] = $extra;
	}
	echo json_encode($payload);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	add_list_json_response(false, 'Invalid request.');
}

$listId = isset($_POST['add_list_id']) ? trim((string) $_POST['add_list_id']) : '';
$listName = isset($_POST['list_name']) ? trim((string) $_POST['list_name']) : '';

if ($listId === '' || !ctype_digit($listId)) {
	add_list_json_response(false, $lh->translationFor('some_fields_missing') . ' (list ID).');
}
if ($listName === '') {
	add_list_json_response(false, $lh->translationFor('some_fields_missing') . ' (list name).');
}

if (!\creamy\GoHttpClient::canPost()) {
	add_list_json_response(false, 'Cannot contact goAPI (HTTP disabled). Enable PHP cURL or allow_url_fopen and set GO_API_BASE_URL.');
}

$api = \creamy\APIHandler::getInstance();

$postfields = array(
	'goAction' => 'goAddList',
	'list_id' => $listId,
	'list_name' => $listName,
	'list_description' => isset($_POST['list_desc']) ? $_POST['list_desc'] : '',
	'campaign_id' => isset($_POST['campaign_select']) ? $_POST['campaign_select'] : '',
	'active' => isset($_POST['status']) ? $_POST['status'] : 'Y',
);

$output = $api->API_addList($postfields);

if (!is_object($output) || !isset($output->result)) {
	$base = defined('gourl') ? gourl : 'http://localhost/goAPIv2';
	$probe = \creamy\GoHttpClient::post($base . '/goLists/goAPI.php', array(
		'goAction' => 'goGetAllLists',
		'goUser' => defined('goUser') ? goUser : 'goAPI',
		'goPass' => defined('goPass') ? goPass : '',
		'responsetype' => 'json',
		'session_user' => defined('goUser') ? goUser : 'goAPI',
		'log_user' => defined('goUser') ? goUser : 'goAPI',
		'log_group' => 'ADMIN',
		'log_ip' => '127.0.0.1',
	), 5);
	$reachable = is_string($probe) && strlen($probe) > 0 && ($probe[0] === '{' || $probe[0] === '[');
	if ($reachable) {
		add_list_json_response(
			false,
			'Add List failed (goAPIv2 is reachable). Run php/seed_goapi_local.php to create vicidial_lists, pick an existing campaign, then retry. See php/goapi-status.php.'
		);
	}
	add_list_json_response(
		false,
		'goAPI did not respond at ' . $base . '. Install goAPIv2 or set GO_API_BASE_URL in .env (see php/goapi-status.php).'
	);
}

if ($output->result === 'success') {
	add_list_json_response(true);
}

$err = is_string($output->result) ? $output->result : 'unknown error';
if (isset($output->message) && is_string($output->message)) {
	$err = $output->message;
}
add_list_json_response(false, $lh->translationFor('something_went_wrong') . ': ' . $err);
