<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'add_list_id' => '1001',
	'list_name' => 'Test List',
	'list_desc' => 'test',
	'campaign_select' => '',
	'status' => 'Y',
);
chdir(__DIR__ . '/../php');
include 'AddList.php';
