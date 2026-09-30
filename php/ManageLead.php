<?php
/**
 * Manage leads in asterisk.vicidial_list
 * Actions: get | update | delete | delete_all
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/SessionHandler.php';

new \creamy\SessionHandler();

header('Content-Type: application/json; charset=utf-8');

function lead_out($payload) {
	$flags = JSON_UNESCAPED_UNICODE;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	}
	echo json_encode($payload, $flags);
	exit;
}

if (empty($_SESSION['username']) || empty($_SESSION['userid'])) {
	lead_out(array('ok' => 0, 'message' => 'Not logged in.'));
}

$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';

$mysqli = @new mysqli($host, $dbUser, $dbPass, $dbName, $port);
if ($mysqli->connect_errno) {
	lead_out(array('ok' => 0, 'message' => 'DB: ' . $mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');

$action = isset($_REQUEST['action']) ? strtolower(trim((string) $_REQUEST['action'])) : '';

if ($action === 'get') {
	$leadId = isset($_REQUEST['lead_id']) ? (int) $_REQUEST['lead_id'] : 0;
	if ($leadId < 1) {
		lead_out(array('ok' => 0, 'message' => 'lead_id required.'));
	}
	$stmt = $mysqli->prepare('SELECT lead_id, list_id, phone_number, phone_code, title, first_name, middle_initial, last_name,
		address1, address2, address3, city, state, province, postal_code, country_code, gender, date_of_birth,
		alt_phone, email, security_phrase, comments, status, vendor_lead_code, entry_date
		FROM vicidial_list WHERE lead_id = ? LIMIT 1');
	$stmt->bind_param('i', $leadId);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$stmt->close();
	$mysqli->close();
	if (!$row) {
		lead_out(array('ok' => 0, 'message' => 'Lead not found.'));
	}
	lead_out(array('ok' => 1, 'lead' => $row));
}

if ($action === 'delete') {
	$leadId = isset($_REQUEST['lead_id']) ? (int) $_REQUEST['lead_id'] : 0;
	if ($leadId < 1) {
		lead_out(array('ok' => 0, 'message' => 'lead_id required.'));
	}
	$stmt = $mysqli->prepare('DELETE FROM vicidial_list WHERE lead_id = ? LIMIT 1');
	$stmt->bind_param('i', $leadId);
	$ok = $stmt->execute();
	$affected = $stmt->affected_rows;
	$stmt->close();
	$mysqli->close();
	if (!$ok || $affected < 1) {
		lead_out(array('ok' => 0, 'message' => 'Delete failed or lead not found.'));
	}
	lead_out(array('ok' => 1, 'message' => "Deleted lead {$leadId}.", 'deleted' => $affected));
}

if ($action === 'delete_all') {
	$listId = isset($_REQUEST['list_id']) ? trim((string) $_REQUEST['list_id']) : '';
	$confirm = isset($_REQUEST['confirm']) ? trim((string) $_REQUEST['confirm']) : '';
	if ($confirm !== 'DELETE') {
		lead_out(array('ok' => 0, 'message' => 'Type confirm=DELETE to proceed.'));
	}

	if ($listId !== '' && ctype_digit($listId)) {
		$stmt = $mysqli->prepare('DELETE FROM vicidial_list WHERE list_id = ?');
		$listInt = (int) $listId;
		$stmt->bind_param('i', $listInt);
		$ok = $stmt->execute();
		$affected = $stmt->affected_rows;
		$stmt->close();
		$mysqli->close();
		if (!$ok) {
			lead_out(array('ok' => 0, 'message' => 'Delete all failed.'));
		}
		lead_out(array('ok' => 1, 'message' => "Deleted {$affected} leads from list {$listId}.", 'deleted' => $affected));
	}

	// Dangerous: all leads in system
	$ok = $mysqli->query('DELETE FROM vicidial_list');
	$affected = $mysqli->affected_rows;
	$mysqli->close();
	if (!$ok) {
		lead_out(array('ok' => 0, 'message' => 'Delete all failed.'));
	}
	lead_out(array('ok' => 1, 'message' => "Deleted {$affected} leads (all lists).", 'deleted' => $affected));
}

if ($action === 'update') {
	$leadId = isset($_POST['lead_id']) ? (int) $_POST['lead_id'] : 0;
	if ($leadId < 1) {
		lead_out(array('ok' => 0, 'message' => 'lead_id required.'));
	}

	$phone = isset($_POST['phone_number']) ? preg_replace('/[^0-9]/', '', (string) $_POST['phone_number']) : '';
	if ($phone === '') {
		lead_out(array('ok' => 0, 'message' => 'Phone number is required.'));
	}

	$fields = array(
		'list_id' => isset($_POST['list_id']) ? (int) $_POST['list_id'] : 0,
		'phone_number' => substr($phone, 0, 20),
		'phone_code' => substr(isset($_POST['phone_code']) ? (string) $_POST['phone_code'] : '1', 0, 10),
		'title' => substr(isset($_POST['title']) ? (string) $_POST['title'] : '', 0, 4),
		'first_name' => substr(isset($_POST['first_name']) ? (string) $_POST['first_name'] : '', 0, 30),
		'middle_initial' => substr(isset($_POST['middle_initial']) ? (string) $_POST['middle_initial'] : '', 0, 1),
		'last_name' => substr(isset($_POST['last_name']) ? (string) $_POST['last_name'] : '', 0, 30),
		'address1' => substr(isset($_POST['address1']) ? (string) $_POST['address1'] : '', 0, 100),
		'address2' => substr(isset($_POST['address2']) ? (string) $_POST['address2'] : '', 0, 100),
		'address3' => substr(isset($_POST['address3']) ? (string) $_POST['address3'] : '', 0, 100),
		'city' => substr(isset($_POST['city']) ? (string) $_POST['city'] : '', 0, 50),
		'state' => substr(isset($_POST['state']) ? (string) $_POST['state'] : '', 0, 2),
		'province' => substr(isset($_POST['province']) ? (string) $_POST['province'] : '', 0, 50),
		'postal_code' => substr(isset($_POST['postal_code']) ? (string) $_POST['postal_code'] : '', 0, 10),
		'country_code' => substr(isset($_POST['country_code']) ? (string) $_POST['country_code'] : '', 0, 3),
		'gender' => '',
		'date_of_birth' => null,
		'alt_phone' => substr(isset($_POST['alt_phone']) ? preg_replace('/[^0-9]/', '', (string) $_POST['alt_phone']) : '', 0, 20),
		'email' => substr(isset($_POST['email']) ? (string) $_POST['email'] : '', 0, 70),
		'security_phrase' => substr(isset($_POST['security_phrase']) ? (string) $_POST['security_phrase'] : '', 0, 100),
		'comments' => substr(isset($_POST['comments']) ? (string) $_POST['comments'] : '', 0, 255),
		'status' => substr(isset($_POST['status']) ? (string) $_POST['status'] : 'NEW', 0, 6),
		'vendor_lead_code' => substr(isset($_POST['vendor_lead_code']) ? (string) $_POST['vendor_lead_code'] : '', 0, 20),
	);

	$g = strtoupper(substr(isset($_POST['gender']) ? (string) $_POST['gender'] : 'U', 0, 1));
	$fields['gender'] = in_array($g, array('M', 'F', 'U'), true) ? $g : 'U';

	$dobRaw = isset($_POST['date_of_birth']) ? trim((string) $_POST['date_of_birth']) : '';
	if ($dobRaw !== '' && ($ts = strtotime($dobRaw)) !== false) {
		$fields['date_of_birth'] = date('Y-m-d', $ts);
	} else {
		$fields['date_of_birth'] = null;
	}

	if ($fields['list_id'] < 1) {
		lead_out(array('ok' => 0, 'message' => 'Valid list_id is required.'));
	}

	$sql = 'UPDATE vicidial_list SET
		list_id=?, phone_number=?, phone_code=?, title=?, first_name=?, middle_initial=?, last_name=?,
		address1=?, address2=?, address3=?, city=?, state=?, province=?, postal_code=?, country_code=?,
		gender=?, date_of_birth=?, alt_phone=?, email=?, security_phrase=?, comments=?, status=?, vendor_lead_code=?
		WHERE lead_id=? LIMIT 1';

	$stmt = $mysqli->prepare($sql);
	if (!$stmt) {
		lead_out(array('ok' => 0, 'message' => 'Prepare failed: ' . $mysqli->error));
	}

	$dobBind = $fields['date_of_birth'] === null ? '' : $fields['date_of_birth'];
	$stmt->bind_param(
		'issssssssssssssssssssssi',
		$fields['list_id'],
		$fields['phone_number'],
		$fields['phone_code'],
		$fields['title'],
		$fields['first_name'],
		$fields['middle_initial'],
		$fields['last_name'],
		$fields['address1'],
		$fields['address2'],
		$fields['address3'],
		$fields['city'],
		$fields['state'],
		$fields['province'],
		$fields['postal_code'],
		$fields['country_code'],
		$fields['gender'],
		$dobBind,
		$fields['alt_phone'],
		$fields['email'],
		$fields['security_phrase'],
		$fields['comments'],
		$fields['status'],
		$fields['vendor_lead_code'],
		$leadId
	);

	$ok = $stmt->execute();
	$err = $stmt->error;
	$stmt->close();
	$mysqli->close();

	if (!$ok) {
		lead_out(array('ok' => 0, 'message' => 'Update failed: ' . $err));
	}
	lead_out(array('ok' => 1, 'message' => "Lead {$leadId} updated."));
}

$mysqli->close();
lead_out(array('ok' => 0, 'message' => 'Unknown action. Use get, update, delete, or delete_all.'));
