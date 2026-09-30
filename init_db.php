<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('./php/CRMDefaults.php');
require_once('./php/DbInstaller.php');
require_once('./php/DatabaseConnectorFactory.php');

echo "Initializing GOautodial / Creamy Database...\n";

try {
    $dbhost = getenv('DB_HOST') ?: 'db';
    $dbuser = getenv('DB_USER') ?: 'goautodial';
    $dbpass = getenv('DB_PASS') ?: 'goautodial';
    $dbname = getenv('DB_NAME') ?: 'goautodial';
    $dbport = getenv('DB_PORT') ?: '3306';

    $installer = new \DBInstaller($dbhost, $dbname, $dbuser, $dbpass, $dbport);

    if ($installer->getState() != CRM_INSTALL_STATE_SUCCESS) {
        die("Installer connection error: " . $installer->getLastErrorMessage() . "\n");
    }

    echo "1. Dropping existing tables if any...\n";
    $installer->dropPreviousTables();

    echo "2. Setting up Basic Database & Admin User (admin / password)...\n";
    $adminCreated = $installer->setupBasicDatabase("admin", "password", "admin@localhost.com");
    if (!$adminCreated) {
        die("Failed setting up basic database: " . $installer->getLastErrorMessage() . "\n");
    }

    echo "3. Setting up Settings table...\n";
    $installer->setupSettingTable("America/New_York", "en_US", "dev_secret_token_12345");

    echo "4. Setting up Customer Tables...\n";
    $installer->setupCustomerTables(CRM_DEFAULTS_CUSTOMERS_SCHEMA_DEFAULT, null);

    echo "5. Setting up Customer Statistics...\n";
    $installer->setupCustomersStatistics(CRM_DEFAULTS_CUSTOMERS_SCHEMA_DEFAULT, null);

    echo "6. Creating Sessions table (go_sessions)...\n";
    $db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL, $dbhost, $dbname, $dbuser, $dbpass, $dbport);
    $db->rawQuery("CREATE TABLE IF NOT EXISTS go_sessions ( session_id varchar(32) NOT NULL, user_agent varchar(255) NOT NULL, last_activity int(10) unsigned NOT NULL DEFAULT '0', user_data text, ip_address varchar(45) NOT NULL, PRIMARY KEY (session_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

    // Also touch the CRM_INSTALLED_FILE so system knows it's installed
    file_put_contents(CRM_INSTALLED_FILE, date('Y-m-d H:i:s'));

    echo "SUCCESS: Database initialization complete! Login credentials: admin / password\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
?>
