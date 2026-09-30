<?php
/**
 * @file        DeleteList.php
 * @brief       Handles Delete List Requests (local DB + optional goAPI)
 * @copyright   Copyright (c) 2018 GOautodial Inc.
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
*/

require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/APIHandler.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Normalize listid from POST (string, int, or jQuery array of ids).
 * @return int[]
 */
function delete_list_normalize_ids($raw) {
	$ids = array();
	if (is_array($raw)) {
		foreach ($raw as $v) {
			if (is_array($v)) {
				$v = isset($v['id']) ? $v['id'] : (isset($v[0]) ? $v[0] : '');
			}
			$v = trim((string) $v);
			if ($v !== '' && ctype_digit($v)) {
				$id = (int) $v;
				if ($id > 0 && !in_array($id, $ids, true)) {
					$ids[] = $id;
				}
			}
		}
	} else {
		$v = trim((string) $raw);
		if ($v !== '') {
			// comma-separated or single
			foreach (preg_split('/[,\s]+/', $v) as $part) {
				$part = trim($part);
				if ($part !== '' && ctype_digit($part)) {
					$id = (int) $part;
					if ($id > 0 && !in_array($id, $ids, true)) {
						$ids[] = $id;
					}
				}
			}
		}
	}
	return $ids;
}

function delete_list_local(array $listIds) {
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

	$deletedLists = 0;
	$deletedLeads = 0;
	$skipped = array();

	foreach ($listIds as $listId) {
		// Protect system lists
		if (in_array($listId, array(998, 999), true)) {
			$skipped[] = $listId;
			continue;
		}

		$stmt = $mysqli->prepare('DELETE FROM vicidial_list WHERE list_id = ?');
		if ($stmt) {
			$stmt->bind_param('i', $listId);
			$stmt->execute();
			$deletedLeads += max(0, (int) $stmt->affected_rows);
			$stmt->close();
		}

		$stmt = $mysqli->prepare('DELETE FROM vicidial_lists WHERE list_id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('i', $listId);
			$stmt->execute();
			$deletedLists += max(0, (int) $stmt->affected_rows);
			$stmt->close();
		}

		// Best-effort cleanup of hopper rows for this list
		@$mysqli->query('DELETE FROM vicidial_hopper WHERE list_id = ' . (int) $listId);
	}

	$mysqli->close();

	if ($deletedLists < 1 && empty($skipped)) {
		return array('ok' => false, 'message' => 'List not found or already deleted.');
	}
	if ($deletedLists < 1 && !empty($skipped)) {
		return array('ok' => false, 'message' => 'Cannot delete protected system lists (' . implode(',', $skipped) . ').');
	}

	return array(
		'ok' => true,
		'message' => "Deleted {$deletedLists} list(s), {$deletedLeads} lead(s).",
		'deleted_lists' => $deletedLists,
		'deleted_leads' => $deletedLeads,
	);
}

$rawListid = null;
if (isset($_POST['listid'])) {
	$rawListid = $_POST['listid'];
} elseif (isset($_POST['list_id'])) {
	$rawListid = $_POST['list_id'];
} elseif (isset($_REQUEST['listid'])) {
	$rawListid = $_REQUEST['listid'];
}

$listIds = delete_list_normalize_ids($rawListid);
if (empty($listIds)) {
	echo json_encode('Error: No list id provided');
	exit;
}

$localFallback = defined('CRM_LOGIN_LOCAL_DB_FALLBACK') && CRM_LOGIN_LOCAL_DB_FALLBACK === true;

// Prefer local delete on XAMPP / when goAPI is offline
if ($localFallback) {
	$local = delete_list_local($listIds);
	if ($local['ok']) {
		echo json_encode(1);
		exit;
	}
	echo json_encode('Error: ' . $local['message']);
	exit;
}

// Remote goAPI path
$api = \creamy\APIHandler::getInstance();
$postfields = array(
	'goAction' => 'goDeleteList',
	'list_id' => implode(',', $listIds),
	'action' => 'delete_selected',
);
$output = $api->API_Request('goLists', $postfields);

if (is_object($output) && isset($output->result) && $output->result === 'success') {
	echo json_encode(1);
	exit;
}

// Fallback to local if goAPI failed
$local = delete_list_local($listIds);
if ($local['ok']) {
	echo json_encode(1);
	exit;
}

$apiMsg = (is_object($output) && isset($output->result)) ? (string) $output->result : 'Delete failed';
echo json_encode($apiMsg);
