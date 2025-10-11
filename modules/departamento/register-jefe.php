<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Obtener el perfil del jefe de departamento
$jefeDepto = $db->fetch("SELECT * FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeDeptoId = $jefeDepto['id'];

$errors = [];
$success = false;

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos requeridos
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $laboratorio = trim($_POST['laboratorio'] ?? '');
    $especialidad = trim($_POST['especialidad'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $extension = trim($_POST['extension'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $auto_password = isset($_POST['auto_password']);
    
    // Validaciones
    if (empty($nombre)) {
        $errors[] = 'El nombre completo es requerido';
    } elseif (strlen($nombre) < 3) {
        $errors[] = 'El nombre debe tener al menos 3 caracteres';
    }
    
    if (empty($email)) {
        $errors[] = 'El correo electrónico es requerido';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del correo electrónico no es válido';
    } else {
        // Verificar si el email ya existe
        $emailExists = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
        if ($emailExists) {
            $errors[] = 'El correo electrónico ya está registrado en el sistema';
        }
    }
    
    if (empty($laboratorio)) {
        $errors[] = 'El nombre del laboratorio es requerido';
    }
    
    // Validar teléfono si se proporciona
    if (!empty($telefono) && !preg_match('/^[0-9\s\-\+\(\)]+$/', $telefono)) {
        $errors[] = 'El formato del teléfono no es válido (solo números, espacios y guiones)';
    }
    
    // Validar extensión si se proporciona
    if (!empty($extension) && !preg_match('/^[0-9]+$/', $extension)) {
        $errors[] = 'La extensión debe contener solo números';
    }
    
    // Validar contraseña
    if (!$auto_password) {
        if (empty($password)) {
            $errors[] = 'La contraseña es requerida';
        } elseif (strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Las contraseñas no coinciden';
        }
    } else {
        // Generar contraseña automática
        $password = bin2hex(random_bytes(4)); // Genera una contraseña de 8 caracteres
    }
    
    // Si no hay errores, proceder con el registro
    if (empty($errors)) {
        try {
            // Iniciar transacción
            $db->beginTransaction();
            
            // Crear usuario
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $usuarioId = $db->insert('usuarios', [
                'email' => $email,
                'password' => $passwordHash,
                'rol' => 'jefe_laboratorio',
                'activo' => false, // Inactivo hasta que sea aprobado
                'email_verificado' => false,
                'fecha_registro' => date('Y-m-d H:i:s')
            ]);
            
            if (!$usuarioId) {
                throw new Exception('Error al crear el usuario');
            }
            
            // Crear perfil de jefe de laboratorio
            $jefeLabId = $db->insert('jefes_laboratorio', [
                'usuario_id' => $usuarioId,
                'jefe_departamento_id' => $jefeDeptoId,
                'nombre' => $nombre,
                'laboratorio' => $laboratorio,
                'especialidad' => $especialidad,
                'telefono' => $telefono,
                'extension' => $extension,
                'activo' => false // Inactivo hasta que sea aprobado
            ]);
            
            if (!$jefeLabId) {
                throw new Exception('Error al crear el perfil de jefe de laboratorio');
            }
            
            // Confirmar transacción
            $db->commit();
            
            // Aquí podrías enviar un email de notificación
            // sendInvitationEmail($email, $password, $nombre);
            
            $success = true;
            $successMessage = "Jefe de laboratorio invitado exitosamente. ";
            if ($auto_password) {
                $successMessage .= "Contraseña generada: <strong>" . htmlspecialchars($password) . "</strong><br>";
                $successMessage .= "<small>Por favor, comparta esta información de forma segura.</small>";
            }
            
            flashMessage($successMessage, 'success');
            
            // Limpiar formulario después del éxito
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error al registrar: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Invitar Jefe de Laboratorio - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-user-plus"></i>
                        Invitar Jefe de Laboratorio
                    </h1>
                    <p class="page-subtitle">Registrar un nuevo jefe de laboratorio en el departamento</p>
                </div>
                <div class="header-actions">
                    <a href="laboratorios.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="alert-content">
                <h3 class="alert-title">Se encontraron errores</h3>
                <ul class="alert-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <div class="alert-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="alert-content">
                <h3 class="alert-title">¡Registro exitoso!</h3>
                <p><?= $successMessage ?></p>
                <div class="alert-actions">
                    <a href="laboratorios.php" class="btn btn-success btn-sm">
                        <i class="fas fa-list"></i>
                        Ver Jefes de Laboratorio
                    </a>
                    <button onclick="location.reload()" class="btn btn-secondary btn-sm">
                        <i class="fas fa-plus"></i>
                        Invitar Otro
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <div class="header-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="header-info">
                        <h2>Formulario de Registro</h2>
                        <p>Complete la información del nuevo jefe de laboratorio</p>
                    </div>
                </div>

                <form method="POST" id="registerForm" class="register-form">
                    <!-- Información Personal -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Información Personal
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="nombre" class="form-label required">
                                    <i class="fas fa-id-card"></i>
                                    Nombre Completo
                                </label>
                                <input 
                                    type="text" 
                                    id="nombre" 
                                    name="nombre" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                    required
                                    minlength="3"
                                    maxlength="100"
                                    placeholder="Ej: Dr. Juan Pérez García"
                                >
                                <small class="form-help">Nombre completo incluyendo títulos académicos</small>
                            </div>

                            <div class="form-group full-width">
                                <label for="email" class="form-label required">
                                    <i class="fas fa-envelope"></i>
                                    Correo Electrónico Institucional
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    required
                                    maxlength="100"
                                    placeholder="correo@institucion.edu.mx"
                                >
                                <small class="form-help">Se enviará una invitación a este correo</small>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Laboratorio -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-flask"></i>
                            Información del Laboratorio
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="laboratorio" class="form-label required">
                                    <i class="fas fa-vials"></i>
                                    Nombre del Laboratorio
                                </label>
                                <input 
                                    type="text" 
                                    id="laboratorio" 
                                    name="laboratorio" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['laboratorio'] ?? '') ?>"
                                    required
                                    maxlength="100"
                                    placeholder="Ej: Laboratorio de Física"
                                    list="laboratorios-list"
                                >
                                <datalist id="laboratorios-list">
                                    <option value="Laboratorio de Física">
                                    <option value="Laboratorio de Química">
                                    <option value="Laboratorio de Biología">
                                    <option value="Laboratorio de Computación">
                                    <option value="Laboratorio de Electrónica">
                                </datalist>
                                <small class="form-help">Laboratorio que estará a cargo</small>
                            </div>

                            <div class="form-group">
                                <label for="especialidad" class="form-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Especialidad
                                </label>
                                <input 
                                    type="text" 
                                    id="especialidad" 
                                    name="especialidad" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['especialidad'] ?? '') ?>"
                                    maxlength="100"
                                    placeholder="Ej: Física Aplicada, Química Orgánica"
                                >
                                <small class="form-help">Área de especialización (opcional)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-phone"></i>
                            Información de Contacto
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="telefono" class="form-label">
                                    <i class="fas fa-mobile-alt"></i>
                                    Teléfono
                                </label>
                                <input 
                                    type="tel" 
                                    id="telefono" 
                                    name="telefono" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                    maxlength="20"
                                    placeholder="(449) 123-4567"
                                    pattern="[0-9\s\-\+\(\)]+"
                                >
                                <small class="form-help">Teléfono de contacto (opcional)</small>
                            </div>

                            <div class="form-group">
                                <label for="extension" class="form-label">
                                    <i class="fas fa-phone-alt"></i>
                                    Extensión
                                </label>
                                <input 
                                    type="text" 
                                    id="extension" 
                                    name="extension" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($_POST['extension'] ?? '') ?>"
                                    maxlength="10"
                                    placeholder="1234"
                                    pattern="[0-9]+"
                                >
                                <small class="form-help">Extensión telefónica (opcional)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Acceso -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-key"></i>
                            Configuración de Acceso
                        </h3>
                        
                        <div class="password-option">
                            <label class="checkbox-container">
                                <input 
                                    type="checkbox" 
                                    id="auto_password" 
                                    name="auto_password"
                                    <?= isset($_POST['auto_password']) ? 'checked' : '' ?>
                                >
                                <span class="checkmark"></span>
                                <span class="checkbox-label">
                                    <strong>Generar contraseña automáticamente</strong>
                                    <small>El sistema generará una contraseña segura que podrá compartir con el jefe de laboratorio</small>
                                </span>
                            </label>
                        </div>

                        <div id="password-fields" class="form-grid" style="<?= isset($_POST['auto_password']) ? 'display: none;' : '' ?>">
                            <div class="form-group">
                                <label for="password" class="form-label required">
                                    <i class="fas fa-lock"></i>
                                    Contraseña
                                </label>
                                <div class="password-input-wrapper">
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        class="form-input"
                                        minlength="6"
                                        placeholder="Mínimo 6 caracteres"
                                    >
                                    <button type="button" class="toggle-password" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="password-strength"></div>
                                <small class="form-help">La contraseña debe tener al menos 6 caracteres</small>
                            </div>

                            <div class="form-group">
                                <label for="password_confirm" class="form-label required">
                                    <i class="fas fa-lock"></i>
                                    Confirmar Contraseña
                                </label>
                                <div class="password-input-wrapper">
                                    <input 
                                        type="password" 
                                        id="password_confirm" 
                                        name="password_confirm" 
                                        class="form-input"
                                        minlength="6"
                                        placeholder="Repita la contraseña"
                                    >
                                    <button type="button" class="toggle-password" data-target="password_confirm">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="form-help">Debe coincidir con la contraseña anterior</small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" onclick="window.location.href='laboratorios.php'" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-user-plus"></i>
                            Invitar Jefe de Laboratorio
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Card -->
            <div class="info-card">
                <div class="info-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Información Importante</h3>
                </div>
                <div class="info-content">
                    <div class="info-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Aprobación Requerida</strong>
                            <p>El jefe de laboratorio deberá ser aprobado antes de poder acceder al sistema.</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>Notificación</strong>
                            <p>Se recomienda enviar las credenciales de acceso por correo electrónico de forma segura.</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-key"></i>
                        <div>
                            <strong>Contraseña Segura</strong>
                            <p>Puede generar una contraseña automática o definir una personalizada con mínimo 6 caracteres.</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-cog"></i>
                        <div>
                            <strong>Gestión</strong>
                            <p>Podrá editar o eliminar este perfil desde la lista de jefes de laboratorio.</p>
                        </div>
                    </div>
                </div>

                <div class="info-footer">
                    <a href="laboratorios.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-list"></i>
                        Ver Jefes Registrados
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --primary-dark: #4f46e5;
    --secondary: #8b5cf6;
    --success: #10b981;
    --success-light: #34d399;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    --border: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
    --sidebar-width: 280px;
}

/* Main Wrapper */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    transition: margin-left 0.3s ease;
}

/* Dashboard Container */
.dashboard-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-light);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
}

.header-text {
    flex: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.page-title i {
    color: var(--primary);
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Alert Messages */
.alert {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    animation: slideIn 0.4s ease-out;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--error);
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: var(--success);
}

.alert-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.alert-list {
    margin: 0;
    padding-left: 1.25rem;
}

.alert-list li {
    margin-bottom: 0.25rem;
}

.alert-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

/* Form Container */
.form-container {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    animation: fadeIn 0.5s ease-out;
}

/* Form Card */
.form-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.form-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 3px solid rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}

.header-info h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
}

