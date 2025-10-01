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
    SELECT p.*, jl.nombre as jefe_lab_nombre, jl.laboratorio as laboratorio_real,
           jl.telefono as jefe_lab_telefono, jl.extension as jefe_lab_extension,
           u.email as jefe_lab_email
    FROM proyectos_laboratorio p
    LEFT JOIN jefes_laboratorio jl ON p.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u ON jl.usuario_id = u.id
    WHERE p.id = :id AND p.jefe_departamento_id = :jefe_id
", ['id' => $projectId, 'jefe_id' => $jefeId]);

if (!$proyecto) {
    flashMessage('Proyecto no encontrado', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

// Obtener estadísticas del proyecto
$estadisticas = $db->fetch("
    SELECT 
        COUNT(s.id) as total_solicitudes,
        COUNT(CASE WHEN s.estado = 'pendiente' THEN 1 END) as solicitudes_pendientes,
        COUNT(CASE WHEN s.estado = 'aprobada' THEN 1 END) as solicitudes_aprobadas,
        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as estudiantes_activos,
        COUNT(CASE WHEN s.estado = 'concluida' THEN 1 END) as servicios_concluidos,
        COUNT(CASE WHEN s.estado = 'rechazada' THEN 1 END) as solicitudes_rechazadas,
        AVG(CASE WHEN s.estado = 'en_proceso' THEN e.horas_completadas END) as promedio_horas,
        SUM(CASE WHEN s.estado = 'concluida' THEN e.horas_completadas ELSE 0 END) as total_horas_completadas
    FROM solicitudes_servicio s
    LEFT JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.proyecto_id = :proyecto_id
", ['proyecto_id' => $projectId]);

// Obtener estudiantes del proyecto
$estudiantes = $db->fetchAll("
    SELECT s.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.numero_control, 
           e.carrera, e.telefono, e.horas_completadas, s.fecha_solicitud, s.fecha_inicio_propuesta, 
           s.fecha_fin_propuesta,
           (SELECT COUNT(*) FROM reportes_bimestrales rb WHERE rb.solicitud_id = s.id) as reportes_entregados
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.proyecto_id = :proyecto_id
    ORDER BY 
        CASE s.estado 
            WHEN 'en_proceso' THEN 1
            WHEN 'pendiente' THEN 2
            WHEN 'aprobada' THEN 3
            WHEN 'concluida' THEN 4
            ELSE 5
        END,
        s.fecha_solicitud DESC
", ['proyecto_id' => $projectId]);

// Obtener historial reciente del proyecto

$historial = $db->fetchAll("
    SELECT la.*, u.email, u.tipo_usuario,
           CASE 
               WHEN u.tipo_usuario = 'estudiante' THEN CONCAT(e.nombre, ' ', e.apellido_paterno)
               WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre
               WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
               ELSE u.email
           END as usuario_nombre
    FROM log_actividades la
    JOIN usuarios u ON la.usuario_id = u.id
    LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
    LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id AND u.tipo_usuario = 'jefe_departamento'
    LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id AND u.tipo_usuario = 'jefe_laboratorio'
    WHERE la.modulo = 'proyectos' AND la.registro_afectado_id = :proyecto_id
    ORDER BY la.created_at DESC
    LIMIT 10
", ['proyecto_id' => $projectId]);

$pageTitle = "Detalle del Proyecto - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <div>
            <h1><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h1>
            <p>Información completa del proyecto de servicio social</p>
        </div>
        <div class="header-actions">
            <a href="/modules/departamento/proyecto-editar.php?id=<?= $projectId ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Editar Proyecto
            </a>
            <?php if ($proyecto['activo']): ?>
                <a href="/modules/departamento/proyecto-desactivar.php?id=<?= $projectId ?>" 
                   class="btn btn-secondary" 
                   onclick="return confirm('¿Desactivar este proyecto? Las solicitudes pendientes serán rechazadas automáticamente.')">
                    <i class="fas fa-pause"></i> Desactivar
                </a>
            <?php else: ?>
                <a href="/modules/departamento/proyecto-activar.php?id=<?= $projectId ?>" class="btn btn-success">
                    <i class="fas fa-play"></i> Activar
                </a>
            <?php endif; ?>
            <a href="/modules/departamento/proyectos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Proyectos
            </a>
        </div>
    </div>

    <!-- Estado y estadísticas -->
    <div class="project-overview">
        <div class="status-card">
            <div class="status-header">
                <h3>Estado del Proyecto</h3>
                <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-secondary' ?>">
                    <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
            <div class="status-grid">
                <div class="status-item">
                    <span class="status-label">Cupo Total</span>
                    <span class="status-value"><?= $proyecto['cupo_disponible'] ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Cupo Ocupado</span>
                    <span class="status-value"><?= $proyecto['cupo_ocupado'] ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Cupo Disponible</span>
                    <span class="status-value"><?= max(0, $proyecto['cupo_disponible'] - $proyecto['cupo_ocupado']) ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Horas Requeridas</span>
                    <span class="status-value"><?= $proyecto['horas_requeridas'] ?></span>
                </div>
            </div>
            
            <!-- Barra de progreso del cupo -->
            <div class="cupo-progress">
                <div class="progress-header">
                    <span>Ocupación del Cupo</span>
                    <span><?= $proyecto['cupo_ocupado'] ?>/<?= $proyecto['cupo_disponible'] ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $proyecto['cupo_disponible'] > 0 ? ($proyecto['cupo_ocupado'] / $proyecto['cupo_disponible']) * 100 : 0 ?>%"></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $estadisticas['total_solicitudes'] ?></span>
                    <span class="stat-label">Total Solicitudes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $estadisticas['solicitudes_pendientes'] ?></span>
                    <span class="stat-label">Pendientes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $estadisticas['estudiantes_activos'] ?></span>
                    <span class="stat-label">Activos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $estadisticas['servicios_concluidos'] ?></span>
                    <span class="stat-label">Concluidos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Información del proyecto -->
    <div class="project-details">
        <div class="detail-section">
            <h2><i class="fas fa-info-circle"></i> Información General</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Descripción</label>
                    <p><?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?></p>
                </div>
                <div class="detail-item">
                    <label>Laboratorio Asignado</label>
                    <p><?= htmlspecialchars($proyecto['laboratorio_asignado'] ?? 'No especificado') ?></p>
                </div>
                <?php if ($proyecto['jefe_lab_nombre']): ?>
                <div class="detail-item">
                    <label>Jefe de Laboratorio</label>
                    <div class="contact-info">
                        <p><strong><?= htmlspecialchars($proyecto['jefe_lab_nombre']) ?></strong></p>
                        <?php if ($proyecto['jefe_lab_email']): ?>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($proyecto['jefe_lab_email']) ?></p>
                        <?php endif; ?>
                        <?php if ($proyecto['jefe_lab_telefono']): ?>
                            <p><i class="fas fa-phone"></i> <?= htmlspecialchars($proyecto['jefe_lab_telefono']) ?></p>
                        <?php endif; ?>
                        <?php if ($proyecto['jefe_lab_extension']): ?>
                            <p><i class="fas fa-phone-square"></i> Ext. <?= htmlspecialchars($proyecto['jefe_lab_extension']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <label>Fecha de Creación</label>
                    <p><?= formatDateTime($proyecto['created_at']) ?></p>
                </div>
                <div class="detail-item">
                    <label>Última Actualización</label>
                    <p><?= formatDateTime($proyecto['updated_at']) ?></p>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h2><i class="fas fa-tasks"></i> Actividades y Objetivos</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Tipo de Actividades</label>
                    <p><?= nl2br(htmlspecialchars($proyecto['tipo_actividades'])) ?></p>
                </div>
                <div class="detail-item">
                    <label>Objetivos</label>
                    <p><?= nl2br(htmlspecialchars($proyecto['objetivos'])) ?></p>
                </div>
                <?php if ($proyecto['requisitos']): ?>
                <div class="detail-item">
                    <label>Requisitos</label>
                    <p><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lista de estudiantes -->
    <div class="students-section">
        <div class="section-header">
            <h2>Estudiantes del Proyecto (<?= count($estudiantes) ?>)</h2>
            <?php if ($estadisticas['solicitudes_pendientes'] > 0): ?>
                <a href="/modules/departamento/solicitudes.php?proyecto=<?= $projectId ?>" class="btn btn-warning">
                    <i class="fas fa-clipboard-check"></i> Ver Solicitudes Pendientes (<?= $estadisticas['solicitudes_pendientes'] ?>)
                </a>
            <?php endif; ?>
        </div>

        <?php if ($estudiantes): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>No. Control</th>
                            <th>Carrera</th>
                            <th>Estado</th>
                            <th>Progreso</th>
                            <th>Periodo</th>
                            <th>Reportes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td>
                                <div class="student-info">
                                    <strong><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></strong>
                                    <?php if ($estudiante['telefono']): ?>
                                        <br><small><i class="fas fa-phone"></i> <?= htmlspecialchars($estudiante['telefono']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($estudiante['numero_control']) ?></td>
                            <td><?= htmlspecialchars($estudiante['carrera']) ?></td>
                            <td>
                                <span class="badge <?= getEstadoBadgeClass($estudiante['estado']) ?>">
                                    <?= getEstadoText($estudiante['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($estudiante['estado'] === 'en_proceso'): ?>
                                    <div class="progress-info">
                                        <span><?= $estudiante['horas_completadas'] ?>/<?= $proyecto['horas_requeridas'] ?> hrs</span>
                                        <div class="progress-bar small">
                                            <div class="progress-fill" style="width: <?= min(100, ($estudiante['horas_completadas'] / $proyecto['horas_requeridas']) * 100) ?>%"></div>
                                        </div>
                                        <small><?= number_format(($estudiante['horas_completadas'] / $proyecto['horas_requeridas']) * 100, 1) ?>%</small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($estudiante['fecha_inicio_propuesta']): ?>
                                    <div class="date-range">
                                        <span><?= formatDate($estudiante['fecha_inicio_propuesta']) ?></span>
                                        <small class="text-muted">a <?= formatDate($estudiante['fecha_fin_propuesta']) ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sin definir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($estudiante['estado'] === 'en_proceso'): ?>
                                    <span class="badge badge-info"><?= $estudiante['reportes_entregados'] ?>/3</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/modules/departamento/estudiante-detalle.php?id=<?= $estudiante['estudiante_id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($estudiante['estado'] === 'pendiente'): ?>
                                        <a href="/modules/departamento/solicitud-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-clipboard-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>No hay estudiantes asignados</h3>
                <p>Este proyecto aún no tiene estudiantes registrados</p>
                <?php if ($proyecto['activo']): ?>
                    <p class="text-muted">Los estudiantes pueden solicitar unirse a este proyecto desde su panel</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Historial de actividades -->
    <?php if ($historial): ?>
    <div class="history-section">
        <div class="section-header">
            <h2>Historial Reciente</h2>
        </div>
        <div class="timeline">
            <?php foreach ($historial as $item): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-action"><?= ucfirst($item['accion']) ?></span>
                        <span class="timeline-date"><?= timeAgo($item['created_at']) ?></span>
                    </div>
                    <div class="timeline-body">
                        <strong><?= htmlspecialchars($item['usuario_nombre']) ?></strong>
                        realizó la acción: <?= htmlspecialchars($item['accion']) ?>
                        <?php if ($item['detalles']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($item['detalles']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>