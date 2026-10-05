<?php
require_once __DIR__ . '/Security.php';

if (PHP_SAPI !== 'cli') {
    set_exception_handler(function ($exception) {
        error_log('GOautodial request failed: ' . get_class($exception));
        while (ob_get_level() > 0) { ob_end_clean(); }
        \creamy\Security::deny(503, 'Service unavailable. Check server configuration and backend connectivity.');
    });
    ini_set('display_errors', '0');
    \creamy\Security::guard();
}
