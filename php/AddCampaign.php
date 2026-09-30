<?php
/**
 * @file        AddCampaign.php
 * @brief       Add New Campaign
 * @copyright   Copyright (C) GOautodial Inc.
 * @author      Noel Umandap
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
	require_once(__DIR__ . '/APIHandler.php');

	header('Content-Type: application/json');

	$api = \creamy\APIHandler::getInstance();
	$call_route = $_POST['call_route'] ?? 'INGROUP';
	$dial_prefix = ( !isset($_POST['dial_prefix']) ) ? 9 : $_POST['dial_prefix'];
	$auto_dial_level = ( !isset($_POST['auto_dial_level']) ) ? 'OFF': $_POST['auto_dial_level'];

	$lead_file = null;
	$leads = null;
	$uploaded_wav = null;
	$custom_dial_prefix = 0;
	$call_route_text = '';

	if ( $dial_prefix == "CUSTOM") {
		$custom_dial_prefix = $_POST['custom_prefix'] ?? 0;
	}

    switch ($call_route){
        case "INGROUP":
            $call_route_text = $_POST['ingroup_text'] ?? '';
        break;

        case "IVR":
            $call_route_text = $_POST['ivr_text'] ?? '';
        break;

        case "AGENT":
            $call_route_text = $_POST['agent_text'] ?? '';
        break;

        case "VOICEMAIL":
            $call_route_text = $_POST['voicemail_text'] ?? '';
        break;
    }

	if ( !empty($_FILES["lead_file"]["name"]) ) {
		$lead_file = curl_file_create( $_FILES['lead_file']['tmp_name'], $_FILES['lead_file']['type'], $_FILES["lead_file"]["name"] );
	}

	if ( !empty($_FILES["leads"]["name"]) ) {
		$leads = curl_file_create( $_FILES['leads']['tmp_name'], $_FILES['leads']['type'], $_FILES["leads"]["name"] );
	}

    if ( !empty($_FILES["uploaded_wav"]["name"]) ) {
		$uploaded_wav = curl_file_create( $_FILES['uploaded_wav']['tmp_name'], $_FILES['uploaded_wav']['type'], $_FILES["uploaded_wav"]["name"] );
	}

	$postfields = array(
		'goAction' => 'goAddCampaign',
		'campaign_id' => $_POST['campaign_id'] ?? '',
		'campaign_name' => $_POST['campaign_name'] ?? '',
		'campaign_type' => $_POST['campaign_type'] ?? 'outbound',
		'did_tfn_extension' => $_POST['did_tfn_extension'] ?? '',
		'call_route' => $call_route,
		'call_route_text' => $call_route_text,
		'group_color' => $_POST['group_color'] ?? '',
		'survey_type' => $_POST['survey_type'] ?? '',
		'no_channels' => $_POST['no_channels'] ?? '',
		'copy_from_campaign' => $_POST['copy_from_campaign'] ?? '',
		'list_id' => $_POST['list_id'] ?? '',
		'country' => $_POST['country'] ?? '',
		'check_for_duplicates' => $_POST['check_for_duplicates'] ?? '',
		'dial_method' => $_POST['dial_method'] ?? '',
		'auto_dial_level' => $auto_dial_level,
		'auto_dial_level_adv' => $_POST['auto_dial_level_adv'] ?? '',
		'dial_prefix' => $dial_prefix,
		'custom_dial_prefix' => $custom_dial_prefix,
		'description' => $_POST['description'] ?? '',
		'status' => $_POST['status'] ?? '',
		'script' => $_POST['script'] ?? '',
		'answering_machine_detection' => $_POST['answering_machine_detection'] ?? '',
		'caller_id' => $_POST['caller_id'] ?? '',
		'force_reset_hopper' => $_POST['force_reset_hopper'] ?? '',
		'campaign_recording' => $_POST['campaign_recording'] ?? '',
		'lead_file' => $lead_file,
		'leads' => $leads,
		'uploaded_wav' => $uploaded_wav
	);

	require_once(__DIR__ . '/GoHttpClient.php');

	if (!\creamy\GoHttpClient::canPost()) {
		echo json_encode(array(
			'status' => 0,
			'result' => 'error',
			'message' => 'Cannot call goAPIv2: enable PHP cURL in php.ini or set allow_url_fopen=On, then restart Apache from the XAMPP Control Panel.',
		));
		exit;
	}

	$output = $api->API_addCampaign($postfields);

	if (is_object($output) && ($output->result ?? '') == "success") {
		$response = array(
			'status' => 1,
			'result' => 'success',
			'message' => 'Success'
		);
	} else {
		$apiMessage = '';
		if (is_object($output)) {
			$apiMessage = (string) ($output->message ?? '');
			if ($apiMessage === '' || $apiMessage === 'error') {
				$apiMessage = (string) ($output->result ?? '');
			}
		}
		if ($apiMessage === '' || $apiMessage === 'error') {
			$base = defined('gourl') ? gourl : 'http://localhost/goAPIv2';
			$probe = \creamy\GoHttpClient::post($base . '/goCampaigns/goAPI.php', array(
				'goAction' => 'goGetAllCampaigns',
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
				$apiMessage = 'Campaign create failed (goAPIv2 is reachable, but Add Campaign returned no usable result). '
					. 'Common causes: campaign ID shorter than 8 characters, or incomplete local schema. '
					. 'Run php/seed_goapi_local.php, then retry with an 8+ character campaign ID.';
			} else {
				$apiMessage = 'goAPIv2 is not reachable at ' . $base . '. '
					. 'Install GOautodial goAPIv2 on this server, or set GO_API_BASE_URL in the project .env file. '
					. 'Open php/goapi-status.php in the browser for a connection test.';
			}
		}
		$response = array(
			'status' => 0,
			'result' => is_object($output) ? ($output->result ?? 'error') : 'error',
			'message' => $apiMessage
		);
	}

	echo json_encode($response);

?>
