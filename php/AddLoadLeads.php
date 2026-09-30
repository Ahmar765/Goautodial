<?php
/**
 * @file        AddLoadLeads.php
 * @brief       Bulk upload leads into asterisk.vicidial_list
 */
require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';

$cliImport = (PHP_SAPI === 'cli' && getenv('LEAD_UPLOAD_CLI') === '1');

if (!$cliImport) {
	require_once __DIR__ . '/SessionHandler.php';
	new \creamy\SessionHandler();
}

ini_set('memory_limit', '2048M');
ini_set('upload_max_filesize', '600M');
ini_set('post_max_size', '600M');
ini_set('max_execution_time', '3600');

if (!$cliImport) {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store, no-cache, must-revalidate');
}

function leads_json($payload) {
	$flags = JSON_UNESCAPED_UNICODE;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	}
	echo json_encode($payload, $flags);
	exit;
}

if (!$cliImport && (empty($_SESSION['username']) || empty($_SESSION['userid']))) {
	leads_json(array('result' => 'error', 'msg' => 'Not logged in. Refresh and try again.'));
}

if (!$cliImport && $_SERVER['REQUEST_METHOD'] !== 'POST') {
	leads_json(array('result' => 'error', 'msg' => 'Invalid request.'));
}

if (!isset($_FILES['file_upload']) || !is_array($_FILES['file_upload'])) {
	leads_json(array('result' => 'error', 'msg' => 'File upload failed or no file provided.'));
}
$uploadErr = isset($_FILES['file_upload']['error']) ? (int) $_FILES['file_upload']['error'] : UPLOAD_ERR_NO_FILE;
if ($uploadErr !== UPLOAD_ERR_OK) {
	leads_json(array('result' => 'error', 'msg' => 'File upload error code: ' . $uploadErr));
}

$list_id = isset($_REQUEST['list_id']) ? trim((string) $_REQUEST['list_id']) : '';
$dupcheck = isset($_REQUEST['goDupcheck']) ? trim((string) $_REQUEST['goDupcheck']) : 'CHECK_NUM_AND_LIST';
$phone_code_override = isset($_REQUEST['phone_code_override']) ? trim((string) $_REQUEST['phone_code_override']) : '1';
$updateExisting = !isset($_REQUEST['update_existing']) || $_REQUEST['update_existing'] !== '0';

if ($list_id === '' || !ctype_digit($list_id)) {
	leads_json(array('result' => 'error', 'msg' => 'Target List ID is required (numeric).'));
}

$tmp = $_FILES['file_upload']['tmp_name'];
$raw = @file_get_contents($tmp);
if ($raw === false || $raw === '') {
	leads_json(array('result' => 'error', 'msg' => 'Uploaded file is empty.'));
}
// Strip UTF-8 BOM
if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
	$raw = substr($raw, 3);
}
// Normalize newlines
$raw = str_replace(array("\r\n", "\r"), "\n", $raw);

$tmpCsv = tempnam(sys_get_temp_dir(), 'leads');
file_put_contents($tmpCsv, $raw);
$handle = @fopen($tmpCsv, 'r');
if ($handle === false) {
	@unlink($tmpCsv);
	leads_json(array('result' => 'error', 'msg' => 'Could not read uploaded file.'));
}

$header = fgetcsv($handle);
if (!is_array($header) || count($header) < 1) {
	fclose($handle);
	@unlink($tmpCsv);
	leads_json(array('result' => 'error', 'msg' => 'CSV header row is missing.'));
}

$header = array_map(function ($h) {
	$h = (string) $h;
	$h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
	return strtolower(trim($h));
}, $header);

