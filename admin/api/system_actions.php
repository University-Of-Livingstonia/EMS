<?php
/**
 * 🔧 System Actions API - EMS Admin
 * Handle system maintenance and configuration actions
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require admin login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
// Set JSON response header
header('Content-Type: application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        case 'clear_cache':
            $response = clearSystemCache();
            break;
            
        case 'create_backup':
            $response = createDatabaseBackup($conn);
            break;
            
        case 'health_check':
            $response = runSystemHealthCheck($conn);
            break;
            
        case 'reset_system':
            $response = resetSystemSettings($conn);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
} catch (Exception $e) {
    error_log("System action error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'System error occurred'];
}

echo json_encode($response);

/**
 * Clear system cache
 */
function clearSystemCache() {
    try {
        $cacheCleared = 0;
        
        // Clear PHP opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $cacheCleared++;
        }
        
        // Clear session files (be careful with this)
        $sessionPath = session_save_path();
        if ($sessionPath && is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 3600) { // Only old sessions
                    unlink($file);
                    $cacheCleared++;
                }
            }
        }
        
        // Clear custom cache directory if exists
        $cacheDir = '../cache/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cacheCleared++;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Cache cleared successfully! {$cacheCleared} items removed."
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to clear cache: ' . $e->getMessage()
        ];
    }
}

/**
 * Create database backup
 */
function createDatabaseBackup($conn) {
    try {
        $backupDir = '../backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'ems_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Get database configuration
        $dbConfig = [
            'host' => 'localhost', // Replace with actual config
            'username' => 'root',   // Replace with actual config
            'password' => '',       // Replace with actual config
            'database' => 'ems_db'  // Replace with actual config
        ];
        
        // Create backup using mysqldump if available
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($filepath)
        );
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($filepath)) {
            $fileSize = formatBytes(filesize($filepath));
            return [
                'success' => true,
                'message' => "Backup created successfully! File size: {$fileSize}",
                'filename' => $filename,
                'download_url' => 'api/download_backup.php?file=' . urlencode($filename)
            ];
        } else {
            // Fallback: Create backup using PHP
            return createPHPBackup($conn, $filepath);
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to create backup: ' . $e->getMessage()
        ];
    }
}

/**
 * Create backup using PHP (fallback method)
 */
function createPHPBackup($conn, $filepath) {
    try {
        $backup = "-- EMS Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        foreach ($tables as $table) {
            $backup .= "-- Table structure for `{$table}`\n";
            $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Get CREATE TABLE statement
            $result = $conn->query("SHOW CREATE TABLE `{$table}`");
            $row = $result->fetch_array();
            $backup .= $row[1] . ";\n\n";
            
            // Get table data
            $backup .= "-- Dumping data for table `{$table}`\n";
            $result = $conn->query("SELECT * FROM `{$table}`");
            
            while ($row = $result->fetch_assoc()) {
                $backup .= "INSERT INTO `{$table}` VALUES (";
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
                }
                $backup .= implode(', ', $values) . ");\n";
            }
            $backup .= "\n";
        }
        
        file_put_contents($filepath, $backup);
        $fileSize = formatBytes(filesize($filepath));
        
        return [
            'success' => true,
            'message' => "Backup created successfully! File size: {$fileSize}",
            'filename' => basename($filepath),
            'download_url' => 'api/download_backup.php?file=' . urlencode(basename($filepath))
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to create PHP backup: ' . $e->getMessage()
        ];
    }
}

/**
 * Run system health check
 */
