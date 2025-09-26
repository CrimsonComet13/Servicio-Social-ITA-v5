<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener datos actuales del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_map('sanitizeInput', $_POST);
    
    // Validar campos
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
    
    if (empty($errors)) {
        try {
            // Actualizar datos del estudiante
            $db->update('estudiantes', [
                'nombre' => $formData['nombre'],
                'apellido_paterno' => $formData['apellido_paterno'],
                'apellido_materno' => $formData['apellido_materno'] ?? null,
                'carrera' => $formData['carrera'],
                'semestre' => $formData['semestre'],
                'creditos_cursados' => $formData['creditos_cursados'],
                'telefono' => $formData['telefono'] ?? null
            ], 'usuario_id = :usuario_id', ['usuario_id' => $estudianteId]);
            
            // Actualizar email si cambió
            if ($formData['email'] !== $estudiante['email']) {
                if (!validateEmail($formData['email'])) {
                    $errors['email'] = 'El formato del email no es válido';
                } else {
                    // Verificar si el nuevo email ya existe
                    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ? AND id != ?", 
                                              [$formData['email'], $estudianteId]);
                    if ($existingUser) {
                        $errors['email'] = 'Ya existe un usuario con este email';
                    } else {
                        $db->update('usuarios', [
                            'email' => $formData['email'],
                            'email_verificado' => false,
                            'token_verificacion' => generateToken()
                        ], 'id = :id', ['id' => $estudianteId]);
                        
                        // Enviar email de verificación (pendiente)
                        // sendVerificationEmail($formData['email'], $token);
                    }
                }
            }
            
            if (empty($errors)) {
                $success = 'Perfil actualizado correctamente';
                
                // Actualizar datos en sesión
                $estudianteActualizado = $db->fetch("
                    SELECT e.*, u.email 
                    FROM estudiantes e 
                    JOIN usuarios u ON e.usuario_id = u.id 
                    WHERE e.usuario_id = ?
                ", [$estudianteId]);
                
                $session->set('usuario', array_merge($session->getUser(), $estudianteActualizado));
                
                // Recargar la página para mostrar los cambios
                redirectTo('/modules/estudiantes/perfil.php');
            }
            
        } catch (Exception $e) {
            $errors['general'] = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Mi Perfil - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <!-- Header Section -->
    <div class="profile-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Mi Perfil</h1>
                <p class="header-subtitle">Actualiza tu información personal y académica</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Profile Form Card -->
    <div class="profile-card">
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>¡Éxito!</strong>
                <p><?= $success ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Error</strong>
                <p><?= $errors['general'] ?></p>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="profile-form">
            <!-- Personal Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i>
                        Información Personal
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input <?= isset($errors['email']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['email'] ?? '') ?>" 
                               required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?= $errors['email'] ?></span>
                        <?php endif; ?>
                        <?php if (!($estudiante['email_verificado'] ?? true)): ?>
                            <div class="input-help warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Email no verificado. <a href="../../auth/verify-email.php">Verificar ahora</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre" class="form-label required">
                            <i class="fas fa-user"></i>
                            Nombre
                        </label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               class="form-input <?= isset($errors['nombre']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['nombre'] ?? '') ?>" 
                               required>
                        <?php if (isset($errors['nombre'])): ?>
                            <span class="error-message"><?= $errors['nombre'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellido_paterno" class="form-label required">
                            <i class="fas fa-user"></i>
                            Apellido Paterno
                        </label>
                        <input type="text" 
                               id="apellido_paterno" 
                               name="apellido_paterno" 
                               class="form-input <?= isset($errors['apellido_paterno']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['apellido_paterno'] ?? '') ?>" 
                               required>
                        <?php if (isset($errors['apellido_paterno'])): ?>
                            <span class="error-message"><?= $errors['apellido_paterno'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellido_materno" class="form-label">
                            <i class="fas fa-user"></i>
                            Apellido Materno
                        </label>
                        <input type="text" 
                               id="apellido_materno" 
                               name="apellido_materno" 
                               class="form-input"
                               value="<?= htmlspecialchars($estudiante['apellido_materno'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono" class="form-label">
                            <i class="fas fa-phone"></i>
                            Teléfono
                        </label>
                        <input type="tel" 
                               id="telefono" 
                               name="telefono" 
                               class="form-input"
                               value="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>" 
                               placeholder="10 dígitos">
                    </div>
                </div>
            </div>

            <!-- Academic Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Información Académica
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="numero_control" class="form-label">
                            <i class="fas fa-id-card"></i>
                            Número de Control
                        </label>
                        <input type="text" 
                               id="numero_control" 
                               name="numero_control" 
                               class="form-input"
                               value="<?= htmlspecialchars($estudiante['numero_control'] ?? '') ?>" 
                               disabled>
                        <div class="input-help">
                            <i class="fas fa-lock"></i>
                            El número de control no puede ser modificado
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="carrera" class="form-label required">
                            <i class="fas fa-book"></i>
                            Carrera
                        </label>
                        <input type="text" 
                               id="carrera" 
                               name="carrera" 
                               class="form-input <?= isset($errors['carrera']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['carrera'] ?? '') ?>" 
                               required>
                        <?php if (isset($errors['carrera'])): ?>
                            <span class="error-message"><?= $errors['carrera'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="semestre" class="form-label required">
                            <i class="fas fa-calendar"></i>
                            Semestre
                        </label>
                        <input type="number" 
                               id="semestre" 
                               name="semestre" 
                               class="form-input <?= isset($errors['semestre']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['semestre'] ?? '') ?>" 
                               min="1" 
                               max="12" 
                               required>
                        <?php if (isset($errors['semestre'])): ?>
                            <span class="error-message"><?= $errors['semestre'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="creditos_cursados" class="form-label required">
                            <i class="fas fa-star"></i>
                            Créditos Cursados
                        </label>
                        <input type="number" 
                               id="creditos_cursados" 
                               name="creditos_cursados" 
                               class="form-input <?= isset($errors['creditos_cursados']) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($estudiante['creditos_cursados'] ?? '') ?>" 
                               min="0" 
                               required>
                        <?php if (isset($errors['creditos_cursados'])): ?>
                            <span class="error-message"><?= $errors['creditos_cursados'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Actualizar Perfil
                </button>
                <a href="../../auth/change-password.php" class="btn btn-info">
                    <i class="fas fa-key"></i>
                    Cambiar Contraseña
                </a>
            </div>
        </form>
    </div>
    </div>
</div>  

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --success: #10b981;
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
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
}

/* Profile Container */
.profile-container {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Profile Header */
.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.header-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.header-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Profile Card */
.profile-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

/* Alert Messages */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    margin: 1.5rem 1.5rem 0;
    border-radius: var(--radius);
    border: 1px solid transparent;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(52, 211, 153, 0.05) 100%);
    border-color: rgba(16, 185, 129, 0.2);
    color: #064e3b;
}

.alert-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(248, 113, 113, 0.05) 100%);
    border-color: rgba(239, 68, 68, 0.2);
    color: #7f1d1d;
}

.alert i {
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.alert strong {
    display: block;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.alert p {
    margin: 0;
    font-size: 0.9rem;
}

/* Profile Form */
.profile-form {
    padding: 1.5rem;
}

/* Form Sections */
.form-section {
    margin-bottom: 2rem;
}

.form-section:last-of-type {
    margin-bottom: 0;
}

.section-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

/* Form Groups */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.form-label.required::after {
    content: '*';
    color: var(--error);
    margin-left: 0.25rem;
}

.form-label i {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Form Inputs */
.form-input {
    padding: 0.875rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-primary);
    background: var(--bg-white);
    transition: var(--transition);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input:disabled {
    background: var(--bg-gray);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-input.error {
    border-color: var(--error);
}

.form-input.error:focus {
    border-color: var(--error);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

/* Input Help */
.input-help {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.input-help.warning {
    color: #92400e;
}

.input-help i {
    font-size: 0.75rem;
}

.input-help a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.input-help a:hover {
    text-decoration: underline;
}

/* Error Messages */
.error-message {
    color: var(--error);
    font-size: 0.8rem;
    font-weight: 500;
    margin-top: 0.25rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
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
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Loading State */
.btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.profile-card {
    animation: slideIn 0.6s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .profile-container {
        padding: 1rem;
    }
    
    .profile-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
    
    .profile-form {
        padding: 1rem;
    }
}

/* Focus improvements for accessibility */
.form-input:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* High contrast improvements */
@media (prefers-contrast: high) {
    .form-input {
        border-width: 2px;
    }
    
    .btn {
        border-width: 2px;
        border-style: solid;
    }
    
    .btn-primary {
        border-color: var(--primary);
    }
    
    .btn-secondary {
        border-color: var(--text-primary);
    }
    
    .btn-info {
        border-color: var(--info);
    }
}
/* Variables sidebar */
:root {
    --sidebar-width: 280px;
    --header-height: 70px;
}

/* Main wrapper con margen para sidebar */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
}

/* Dashboard container ajustado */
.dashboard-container {
    max-width: calc(1400px - var(--sidebar-width));
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

/* Responsive: En móvil sidebar se oculta */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .dashboard-container {
        max-width: 1400px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('.profile-form');
    const inputs = form.querySelectorAll('.form-input[required]');
    
    // Real-time validation
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    function validateField(field) {
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required');
        
        if (isRequired && !value) {
            field.classList.add('error');
            return false;
        }
        
        // Specific validations
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                field.classList.add('error');
                return false;
            }
        }
        
        if (field.name === 'semestre' && value) {
            if (parseInt(value) < 1 || parseInt(value) > 12) {
                field.classList.add('error');
                return false;
            }
        }
        
        if (field.name === 'creditos_cursados' && value) {
            if (parseInt(value) < 0) {
                field.classList.add('error');
                return false;
            }
        }
        
        field.classList.remove('error');
        return true;
    }
    
    // Form submission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            
            // Scroll to first error
            const firstError = form.querySelector('.form-input.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            
            return;
        }
        
        // Add loading state
        const submitBtn = form.querySelector('.btn-primary');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
        }
    });
    
    // Enhanced input interactions
    const allInputs = document.querySelectorAll('.form-input');
    allInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('telefono');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });
    }
    
    // Semester validation
    const semestreInput = document.getElementById('semestre');
    if (semestreInput) {
        semestreInput.addEventListener('input', function(e) {
            let value = parseInt(e.target.value);
            if (value > 12) {
                e.target.value = '12';
            } else if (value < 1) {
                e.target.value = '1';
            }
        });
    }
    
    // Credits validation
    const creditosInput = document.getElementById('creditos_cursados');
    if (creditosInput) {
        creditosInput.addEventListener('input', function(e) {
            let value = parseInt(e.target.value);
            if (value < 0) {
                e.target.value = '0';
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>