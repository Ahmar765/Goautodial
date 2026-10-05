<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../php/LegacyPassword.php';
$options = getopt('', array('pass:', 'cost:', 'salt:', 'help'));
if (isset($options['help']) || !isset($options['salt'])) {
    echo "Usage: php bin/pass_hasher.php --salt=<backend pass_key> --cost=<backend pass_cost>\n";
    echo "Read the password from standard input (recommended), or supply --pass=<password>.\n";
    exit(isset($options['help']) ? 0 : 1);
}
$password = $options['pass'] ?? rtrim((string) fgets(STDIN), "\r\n");
try {
    if ($password === '') throw new InvalidArgumentException();
    echo \creamy\LegacyPassword::hash($password, $options['cost'] ?? 12, $options['salt']) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Invalid password, cost or backend salt.\n");
    exit(1);
}
