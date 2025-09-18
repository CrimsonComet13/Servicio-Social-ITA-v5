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

$errors = [];
$success = '';
$formData = [
    'email' => '',
    'nombre' => '',
    'laboratorio' => '',
    'especialidad' => '',
    'telefono' => '',
    'extension' => ''
];

// Obtener jefes de departamento para el select
$db = Database::getInstance();
$jefesDepartamento = $db->fetchAll("SELECT id, nombre, departamento FROM jefes_departamento ORDER BY nombre");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_map('sanitizeInput', $_POST);
    
    // Validar campos
    if (empty($formData['email'])) {
        $errors['email'] = 'El email es obligatorio';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'El formato del email no es válido';
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = 'La contraseña es obligatoria';
    } elseif (strlen($formData['password']) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    if (empty($formData['nombre'])) {
        $errors['nombre'] = 'El nombre es obligatorio';
    }
    
    if (empty($formData['laboratorio'])) {
        $errors['laboratorio'] = 'El laboratorio es obligatorio';
    }
    
    if (empty($formData['jefe_departamento_id'])) {
        $errors['jefe_departamento_id'] = 'Debe seleccionar un jefe de departamento';
    }
    
    // Verificar si el email ya existe
    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$formData['email']]);
    if ($existingUser) {
        $errors['email'] = 'Ya existe un usuario con este email';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Crear usuario
            $userId = $db->insert('usuarios', [
                'email' => $formData['email'],
                'password' => hashPassword($formData['password']),
                'tipo_usuario' => 'jefe_laboratorio',
                'activo' => false,
                'email_verificado' => false,
                'token_verificacion' => generateToken()
            ]);
            
            // Crear jefe de laboratorio
            $jefeLabId = $db->insert('jefes_laboratorio', [
                'usuario_id' => $userId,
                'jefe_departamento_id' => $formData['jefe_departamento_id'],
                'nombre' => $formData['nombre'],
                'laboratorio' => $formData['laboratorio'],
                'especialidad' => $formData['especialidad'] ?? null,
                'telefono' => $formData['telefono'] ?? null,
                'extension' => $formData['extension'] ?? null,
                'activo' => false
            ]);
            
            $db->commit();
            
            // Notificar al jefe de departamento
            $jefeDepartamento = $db->fetch("SELECT usuario_id FROM jefes_departamento WHERE id = ?", 
                                          [$formData['jefe_departamento_id']]);
            
            if ($jefeDepartamento) {
                createNotification(
                    $jefeDepartamento['usuario_id'],
                    'Nueva solicitud de registro',
                    "El jefe de laboratorio {$formData['nombre']} ha solicitado registro en el sistema.",
                    'info',
                    "/modules/departamento/laboratorios.php"
                );
            }
            
            $success = 'Solicitud de registro enviada. Debe ser aprobada por el jefe de departamento.';
            $formData = [];
            
        } catch (Exception $e) {
            $db->rollback();
            $errors['general'] = 'Error en el registro: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Registro de Jefe de Laboratorio - " . APP_NAME;
include '../includes/header.php';
?>

<!-- Background Pattern -->
<div class="background-pattern"></div>

<!-- Navigation Back -->
<nav class="nav-back">
    <a href="login.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        <span>Volver al login</span>
    </a>
</nav>

<div class="register-container">
    <div class="register-wrapper">
        <!-- Left Side - Branding -->
        <div class="register-branding">
            <div class="branding-content">
                <div class="logo-container">
                    <div class="logo">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h2>ITA Social</h2>
                </div>
                
                <h1>Únete como Jefe de Laboratorio</h1>
                <p>Gestiona tu laboratorio y supervisa las actividades de servicio social de tus estudiantes</p>
                
                <div class="features-preview">
                    <div class="feature-item">
                        <i class="fas fa-flask"></i>
                        <span>Gestión de laboratorio</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Supervisión de estudiantes</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Reportes y seguimiento</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Programación de actividades</span>
                    </div>
                </div>
                
                <div class="process-info">
                    <h3>Proceso de registro</h3>
                    <div class="process-step">
                        <span class="step-number">1</span>
                        <span>Completa el formulario</span>
                    </div>
                    <div class="process-step">
                        <span class="step-number">2</span>
                        <span>Espera la aprobación</span>
                    </div>
                    <div class="process-step">
                        <span class="step-number">3</span>
                        <span>Accede a tu panel</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Register Form -->
        <div class="register-form-container">
            <div class="register-card">
                <div class="form-header">
                    <img src="../assets/images/logo-ita.png" alt="Logo ITA" class="register-logo">
                    <h2>Registro de Jefe de Laboratorio</h2>
                    <p>Complete la información requerida</p>
                </div>
                
                <!-- PHP Error/Success Messages -->
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>¡Solicitud enviada!</strong>
                        <p><?= htmlspecialchars($success) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errors['general']) ?></span>
                </div>
                <?php endif; ?>
                
                <form class="register-form" method="POST" id="registerForm">
                    <div class="form-sections">
                        <!-- Información de cuenta -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user-circle"></i>
                                Información de cuenta
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Correo Electrónico *</label>
                                    <div class="input-container">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input 
                                            type="email" 
                                            id="email" 
                                            name="email" 
                                            value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                            placeholder="jefe@ita.mx"
                                            required
                                            autocomplete="email"
                                        >
                                    </div>
                                    <?php if (isset($errors['email'])): ?>
                                        <span class="error-message"><?= $errors['email'] ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nombre">Nombre Completo *</label>
                                    <div class="input-container">
                                        <i class="fas fa-user input-icon"></i>
                                        <input 
                                            type="text" 
                                            id="nombre" 
                                            name="nombre" 
                                            value="<?= htmlspecialchars($formData['nombre'] ?? '') ?>"
                                            placeholder="Nombre completo"
                                            required
                                            autocomplete="name"
                                        >
                                    </div>
                                    <?php if (isset($errors['nombre'])): ?>
                                        <span class="error-message"><?= $errors['nombre'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">Contraseña *</label>
                                    <div class="input-container">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input 
                                            type="password" 
                                            id="password" 
                                            name="password" 
                                            placeholder="••••••••"
                                            required
                                            autocomplete="new-password"
                                        >
                                        <button type="button" class="toggle-password" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['password'])): ?>
                                        <span class="error-message"><?= $errors['password'] ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirmar Contraseña *</label>
                                    <div class="input-container">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input 
                                            type="password" 
                                            id="confirm_password" 
                                            name="confirm_password" 
                                            placeholder="••••••••"
                                            required
                                            autocomplete="new-password"
                                        >
                                        <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <span class="error-message"><?= $errors['confirm_password'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información profesional -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-briefcase"></i>
                                Información profesional
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="jefe_departamento_id">Jefe de Departamento *</label>
                                    <div class="input-container">
                                        <i class="fas fa-user-tie input-icon"></i>
                                        <select id="jefe_departamento_id" name="jefe_departamento_id" required>
                                            <option value="">Seleccione un jefe de departamento</option>
                                            <?php foreach ($jefesDepartamento as $jefe): ?>
                                                <option value="<?= $jefe['id'] ?>" <?= isset($formData['jefe_departamento_id']) && $formData['jefe_departamento_id'] == $jefe['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($jefe['nombre'] . ' - ' . $jefe['departamento']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if (isset($errors['jefe_departamento_id'])): ?>
                                        <span class="error-message"><?= $errors['jefe_departamento_id'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="laboratorio">Laboratorio *</label>
                                    <div class="input-container">
                                        <i class="fas fa-flask input-icon"></i>
                                        <input 
                                            type="text" 
                                            id="laboratorio" 
                                            name="laboratorio" 
                                            value="<?= htmlspecialchars($formData['laboratorio'] ?? '') ?>"
                                            placeholder="Nombre del laboratorio"
                                            required
                                        >
                                    </div>
                                    <?php if (isset($errors['laboratorio'])): ?>
                                        <span class="error-message"><?= $errors['laboratorio'] ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="especialidad">Especialidad</label>
                                    <div class="input-container">
                                        <i class="fas fa-microscope input-icon"></i>
                                        <input 
                                            type="text" 
                                            id="especialidad" 
                                            name="especialidad" 
                                            value="<?= htmlspecialchars($formData['especialidad'] ?? '') ?>"
                                            placeholder="Área de especialidad"
                                        >
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <div class="input-container">
                                        <i class="fas fa-phone input-icon"></i>
                                        <input 
                                            type="tel" 
                                            id="telefono" 
                                            name="telefono" 
                                            value="<?= htmlspecialchars($formData['telefono'] ?? '') ?>"
                                            placeholder="(123) 456-7890"
                                        >
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="extension">Extensión</label>
                                    <div class="input-container">
                                        <i class="fas fa-hashtag input-icon"></i>
                                        <input 
                                            type="text" 
                                            id="extension" 
                                            name="extension" 
                                            value="<?= htmlspecialchars($formData['extension'] ?? '') ?>"
                                            placeholder="1234"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <div class="terms-info">
                            <p class="terms-text">
                                <i class="fas fa-info-circle"></i>
                                Su solicitud será revisada y aprobada por el jefe de departamento correspondiente.
                            </p>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="registerBtn">
                            <span class="btn-text">Enviar Solicitud</span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="login-prompt">
                    <p>¿Ya tienes cuenta?</p>
                    <div class="login-options">
                        <a href="login.php" class="login-link primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Iniciar sesión
                        </a>
                        <a href="register.php" class="login-link secondary">
                            <i class="fas fa-user-graduate"></i>
                            Registro de estudiante
                        </a>
                    </div>
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
}

/* Background Pattern */
.background-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(139, 140, 247, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(79, 70, 229, 0.1) 0%, transparent 50%);
    pointer-events: none;
}

/* Navigation */
.nav-back {
    position: absolute;
    top: 2rem;
    left: 2rem;
    z-index: 10;
}

.back-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    text-decoration: none;
    font-weight: 500;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: var(--radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.2s ease;
}

.back-link:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(-2px);
}

