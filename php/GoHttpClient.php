<?php
/**
 * HTTP POST helper: uses cURL when loaded, otherwise application/x-www-form-urlencoded via streams.
 */
namespace creamy;

class GoHttpClient {

	public static function canPost() {
		if (extension_loaded('curl') && function_exists('curl_init')) {
			return true;
		}
		return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @return bool True if any field is a file upload (CURLFile or readable path intended as upload).
	 */
	public static function hasFileFields(array $fields) {
		foreach ($fields as $value) {
			if ($value instanceof \CURLFile) {
				return true;
			}
			if (is_object($value) && isset($value->name) && class_exists('CURLFile', false) && $value instanceof \CURLFile) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Scalar fields only (for stream POST).
	 */
	public static function scalarFields(array $fields) {
		$out = array();
		foreach ($fields as $key => $value) {
			if ($value === null || $value === '') {
				continue;
			}
			if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
				$out[$key] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * @return string|false Response body
	 */
	public static function post($url, array $fields, $timeout = 30) {
		if (self::hasFileFields($fields)) {
			if (!extension_loaded('curl') || !function_exists('curl_init')) {
				return false;
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
		}

		$body = http_build_query(self::scalarFields($fields));

		if (extension_loaded('curl') && function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
		}

		if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
			return false;
		}

		$context = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
					. 'Content-Length: ' . strlen($body) . "\r\n",
				'content' => $body,
				'timeout' => (int) $timeout,
				'ignore_errors' => true,
			),
		));

		return @file_get_contents($url, false, $context);
	}
}
