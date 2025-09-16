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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'El email es obligatorio';
    } elseif (!validateEmail($email)) {
        $error = 'El formato del email no es válido';
    } else {
        $db = Database::getInstance();
        
        // Verificar si el email existe
        $user = $db->fetch("SELECT id, email FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
        
        if ($user) {
            // Generar token de recuperación
            $resetToken = generateToken();
            $resetTokenExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            
            try {
                $db->update('usuarios', [
                    'reset_token' => $resetToken,
                    'reset_token_expires' => $resetTokenExpires
                ], 'id = :id', ['id' => $user['id']]);
                
                // Enviar email de recuperación (simulado)
                $resetLink = BASE_URL . "auth/reset-password.php?token=$resetToken";
                
                // En un entorno real, se enviaría un email
                $success = "Se ha enviado un enlace de recuperación a $email";
                
                // Para desarrollo, mostramos el enlace
                if (APP_DEBUG) {
                    $success .= "<br><br>Enlace de desarrollo: <a href='$resetLink'>$resetLink</a>";
                }
                
            } catch (Exception $e) {
                $error = 'Error al procesar la solicitud: ' . $e->getMessage();
            }
        } else {
            // Por seguridad, no revelamos si el email existe o no
            $success = "Si el email existe en nuestro sistema, recibirás un enlace de recuperación";
        }
    }
}

$pageTitle = "Recuperar Contraseña - " . APP_NAME;
include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h1>Recuperar Contraseña</h1>
            <p>Ingresa tu email para recibir instrucciones de recuperación</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Enviar Instrucciones</button>
        </form>
        
        <div class="form-footer">
            <p><a href="login.php">Volver al inicio de sesión</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>