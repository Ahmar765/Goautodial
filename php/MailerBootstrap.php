<?php
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) throw new RuntimeException('Install the Composer dependencies before enabling SMTP.');
require_once $autoload;
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('PHPMailer is unavailable.');
