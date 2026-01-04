<?php
// Simple JWT implementation for authentication
// Note: For production, consider using a library like firebase/php-jwt

function generateToken($userId, $email) {
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    
    $payload = [
        'user_id' => $userId,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRATION
    ];
    
    $payloadEncoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true);
    $signatureEncoded = base64_encode($signature);
    
    return "$header.$payloadEncoded.$signatureEncoded";
}

function verifyToken($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return null;
    }
    
    list($header, $payloadEncoded, $signatureEncoded) = $parts;
    
    $signature = base64_decode($signatureEncoded);
    $expectedSignature = hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true);
    
    if (!hash_equals($signature, $expectedSignature)) {
        return null;
    }
    
    $payload = json_decode(base64_decode($payloadEncoded), true);
    
    if ($payload['exp'] < time()) {
        return null; // Token expired
    }
    
    return $payload;
}

function getAuthUser($conn) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    $payload = verifyToken($token);
    
    if (!$payload) {
        return null;
    }
    
    // Get user from database
    $userId = $payload['user_id'];
    $stmt = $conn->prepare("SELECT id, email, display_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        return $user;
    }
    
    return null;
}

?>

