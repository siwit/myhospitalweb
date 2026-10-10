<?php
// setup_db.php
require_once 'config/db_connect.php';

try {
    echo "Setting up database tables...<br>";

    // 1. Create Users Table
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sqlUsers);
    echo "Users table created successfully.<br>";

    // 2. Create Default Admin User (if not exists)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'Admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        // Only create if Admin doesn't exist
        $defaultPass = password_hash('Admin', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES ('Admin', :pass, 'admin')");
        $stmt->execute(['pass' => $defaultPass]);
        echo "Default Admin user created (User: Admin, Pass: Admin).<br>";
    } else {
        echo "Default Admin user already exists.<br>";
    }

    // 3. Create News/Content Table
    // type: news, activity, procurement, job
    $sqlNews = "CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        type VARCHAR(50) NOT NULL, 
        source_url VARCHAR(512) DEFAULT NULL,
        image_url VARCHAR(255),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->exec($sqlNews);
    echo "News table created successfully.<br>";

    echo "Database setup complete!";

} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
