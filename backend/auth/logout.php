<?php
require_once __DIR__ . '/../config/config.php';

// For JWT tokens, logout is typically handled client-side by removing the token
// This endpoint can be used for server-side token blacklisting if needed

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// In a stateless JWT system, logout is handled client-side
// If you need server-side logout, implement token blacklisting here

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);

?>

