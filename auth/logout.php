<?php
require_once '../config/config.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Registrar actividad de logout
if ($session->isLoggedIn()) {
    $user = $session->getUser();
    logActivity($user['id'], 'logout', 'auth');
}

// Destruir la sesión
$session->destroy();

// Redirigir al login con mensaje
flashMessage('Sesión cerrada correctamente', 'success');
header('Location: ../auth/login.php');
exit();
?>