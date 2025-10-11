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

// Calcular porcentaje de ocupación
$porcentajeOcupacion = $proyecto['cupo_disponible'] > 0 
    ? round(($proyecto['cupo_ocupado'] / $proyecto['cupo_disponible']) * 100, 1) 
    : 0;

$cuposDisponibles = max(0, $proyecto['cupo_disponible'] - $proyecto['cupo_ocupado']);

$pageTitle = "Detalle del Proyecto - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <div class="breadcrumb">
                    <a href="/modules/departamento/proyectos.php">
                        <i class="fas fa-folder"></i> Proyectos
                    </a>
                    <span>/</span>
                    <span>Detalle</span>
                </div>
                <h1 class="welcome-title">
                    <span class="welcome-text"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></span>
                    <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-secondary' ?>">
                        <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </h1>
                <p class="welcome-subtitle"><?= htmlspecialchars($proyecto['laboratorio_asignado'] ?? 'Sin laboratorio asignado') ?></p>
            </div>
            <div class="header-actions">
                <a href="/modules/departamento/proyecto-editar.php?id=<?= $projectId ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> <span>Editar</span>
                </a>
                <?php if ($proyecto['activo']): ?>
                    <a href="/modules/departamento/proyecto-desactivar.php?id=<?= $projectId ?>" 
                       class="btn btn-secondary" 
                       onclick="return confirm('¿Desactivar este proyecto? Las solicitudes pendientes serán rechazadas.')">
                        <i class="fas fa-pause"></i> <span>Desactivar</span>
                    </a>
                <?php else: ?>
                    <a href="/modules/departamento/proyecto-activar.php?id=<?= $projectId ?>" class="btn btn-success">
                        <i class="fas fa-play"></i> <span>Activar</span>
                    </a>
                <?php endif; ?>
                <a href=proyectos.php class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <span>Volver</span>
                </a>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas Principales - HORIZONTAL -->
        <div class="status-cards">
            <div class="card stat-card-primary">
                <div class="card-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $estadisticas['estudiantes_activos'] ?></span>
                        <span class="stat-label">Estudiantes Activos</span>
                    </div>
                </div>
            </div>

            <div class="card stat-card-primary">
                <div class="card-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $estadisticas['solicitudes_pendientes'] ?></span>
                        <span class="stat-label">Solicitudes Pendientes</span>
                    </div>
                </div>
            </div>

            <div class="card stat-card-primary">
                <div class="card-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #27ae60, #229954);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $estadisticas['servicios_concluidos'] ?></span>
                        <span class="stat-label">Servicios Concluidos</span>
                    </div>
                </div>
            </div>

            <div class="card stat-card-primary">
                <div class="card-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $estadisticas['total_solicitudes'] ?></span>
                        <span class="stat-label">Total Solicitudes</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de Cupos y Horas -->
        <div class="info-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Ocupación del Cupo</h3>
                </div>
                <div class="card-body">
                    <div class="cupo-stats">
                        <div class="cupo-item">
                            <span class="cupo-label">Total</span>
                            <span class="cupo-value"><?= $proyecto['cupo_disponible'] ?></span>
                        </div>
                        <div class="cupo-item">
                            <span class="cupo-label">Ocupados</span>
                            <span class="cupo-value" style="color: #3498db;"><?= $proyecto['cupo_ocupado'] ?></span>
                        </div>
                        <div class="cupo-item">
                            <span class="cupo-label">Disponibles</span>
                            <span class="cupo-value" style="color: #27ae60;"><?= $cuposDisponibles ?></span>
                        </div>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $porcentajeOcupacion ?>%;"></div>
                        </div>
                        <p class="progress-text"><?= $porcentajeOcupacion ?>% ocupado</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Estadísticas de Horas</h3>
                </div>
                <div class="card-body">
                    <div class="cupo-stats">
                        <div class="cupo-item">
                            <span class="cupo-label">Requeridas</span>
                            <span class="cupo-value"><?= $proyecto['horas_requeridas'] ?></span>
                        </div>
                        <div class="cupo-item">
                            <span class="cupo-label">Promedio</span>
                            <span class="cupo-value" style="color: #3498db;"><?= $estadisticas['promedio_horas'] ? round($estadisticas['promedio_horas']) : 0 ?></span>
                        </div>
                        <div class="cupo-item">
                            <span class="cupo-label">Completadas</span>
                            <span class="cupo-value" style="color: #27ae60;"><?= $estadisticas['total_horas_completadas'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información Detallada del Proyecto -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Información del Proyecto</h2>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label><i class="fas fa-align-left"></i> Descripción</label>
                        <p><?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?></p>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-flask"></i> Laboratorio</label>
                        <p><?= htmlspecialchars($proyecto['laboratorio_asignado'] ?? 'No especificado') ?></p>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-tasks"></i> Tipo de Actividades</label>
                        <p><?= nl2br(htmlspecialchars($proyecto['tipo_actividades'])) ?></p>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-bullseye"></i> Objetivos</label>
                        <p><?= nl2br(htmlspecialchars($proyecto['objetivos'])) ?></p>
                    </div>
                    <?php if ($proyecto['requisitos']): ?>
                    <div class="detail-item">
                        <label><i class="fas fa-clipboard-check"></i> Requisitos</label>
                        <p><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($proyecto['jefe_lab_nombre']): ?>
                    <div class="detail-item">
                        <label><i class="fas fa-user-tie"></i> Jefe de Laboratorio</label>
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
                </div>
                <div class="detail-footer">
                    <span><i class="fas fa-calendar-plus"></i> Creado: <?= formatDateTime($proyecto['created_at']) ?></span>
                    <span><i class="fas fa-calendar-alt"></i> Actualizado: <?= formatDateTime($proyecto['updated_at']) ?></span>
                </div>
            </div>
        </div>

        <!-- Sección de Estudiantes -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-user-graduate"></i> Estudiantes del Proyecto (<?= count($estudiantes) ?>)</h2>
                <?php if ($estadisticas['solicitudes_pendientes'] > 0): ?>
                    <a href="/modules/departamento/solicitudes.php?proyecto=<?= $projectId ?>" class="btn btn-warning">
                        <i class="fas fa-clipboard-check"></i> Ver Solicitudes (<?= $estadisticas['solicitudes_pendientes'] ?>)
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
                                <td><small><?= htmlspecialchars($estudiante['carrera']) ?></small></td>
                                <td>
                                    <span class="badge <?= getEstadoBadgeClass($estudiante['estado']) ?>">
                                        <?= getEstadoText($estudiante['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($estudiante['estado'] === 'en_proceso'): ?>
                                        <div class="progress-info">
                                            <span class="progress-label"><?= $estudiante['horas_completadas'] ?>/<?= $proyecto['horas_requeridas'] ?> hrs</span>
                                            <div class="progress-bar small">
                                                <div class="progress-fill" style="width: <?= min(100, ($estudiante['horas_completadas'] / $proyecto['horas_requeridas']) * 100) ?>%"></div>
                                            </div>
                                            <small class="progress-percent"><?= number_format(($estudiante['horas_completadas'] / $proyecto['horas_requeridas']) * 100, 1) ?>%</small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($estudiante['fecha_inicio_propuesta']): ?>
                                        <small>
                                            <?= formatDate($estudiante['fecha_inicio_propuesta']) ?><br>
                                            <span class="text-muted">al <?= formatDate($estudiante['fecha_fin_propuesta']) ?></span>
                                        </small>
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
                                        <a href="/servicio_social_ita/modules/departamento/estudiante-detalle.php?id=<?= $estudiante['estudiante_id'] ?>" 
                                           class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($estudiante['estado'] === 'pendiente'): ?>
                                            <a href="/servicio_social_ita//modules/departamento/solicitud-detalle.php?id=<?= $estudiante['id'] ?>" 
                                               class="btn btn-sm btn-warning" title="Revisar solicitud">
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
                        <p class="text-muted">Los estudiantes pueden solicitar unirse desde su panel</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Historial de Actividades -->
        <?php if ($historial): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Historial Reciente</h2>
            </div>
            <div class="card-body">
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
        </div>
        <?php endif; ?>

        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
            <div class="actions-grid">
                <a href="proyecto-editar.php?id=<?= $projectId ?>" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h3>Editar Proyecto</h3>
                    <p>Modificar información y configuración</p>
                </a>

                <?php if ($estadisticas['solicitudes_pendientes'] > 0): ?>
                <a href="solicitudes.php?proyecto=<?= $projectId ?>" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Revisar Solicitudes</h3>
                    <p><?= $estadisticas['solicitudes_pendientes'] ?> solicitudes pendientes</p>
                </a>
                <?php endif; ?>

                <a href="reportes.php?proyecto=<?= $projectId ?>" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Ver Reportes</h3>
                    <p>Estadísticas y análisis del proyecto</p>
                </a>

                <a href="proyectos.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d);">
                        <i class="fas fa-arrow-left"></i>
                    </div>
                    <h3>Volver a Proyectos</h3>
                    <p>Regresar a la lista de proyectos</p>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* ==================== VARIABLES CSS ==================== */
:root {
    /* Colors */
    --primary: #6366f1;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
    
    /* Text Colors */
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --text-muted: #9ca3af;
    
    /* Backgrounds */
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    
    /* Borders */
    --border: #e5e7eb;
    --border-light: #f3f4f6;
    
    /* Shadows */
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    
    /* Border Radius */
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    
    /* Spacing */
    --sidebar-width: 280px;
    --header-height: 70px;
    
    /* Transitions */
    --transition: all 0.3s ease;
}

/* ==================== LAYOUT ==================== */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
}

.dashboard-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* ==================== HEADER ==================== */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section {
    flex: 1;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.breadcrumb a:hover {
    color: var(--primary-light);
}

.welcome-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    line-height: 1.2;
}

.welcome-text {
    flex: 1;
}

.welcome-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* ==================== BUTTONS ==================== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
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
    transform: translateY(-1px);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fb923c);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    min-width: auto;
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

/* ==================== CARDS ==================== */
.card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header h2,
.card-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h2 i,
.card-header h3 i {
    color: var(--primary);
}

.card-body {
    padding: 1.5rem;
}

/* ==================== STATUS CARDS (4 HORIZONTALES) ==================== */
.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card-primary {
    border-left: 4px solid var(--primary);
}

.stat-card-primary .card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    border-bottom: none;
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
    font-weight: 500;
}