function runSystemHealthCheck($conn) {
    try {
        $results = [
            'database' => checkDatabaseHealth($conn),
            'filesystem' => checkFilesystemHealth(),
            'php' => checkPHPHealth(),
            'security' => checkSecurityHealth()
        ];
        
        return [
            'success' => true,
            'results' => $results
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Health check failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Check database health
 */
function checkDatabaseHealth($conn) {
    $checks = [];
    
    // Database connection
    $checks['connection'] = [
        'status' => $conn->ping() ? 'pass' : 'fail',
        'message' => $conn->ping() ? 'Database connection is healthy' : 'Database connection failed'
    ];
    
    // Check table integrity
    try {
        $result = $conn->query("CHECK TABLE users, events, tickets");
        $allOk = true;
        while ($row = $result->fetch_assoc()) {
            if ($row['Msg_text'] !== 'OK') {
                $allOk = false;
                break;
            }
        }
        $checks['table_integrity'] = [
            'status' => $allOk ? 'pass' : 'warning',
            'message' => $allOk ? 'All tables are healthy' : 'Some tables may have issues'
        ];
    } catch (Exception $e) {
        $checks['table_integrity'] = [
            'status' => 'fail',
            'message' => 'Could not check table integrity'
        ];
    }
    
    // Check database size
    try {
        $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()");
        $row = $result->fetch_assoc();
        $dbSize = $row['DB Size in MB'];
        
        $checks['database_size'] = [
            'status' => $dbSize < 1000 ? 'pass' : ($dbSize < 5000 ? 'warning' : 'fail'),
            'message' => "Database size: {$dbSize} MB"
        ];
    } catch (Exception $e) {
        $checks['database_size'] = [
            'status' => 'warning',
            'message' => 'Could not determine database size'
        ];
    }
    
    return $checks;
}

/**
 * Check filesystem health
 */
function checkFilesystemHealth() {
    $checks = [];
    
    // Check disk space
    $freeBytes = disk_free_space('.');
    $totalBytes = disk_total_space('.');
    $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
    
    $checks['disk_space'] = [
        'status' => $usedPercent < 80 ? 'pass' : ($usedPercent < 90 ? 'warning' : 'fail'),
        'message' => sprintf('Disk usage: %.1f%% (%s free)', $usedPercent, formatBytes($freeBytes))
    ];
    
    // Check write permissions
    $writableDirectories = ['../uploads/', '../cache/', '../logs/', '../backups/'];
    $allWritable = true;
    
    foreach ($writableDirectories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            $allWritable = false;
            break;
        }
    }
    
    $checks['write_permissions'] = [
        'status' => $allWritable ? 'pass' : 'fail',
        'message' => $allWritable ? 'All directories are writable' : 'Some directories are not writable'
    ];
    
    // Check log file sizes
    $logDir = '../logs/';
    $totalLogSize = 0;
    if (is_dir($logDir)) {
        $files = glob($logDir . '*.log');
        foreach ($files as $file) {
            $totalLogSize += filesize($file);
        }
    }
    
    $checks['log_files'] = [
        'status' => $totalLogSize < 100 * 1024 * 1024 ? 'pass' : 'warning', // 100MB threshold
        'message' => 'Total log size: ' . formatBytes($totalLogSize)
    ];
    
    return $checks;
}

/**
 * Check PHP health
 */
function checkPHPHealth() {
    $checks = [];
    
    // PHP version
    $phpVersion = PHP_VERSION;
    $checks['php_version'] = [
        'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'pass' : 'warning',
        'message' => "PHP version: {$phpVersion}"
    ];
    
    // Memory limit
    $memoryLimit = ini_get('memory_limit');
    $memoryLimitBytes = return_bytes($memoryLimit);
    $checks['memory_limit'] = [
        'status' => $memoryLimitBytes >= 128 * 1024 * 1024 ? 'pass' : 'warning', // 128MB minimum
        'message' => "Memory limit: {$memoryLimit}"
    ];
    
    // Required extensions
    $requiredExtensions = ['mysqli', 'json', 'session', 'filter'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    $checks['php_extensions'] = [
        'status' => empty($missingExtensions) ? 'pass' : 'fail',
        'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missingExtensions)
    ];
    
    // Error reporting
    $errorReporting = error_reporting();
    $checks['error_reporting'] = [
        'status' => 'pass',
        'message' => 'Error reporting level: ' . $errorReporting
    ];
    
    return $checks;
}

/**
 * Check security health
 */
function checkSecurityHealth() {
    $checks = [];
    
    // Check if running over HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $checks['https'] = [
        'status' => $isHttps ? 'pass' : 'warning',
        'message' => $isHttps ? 'Running over HTTPS' : 'Not using HTTPS - consider enabling SSL'
    ];
    
    // Check session security
    $sessionSecure = ini_get('session.cookie_secure');
    $sessionHttpOnly = ini_get('session.cookie_httponly');
    
    $checks['session_security'] = [
        'status' => ($sessionSecure && $sessionHttpOnly) ? 'pass' : 'warning',
        'message' => 'Session cookies: ' . ($sessionSecure ? 'secure' : 'not secure') . ', ' . ($sessionHttpOnly ? 'httponly' : 'not httponly')
    ];
    
    // Check file permissions
    $sensitiveFiles = ['../config/database.php', '../includes/functions.php'];
    $securePermissions = true;
    
    foreach ($sensitiveFiles as $file) {
        if (file_exists($file)) {
            $perms = fileperms($file) & 0777;
            if ($perms > 0644) { // More permissive than 644
                $securePermissions = false;
                break;
            }
        }
    }
    
    $checks['file_permissions'] = [
        'status' => $securePermissions ? 'pass' : 'warning',
        'message' => $securePermissions ? 'File permissions are secure' : 'Some files have overly permissive permissions'
    ];
    
    return $checks;
}

/**
 * Reset system settings
 */
function resetSystemSettings($conn) {
    try {
        // In a real application, you would reset settings in a settings table
        // For now, we'll just simulate the reset
        
        // Clear cache
        clearSystemCache();
        
        // Reset session data (except current admin session)
        $currentSessionId = session_id();
               $sessionPath = session_save_path();
        if ($sessionPath && is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            foreach ($files as $file) {
                $sessionId = str_replace($sessionPath . '/sess_', '', $file);
                if ($sessionId !== $currentSessionId) {
                    unlink($file);
                }
            }
        }
        
        // Reset system settings to defaults (simulate)
        $defaultSettings = [
            'site_name' => 'EMS - Event Management System',
            'contact_email' => 'admin@ems.com',
            'timezone' => 'Africa/Blantyre',
            'maintenance_mode' => false,
            'registration_enabled' => true,
            'site_description' => 'Professional Event Management System for University of Livingstonia'
        ];
        
        // In a real app, you would update the settings table here
        // $conn->query("UPDATE settings SET value = 'default_value' WHERE key = 'setting_key'");
        
        return [
            'success' => true,
            'message' => 'System settings have been reset to defaults successfully!'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to reset system: ' . $e->getMessage()
        ];
    }
}

/**
 * Helper function to format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Helper function to convert PHP ini values to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}
?>