$namedMap = array();
$aliasToField = array(
	'phone_number' => 'phone_number', 'phone' => 'phone_number', 'mobile' => 'phone_number',
	'telephone' => 'phone_number', 'cell' => 'phone_number', 'phonenumber' => 'phone_number',
	'mobilenumber' => 'phone_number', 'contactnumber' => 'phone_number', 'number' => 'phone_number',
	'phoneno' => 'phone_number', 'tel' => 'phone_number', 'msisdn' => 'phone_number',
	'first_name' => 'first_name', 'firstname' => 'first_name', 'fname' => 'first_name',
	'givenname' => 'first_name', 'name' => 'first_name', 'fullname' => 'first_name',
	'fullname' => 'first_name', 'customername' => 'first_name', 'contactname' => 'first_name',
	'last_name' => 'last_name', 'lastname' => 'last_name', 'lname' => 'last_name', 'surname' => 'last_name',
	'email' => 'email', 'emailaddress' => 'email', 'mail' => 'email', 'emailid' => 'email',
	'middle_initial' => 'middle_initial', 'middle' => 'middle_initial', 'mi' => 'middle_initial',
	'alt_phone' => 'alt_phone', 'altphone' => 'alt_phone', 'phone2' => 'alt_phone',
	'city' => 'city', 'state' => 'state', 'postal_code' => 'postal_code', 'zip' => 'postal_code',
	'zipcode' => 'postal_code', 'country_code' => 'country_code', 'country' => 'country_code',
	'address1' => 'address1', 'address' => 'address1', 'address2' => 'address2',
	'comments' => 'comments', 'notes' => 'comments', 'vendor_lead_code' => 'vendor_lead_code',
	'province' => 'province', 'gender' => 'gender', 'date_of_birth' => 'date_of_birth', 'dob' => 'date_of_birth',
	'title' => 'title', 'phone_code' => 'phone_code', 'security_phrase' => 'security_phrase',
);

$fullNameCol = null;
foreach ($header as $i => $name) {
	$clean = preg_replace('/[^a-z0-9]/', '', $name);
	if ($clean === '') {
		continue;
	}
	if (isset($aliasToField[$clean])) {
		$field = $aliasToField[$clean];
		if (!isset($namedMap[$field])) {
			$namedMap[$field] = $i;
		}
		if (in_array($clean, array('name', 'fullname', 'fullname', 'customername', 'contactname'), true)) {
			$fullNameCol = $i;
		}
	} elseif (strpos($clean, 'email') !== false && !isset($namedMap['email'])) {
		$namedMap['email'] = $i;
	} elseif ((strpos($clean, 'phone') !== false || strpos($clean, 'mobile') !== false || strpos($clean, 'tel') !== false) && !isset($namedMap['phone_number'])) {
		$namedMap['phone_number'] = $i;
	}
}
$useNamed = isset($namedMap['phone_number']) || count(array_intersect($header, array('phone_number', 'first_name', 'email', 'last_name'))) > 0;
$splitFullName = ($fullNameCol !== null && !isset($namedMap['last_name']));

$pos = array(
	'phone_number' => 0, 'vendor_lead_code' => 1, 'phone_code' => 2, 'title' => 3,
	'first_name' => 4, 'middle_initial' => 5, 'last_name' => 6, 'address1' => 7,
	'address2' => 8, 'address3' => 9, 'city' => 10, 'state' => 11, 'province' => 12,
	'postal_code' => 13, 'country_code' => 14, 'gender' => 15, 'date_of_birth' => 16,
	'alt_phone' => 17, 'email' => 18, 'security_phrase' => 19, 'comments' => 20,
);

$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';

$mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
if ($mysqli->connect_errno) {
	fclose($handle);
	@unlink($tmpCsv);
	leads_json(array('result' => 'error', 'msg' => 'Database connection failed: ' . $mysqli->connect_error));
}
$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET SESSION sql_mode = ''");

$listCheck = $mysqli->prepare('SELECT list_id FROM vicidial_lists WHERE list_id = ? LIMIT 1');
$listCheck->bind_param('s', $list_id);
$listCheck->execute();
$listCheck->store_result();
if ($listCheck->num_rows < 1) {
	$listCheck->close();
	$mysqli->close();
	fclose($handle);
	@unlink($tmpCsv);
	leads_json(array('result' => 'error', 'msg' => "List ID {$list_id} does not exist. Create the list first."));
}
$listCheck->close();

function cell_val(array $row, $idx) {
	if ($idx === null || $idx === '' || !isset($row[$idx])) {
		return '';
	}
	return trim((string) $row[$idx]);
}

function normalize_phone($raw) {
	$raw = trim((string) $raw);
	if ($raw === '') {
		return '';
	}
	// Excel scientific notation e.g. 3.101E+12
	if (preg_match('/^\d+(\.\d+)?e[+\-]?\d+$/i', $raw)) {
		$raw = sprintf('%.0f', (float) $raw);
	}
	// Strip trailing .0 from floats
	if (preg_match('/^\d+\.0+$/', $raw)) {
		$raw = preg_replace('/\.0+$/', '', $raw);
	}
	$digits = preg_replace('/\D+/', '', $raw);
	// Drop leading country trunk zeros only if absurdly long — keep as-is otherwise
	if (strlen($digits) > 20) {
		$digits = substr($digits, -20);
	}
	return $digits;
}

