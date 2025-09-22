<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

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

<div class="dashboard-content">
    <div class="dashboard-header">
        <div>
            <h1>Editar Proyecto</h1>
            <p>Modifica la información del proyecto de servicio social</p>
        </div>
        <div class="header-actions">
            <a href="/modules/departamento/proyecto-detalle.php?id=<?= $projectId ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> Ver Detalle
            </a>
            <a href="/modules/departamento/proyectos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Proyectos
            </a>
        </div>
    </div>

    <?php if ($estudiantesAsignados > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Atención:</strong> Este proyecto tiene <?= $estudiantesAsignados ?> estudiante(s) asignado(s).
                Los cambios importantes serán notificados automáticamente.
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= $errors['general'] ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" class="project-form">
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Información General</h2>
                
                <div class="form-group">
                    <label for="nombre_proyecto" class="required">Nombre del Proyecto</label>
                    <input type="text" 
                           id="nombre_proyecto" 
                           name="nombre_proyecto" 
                           value="<?= htmlspecialchars($formData['nombre_proyecto']) ?>"
                           class="form-control <?= isset($errors['nombre_proyecto']) ? 'error' : '' ?>"
                           placeholder="Ej: Desarrollo de Sistema de Inventario"
                           maxlength="250"
                           required>
                    <?php if (isset($errors['nombre_proyecto'])): ?>
                        <span class="error-message"><?= $errors['nombre_proyecto'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="descripcion" class="required">Descripción del Proyecto</label>
                    <textarea id="descripcion" 
                              name="descripcion" 
                              rows="4"
                              class="form-control <?= isset($errors['descripcion']) ? 'error' : '' ?>"
                              placeholder="Describe detalladamente el proyecto, su propósito y alcance..."
                              required><?= htmlspecialchars($formData['descripcion']) ?></textarea>
                    <?php if (isset($errors['descripcion'])): ?>
                        <span class="error-message"><?= $errors['descripcion'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="jefe_laboratorio_id">Jefe de Laboratorio Asignado</label>
                        <select id="jefe_laboratorio_id" name="jefe_laboratorio_id" class="form-control">
                            <option value="">Seleccionar (Opcional)</option>
                            <?php foreach ($jefesLaboratorio as $jefe): ?>
                                <option value="<?= $jefe['id'] ?>" 
                                        <?= $formData['jefe_laboratorio_id'] == $jefe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jefe['nombre']) ?> - <?= htmlspecialchars($jefe['laboratorio']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($proyecto['jefe_laboratorio_id'] != $formData['jefe_laboratorio_id']): ?>
                            <small class="form-help text-warning">
                                <i class="fas fa-info-circle"></i> El cambio de jefe de laboratorio será notificado
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="laboratorio_asignado">Laboratorio/Área</label>
                        <input type="text" 
                               id="laboratorio_asignado" 
                               name="laboratorio_asignado" 
                               value="<?= htmlspecialchars($formData['laboratorio_asignado'] ?? '') ?>"
                               class="form-control"
                               placeholder="Ej: Laboratorio de Sistemas">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="fas fa-tasks"></i> Detalles del Proyecto</h2>
                
                <div class="form-group">
                    <label for="tipo_actividades" class="required">Tipo de Actividades</label>
                    <textarea id="tipo_actividades" 
                              name="tipo_actividades" 
                              rows="3"
                              class="form-control <?= isset($errors['tipo_actividades']) ? 'error' : '' ?>"
                              placeholder="Describe las actividades que realizarán los estudiantes..."
                              required><?= htmlspecialchars($formData['tipo_actividades']) ?></textarea>
                    <?php if (isset($errors['tipo_actividades'])): ?>
                        <span class="error-message"><?= $errors['tipo_actividades'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="objetivos" class="required">Objetivos del Proyecto</label>
                    <textarea id="objetivos" 
                              name="objetivos" 
                              rows="3"
                              class="form-control <?= isset($errors['objetivos']) ? 'error' : '' ?>"
                              placeholder="Define los objetivos que se esperan alcanzar..."
                              required><?= htmlspecialchars($formData['objetivos']) ?></textarea>
                    <?php if (isset($errors['objetivos'])): ?>
                        <span class="error-message"><?= $errors['objetivos'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="requisitos">Requisitos y Competencias</label>
                    <textarea id="requisitos" 
                              name="requisitos" 
                              rows="3"
                              class="form-control"
                              placeholder="Especifica los requisitos o competencias necesarias para participar..."><?= htmlspecialchars($formData['requisitos'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h2><i class="fas fa-cog"></i> Configuración</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="horas_requeridas" class="required">Horas Requeridas</label>
                        <input type="number" 
                               id="horas_requeridas" 
                               name="horas_requeridas" 
                               value="<?= htmlspecialchars($formData['horas_requeridas']) ?>"
                               class="form-control <?= isset($errors['horas_requeridas']) ? 'error' : '' ?>"
                               min="1"
                               max="1000"
                               required>
                        <?php if (isset($errors['horas_requeridas'])): ?>
                            <span class="error-message"><?= $errors['horas_requeridas'] ?></span>
                        <?php endif; ?>
                        <?php if ($proyecto['horas_requeridas'] != $formData['horas_requeridas']): ?>
                            <small class="form-help text-warning">
                                <i class="fas fa-info-circle"></i> El cambio de horas será notificado a los estudiantes
                            </small>
                        <?php else: ?>
                            <small class="form-help">Horas totales que debe cumplir cada estudiante</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cupo_disponible" class="required">Cupo Disponible</label>
                        <input type="number" 
                               id="cupo_disponible" 
                               name="cupo_disponible" 
                               value="<?= htmlspecialchars($formData['cupo_disponible']) ?>"
                               class="form-control <?= isset($errors['cupo_disponible']) ? 'error' : '' ?>"
                               min="<?= $proyecto['cupo_ocupado'] ?>"
                               max="20"
                               required>
                        <?php if (isset($errors['cupo_disponible'])): ?>
                            <span class="error-message"><?= $errors['cupo_disponible'] ?></span>
                        <?php endif; ?>
                        <small class="form-help">
                            Ocupado: <?= $proyecto['cupo_ocupado'] ?> | 
                            Disponible: <?= max(0, $formData['cupo_disponible'] - $proyecto['cupo_ocupado']) ?>
                        </small>
                    </div>
                </div>

                <div class="project-status">
                    <div class="status-item">
                        <span class="status-label">Estado:</span>
                        <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-secondary' ?>">
                            <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Creado:</span>
                        <span><?= formatDateTime($proyecto['created_at']) ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Última modificación:</span>
                        <span><?= formatDateTime($proyecto['updated_at']) ?></span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
                <a href="/modules/departamento/proyectos.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-llenar laboratorio asignado cuando se selecciona jefe de laboratorio
document.getElementById('jefe_laboratorio_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const laboratorioField = document.getElementById('laboratorio_asignado');
    
    if (selectedOption.value) {
        const laboratorioText = selectedOption.text.split(' - ')[1];
        if (laboratorioText && !laboratorioField.value) {
            laboratorioField.value = laboratorioText;
        }
    }
});

// Actualizar información de cupo en tiempo real
document.getElementById('cupo_disponible').addEventListener('input', function() {
    const cupoOcupado = <?= $proyecto['cupo_ocupado'] ?>;
    const cupoTotal = parseInt(this.value) || 0;
    const cupoDisponible = Math.max(0, cupoTotal - cupoOcupado);
    
    const helpText = this.parentNode.querySelector('.form-help');
    if (helpText && !helpText.classList.contains('error-message')) {
        helpText.innerHTML = `Ocupado: ${cupoOcupado} | Disponible: ${cupoDisponible}`;
    }
});

// Confirmación para cambios importantes
document.querySelector('.project-form').addEventListener('submit', function(e) {
    const horasOriginales = <?= $proyecto['horas_requeridas'] ?>;
    const horasNuevas = parseInt(document.getElementById('horas_requeridas').value);
    const estudiantesAsignados = <?= $estudiantesAsignados ?>;
    
    if (estudiantesAsignados > 0 && horasOriginales !== horasNuevas) {
        if (!confirm(`¿Estás seguro de cambiar las horas requeridas de ${horasOriginales} a ${horasNuevas}? Los ${estudiantesAsignados} estudiante(s) asignado(s) serán notificados.`)) {
            e.preventDefault();
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>