<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

$jefeLab = $db->fetch("
    SELECT jl.*, u.email 
    FROM jefes_laboratorio jl
    JOIN usuarios u ON jl.usuario_id = u.id
    WHERE jl.usuario_id = ?
", [$usuarioId]);

if (!$jefeLab) {
    flashMessage('Error: Perfil de jefe de laboratorio no encontrado', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
}

$jefeLabId = $jefeLab['id']; // ✅ ID CORRECTO de jefes_laboratorio
$nombreLaboratorio = $jefeLab['laboratorio'] ?? 'Sin asignar'; // ✅ NOMBRE CORRECTO

// Obtener ID del proyecto
$proyectoId = $_GET['id'] ?? null;

if (!$proyectoId) {
    flashMessage('ID de proyecto no válido', 'error');
    redirectTo('/modules/laboratorio/proyectos.php');
}

// Procesar acciones (activar/desactivar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'toggle_estado') {
            $nuevoEstado = $_POST['nuevo_estado'];
            
            $db->update('proyectos_laboratorio', [
                'activo' => $nuevoEstado,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id AND jefe_laboratorio_id = :jefe_id', [
                'id' => $proyectoId,
                'jefe_id' => $jefeLabId
            ]);
            
            $mensaje = $nuevoEstado == 1 ? 'Proyecto activado exitosamente' : 'Proyecto desactivado exitosamente';
            flashMessage($mensaje, 'success');
            redirectTo('/modules/laboratorio/proyecto-detalle.php?id=' . $proyectoId);
        }
    } catch (Exception $e) {
        flashMessage('Error al actualizar el proyecto: ' . $e->getMessage(), 'error');
    }
}

// Obtener información del proyecto
$proyecto = $db->fetch("
    SELECT p.*
    FROM proyectos_laboratorio p
    WHERE p.id = :id AND p.jefe_laboratorio_id = :jefe_id
", [
    'id' => $proyectoId,
    'jefe_id' => $jefeLabId
]);

if (!$proyecto) {
    flashMessage('Proyecto no encontrado', 'error');
    redirectTo('/modules/laboratorio/proyectos.php');
}

// Obtener estadísticas del proyecto
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN s.id END) as solicitudes_pendientes,
        COUNT(DISTINCT CASE WHEN s.estado IN ('aprobada', 'en_proceso') THEN s.id END) as estudiantes_activos,
        COUNT(DISTINCT CASE WHEN s.estado = 'concluida' THEN s.id END) as estudiantes_completados,
        COUNT(DISTINCT CASE WHEN s.estado = 'rechazada' THEN s.id END) as solicitudes_rechazadas,
        COUNT(DISTINCT s.id) as total_solicitudes,
        COALESCE(AVG(CASE WHEN s.estado = 'concluida' THEN e.horas_completadas END), 0) as promedio_horas_completados
    FROM solicitudes_servicio s
    LEFT JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.proyecto_id = :proyecto_id
", ['proyecto_id' => $proyectoId]);

// Calcular porcentaje de ocupación
$porcentajeOcupacion = $proyecto['cupo_disponible'] > 0 
    ? round(($proyecto['cupo_ocupado'] / $proyecto['cupo_disponible']) * 100) 
    : 0;