/* Main Container */
.register-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.register-wrapper {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
    max-width: 1200px;
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    min-height: fit-content;
}

/* Left Side - Branding */
.register-branding {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    padding: 3rem;
    display: flex;
    align-items: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.register-branding::before {
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

.branding-content > p {
    font-size: 1.125rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.features-preview {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
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

.process-info {
    margin-top: 2rem;
}

.process-info h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.process-step {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
    opacity: 0.9;
}

.step-number {
    width: 28px;
    height: 28px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Right Side - Form */
.register-form-container {
    padding: 3rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.register-card {
    max-width: 600px;
    margin: 0 auto;
    width: 100%;
    flex-shrink: 0;
}

.form-header {
    text-align: center;
    margin-bottom: 2rem;
}

.register-logo {
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
    align-items: flex-start;
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

.alert-success div {
    flex: 1;
}

.alert-success strong {
    display: block;
    margin-bottom: 0.25rem;
}

.alert-success p {
    margin: 0;
    font-weight: normal;
    opacity: 0.9;
}

/* Form Sections */
.form-sections {
    margin-bottom: 1.5rem;
}

.form-section {
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border: 1px solid var(--border-color);
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.section-title i {
    color: var(--primary-color);
}

/* Form Elements */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-row:last-child {
    margin-bottom: 0;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
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
    z-index: 2;
}

.input-container input,
.input-container select {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.2s ease;
    background: white;
}

.input-container input:focus,
.input-container select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-container select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
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
    transition: color 0.2s ease;
    z-index: 2;
}

.toggle-password:hover {
    color: var(--primary-color);
}

.error-message {
    color: var(--error-color);
    font-size: 0.8rem;
    margin-top: 0.25rem;
    font-weight: 500;
}

/* Form Footer */
.form-footer {
    margin-top: 1.5rem;
}

.terms-info {
    background: #f0f9ff;
    border: 1px solid #e0f2fe;
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.terms-text {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    color: #0369a1;
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
}

.terms-text i {
    margin-top: 0.1rem;
    flex-shrink: 0;
}

/* Primary Button */
.btn-primary {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Login Prompt */
.login-prompt {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.login-prompt p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.login-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.login-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.login-link.primary {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.login-link.secondary {
    background: rgba(107, 114, 128, 0.1);
    color: var(--text-secondary);
    border: 1px solid rgba(107, 114, 128, 0.3);
}

.login-link:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .register-wrapper {
        grid-template-columns: 1fr;
        max-width: 700px;
        min-height: auto;
    }

    .register-branding {
        display: none;
    }

    .register-form-container {
        padding: 2rem;
    }
}

@media (max-width: 768px) {
    .nav-back {
        top: 1rem;
        left: 1rem;
    }

    .register-container {
        padding: 1rem;
        align-items: flex-start;
        padding-top: 2rem;
    }

    .register-form-container {
        padding: 1.5rem;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .form-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .branding-content h1 {
        font-size: 2rem;
    }

    .login-options {
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .form-header h2 {
        font-size: 1.5rem;
    }

    .section-title {
        font-size: 1rem;
    }

    .input-container input,
    .input-container select {
        padding: 0.875rem 0.875rem 0.875rem 2.5rem;
        font-size: 0.9rem;
    }

    .input-icon {
        left: 0.875rem;
        font-size: 0.8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Password Visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Form Submission Loading State
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    
    if (registerForm && registerBtn) {
        const btnText = registerBtn.querySelector('.btn-text');
        const btnLoader = registerBtn.querySelector('.btn-loader');

        registerForm.addEventListener('submit', function(e) {
            // Show loading state
            registerBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
        });
    }

    // Input Focus Effects
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // Password Strength Indicator (Optional)
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    if (passwordInput && confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && passwordInput.value) {
                if (this.value === passwordInput.value) {
                    this.style.borderColor = 'var(--success-color)';
                } else {
                    this.style.borderColor = 'var(--error-color)';
                }
            } else {
                this.style.borderColor = 'var(--border-color)';
            }
        });
    }

    // Real-time form validation
    const form = document.getElementById('registerForm');
    if (form) {
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.addEventListener('blur', function() {
                validateField(this);
            });
        });
    }
});

function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    
    // Remove existing error styling
    field.style.borderColor = '';
    
    // Basic validation
    if (!value && field.hasAttribute('required')) {
        field.style.borderColor = 'var(--error-color)';
        return false;
    }
    
    // Email validation
    if (fieldName === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            field.style.borderColor = 'var(--error-color)';
            return false;
        }
    }
    
    // Success styling
    if (value) {
        field.style.borderColor = 'var(--success-color)';
    }
    
    return true;
}
</script>

<?php include '../includes/footer.php'; ?>