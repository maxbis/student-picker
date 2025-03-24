<?php
// Include database credentials
if (!file_exists('db_credentials.php')) {
    die('Database credentials file not found. Please create db_credentials.php with your database connection details.');
}

require_once 'db_credentials.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
} 