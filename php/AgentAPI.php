<?php
require_once __DIR__ . '/RequestGuard.php';
require_once __DIR__ . '/APIHandler.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    \creamy\Security::deny(405, 'POST is required.');
}
if (!is_string($_POST['goAction'] ?? null)) {
    \creamy\Security::deny(400, 'An agent action is required.');
}
$body = \creamy\APIHandler::getInstance()->API_Request('goAgent', $_POST, true);
if (!is_string($body) || json_decode($body) === null) {
    \creamy\Security::deny(502, 'Invalid response from the calling backend.');
}
header('Content-Type: application/json; charset=utf-8');
echo $body;