/* ==================== INFO GRID ==================== */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.cupo-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 1.25rem;
}

.cupo-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.cupo-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.375rem;
    font-weight: 500;
}

.cupo-value {
    font-size: 1.875rem;
    font-weight: 700;
    color: var(--text-primary);
}

.progress-bar-wrapper {
    margin-top: 1rem;
}

.progress-bar {
    background: var(--bg-gray);
    border-radius: var(--radius);
    height: 10px;
    overflow: hidden;
    margin-bottom: 0.625rem;
}

.progress-bar.small {
    height: 6px;
    margin: 0.25rem 0;
}

.progress-fill {
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    height: 100%;
    border-radius: var(--radius);
    transition: width 0.5s ease;
}

.progress-text {
    text-align: center;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* ==================== DETAIL GRID ==================== */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.detail-item label i {
    color: var(--primary);
    font-size: 0.875rem;
}

.detail-item p {
    color: var(--text-secondary);
    font-size: 0.9375rem;
    line-height: 1.6;
    margin: 0;
}

.contact-info p {
    margin: 0.375rem 0;
    font-size: 0.875rem;
}

.contact-info i {
    width: 18px;
    color: var(--primary);
}

.detail-footer {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.detail-footer span {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

/* ==================== SECTIONS ==================== */
.dashboard-section {
    margin-bottom: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-header h2 i {
    color: var(--primary);
}

/* ==================== TABLES ==================== */
.table-responsive {
    overflow-x: auto;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table thead {
    background: var(--bg-light);
}

.data-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: var(--bg-light);
}

.student-info strong {
    display: block;
    color: var(--text-primary);
    font-size: 0.9375rem;
    margin-bottom: 0.25rem;
}

.student-info small {
    color: var(--text-light);
    font-size: 0.8125rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

/* ==================== PROGRESS INFO ==================== */
.progress-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 120px;
}

.progress-label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-primary);
}

.progress-percent {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-align: center;
}

/* ==================== BADGES ==================== */
.badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.badge-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.badge-secondary {
    background: var(--bg-gray);
    color: var(--text-secondary);
}

.badge-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.badge-warning {
    background: linear-gradient(135deg, var(--warning), #fb923c);
    color: white;
}

.badge-error,
.badge-danger {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

/* ==================== EMPTY STATE ==================== */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-light);
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.4;
    color: var(--text-light);
}

.empty-state h3 {
    color: var(--text-secondary);
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    color: var(--text-light);
    font-size: 0.9375rem;
    margin: 0.5rem 0;
}

/* ==================== TIMELINE ==================== */
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    width: 12px;
    height: 12px;
    background: var(--primary);
    border-radius: 50%;
    border: 3px solid var(--bg-white);
    box-shadow: 0 0 0 2px var(--primary);
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.625rem;
    top: 12px;
    bottom: -12px;
    width: 2px;
    background: var(--border);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.timeline-action {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.timeline-date {
    font-size: 0.8125rem;
    color: var(--text-light);
}

.timeline-body {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.6;
}

.timeline-body strong {
    color: var(--text-primary);
    font-weight: 600;
}

/* ==================== QUICK ACTIONS ==================== */
.quick-actions {
    margin-top: 2rem;
}

.quick-actions h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quick-actions h2 i {
    color: var(--warning);
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
}

.action-card {
    background: var(--bg-white);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: inherit;
    box-shadow: var(--shadow);
    transition: var(--transition);
    text-align: center;
    border: 1px solid var(--border-light);
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.action-card .action-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: white;
    font-size: 1.75rem;
}

.action-card h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.action-card p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .dashboard-container {
        padding: 1rem;
    }
    
    .status-cards {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .welcome-title {
        font-size: 1.5rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .status-cards {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .data-table {
        font-size: 0.8125rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem;
    }
    
    .detail-footer {
        flex-direction: column;
        gap: 0.75rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .welcome-title {
        font-size: 1.25rem;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn span {
        display: none;
    }
    
    .btn {
        padding: 0.625rem 0.875rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>