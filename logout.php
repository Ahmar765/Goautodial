<?php
	/**
		The MIT License (MIT)
		
		Copyright (c) 2015 Ignacio Nieto Carvajal
		
		Permission is hereby granted, free of charge, to any person obtaining a copy
		of this software and associated documentation files (the "Software"), to deal
		in the Software without restriction, including without limitation the rights
		to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
		copies of the Software, and to permit persons to whom the Software is
		furnished to do so, subject to the following conditions:
		
		The above copyright notice and this permission notice shall be included in
		all copies or substantial portions of the Software.
		
		THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
		IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
		FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
		AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
		LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
		OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
		THE SOFTWARE.
	*/
	require_once('./php/goCRMAPISettings.php');
	require_once('./php/CRMDefaults.php');

	if (CRM_SESSION_DRIVER == 'database') {
		require_once('./php/SessionHandler.php');
		$session_class = new \creamy\SessionHandler();
	} else {
		if (session_status() === PHP_SESSION_NONE) {
			session_start(); // Starting Session
		}
	}
	
	$log_user = (isset($_SESSION['user']) ? $_SESSION['user'] : '');
	$rcAuthToken = isset($_SESSION['gad_authToken']) ? $_SESSION['gad_authToken'] : '';
	$rcUserID = isset($_SESSION['gad_userID']) ? $_SESSION['gad_userID'] : '';
	$needsRocketChatLogout = (ROCKETCHAT_ENABLE === 'y' && $rcAuthToken !== '');
	
	if (strlen($log_user) > 0) {
		$log_group = (isset($_SESSION['usergroup']) ? $_SESSION['usergroup'] : '');
		$details = "User {$log_user} logging out";
	} else {
		$log_user = 'sess_expired';
		$details = "Session Expired";
		$log_group = '';
	}

	if ($needsRocketChatLogout) {
?>
<link rel="stylesheet" href="css/customizedLoader.css">
<link rel="stylesheet" href="adminlte/css/AdminLTE.min.css">
<div class="preloader">
                        <div class="pull-right close-preloader" style="display:none;">
                                <a type="button" class="close-preloader-button" aria-label="Close" style="color:white;"><i class="fa fa-close fa-lg"></i></a>
                        </div>
                        <center>
                                        <span class="dots">
                                        <div class="circ1"></div><div class="circ2"></div><div class="circ3"></div><div class="circ4"></div>
                                        </span>

                                        <br/><br/>
                                        <div class="rc-loading-reports" style="color:white;">
                                                LOGGING YOU OUT...<br/> Please wait while we redirect you to the Login Page... <br/>
                                                <br/><button type="button" class="btn reload-button" style="display:none; color: #333333;"><i class="fa fa-refresh fa-3x"></i></button>
                                        </div>
                        </center>

                </div>
<div id="rc_div" ></div>	
<script language="JavaScript" type="text/javascript" src="/js/jquery.min.js"></script>
<script>
   $(document).ready(function() {
	$('<iframe>', {
                                   src: '<?php echo ROCKETCHAT_URL;?>?layout=embedded',
                                   id:  'rc_frame',
                                   name: 'rc_frame',
                                   frameborder: 0,
                                   width: '10%',
                                   height: '10%',
                                   scrolling: 'no'
                                   }).appendTo('#rc_div');
	
	$("#rc_div").hide();
	var rcWin = document.getElementById('rc_frame').contentWindow;
	setTimeout(function() {
          $.ajax({
                url: "./php/LogoutRocketChat.php",
                type: 'POST',
                dataType: "json",
                data: {userID: <?php echo json_encode($rcUserID); ?>, authToken: <?php echo json_encode($rcAuthToken); ?>},
                success: function(data) {
		setTimeout(function() {
                rcWin.postMessage({
                   event: "log-me-out-iframe"
                }, "<?php echo ROCKETCHAT_URL;?>");
                delayLogoutforRocketchat();
                }, 4000);
		
		}
          });
	}, 3000);
   });
   function delayLogoutforRocketchat(){
      setTimeout(function() {
         console.log("Logging out of Rocketchat...");
	$(location).attr("href", "login.php");
      }, 1000);
   }
</script>

<?php
	}//rocketchat integration

	// Always clear local session state and cookie, even if session_destroy() fails.
	// Previously logout only redirected when session_destroy() returned true, which
	// left users on a blank page (still appearing logged in) when the DB session
	// handler could not delete the row.
	$_SESSION = array();
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			(bool) $params['secure'],
			(bool) $params['httponly']
		);
	}
	if (session_status() === PHP_SESSION_ACTIVE) {
		@session_destroy();
	}
	
	$url = gourl."/goAdminLogs/goAPI.php"; #URL to GoAutoDial API. (required)
	$postfields = array();
	$postfields["goUser"] = goUser; #Username goes here. (required)
	$postfields["goPass"] = goPass;
	$postfields["goAction"] = "goLogActions"; #action performed by the [[API:Functions]]
	$postfields["action"] = "LOGOUT";
	$postfields["user"] = $log_user;
	$postfields["user_group"] = $log_group;
	$postfields["details"] = $details;
	$postfields["ip_address"] = $_SERVER['REMOTE_ADDR'];
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	@curl_exec($ch);
	curl_close($ch);

	if (!$needsRocketChatLogout) {
		header("Location: login.php");
		exit;
	}

?>
