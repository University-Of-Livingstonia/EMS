<?php
/**
 * Compatible Database Configuration
 * Works with both MySQLi and PDO
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ems_database');
define('DEVELOPMENT', true);

try {
    // Try MySQLi first
    if (class_exists('mysqli')) {
        echo "✅ Using MySQLi\n";
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            throw new Exception("MySQLi Connection failed: " . $conn->connect_error);
        }
        
        // Create database if needed
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if ($conn->query($sql) !== TRUE) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        $conn->select_db(DB_NAME);
        $conn->set_charset("utf8mb4");
        
    } 
    // Fallback to PDO
    elseif (class_exists('PDO')) {
        echo "⚡ Using PDO (fallback)\n";
        $dsn = "mysql:host=" . DB_HOST;
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        // Create database
        $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE " . DB_NAME);
        
    } else {
        throw new Exception("Neither MySQLi nor PDO is available!");
    }
    
    echo "🎉 Database connection successful!\n";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    
    if (DEVELOPMENT) {
        echo "\n🔧 Troubleshooting Steps:\n";
        echo "1. Check if XAMPP/LAMPP is running: sudo /opt/lampp/lampp status\n";
        echo "2. Start XAMPP: sudo /opt/lampp/lampp start\n";
        echo "3. Check PHP extensions: php -m | grep -E '(mysqli|pdo)'\n";
        echo "4. Edit php.ini: sudo nano /opt/lampp/etc/php.ini\n";
        echo "5. Uncomment: extension=mysqli and extension=pdo_mysql\n";
        echo "6. Restart XAMPP: sudo /opt/lampp/lampp restart\n";
    }
    
    exit(1);
}

return $conn;
?>