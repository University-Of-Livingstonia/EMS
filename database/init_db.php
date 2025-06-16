<?php
/**
 * Database Initialization Script
 * 
 * This script initializes the database for the Ekwendeni Mighty Campus
 * Event Management System by executing the SQL schema.
 */

// Include database configuration
$conn = require_once __DIR__ . '/../config/database.php';

// Read the schema file
$sql = file_get_contents(__DIR__ . '/schema.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "Database initialized successfully!\n";
    
    // Process all result sets to clear them
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
} else {
    echo "Error initializing database: " . $conn->error . "\n";
}

// Close the connection
$conn->close();
?>
