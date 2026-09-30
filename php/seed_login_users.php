<?php
/**
 * Creates vicidial_users (minimal) in the asterisk database and seeds admin + agent.
 * Run once: http://localhost/v4.0-master/php/seed_login_users.php
 * Or CLI: php php/seed_login_users.php
 */
require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/DatabaseConnectorFactory.php';

header('Content-Type: text/plain; charset=utf-8');

$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? DB_PORT : '3306';

$mysqli = @new mysqli($host, $user, $pass, $dbName, (int) $port);
if ($mysqli->connect_errno) {
	echo "Database connection failed: {$mysqli->connect_error}\n";
	echo "Check php/Config.php (DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME_ASTERISK).\n";
	exit(1);
}

$createSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user VARCHAR(20) NOT NULL,
  pass VARCHAR(100) NOT NULL DEFAULT '',
  pass_hash VARCHAR(255) NOT NULL DEFAULT '',
  full_name VARCHAR(50) NOT NULL DEFAULT '',
  user_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  user_group VARCHAR(20) NOT NULL DEFAULT 'ADMIN',
  phone_login VARCHAR(20) NOT NULL DEFAULT '',
  phone_pass VARCHAR(20) NOT NULL DEFAULT '',
  email VARCHAR(100) NOT NULL DEFAULT '',
  active ENUM('Y','N') NOT NULL DEFAULT 'Y',
  bcrypt TINYINT NOT NULL DEFAULT 0,
  use_webrtc TINYINT NOT NULL DEFAULT 0,
  ha1 VARCHAR(64) NOT NULL DEFAULT '',
  realm VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (user_id),
  UNIQUE KEY vicidial_users_user (user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

if (!$mysqli->query($createSql)) {
	echo "Failed to create vicidial_users: {$mysqli->error}\n";
	exit(1);
}

$users = array(
	array(
		'user' => 'goadmin',
		'pass' => 'admin123',
		'full_name' => 'GO Admin',
		'user_level' => 9,
		'user_group' => 'ADMIN',
		'phone_login' => '8001',
		'phone_pass' => 'admin123',
		'email' => 'admin@localhost.com',
	),
	array(
		'user' => 'agent1',
		'pass' => 'agent123',
		'full_name' => 'Test Agent',
		'user_level' => 1,
		'user_group' => 'ADMIN',
		'phone_login' => '8002',
		'phone_pass' => 'agent123',
		'email' => 'agent1@localhost.com',
	),
	array(
		'user' => 'agent2',
		'pass' => 'agent123',
		'full_name' => 'Test Agent 2',
		'user_level' => 1,
		'user_group' => 'ADMIN',
		'phone_login' => '8003',
		'phone_pass' => 'agent123',
		'email' => 'agent2@localhost.com',
	),
);

$stmt = $mysqli->prepare(
	'INSERT INTO vicidial_users (user, pass, full_name, user_level, user_group, phone_login, phone_pass, email, active)
	 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'Y\')
	 ON DUPLICATE KEY UPDATE
	   pass = VALUES(pass),
	   pass_hash = VALUES(pass),
	   full_name = VALUES(full_name),
	   user_level = VALUES(user_level),
	   user_group = VALUES(user_group),
	   phone_login = VALUES(phone_login),
	   phone_pass = VALUES(phone_pass),
	   email = VALUES(email),
	   active = \'Y\''
);

if (!$stmt) {
	echo "Prepare failed: {$mysqli->error}\n";
	exit(1);
}

foreach ($users as $u) {
	$stmt->bind_param(
		'sssissss',
		$u['user'],
		$u['pass'],
		$u['full_name'],
		$u['user_level'],
		$u['user_group'],
		$u['phone_login'],
		$u['phone_pass'],
		$u['email']
	);
	$stmt->execute();
}

echo "OK — login users ready in database \"{$dbName}\" (table vicidial_users).\n\n";
echo "Ensure php/Config.php has: define('CRM_LOGIN_LOCAL_DB_FALLBACK', true);\n\n";
echo "Admin (dashboard):\n";
echo "  Username: goadmin\n";
echo "  Password: admin123\n\n";
echo "Agent (agent screen):\n";
echo "  Username: agent1\n";
echo "  Password: agent123\n\n";
echo "  Username: agent2\n";
echo "  Password: agent123\n\n";
echo "Log out, then sign in at login.php with these credentials.\n";

$mysqli->close();
