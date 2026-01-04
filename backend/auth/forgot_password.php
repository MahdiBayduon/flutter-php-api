<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = getDBConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, display_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Always return success (for security, don't reveal if email exists)
    if ($user = $result->fetch_assoc()) {
        // In a real application, you would:
        // 1. Generate a password reset token
        // 2. Store it in the database with an expiration time
        // 3. Send an email with a reset link
        // For now, we'll just return success
        
        // TODO: Implement email sending functionality
        // mail($email, 'Password Reset', 'Reset link here...');
    }
    
    $stmt->close();
    
    // Always return success to prevent email enumeration
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, a password reset link has been sent.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    closeDBConnection($conn);
}

?>

