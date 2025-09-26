<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Obtener jefes de laboratorio del departamento
$jefesLaboratorio = $db->fetchAll("
    SELECT id, nombre, laboratorio 
    FROM jefes_laboratorio 
    WHERE jefe_departamento_id = :jefe_id AND activo = 1
    ORDER BY nombre
", ['jefe_id' => $jefeId]);

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = sanitizeInput($_POST);
    
    // Validaciones
    $requiredFields = [
        'nombre_proyecto', 'descripcion', 'tipo_actividades', 
        'objetivos', 'horas_requeridas', 'cupo_disponible'
    ];
    
    $errors = validateRequired($requiredFields, $formData);
    
    // Validaciones específicas
    if (empty($errors['horas_requeridas']) && (!is_numeric($formData['horas_requeridas']) || $formData['horas_requeridas'] < 1)) {
        $errors['horas_requeridas'] = 'Las horas requeridas deben ser un número mayor a 0';
    }
    
    if (empty($errors['cupo_disponible']) && (!is_numeric($formData['cupo_disponible']) || $formData['cupo_disponible'] < 1)) {
        $errors['cupo_disponible'] = 'El cupo disponible debe ser un número mayor a 0';
    }
    
    if (empty($errors['nombre_proyecto']) && strlen($formData['nombre_proyecto']) > 250) {
        $errors['nombre_proyecto'] = 'El nombre no debe exceder 250 caracteres';
    }
    
    // Si no hay errores, crear el proyecto
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $projectData = [
                'jefe_departamento_id' => $jefeId,
                'jefe_laboratorio_id' => !empty($formData['jefe_laboratorio_id']) ? $formData['jefe_laboratorio_id'] : null,
                'nombre_proyecto' => $formData['nombre_proyecto'],
                'descripcion' => $formData['descripcion'],
                'laboratorio_asignado' => $formData['laboratorio_asignado'] ?? null,
                'tipo_actividades' => $formData['tipo_actividades'],
                'objetivos' => $formData['objetivos'],
                'horas_requeridas' => (int)$formData['horas_requeridas'],
                'cupo_disponible' => (int)$formData['cupo_disponible'],
                'requisitos' => $formData['requisitos'] ?? null,
                'activo' => 1
            ];
            
            $projectId = $db->insert('proyectos_laboratorio', $projectData);
            
            // Registrar actividad
            logActivity($usuario['id'], 'crear', 'proyectos', $projectId, [
                'nombre_proyecto' => $formData['nombre_proyecto'],
                'horas_requeridas' => $formData['horas_requeridas'],
                'cupo_disponible' => $formData['cupo_disponible']
            ]);
            
            // Crear notificación para el jefe de laboratorio si se asignó
            if (!empty($formData['jefe_laboratorio_id'])) {
                $jefeLabData = $db->fetch("SELECT usuario_id, nombre FROM jefes_laboratorio WHERE id = ?", 
                                         [$formData['jefe_laboratorio_id']]);
                
                if ($jefeLabData) {
                    createNotification(
                        $jefeLabData['usuario_id'],
                        'Nuevo Proyecto Asignado',
                        "Se te ha asignado el proyecto: {$formData['nombre_proyecto']}",
                        'info',
                        "/modules/laboratorio/proyecto-detalle.php?id={$projectId}"
                    );
                }
            }
            
            $db->commit();
            
            flashMessage('Proyecto creado exitosamente', 'success');
            redirectTo('/servicio_social_ita/modules/departamento/proyectos.php');
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error creando proyecto: " . $e->getMessage());
            $errors['general'] = 'Error al crear el proyecto. Inténtalo nuevamente.';
        }
    }
}

