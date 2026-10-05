<?php
require_once __DIR__ . '/RequestGuard.php';

###########################################################
### Name: SaveImage.php                                 ###
### Functions: Save image to the database               ###
### Copyright: GOAutoDial Ltd. (c) 2011-2016            ###
### Version: 4.0                                        ###
### Written by: Christopher P. Lomuntad                 ###
### License: AGPLv2                                     ###
###########################################################

require_once('CRMDefaults.php');
require_once('DbHandler.php');

$db = new \creamy\DbHandler();

$uid = $_POST['user_id'] ?? '';
require_once __DIR__ . '/AvatarImage.php';
$avatar = \creamy\AvatarImage::decode($_POST['image'] ?? null);
if ($avatar === null) { \creamy\Security::deny(400, 'A PNG, JPEG or GIF avatar under 2 MB is required.'); }
$type = $avatar['type'];
$image = $avatar['data'];

$uploaded = $db->saveUserAvatar($uid, $type, $image);

if ($uploaded) {
    echo "success";
} else {
    echo "error";
}
?>
