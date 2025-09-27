<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard correspondiente
if ($session->isLoggedIn()) {
    $userRole = $session->getUserRole();
    // Usar ruta relativa consistente
    header("Location: ../dashboard/$userRole.php");
    exit();
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
            
            // Buscar usuario con manejo de errores
            try {
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
                    try {
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
                            default:
                                // Tipo de usuario no reconocido
                                $error = 'Tipo de usuario no válido';
                                recordLoginAttempt($email, false);
                                break;
                        }
                        
                        if (empty($error)) {
                            // Configurar sesión con datos completos
                            $userComplete = $user;
                            $userComplete['perfil'] = $userData;
                            $userComplete['usuario_id'] = $user['id'];
                            $session->set('usuario', $userComplete);
                            
                            // Verificar que la sesión se guardó correctamente
                            if ($session->isLoggedIn() && $session->getUserRole() === $user['tipo_usuario']) {
                                // Registrar actividad
                                logActivity($user['id'], 'login', 'auth');
                                
                                // Limpiar cualquier output buffer antes de redireccionar
                                if (ob_get_level()) {
                                    ob_end_clean();
                                }
                                
                                // Redirigir usando ruta relativa consistente
                                header("Location: ../dashboard/{$user['tipo_usuario']}.php");
                                exit();
                            } else {
                                $error = 'Error al inicializar la sesión. Inténtalo nuevamente.';
                                // Limpiar sesión fallida
                                $session->destroy();
                            }
                        }
                        
                    } catch (Exception $e) {
                        error_log("Error obteniendo datos del usuario: " . $e->getMessage());
                        $error = 'Error interno. Inténtalo más tarde.';
                        recordLoginAttempt($email, false);
                    }
                    
                } else {
                    // Login fallido
                    recordLoginAttempt($email, false);
                    $error = 'Credenciales incorrectas';
                }
                
            } catch (Exception $e) {
                error_log("Error en consulta de login: " . $e->getMessage());
                $error = 'Error de conexión. Inténtalo más tarde.';
            }
        }
    }
}

// Mostrar página de login
$pageTitle = "Iniciar Sesión - " . APP_NAME;
include '../includes/header.php';
?>

<!-- Navigation Bar para usuarios no autenticados -->
<nav class="login-nav">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="../index.php" class="brand-link">
                <div class="brand-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span class="brand-text">ITA Social</span>
            </a>
        </div>
        
        <div class="nav-actions">
            <a href="../index.php" class="btn btn-ghost">
                <i class="fas fa-arrow-left"></i>
                Volver al Inicio
            </a>
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Registrarse
            </a>
        </div>
    </div>
</nav>

<!-- Background Pattern -->
<div class="background-pattern"></div>

<div class="login-container">
    <div class="login-wrapper">
        <!-- Left Side - Branding -->
        <div class="login-branding">
            <div class="branding-content">
                <div class="logo-container">
                    <div class="logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h2>ITA Social</h2>
                </div>
                
                <h1>Bienvenido de vuelta</h1>
                <p>Inicia sesión para acceder a tu panel de control del servicio social</p>
                
                <div class="features-preview">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Acceso seguro</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Multiplataforma</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Disponible 24/7</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-form-container">
            <div class="login-card">
                <div class="form-header">
                    <img src="../assets/images/logo-ita.png" alt="Logo ITA" class="login-logo">
                    <h2>Iniciar Sesión</h2>
                    <p>Sistema de Servicio Social</p>
                </div>
                
                <!-- PHP Error/Success Messages -->
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($flash = getFlashMessage()): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
                <?php endif; ?>
                
                <form class="login-form" method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <div class="input-container">
                            <i class="fas fa-envelope input-icon"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?= htmlspecialchars($email) ?>"
                                placeholder="tu-email@ita.mx"
                                required
                                autocomplete="email"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-container">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkmark"></span>
                            <span class="checkbox-text">Recordar sesión</span>
                        </label>
                        
                        <a href="forgot-password.php" class="forgot-link">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="loginBtn">
                        <span class="btn-text">Iniciar Sesión</span>
                        <span class="btn-loader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                </form>
                
                <div class="signup-prompt">
                    <p>¿No tienes cuenta?</p>
                    <div class="signup-options">
                        <a href="register.php" class="signup-link primary">
                            <i class="fas fa-user-graduate"></i>
                            Regístrate como estudiante
                        </a>
                        <a href="register-jefe.php" class="signup-link secondary">
                            <i class="fas fa-user-tie"></i>
                            Regístrate como jefe de laboratorio
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Access Cards -->
            <div class="quick-access">
                <div class="quick-card">
                    <i class="fas fa-question-circle"></i>
                    <h4>¿Necesitas ayuda?</h4>
                    <p>Consulta nuestras guías</p>
                    <a href="../help">Ver guías</a>
                </div>
                <div class="quick-card">
                    <i class="fas fa-phone"></i>
                    <h4>Soporte técnico</h4>
                    <p>Contacta a nuestro equipo</p>
                    <a href="mailto:soporte@ita.mx">Contactar</a>
                </div>
            </div>
        </div>
    </div>