$pageTitle = "Crear Proyecto - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span class="welcome-text">Crear Nuevo Proyecto</span>
                <span class="welcome-emoji">🚀</span>
            </h1>
            <p class="welcome-subtitle">Registra un nuevo proyecto de servicio social para tu departamento</p>
        </div>
        <div class="header-actions">
            <a href="../departamento/proyectos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Proyectos
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h4>Error al crear proyecto</h4>
                <p><?= $errors['general'] ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Form Container -->
    <div class="form-container">
        <form method="POST" class="project-form">
            
            <!-- Información General -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon general">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="section-title-group">
                        <h2 class="section-title">Información General</h2>
                        <p class="section-subtitle">Datos básicos del proyecto</p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nombre_proyecto" class="form-label required">
                            <i class="fas fa-project-diagram"></i>
                            Nombre del Proyecto
                        </label>
                        <input type="text" 
                               id="nombre_proyecto" 
                               name="nombre_proyecto" 
                               value="<?= htmlspecialchars($formData['nombre_proyecto'] ?? '') ?>"
                               class="form-control <?= isset($errors['nombre_proyecto']) ? 'error' : '' ?>"
                               placeholder="Ej: Desarrollo de Sistema de Inventario"
                               maxlength="250"
                               required>
                        <?php if (isset($errors['nombre_proyecto'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['nombre_proyecto'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="descripcion" class="form-label required">
                            <i class="fas fa-align-left"></i>
                            Descripción del Proyecto
                        </label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  rows="4"
                                  class="form-control <?= isset($errors['descripcion']) ? 'error' : '' ?>"
                                  placeholder="Describe detalladamente el proyecto, su propósito y alcance..."
                                  required><?= htmlspecialchars($formData['descripcion'] ?? '') ?></textarea>
                        <?php if (isset($errors['descripcion'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['descripcion'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="jefe_laboratorio_id" class="form-label">
                            <i class="fas fa-user-tie"></i>
                            Jefe de Laboratorio Asignado
                        </label>
                        <div class="select-wrapper">
                            <select id="jefe_laboratorio_id" name="jefe_laboratorio_id" class="form-control">
                                <option value="">Seleccionar (Opcional)</option>
                                <?php foreach ($jefesLaboratorio as $jefe): ?>
                                    <option value="<?= $jefe['id'] ?>" 
                                            <?= ($formData['jefe_laboratorio_id'] ?? '') == $jefe['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jefe['nombre']) ?> - <?= htmlspecialchars($jefe['laboratorio']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="laboratorio_asignado" class="form-label">
                            <i class="fas fa-flask"></i>
                            Laboratorio/Área
                        </label>
                        <input type="text" 
                               id="laboratorio_asignado" 
                               name="laboratorio_asignado" 
                               value="<?= htmlspecialchars($formData['laboratorio_asignado'] ?? '') ?>"
                               class="form-control"
                               placeholder="Ej: Laboratorio de Sistemas">
                    </div>
                </div>
            </div>

            <!-- Detalles del Proyecto -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon activities">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="section-title-group">
                        <h2 class="section-title">Detalles del Proyecto</h2>
                        <p class="section-subtitle">Actividades y objetivos específicos</p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="tipo_actividades" class="form-label required">
                            <i class="fas fa-clipboard-list"></i>
                            Tipo de Actividades
                        </label>
                        <textarea id="tipo_actividades" 
                                  name="tipo_actividades" 
                                  rows="4"
                                  class="form-control <?= isset($errors['tipo_actividades']) ? 'error' : '' ?>"
                                  placeholder="Describe las actividades que realizarán los estudiantes..."
                                  required><?= htmlspecialchars($formData['tipo_actividades'] ?? '') ?></textarea>
                        <?php if (isset($errors['tipo_actividades'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['tipo_actividades'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="objetivos" class="form-label required">
                            <i class="fas fa-bullseye"></i>
                            Objetivos del Proyecto
                        </label>
                        <textarea id="objetivos" 
                                  name="objetivos" 
                                  rows="4"
                                  class="form-control <?= isset($errors['objetivos']) ? 'error' : '' ?>"
                                  placeholder="Define los objetivos que se esperan alcanzar..."
                                  required><?= htmlspecialchars($formData['objetivos'] ?? '') ?></textarea>
                        <?php if (isset($errors['objetivos'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['objetivos'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="requisitos" class="form-label">
                            <i class="fas fa-check-square"></i>
                            Requisitos y Competencias
                        </label>
                        <textarea id="requisitos" 
                                  name="requisitos" 
                                  rows="3"
                                  class="form-control"
                                  placeholder="Especifica los requisitos o competencias necesarias para participar..."><?= htmlspecialchars($formData['requisitos'] ?? '') ?></textarea>
                        <small class="form-help">Opcional: Define los requisitos previos para participar en el proyecto</small>
                    </div>
                </div>
            </div>

            <!-- Configuración -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon config">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="section-title-group">
                        <h2 class="section-title">Configuración del Proyecto</h2>
                        <p class="section-subtitle">Horas y cupos disponibles</p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="horas_requeridas" class="form-label required">
                            <i class="fas fa-clock"></i>
                            Horas Requeridas
                        </label>
                        <div class="input-with-addon">
                            <input type="number" 
                                   id="horas_requeridas" 
                                   name="horas_requeridas" 
                                   value="<?= htmlspecialchars($formData['horas_requeridas'] ?? '500') ?>"
                                   class="form-control <?= isset($errors['horas_requeridas']) ? 'error' : '' ?>"
                                   min="1"
                                   max="1000"
                                   required>
                            <span class="input-addon">horas</span>
                        </div>
                        <?php if (isset($errors['horas_requeridas'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['horas_requeridas'] ?>
                            </span>
                        <?php endif; ?>
                        <small class="form-help">Horas totales que debe cumplir cada estudiante</small>
                    </div>

                    <div class="form-group">
                        <label for="cupo_disponible" class="form-label required">
                            <i class="fas fa-users"></i>
                            Cupo Disponible
                        </label>
                        <div class="input-with-addon">
                            <input type="number" 
                                   id="cupo_disponible" 
                                   name="cupo_disponible" 
                                   value="<?= htmlspecialchars($formData['cupo_disponible'] ?? '3') ?>"
                                   class="form-control <?= isset($errors['cupo_disponible']) ? 'error' : '' ?>"
                                   min="1"
                                   max="20"
                                   required>
                            <span class="input-addon">estudiantes</span>
                        </div>
                        <?php if (isset($errors['cupo_disponible'])): ?>
                            <span class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['cupo_disponible'] ?>
                            </span>
                        <?php endif; ?>
                        <small class="form-help">Número máximo de estudiantes que pueden participar</small>
                    </div>
                </div>
            </div>

            <!-- Acciones del Formulario -->
            <div class="form-actions">
                <div class="actions-group primary">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i>
                        <span>Crear Proyecto</span>
                    </button>
                </div>
                <div class="actions-group secondary">
                    <a href="../departamento/proyectos.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i>
                        <span>Cancelar</span>
                    </a>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

<style>
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
/* Variables CSS - Matching Dashboard */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
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
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
}

/* Dashboard Container */
.dashboard-container {
    padding: 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-subtitle {
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
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    animation: slideIn 0.5s ease-out;
}

.alert-error {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 1px solid #fecaca;
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--error);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.alert-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--error);
    margin: 0 0 0.25rem 0;
}

.alert-content p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Form Container */
.form-container {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.project-form {
    display: flex;
    flex-direction: column;
}

/* Form Sections */
.form-section {
    padding: 2rem;
    border-bottom: 1px solid var(--border-light);
    animation: slideIn 0.6s ease-out;
}

.form-section:last-child {
    border-bottom: none;
}

.section-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.section-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.section-icon.general {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.section-icon.activities {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.section-icon.config {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

.section-title-group {
    flex: 1;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.section-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    align-items: start;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

/* Form Labels */
.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-label.required::after {
    content: '*';
    color: var(--error);
    margin-left: 0.25rem;
}

.form-label i {
    color: var(--primary);
    font-size: 0.9rem;
}

/* Form Controls */
.form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.95rem;
    color: var(--text-primary);
    background: var(--bg-white);
    transition: var(--transition);
    resize: vertical;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: var(--bg-white);
}

.form-control.error {
    border-color: var(--error);
    background: #fef2f2;
}

.form-control.error:focus {
    border-color: var(--error);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.form-control::placeholder {
    color: var(--text-light);
    font-style: italic;
}

/* Select Wrapper */
.select-wrapper {
    position: relative;
}

.select-wrapper select {
    appearance: none;
    background-image: none;
    padding-right: 3rem;
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
    pointer-events: none;
    font-size: 0.8rem;
}

/* Input with Addon */
.input-with-addon {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-addon .form-control {
    padding-right: 4rem;
}

.input-addon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
    pointer-events: none;
}

/* Error Messages */
.error-message {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.85rem;
    color: var(--error);
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fef2f2;
    border-radius: var(--radius);
    border-left: 3px solid var(--error);
}

.error-message i {
    flex-shrink: 0;
}

/* Form Help */
.form-help {
    font-size: 0.8rem;
    color: var(--text-light);
    margin-top: 0.375rem;
    font-style: italic;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
    gap: 1rem;
}

.actions-group {
    display: flex;
    gap: 1rem;
}

.actions-group.primary {
    flex: 1;
}

.actions-group.secondary {
    justify-content: flex-end;
}

/* Buttons - Matching Dashboard */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
    min-width: 140px;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
    min-width: 160px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
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

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Form Section Stagger */
.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.2s; }
.form-section:nth-child(3) { animation-delay: 0.3s; }

/* Loading State */
.btn.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .section-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    
    .welcome-title {
        font-size: 1.75rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .form-section {
        padding: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
        padding: 1.5rem;
    }
    
    .actions-group {
        width: 100%;
        justify-content: center;
    }
    
    .actions-group.secondary {
        justify-content: center;
        order: -1;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .section-title {
        font-size: 1.25rem;
    }
    
    .form-control {
        padding: 0.75rem 0.875rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .welcome-title {
        font-size: 1.5rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .form-section {
        padding: 1rem;
    }
    
    .form-actions {
        padding: 1rem;
    }
    
    .section-header {
        gap: 0.5rem;
    }
    
    .section-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .btn-lg {
        padding: 0.875rem 1.5rem;
        font-size: 0.95rem;
        min-width: auto;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    :root {
        --border: #000000;
        --text-primary: #000000;
        --bg-white: #ffffff;
    }
    
    .form-control:focus {
        outline: 3px solid var(--primary);
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-llenar laboratorio asignado cuando se selecciona jefe de laboratorio
    const jefeSelect = document.getElementById('jefe_laboratorio_id');
    const laboratorioField = document.getElementById('laboratorio_asignado');
    
    jefeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            const laboratorioText = selectedOption.text.split(' - ')[1];
            if (laboratorioText && !laboratorioField.value.trim()) {
                laboratorioField.value = laboratorioText;
                // Animate the field to show the change
                laboratorioField.style.background = '#f0f9ff';
                setTimeout(() => {
                    laboratorioField.style.background = '';
                }, 1000);
            }
        }
    });
    
    // Form validation enhancement
    const form = document.querySelector('.project-form');
    const requiredFields = form.querySelectorAll('[required]');
    
    // Real-time validation
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    function validateField(field) {
        const errorMsg = field.parentNode.querySelector('.error-message');
        
        if (!field.value.trim()) {
            field.classList.add('error');
            if (!errorMsg) {
                showFieldError(field, 'Este campo es obligatorio');
            }
        } else {
            field.classList.remove('error');
            if (errorMsg && !field.dataset.serverError) {
                errorMsg.remove();
            }
        }
    }
    
    function showFieldError(field, message) {
        const errorElement = document.createElement('span');
        errorElement.className = 'error-message';
        errorElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        field.parentNode.appendChild(errorElement);
    }
    
    // Enhanced form submission
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Add loading state
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Creando proyecto...</span>';
        submitBtn.disabled = true;
        
        // Validate all required fields
        let hasErrors = false;
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                hasErrors = true;
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            submitBtn.classList.remove('loading');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            // Scroll to first error
            const firstError = form.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
    });
    
    // Character counters for text areas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        if (maxLength) {
            addCharacterCounter(textarea, maxLength);
        }
    });
    
    function addCharacterCounter(textarea, maxLength) {
        const counter = document.createElement('div');
        counter.className = 'character-counter';
        counter.style.cssText = `
            font-size: 0.75rem;
            color: var(--text-light);
            text-align: right;
            margin-top: 0.25rem;
        `;
        
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length}/${maxLength}`;
            counter.style.color = remaining < 50 ? 'var(--warning)' : 'var(--text-light)';
        }
        
        textarea.addEventListener('input', updateCounter);
        textarea.parentNode.appendChild(counter);
        updateCounter();
    }
    
    // Smooth animations for form sections
    const sections = document.querySelectorAll('.form-section');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    sections.forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(section);
    });
    
    // Auto-save draft functionality (optional)
    let saveTimeout;
    const formInputs = form.querySelectorAll('input, textarea, select');
    
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveDraft();
            }, 2000);
        });
    });
    
    function saveDraft() {
        const formData = new FormData(form);
        const draftData = {};
        
        for (let [key, value] of formData.entries()) {
            draftData[key] = value;
        }
        
        localStorage.setItem('proyecto_draft', JSON.stringify(draftData));
        
        // Show save indicator
        showSaveIndicator();
    }
    
    function showSaveIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'save-indicator';
        indicator.innerHTML = '<i class="fas fa-check"></i> Borrador guardado';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        document.body.appendChild(indicator);
        
        setTimeout(() => indicator.style.opacity = '1', 10);
        setTimeout(() => {
            indicator.style.opacity = '0';
            setTimeout(() => indicator.remove(), 300);
        }, 2000);
    }
    
    // Load draft on page load
    const savedDraft = localStorage.getItem('proyecto_draft');
    if (savedDraft && !form.querySelector('.error-message')) {
        try {
            const draftData = JSON.parse(savedDraft);
            
            Object.keys(draftData).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && !field.value) {
                    field.value = draftData[key];
                }
            });
        } catch (e) {
            console.warn('Error loading draft:', e);
        }
    }
    
    // Clear draft on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem('proyecto_draft');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>