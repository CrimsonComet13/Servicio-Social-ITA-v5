<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();

// ✅ CORRECCIÓN: Validar que el usuario tiene los datos necesarios
if (!$usuario || !isset($usuario['id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// ✅ CORRECCIÓN: Obtener datos del jefe de laboratorio desde la BD
$jefeLab = $db->fetch("SELECT id, nombre, laboratorio FROM jefes_laboratorio WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeLab) {
    flashMessage('No se encontró el perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
}
$jefeLabId = $jefeLab['id'];

// ✅ Asegurar que los campos existen con valores por defecto
$nombreUsuario = !empty($jefeLab['nombre']) ? $jefeLab['nombre'] : 'Usuario';
$laboratorioUsuario = !empty($jefeLab['laboratorio']) ? $jefeLab['laboratorio'] : 'Sin Laboratorio';

// Obtener estadísticas del laboratorio - ACTUALIZADO
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT s.id) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as estudiantes_activos,
        COUNT(DISTINCT r.id) as total_reportes,
        COUNT(DISTINCT CASE WHEN r.estado = 'pendiente_evaluacion' THEN r.id END) as reportes_pendientes,
        COUNT(DISTINCT CASE WHEN r.estado = 'aprobado' THEN r.id END) as reportes_aprobados,
        COALESCE(SUM(r.horas_reportadas), 0) as total_horas,
        COUNT(DISTINCT p.id) as total_proyectos
    FROM jefes_laboratorio jl
    LEFT JOIN solicitudes_servicio s ON jl.id = s.jefe_laboratorio_id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    LEFT JOIN proyectos_laboratorio p ON jl.id = p.jefe_laboratorio_id
    WHERE jl.id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Verificar que las estadísticas se obtuvieron correctamente
if (!$stats) {
    $stats = [
        'total_estudiantes' => 0,
        'estudiantes_activos' => 0,
        'total_reportes' => 0,
        'reportes_pendientes' => 0,
        'reportes_aprobados' => 0,
        'total_horas' => 0,
        'total_proyectos' => 0
    ];
}

// ✅ Obtener estudiantes activos - CORREGIDO
$estudiantesActivos = $db->fetchAll("
    SELECT e.*, s.id as solicitud_id, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto, e.horas_completadas
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.jefe_laboratorio_id = :jefe_id 
    AND s.estado = 'en_proceso'
    ORDER BY s.fecha_inicio_propuesta DESC
    LIMIT 5
", ['jefe_id' => $jefeLabId]);

// Obtener reportes pendientes de evaluación
$reportesPendientes = $db->fetchAll("
    SELECT r.*, e.nombre as estudiante_nombre, e.apellido_paterno, e.numero_control,
           p.nombre_proyecto, s.id as solicitud_id
    FROM reportes_bimestrales r
    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.jefe_laboratorio_id = :jefe_id 
    AND r.estado = 'pendiente_evaluacion'
    ORDER BY r.fecha_entrega DESC
    LIMIT 5
", ['jefe_id' => $jefeLabId]);

// Obtener actividades recientes del laboratorio
$actividadesRecientes = $db->fetchAll("
    SELECT 
        'reporte' as tipo,
        CONCAT('Reporte de ', e.nombre, ' ', e.apellido_paterno) as titulo,
        CONCAT('Reporte bimestral #', r.numero_reporte, ' - ', r.estado) as descripcion,
        r.created_at as fecha,
        r.estado
    FROM reportes_bimestrales r
    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
    JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.jefe_laboratorio_id = :jefe_id
    ORDER BY r.created_at DESC
    LIMIT 5
", ['jefe_id' => $jefeLabId]);

// Obtener proyectos del laboratorio
$proyectosLab = $db->fetchAll("
    SELECT p.*, 
           COUNT(DISTINCT s.id) as total_solicitudes,
           COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as estudiantes_activos
    FROM proyectos_laboratorio p
    LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    WHERE p.jefe_laboratorio_id = :jefe_id AND p.activo = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 3
", ['jefe_id' => $jefeLabId]);

// Asegurar que las consultas no devuelvan null
$estudiantesActivos = $estudiantesActivos ?: [];
$reportesPendientes = $reportesPendientes ?: [];
$actividadesRecientes = $actividadesRecientes ?: [];
$proyectosLab = $proyectosLab ?: [];

$pageTitle = "Dashboard Jefe de Laboratorio - " . APP_NAME;
$dashboardJS = true;
$chartsJS = true;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">
                    <span class="welcome-text">¡Hola, <?= htmlspecialchars(explode(' ', $nombreUsuario)[0]) ?>!</span>
                    <span class="welcome-emoji">🔬</span>
                </h1>
                <p class="welcome-subtitle">Panel de control del laboratorio <?= htmlspecialchars($laboratorioUsuario) ?></p>
            </div>
            <div class="date-section">
                <div class="current-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= formatDate(date('Y-m-d')) ?></span>
                </div>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"><?= date('H:i') ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card estudiantes">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Estudiantes Activos</h3>
                    <div class="stat-number"><?= $stats['estudiantes_activos'] ?? 0 ?></div>
                    <p class="stat-description">Realizando servicio</p>
                    <div class="stat-trend">
                        <i class="fas fa-users"></i>
                        <span><?= $stats['total_estudiantes'] ?? 0 ?> total</span>
                    </div>
                </div>
            </div>

            <div class="stat-card reportes-pendientes">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Reportes Pendientes</h3>
                    <div class="stat-number"><?= $stats['reportes_pendientes'] ?? 0 ?></div>
                    <p class="stat-description">Por evaluar</p>
                    <?php if (($stats['reportes_pendientes'] ?? 0) > 0): ?>
                    <div class="stat-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Requiere atención</span>
                    </div>
                    <?php else: ?>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle"></i>
                        <span>Al día</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card horas">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Horas Acumuladas</h3>
                    <div class="stat-number"><?= number_format($stats['total_horas'] ?? 0) ?></div>
                    <p class="stat-description">Horas reportadas</p>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span><?= $stats['total_reportes'] ?? 0 ?> reportes</span>
                    </div>
                </div>
            </div>

            <div class="stat-card proyectos">
                <div class="stat-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Proyectos Activos</h3>
                    <div class="stat-number"><?= $stats['total_proyectos'] ?? 0 ?></div>
                    <p class="stat-description">En el laboratorio</p>
                    <div class="stat-trend">
                        <i class="fas fa-flask"></i>
                        <span>Activos</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content-area">
            <!-- Left Column -->
            <div class="content-column">
                <!-- Reportes Pendientes Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-clipboard-check"></i>
                            Reportes Pendientes de Evaluación
                        </h2>
                        <a href="../modules/laboratorio/evaluaciones.php" class="section-link">
                            Ver todos <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (!empty($reportesPendientes)): ?>
                        <div class="reports-grid">
                            <?php foreach ($reportesPendientes as $reporte): ?>
                            <div class="report-card">
                                <div class="report-header">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($reporte['estudiante_nombre'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div class="student-info">
                                        <h4><?= htmlspecialchars(($reporte['estudiante_nombre'] ?? 'Sin nombre') . ' ' . ($reporte['apellido_paterno'] ?? '')) ?></h4>
                                        <p><?= htmlspecialchars($reporte['numero_control'] ?? 'Sin número') ?></p>
                                    </div>
                                    <div class="report-badge pending">
                                        <i class="fas fa-clock"></i>
                                        Pendiente
                                    </div>
                                </div>
                                
                                <div class="report-body">
                                    <div class="report-info">
                                        <div class="info-row">
                                            <span class="info-label">Proyecto:</span>
                                            <span class="info-value"><?= htmlspecialchars($reporte['nombre_proyecto'] ?? 'Sin proyecto') ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Reporte:</span>
                                            <span class="info-value">Bimestre #<?= $reporte['numero_reporte'] ?? 0 ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Horas reportadas:</span>
                                            <span class="info-value"><?= $reporte['horas_reportadas'] ?? 0 ?> horas</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Fecha de entrega:</span>
                                            <span class="info-value"><?= formatDate($reporte['fecha_entrega'] ?? date('Y-m-d')) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="report-actions">
                                    <a href="../modules/laboratorio/reporte-detalle.php?id=<?= $reporte['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="../modules/laboratorio/evaluar-reporte.php?id=<?= $reporte['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Evaluar
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="empty-content">
                                <h3>¡Excelente trabajo!</h3>
                                <p>No tienes reportes pendientes de evaluación.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Estudiantes Activos Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-users"></i>
                            Estudiantes Activos
                        </h2>
                        <a href="../modules/laboratorio/estudiantes.php" class="section-link">
                            Ver todos <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (!empty($estudiantesActivos)): ?>
                        <div class="students-list">
                            <?php foreach ($estudiantesActivos as $estudiante): ?>
                            <div class="student-item">
                                <div class="student-avatar-large">
                                    <?= strtoupper(substr($estudiante['nombre'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="student-details">
                                    <h4><?= htmlspecialchars(($estudiante['nombre'] ?? 'Sin nombre') . ' ' . ($estudiante['apellido_paterno'] ?? '')) ?></h4>
                                    <div class="student-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-id-card"></i>
                                            <?= htmlspecialchars($estudiante['numero_control'] ?? 'N/A') ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-project-diagram"></i>
                                            <?= htmlspecialchars($estudiante['nombre_proyecto'] ?? 'Sin proyecto') ?>
                                        </span>
                                    </div>
                                    <div class="student-progress">
                                        <span class="progress-label">Periodo:</span>
                                        <span class="progress-dates">
                                            <?= formatDate($estudiante['fecha_inicio_propuesta'] ?? date('Y-m-d')) ?> - 
                                            <?= formatDate($estudiante['fecha_fin_propuesta'] ?? date('Y-m-d')) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="student-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?= $estudiante['horas_completadas'] ?? 0 ?></span>
                                        <span class="stat-label">Horas</span>
                                    </div>
                                    <div class="progress-circle-small" data-percentage="<?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>">
                                        <span><?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%</span>
                                    </div>
                                </div>
                                <div class="student-actions">
                                    <a href="../modules/laboratorio/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Ver Detalle
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="empty-content">
                                <h3>Sin estudiantes activos</h3>
                                <p>Aún no hay estudiantes realizando su servicio social en este laboratorio.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Proyectos del Laboratorio Section -->
                <?php if (!empty($proyectosLab)): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-project-diagram"></i>
                            Proyectos del Laboratorio
                        </h2>
                        <a href="../modules/laboratorio/proyectos.php" class="section-link">
                            Ver todos <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="projects-grid">
                        <?php foreach ($proyectosLab as $proyecto): ?>
                        <div class="project-card">
                            <div class="project-header">
                                <div class="project-icon">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <h4><?= htmlspecialchars($proyecto['nombre_proyecto'] ?? 'Sin nombre') ?></h4>
                            </div>
                            <div class="project-body">
                                <p><?= htmlspecialchars(shortenText($proyecto['descripcion'] ?? 'Sin descripción', 100)) ?></p>
                                <div class="project-stats-mini">
                                    <div class="stat-mini">
                                        <i class="fas fa-users"></i>
                                        <span><?= $proyecto['estudiantes_activos'] ?? 0 ?> activos</span>
                                    </div>
                                    <div class="stat-mini">
                                        <i class="fas fa-paper-plane"></i>
                                        <span><?= $proyecto['total_solicitudes'] ?? 0 ?> solicitudes</span>
                                    </div>
                                    <div class="stat-mini">
                                        <i class="fas fa-user-check"></i>
                                        <span><?= $proyecto['cupo_ocupado'] ?? 0 ?>/<?= $proyecto['cupo_disponible'] ?? 0 ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="project-actions">
                                <a href="../modules/laboratorio/proyecto-detalle.php?id=<?= $proyecto['id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="sidebar-column">
                <!-- Quick Actions Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">
                            <i class="fas fa-bolt"></i>
                            Acciones Rápidas
                        </h3>
                    </div>
                    <div class="widget-content">
                        <div class="quick-actions-grid">
                            <a href="../modules/laboratorio/evaluaciones.php" class="action-card">
                                <div class="action-icon evaluaciones">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="action-text">
                                    <span>Evaluar Reportes</span>
                                    <small>Revisar pendientes</small>
                                </div>
                                <div class="action-badge">
                                    <?= $stats['reportes_pendientes'] ?? 0 ?>
                                </div>
                            </a>

                            <a href="../modules/laboratorio/estudiantes.php" class="action-card">
                                <div class="action-icon estudiantes">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="action-text">
                                    <span>Estudiantes</span>
                                    <small>Ver progreso</small>
                                </div>
                                <div class="action-badge">
                                    <?= $stats['estudiantes_activos'] ?? 0 ?>
                                </div>
                            </a>

                            <a href="../modules/laboratorio/proyectos.php" class="action-card">
                                <div class="action-icon proyectos">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <div class="action-text">
                                    <span>Proyectos</span>
                                    <small>Gestionar</small>
                                </div>
                            </a>

                            <a href="../modules/laboratorio/reportes.php" class="action-card">
                                <div class="action-icon reportes">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <div class="action-text">
                                    <span>Estadísticas</span>
                                    <small>Ver reportes</small>
                                </div>
                            </a>                   
                        </div>
                    </div>
                </div>

                <!-- Lab Summary Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">
                            <i class="fas fa-flask"></i>
                            Resumen del Laboratorio
                        </h3>
                    </div>
                    <div class="widget-content">
                        <div class="summary-stats">
                            <div class="summary-item">
                                <div class="summary-icon">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="summary-data">
                                    <span class="summary-value">
                                        <?= $stats['total_reportes'] > 0 ? round(($stats['reportes_aprobados'] / $stats['total_reportes']) * 100) : 0 ?>%
                                    </span>
                                    <span class="summary-label">Tasa de Aprobación</span>
                                </div>
                            </div>

                            <div class="summary-item">
                                <div class="summary-icon">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div class="summary-data">
                                    <span class="summary-value"><?= $stats['reportes_aprobados'] ?? 0 ?></span>
                                    <span class="summary-label">Reportes Aprobados</span>
                                </div>
                            </div>

                            <div class="summary-item">
                                <div class="summary-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-data">
                                    <span class="summary-value"><?= round(($stats['total_horas'] ?? 0) / max(1, $stats['estudiantes_activos'] ?? 1)) ?></span>
                                    <span class="summary-label">Promedio de Horas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividades Recientes Widget -->
                <div class="widget">
                    <div class="widget-header">
                        <h3 class="widget-title">
                            <i class="fas fa-history"></i>
                            Actividades Recientes
                        </h3>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($actividadesRecientes)): ?>
                            <div class="activities-list">
                                <?php foreach (array_slice($actividadesRecientes, 0, 5) as $actividad): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= htmlspecialchars($actividad['estado'] ?? 'pendiente') ?>">
                                        <i class="fas fa-<?= $actividad['tipo'] === 'reporte' ? 'file-alt' : 'info-circle' ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h5><?= htmlspecialchars($actividad['titulo']) ?></h5>
                                        <p><?= htmlspecialchars($actividad['descripcion']) ?></p>
                                        <span class="activity-date"><?= timeAgo($actividad['fecha']) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-activities">
                                <i class="fas fa-inbox"></i>
                                <p>No hay actividades recientes</p>
                            </div>
                        <?php endif; ?>
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
    --sidebar-width: 280px;
    --header-height: 70px;
}

/* Main wrapper */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
}

/* Dashboard Container */
.dashboard-container {
    padding: 1.5rem;
    max-width: calc(1400px - var(--sidebar-width));
    margin: 0 auto;
    width: 100%;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.date-section {
    display: flex;
    gap: 1rem;
}

.current-date, .current-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-secondary);
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

.stat-card.estudiantes {
    --gradient-color: var(--success);
}

.stat-card.reportes-pendientes {
    --gradient-color: var(--warning);
}

.stat-card.horas {
    --gradient-color: var(--info);
}

.stat-card.proyectos {
    --gradient-color: var(--secondary);
}

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

.stat-card.estudiantes .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.reportes-pendientes .stat-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-card.horas .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-card.proyectos .stat-icon {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

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
    font-size: 0.85rem;
    font-weight: 500;
}

.stat-trend {
    color: var(--success);
}

.stat-alert {
    color: var(--warning);
}

/* Main Content Area */
.main-content-area {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

/* Content Sections */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-link {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition);
}

.section-link:hover {
    color: var(--primary-light);
}

/* Report Cards */
.reports-grid {
    display: grid;
    gap: 1rem;
}

.report-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.25rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.report-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.student-info {
    flex: 1;
}

.student-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.student-info p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
}

.report-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.report-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.report-body {
    margin-bottom: 1rem;
}

.report-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.info-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.info-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
}

.report-actions {
    display: flex;
    gap: 0.5rem;
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
    padding: 1.25rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    transition: var(--transition);
}

.student-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.student-avatar-large {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #34d399);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.student-details {
    flex: 1;
}

.student-details h4 {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.student-progress {
    font-size: 0.8rem;
    color: var(--text-light);
}

.progress-label {
    font-weight: 500;
}

.progress-dates {
    font-weight: 600;
}

.student-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.progress-circle-small {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--success);
    background: conic-gradient(var(--success) 0% var(--percentage), var(--bg-gray) 0% 100%);
}

/* Projects Grid */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.project-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.25rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.project-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.project-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.project-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
}

.project-header h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.project-body p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.project-stats-mini {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-mini {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.stat-mini i {
    font-size: 0.75rem;
    color: var(--primary);
}

.project-actions {
    display: flex;
    gap: 0.5rem;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--text-light);
    margin: 0 auto 1rem;
}

.empty-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin: 0;
}

/* Widgets */
.widget {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.widget-content {
    padding: 1.5rem;
}

/* Quick Actions Grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    position: relative;
    border: 1px solid var(--border);
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.action-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    margin-bottom: 0.75rem;
}

.action-icon.evaluaciones {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.action-icon.estudiantes {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.action-icon.proyectos {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

.action-icon.reportes {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.action-icon.perfil {
    background: linear-gradient(135deg, var(--text-secondary), var(--text-light));
}

.action-text {
    text-align: center;
}

.action-text span {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.action-text small {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.action-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: var(--error);
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
}

/* Summary Stats */
.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.summary-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.summary-data {
    flex: 1;
}

.summary-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.summary-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Activities List */
.activities-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.activity-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.activity-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: white;
    flex-shrink: 0;
}

.activity-icon.pendiente_evaluacion {
    background: var(--warning);
}

.activity-icon.aprobado {
    background: var(--success);
}

.activity-icon.rechazado {
    background: var(--error);
}

.activity-content {
    flex: 1;
}

.activity-content h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.activity-content p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.activity-date {
    font-size: 0.75rem;
    color: var(--text-light);
}

.empty-activities {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

.empty-activities i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
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
    font-size: 0.85rem;
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

/* Animaciones */
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

.statistics-overview > * {
    animation: slideIn 0.6s ease-out;
}

.statistics-overview > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-overview > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-overview > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-overview > *:nth-child(4) { animation-delay: 0.4s; }

/* Responsive Design */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .dashboard-container {
        max-width: 1400px;
    }
    
    .main-content-area {
        grid-template-columns: 1fr;
    }
    
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .student-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .student-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
    }
    
    updateTime();
    setInterval(updateTime, 60000);
    
    // Progress circles animation
    const progressCircles = document.querySelectorAll('.progress-circle-small');
    progressCircles.forEach(circle => {
        const percentage = parseInt(circle.dataset.percentage);
        circle.style.background = `conic-gradient(var(--success) 0% ${percentage}%, var(--bg-gray) 0% 100%)`;
    });
    
    console.log('✅ Dashboard Jefe de Laboratorio inicializado');
});
</script>

<?php include '../includes/footer.php'; ?>