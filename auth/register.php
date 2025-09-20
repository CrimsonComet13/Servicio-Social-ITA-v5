<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if ($session->isLoggedIn()) {
    $userRole = $session->getUserRole();
    header("Location: ../dashboard/$userRole.php");
    exit();
}

$errors = [];
$success = '';
$formData = [
    'email' => '',
    'numero_control' => '',
    'nombre' => '',
    'apellido_paterno' => '',
    'apellido_materno' => '',
    'carrera' => '',
    'telefono' => '',
    'semestre' => '',
    'creditos_cursados' => ''
];

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
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    if (empty($formData['numero_control'])) {
        $errors['numero_control'] = 'El número de control es obligatorio';
    } elseif (!preg_match('/^\d{8}$/', $formData['numero_control'])) {
        $errors['numero_control'] = 'El número de control debe tener 8 dígitos';
    }
    
    if (empty($formData['nombre'])) {
        $errors['nombre'] = 'El nombre es obligatorio';
    }
    
    if (empty($formData['apellido_paterno'])) {
        $errors['apellido_paterno'] = 'El apellido paterno es obligatorio';
    }
    
    if (empty($formData['carrera'])) {
        $errors['carrera'] = 'La carrera es obligatoria';
    }
    
    if (empty($formData['semestre'])) {
        $errors['semestre'] = 'El semestre es obligatorio';
    } elseif ($formData['semestre'] < 1 || $formData['semestre'] > 12) {
        $errors['semestre'] = 'El semestre debe estar entre 1 y 12';
    }
    
    if (empty($formData['creditos_cursados'])) {
        $errors['creditos_cursados'] = 'Los créditos cursados son obligatorios';
    } elseif ($formData['creditos_cursados'] < 0) {
        $errors['creditos_cursados'] = 'Los créditos cursados no pueden ser negativos';
    }
    
    // Verificar si el email ya existe
    if (empty($errors['email'])) {
        $db = Database::getInstance();
        $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$formData['email']]);
        if ($existingUser) {
            $errors['email'] = 'Ya existe un usuario con este email';
        }
    }
    
    // Verificar si el número de control ya existe
    if (empty($errors['numero_control'])) {
        $db = Database::getInstance();
        $existingStudent = $db->fetch("SELECT id FROM estudiantes WHERE numero_control = ?", [$formData['numero_control']]);
        if ($existingStudent) {
            $errors['numero_control'] = 'Ya existe un estudiante con este número de control';
        }
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();
            
            // Crear usuario
            $userId = $db->insert('usuarios', [
                'email' => $formData['email'],
                'password' => password_hash($formData['password'], PASSWORD_DEFAULT),
                'tipo_usuario' => 'estudiante',
                'activo' => true,
                'email_verificado' => false,
                'token_verificacion' => bin2hex(random_bytes(32))
            ]);
            
            // Crear estudiante
            $estudianteId = $db->insert('estudiantes', [
                'usuario_id' => $userId,
                'numero_control' => $formData['numero_control'],
                'nombre' => $formData['nombre'],
                'apellido_paterno' => $formData['apellido_paterno'],
                'apellido_materno' => $formData['apellido_materno'] ?? null,
                'carrera' => $formData['carrera'],
                'semestre' => $formData['semestre'],
                'creditos_cursados' => $formData['creditos_cursados'],
                'telefono' => $formData['telefono'] ?? null,
                'estado_servicio' => 'sin_solicitud'
            ]);
            
            $db->commit();
            
            // Iniciar sesión automáticamente
            $userData = $db->fetch("
                SELECT e.*, u.email 
                FROM estudiantes e 
                JOIN usuarios u ON e.usuario_id = u.id 
                WHERE e.usuario_id = ?
            ", [$userId]);
            
            $session->set('usuario', array_merge([
                'id' => $userId,
                'email' => $formData['email'],
                'tipo_usuario' => 'estudiante',
                'activo' => true,
                'email_verificado' => false
            ], $userData));
            
            // Registrar actividad si la función existe
            if (function_exists('logActivity')) {
                logActivity($userId, 'register', 'auth');
            }
            
            $success = 'Registro exitoso. Bienvenido al sistema.';
            
            // Redirigir al dashboard de estudiante
            header("Location: ../dashboard/estudiante.php");
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            $errors['general'] = 'Error en el registro: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Registro de Estudiante - " . APP_NAME;
include '../includes/header.php';
?>

<!-- Navigation Bar para usuarios no autenticados -->
<nav class="register-nav">
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
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </a>
        </div>
    </div>
</nav>

<!-- Background Pattern -->
<div class="background-pattern"></div>

<div class="register-container">
    <div class="register-wrapper">
        <div class="register-card">
            <div class="form-header">
                <div class="header-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Registro de Estudiante</h1>
                <p>Instituto Tecnológico de Aguascalientes</p>
                <div class="progress-indicator">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-label">Información Personal</span>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="register-form" id="registerForm">
                <!-- Información Personal -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Información Personal
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Correo Electrónico <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" id="email" name="email" 
                                       value="<?= htmlspecialchars($formData['email']) ?>" 
                                       placeholder="juan.perez@ita.mx" required>
                            </div>
                            <?php if (isset($errors['email'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['email']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="numero_control">Número de Control <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" id="numero_control" name="numero_control" 
                                       value="<?= htmlspecialchars($formData['numero_control']) ?>" 
                                       pattern="\d{8}" title="8 dígitos" placeholder="20180001" 
                                       maxlength="8" required>
                            </div>
                            <?php if (isset($errors['numero_control'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['numero_control']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="nombre">Nombre(s) <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($formData['nombre']) ?>" 
                                       placeholder="Juan Carlos" required>
                            </div>
                            <?php if (isset($errors['nombre'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['nombre']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="apellido_paterno">Apellido Paterno <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="apellido_paterno" name="apellido_paterno" 
                                       value="<?= htmlspecialchars($formData['apellido_paterno']) ?>" 
                                       placeholder="Pérez" required>
                            </div>
                            <?php if (isset($errors['apellido_paterno'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['apellido_paterno']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="apellido_materno">Apellido Materno</label>
                            <div class="input-container">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="apellido_materno" name="apellido_materno" 
                                       value="<?= htmlspecialchars($formData['apellido_materno']) ?>" 
                                       placeholder="García">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <div class="input-container">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="tel" id="telefono" name="telefono" 
                                       value="<?= htmlspecialchars($formData['telefono']) ?>" 
                                       placeholder="449 123 4567">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Académica -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Información Académica
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="carrera">Carrera <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-book input-icon"></i>
                                <select id="carrera" name="carrera" required>
                                    <option value="">Seleccionar carrera</option>
                                    <option value="Ingeniería en Sistemas Computacionales" <?= ($formData['carrera'] ?? '') === 'Ingeniería en Sistemas Computacionales' ? 'selected' : '' ?>>Ingeniería en Sistemas Computacionales</option>
                                    <option value="Ingeniería Industrial" <?= ($formData['carrera'] ?? '') === 'Ingeniería Industrial' ? 'selected' : '' ?>>Ingeniería Industrial</option>
                                    <option value="Ingeniería Mecánica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Mecánica' ? 'selected' : '' ?>>Ingeniería Mecánica</option>
                                    <option value="Ingeniería Electrónica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Electrónica' ? 'selected' : '' ?>>Ingeniería Electrónica</option>
                                    <option value="Ingeniería Química" <?= ($formData['carrera'] ?? '') === 'Ingeniería Química' ? 'selected' : '' ?>>Ingeniería Química</option>
                                    <option value="Ingeniería Mecatrónica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Mecatrónica' ? 'selected' : '' ?>>Ingeniería Mecatrónica</option>
                                    <option value="Ingeniería en Gestión Empresarial" <?= ($formData['carrera'] ?? '') === 'Ingeniería en Gestión Empresarial' ? 'selected' : '' ?>>Ingeniería en Gestión Empresarial</option>
                                    <option value="Licenciatura en Administración" <?= ($formData['carrera'] ?? '') === 'Licenciatura en Administración' ? 'selected' : '' ?>>Licenciatura en Administración</option>
                                </select>
                            </div>
                            <?php if (isset($errors['carrera'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['carrera']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="semestre">Semestre Actual <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-calendar input-icon"></i>
                                <input type="number" id="semestre" name="semestre" 
                                       value="<?= htmlspecialchars($formData['semestre']) ?>" 
                                       min="1" max="12" placeholder="6" required>
                            </div>
                            <?php if (isset($errors['semestre'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['semestre']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="creditos_cursados">Créditos Cursados <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-chart-bar input-icon"></i>
                                <input type="number" id="creditos_cursados" name="creditos_cursados" 
                                       value="<?= htmlspecialchars($formData['creditos_cursados']) ?>" 
                                       min="0" placeholder="180" required>
                            </div>
                            <?php if (isset($errors['creditos_cursados'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['creditos_cursados']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Información de Seguridad -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-lock"></i>
                        Configuración de Seguridad
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">Contraseña <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="password" name="password" 
                                       placeholder="Mínimo 8 caracteres" required>
                                <button type="button" class="toggle-password" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <?php if (isset($errors['password'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['password']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmar Contraseña <span class="required">*</span></label>
                            <div class="input-container">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Repetir contraseña" required>
                                <button type="button" class="toggle-password" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-match" id="passwordMatch"></div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <span class="btn-text">
                        <i class="fas fa-user-plus"></i>
                        Crear Cuenta
                    </span>
                    <span class="btn-loader" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Creando cuenta...
                    </span>
                </button>
            </form>

            <div class="form-footer">
                <div class="login-prompt">
                    <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                </div>
                
                <div class="other-options">
                    <p>¿Eres jefe de laboratorio? <a href="register-jefe.php">Regístrate aquí</a></p>
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
}

/* Navigation Bar */
.register-nav {
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
.register-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6rem 2rem 2rem;
    position: relative;
    z-index: 2;
}

.register-wrapper {
    width: 100%;
    max-width: 900px;
}

.register-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 3rem;
    position: relative;
    overflow: hidden;
}

.register-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
}

/* Form Header */
.form-header {
    text-align: center;
    margin-bottom: 3rem;
}

.header-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 1.5rem;
    box-shadow: var(--shadow-lg);
}

.form-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.progress-indicator {
    display: flex;
    justify-content: center;
}

.step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 2rem;
    color: var(--primary-color);
    font-weight: 500;
}

.step-number {
    width: 24px;
    height: 24px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
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

/* Form Sections */
.form-section {
    margin-bottom: 3rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border-color);
}

.section-title i {
    color: var(--primary-color);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.required {
    color: var(--error-color);
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

.form-group input,
.form-group select {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 1rem;
    font-family: inherit;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus {
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

.error {
    color: var(--error-color);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    font-weight: 500;
}

/* Password Strength Indicator */
.password-strength {
    margin-top: 0.5rem;
    font-size: 0.85rem;
}

.password-strength.weak {
    color: var(--error-color);
}

.password-strength.medium {
    color: var(--warning-color);
}

.password-strength.strong {
    color: var(--success-color);
}

.password-match {
    margin-top: 0.5rem;
    font-size: 0.85rem;
}

.password-match.match {
    color: var(--success-color);
}

.password-match.no-match {
    color: var(--error-color);
}

/* Submit Button */
.register-form .btn-primary {
    width: 100%;
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 2rem;
}

.register-form .btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.register-form .btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Form Footer */
.form-footer {
    text-align: center;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.login-prompt {
    margin-bottom: 1rem;
}

.login-prompt p,
.other-options p {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.login-prompt a,
.other-options a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.login-prompt a:hover,
.other-options a:hover {
    text-decoration: underline;
}

/* Footer */
.register-footer {
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

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 2rem;
    text-align: center;
}

.footer-bottom p {
    font-size: 0.875rem;
    opacity: 0.8;
    margin: 0;
}

/* Loading State */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    z-index: 1;
}

@keyframes spin {
    from { transform: translate(-50%, -50%) rotate(0deg); }
    to { transform: translate(-50%, -50%) rotate(360deg); }
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
    
    .nav-actions .btn span {
        display: none;
    }

    .register-container {
        padding: 1rem;
        padding-top: 5rem;
    }

    .register-card {
        padding: 2rem;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-header h1 {
        font-size: 2rem;
    }
    
    .footer-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}

@media (max-width: 480px) {
    .register-container {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }
    
    .register-card {
        padding: 1.5rem;
    }
    
    .form-header h1 {
        font-size: 1.75rem;
    }
    
    .header-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del número de control
    const numeroControl = document.getElementById('numero_control');
    if (numeroControl) {
        numeroControl.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 8);
        });
    }

    // Toggle Password Visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });

    // Password Strength Indicator
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('passwordStrength');
    
    if (passwordInput && passwordStrength) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength < 3) {
                passwordStrength.textContent = 'Contraseña débil';
                passwordStrength.className = 'password-strength weak';
            } else if (strength < 4) {
                passwordStrength.textContent = 'Contraseña media';
                passwordStrength.className = 'password-strength medium';
            } else {
                passwordStrength.textContent = 'Contraseña fuerte';
                passwordStrength.className = 'password-strength strong';
            }
        });
    }

    // Password Match Validation
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    if (confirmPassword && passwordMatch) {
        confirmPassword.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPass = this.value;
            
            if (confirmPass.length > 0) {
                if (password === confirmPass) {
                    passwordMatch.textContent = 'Las contraseñas coinciden';
                    passwordMatch.className = 'password-match match';
                    this.style.borderColor = 'var(--success-color)';
                } else {
                    passwordMatch.textContent = 'Las contraseñas no coinciden';
                    passwordMatch.className = 'password-match no-match';
                    this.style.borderColor = 'var(--error-color)';
                }
            } else {
                passwordMatch.textContent = '';
                passwordMatch.className = 'password-match';
                this.style.borderColor = 'var(--border-color)';
            }
        });
    }

    // Form Submission Loading State
    const registerForm = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (registerForm && submitBtn) {
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');

        registerForm.addEventListener('submit', function(e) {
            // Show loading state
            submitBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
            
            // Re-enable after 15 seconds in case of error
            setTimeout(() => {
                submitBtn.disabled = false;
                if (btnText) btnText.style.display = 'inline-flex';
                if (btnLoader) btnLoader.style.display = 'none';
            }, 15000);
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
    
    // Header scroll effect
    let lastScrollY = window.scrollY;
    const nav = document.querySelector('.register-nav');
    
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