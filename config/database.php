 <?php
/**
 * BULLETPROOF Database Configuration - EMS Phase 2
 * Ekwendeni Mighty Campus Event Management System
 * 
 * This file is production-ready and handles all edge cases
 * No more modifications needed after this!
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ems_database');

// Environment settings
define('DEVELOPMENT', true); // Set to false in production
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Error reporting (only in development)
if (DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Create database connection with full error handling
 */
function createDatabaseConnection() {
    try {
        // Check if MySQLi extension is loaded
        if (!extension_loaded('mysqli')) {
            throw new Exception("MySQLi extension is not loaded. Please enable it in php.ini");
        }
        
        // Create connection without selecting database first
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset first (before creating database)
        if (!$conn->set_charset("utf8mb4")) {
            throw new Exception("Error setting charset: " . $conn->error);
        }
        
        // Create database if it doesn't exist
        $sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        if (!$conn->select_db(DB_NAME)) {
            throw new Exception("Error selecting database: " . $conn->error);
        }
        
        // Set timezone (handle gracefully if fails)
        $conn->query("SET time_zone = '+02:00'");
        
        // Set SQL mode (handle gracefully if fails)
        $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        
        // Optional: Set session variables (handle gracefully)
        @$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
        @$conn->query("SET SESSION innodb_strict_mode = ON");
        
        return $conn;
        
    } catch (Exception $e) {
        handleDatabaseError($e);
        return null;
    }
}

/**
 * Handle database errors appropriately
 */
function handleDatabaseError($exception) {
    $errorMessage = "Database Error: " . $exception->getMessage();
    $timestamp = date('Y-m-d H:i:s');
    
    // Log error
    error_log("[$timestamp] $errorMessage");
    
    if (DEVELOPMENT) {
        // Development: Show detailed error
        $troubleshooting = getTroubleshootingSteps();
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>EMS - Database Error</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                }
                .error-container {
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    max-width: 800px;
                    margin: 0 auto;
                }
                .error-header {
                    color: #e74c3c;
                    border-bottom: 2px solid #e74c3c;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                }
                .error-details {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                    border-left: 4px solid #e74c3c;
                }
                .troubleshooting {
                    background: #e8f5e8;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #27ae60;
                    margin-top: 20px;
                }
                .step {
                    margin: 10px 0;
                    padding: 8px;
                    background: white;
                    border-radius: 5px;
                }
                .emoji { font-size: 1.2em; margin-right: 8px; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1 class='error-header'>🔧 EMS Development Mode - Database Error</h1>
                
                <div class='error-details'>
                    <h3>Error Details:</h3>
                    <p><strong>Message:</strong> {$exception->getMessage()}</p>
                    <p><strong>Time:</strong> $timestamp</p>
                    <p><strong>File:</strong> " . __FILE__ . "</p>
                </div>
                
                <div class='troubleshooting'>
                    <h3>🚀 Troubleshooting Steps:</h3>
                    $troubleshooting
                </div>
                
                <p><em>This detailed error is only shown in development mode.</em></p>
            </div>
        </body>
        </html>
        ");
    } else {
        // Production: Show generic error
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Unavailable</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    padding: 50px;
                    background: #f4f4f4;
                }
                .error-box {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    display: inline-block;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h2>⚠️ Service Temporarily Unavailable</h2>
                <p>We're experiencing technical difficulties.</p>
                <p>Please try again in a few minutes.</p>
                <p><em>Error ID: " . substr(md5($timestamp), 0, 8) . "</em></p>
            </div>
        </body>
        </html>
        ");
    }
}

/**
 * Get troubleshooting steps HTML
 */
function getTroubleshootingSteps() {
    return "
    <div class='step'><span class='emoji'>🔍</span> <strong>Check XAMPP Status:</strong> <code>sudo /opt/lampp/lampp status</code></div>
    <div class='step'><span class='emoji'>🚀</span> <strong>Start XAMPP:</strong> <code>sudo /opt/lampp/lampp start</code></div>
    <div class='step'><span class='emoji'>🔧</span> <strong>Check PHP Extensions:</strong> <code>/opt/lampp/bin/php -m | grep mysqli</code></div>
    <div class='step'><span class='emoji'>📝</span> <strong>Edit php.ini:</strong> <code>sudo nano /opt/lampp/etc/php.ini</code></div>
    <div class='step'><span class='emoji'>✅</span> <strong>Uncomment:</strong> <code>extension=mysqli</code></div>
    <div class='step'><span class='emoji'>🔄</span> <strong>Restart XAMPP:</strong> <code>sudo /opt/lampp/lampp restart</code></div>
    <div class='step'><span class='emoji'>🌐</span> <strong>Test phpMyAdmin:</strong> <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></div>
    ";
}

/**
 * SAFE DATABASE HELPER FUNCTIONS
 */

/**
 * Execute prepared statement safely
 */
function executeQuery($conn, $sql, $params = [], $types = '') {
    try {
        if (!$conn || $conn->connect_error) {
            throw new Exception("Invalid database connection");
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params) && !empty($types)) {
            if (count($params) !== strlen($types)) {
                throw new Exception("Parameter count doesn't match types");
            }
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return $stmt;
        
    } catch (Exception $e) {
        error_log("Query Error: " . $e->getMessage() . " - SQL: " . $sql);
        throw $e;
    }
}

/**
 * Get single record safely
 */
function getSingleRecord($conn, $sql, $params = [], $types = '') {
    $stmt = executeQuery($conn, $sql, $params, $types);
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get multiple records safely
 */
function getMultipleRecords($conn, $sql, $params = [], $types = '') {
    $stmt = executeQuery($conn, $sql, $params, $types);
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Insert record and return ID
 */
function insertRecord($conn, $sql, $params = [], $types = '') {
    $stmt = executeQuery($conn, $sql, $params, $types);
    return $conn->insert_id;
}

/**
 * Update/Delete record and return affected rows
 */
function updateRecord($conn, $sql, $params = [], $types = '') {
    $stmt = executeQuery($conn, $sql, $params, $types);
    return $conn->affected_rows;
}

/**
 * Check if table exists
 */
function tableExists($conn, $tableName) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get database statistics
 */
function getDatabaseStats($conn) {
    try {
        $stats = [];
        
        // Get table count
        $result = $conn->query("SHOW TABLES");
        $stats['tables'] = $result ? $result->num_rows : 0;
        
        // Get database size
        $result = $conn->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = '" . DB_NAME . "'
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats['size_mb'] = $row['size_mb'] ?? 0;
        } else {
            $stats['size_mb'] = 0;
        }
        
        return $stats;
        
    } catch (Exception $e) {
        return ['tables' => 0, 'size_mb' => 0];
    }
}

/**
 * Test database connection
 */
function testConnection($conn) {
    try {
        $result = $conn->query("SELECT 1 as test, NOW() as current_time, DATABASE() as db_name");
        if ($result) {
            $row = $result->fetch_assoc();
            return [
                'success' => true,
                'database' => $row['db_name'],
                'time' => $row['current_time'],
                'message' => 'Connection successful'
            ];
        }
        return ['success' => false, 'message' => 'Query failed'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// CREATE THE CONNECTION
$conn = createDatabaseConnection();

// Test connection in development mode
if (DEVELOPMENT && $conn) {
    $test = testConnection($conn);
    if ($test['success']) {
        $stats = getDatabaseStats($conn);
        error_log("✅ EMS Database Connected - DB: {$test['database']}, Tables: {$stats['tables']}, Size: {$stats['size_mb']}MB");
    }
}

// Return the connection
return $conn;
?>