// Obtener estudiantes activos en el proyecto
$estudiantesActivos = $db->fetchAll("
    SELECT 
        e.*,
        s.id as solicitud_id,
        s.estado as estado_servicio,
        s.fecha_inicio_propuesta,
        s.fecha_fin_propuesta,
        s.fecha_aprobacion,
        COUNT(DISTINCT r.id) as total_reportes,
        COUNT(DISTINCT CASE WHEN r.estado = 'aprobado' THEN r.id END) as reportes_aprobados,
        MAX(r.fecha_entrega) as ultimo_reporte
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    WHERE s.proyecto_id = :proyecto_id 
    AND s.estado IN ('aprobada', 'en_proceso')
    GROUP BY e.id, s.id
    ORDER BY s.fecha_aprobacion DESC
", ['proyecto_id' => $proyectoId]);

// Obtener solicitudes pendientes
$solicitudesPendientes = $db->fetchAll("
    SELECT 
        s.*,
        e.nombre,
        e.apellido_paterno,
        e.apellido_materno,
        e.numero_control,
        e.carrera,
        e.semestre
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.proyecto_id = :proyecto_id 
    AND s.estado = 'pendiente'
    ORDER BY s.fecha_solicitud DESC
", ['proyecto_id' => $proyectoId]);

// Obtener historial de actividades (últimas 10)
$historial = $db->fetchAll("
    SELECT 
        s.id,
        s.estado,
        s.fecha_solicitud,
        s.fecha_aprobacion,
        s.fecha_inicio_propuesta,
        e.nombre,
        e.apellido_paterno,
        e.numero_control
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.proyecto_id = :proyecto_id
    ORDER BY s.fecha_solicitud DESC
    LIMIT 10
", ['proyecto_id' => $proyectoId]);

$pageTitle = "Detalle del Proyecto - " . APP_NAME;
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
                        <i class="fas fa-project-diagram"></i>
                        <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                    </h1>
                    <p class="page-subtitle">
                        <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-error' ?>">
                            <i class="fas fa-<?= $proyecto['activo'] ? 'check-circle' : 'pause-circle' ?>"></i>
                            <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                        <span class="separator">•</span>
                        Laboratorio <?= htmlspecialchars($proyecto['laboratorio_asignado']) ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="proyectos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                    <a href="proyecto-editar.php?id=<?= $proyecto['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Editar Proyecto
                    </a>
                    <?php if ($proyecto['activo']): ?>
                        <button class="btn btn-warning" onclick="toggleEstado(<?= $proyecto['id'] ?>, 0)">
                            <i class="fas fa-pause"></i>
                            Desactivar
                        </button>
                    <?php else: ?>
                        <button class="btn btn-success" onclick="toggleEstado(<?= $proyecto['id'] ?>, 1)">
                            <i class="fas fa-play"></i>
                            Activar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card solicitudes">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Solicitudes</h3>
                    <div class="stat-number"><?= $stats['total_solicitudes'] ?></div>
                    <p class="stat-description">Solicitudes recibidas</p>
                </div>
            </div>

            <div class="stat-card pendientes">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Pendientes</h3>
                    <div class="stat-number"><?= $stats['solicitudes_pendientes'] ?></div>
                    <p class="stat-description">Por revisar</p>
                    <?php if ($stats['solicitudes_pendientes'] > 0): ?>
                    <div class="stat-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Requiere atención</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card activos">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Estudiantes Activos</h3>
                    <div class="stat-number"><?= $stats['estudiantes_activos'] ?></div>
                    <p class="stat-description">Realizando servicio</p>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i>
                        <span><?= $proyecto['cupo_ocupado'] ?>/<?= $proyecto['cupo_disponible'] ?> cupos</span>
                    </div>
                </div>
            </div>

            <div class="stat-card completados">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Completados</h3>
                    <div class="stat-number"><?= $stats['estudiantes_completados'] ?></div>
                    <p class="stat-description">Servicio finalizado</p>
                    <div class="stat-trend">
                        <i class="fas fa-clock"></i>
                        <span><?= round($stats['promedio_horas_completados']) ?> hrs promedio</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Left Column -->
            <div class="content-main">
                <!-- Project Information Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Información del Proyecto
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="info-section">
                            <h3 class="section-subtitle">Descripción</h3>
                            <p class="project-description"><?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?></p>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-book"></i>
                                    Área de Conocimiento
                                </div>
                                <div class="info-value"><?= htmlspecialchars($proyecto['area_conocimiento']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-clock"></i>
                                    Duración Estimada
                                </div>
                                <div class="info-value"><?= htmlspecialchars($proyecto['duracion_estimada']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-laptop-house"></i>
                                    Modalidad
                                </div>
                                <div class="info-value">
                                    <?php
                                    $modalidades = [
                                        'presencial' => 'Presencial',
                                        'remota' => 'Remota',
                                        'hibrida' => 'Híbrida'
                                    ];
                                    echo htmlspecialchars($modalidades[$proyecto['modalidad']] ?? $proyecto['modalidad']);
                                    ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Horario
                                </div>
                                <div class="info-value"><?= htmlspecialchars($proyecto['horario'] ?: 'Flexible') ?></div>
                            </div>
                        </div>

                        <?php if (!empty($proyecto['objetivos'])): ?>
                        <div class="info-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-bullseye"></i>
                                Objetivos
                            </h3>
                            <p class="project-objectives"><?= nl2br(htmlspecialchars($proyecto['objetivos'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($proyecto['requisitos'])): ?>
                        <div class="info-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-check-square"></i>
                                Requisitos
                            </h3>
                            <p class="project-requirements"><?= nl2br(htmlspecialchars($proyecto['requisitos'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($proyecto['responsable_directo']) || !empty($proyecto['contacto_responsable'])): ?>
                        <div class="info-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-user-tie"></i>
                                Responsable del Proyecto
                            </h3>
                            <div class="info-grid">
                                <?php if (!empty($proyecto['responsable_directo'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Nombre</div>
                                    <div class="info-value"><?= htmlspecialchars($proyecto['responsable_directo']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($proyecto['contacto_responsable'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Contacto</div>
                                    <div class="info-value"><?= htmlspecialchars($proyecto['contacto_responsable']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Students Section -->
                <div class="students-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-users"></i>
                            Estudiantes Activos
                            <span class="count-badge"><?= count($estudiantesActivos) ?></span>
                        </h2>
                        <?php if (!empty($estudiantesActivos)): ?>
                        <a href="estudiantes-asignados.php?proyecto=<?= $proyecto['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i>
                            Ver Todos
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($estudiantesActivos)): ?>
                            <div class="students-list">
                                <?php foreach ($estudiantesActivos as $estudiante): ?>
                                <div class="student-item">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                                    </div>
                                    <div class="student-info">
                                        <h4 class="student-name">
                                            <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?>
                                        </h4>
                                        <p class="student-details">
                                            <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($estudiante['numero_control']) ?></span>
                                            <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($estudiante['carrera']) ?></span>
                                        </p>
                                        <div class="student-progress-mini">
                                            <div class="progress-bar-mini">
                                                <div class="progress-fill-mini" style="width: <?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%"></div>
                                            </div>
                                            <span class="progress-text-mini"><?= $estudiante['horas_completadas'] ?? 0 ?> / 500 hrs</span>
                                        </div>
                                    </div>
                                    <div class="student-actions-mini">
                                        <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                                            <?= getEstadoText($estudiante['estado_servicio']) ?>
                                        </span>
                                        <a href="estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-mini">
                                <i class="fas fa-user-graduate"></i>
                                <p>No hay estudiantes activos en este proyecto</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pending Applications -->
                <?php if (!empty($solicitudesPendientes)): ?>
                <div class="applications-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-clipboard-list"></i>
                            Solicitudes Pendientes
                            <span class="count-badge warning"><?= count($solicitudesPendientes) ?></span>
                        </h2>
                        <a href="estudiantes-solicitudes.php?proyecto=<?= $proyecto['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i>
                            Ver Todas
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="applications-list">
                            <?php foreach ($solicitudesPendientes as $solicitud): ?>
                            <div class="application-item">
                                <div class="application-avatar">
                                    <?= strtoupper(substr($solicitud['nombre'], 0, 1)) ?>
                                </div>
                                <div class="application-info">
                                    <h4><?= htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido_paterno']) ?></h4>
                                    <p class="application-details">
                                        <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($solicitud['numero_control']) ?></span>
                                        <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($solicitud['carrera']) ?></span>
                                        <span><i class="fas fa-calendar"></i> <?= formatDate($solicitud['fecha_solicitud']) ?></span>
                                    </p>
                                </div>
                                <div class="application-actions">
                                    <a href="solicitud-detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Revisar
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="content-sidebar">
                <!-- Capacity Card -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-users"></i>
                            Capacidad del Proyecto
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="capacity-visual">
                            <div class="capacity-circle">
                                <svg viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#4caf50" stroke-width="8"
                                            stroke-dasharray="<?= $porcentajeOcupacion * 2.51 ?> 251"
                                            transform="rotate(-90 50 50)"/>
                                </svg>
                                <div class="capacity-percentage"><?= $porcentajeOcupacion ?>%</div>
                            </div>
                            <div class="capacity-details">
                                <div class="capacity-item">
                                    <span class="capacity-label">Ocupados</span>
                                    <span class="capacity-value"><?= $proyecto['cupo_ocupado'] ?></span>
                                </div>
                                <div class="capacity-item">
                                    <span class="capacity-label">Disponibles</span>
                                    <span class="capacity-value"><?= $proyecto['cupo_disponible'] - $proyecto['cupo_ocupado'] ?></span>
                                </div>
                                <div class="capacity-item">
                                    <span class="capacity-label">Total</span>
                                    <span class="capacity-value"><?= $proyecto['cupo_disponible'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-chart-bar"></i>
                            Estadísticas Rápidas
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-stats">
                            <div class="quick-stat-item">
                                <div class="quick-stat-icon success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value"><?= $stats['estudiantes_completados'] ?></div>
                                    <div class="quick-stat-label">Completados</div>
                                </div>
                            </div>

                            <div class="quick-stat-item">
                                <div class="quick-stat-icon error">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value"><?= $stats['solicitudes_rechazadas'] ?></div>
                                    <div class="quick-stat-label">Rechazadas</div>
                                </div>
                            </div>

                            <div class="quick-stat-item">
                                <div class="quick-stat-icon info">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value"><?= round($stats['promedio_horas_completados']) ?></div>
                                    <div class="quick-stat-label">Hrs Promedio</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-history"></i>
                            Actividad Reciente
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($historial)): ?>
                            <div class="timeline">
                                <?php foreach (array_slice($historial, 0, 5) as $actividad): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker <?= getEstadoBadgeClass($actividad['estado']) ?>"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-title">
                                            <?= htmlspecialchars($actividad['nombre'] . ' ' . $actividad['apellido_paterno']) ?>
                                        </div>
                                        <div class="timeline-description">
                                            <?= getEstadoText($actividad['estado']) ?>
                                        </div>
                                        <div class="timeline-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= formatDate($actividad['fecha_solicitud']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-mini">
                                <i class="fas fa-history"></i>
                                <p>Sin actividad reciente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Project Metadata -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-info-circle"></i>
                            Metadatos
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="metadata-list">
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-calendar-plus"></i>
                                    Creado
                                </span>
                                <span class="metadata-value"><?= formatDate($proyecto['created_at']) ?></span>
                            </div>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-calendar-check"></i>
                                    Última Actualización
                                </span>
                                <span class="metadata-value"><?= formatDate($proyecto['updated_at']) ?></span>
                            </div>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-hashtag"></i>
                                    ID del Proyecto
                                </span>
                                <span class="metadata-value"><?= $proyecto['id'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
    background: var(--bg-light);
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
    --primary: #4caf50;
    --primary-light: #66bb6a;
    --secondary: #2196f3;
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
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
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
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

.separator {
    color: var(--text-light);
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

/* Statistics Overview */
.statistics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--gradient-color), transparent);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.solicitudes { --gradient-color: var(--info); }
.stat-card.pendientes { --gradient-color: var(--warning); }
.stat-card.activos { --gradient-color: var(--primary); }
.stat-card.completados { --gradient-color: var(--success); }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-card.solicitudes .stat-icon { background: linear-gradient(135deg, var(--info), #60a5fa); }
.stat-card.pendientes .stat-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.stat-card.activos .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card.completados .stat-icon { background: linear-gradient(135deg, var(--success), #34d399); }

.stat-content {
    flex: 1;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.75rem 0;
}

.stat-trend, .stat-alert {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.stat-trend {
    color: var(--success);
}

.stat-alert {
    color: var(--warning);
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
}

.content-main {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.content-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Cards */
.info-card,
.students-card,
.applications-card,
.sidebar-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.card-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-title-sm {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-title i {
    color: var(--primary);
}

.card-body {
    padding: 1.5rem;
}

.count-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.count-badge.warning {
    background: var(--warning);
}

/* Info Sections */
.info-section {
    margin-bottom: 2rem;
}

.info-section:last-child {
    margin-bottom: 0;
}

.section-subtitle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.section-subtitle i {
    color: var(--primary);
    font-size: 1rem;
}

.project-description,
.project-objectives,
.project-requirements {
    color: var(--text-secondary);
    line-height: 1.7;
    margin: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-label i {
    color: var(--primary);
}

.info-value {
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 500;
}

/* Students List */
.students-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.student-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.student-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.student-details span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.student-progress-mini {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar-mini {
    flex: 1;
    height: 6px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 1rem;
    transition: width 1s ease;
}

.progress-text-mini {
    font-size: 0.75rem;
    color: var(--text-secondary);
    white-space: nowrap;
}

.student-actions-mini {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Applications List */
.applications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.application-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border: 2px solid transparent;
    transition: var(--transition);
}

.application-item:hover {
    background: var(--bg-white);
    border-color: var(--warning);
    box-shadow: var(--shadow);
}

.application-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.application-info {
    flex: 1;
}

.application-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.application-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0;
}

.application-details span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.application-actions {
    flex-shrink: 0;
}

/* Capacity Visual */
.capacity-visual {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
}

.capacity-circle {
    position: relative;
    width: 150px;
    height: 150px;
}

.capacity-circle svg {
    transform: rotate(0deg);
}

.capacity-percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.capacity-details {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.capacity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.capacity-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.capacity-value {
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 700;
}

/* Quick Stats */
.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.quick-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.quick-stat-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.quick-stat-icon.error {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.quick-stat-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.quick-stat-content {
    flex: 1;
}

.quick-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.quick-stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* Timeline */
.timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    position: relative;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 6px;
    top: 20px;
    bottom: -16px;
    width: 2px;
    background: var(--border);
}

.timeline-marker {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 4px;
    z-index: 1;
}

.timeline-marker.badge-success {
    background: var(--success);
}

.timeline-marker.badge-warning {
    background: var(--warning);
}

.timeline-marker.badge-primary {
    background: var(--primary);
}

.timeline-marker.badge-error {
    background: var(--error);
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.timeline-description {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.timeline-date {
    font-size: 0.75rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Metadata */
.metadata-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.metadata-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.metadata-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.metadata-label i {
    color: var(--primary);
}

.metadata-value {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 600;
}

/* Empty State */
.empty-state-mini {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-light);
}

.empty-state-mini i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.empty-state-mini p {
    font-size: 0.9rem;
    margin: 0;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.badge-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.badge-primary {
    background: rgba(76, 175, 80, 0.1);
    color: var(--primary);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.5rem 0.875rem;
    font-size: 0.8rem;
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
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
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

@keyframes countUp {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.stat-number {
    animation: countUp 0.8s ease-out;
}

.statistics-overview > * {
    animation: slideIn 0.6s ease-out;
}

.statistics-overview > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-overview > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-overview > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-overview > *:nth-child(4) { animation-delay: 0.4s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr 300px;
    }
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .content-sidebar {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .student-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .student-actions-mini {
        width: 100%;
        justify-content: space-between;
    }
    
    .application-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .application-actions {
        width: 100%;
    }
    
    .application-actions .btn {
        width: 100%;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .header-actions .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .card-header,
    .card-body {
        padding: 1rem;
    }
    
    .capacity-circle {
        width: 120px;
        height: 120px;
    }
    
    .capacity-percentage {
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent.replace(/,/g, ''));
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                numberElement.textContent = Math.floor(Math.min(currentNumber, finalNumber)).toLocaleString();
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber.toLocaleString();
            }
        }
        
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Animate progress bars
    setTimeout(() => {
        const progressFills = document.querySelectorAll('.progress-fill-mini');
        progressFills.forEach((fill, index) => {
            const width = fill.style.width;
            fill.style.width = '0%';
            
            setTimeout(() => {
                fill.style.width = width;
            }, index * 100);
        });
    }, 500);
});

function toggleEstado(proyectoId, nuevoEstado) {
    const mensaje = nuevoEstado === 1 ? 
        '¿Está seguro de que desea activar este proyecto?' : 
        '¿Está seguro de que desea desactivar este proyecto? Los estudiantes activos no se verán afectados.';
    
    if (confirm(mensaje)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_estado">
            <input type="hidden" name="nuevo_estado" value="${nuevoEstado}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>