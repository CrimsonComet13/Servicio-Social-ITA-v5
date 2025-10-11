<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Obtener el perfil del jefe de departamento
$jefeDepto = $db->fetch("SELECT id FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeDeptoId = $jefeDepto['id'];

// Obtener ID del jefe de laboratorio a editar
$jefeLabId = $_GET['id'] ?? 0;
if (!$jefeLabId) {
    flashMessage('ID de jefe de laboratorio no válido', 'error');
    redirectTo('/modules/departamento/laboratorios.php');
}

// Obtener datos del jefe de laboratorio (solo si pertenece a este departamento)
$jefeLab = $db->fetch("
    SELECT jl.*, u.email, u.activo as usuario_activo
    FROM jefes_laboratorio jl
    JOIN usuarios u ON jl.usuario_id = u.id
    WHERE jl.id = ? AND jl.jefe_departamento_id = ?
", [$jefeLabId, $jefeDeptoId]);

if (!$jefeLab) {
    flashMessage('Jefe de laboratorio no encontrado o no tiene permisos para editarlo', 'error');
    redirectTo('/modules/departamento/laboratorios.php');
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validar campos requeridos
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $laboratorio = trim($_POST['laboratorio'] ?? '');
    $especialidad = trim($_POST['especialidad'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $extension = trim($_POST['extension'] ?? '');
    
    if (empty($nombre)) {
        $errors[] = 'El nombre es requerido';
    }
    
    if (empty($email)) {
        $errors[] = 'El email es requerido';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no es válido';
    } else {
        // Verificar si el email ya existe (excepto el actual)
        $emailExists = $db->fetch(
            "SELECT id FROM usuarios WHERE email = ? AND id != ?", 
            [$email, $jefeLab['usuario_id']]
        );
        if ($emailExists) {
            $errors[] = 'El email ya está registrado';
        }
    }
    
    if (empty($laboratorio)) {
        $errors[] = 'El laboratorio es requerido';
    }
    
    // Validar teléfono si se proporciona
    if (!empty($telefono) && !preg_match('/^[0-9\s\-\+\(\)]+$/', $telefono)) {
        $errors[] = 'El formato del teléfono no es válido';
    }
    
    if (empty($errors)) {
        try {
            // Iniciar transacción
            $db->beginTransaction();
            
            // Actualizar tabla usuarios
            $db->update('usuarios', [
                'email' => $email
            ], 'id = :id', ['id' => $jefeLab['usuario_id']]);
            
            // Actualizar tabla jefes_laboratorio
            $db->update('jefes_laboratorio', [
                'nombre' => $nombre,
                'laboratorio' => $laboratorio,
                'especialidad' => $especialidad,
                'telefono' => $telefono,
                'extension' => $extension
            ], 'id = :id', ['id' => $jefeLabId]);
            
            // Confirmar transacción
            $db->commit();
            
            flashMessage('Jefe de laboratorio actualizado correctamente', 'success');
            redirectTo('/modules/departamento/laboratorios.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Editar Jefe de Laboratorio - " . APP_NAME;
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
                        <i class="fas fa-user-edit"></i>
                        Editar Jefe de Laboratorio
                    </h1>
                    <p class="page-subtitle">Actualizar información del jefe de laboratorio</p>
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

        <!-- Form Section -->
        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($jefeLab['nombre'], 0, 1)) ?>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?= htmlspecialchars($jefeLab['nombre']) ?></h2>
                        <p class="profile-lab"><?= htmlspecialchars($jefeLab['laboratorio']) ?></p>
                        <span class="status-badge <?= $jefeLab['activo'] ? 'status-active' : 'status-inactive' ?>">
                            <i class="fas fa-<?= $jefeLab['activo'] ? 'check-circle' : 'times-circle' ?>"></i>
                            <?= $jefeLab['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                </div>

                <form method="POST" id="editForm" class="edit-form">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Información Personal
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre" class="form-label required">
                                    <i class="fas fa-user"></i>
                                    Nombre Completo
                                </label>
                                <input 
                                    type="text" 
                                    id="nombre" 
                                    name="nombre" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($jefeLab['nombre']) ?>"
                                    required
                                    maxlength="100"
                                    placeholder="Ej: Dr. Juan Pérez García"
                                >
                                <small class="form-help">Nombre completo incluyendo títulos</small>
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label required">
                                    <i class="fas fa-envelope"></i>
                                    Correo Electrónico
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($jefeLab['email']) ?>"
                                    required
                                    maxlength="100"
                                    placeholder="correo@ejemplo.com"
                                >
                                <small class="form-help">Email institucional preferentemente</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-flask"></i>
                            Información del Laboratorio
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="laboratorio" class="form-label required">
                                    <i class="fas fa-vials"></i>
                                    Laboratorio
                                </label>
                                <input 
                                    type="text" 
                                    id="laboratorio" 
                                    name="laboratorio" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($jefeLab['laboratorio']) ?>"
                                    required
                                    maxlength="100"
                                    placeholder="Ej: Laboratorio de Física"
                                >
                                <small class="form-help">Nombre del laboratorio a cargo</small>
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
                                    value="<?= htmlspecialchars($jefeLab['especialidad'] ?? '') ?>"
                                    maxlength="100"
                                    placeholder="Ej: Física Aplicada, Química Orgánica"
                                >
                                <small class="form-help">Área de especialización (opcional)</small>
                            </div>
                        </div>
                    </div>

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
                                    value="<?= htmlspecialchars($jefeLab['telefono'] ?? '') ?>"
                                    maxlength="20"
                                    placeholder="Ej: (449) 123-4567"
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
                                    value="<?= htmlspecialchars($jefeLab['extension'] ?? '') ?>"
                                    maxlength="10"
                                    placeholder="Ej: 1234"
                                    pattern="[0-9]+"
                                >
                                <small class="form-help">Extensión telefónica (opcional)</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" onclick="window.location.href='laboratorios.php'" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Card -->
            <div class="info-card">
                <div class="info-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Información</h3>
                </div>
                <div class="info-content">
                    <div class="info-item">
                        <i class="fas fa-check-circle"></i>
                        <p>Los campos marcados con <span class="required-mark">*</span> son obligatorios</p>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-shield-alt"></i>
                        <p>Los cambios se guardarán de forma segura en la base de datos</p>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <p>Si cambia el email, se actualizará en el sistema de usuarios</p>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-history"></i>
                        <p>El jefe de laboratorio recibirá notificación de los cambios</p>
                    </div>
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
    max-width: 1200px;
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

/* Form Container */
.form-container {
    display: grid;
    grid-template-columns: 1fr 350px;
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

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    border: 3px solid rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}

.profile-info {
    flex: 1;
}

.profile-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
}

.profile-lab {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0 0 0.75rem 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Edit Form */
.edit-form {
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

.required-mark {
    color: var(--error);
    font-weight: 700;
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
    background: linear-gradient(135deg, var(--info), #60a5fa);
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
    margin-bottom: 1.25rem;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item i {
    color: var(--primary);
    font-size: 1.1rem;
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.info-item p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.6;
    font-size: 0.95rem;
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-primary:active:not(:disabled) {
    transform: translateY(0);
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

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
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

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
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
    
    .edit-form {
        padding: 1.5rem;
    }
    
    .form-header {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .profile-name {
        font-size: 1.25rem;
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
    
    .edit-form {
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

/* Input Validation States */
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editForm');
    const submitBtn = document.getElementById('submitBtn');
    const inputs = form.querySelectorAll('.form-input');
    
    // Real-time validation
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
    
    // Validate field
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
        
        if (field.type === 'tel' && field.value) {
            const phoneRegex = /^[0-9\s\-\+\(\)]+$/;
            if (!phoneRegex.test(field.value)) {
                formGroup.classList.add('has-error');
                field.classList.add('is-invalid');
                return false;
            }
        }
        
        formGroup.classList.remove('has-error');
        field.classList.remove('is-invalid');
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
            
            // Show error message
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
            
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            form.parentNode.insertBefore(alertDiv, form);
            
            // Scroll to first error
            const firstError = document.querySelector('.has-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Remove alert after 5 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-20px)';
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
            
            return false;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        
        // If validation passes, form will submit normally
    });
    
    // Add focus animation to inputs
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.01)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = '';
        });
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
    
    // Animate form elements on load
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
    
    // Unsaved changes warning
    let formChanged = false;
    
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged && !form.classList.contains('submitting')) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
    
    form.addEventListener('submit', function() {
        form.classList.add('submitting');
        formChanged = false;
    });
});
</script>

<?php include '../../includes/footer.php'; ?>