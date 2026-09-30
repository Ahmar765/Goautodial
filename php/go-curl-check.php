<?php
header('Content-Type: application/json');
$ext = get_loaded_extensions();
sort($ext);
echo json_encode(array(
	'curl_extension' => extension_loaded('curl'),
	'curl_init' => function_exists('curl_init'),
	'loaded_ini' => php_ini_loaded_file(),
	'has_curl_in_loaded_extensions' => in_array('curl', $ext, true),
	'extension_dir' => ini_get('extension_dir'),
), JSON_PRETTY_PRINT);
