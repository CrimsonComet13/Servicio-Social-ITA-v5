<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if (!$session->isLoggedIn()) {
    redirectTo("/auth/login.php");
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verificar si ya está verificado
$user = $session->getUser();
if ($user['email_verificado']) {
    $success = 'Tu email ya está verificado';
}

// Procesar verificación por token
if (!empty($token) && empty($success)) {
    $db = Database::getInstance();
    $userData = $db->fetch("SELECT id, token_verificacion FROM usuarios WHERE id = ?", [$user['id']]);
    
    if ($userData && $userData['token_verificacion'] === $token) {
        try {
            $db->update('usuarios', [
                'email_verificado' => true,
                'token_verificacion' => null
            ], 'id = :id', ['id' => $user['id']]);
            
            // Actualizar sesión
            $session->set('usuario', array_merge($session->getUser(), ['email_verificado' => true]));
            
            $success = 'Email verificado correctamente';
            
        } catch (Exception $e) {
            $error = 'Error al verificar el email: ' . $e->getMessage();
        }
    } else {
        $error = 'Token de verificación no válido';
    }
}

// Reenviar email de verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($success)) {
    try {
        $db = Database::getInstance();
        
        // Generar nuevo token
        $newToken = generateToken();
        
        $db->update('usuarios', [
            'token_verificacion' => $newToken
        ], 'id = :id', ['id' => $user['id']]);
        
        // Enviar email de verificación (simulado)
        $verifyLink = BASE_URL . "auth/verify-email.php?token=$newToken";
        
        // En un entorno real, se enviaría un email
        $success = "Se ha enviado un nuevo enlace de verificación a {$user['email']}";
        
        // Para desarrollo, mostramos el enlace
        if (APP_DEBUG) {
            $success .= "<br><br>Enlace de desarrollo: <a href='$verifyLink'>$verifyLink</a>";
        }
        
    } catch (Exception $e) {
        $error = 'Error al enviar el email de verificación: ' . $e->getMessage();
    }
}

$pageTitle = "Verificar Email - " . APP_NAME;
include '../includes/header.php';

// Si el usuario está logueado, incluir sidebar
if ($session->isLoggedIn()) {
    include '../includes/sidebar.php';
    echo '<div class="dashboard-content">';
}
?>

<div class="<?= $session->isLoggedIn() ? 'dashboard-container' : 'container' ?>">
    <div class="form-container">
        <div class="form-header">
            <h1>Verificación de Email</h1>
            <p>Confirma tu dirección de email para acceder a todas las funcionalidades</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!$user['email_verificado'] && empty($success)): ?>
            <div class="verification-info">
                <p>Hemos enviado un enlace de verificación a <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>
                <p>Si no has recibido el email, puedes solicitar uno nuevo.</p>
                
                <form method="POST" class="form">
                    <button type="submit" class="btn btn-primary">Reenviar Email de Verificación</button>
                </form>
                
                <div class="verification-help">
                    <p>¿Problemas con la verificación? <a href="/contacto.php">Contacta con soporte</a></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="form-footer">
            <p><a href="/dashboard/<?= $user['tipo_usuario'] ?>.php">Volver al Dashboard</a></p>
        </div>
    </div>
</div>

<?php 
if ($session->isLoggedIn()) {
    echo '</div>'; // Cerrar dashboard-content
    include '../includes/footer.php';
} else {
    include '../includes/footer.php';
}
?>