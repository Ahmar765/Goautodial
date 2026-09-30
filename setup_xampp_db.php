<?php
$conn = @new mysqli('127.0.0.1', 'root', '', '', 3306);
if ($conn->connect_error) {
    die("ERROR: Could not connect to XAMPP MySQL: " . $conn->connect_error . "\nPlease ensure MySQL is started (green) in XAMPP Control Panel!\n");
}
echo "1. Connected to XAMPP MySQL successfully on port 3306!\n";
$conn->query("CREATE DATABASE IF NOT EXISTS goautodial CHARACTER SET utf8 COLLATE utf8_general_ci;");
echo "2. Database 'goautodial' created / verified.\n";
$conn->close();

putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_NAME=goautodial');
putenv('DB_PORT=3306');

chdir('C:/xampp/htdocs/v4.0-master');
include('C:/xampp/htdocs/v4.0-master/init_db.php');
?>