function looks_like_phone($raw) {
	$p = normalize_phone($raw);
	return (strlen($p) >= 7 && strlen($p) <= 20);
}

$insertSql = 'INSERT INTO vicidial_list (
	entry_date, status, vendor_lead_code, list_id, gmt_offset_now, phone_code, phone_number,
	title, first_name, middle_initial, last_name, address1, address2, address3, city, state,
	province, postal_code, country_code, gender, date_of_birth, alt_phone, email, security_phrase,
	comments, entry_list_id
) VALUES (?,?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)';

$updateSql = 'UPDATE vicidial_list SET
	vendor_lead_code=?, phone_code=?, title=?, first_name=?, middle_initial=?, last_name=?,
	address1=?, address2=?, address3=?, city=?, state=?, province=?, postal_code=?, country_code=?,
	gender=?, date_of_birth=?, alt_phone=?, email=?, security_phrase=?, comments=?
	WHERE lead_id=? LIMIT 1';

$ins = $mysqli->prepare($insertSql);
$upd = $mysqli->prepare($updateSql);
if (!$ins || !$upd) {
	fclose($handle);
	@unlink($tmpCsv);
	$mysqli->close();
	leads_json(array('result' => 'error', 'msg' => 'Prepare failed: ' . $mysqli->error));
}

$inserted = 0;
$updated = 0;
$duplicates = 0;
$skipped = 0;
$errors = array();
$sampleRows = array();
$rowNum = 1; // header already read
$now = date('Y-m-d H:i:s');
$listIdInt = (int) $list_id;

while (($row = fgetcsv($handle)) !== false) {
	$rowNum++;
	if (!is_array($row) || !count(array_filter($row, function ($v) { return trim((string) $v) !== ''; }))) {
		continue;
	}

	// Pad short rows to header width (SheetJS sparse rows)
	if (count($row) < count($header)) {
		$row = array_pad($row, count($header), '');
	}

	if (count($sampleRows) < 3) {
		$sampleRows[] = $row;
	}

	$get = function ($key) use ($useNamed, $namedMap, $pos, $row) {
		if ($useNamed) {
			return cell_val($row, isset($namedMap[$key]) ? $namedMap[$key] : null);
		}
		return cell_val($row, isset($pos[$key]) ? $pos[$key] : null);
	};

	$phone = normalize_phone($get('phone_number'));
	// Fallback: scan row for a phone-like cell if mapped phone is empty
	if ($phone === '' || !looks_like_phone($phone)) {
		foreach ($row as $cell) {
			if (looks_like_phone($cell)) {
				$phone = normalize_phone($cell);
				break;
			}
		}
	}
	if ($phone === '') {
		$skipped++;
		if (count($errors) < 8) {
			$errors[] = "Row {$rowNum}: empty/invalid phone";
		}
		continue;
	}
	$phone = substr($phone, 0, 20);

	$vendor = substr($get('vendor_lead_code'), 0, 20);
	$phoneCode = $phone_code_override !== '' ? $phone_code_override : $get('phone_code');
	if ($phoneCode === '') {
		$phoneCode = '1';
	}
	$title = substr($get('title'), 0, 4);
	$first = substr($get('first_name'), 0, 30);
	$middle = substr($get('middle_initial'), 0, 1);
	$last = substr($get('last_name'), 0, 30);

	if (!empty($splitFullName) && $last === '' && $fullNameCol !== null) {
		$full = cell_val($row, $fullNameCol);
		$parts = preg_split('/\s+/', $full, -1, PREG_SPLIT_NO_EMPTY);
		if (count($parts) >= 1) {
			$first = substr($parts[0], 0, 30);
		}
		if (count($parts) >= 2) {
			$last = substr(implode(' ', array_slice($parts, 1)), 0, 30);
		}
	} elseif ($fullNameCol !== null && $last === '' && strpos($first, ' ') !== false) {
		$parts = preg_split('/\s+/', $first, -1, PREG_SPLIT_NO_EMPTY);
		if (count($parts) >= 2) {
			$first = substr($parts[0], 0, 30);
			$last = substr(implode(' ', array_slice($parts, 1)), 0, 30);
		}
	}

	$a1 = substr($get('address1'), 0, 100);
	$a2 = substr($get('address2'), 0, 100);
	$a3 = substr($get('address3'), 0, 100);
	$city = substr($get('city'), 0, 50);
	$state = substr($get('state'), 0, 2);
	$province = substr($get('province'), 0, 50);
	$postal = substr($get('postal_code'), 0, 10);
	$country = substr($get('country_code'), 0, 3);
	$genderRaw = strtoupper(substr($get('gender'), 0, 1));
	$gender = in_array($genderRaw, array('M', 'F'), true) ? $genderRaw : 'U';
	$dobRaw = $get('date_of_birth');
	$dobBind = '0000-00-00';
	if ($dobRaw !== '' && ($ts = strtotime($dobRaw)) !== false) {
		$dobBind = date('Y-m-d', $ts);
	}
	$alt = substr(normalize_phone($get('alt_phone')), 0, 20);
	$email = substr($get('email'), 0, 70);
	$phrase = substr($get('security_phrase'), 0, 100);
	$comments = substr($get('comments'), 0, 255);
	$status = 'NEW';

	$existingId = 0;
	if ($dupcheck !== 'NONE' && $dupcheck !== '') {
		$chk = $mysqli->prepare('SELECT lead_id FROM vicidial_list WHERE phone_number = ? AND list_id = ? LIMIT 1');
		$chk->bind_param('si', $phone, $listIdInt);
		$chk->execute();
		$chk->bind_result($existingId);
		$chk->fetch();
		$chk->close();
		$existingId = (int) $existingId;

		if ($existingId < 1 && ($dupcheck === 'CHECK_NUM_LIST_AND_SYSTEM' || $dupcheck === 'DUPSYS')) {
			$chk = $mysqli->prepare('SELECT lead_id FROM vicidial_list WHERE phone_number = ? LIMIT 1');
			$chk->bind_param('s', $phone);
			$chk->execute();
			$chk->bind_result($existingId);
			$chk->fetch();
			$chk->close();
			$existingId = (int) $existingId;
		}
	}

	if ($existingId > 0) {
		if ($updateExisting) {
			$upd->bind_param(
				'ssssssssssssssssssssi',
				$vendor, $phoneCode, $title, $first, $middle, $last,
				$a1, $a2, $a3, $city, $state, $province, $postal, $country,
				$gender, $dobBind, $alt, $email, $phrase, $comments, $existingId
			);
			if ($upd->execute()) {
				$updated++;
			} else {
				$skipped++;
				if (count($errors) < 8) {
					$errors[] = "Row {$rowNum}: update failed — " . $upd->error;
				}
			}
		} else {
			$duplicates++;
		}
		continue;
	}

	$okBind = $ins->bind_param(
		'sssissssssssssssssssssss',
		$now, $status, $vendor, $listIdInt, $phoneCode, $phone,
		$title, $first, $middle, $last, $a1, $a2, $a3, $city, $state,
		$province, $postal, $country, $gender, $dobBind, $alt, $email, $phrase, $comments
	);
	if (!$okBind) {
		$skipped++;
		if (count($errors) < 8) {
			$errors[] = "Row {$rowNum}: bind failed — " . $ins->error;
		}
		continue;
	}

	if ($ins->execute()) {
		$inserted++;
	} else {
		$skipped++;
		if (count($errors) < 8) {
			$errors[] = "Row {$rowNum}: insert failed — " . $ins->error;
		}
	}
}