.header-info p {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}

/* Register Form */
.register-form {
    padding: 2rem;
}

.form-section {
    margin-bottom: 2.5rem;
}

.form-section:last-of-type {
    margin-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border-light);
}

.section-title i {
    color: var(--primary);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.form-label i {
    color: var(--primary);
    font-size: 0.9rem;
}

.form-label.required::after {
    content: '*';
    color: var(--error);
    margin-left: 0.25rem;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.95rem;
    color: var(--text-primary);
    transition: var(--transition);
    background: var(--bg-white);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input:hover:not(:focus) {
    border-color: var(--primary-light);
}

.form-help {
    font-size: 0.85rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Password Input */
.password-input-wrapper {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.5rem;
    transition: var(--transition);
}

.toggle-password:hover {
    color: var(--primary);
}

.toggle-password i {
    font-size: 1rem;
}

/* Password Strength */
.password-strength {
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
    position: relative;
}

.password-strength::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    transition: var(--transition);
    border-radius: 2px;
}

.password-strength.weak::before {
    width: 33.33%;
    background: var(--error);
}

.password-strength.medium::before {
    width: 66.66%;
    background: var(--warning);
}

.password-strength.strong::before {
    width: 100%;
    background: var(--success);
}

/* Checkbox Container */
.password-option {
    margin-bottom: 1.5rem;
}

.checkbox-container {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.checkbox-container:hover {
    background: var(--bg-gray);
}

.checkbox-container input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}

.checkmark {
    width: 24px;
    height: 24px;
    border: 2px solid var(--border);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    flex-shrink: 0;
    background: var(--bg-white);
}

.checkbox-container input[type="checkbox"]:checked ~ .checkmark {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-color: var(--primary);
}

.checkbox-container input[type="checkbox"]:checked ~ .checkmark::after {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    color: white;
    font-size: 0.75rem;
}

.checkbox-label {
    flex: 1;
}

.checkbox-label strong {
    display: block;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.checkbox-label small {
    display: block;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-light);
    margin-top: 2rem;
}

/* Info Card */
.info-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    height: fit-content;
    position: sticky;
    top: 1.5rem;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--success), var(--success-light));
    color: white;
}

