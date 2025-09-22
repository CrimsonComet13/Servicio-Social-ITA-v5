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
            redirectTo('/modules/departamento/proyectos.php');
            
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

<div class="dashboard-content">
    <div class="dashboard-header">
        <div>
            <h1>Crear Nuevo Proyecto</h1>
            <p>Registra un nuevo proyecto de servicio social para tu departamento</p>
        </div>
        <a href="/servicio_social_ita/modules/departamento/proyectos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Proyectos
        </a>
    </div>

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
                           value="<?= htmlspecialchars($formData['nombre_proyecto'] ?? '') ?>"
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
                              required><?= htmlspecialchars($formData['descripcion'] ?? '') ?></textarea>
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
                                        <?= ($formData['jefe_laboratorio_id'] ?? '') == $jefe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jefe['nombre']) ?> - <?= htmlspecialchars($jefe['laboratorio']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                              required><?= htmlspecialchars($formData['tipo_actividades'] ?? '') ?></textarea>
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
                              required><?= htmlspecialchars($formData['objetivos'] ?? '') ?></textarea>
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
                               value="<?= htmlspecialchars($formData['horas_requeridas'] ?? '500') ?>"
                               class="form-control <?= isset($errors['horas_requeridas']) ? 'error' : '' ?>"
                               min="1"
                               max="1000"
                               required>
                        <?php if (isset($errors['horas_requeridas'])): ?>
                            <span class="error-message"><?= $errors['horas_requeridas'] ?></span>
                        <?php endif; ?>
                        <small class="form-help">Horas totales que debe cumplir cada estudiante</small>
                    </div>

                    <div class="form-group">
                        <label for="cupo_disponible" class="required">Cupo Disponible</label>
                        <input type="number" 
                               id="cupo_disponible" 
                               name="cupo_disponible" 
                               value="<?= htmlspecialchars($formData['cupo_disponible'] ?? '3') ?>"
                               class="form-control <?= isset($errors['cupo_disponible']) ? 'error' : '' ?>"
                               min="1"
                               max="20"
                               required>
                        <?php if (isset($errors['cupo_disponible'])): ?>
                            <span class="error-message"><?= $errors['cupo_disponible'] ?></span>
                        <?php endif; ?>
                        <small class="form-help">Número máximo de estudiantes que pueden participar</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Crear Proyecto
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
</script>

<?php include '../../includes/footer.php'; ?>