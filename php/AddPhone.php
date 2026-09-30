<?php
/**
 * @file        AddPhone.php
 * @brief       Handles Add Phone Request
 * @copyright   Copyright (C) GOautodial Inc.
 * @author      Alexander Jim Abenoja  <alex@goautodial.com>
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

	require_once('APIHandler.php');
	$api = \creamy\APIHandler::getInstance();

	if($_POST['add_phones'] !== "CUSTOM")
		$seats	= $_POST['add_phones'];
	else
		$seats	= $_POST['custom_seats'];

	$extension   = isset($_POST['phone_ext'])  ? trim($_POST['phone_ext'])  : '';
	$server_ip   = isset($_POST['ip'])         ? trim($_POST['ip'])         : '127.0.0.1';
	$phone_pass  = isset($_POST['phone_pass']) ? trim($_POST['phone_pass']) : 'Go'.date('Y');
	$protocol    = isset($_POST['protocol'])   ? trim($_POST['protocol'])   : 'SIP';
	$fullname    = isset($_POST['pfullname'])  ? trim($_POST['pfullname'])  : '';
	$gmt         = isset($_POST['gmt'])        ? trim($_POST['gmt'])        : '-5:00';
	$user_group  = isset($_POST['user_group']) ? trim($_POST['user_group']) : 'AGENTS';

	$postfields = array(
		'goAction'        => 'goAddPhones',
		'seats'           => $seats,
		'extension'       => $extension,
		'server_ip'       => $server_ip,
		'pass'            => $phone_pass,
		'protocol'        => $protocol,
		'dialplan_number' => "9999".$extension,
		'voicemail_id'    => $extension,
		'status'          => "ACTIVE",
		'active'          => "Y",
		'fullname'        => $fullname,
		'gmt'             => $gmt,
		'messages'        => "0",
		'old_messages'    => "0",
		'user_group'      => $user_group
	);
	
	$output = $api->API_addPhones($postfields);
	
	// Check if remote API succeeded
	if (!is_null($output) && isset($output->result) && $output->result === "success") {
		$status = 1;
	} else {
		// Fallback: save phone directly to local goautodial database
		try {
			$db_host = defined('DB_HOST')     ? DB_HOST     : '127.0.0.1';
			$db_user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
			$db_pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
			$db_port = defined('DB_PORT')     ? intval(DB_PORT) : 3306;
			$conn = @new \mysqli($db_host, $db_user, $db_pass, 'goautodial', $db_port);
			if (!$conn->connect_error) {
				// Ensure phones table exists
				$conn->query("CREATE TABLE IF NOT EXISTS `phones` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`extension` varchar(20) NOT NULL,
					`server_ip` varchar(50) NOT NULL DEFAULT '',
					`protocol` varchar(20) NOT NULL DEFAULT 'SIP',
					`pass` varchar(100) NOT NULL DEFAULT '',
					`fullname` varchar(100) NOT NULL DEFAULT '',
					`user_group` varchar(50) NOT NULL DEFAULT 'AGENTS',
					`gmt_offset_now` varchar(10) NOT NULL DEFAULT '-5:00',
					`status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
					`active` varchar(1) NOT NULL DEFAULT 'Y',
					`messages` int(5) NOT NULL DEFAULT 0,
					`old_messages` int(5) NOT NULL DEFAULT 0,
					`voicemail_id` varchar(30) NOT NULL DEFAULT '',
					`dialplan_number` varchar(20) NOT NULL DEFAULT '',
					`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`),
					UNIQUE KEY `extension` (`extension`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

				$ext_safe  = $conn->real_escape_string($extension);
				$ip_safe   = $conn->real_escape_string($server_ip);
				$pass_safe = $conn->real_escape_string($phone_pass);
				$prot_safe = $conn->real_escape_string($protocol);
				$full_safe = $conn->real_escape_string($fullname);
				$gmt_safe  = $conn->real_escape_string($gmt);
				$ug_safe   = $conn->real_escape_string($user_group);
				$dial_safe = $conn->real_escape_string("9999".$extension);

				$numSeats = max(1, intval($seats));
				$allOk = true;
				for ($i = 0; $i < $numSeats; $i++) {
					$ext_i = $conn->real_escape_string(strval(intval($extension) + $i));
					$dial_i = $conn->real_escape_string("9999".strval(intval($extension) + $i));
					$sql = "INSERT INTO phones (extension, server_ip, protocol, pass, fullname, user_group,
					        gmt_offset_now, status, active, messages, old_messages, voicemail_id, dialplan_number)
					        VALUES ('$ext_i', '$ip_safe', '$prot_safe', '$pass_safe', '$full_safe', '$ug_safe',
					        '$gmt_safe', 'ACTIVE', 'Y', 0, 0, '$ext_i', '$dial_i')
					        ON DUPLICATE KEY UPDATE
					        server_ip='$ip_safe', protocol='$prot_safe', pass='$pass_safe',
					        fullname='$full_safe', user_group='$ug_safe', status='ACTIVE', active='Y'";
					if ($conn->query($sql) !== TRUE) {
						$allOk = false;
					}
				}
				$conn->close();
				$status = $allOk ? 1 : "DB insert error";
			} else {
				$status = "Could not connect to database";
			}
		} catch (\Exception $e) {
			$status = "Exception: " . $e->getMessage();
		}
	}

	echo json_encode($status);

?>