.info-header i {
    font-size: 1.5rem;
}

.info-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.info-content {
    padding: 1.5rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.info-item:hover {
    background: var(--bg-gray);
    transform: translateX(5px);
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item i {
    color: var(--primary);
    font-size: 1.25rem;
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.info-item div {
    flex: 1;
}

.info-item strong {
    display: block;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.info-item p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.6;
    font-size: 0.9rem;
}

.info-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-light);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-sm {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--bg-light);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), var(--success-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.btn.loading {
    pointer-events: none;
    position: relative;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

/* Validation States */
.form-input:invalid:not(:focus):not(:placeholder-shown) {
    border-color: var(--error);
}

.form-input:valid:not(:focus):not(:placeholder-shown) {
    border-color: var(--success);
}

.form-group.has-error .form-input {
    border-color: var(--error);
}

.form-group.has-error .form-help {
    color: var(--error);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .form-container {
        grid-template-columns: 1fr;
    }
    
    .info-card {
        position: static;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .register-form {
        padding: 1.5rem;
    }
    
    .form-header {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .header-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column-reverse;
    }
    
    .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .register-form {
        padding: 1rem;
    }
    
    .section-title {
        font-size: 1.1rem;
    }
    
    .form-input {
        padding: 0.75rem;
        font-size: 0.9rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const autoPasswordCheckbox = document.getElementById('auto_password');
    const passwordFields = document.getElementById('password-fields');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const passwordStrength = document.getElementById('password-strength');
    
    // Toggle password fields visibility
    autoPasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
            passwordFields.style.display = 'none';
            passwordInput.removeAttribute('required');
            passwordConfirmInput.removeAttribute('required');
            passwordInput.value = '';
            passwordConfirmInput.value = '';
        } else {
            passwordFields.style.display = 'grid';
            passwordInput.setAttribute('required', 'required');
            passwordConfirmInput.setAttribute('required', 'required');
        }
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Password strength indicator
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = '';
        
        if (password.length === 0) {
            passwordStrength.className = 'password-strength';
        } else if (password.length < 6) {
            passwordStrength.className = 'password-strength weak';
        } else if (password.length < 10 && !/[A-Z]/.test(password)) {
            passwordStrength.className = 'password-strength medium';
        } else if (password.length >= 10 || (/[A-Z]/.test(password) && /[0-9]/.test(password))) {
            passwordStrength.className = 'password-strength strong';
        } else {
            passwordStrength.className = 'password-strength medium';
        }
    });
    
    // Real-time password confirmation validation
    passwordConfirmInput.addEventListener('input', function() {
        if (this.value && this.value !== passwordInput.value) {
            this.setCustomValidity('Las contraseñas no coinciden');
        } else {
            this.setCustomValidity('');
        }
    });
    
    passwordInput.addEventListener('input', function() {
        if (passwordConfirmInput.value) {
            if (passwordConfirmInput.value !== this.value) {
                passwordConfirmInput.setCustomValidity('Las contraseñas no coinciden');
            } else {
                passwordConfirmInput.setCustomValidity('');
            }
        }
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('telefono');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substr(0, 10);
            }
            
            if (value.length >= 6) {
                e.target.value = `(${value.substr(0, 3)}) ${value.substr(3, 3)}-${value.substr(6)}`;
            } else if (value.length >= 3) {
                e.target.value = `(${value.substr(0, 3)}) ${value.substr(3)}`;
            } else {
                e.target.value = value;
            }
        });
    }
    
    // Extension input - only numbers
    const extensionInput = document.getElementById('extension');
    if (extensionInput) {
        extensionInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
    
    // Form validation
    const inputs = form.querySelectorAll('.form-input');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
    
    function validateField(field) {
        const formGroup = field.closest('.form-group');
        
        if (field.hasAttribute('required') && !field.value.trim()) {
            formGroup.classList.add('has-error');
            field.classList.add('is-invalid');
            return false;
        }
        
        if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                formGroup.classList.add('has-error');
                field.classList.add('is-invalid');
                return false;
            }
        }
        
        if (field.minLength > 0 && field.value.length > 0 && field.value.length < field.minLength) {
            formGroup.classList.add('has-error');
            field.classList.add('is-invalid');
            return false;
        }
        
        formGroup.classList.remove('has-error');
        field.classList.remove('is-invalid');
        return true;
    }
    
    // Form submission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate all required fields
        inputs.forEach(input => {
            // Skip password fields if auto-password is checked
            if (autoPasswordCheckbox.checked && 
                (input.id === 'password' || input.id === 'password_confirm')) {
                return;
            }
            
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        // Check password match if not auto-generating
        if (!autoPasswordCheckbox.checked) {
            if (passwordInput.value !== passwordConfirmInput.value) {
                isValid = false;
                passwordConfirmInput.closest('.form-group').classList.add('has-error');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // Show error message
            const existingAlert = document.querySelector('.alert-error');
            if (!existingAlert) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="alert-content">
                        <h3 class="alert-title">Error en el formulario</h3>
                        <p>Por favor, corrija los errores antes de continuar.</p>
                    </div>
                `;
                
                const header = document.querySelector('.dashboard-header');
                header.parentNode.insertBefore(alertDiv, header.nextSibling);
                
                // Scroll to alert
                alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Remove after 5 seconds
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transform = 'translateY(-20px)';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 5000);
            }
            
            // Scroll to first error
            const firstError = document.querySelector('.has-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return false;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    });
    
    // Animate form sections on load
    const formSections = document.querySelectorAll('.form-section');
    formSections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        setTimeout(() => {
            section.style.transition = 'all 0.5s ease-out';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Auto-hide success/error alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (!alert.classList.contains('alert-error')) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            }
        });
    }, 8000);
    
    // Add focus animations
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.01)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = '';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>