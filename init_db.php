<?php
require_once __DIR__ . '/php/RequestGuard.php';
require_once __DIR__ . '/php/RuntimeConfig.php';
if (PHP_SAPI !== 'cli' || \creamy\RuntimeConfig::production()
    || !in_array('--reset-development-database', $argv ?? array(), true)) {
    fwrite(STDERR, "Development reset requires APP_ENV=development and --reset-development-database.\n");
    exit(1);
}
$initialPassword = \creamy\RuntimeConfig::required('INIT_ADMIN_PASSWORD');
if (strlen($initialPassword) < 12) {
    fwrite(STDERR, "INIT_ADMIN_PASSWORD must contain at least 12 characters.\n");
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once('./php/CRMDefaults.php');
require_once('./php/DbInstaller.php');
require_once('./php/DatabaseConnectorFactory.php');

echo "Initializing GOautodial / Creamy Database...\n";

try {
    require_once __DIR__ . '/php/Config.php';
    $dbhost = DB_HOST;
    $dbuser = DB_USERNAME;
    $dbpass = DB_PASSWORD;
    $dbname = DB_NAME;
    $dbport = DB_PORT;

    $installer = new \DBInstaller($dbhost, $dbname, $dbuser, $dbpass, $dbport);

    if ($installer->getState() != CRM_INSTALL_STATE_SUCCESS) {
        die("Installer connection error: " . $installer->getLastErrorMessage() . "\n");
    }

    echo "1. Dropping existing tables if any...\n";
    $installer->dropPreviousTables();

    echo "2. Setting up the development CRM administrator...\n";
    $adminCreated = $installer->setupBasicDatabase("admin", $initialPassword, CRM_ADMIN_EMAIL);
    if (!$adminCreated) {
        die("Failed setting up basic database: " . $installer->getLastErrorMessage() . "\n");
    }

    echo "3. Setting up Settings table...\n";
    $installer->setupSettingTable("UTC", "en_US", bin2hex(random_bytes(32)));

    echo "4. Setting up Customer Tables...\n";
    $installer->setupCustomerTables(CRM_DEFAULTS_CUSTOMERS_SCHEMA_DEFAULT, null);

    echo "5. Setting up Customer Statistics...\n";
    $installer->setupCustomersStatistics(CRM_DEFAULTS_CUSTOMERS_SCHEMA_DEFAULT, null);

    echo "6. Creating Sessions table (go_sessions)...\n";
    $db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL, $dbhost, $dbname, $dbuser, $dbpass, $dbport);
    $db->rawQuery("CREATE TABLE IF NOT EXISTS go_sessions ( session_id varchar(32) NOT NULL, user_agent varchar(255) NOT NULL, last_activity int(10) unsigned NOT NULL DEFAULT '0', user_data text, ip_address varchar(45) NOT NULL, PRIMARY KEY (session_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // Also touch the CRM_INSTALLED_FILE so system knows it's installed
    file_put_contents(CRM_INSTALLED_FILE, date('Y-m-d H:i:s'));

    echo "SUCCESS: Development CRM initialized. Full telephony schemas must be installed separately.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Development initialization failed: " . get_class($e) . "\n");
    exit(1);
}
?>
