<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard correspondiente
if ($session->isLoggedIn()) {
    $userRole = $session->getUserRole();
    redirectTo("/dashboard/$userRole.php");
}

$error = '';
$email = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validar campos
    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son obligatorios';
    } elseif (!validateEmail($email)) {
        $error = 'El formato del email no es válido';
    } else {
        // Verificar intentos de login
        if (checkLoginAttempts($email)) {
            $error = 'Demasiados intentos fallidos. Espere 15 minutos e intente nuevamente.';
        } else {
            $db = Database::getInstance();
            
            // Buscar usuario
            $user = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Login exitoso
                recordLoginAttempt($email, true);
                
                // Actualizar último acceso
                $db->update('usuarios', 
                           ['ultimo_acceso' => date('Y-m-d H:i:s')], 
                           'id = :id', 
                           ['id' => $user['id']]);
                
                // Obtener datos específicos según el tipo de usuario
                $userData = [];
                switch ($user['tipo_usuario']) {
                    case 'estudiante':
                        $userData = getEstudianteData($user['id']);
                        break;
                    case 'jefe_departamento':
                        $userData = getJefeDepartamentoData($user['id']);
                        break;
                    case 'jefe_laboratorio':
                        $userData = getJefeLaboratorioData($user['id']);
                        break;
                }
                
                // Configurar sesión
                $session->set('usuario', array_merge($user, $userData));
                
                // Registrar actividad
                logActivity($user['id'], 'login', 'auth');
                
                // Redirigir al dashboard correspondiente
                redirectTo("/dashboard/{$user['tipo_usuario']}.php");
                
            } else {
                // Login fallido
                recordLoginAttempt($email, false);
                $error = 'Credenciales incorrectas';
            }
        }
    }
}

// Mostrar página de login
$pageTitle = "Iniciar Sesión - " . APP_NAME;
include '../includes/header.php';
?>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <img src="../assets/images/logo-ita.png" alt="Logo ITA" class="login-logo">
            <h1>Iniciar Sesión</h1>
            <p>Sistema de Servicio Social</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($flash = getFlashMessage()): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-options">
                <label class="checkbox">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Recordar sesión</span>
                </label>
                
                <a href="forgot-password.php">¿Olvidaste tu contraseña?</a>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
        </form>
        
        <div class="login-footer">
            <p>¿No tienes cuenta? 
                <a href="register.php">Regístrate como estudiante</a> o 
                <a href="register-jefe.php">Regístrate como jefe de laboratorio</a>
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>