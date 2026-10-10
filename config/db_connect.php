<?php
// config/db_connect.php

$host = 'localhost';
$user = 'sa';
$pass = 'sa';
$dbname = 'myhospit_mywebdb'; 
// We will create this DB if it doesn't exist
// $host = 'localhost';
// $user = 'myhospit_myweb';
// $pass = '11602@MY@hospital';
// $dbname = 'myhospit_mywebdb'; 


try {
    // Connect to MySQL server first (without selecting DB) to create DB if needed
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Connect to the specific database
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
