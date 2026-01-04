<?php
// Database configuration
// Update these values to match your phpMyAdmin setup

define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Change to your database username
define('DB_PASS', '');      // Change to your database password
define('DB_NAME', 'habit_tracker');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
        exit;
    }
}

// Close database connection
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

?>

