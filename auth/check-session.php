<?php
require_once '../config/config.php';
require_once '../config/session.php';

// Establecer headers para JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $session = SecureSession::getInstance();
    
    $response = [
        'valid' => $session->isLoggedIn(),
        'timestamp' => time()
    ];
    
    if ($response['valid']) {
        $user = $session->getUser();
        $response['user_role'] = $session->getUserRole();
        $response['user_id'] = $user['id'] ?? null;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'error' => 'Session check failed',
        'timestamp' => time()
    ]);
}
?>