fclose($handle);
@unlink($tmpCsv);
$ins->close();
$upd->close();
$mysqli->close();

$total = $inserted + $updated + $duplicates + $skipped;
$msgParts = array();
$msgParts[] = "Inserted {$inserted}";
if ($updated > 0) {
	$msgParts[] = "updated {$updated} (name/email refreshed)";
}
if ($duplicates > 0) {
	$msgParts[] = "skipped {$duplicates} duplicates";
}
if ($skipped > 0) {
	$msgParts[] = "skipped {$skipped} invalid/failed rows";
}
$msg = implode(', ', $msgParts) . " into list {$list_id}.";
if ($inserted === 0 && $updated === 0 && $total === 0) {
	$msg = 'No data rows found in the file. Check column mapping (Phone / Name / Email) and re-upload.';
} elseif ($inserted === 0 && $updated === 0 && $skipped > 0 && $duplicates === 0) {
	$msg .= ' Tip: map Phone Number correctly; empty phones are skipped.';
}
if (!empty($errors)) {
	$msg .= ' Details: ' . implode('; ', $errors);
}

$result = ($inserted > 0 || $updated > 0) ? 'success' : 'error';

leads_json(array(
	'result' => $result,
	'msg' => $msg,
	'inserted' => $inserted,
	'updated' => $updated,
	'dups' => $duplicates,
	'skipped' => $skipped,
	'total' => $total,
	'headers' => $header,
	'mapped' => $namedMap,
	'sample' => $sampleRows,
));
