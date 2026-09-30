<?php
session_start();
$_SESSION['usergroup'] = 'ADMIN';
require_once('./php/UIHandler.php');
$ui = \creamy\UIHandler::getInstance();
echo $ui->getSidebar(1, 'admin', 0, '');
?>
