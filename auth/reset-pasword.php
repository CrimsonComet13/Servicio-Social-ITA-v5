<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if ($session->isLoggedIn()) {
    redirectTo("/dashboard/{$session->getUserRole()}.php");
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verificar token
if (empty($token)) {
    $error = 'Token de recuperación no válido';
} else {
    $db = Database::getInstance();
    $user = $db->fetch("SELECT id, reset_token_expires FROM usuarios WHERE reset_token = ?", [$token]);
    
    if (!$user || strtotime($user['reset_token_expires']) < time()) {
        $error = 'El token de recuperación ha expirado o no es válido';
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'La contraseña es obligatoria';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $db->beginTransaction();
            
            // Actualizar contraseña
            $db->update('usuarios', [
                'password' => hashPassword($password),
                'reset_token' => null,
                'reset_token_expires' => null
            ], 'reset_token = :token', ['token' => $token]);
            
            $db->commit();
            
            $success = 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.';
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error al actualizar la contraseña: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Restablecer Contraseña - " . APP_NAME;
include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h1>Restablecer Contraseña</h1>
            <p>Crea una nueva contraseña para tu cuenta</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
                <div class="alert-actions">
                    <a href="login.php" class="btn btn-primary">Iniciar Sesión</a>
                </div>
            </div>
        <?php elseif (empty($error)): ?>
            <form method="POST" class="form">
                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Restablecer Contraseña</button>
            </form>
        <?php endif; ?>
        
        <div class="form-footer">
            <p><a href="login.php">Volver al inicio de sesión</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>