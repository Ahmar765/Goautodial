
	var audioElement = document.querySelector('#remoteStream');
	var localStream;
	var remoteStream;
	var globalSession;
	var phone_login = '1001';
	
	var socket = new JsSIP.WebSocketInterface('wss://ws.example.test:8089/');
	var configuration = {
		sockets : [ socket ],
		uri: 'sip:'+phone_login+'@sip.example.test:5070'',
		password: phone_pass,
		session_timers: false,
		registrar_server: 'sip.example.test',
		use_preloaded_route: false,
		register: true
	};
	
	//init rtcninja libraries...
	
	var phone = new JsSIP.UA(configuration);
	
	phone.on('connected', function(e) {
		//console.log('connected', e);
		
		//phone.register();
	});
	
	phone.on('disconnected', function(e) {
		//console.log('disconnected', e);
	});
	
	phone.on('newRTCSession', function(e) {
		//console.log(e);
		
		var session = e.session;
		//console.log('newRTCSession: originator', e.originator, 'session', e.session, 'request', e.request);
	
		session.on('peerconnection', function (data) {
			//console.log('session::peerconnection', data);
		});
	
		session.on('iceconnectionstatechange', function (data) {
			//console.log('session::iceconnectionstatechange', data);
		});
	
		session.on('connecting', function (data) {
			//console.log('session::connecting', data);
		});
	
		session.on('sending', function (data) {
			//console.log('session::sending', data);
		});
	
		session.on('progress', function (data) {
			//console.log('session::progress', data);
		});
	
		session.on('accepted', function (data) {
			//console.log('session::accepted', data);
		});
	
		session.on('confirmed', function (data) {
			//console.log('session::confirmed', data);
		});
	
		session.on('ended', function (data) {
			//console.log('session::ended', data);
			if (data.cause !== 'Terminated') {
				alertLogout = false;
				sendLogout(true);
				swal({
					title: data.cause,
					text: "contact_admin",
					type: 'error'
				});
			}
		});
	
		session.on('failed', function (data) {
			//console.log('session::failed', data);
			alertLogout = false;
			sendLogout(true);
			swal({
				title: data.cause,
				text: "contact_admin",
				type: 'error'
			});
		});
	
		//session.on('addstream', function (data) {
		//	console.log('session::addstream', data);
		//
		//	remoteStream = data.stream;
		//	audioElement = document.querySelector('#remoteStream');
		//	audioElement.src = window.URL.createObjectURL(remoteStream);
		//	
		//	globalSession = session;
		//});
	
		session.on('removestream', function (data) {
			//console.log('session::removestream', data);
		});
	
		session.on('newDTMF', function (data) {
			//console.log('session::newDTMF', data);
		});
	
		session.on('hold', function (data) {
			//console.log('session::hold', data);
		});
	
		session.on('unhold', function (data) {
			//console.log('session::unhold', data);
		});
	
		session.on('muted', function (data) {
			//console.log('session::muted', data);
            $.snackbar({id: "mutedMic", content: "<i class='fa fa-microphone-slash fa-lg text-danger' aria-hidden='true'></i>&nbsp; you_have_turn_off_mic", timeout: 0, htmlAllowed: true});
		});
	
		session.on('unmuted', function (data) {
			//console.log('session::unmuted', data);
			$("#mutedMic").snackbar('hide');
            $.snackbar({content: "<i class='fa fa-microphone fa-lg text-success' aria-hidden='true'></i>&nbsp; you_have_turn_on_mic", timeout: 5000, htmlAllowed: true});
		});
	
		session.on('reinvite', function (data) {
			//console.log('session::reinvite', data);
		});
	
		session.on('update', function (data) {
			//console.log('session::update', data);
		});
	
		session.on('refer', function (data) {
			//console.log('session::refer', data);
		});
	
		session.on('replaces', function (data) {
			//console.log('session::replaces', data);
		});
	
		session.on('sdp', function (data) {
			//console.log('session::sdp', data);
		});
	
		session.answer({
			mediaConstraints: {
				audio: true,
				video: false
			}
		});
		
		session.connection.addEventListener('addstream', (event) => {
			//console.log("session::addstream", event);
			
			remoteStream = event.stream;
			audioElement = document.querySelector('#remoteStream');
			audioElement.srcObject = remoteStream;
			
			globalSession = session;
		});
	});
	
	phone.on('newMessage', function(e) {
		//console.log('newMessage', e);
	});
	
	phone.on('registered', function(e) {
		//console.log('registered', e);
		phoneRegistered = true;
		registrationFailed = false;
		if ( !!$.prototype.snackbar ) {
			$.snackbar({content: "<i class='fa fa-info-circle fa-lg text-success' aria-hidden='true'></i>&nbsp; phone_is_now_registered", timeout: 5000, htmlAllowed: true});
		}
	});
	
	phone.on('unregistered', function(e) {
		//console.log('unregistered', e);
		phoneRegistered = false;
	});
	
	phone.on('registrationFailed', function(e) {
		//console.log('registrationFailed', e);
		phoneRegistered = false;
		registrationFailed = true;
		phone.stop();
		swal({
			title: "Registration Failed - " + e.cause,
			text: "contact_admin",
			type: 'error'
		});
		
		if ( !!$.prototype.snackbar ) {
			$.snackbar({content: "<i class='fa fa-exclamation-triangle fa-lg text-danger' aria-hidden='true'></i>&nbsp; registration_failed_refresh", timeout: 5000, htmlAllowed: true});
		}
	});
	
	navigator.mediaDevices.getUserMedia({
		audio: true,
		video: false
	}).then(function (stream) {
		localStream = stream;
		//console.log('getUserMedia', stream);
	
		//phone.start();
	}).catch(function (err) {
		console.error('getUserMedia failed: %s', err.toString());
		swal({
			title: "Microphone NOT Detected",
			text: "contact_admin",
			type: 'error'
		});
	});
