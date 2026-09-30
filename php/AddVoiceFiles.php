<?php
/**
 * @file        AddVoiceFiles.php
 * @brief       Upload voice/audio files (local sounds/ + optional goAPI)
 * @copyright   Copyright (c) 2018 GOautodial Inc.
 */

require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/APIHandler.php';
require_once __DIR__ . '/GoHttpClient.php';
require_once __DIR__ . '/Session.php';

function voicefiles_redirect($result) {
	header('Location: ../audiofiles.php?upload_result=' . urlencode((string) $result));
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	voicefiles_redirect('error');
}

if (empty($_FILES['voice_file']) || !is_uploaded_file($_FILES['voice_file']['tmp_name'])) {
	voicefiles_redirect('error');
}

$file = $_FILES['voice_file'];
if (!empty($file['error'])) {
	voicefiles_redirect('error');
}

$maxBytes = 16 * 1024 * 1024; // 16MB
if ((int) $file['size'] > $maxBytes) {
	voicefiles_redirect('error');
}

$origName = (string) $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowed = array('wav', 'mp3', 'gsm', 'ulaw', 'alaw', 'ogg', 'sln', 'sln16');
if (!in_array($ext, $allowed, true)) {
	voicefiles_redirect('error');
}

$base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
$base = trim($base, '_');
if ($base === '') {
	$base = 'voice_' . date('YmdHis');
}
$safeName = $base . '.' . $ext;

// Store under web-root /sounds so play URLs (/sounds/file) work on XAMPP
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$soundsDir = $docRoot . '/sounds';
if (!is_dir($soundsDir)) {
	@mkdir($soundsDir, 0775, true);
}
// Also keep a copy under the CRM project for backups/listing fallback
$crmSounds = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sounds';
if (!is_dir($crmSounds)) {
	@mkdir($crmSounds, 0775, true);
}

$destWeb = $soundsDir . '/' . $safeName;
$destCrm = $crmSounds . DIRECTORY_SEPARATOR . $safeName;

if (is_file($destWeb) || is_file($destCrm)) {
	voicefiles_redirect('exists');
}

$ok = @move_uploaded_file($file['tmp_name'], $destWeb);
if (!$ok) {
	$ok = @move_uploaded_file($file['tmp_name'], $destCrm);
	if ($ok && is_dir($soundsDir)) {
		@copy($destCrm, $destWeb);
	}
} else {
	@copy($destWeb, $destCrm);
}

if (!$ok) {
	voicefiles_redirect('error');
}

// Best-effort goAPI upload when available (production)
$localFb = defined('CRM_LOGIN_LOCAL_DB_FALLBACK') && CRM_LOGIN_LOCAL_DB_FALLBACK === true;
if (!$localFb && \creamy\GoHttpClient::canPost() && function_exists('curl_file_create') && is_readable($destWeb)) {
	try {
		$api = \creamy\APIHandler::getInstance();
		$postfields = array(
			'goAction' => 'goAddVoiceFiles',
			'files' => curl_file_create($destWeb, $file['type'] ?: 'application/octet-stream', $safeName),
			'stage' => 'upload',
		);
		$output = $api->API_addVoiceFiles($postfields);
		if (is_object($output) && isset($output->result) && $output->result === 'success') {
			voicefiles_redirect('success');
		}
	} catch (\Throwable $e) {
		// keep local success
	}
}

voicefiles_redirect('success');
