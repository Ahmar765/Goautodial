<?php
// Dummy settings only. Never include this fixture in a production entry point.
putenv('APP_ENV=development');
putenv('APP_URL=http://127.0.0.1:8097');
putenv('SESSION_DRIVER=files');
foreach (array('DB_USER', 'DB_PASS', 'DB_USERNAME_KAMAILIO', 'DB_PASSWORD_KAMAILIO', 'GO_API_USER', 'GO_API_PASSWORD') as $key) putenv($key . '=fixture');
putenv('GO_API_URL=http://127.0.0.1:8098');
putenv('SIP_DOMAIN=sip.example.test');
putenv('SIP_WS_HOST=ws.example.test');
putenv('SIP_WS_PORT=8089');
