<?php
// update_db.php
require_once 'config/db_connect.php';

try {
    echo "Updating database...<br>";

    // Add source_url column to news table if it doesn't exist
    // Attempting to add column, if it exists it might error or we can check first. 
    // MySQL 8.0 support 'IF NOT EXISTS' in ALTER TABLE ADD COLUMN but simpler to just try-catch or check schema.
    
    // Check if column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM news LIKE 'source_url'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "Column 'source_url' already exists.<br>";
    } else {
        $conn->exec("ALTER TABLE news ADD COLUMN source_url VARCHAR(512) DEFAULT NULL AFTER content");
        echo "Column 'source_url' added successfully.<br>";
    }

    echo "Update complete!";

} catch(PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
