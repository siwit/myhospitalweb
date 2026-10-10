<?php
// update_popup_table.php
require_once 'config/db_connect.php';

try {
    echo "Updating database for popup...<br>";

    // Create Popup Configuration Table
    $sqlPopup = "CREATE TABLE IF NOT EXISTS popup_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_active BOOLEAN DEFAULT 0,
        image_url VARCHAR(255),
        link_url VARCHAR(512),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sqlPopup);
    echo "Popup table created successfully.<br>";

    // Insert default record if empty
    $stmt = $conn->prepare("SELECT COUNT(*) FROM popup_config");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO popup_config (is_active, image_url) VALUES (0, '')");
        echo "Default popup config inserted.<br>";
    }

    echo "Update complete!";

} catch(PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
