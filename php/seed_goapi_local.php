<?php
/**
 * Minimal Vicidial / goAPIv2 tables for local XAMPP (list + create outbound campaigns).
 * Run: php php/seed_goapi_local.php
 * Or:  http://localhost/v4.0-master/php/seed_goapi_local.php
 */
require_once __DIR__ . '/Config.php';

header('Content-Type: text/plain; charset=utf-8');

$dbName = defined('DB_NAME_ASTERISK') ? DB_NAME_ASTERISK : 'asterisk';
$dbGo = defined('DB_NAME') ? DB_NAME : 'goautodial';
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$user = defined('DB_USERNAME') ? DB_USERNAME : 'root';
$pass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;

$mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
if ($mysqli->connect_errno) {
	echo "Database connection failed: {$mysqli->connect_error}\n";
	exit(1);
}

function runSql(mysqli $db, $sql) {
	if (!$db->query($sql)) {
		echo "SQL error: {$db->error}\nOffending:\n{$sql}\n";
		exit(1);
	}
}

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS system_settings (
  version varchar(50) NOT NULL DEFAULT '2.14',
  pass_hash_enabled tinyint NOT NULL DEFAULT 0,
  pass_cost tinyint NOT NULL DEFAULT 2,
  pass_key varchar(20) NOT NULL DEFAULT 'goautodial',
  use_non_latin enum('0','1') NOT NULL DEFAULT '0',
  webroot_writable enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, "INSERT INTO system_settings (version, pass_hash_enabled, pass_cost, pass_key)
 SELECT '2.14-local', 0, 2, 'goautodial'
 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM system_settings LIMIT 1)");

