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
        <h1>Solicitud de Servicio Social</h1>
        <p>Completa el formulario para solicitar tu servicio social</p>
    </div>

    <?php if ($solicitudActiva): ?>
        <div class="alert alert-info">
            <h3>Ya tienes una solicitud activa</h3>
            <p>Actualmente tienes una solicitud de servicio social en estado: 
                <strong><?= getEstadoText($solicitudActiva['estado']) ?></strong>
            </p>
            <p>No puedes crear una nueva solicitud hasta que la actual sea procesada.</p>
            <div class="alert-actions">
                <a href="/modules/estudiantes/solicitud.php?id=<?= $solicitudActiva['id'] ?>" class="btn btn-info">
                    Ver Solicitud Actual
                </a>
                <a href="/dashboard/estudiante.php" class="btn btn-secondary">
                    Volver al Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?= $errors['general'] ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" class="form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="proyecto_id">Proyecto *</label>
                        <select id="proyecto_id" name="proyecto_id" required>
                            <option value="">Selecciona un proyecto</option>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <option value="<?= $proyecto['id'] ?>" <?= isset($_POST['proyecto_id']) && $_POST['proyecto_id'] == $proyecto['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proyecto['nombre_proyecto']) ?> - 
                                    <?= htmlspecialchars($proyecto['laboratorio'] ?? 'Sin laboratorio asignado') ?> 
                                    (Cupo: <?= $proyecto['cupo_disponible'] - $proyecto['cupo_ocupado'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['proyecto_id'])): ?>
                            <span class="error"><?= $errors['proyecto_id'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de Inicio *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" 
                               value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '') ?>" 
                               min="<?= date('Y-m-d') ?>" required>
                        <?php if (isset($errors['fecha_inicio'])): ?>
                            <span class="error"><?= $errors['fecha_inicio'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha de Finalización *</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" 
                               value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '') ?>" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        <?php if (isset($errors['fecha_fin'])): ?>
                            <span class="error"><?= $errors['fecha_fin'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="motivo">Motivo de la Solicitud *</label>
                    <textarea id="motivo" name="motivo" rows="5" 
                              placeholder="Explica por qué estás interesado en este proyecto y cómo contribuirás..." 
                              required><?= htmlspecialchars($_POST['motivo'] ?? '') ?></textarea>
                    <?php if (isset($errors['motivo'])): ?>
                        <span class="error"><?= $errors['motivo'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
                    <a href="/dashboard/estudiante.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Información del estudiante -->
        <div class="info-card">
            <h3>Información del Estudiante</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Nombre:</strong> <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?>
                </div>
                <div class="info-item">
                    <strong>Número de Control:</strong> <?= htmlspecialchars($estudiante['numero_control']) ?>
                </div>
                <div class="info-item">
                    <strong>Carrera:</strong> <?= htmlspecialchars($estudiante['carrera']) ?>
                </div>
                <div class="info-item">
                    <strong>Semestre:</strong> <?= htmlspecialchars($estudiante['semestre']) ?>
                </div>
                <div class="info-item">
                    <strong>Créditos Cursados:</strong> <?= htmlspecialchars($estudiante['creditos_cursados']) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.form-container {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 500;
    color: var(--text-color);
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(29, 185, 84, 0.1);
}

.form-group .error {
    color: var(--error-color);
    font-size: 0.875rem;
}

.form-actions {
    margin-top: 1.5rem;
    display: flex;
    gap: 1rem;
}

.info-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
}

.info-card h3 {
    margin: 0 0 1rem 0;
    color: var(--secondary-color);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.info-item:last-child {
    border-bottom: none;
}

.alert-actions {
    margin-top: 1rem;
    display: flex;
    gap: 1rem;
}
</style>

<script>
// Validación de fechas en tiempo real
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php include '../../includes/footer.php'; ?>