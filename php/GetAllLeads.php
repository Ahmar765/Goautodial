<?php
/**
 * JSON: all leads from asterisk.vicidial_list for admin table.
 * Uses the same DB session handler as the rest of the CRM.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/SessionHandler.php';

// Same session backend as APIHandler / Session.php (database), but no login redirect
new \creamy\SessionHandler();

header('Content-Type: application/json; charset=utf-8');

function leads_api_out($payload) {
	$flags = JSON_UNESCAPED_UNICODE;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	}
	$json = json_encode($payload, $flags);
	if ($json === false) {
		$json = json_encode(array(
			'ok' => 0,
			'data' => array(),
			'message' => 'JSON encode failed: ' . json_last_error_msg(),
		));
	}
	echo $json;
	exit;
}

if (empty($_SESSION['username']) || empty($_SESSION['userid'])) {
	leads_api_out(array(
		'ok' => 0,
		'data' => array(),
		'message' => 'Not logged in (session). Refresh the page or log in again.',
	));
}

$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';

$mysqli = @new mysqli($host, $dbUser, $dbPass, $dbName, $port);
if ($mysqli->connect_errno) {
	leads_api_out(array('ok' => 0, 'data' => array(), 'message' => 'DB: ' . $mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');

$listId = isset($_REQUEST['list_id']) ? trim((string) $_REQUEST['list_id']) : '';
$status = isset($_REQUEST['status']) ? trim((string) $_REQUEST['status']) : '';
$q = isset($_REQUEST['q']) ? trim((string) $_REQUEST['q']) : '';
$limit = isset($_REQUEST['limit']) ? (int) $_REQUEST['limit'] : 2000;
if ($limit < 1 || $limit > 10000) {
	$limit = 2000;
}

$sql = "SELECT l.lead_id, l.list_id, COALESCE(ls.list_name, '') AS list_name,
	l.phone_number, l.first_name, l.middle_initial, l.last_name, l.email,
	l.city, l.state, l.status, l.vendor_lead_code, l.entry_date, l.alt_phone, l.comments
	FROM vicidial_list l
	LEFT JOIN vicidial_lists ls ON ls.list_id = l.list_id
	WHERE 1=1";
$types = '';
$params = array();

if ($listId !== '' && ctype_digit($listId)) {
	$sql .= ' AND l.list_id = ?';
	$types .= 'i';
	$params[] = (int) $listId;
}
if ($status !== '') {
	$sql .= ' AND l.status = ?';
	$types .= 's';
	$params[] = $status;
}
if ($q !== '') {
	$sql .= ' AND (l.phone_number LIKE ? OR l.first_name LIKE ? OR l.last_name LIKE ? OR l.email LIKE ? OR CAST(l.lead_id AS CHAR) LIKE ?)';
	$like = '%' . $q . '%';
	$types .= 'sssss';
	array_push($params, $like, $like, $like, $like, $like);
}

$sql .= ' ORDER BY l.lead_id DESC LIMIT ' . (int) $limit;

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
	leads_api_out(array('ok' => 0, 'data' => array(), 'message' => 'SQL: ' . $mysqli->error));
}
if ($types !== '') {
	$bind = array($types);
	foreach ($params as $k => $v) {
		$bind[] = &$params[$k];
	}
	call_user_func_array(array($stmt, 'bind_param'), $bind);
}

if (!$stmt->execute()) {
	leads_api_out(array('ok' => 0, 'data' => array(), 'message' => 'Execute: ' . $stmt->error));
}

$rows = array();
if (method_exists($stmt, 'get_result')) {
	$result = $stmt->get_result();
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$clean = array();
			foreach ($row as $k => $v) {
				$clean[$k] = ($v === null) ? '' : $v;
			}
			$rows[] = $clean;
		}
	}
} else {
	$meta = $stmt->result_metadata();
	$fields = array();
	$row = array();
	$bindResult = array();
	while ($field = $meta->fetch_field()) {
		$fields[] = $field->name;
		$row[$field->name] = null;
		$bindResult[] = &$row[$field->name];
	}
	call_user_func_array(array($stmt, 'bind_result'), $bindResult);
	while ($stmt->fetch()) {
		$copy = array();
		foreach ($fields as $name) {
			$copy[$name] = $row[$name];
		}
		$rows[] = $copy;
	}
}
$stmt->close();

$totalSql = 'SELECT COUNT(*) AS c FROM vicidial_list';
if ($listId !== '' && ctype_digit($listId)) {
	$totalSql .= ' WHERE list_id = ' . (int) $listId;
}
$total = count($rows);
$countRes = $mysqli->query($totalSql);
if ($countRes) {
	$total = (int) $countRes->fetch_assoc()['c'];
}
$mysqli->close();

leads_api_out(array(
	'ok' => 1,
	'total' => $total,
	'count' => count($rows),
	'data' => $rows,
));
