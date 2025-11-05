<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Ganhata05!');  
define('DB_NAME', 'pphase_3');

// Connect to database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to handle special characters
mysqli_set_charset($conn, "utf8mb4");

// Base paths
define('BASE_PATH', __DIR__ . '/');
define('DATA_PATH', BASE_PATH . 'data/');
define('UPLOAD_PATH', DATA_PATH . 'uploads/');

// Start session for login
session_start();

// Timezone
date_default_timezone_set('Australia/Melbourne');
?>