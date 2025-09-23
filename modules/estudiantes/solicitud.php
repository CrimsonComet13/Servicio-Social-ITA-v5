<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener datos del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

// Verificar si ya tiene una solicitud activa
$solicitudActiva = $db->fetch("
    SELECT * FROM solicitudes_servicio 
    WHERE estudiante_id = :estudiante_id 
    AND estado IN ('pendiente', 'aprobada', 'en_proceso')
    LIMIT 1
", ['estudiante_id' => $estudiante['id']]);

// Obtener proyectos disponibles
$proyectos = $db->fetchAll("
    SELECT p.*, jd.nombre as jefe_nombre, jd.departamento,
           jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM proyectos_laboratorio p
    JOIN jefes_departamento jd ON p.jefe_departamento_id = jd.id
    LEFT JOIN jefes_laboratorio jl ON p.jefe_laboratorio_id = jl.id
    WHERE p.activo = TRUE AND p.cupo_disponible > p.cupo_ocupado
    ORDER BY p.nombre_proyecto
");

$errors = [];
$success = '';

// Procesar formulario de solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$solicitudActiva) {
    $proyectoId = $_POST['proyecto_id'] ?? 0;
    $fechaInicio = $_POST['fecha_inicio'] ?? '';
    $fechaFin = $_POST['fecha_fin'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    
    // Validaciones
    if (empty($proyectoId)) {
        $errors['proyecto_id'] = 'Debe seleccionar un proyecto';
    }
    
    if (empty($fechaInicio)) {
        $errors['fecha_inicio'] = 'La fecha de inicio es obligatoria';
    } elseif (strtotime($fechaInicio) < strtotime('today')) {
        $errors['fecha_inicio'] = 'La fecha de inicio no puede ser anterior a hoy';
    }
    
    if (empty($fechaFin)) {
        $errors['fecha_fin'] = 'La fecha de fin es obligatoria';
    } elseif (strtotime($fechaFin) <= strtotime($fechaInicio)) {
        $errors['fecha_fin'] = 'La fecha de fin debe ser posterior a la fecha de inicio';
    }
    
    // Validar duración máxima
    $duracionMeses = getConfig('duracion_maxima_meses', 12);
    $fechaInicioObj = new DateTime($fechaInicio);
    $fechaFinObj = new DateTime($fechaFin);
    $diferencia = $fechaInicioObj->diff($fechaFinObj);
    $meses = $diferencia->y * 12 + $diferencia->m;
    
    if ($meses > $duracionMeses) {
        $errors['fecha_fin'] = "La duración máxima permitida es de $duracionMeses meses";
    }
    
    if (empty($motivo)) {
        $errors['motivo'] = 'El motivo de la solicitud es obligatorio';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Obtener información del proyecto
            $proyecto = $db->fetch("
                SELECT jefe_departamento_id, jefe_laboratorio_id 
                FROM proyectos_laboratorio 
                WHERE id = ?
            ", [$proyectoId]);
            
            // Crear solicitud
            $solicitudId = $db->insert('solicitudes_servicio', [
                'estudiante_id' => $estudiante['id'],
                'proyecto_id' => $proyectoId,
                'jefe_departamento_id' => $proyecto['jefe_departamento_id'],
                'jefe_laboratorio_id' => $proyecto['jefe_laboratorio_id'],
                'fecha_solicitud' => date('Y-m-d'),
                'fecha_inicio_propuesta' => $fechaInicio,
                'fecha_fin_propuesta' => $fechaFin,
                'motivo_solicitud' => $motivo,
                'estado' => 'pendiente'
            ]);
            
            // Actualizar estado del estudiante
            $db->update('estudiantes', [
                'estado_servicio' => 'solicitud_pendiente'
            ], 'id = :id', ['id' => $estudiante['id']]);
            
            // Notificar al jefe de departamento
            createNotification(
                $proyecto['jefe_departamento_id'],
                'Nueva solicitud de servicio social',
                "El estudiante {$estudiante['nombre']} ha solicitado realizar servicio social en uno de sus proyectos.",
                'info',
                "/modules/departamento/solicitudes.php"
            );
            
            $db->commit();
            
            $success = 'Solicitud enviada correctamente. Será revisada por el jefe de departamento.';
            flashMessage($success, 'success');
            redirectTo('/dashboard/estudiante.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $errors['general'] = 'Error al enviar la solicitud: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Solicitud de Servicio Social - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1 class="page-title">Solicitud de Servicio Social</h1>
        <p class="page-subtitle">Completa el formulario para solicitar tu servicio social</p>
    </div>

    <?php if ($solicitudActiva): ?>
        <div class="status-card status-info">
            <div class="status-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="status-content">
                <h3>Ya tienes una solicitud activa</h3>
                <p>Actualmente tienes una solicitud de servicio social en estado: 
                    <span class="status-badge status-<?= $solicitudActiva['estado'] ?>">
                        <?= getEstadoText($solicitudActiva['estado']) ?>
                    </span>
                </p>
                <p>No puedes crear una nueva solicitud hasta que la actual sea procesada.</p>
            </div>
            <div class="status-actions">
                
                <a href="/dashboard/estudiante.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <p><?= $success ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <p><?= $errors['general'] ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <div class="form-container">
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="fas fa-file-alt"></i>
                        Formulario de Solicitud
                    </h2>
                    <p class="form-description">Completa toda la información requerida para tu solicitud</p>
                </div>

                <form method="POST" class="modern-form">
                    <div class="form-grid">
                        <div class="form-group <?= isset($errors['proyecto_id']) ? 'has-error' : '' ?>">
                            <label for="proyecto_id" class="form-label">
                                <i class="fas fa-project-diagram"></i>
                                Proyecto *
                            </label>
                            <div class="select-wrapper">
                                <select id="proyecto_id" name="proyecto_id" class="form-select" required>
                                    <option value="">Selecciona un proyecto</option>
                                    <?php foreach ($proyectos as $proyecto): ?>
                                        <option value="<?= $proyecto['id'] ?>" <?= isset($_POST['proyecto_id']) && $_POST['proyecto_id'] == $proyecto['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($proyecto['nombre_proyecto']) ?> - 
                                            <?= htmlspecialchars($proyecto['laboratorio'] ?? 'Sin laboratorio asignado') ?> 
                                            (Cupo: <?= $proyecto['cupo_disponible'] - $proyecto['cupo_ocupado'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <?php if (isset($errors['proyecto_id'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= $errors['proyecto_id'] ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group <?= isset($errors['fecha_inicio']) ? 'has-error' : '' ?>">
                            <label for="fecha_inicio" class="form-label">
                                <i class="fas fa-calendar-plus"></i>
                                Fecha de Inicio *
                            </label>
                            <div class="input-icon">
                                <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>" 
                                       min="<?= date('Y-m-d') ?>" class="form-input" required>
                                <i class="fas fa-calendar"></i>
                            </div>
                            <?php if (isset($errors['fecha_inicio'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= $errors['fecha_inicio'] ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group <?= isset($errors['fecha_fin']) ? 'has-error' : '' ?>">
                            <label for="fecha_fin" class="form-label">
                                <i class="fas fa-calendar-check"></i>
                                Fecha de Finalización *
                            </label>
                            <div class="input-icon">
                                <input type="date" id="fecha_fin" name="fecha_fin" 
                                       value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>" 
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-input" required>
                                <i class="fas fa-calendar"></i>
                            </div>
                            <?php if (isset($errors['fecha_fin'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= $errors['fecha_fin'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group <?= isset($errors['motivo']) ? 'has-error' : '' ?>">
                        <label for="motivo" class="form-label">
                            <i class="fas fa-comment-dots"></i>
                            Motivo de la Solicitud *
                        </label>
                        <textarea id="motivo" name="motivo" rows="5" 
                                  placeholder="Explica por qué estás interesado en este proyecto y cómo contribuirás..." 
                                  class="form-textarea" required><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea>
                        <div class="char-counter" id="motivo-counter">0 caracteres</div>
                        <?php if (isset($errors['motivo'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= $errors['motivo'] ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Solicitud
                        </button>
                        <a href="/servicio_social_ita/dashboard/estudiante.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Información del estudiante -->
            <div class="info-card">
                <div class="info-card-header">
                    <h3>
                        <i class="fas fa-user-graduate"></i>
                        Información del Estudiante
                    </h3>
                    <div class="status-badge status-active">
                        Activo
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user"></i>
                            Nombre:
                        </div>
                        <div class="info-value"><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-id-card"></i>
                            Número de Control:
                        </div>
                        <div class="info-value"><?= htmlspecialchars($estudiante['numero_control']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-graduation-cap"></i>
                            Carrera:
                        </div>
                        <div class="info-value"><?= htmlspecialchars($estudiante['carrera']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-layer-group"></i>
                            Semestre:
                        </div>
                        <div class="info-value"><?= htmlspecialchars($estudiante['semestre']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-award"></i>
                            Créditos Cursados:
                        </div>
                        <div class="info-value"><?= htmlspecialchars($estudiante['creditos_cursados']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
:root {
    --primary: #6366f1;
    --primary-light: #8b8cf7;
    --primary-dark: #4f46e5;
    --secondary: #1f2937;
    --success: #10b981;
    --warning: #f59e0b;
    --info: #3b82f6;
    --danger: #ef4444;
    --bg-dark: #0f1419;
    --bg-darker: #1a202c;
    --bg-light: #f8fafc;
    --bg-white: #ffffff;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --border: #e5e7eb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-content {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.dashboard-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 1.125rem;
}

/* Status Card */
.status-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.status-info {
    border-left: 4px solid var(--info);
}

.status-icon {
    font-size: 2rem;
    color: var(--info);
}

.status-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.status-content p {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-pendiente {
    background: rgba(247, 144, 9, 0.1);
    color: #f79009;
}

.status-aprobada {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-en_proceso {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: #065f46;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #7f1d1d;
}

.alert-icon {
    font-size: 1.5rem;
}

.alert-content p {
    font-weight: 500;
}

/* Form Section */
.form-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.form-container {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 2rem;
}

.form-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.form-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.form-description {
    color: var(--text-secondary);
}

.modern-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.select-wrapper {
    position: relative;
}

.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    background: var(--bg-white);
    appearance: none;
    transition: var(--transition);
}

.select-wrapper i {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--text-secondary);
}

.form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-icon {
    position: relative;
}

.input-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    resize: vertical;
    min-height: 120px;
    transition: var(--transition);
}

.form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.char-counter {
    font-size: 0.875rem;
    color: var(--text-secondary);
    text-align: right;
}

.has-error .form-input,
.has-error .form-select,
.has-error .form-textarea {
    border-color: var(--danger);
}

.has-error .form-input:focus,
.has-error .form-select:focus,
.has-error .form-textarea:focus {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-size: 0.875rem;
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 1rem;
    transition: var(--transition);
    text-decoration: none;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
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
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.125rem;
}

/* Info Card */
.info-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    height: fit-content;
}

.info-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.info-card-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.info-value {
    font-weight: 500;
    color: var(--text-primary);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .form-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-content {
        padding: 1rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .status-card {
        flex-direction: column;
        text-align: center;
    }
    
    .status-actions {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación de fechas en tiempo real
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    
    if (fechaInicio && fechaFin) {
        fechaInicio.addEventListener('change', function() {
            if (this.value) {
                const minDate = new Date(this.value);
                minDate.setDate(minDate.getDate() + 1);
                fechaFin.min = minDate.toISOString().split('T')[0];
                
                if (fechaFin.value && fechaFin.value <= this.value) {
                    fechaFin.value = '';
                }
            }
        });
    }
    
    // Contador de caracteres para el motivo
    const motivoTextarea = document.getElementById('motivo');
    const motivoCounter = document.getElementById('motivo-counter');
    
    if (motivoTextarea && motivoCounter) {
        motivoTextarea.addEventListener('input', function() {
            const length = this.value.length;
            motivoCounter.textContent = `${length} caracteres`;
            
            if (length > 0) {
                motivoCounter.style.color = 'var(--success)';
            } else {
                motivoCounter.style.color = 'var(--text-secondary)';
            }
        });
    }
    
    // Mejorar la experiencia de selección de proyectos
    const proyectoSelect = document.getElementById('proyecto_id');
    
    if (proyectoSelect) {
        proyectoSelect.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        proyectoSelect.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>