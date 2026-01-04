<?php
// General configuration file

// Enable error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS headers (allow Flutter app to access API)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// JWT Secret key (change this to a random string in production)
define('JWT_SECRET', 'habit_tracker_secret_key_2024_change_in_production');

// JWT token expiration time (24 hours)
define('JWT_EXPIRATION', 86400);

// Include database configuration
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

?>

