<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeDepto = $db->fetch("SELECT id FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

// Validar ID del proyecto
$projectId = $_GET['id'] ?? null;
if (!$projectId || !is_numeric($projectId)) {
    flashMessage('Proyecto no válido', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

// Obtener datos del proyecto
$proyecto = $db->fetch("
    SELECT * FROM proyectos_laboratorio 
    WHERE id = :id AND jefe_departamento_id = :jefe_id
", ['id' => $projectId, 'jefe_id' => $jefeId]);

if (!$proyecto) {
    flashMessage('Proyecto no encontrado', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

// Verificar si hay estudiantes asignados (para mostrar advertencias)
$estudiantesAsignados = $db->fetch("
    SELECT COUNT(*) as total 
    FROM solicitudes_servicio 
    WHERE proyecto_id = :proyecto_id AND estado IN ('aprobada', 'en_proceso')
", ['proyecto_id' => $projectId])['total'];

// Obtener jefes de laboratorio del departamento
$jefesLaboratorio = $db->fetchAll("
    SELECT id, nombre, laboratorio 
    FROM jefes_laboratorio 
    WHERE jefe_departamento_id = :jefe_id AND activo = 1
    ORDER BY nombre
", ['jefe_id' => $jefeId]);

$errors = [];
$formData = $proyecto; // Inicializar con datos existentes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_merge($proyecto, sanitizeInput($_POST));
    
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
    
    // Validar que el nuevo cupo no sea menor que el ocupado actual
    if (empty($errors['cupo_disponible']) && $formData['cupo_disponible'] < $proyecto['cupo_ocupado']) {
        $errors['cupo_disponible'] = "El cupo no puede ser menor al ocupado actualmente ({$proyecto['cupo_ocupado']} estudiantes)";
    }
    
    if (empty($errors['nombre_proyecto']) && strlen($formData['nombre_proyecto']) > 250) {
        $errors['nombre_proyecto'] = 'El nombre no debe exceder 250 caracteres';
    }
    
    // Si no hay errores, actualizar el proyecto
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Detectar cambios importantes
            $cambiosImportantes = [];
            if ($proyecto['horas_requeridas'] != $formData['horas_requeridas']) {
                $cambiosImportantes[] = "Horas requeridas: {$proyecto['horas_requeridas']} → {$formData['horas_requeridas']}";
            }
            if ($proyecto['jefe_laboratorio_id'] != $formData['jefe_laboratorio_id']) {
                $cambiosImportantes[] = "Jefe de laboratorio cambiado";
            }
            
            $updateData = [
                'jefe_laboratorio_id' => !empty($formData['jefe_laboratorio_id']) ? $formData['jefe_laboratorio_id'] : null,
                'nombre_proyecto' => $formData['nombre_proyecto'],
                'descripcion' => $formData['descripcion'],
                'laboratorio_asignado' => $formData['laboratorio_asignado'] ?? null,
                'tipo_actividades' => $formData['tipo_actividades'],
                'objetivos' => $formData['objetivos'],
                'horas_requeridas' => (int)$formData['horas_requeridas'],
                'cupo_disponible' => (int)$formData['cupo_disponible'],
                'requisitos' => $formData['requisitos'] ?? null
            ];
            
            $db->update('proyectos_laboratorio', $updateData, 'id = :id', ['id' => $projectId]);
            
            // Registrar actividad
            logActivity($usuario['id'], 'editar', 'proyectos', $projectId, [
                'nombre_proyecto' => $formData['nombre_proyecto'],
                'cambios_importantes' => $cambiosImportantes
            ]);
            
            // Notificar a estudiantes si hay cambios importantes y hay estudiantes asignados
            if (!empty($cambiosImportantes) && $estudiantesAsignados > 0) {
                $estudiantes = $db->fetchAll("
                    SELECT DISTINCT e.usuario_id, e.nombre 
                    FROM estudiantes e
                    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
                    WHERE s.proyecto_id = :proyecto_id AND s.estado IN ('aprobada', 'en_proceso')
                ", ['proyecto_id' => $projectId]);
                
                foreach ($estudiantes as $estudiante) {
                    createNotification(
                        $estudiante['usuario_id'],
                        'Proyecto Actualizado',
                        "El proyecto '{$formData['nombre_proyecto']}' ha sido actualizado. Revisa los cambios.",
                        'info',
                        "/modules/estudiante/proyecto-detalle.php?id={$projectId}"
                    );
                }
            }
            
            // Notificar al nuevo jefe de laboratorio si cambió
            if ($proyecto['jefe_laboratorio_id'] != $formData['jefe_laboratorio_id'] && !empty($formData['jefe_laboratorio_id'])) {
                $jefeLabData = $db->fetch("SELECT usuario_id, nombre FROM jefes_laboratorio WHERE id = ?", 
                                         [$formData['jefe_laboratorio_id']]);
                
                if ($jefeLabData) {
                    createNotification(
                        $jefeLabData['usuario_id'],
                        'Proyecto Asignado',
                        "Se te ha asignado el proyecto: {$formData['nombre_proyecto']}",
                        'info',
                        "/modules/laboratorio/proyecto-detalle.php?id={$projectId}"
                    );
                }
            }
            
            $db->commit();
            
            flashMessage('Proyecto actualizado exitosamente', 'success');
            redirectTo('/modules/departamento/proyectos.php');
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error actualizando proyecto: " . $e->getMessage());
            $errors['general'] = 'Error al actualizar el proyecto. Inténtalo nuevamente.';
        }
    }
}

$pageTitle = "Editar Proyecto - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <i class="fas fa-edit"></i>
                <span class="welcome-text">Editar Proyecto</span>
            </h1>
            <p class="welcome-subtitle">Modifica la información del proyecto de servicio social</p>
        </div>
        <div class="header-actions">
            <a href="/servicio_social_ita/modules/departamento/proyecto-detalle.php?id=<?= $projectId ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> Ver Detalle
            </a>
            <a href="/servicio_social_ita/modules/departamento/proyectos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Proyectos
            </a>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if ($estudiantesAsignados > 0): ?>
        <div class="alert alert-warning">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">Atención</div>
                <div class="alert-message">
                    Este proyecto tiene <?= $estudiantesAsignados ?> estudiante(s) asignado(s).
                    Los cambios importantes serán notificados automáticamente.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-message"><?= $errors['general'] ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Container -->
    <div class="form-container">
        <form method="POST" class="project-form">
            <!-- Información General Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nombre_proyecto" class="form-label required">Nombre del Proyecto</label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <input type="text" 
                                   id="nombre_proyecto" 
                                   name="nombre_proyecto" 
                                   value="<?= htmlspecialchars($formData['nombre_proyecto']) ?>"
                                   class="form-control <?= isset($errors['nombre_proyecto']) ? 'error' : '' ?>"
                                   placeholder="Ej: Desarrollo de Sistema de Inventario"
                                   maxlength="250"
                                   required>
                        </div>
                        <?php if (isset($errors['nombre_proyecto'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['nombre_proyecto'] ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="descripcion" class="form-label required">Descripción del Proyecto</label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  rows="4"
                                  class="form-control <?= isset($errors['descripcion']) ? 'error' : '' ?>"
                                  placeholder="Describe detalladamente el proyecto, su propósito y alcance..."
                                  required><?= htmlspecialchars($formData['descripcion']) ?></textarea>
                        <?php if (isset($errors['descripcion'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['descripcion'] ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="jefe_laboratorio_id" class="form-label">Jefe de Laboratorio Asignado</label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <select id="jefe_laboratorio_id" name="jefe_laboratorio_id" class="form-control">
                                <option value="">Seleccionar (Opcional)</option>
                                <?php foreach ($jefesLaboratorio as $jefe): ?>
                                    <option value="<?= $jefe['id'] ?>" 
                                            <?= $formData['jefe_laboratorio_id'] == $jefe['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jefe['nombre']) ?> - <?= htmlspecialchars($jefe['laboratorio']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($proyecto['jefe_laboratorio_id'] != $formData['jefe_laboratorio_id']): ?>
                            <div class="form-help warning">
                                <i class="fas fa-info-circle"></i> 
                                El cambio de jefe de laboratorio será notificado
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="laboratorio_asignado" class="form-label">Laboratorio/Área</label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-flask"></i>
                            </div>
                            <input type="text" 
                                   id="laboratorio_asignado" 
                                   name="laboratorio_asignado" 
                                   value="<?= htmlspecialchars($formData['laboratorio_asignado'] ?? '') ?>"
                                   class="form-control"
                                   placeholder="Ej: Laboratorio de Sistemas">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles del Proyecto Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i>
                        Detalles del Proyecto
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="tipo_actividades" class="form-label required">Tipo de Actividades</label>
                        <textarea id="tipo_actividades" 
                                  name="tipo_actividades" 
                                  rows="3"
                                  class="form-control <?= isset($errors['tipo_actividades']) ? 'error' : '' ?>"
                                  placeholder="Describe las actividades que realizarán los estudiantes..."
                                  required><?= htmlspecialchars($formData['tipo_actividades']) ?></textarea>
                        <?php if (isset($errors['tipo_actividades'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['tipo_actividades'] ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="objetivos" class="form-label required">Objetivos del Proyecto</label>
                        <textarea id="objetivos" 
                                  name="objetivos" 
                                  rows="3"
                                  class="form-control <?= isset($errors['objetivos']) ? 'error' : '' ?>"
                                  placeholder="Define los objetivos que se esperan alcanzar..."
                                  required><?= htmlspecialchars($formData['objetivos']) ?></textarea>
                        <?php if (isset($errors['objetivos'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['objetivos'] ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label for="requisitos" class="form-label">Requisitos y Competencias</label>
                        <textarea id="requisitos" 
                                  name="requisitos" 
                                  rows="3"
                                  class="form-control"
                                  placeholder="Especifica los requisitos o competencias necesarias para participar..."><?= htmlspecialchars($formData['requisitos'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Configuración Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-cog"></i>
                        Configuración
                    </h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="horas_requeridas" class="form-label required">Horas Requeridas</label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <input type="number" 
                                   id="horas_requeridas" 
                                   name="horas_requeridas" 
                                   value="<?= htmlspecialchars($formData['horas_requeridas']) ?>"
                                   class="form-control <?= isset($errors['horas_requeridas']) ? 'error' : '' ?>"
                                   min="1"
                                   max="1000"
                                   required>
                        </div>
                        <?php if (isset($errors['horas_requeridas'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['horas_requeridas'] ?>
                            </div>
                        <?php elseif ($proyecto['horas_requeridas'] != $formData['horas_requeridas']): ?>
                            <div class="form-help warning">
                                <i class="fas fa-info-circle"></i> 
                                El cambio de horas será notificado a los estudiantes
                            </div>
                        <?php else: ?>
                            <div class="form-help">
                                Horas totales que debe cumplir cada estudiante
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cupo_disponible" class="form-label required">Cupo Disponible</label>
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <input type="number" 
                                   id="cupo_disponible" 
                                   name="cupo_disponible" 
                                   value="<?= htmlspecialchars($formData['cupo_disponible']) ?>"
                                   class="form-control <?= isset($errors['cupo_disponible']) ? 'error' : '' ?>"
                                   min="<?= $proyecto['cupo_ocupado'] ?>"
                                   max="20"
                                   required>
                        </div>
                        <?php if (isset($errors['cupo_disponible'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['cupo_disponible'] ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-help" id="cupo-info">
                            Ocupado: <?= $proyecto['cupo_ocupado'] ?> | 
                            Disponible: <?= max(0, $formData['cupo_disponible'] - $proyecto['cupo_ocupado']) ?>
                        </div>
                    </div>
                </div>

                <!-- Project Status -->
                <div class="project-status">
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="status-content">
                                <span class="status-label">Estado</span>
                                <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="status-content">
                                <span class="status-label">Creado</span>
                                <span class="status-value"><?= formatDateTime($proyecto['created_at']) ?></span>
                            </div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-icon">
                                <i class="fas fa-calendar-edit"></i>
                            </div>
                            <div class="status-content">
                                <span class="status-label">Última modificación</span>
                                <span class="status-value"><?= formatDateTime($proyecto['updated_at']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 
                    <span>Guardar Cambios</span>
                </button>
                <a href="/modules/departamento/proyectos.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> 
                    <span>Cancelar</span>
                </a>
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
/* Variables CSS */
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
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-title i {
    color: var(--primary);
}

.welcome-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 1rem 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.25rem;
    border: 1px solid transparent;
    animation: slideIn 0.4s ease-out;
}

.alert-warning {
    background: linear-gradient(135deg, #fef3cd, #fff3cd);
    border-color: #facc15;
    color: #92400e;
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border-color: #f87171;
    color: #b91c1c;
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.alert-warning .alert-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.alert-error .alert-icon {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.alert-message {
    font-size: 0.9rem;
    line-height: 1.5;
}

/* Form Container */
.form-container {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: slideIn 0.6s ease-out;
}

/* Form Sections */
.form-section {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-light);
}

.form-section:last-child {
    border-bottom: none;
}

.section-header {
    margin-bottom: 1.5rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-title i {
    color: var(--primary);
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
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
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.form-label.required::after {
    content: '*';
    color: var(--error);
    margin-left: 0.25rem;
}

/* Input Groups */
.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: var(--text-light);
    font-size: 0.9rem;
    z-index: 10;
    pointer-events: none;
}

/* Form Controls */
.form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: var(--bg-white);
    color: var(--text-primary);
    transition: var(--transition);
}

.input-group .form-control {
    padding-left: 2.75rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control.error {
    border-color: var(--error);
    background: #fef2f2;
}

.form-control.error:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

select.form-control {
    cursor: pointer;
}

/* Form Help Text */
.form-help {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.form-help.warning {
    color: var(--warning);
}

.form-help i {
    font-size: 0.75rem;
}

/* Error Messages */
.error-message {
    color: var(--error);
    font-size: 0.8rem;
    margin-top: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    animation: shake 0.3s ease-in-out;
}

.error-message i {
    font-size: 0.75rem;
}

/* Project Status */
.project-status {
    background: var(--bg-light);
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-top: 1.5rem;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.status-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.status-content {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.status-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-value {
    font-size: 0.9rem;
    color: var(--text-primary);
    font-weight: 500;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.badge-secondary {
    background: var(--bg-gray);
    color: var(--text-secondary);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: var(--bg-light);
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
    border: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border-color: var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--bg-light);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
    box-shadow: var(--shadow);
}

.btn-info:hover {
    box-shadow: var(--shadow-lg);
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

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .header-actions .btn {
        flex: 1;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .form-section {
        padding: 1.25rem 1.5rem;
    }
    
    .form-actions {
        padding: 1.25rem 1.5rem;
        flex-direction: column;
    }
    
    .status-grid {
        grid-template-columns: 1fr;
    }
    
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .section-title {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .form-section {
        padding: 1rem;
    }
    
    .form-actions {
        padding: 1rem;
    }
    
    .form-grid {
        gap: 1rem;
    }
    
    .alert {
        padding: 0.875rem 1rem;
        flex-direction: column;
        text-align: center;
    }
    
    .alert-icon {
        margin: 0 auto;
    }
    
    .status-item {
        padding: 0.75rem;
    }
}

/* Loading state for buttons */
.btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.btn.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Form validation enhancement */
.form-control:invalid {
    border-color: var(--error);
}

.form-control:valid {
    border-color: var(--success);
}

/* Focus visible for accessibility */
.btn:focus-visible,
.form-control:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
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
            if (laboratorioText && !laboratorioField.value) {
                laboratorioField.value = laboratorioText;
                
                // Animación suave
                laboratorioField.style.background = '#f0fdf4';
                setTimeout(() => {
                    laboratorioField.style.background = '';
                }, 2000);
            }
        }
    });

    // Actualizar información de cupo en tiempo real
    const cupoInput = document.getElementById('cupo_disponible');
    const cupoInfo = document.getElementById('cupo-info');
    const cupoOcupado = <?= $proyecto['cupo_ocupado'] ?>;
    
    cupoInput.addEventListener('input', function() {
        const cupoTotal = parseInt(this.value) || 0;
        const cupoDisponible = Math.max(0, cupoTotal - cupoOcupado);
        
        if (cupoInfo && !cupoInfo.classList.contains('error-message')) {
            cupoInfo.innerHTML = `Ocupado: ${cupoOcupado} | Disponible: ${cupoDisponible}`;
            
            // Cambiar color según disponibilidad
            if (cupoDisponible === 0) {
                cupoInfo.style.color = 'var(--warning)';
            } else if (cupoDisponible < 0) {
                cupoInfo.style.color = 'var(--error)';
            } else {
                cupoInfo.style.color = 'var(--success)';
            }
        }
    });

    // Confirmación para cambios importantes
    const form = document.querySelector('.project-form');
    form.addEventListener('submit', function(e) {
        const horasOriginales = <?= $proyecto['horas_requeridas'] ?>;
        const horasNuevas = parseInt(document.getElementById('horas_requeridas').value);
        const estudiantesAsignados = <?= $estudiantesAsignados ?>;
        
        if (estudiantesAsignados > 0 && horasOriginales !== horasNuevas) {
            if (!confirm(`¿Estás seguro de cambiar las horas requeridas de ${horasOriginales} a ${horasNuevas}?\n\nLos ${estudiantesAsignados} estudiante(s) asignado(s) serán notificados de este cambio.`)) {
                e.preventDefault();
                return;
            }
        }
        
        // Agregar estado de carga al botón
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner"></i> <span>Guardando...</span>';
        }
    });

    // Validación en tiempo real
    const requiredFields = document.querySelectorAll('input[required], textarea[required], select[required]');
    
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('error');
            } else {
                this.classList.remove('error');
            }
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('error') && this.value.trim() !== '') {
                this.classList.remove('error');
            }
        });
    });

    // Contador de caracteres para campos de texto
    const textFields = document.querySelectorAll('input[maxlength], textarea[maxlength]');
    
    textFields.forEach(field => {
        const maxLength = field.getAttribute('maxlength');
        if (maxLength) {
            const counter = document.createElement('div');
            counter.className = 'character-counter';
            counter.style.cssText = `
                font-size: 0.75rem;
                color: var(--text-light);
                text-align: right;
                margin-top: 0.25rem;
            `;
            
            field.parentNode.appendChild(counter);
            
            function updateCounter() {
                const remaining = maxLength - field.value.length;
                counter.textContent = `${field.value.length}/${maxLength}`;
                
                if (remaining < 20) {
                    counter.style.color = 'var(--warning)';
                } else if (remaining === 0) {
                    counter.style.color = 'var(--error)';
                } else {
                    counter.style.color = 'var(--text-light)';
                }
            }
            
            updateCounter();
            field.addEventListener('input', updateCounter);
        }
    });

    // Animación de entrada para los elementos
    const animatedElements = document.querySelectorAll('.form-section, .alert');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    });

    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Smooth scroll para enlaces internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Auto-save (opcional - guardado temporal en localStorage)
    const formData = {};
    const formFields = form.querySelectorAll('input, textarea, select');
    
    formFields.forEach(field => {
        // Cargar datos guardados
        const savedValue = localStorage.getItem(`proyecto_edit_${field.name}`);
        if (savedValue && field.value === '') {
            field.value = savedValue;
        }
        
        // Guardar cambios automáticamente
        field.addEventListener('input', debounce(function() {
            localStorage.setItem(`proyecto_edit_${field.name}`, this.value);
        }, 1000));
    });

    // Limpiar localStorage al enviar el formulario exitosamente
    form.addEventListener('submit', function() {
        formFields.forEach(field => {
            localStorage.removeItem(`proyecto_edit_${field.name}`);
        });
    });
});

// Función debounce para optimizar performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Función para validar formulario antes del envío
function validateForm() {
    const form = document.querySelector('.project-form');
    let isValid = true;
    
    const requiredFields = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    requiredFields.forEach(field => {
        if (field.value.trim() === '') {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}
</script>

<?php include '../../includes/footer.php'; ?>