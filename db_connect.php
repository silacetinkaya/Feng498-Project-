<?php
// db_connect.php
// KENDİ DB ŞİFRENİ FALAN GİRCEN
// CONFIGURATION: Fill in your specific database details here
$host = 'localhost';
$db_name = 'testdb'; // <--- ENTER DB NAME
$username = 'postgres';     // <--- ENTER DB USER
$password = 'murat123'; // <--- ENTER DB PASSWORD
$port = '5432'; // Default PostgreSQL port

try {
    // specific DSN for PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$db_name";
    
    // Create a PDO instance (more secure than standard pg_connect)
    $pdo = new PDO($dsn, $username, $password);
    
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // In a real production app, log this error instead of echoing it
    die("Connection failed: " . $e->getMessage());
}

?>
