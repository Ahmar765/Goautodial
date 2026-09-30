<?php
/**
 * PHP 8 compatibility for this namespaced app:
 * - CURLOPT_* constants do not fall back to global from namespace creamy
 * - cURL functions are shimmed when the curl extension is missing
 */

$creamyCurlConstants = array(
	'CURLOPT_URL' => 10002,
	'CURLOPT_POST' => 47,
	'CURLOPT_POSTFIELDS' => 10015,
	'CURLOPT_RETURNTRANSFER' => 19913,
	'CURLOPT_SSL_VERIFYPEER' => 64,
	'CURLOPT_SSL_VERIFYHOST' => 81,
	'CURLOPT_TIMEOUT' => 13,
	'CURLOPT_CONNECTTIMEOUT' => 78,
	'CURLOPT_HTTPHEADER' => 10023,
	'CURLOPT_HEADER' => 42,
	'CURLOPT_CUSTOMREQUEST' => 10036,
	'CURLOPT_FOLLOWLOCATION' => 52,
	'CURLOPT_USERAGENT' => 10018,
	'CURLOPT_ENCODING' => 10102,
	'CURLOPT_MAXREDIRS' => 68,
	'CURLOPT_HTTP_VERSION' => 84,
	'CURLOPT_SAFE_UPLOAD' => -1,
	'CURL_HTTP_VERSION_1_1' => 2,
	'CURLINFO_HTTP_CODE' => 2097154,
);

foreach ($creamyCurlConstants as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
	$namespaced = 'creamy\\' . $name;
	if (!defined($namespaced)) {
		define($namespaced, constant($name));
	}
}

if (!function_exists('curl_init')) {

	class CreamyCurlHandle {
		public $url = '';
		public $opts = array();
		public $errno = 0;
		public $error = '';
		public $http_code = 0;
	}

	function curl_init($url = null) {
		$ch = new CreamyCurlHandle();
		if (!empty($url)) {
			$ch->url = $url;
		}
		return $ch;
	}

	function curl_setopt($ch, $option, $value) {
		if (!($ch instanceof CreamyCurlHandle)) {
			return false;
		}
		$ch->opts[$option] = $value;
		if ($option === CURLOPT_URL) {
			$ch->url = $value;
		}
		return true;
	}

	function curl_setopt_array($ch, $options) {
		foreach ($options as $option => $value) {
			curl_setopt($ch, $option, $value);
		}
		return true;
	}

	function curl_exec($ch) {
		if (!($ch instanceof CreamyCurlHandle)) {
			return false;
		}
		$ch->errno = 7;
		$ch->error = 'cURL extension is not installed';
		$ch->http_code = 0;
		return false;
	}

	function curl_close($ch) {
		return true;
	}

	function curl_errno($ch) {
		return ($ch instanceof CreamyCurlHandle) ? $ch->errno : 0;
	}

	function curl_error($ch) {
		return ($ch instanceof CreamyCurlHandle) ? $ch->error : '';
	}

	function curl_getinfo($ch, $opt = null) {
		$code = ($ch instanceof CreamyCurlHandle) ? $ch->http_code : 0;
		if ($opt === CURLINFO_HTTP_CODE) {
			return $code;
		}
		return array('http_code' => $code);
	}

	function curl_file_create($filename, $mimetype = '', $postname = '') {
		return $filename;
	}
}