// Full-enough campaign table for goAddCampaign OUTBOUND inserts
runSql($mysqli, "DROP TABLE IF EXISTS vicidial_campaigns");
runSql($mysqli, <<<'SQL'
CREATE TABLE vicidial_campaigns (
  campaign_id varchar(8) NOT NULL,
  campaign_name varchar(40) NOT NULL DEFAULT '',
  active enum('Y','N') NOT NULL DEFAULT 'Y',
  dial_method enum('MANUAL','RATIO','INBOUND_MAN','ADAPT_AVERAGE','ADAPT_HARD_LIMIT','ADAPT_TAPERED') NOT NULL DEFAULT 'MANUAL',
  dial_status_a varchar(20) NOT NULL DEFAULT '',
  dial_statuses varchar(255) NOT NULL DEFAULT '',
  lead_order varchar(30) NOT NULL DEFAULT 'DOWN',
  allow_closers enum('Y','N') NOT NULL DEFAULT 'Y',
  hopper_level int NOT NULL DEFAULT 100,
  auto_dial_level varchar(10) NOT NULL DEFAULT '0',
  next_agent_call varchar(40) NOT NULL DEFAULT 'oldest_call_finish',
  local_call_time varchar(40) NOT NULL DEFAULT '9am-9pm',
  dial_prefix varchar(20) NOT NULL DEFAULT '9',
  get_call_launch varchar(20) NOT NULL DEFAULT 'NONE',
  campaign_changedate datetime NULL,
  campaign_stats_refresh enum('Y','N') NOT NULL DEFAULT 'Y',
  list_order_mix varchar(20) NOT NULL DEFAULT 'DISABLED',
  dial_timeout tinyint NOT NULL DEFAULT 30,
  campaign_recording varchar(20) NOT NULL DEFAULT 'ONDEMAND',
  campaign_rec_filename varchar(50) NOT NULL DEFAULT '',
  scheduled_callbacks enum('Y','N') NOT NULL DEFAULT 'Y',
  scheduled_callbacks_alert varchar(20) NOT NULL DEFAULT 'BLINK_RED',
  no_hopper_leads_logins enum('Y','N') NOT NULL DEFAULT 'Y',
  use_internal_dnc enum('Y','N','AREACODE') NOT NULL DEFAULT 'Y',
  use_campaign_dnc enum('Y','N','AREACODE') NOT NULL DEFAULT 'Y',
  available_only_ratio_tally enum('Y','N') NOT NULL DEFAULT 'Y',
  campaign_cid varchar(20) NOT NULL DEFAULT '0000000000',
  use_custom_cid enum('Y','N','AREACODE','USER_CHOICE','') NOT NULL DEFAULT 'N',
  manual_dial_filter varchar(50) NOT NULL DEFAULT 'NONE',
  manual_dial_search_filter varchar(50) NOT NULL DEFAULT 'CAMPLISTS_ALL',
  user_group varchar(20) NOT NULL DEFAULT '---ALL---',
  manual_dial_list_id varchar(20) NOT NULL DEFAULT '',
  drop_call_seconds tinyint NOT NULL DEFAULT 7,
  campaign_vdad_exten varchar(20) NOT NULL DEFAULT '8368',
  disable_alter_custdata enum('Y','N') NOT NULL DEFAULT 'N',
  disable_alter_custphone enum('Y','N') NOT NULL DEFAULT 'Y',
  campaign_script varchar(20) NOT NULL DEFAULT '',
  campaign_allow_inbound enum('Y','N') NOT NULL DEFAULT 'N',
  modify_inbound_dids tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, "INSERT INTO vicidial_campaigns (campaign_id, campaign_name, dial_method, active, user_group)
 VALUES ('DEVLOCAL', 'Local Dev Campaign', 'MANUAL', 'Y', '---ALL---')
 ON DUPLICATE KEY UPDATE campaign_name = VALUES(campaign_name), active = 'Y'");

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_campaign_stats (
  campaign_id varchar(8) NOT NULL,
  dialable_leads int NOT NULL DEFAULT 0,
  PRIMARY KEY (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS servers (
  server_id int unsigned NOT NULL AUTO_INCREMENT,
  server_ip varchar(15) NOT NULL DEFAULT '127.0.0.1',
  server_description varchar(100) NOT NULL DEFAULT 'Local XAMPP',
  local_gmt varchar(6) NOT NULL DEFAULT '-5.00',
  asterisk_version varchar(20) NOT NULL DEFAULT '13.X',
  active enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (server_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
runSql($mysqli, "INSERT INTO servers (server_ip, server_description, local_gmt, active)
 SELECT '127.0.0.1', 'Local XAMPP', '5.00', 'Y'
 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM servers LIMIT 1)");

runSql($mysqli, "DROP TABLE IF EXISTS vicidial_list");
runSql($mysqli, <<<'SQL'
CREATE TABLE vicidial_list (
  lead_id int unsigned NOT NULL AUTO_INCREMENT,
  entry_date datetime NULL,
  modify_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status varchar(6) NOT NULL DEFAULT 'NEW',
  user varchar(20) NOT NULL DEFAULT '',
  vendor_lead_code varchar(20) NOT NULL DEFAULT '',
  source_id varchar(50) NOT NULL DEFAULT '',
  list_id bigint NOT NULL DEFAULT 0,
  gmt_offset_now decimal(4,2) NOT NULL DEFAULT 0.00,
  called_since_last_reset enum('Y','N','Y1','Y2','Y3','Y4','Y5','Y6','Y7','Y8','Y9','Y10') NOT NULL DEFAULT 'N',
  phone_code varchar(10) NOT NULL DEFAULT '1',
  phone_number varchar(20) NOT NULL DEFAULT '',
  title varchar(4) NOT NULL DEFAULT '',
  first_name varchar(30) NOT NULL DEFAULT '',
  middle_initial varchar(1) NOT NULL DEFAULT '',
  last_name varchar(30) NOT NULL DEFAULT '',
  address1 varchar(100) NOT NULL DEFAULT '',
  address2 varchar(100) NOT NULL DEFAULT '',
  address3 varchar(100) NOT NULL DEFAULT '',
  city varchar(50) NOT NULL DEFAULT '',
  state varchar(2) NOT NULL DEFAULT '',
  province varchar(50) NOT NULL DEFAULT '',
  postal_code varchar(10) NOT NULL DEFAULT '',
  country_code varchar(3) NOT NULL DEFAULT '',
  gender enum('M','F','U') NOT NULL DEFAULT 'U',
  date_of_birth date NULL,
  alt_phone varchar(20) NOT NULL DEFAULT '',
  email varchar(70) NOT NULL DEFAULT '',
  security_phrase varchar(100) NOT NULL DEFAULT '',
  comments varchar(255) NOT NULL DEFAULT '',
  called_count smallint NOT NULL DEFAULT 0,
  last_local_call_time datetime NULL,
  rank smallint NOT NULL DEFAULT 0,
  owner varchar(20) NOT NULL DEFAULT '',
  entry_list_id bigint NOT NULL DEFAULT 0,
  PRIMARY KEY (lead_id),
  KEY list_id (list_id),
  KEY phone_number (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_dnc (
  phone_number varchar(20) NOT NULL,
  PRIMARY KEY (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

// Extra columns list-info API expects
$listCols = array(
	'web_form_address' => "ALTER TABLE vicidial_lists ADD COLUMN web_form_address text",
	'agent_script_override' => "ALTER TABLE vicidial_lists ADD COLUMN agent_script_override varchar(20) NOT NULL DEFAULT ''",
	'campaign_cid_override' => "ALTER TABLE vicidial_lists ADD COLUMN campaign_cid_override varchar(20) NOT NULL DEFAULT ''",
	'am_message_exten_override' => "ALTER TABLE vicidial_lists ADD COLUMN am_message_exten_override varchar(20) NOT NULL DEFAULT ''",
	'drop_inbound_group_override' => "ALTER TABLE vicidial_lists ADD COLUMN drop_inbound_group_override varchar(20) NOT NULL DEFAULT ''",
	'xferconf_a_number' => "ALTER TABLE vicidial_lists ADD COLUMN xferconf_a_number varchar(50) NOT NULL DEFAULT ''",
	'xferconf_b_number' => "ALTER TABLE vicidial_lists ADD COLUMN xferconf_b_number varchar(50) NOT NULL DEFAULT ''",
	'xferconf_c_number' => "ALTER TABLE vicidial_lists ADD COLUMN xferconf_c_number varchar(50) NOT NULL DEFAULT ''",
	'xferconf_d_number' => "ALTER TABLE vicidial_lists ADD COLUMN xferconf_d_number varchar(50) NOT NULL DEFAULT ''",
	'xferconf_e_number' => "ALTER TABLE vicidial_lists ADD COLUMN xferconf_e_number varchar(50) NOT NULL DEFAULT ''",
);
foreach ($listCols as $col => $ddl) {
	$exists = $mysqli->query("SHOW COLUMNS FROM vicidial_lists LIKE '{$col}'");
	if ($exists && $exists->num_rows === 0) {
		runSql($mysqli, $ddl);
	}
}

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_lists (
  list_id bigint NOT NULL,
  list_name varchar(30) NOT NULL DEFAULT '',
  campaign_id varchar(8) NOT NULL DEFAULT '',
  active enum('Y','N') NOT NULL DEFAULT 'Y',
  list_description varchar(255) NOT NULL DEFAULT '',
  list_changedate datetime NULL,
  list_lastcalldate datetime NULL,
  reset_time varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (list_id),
  KEY campaign_id (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_lists_fields (
  field_id int unsigned NOT NULL AUTO_INCREMENT,
  list_id bigint NOT NULL DEFAULT 0,
  field_label varchar(50) NOT NULL DEFAULT '',
  field_name varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (field_id),
  KEY list_id (list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_hopper (
  hopper_id int unsigned NOT NULL AUTO_INCREMENT,
  lead_id int unsigned NOT NULL DEFAULT 0,
  campaign_id varchar(8) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'READY',
  list_id bigint NOT NULL DEFAULT 0,
  priority int NOT NULL DEFAULT 0,
  PRIMARY KEY (hopper_id),
  KEY campaign_id (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, "INSERT INTO vicidial_lists (list_id, list_name, campaign_id, active, list_description)
 VALUES (1001, 'Local Dev List', 'DEVLOCAL', 'Y', 'Seeded for local XAMPP')
 ON DUPLICATE KEY UPDATE list_name = VALUES(list_name), active = 'Y'");

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_phone_codes (
  country_code smallint NOT NULL DEFAULT 1,
  country char(3) NOT NULL DEFAULT 'USA',
  areacode smallint NOT NULL DEFAULT 0,
  state varchar(2) NOT NULL DEFAULT '',
  GMT_offset varchar(6) NOT NULL DEFAULT '0',
  DST enum('Y','N') NOT NULL DEFAULT 'N',
  DST_range varchar(30) NOT NULL DEFAULT '',
  geographic_description varchar(100) NOT NULL DEFAULT '',
  KEY country_code (country_code),
  KEY areacode (areacode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($mysqli, <<<'SQL'
CREATE TABLE IF NOT EXISTS vicidial_statuses (
  status varchar(6) NOT NULL,
  status_name varchar(30) NOT NULL DEFAULT '',
  selectable enum('Y','N') NOT NULL DEFAULT 'Y',
  human_answered enum('Y','N') NOT NULL DEFAULT 'N',
  category varchar(20) NOT NULL DEFAULT 'UNDEFINED',
  PRIMARY KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
runSql($mysqli, "INSERT IGNORE INTO vicidial_statuses (status,status_name,selectable,human_answered) VALUES
 ('NEW','New Lead','Y','N'),('A','Answering Machine','Y','N'),('B','Busy','Y','N'),
 ('N','No Answer','Y','N'),('NA','No Answer Busy','Y','N'),('DROP','Drop','N','N'),
 ('SALE','Sale','Y','Y'),('DNC','Do Not Call','Y','N')");

// Columns some goAPI user-group calls expect
$cols = array(
	'forced_timeclock_login' => "ALTER TABLE vicidial_user_groups ADD COLUMN forced_timeclock_login enum('Y','N') NOT NULL DEFAULT 'N'",
	'shift_enforcement' => "ALTER TABLE vicidial_user_groups ADD COLUMN shift_enforcement varchar(20) NOT NULL DEFAULT 'OFF'",
	'agent_call_log_view' => "ALTER TABLE vicidial_user_groups ADD COLUMN agent_call_log_view enum('Y','N') NOT NULL DEFAULT 'N'",
	'agent_xfer_consultative' => "ALTER TABLE vicidial_user_groups ADD COLUMN agent_xfer_consultative enum('Y','N') NOT NULL DEFAULT 'N'",
	'admin_viewable_groups' => "ALTER TABLE vicidial_user_groups ADD COLUMN admin_viewable_groups text",
);
foreach ($cols as $col => $ddl) {
	$exists = $mysqli->query("SHOW COLUMNS FROM vicidial_user_groups LIKE '{$col}'");
	if ($exists && $exists->num_rows === 0) {
		runSql($mysqli, $ddl);
	}
}

runSql($mysqli, "UPDATE vicidial_user_groups SET allowed_campaigns = ' ALL-CAMPAIGNS -' WHERE user_group = 'ADMIN'");
runSql($mysqli, "UPDATE vicidial_users SET pass_hash = pass, user_level = 9 WHERE user IN ('goadmin', 'goAPI')");

$apiUser = 'goAPI';
$apiPass = 'KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK';
$stmt = $mysqli->prepare(
	"INSERT INTO vicidial_users (user, pass, pass_hash, full_name, user_level, user_group, phone_login, phone_pass, email, active)
	 VALUES (?, ?, ?, 'goAPI Service', 9, 'ADMIN', '', '', 'goapi@localhost', 'Y')
	 ON DUPLICATE KEY UPDATE pass = VALUES(pass), pass_hash = VALUES(pass_hash), user_level = 9, user_group = 'ADMIN', active = 'Y'"
);
if ($stmt) {
	$stmt->bind_param('sss', $apiUser, $apiPass, $apiPass);
	$stmt->execute();
	$stmt->close();
}

$go = @new mysqli($host, $user, $pass, $dbGo, $port);
if ($go->connect_errno) {
	echo "goautodial DB failed: {$go->connect_error}\n";
	exit(1);
}

runSql($go, <<<'SQL'
CREATE TABLE IF NOT EXISTS go_campaigns (
  id int unsigned NOT NULL AUTO_INCREMENT,
  campaign_id varchar(20) NOT NULL,
  campaign_type varchar(20) NOT NULL DEFAULT 'OUTBOUND',
  location_id varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY campaign_id (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($go, <<<'SQL'
CREATE TABLE IF NOT EXISTS go_multi_tenant (
  tenant_id varchar(20) NOT NULL,
  tenant_name varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

runSql($go, <<<'SQL'
CREATE TABLE IF NOT EXISTS go_action_logs (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  user varchar(20) NOT NULL DEFAULT '',
  ip_address varchar(45) NOT NULL DEFAULT '',
  event_date datetime NOT NULL,
  action varchar(50) NOT NULL DEFAULT '',
  details text,
  db_query text,
  user_group varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

echo "OK — goAPIv2 local schema ready.\n";
echo "  asterisk: system_settings, vicidial_campaigns, vicidial_campaign_stats\n";
echo "  goautodial: go_campaigns, go_multi_tenant, go_action_logs\n";
echo "Sample campaign: DEVLOCAL\n\n";
echo "Note: New campaign IDs must be at least 8 characters (goAPIv2 rule).\n";
echo "Check: http://localhost/v4.0-master/php/goapi-status.php\n";

$mysqli->close();
$go->close();
