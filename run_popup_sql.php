<?php
// run_popup_sql.php - Alternative database updater
// Please put this file where your index.php is accessible

require_once 'config/db_connect.php';

try {
    echo "<h1>Creating Popup Table...</h1>";
    $sql = "CREATE TABLE IF NOT EXISTS popup_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_active BOOLEAN DEFAULT 0,
        image_url VARCHAR(255),
        link_url VARCHAR(512),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "<p style='color:green;'>Table popup_config created.</p>";
    
    // Check initial data
    $count = $conn->query("SELECT COUNT(*) FROM popup_config")->fetchColumn();
    if ($count == 0) {
        $conn->exec("INSERT INTO popup_config (is_active, image_url) VALUES (0, '')");
        echo "<p>Inserted default popup config.</p>";
    } else {
        echo "<p>Config already exists.</p>";
    }
    
} catch(PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
?>
