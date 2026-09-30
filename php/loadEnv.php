<?php
/**
 * Minimal .env loader (no Composer). Loads project-root/.env into getenv()/$_ENV.
 * Does not overwrite variables already set in the environment (e.g. Apache SetEnv, Docker).
 */
function creamy_load_dotenv($rootDirectory) {
	$path = rtrim($rootDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
	if (!is_readable($path)) {
		return false;
	}
	$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return false;
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') {
			continue;
		}
		if (strpos($line, '=') === false) {
			continue;
		}
		list($name, $value) = explode('=', $line, 2);
		$name = trim($name);
		$value = trim($value);
		if ($name === '') {
			continue;
		}
		if (strlen($value) >= 2) {
			$q = $value[0];
			if (($q === '"' || $q === "'") && substr($value, -1) === $q) {
				$value = substr($value, 1, -1);
			}
		}
		if (getenv($name) !== false) {
			continue;
		}
		putenv($name . '=' . $value);
		$_ENV[$name] = $value;
	}
	return true;
}

function creamy_env($key, $default = null) {
	$value = getenv($key);
	if ($value === false || $value === '') {
		return $default;
	}
	return $value;
}

function creamy_env_bool($key, $default = false) {
	$value = creamy_env($key, null);
	if ($value === null) {
		return (bool) $default;
	}
	return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}
