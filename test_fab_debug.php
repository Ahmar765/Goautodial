<?php
require_once('./php/UIHandler.php');
require_once('./php/APIHandler.php');
require_once('./php/CRMDefaults.php');
require_once('./php/LanguageHandler.php');
include('./php/Session.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();
$perm = $api->goGetPermissions('campaign,disposition,pausecodes,hotkeys,list', $_SESSION['usergroup']);
?>
<html>
<head>
<?php print $ui->standardizedThemeCSS(); ?>
<link rel="stylesheet" href="css/circle-buttons.css">
</head>
<body>
<p>perm: <?php echo htmlspecialchars(print_r($perm,true)); ?></p>
<div class="bottom-menu skin-blue">
    <div class="action-button-circle">
        <?php print $ui->getCircleButton("campaigns", "plus"); ?>
    </div>
</div>
<p style="position:fixed;bottom:5px;left:5px;background:yellow;padding:5px;z-index:9999999">
If blue circle appears bottom-right, CSS works!
</p>
</body>
</html>