</div>



<style>
:root {
    --primary-color: #6366f1;
    --primary-light: #8b8cf7;
    --primary-dark: #4f46e5;
    --secondary-color: #1f2937;
    --success-color: #10b981;
    --error-color: #ef4444;
    --warning-color: #f59e0b;
    --bg-primary: #0f1419;
    --bg-secondary: #1a202c;
    --bg-light: #f8fafc;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --border-color: #e5e7eb;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    line-height: 1.6;
    color: var(--text-primary);
    background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    /* Removido padding-top que causaba problemas */
}

/* Navigation Bar */
.login-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border-color);
    z-index: 1000;
    padding: 1rem 0;
    transition: var(--transition);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand .brand-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: inherit;
}

.brand-logo {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: var(--shadow);
}

.brand-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.nav-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid transparent;
}

.btn-ghost:hover {
    color: var(--primary-color);
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.2);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Background Pattern */
.background-pattern {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 140, 247, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(79, 70, 229, 0.1) 0%, transparent 50%);
    pointer-events: none;
    z-index: 1;
}

/* Main Container */
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6rem 2rem 2rem;
    position: relative;
    z-index: 2;
}

.login-wrapper {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
    max-width: 1000px;
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 600px;
}

/* Left Side - Branding */
.login-branding {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    padding: 3rem;
    display: flex;
    align-items: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.login-branding::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.branding-content {
    position: relative;
    z-index: 2;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.logo {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.logo-container h2 {
    font-size: 1.5rem;
    font-weight: 700;
}

.branding-content h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.branding-content p {
    font-size: 1.125rem;
    opacity: 0.9;
    margin-bottom: 3rem;
}

.features-preview {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius);
    backdrop-filter: blur(10px);
}

.feature-item i {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Right Side - Form */
.login-form-container {
    padding: 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-card {
    max-width: 400px;
    margin: 0 auto;
    width: 100%;
}

.form-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-logo {
    width: 60px;
    height: auto;
    margin-bottom: 1rem;
}

.form-header h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-header p {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-error {
    background: #fef2f2;
    color: var(--error-color);
    border: 1px solid #fecaca;
}

.alert-success {
    background: #f0fdf4;
    color: var(--success-color);
    border: 1px solid #bbf7d0;
}

.alert-warning {
    background: #fffbeb;
    color: var(--warning-color);
    border: 1px solid #fed7aa;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.input-container {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
    font-size: 0.9rem;
}

.input-container input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 1rem;
    font-family: inherit;
    transition: var(--transition);
    background: white;
}

.input-container input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.toggle-password {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.toggle-password:hover {
    color: var(--primary-color);
}

/* Form Options */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.checkbox-container {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.checkbox-container input {
    display: none;
}

.checkmark {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-color);
    border-radius: 4px;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.checkbox-container input:checked + .checkmark {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.checkbox-container input:checked + .checkmark::after {
    content: '✓';
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.forgot-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.forgot-link:hover {
    text-decoration: underline;
}

/* Primary Button (form) */
.login-form .btn-primary {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.login-form .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.login-form .btn-primary:active {
    transform: translateY(0);
}

.login-form .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Signup Prompt */
.signup-prompt {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.signup-prompt p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.signup-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.signup-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
}

.signup-link.primary {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.signup-link.secondary {
    background: rgba(107, 114, 128, 0.1);
    color: var(--text-secondary);
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.signup-link:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Quick Access */
.quick-access {
    margin-top: 2rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.quick-card {
    background: var(--bg-light);
    padding: 1.5rem;
    border-radius: var(--radius);
    text-align: center;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.quick-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.quick-card i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.quick-card h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.quick-card p {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.quick-card a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
}

/* Footer Styles */
.login-footer {
    background: var(--secondary-color);
    color: white;
    padding: 3rem 0 1rem;
    margin-top: 4rem;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-section h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: white;
}

.footer-brand {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.footer-logo {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.footer-info h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
}

.footer-info p {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0;
}

.footer-description {
    font-size: 0.95rem;
    line-height: 1.6;
    opacity: 0.9;
}

.footer-links {
    list-style: none;
    padding: 0;
}

.footer-links li {
    margin-bottom: 0.5rem;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.9rem;
    transition: var(--transition);
}

.footer-links a:hover {
    color: var(--primary-light);
    text-decoration: underline;
}

.social-links {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.social-links a {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: var(--transition);
}

.social-links a:hover {
    background: var(--primary-color);
    transform: translateY(-2px);
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-bottom p {
    font-size: 0.875rem;
    opacity: 0.8;
    margin: 0;
}

.footer-links-bottom {
    display: flex;
    gap: 2rem;
}

.footer-links-bottom a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.875rem;
    transition: var(--transition);
}

.footer-links-bottom a:hover {
    color: var(--primary-light);
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .nav-actions {
        gap: 0.5rem;
    }
    
    .nav-actions .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

@media (max-width: 768px) {
    .nav-container {
        padding: 0 1rem;
    }
    
    .brand-text {
        display: none;
    }
    
    .nav-actions {
        gap: 0.5rem;
    }
    
    .nav-actions .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .nav-actions .btn i {
        margin-right: 0;
    }
    
    .nav-actions .btn span {
        display: none;
    }

    .login-container {
        padding: 1rem;
        padding-top: 5rem;
    }

    .login-wrapper {
        grid-template-columns: 1fr;
        max-width: 500px;
    }

    .login-branding {
        display: none;
    }

    .login-form-container {
        padding: 2rem;
    }

    .form-options {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .quick-access {
        grid-template-columns: 1fr;
    }

    .signup-options {
        gap: 0.5rem;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-links-bottom {
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .login-container {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }
    
    .login-form-container {
        padding: 1.5rem;
    }
    
    .form-header h2 {
        font-size: 1.75rem;
    }
    
    .branding-content h1 {
        font-size: 2rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    // Form Submission Loading State
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    
    if (loginForm && loginBtn) {
        const btnText = loginBtn.querySelector('.btn-text');
        const btnLoader = loginBtn.querySelector('.btn-loader');

        loginForm.addEventListener('submit', function(e) {
            // Show loading state
            loginBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
            
            // Re-enable after 10 seconds in case of error
            setTimeout(() => {
                loginBtn.disabled = false;
                if (btnText) btnText.style.display = 'inline';
                if (btnLoader) btnLoader.style.display = 'none';
            }, 10000);
        });
    }

    // Input Focus Effects
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    
    // Header scroll effect para la navegación
    let lastScrollY = window.scrollY;
    const nav = document.querySelector('.login-nav');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav.style.background = 'rgba(255, 255, 255, 0.98)';
            nav.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        } else {
            nav.style.background = 'rgba(255, 255, 255, 0.95)';
            nav.style.boxShadow = 'none';
        }
        lastScrollY = window.scrollY;
    });
});
</script>

<?php include '../includes/footer.php'; ?>