<?php
require_once __DIR__ . '/RequestGuard.php';
 
/**
 * @file        AddLoadLeads.php
 * @brief       Handles Bulk Upload Leads Request (CSV & XLSX)
 * @copyright   Copyright (C) GOautodial Inc.
 * @author      Noel Umandap
 * @author      Alexander Jim Abenoja  <alex@goautodial.com>
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
*/

	require_once('APIHandler.php');
	require_once('CRMDefaults.php');
	$api = \creamy\APIHandler::getInstance();

	ini_set('memory_limit','2048M');
	ini_set('upload_max_filesize', '600M');
	ini_set('post_max_size', '600M');
	ini_set('max_execution_time', 3600);

	header('Content-Type: application/json');

	if (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
		echo json_encode(array(
			"result" => "error",
			"msg" => "File upload failed or no file provided."
		));
		exit;
	}

	$list_id = isset($_REQUEST['list_id']) ? trim($_REQUEST['list_id']) : '';
	$dupcheck = isset($_REQUEST['goDupcheck']) ? trim($_REQUEST['goDupcheck']) : 'CHECK_NUM_AND_LIST';
	$phone_code_override = isset($_REQUEST['phone_code_override']) ? trim($_REQUEST['phone_code_override']) : '1';

	if (empty($list_id)) {
		echo json_encode(array(
			"result" => "error",
			"msg" => "Target List ID is required."
		));
		exit;
	}

	$postfields = array(
		'goFileMe' => curl_file_create($_FILES['file_upload']['tmp_name'], 'text/csv', $_FILES["file_upload"]["name"]),
		'goListId' => $list_id, 
		'goDupcheck' => $dupcheck,
		'phone_code_override' => $phone_code_override,
		'goAction' => 'goUploadMe'
	);
	
	if (defined('LEADUPLOAD_CUSTOM_DELIMITER')) {
		$postfields["custom_delimiter"] = LEADUPLOAD_CUSTOM_DELIMITER;
	} else {
		$postfields["custom_delimiter"] = ",";
	}
	
	if (isset($_POST["lead_mapping_data"]) && isset($_POST["lead_mapping_fields"])) {
		$map_data = is_array($_POST["lead_mapping_data"]) ? implode(",", $_POST["lead_mapping_data"]) : $_POST["lead_mapping_data"];
		$map_fields = is_array($_POST["lead_mapping_fields"]) ? implode(",", $_POST["lead_mapping_fields"]) : $_POST["lead_mapping_fields"];
		
		$postfields["lead_mapping_data"] = $map_data;
		$postfields["lead_mapping_fields"] = $map_fields;
		$postfields["lead_mapping"] = "y";
	}

	$return = $api->API_Upload("goUploadLeads", $postfields, "data");
	$output = isset($return["output"]) ? $return["output"] : null;
	$data = isset($return["data"]) ? $return["data"] : null;

	$res = array();
	if (is_object($output)) {
		$res["result"] = isset($output->result) ? $output->result : 'success';
		$res["msg"] = isset($output->message) ? $output->message : 'Leads uploaded successfully.';	
		$res["dups"] = isset($output->duplicates) ? $output->duplicates : 0;
		$res["inserted"] = isset($output->inserted) ? $output->inserted : 0;
		$res["total"] = isset($output->total) ? $output->total : 0;
	} else if (!empty($data)) {
		$decoded = json_decode($data, true);
		if (is_array($decoded)) {
			$res["result"] = isset($decoded['result']) ? $decoded['result'] : 'success';
			$res["msg"] = isset($decoded['message']) ? $decoded['message'] : 'Leads processed.';
			$res["dups"] = isset($decoded['duplicates']) ? $decoded['duplicates'] : 0;
			$res["inserted"] = isset($decoded['inserted']) ? $decoded['inserted'] : 0;
		} else {
			$res["result"] = "success";
			$res["msg"] = "Leads submitted to dialer successfully.";
		}
	} else {
		$res["result"] = "success";
		$res["msg"] = "Leads file received and submitted.";
	}

	echo json_encode($res);
?>
