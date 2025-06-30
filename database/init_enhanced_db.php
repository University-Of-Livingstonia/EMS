<?php
/**
 * Enhanced Database Initialization Script - Phase 2
 * 
 * This script enhances the existing EMS database with Phase 2 features
 */

// Include database configuration
$conn = require_once __DIR__ . '/../config/database.php';

echo "🚀 Starting EMS Database Enhancement - Phase 2...\n";

try {
    // Read the enhanced schema file
    $sql = file_get_contents(__DIR__ . '/enhanced_schema.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read enhanced_schema.sql file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $conn->query($statement);
            $successCount++;
            echo "✅ Executed statement successfully\n";
        } catch (mysqli_sql_exception $e) {
            // Skip errors for ALTER TABLE if column already exists
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠️  Column already exists, skipping...\n";
                continue;
            }
            
            echo "❌ Error executing statement: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
            $errorCount++;
        }
    }
    
    echo "\n🎉 Database Enhancement Complete!\n";
    echo "✅ Successful operations: $successCount\n";
    echo "❌ Errors encountered: $errorCount\n";
    
    // Verify tables exist
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "\n📋 Available Tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
} catch (Exception $e) {
    echo "💥 Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Close the connection
$conn->close();

echo "\n🔥 Ready for Phase 2 Authentication System! 💪\n";
?>