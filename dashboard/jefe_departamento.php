<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Validar que el usuario tiene los datos necesarios
if (!$usuario || !isset($usuario['id'])) {
    // Redirigir al login si no hay usuario válido
    header('Location: ../auth/login.php');
    exit;
}

$jefeDepto = $db->fetch("SELECT id, nombre, departamento FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

// Asegurar que los campos del usuario existen con valores por defecto
$nombreUsuario = !empty($jefeDepto['nombre']) ? $jefeDepto['nombre'] : 'Usuario';
$departamentoUsuario = !empty($jefeDepto['departamento']) ? $jefeDepto['departamento'] : 'Sin Departamento';

// Obtener estadísticas del departamento - ACTUALIZADO CON PROYECTOS
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT s.id) as total_solicitudes,
        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN s.id END) as solicitudes_pendientes,
        COUNT(DISTINCT e.id) as total_estudiantes,
        COUNT(DISTINCT jl.id) as total_laboratorios,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as servicios_activos,
        COUNT(DISTINCT p.id) as total_proyectos,
        COUNT(DISTINCT CASE WHEN p.activo = 1 THEN p.id END) as proyectos_activos,
        COALESCE(SUM(e.horas_completadas), 0) as horas_totales
    FROM jefes_departamento jd
    LEFT JOIN solicitudes_servicio s ON jd.id = s.jefe_departamento_id
    LEFT JOIN estudiantes e ON s.estudiante_id = e.id
    LEFT JOIN jefes_laboratorio jl ON jd.id = jl.jefe_departamento_id
    LEFT JOIN proyectos_laboratorio p ON jd.id = p.jefe_departamento_id
    WHERE jd.id = :jefe_id
", ['jefe_id' => $jefeId]);

// Verificar que las estadísticas se obtuvieron correctamente
if (!$stats) {
    $stats = [
        'total_solicitudes' => 0,
        'solicitudes_pendientes' => 0,
        'total_estudiantes' => 0,
        'total_laboratorios' => 0,
        'servicios_activos' => 0,
        'total_proyectos' => 0,
        'proyectos_activos' => 0,
        'horas_totales' => 0
    ];
}

// Obtener solicitudes pendientes recientes
$solicitudesPendientes = $db->fetchAll("
    SELECT s.*, e.nombre as estudiante_nombre, e.numero_control, e.carrera,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND s.estado = 'pendiente'
    ORDER BY s.fecha_solicitud DESC
    LIMIT 5
", ['jefe_id' => $jefeId]);

// Obtener estudiantes activos recientes - CORREGIDO
$estudiantesActivos = $db->fetchAll("
    SELECT e.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND s.estado = 'en_proceso'
    ORDER BY s.fecha_inicio_propuesta DESC
    LIMIT 5
", ['jefe_id' => $jefeId]);

// Obtener actividades recientes del departamento
$actividadesRecientes = $db->fetchAll("
    SELECT 
        'solicitud' as tipo,
        CONCAT('Nueva solicitud de ', e.nombre) as titulo,
        CONCAT('Solicitud para el proyecto: ', p.nombre_proyecto) as descripcion,
        s.fecha_solicitud as fecha,
        s.estado
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.jefe_departamento_id = :jefe_id
    ORDER BY s.fecha_solicitud DESC
    LIMIT 5
", ['jefe_id' => $jefeId]);

// Obtener proyectos activos recientes - NUEVA CONSULTA INTEGRADA
$proyectosRecientes = $db->fetchAll("
    SELECT p.*, 
           COUNT(s.id) as total_solicitudes,
           COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as estudiantes_activos,
           jl.nombre as jefe_lab_nombre
    FROM proyectos_laboratorio p
    LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    LEFT JOIN jefes_laboratorio jl ON p.jefe_laboratorio_id = jl.id
    WHERE p.jefe_departamento_id = :jefe_id AND p.activo = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 3
", ['jefe_id' => $jefeId]);

// Asegurar que las consultas no devuelvan null
$solicitudesPendientes = $solicitudesPendientes ?: [];
$estudiantesActivos = $estudiantesActivos ?: [];
$actividadesRecientes = $actividadesRecientes ?: [];
$proyectosRecientes = $proyectosRecientes ?: [];

$pageTitle = "Dashboard Jefe de Departamento - " . APP_NAME;
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
                <span class="welcome-emoji">👨‍💼</span>
            </h1>
            <p class="welcome-subtitle">Panel de control del departamento <?= htmlspecialchars($departamentoUsuario) ?></p>
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

    <!-- Statistics Overview - ACTUALIZADO CON PROYECTOS -->
    <div class="statistics-overview">
        <div class="stat-card solicitudes">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Solicitudes Totales</h3>
                <div class="stat-number"><?= $stats['total_solicitudes'] ?? 0 ?></div>
                <p class="stat-description">Solicitudes recibidas</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% este mes</span>
                </div>
            </div>
        </div>

        <div class="stat-card pendientes">
            <div class="stat-icon">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Pendientes</h3>
                <div class="stat-number"><?= $stats['solicitudes_pendientes'] ?? 0 ?></div>
                <p class="stat-description">Por revisar</p>
                <?php if (($stats['solicitudes_pendientes'] ?? 0) > 0): ?>
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

        <div class="stat-card activos">
            <div class="stat-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Estudiantes Activos</h3>
                <div class="stat-number"><?= $stats['servicios_activos'] ?? 0 ?></div>
                <p class="stat-description">En servicio social</p>
                <div class="stat-trend">
                    <i class="fas fa-users"></i>
                    <span><?= $stats['total_estudiantes'] ?? 0 ?> total</span>
                </div>
            </div>
        </div>

        <div class="stat-card horas">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Horas Acumuladas</h3>
                <div class="stat-number"><?= number_format($stats['horas_totales'] ?? 0) ?></div>
                <p class="stat-description">Horas cumplidas</p>
                <div class="stat-trend">
                    <i class="fas fa-chart-line"></i>
                    <span><?= round(($stats['horas_totales'] ?? 0) / max(1, $stats['total_estudiantes'] ?? 1)) ?> promedio</span>
                </div>
            </div>
        </div>

        <div class="stat-card laboratorios">
            <div class="stat-icon">
                <i class="fas fa-flask"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Laboratorios</h3>
                <div class="stat-number"><?= $stats['total_laboratorios'] ?? 0 ?></div>
                <p class="stat-description">Registrados</p>
                <div class="stat-trend">
                    <i class="fas fa-building"></i>
                    <span>Activos</span>
                </div>
            </div>
        </div>

        <!-- Nueva tarjeta para proyectos - INTEGRADA -->
        <div class="stat-card proyectos">
            <div class="stat-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Proyectos Activos</h3>
                <div class="stat-number"><?= $stats['proyectos_activos'] ?? 0 ?></div>
                <p class="stat-description">En ejecución</p>
                <div class="stat-trend">
                    <i class="fas fa-project-diagram"></i>
                    <span><?= $stats['total_proyectos'] ?? 0 ?> total</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content-area">
        <!-- Left Column -->
        <div class="content-column">
            <!-- Pending Requests Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-check"></i>
                        Solicitudes Pendientes
                    </h2>
                    <a href="../modules/departamento/solicitudes.php" class="section-link">
                        Ver todas <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (!empty($solicitudesPendientes)): ?>
                    <div class="requests-grid">
                        <?php foreach ($solicitudesPendientes as $solicitud): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div class="student-avatar">
                                    <?= strtoupper(substr($solicitud['estudiante_nombre'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="student-info">
                                    <h4><?= htmlspecialchars($solicitud['estudiante_nombre'] ?? 'Sin nombre') ?></h4>
                                    <p><?= htmlspecialchars($solicitud['numero_control'] ?? 'Sin número') ?> - <?= htmlspecialchars($solicitud['carrera'] ?? 'Sin carrera') ?></p>
                                </div>
                                <div class="request-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= formatDate($solicitud['fecha_solicitud'] ?? date('Y-m-d')) ?></span>
                                </div>
                            </div>
                            
                            <div class="request-body">
                                <div class="project-info">
                                    <h5>Proyecto Solicitado</h5>
                                    <p><?= htmlspecialchars($solicitud['nombre_proyecto'] ?? 'Sin proyecto asignado') ?></p>
                                    <?php if (!empty($solicitud['jefe_lab_nombre'])): ?>
                                    <small>Supervisor: <?= htmlspecialchars($solicitud['jefe_lab_nombre']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="request-actions">
                                <a href="../modules/departamento/solicitud-detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="../modules/departamento/aprobar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Aprobar
                                </a>
                                <a href="../modules/departamento/rechazar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-error btn-sm">
                                    <i class="fas fa-times"></i> Rechazar
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="empty-content">
                            <h3>¡Excelente trabajo!</h3>
                            <p>No tienes solicitudes pendientes por revisar.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Active Students Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        Estudiantes Activos
                    </h2>
                    <a href="../modules/departamento/estudiantes.php" class="section-link">
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
                                        <i class="fas fa-flask"></i>
                                        <?= htmlspecialchars($estudiante['laboratorio'] ?? 'N/A') ?>
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
                                <div class="progress-circle-small">
                                    <span><?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%</span>
                                </div>
                            </div>
                            <div class="student-actions">
                                <a href="../modules/departamento/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-primary btn-sm">
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
                            <p>Aún no hay estudiantes realizando su servicio social.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Proyectos Activos Section - NUEVA SECCIÓN INTEGRADA -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-project-diagram"></i>
                        Proyectos Activos Recientes
                    </h2>
                    <a href="../modules/departamento/proyectos.php" class="section-link">
                        Ver todos <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (!empty($proyectosRecientes)): ?>
                    <div class="requests-grid">
                        <?php foreach ($proyectosRecientes as $proyecto): ?>
                        <div class="request-card project-card">
                            <div class="request-header">
                                <div class="student-avatar project-avatar">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <div class="student-info">
                                    <h4><?= htmlspecialchars($proyecto['nombre_proyecto'] ?? 'Sin nombre') ?></h4>
                                    <p><?= htmlspecialchars($proyecto['laboratorio_asignado'] ?? 'Sin laboratorio asignado') ?></p>
                                </div>
                                <div class="request-date">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= formatDate($proyecto['created_at'] ?? date('Y-m-d')) ?></span>
                                </div>
                            </div>
                            
                            <div class="request-body">
                                <div class="project-info">
                                    <h5>Descripción del Proyecto</h5>
                                    <p><?= htmlspecialchars(shortenText($proyecto['descripcion'] ?? 'Sin descripción', 100)) ?></p>
                                    <?php if (!empty($proyecto['jefe_lab_nombre'])): ?>
                                    <small>Jefe de Laboratorio: <?= htmlspecialchars($proyecto['jefe_lab_nombre']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
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
                                    <span><?= $proyecto['cupo_ocupado'] ?? 0 ?>/<?= $proyecto['cupo_disponible'] ?? 0 ?> cupo</span>
                                </div>
                            </div>
                            
                            <div class="request-actions">
                                <a href="../modules/departamento/proyecto-detalle.php?id=<?= $proyecto['id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </a>
                                <a href="../modules/departamento/proyecto-editar.php?id=<?= $proyecto['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="../modules/departamento/proyectos.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list"></i> Gestionar
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="empty-content">
                            <h3>No hay proyectos activos</h3>
                            <p>Comienza creando tu primer proyecto de servicio social.</p>
                            <a href="../modules/departamento/proyecto-crear.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Proyecto
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="sidebar-column">
            <!-- Quick Actions Widget - ACTUALIZADO CON PROYECTOS -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-bolt"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="quick-actions-grid">
                        <a href="../modules/departamento/solicitudes.php" class="action-card">
                            <div class="action-icon solicitudes">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="action-text">
                                <span>Gestionar Solicitudes</span>
                                <small>Revisar y aprobar</small>
                            </div>
                            <div class="action-badge">
                                <?= $stats['solicitudes_pendientes'] ?? 0 ?>
                            </div>
                        </a>

                        <a href="../modules/departamento/estudiantes.php" class="action-card">
                            <div class="action-icon estudiantes">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="action-text">
                                <span>Ver Estudiantes</span>
                                <small>Gestionar progreso</small>
                            </div>
                            <div class="action-badge">
                                <?= $stats['servicios_activos'] ?? 0 ?>
                            </div>
                        </a>

                        <a href="../modules/departamento/proyectos.php" class="action-card">
                            <div class="action-icon proyectos">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="action-text">
                                <span>Proyectos</span>
                                <small>Gestionar proyectos</small>
                            </div>
                            <div class="action-badge">
                                <?= $stats['proyectos_activos'] ?? 0 ?>
                            </div>
                        </a>

                        <a href="../modules/departamento/laboratorios.php" class="action-card">
                            <div class="action-icon laboratorios">
                                <i class="fas fa-flask"></i>
                            </div>
                            <div class="action-text">
                                <span>Laboratorios</span>
                                <small>Administrar jefes</small>
                            </div>
                            <div class="action-badge">
                                <?= $stats['total_laboratorios'] ?? 0 ?>
                            </div>
                        </a>

                        <a href="../modules/departamento/reportes.php" class="action-card">
                            <div class="action-icon reportes">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="action-text">
                                <span>Reportes</span>
                                <small>Generar estadísticas</small>
                            </div>
                        </a>

                        <a href="../modules/departamento/proyecto-crear.php" class="action-card">
                            <div class="action-icon configuracion">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="action-text">
                                <span>Nuevo Proyecto</span>
                                <small>Crear proyecto</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>         

            <!-- Department Summary Widget - ACTUALIZADO -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-building"></i>
                        Resumen del Departamento
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
                                    <?= $stats['total_estudiantes'] > 0 ? round(($stats['servicios_activos'] / $stats['total_estudiantes']) * 100) : 0 ?>%
                                </span>
                                <span class="summary-label">Tasa de Actividad</span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="summary-data">
                                <span class="summary-value"><?= $stats['proyectos_activos'] ?? 0 ?></span>
                                <span class="summary-label">Proyectos Activos</span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="summary-data">
                                <span class="summary-value">85%</span>
                                <span class="summary-label">Tasa de Éxito</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Todo el CSS y JavaScript se mantiene igual -->
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
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
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

.stat-card.solicitudes {
    --gradient-color: var(--primary);
}

.stat-card.pendientes {
    --gradient-color: var(--warning);
}

.stat-card.activos {
    --gradient-color: var(--success);
}

.stat-card.horas {
    --gradient-color: var(--info);
}

.stat-card.laboratorios {
    --gradient-color: var(--secondary);
}

/* Estilos específicos para proyectos - INTEGRADO */
.stat-card.proyectos {
    --gradient-color: #8b5cf6;
}

.stat-card.proyectos .stat-icon {
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
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

.stat-card.solicitudes .stat-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card.pendientes .stat-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-card.activos .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.horas .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-card.laboratorios .stat-icon {
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
    font-size: 0.8rem;
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
    gap: 1.25rem;
}

/* Content Sections */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
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

/* Request Cards */
.requests-grid {
    display: grid;
    gap: 1rem;
}

.request-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.25rem;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.request-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.request-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    margin-bottom: 0.875rem;
}

.student-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}

/* Estilos específicos para avatares de proyectos - INTEGRADO */
.project-avatar {
    background: linear-gradient(135deg, #8b5cf6, #a78bfa) !important;
}

.project-avatar i {
    font-size: 1rem;
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

.request-date {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-light);
}

.request-body {
    margin-bottom: 0.875rem;
}

.project-info h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.375rem 0;
}

.project-info p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
}

.project-info small {
    color: var(--text-light);
    font-size: 0.8rem;
}

/* Estilos para estadísticas mini de proyectos - INTEGRADO */
.project-stats-mini {
    display: flex;
    gap: 1rem;
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
    font-size: 0.7rem;
    color: var(--primary);
}

.request-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Students List */
.students-list {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
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
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #34d399);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.student-details {
    flex: 1;
}

.student-details h4 {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.375rem 0;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.875rem;
    margin-bottom: 0.375rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.student-progress {
    font-size: 0.75rem;
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
    gap: 0.875rem;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.progress-circle-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: conic-gradient(var(--success) 0% var(--percentage), var(--bg-gray) 0% 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
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

.widget-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
}

.widget-content {
    padding: 1.5rem;
}

/* Quick Actions Grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
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
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    color: white;
    margin-bottom: 0.75rem;
}

.action-icon.solicitudes {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.action-icon.estudiantes {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.action-icon.laboratorios {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

.action-icon.reportes {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.action-icon.configuracion {
    background: linear-gradient(135deg, var(--text-secondary), var(--text-light));
}

.action-icon.proyectos {
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
}

.action-text {
    text-align: center;
}

.action-text span {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
    line-height: 1.2;
}

.action-text small {
    font-size: 0.7rem;
    color: var(--text-secondary);
    line-height: 1.1;
}

.action-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: var(--error);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
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

.activity-icon.pendiente {
    background: var(--warning);
}

.activity-icon.aprobada {
    background: var(--success);
}

.activity-icon.en_proceso {
    background: var(--info);
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
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.activity-date {
    font-size: 0.75rem;
    color: var(--text-light);
}

.empty-activities {
    text-align: center;
    padding: 1.5rem;
    color: var(--text-light);
}

.empty-activities i {
    font-size: 1.75rem;
    margin-bottom: 0.75rem;
}

/* Summary Stats */
.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.summary-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.summary-data {
    flex: 1;
}

.summary-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.summary-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
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
    padding: 0.5rem 0.75rem;
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

.btn-error {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-error:hover {
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
.statistics-overview > *:nth-child(5) { animation-delay: 0.5s; }
.statistics-overview > *:nth-child(6) { animation-delay: 0.6s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .main-content-area {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (max-width: 1024px) {
    .quick-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }
    
    .stat-icon {
        margin: 0 auto 0.75rem;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .request-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .student-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
    }
    
    .student-meta {
        flex-direction: column;
        gap: 0.375rem;
    }
    
    .request-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .request-actions .btn {
        flex: 1;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.375rem;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
        gap: 0.5rem;
    }
    
    .action-card {
        padding: 0.75rem;
    }
    
    .action-icon {
        width: 35px;
        height: 35px;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .content-section {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    
    .widget-content {
        padding: 1rem;
    }

    /* Ajustes responsivos para proyectos */
    .project-stats-mini {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-mini {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .stat-card {
        padding: 0.875rem;
    }
    
    .request-card,
    .student-item {
        padding: 0.875rem;
    }
    
    .widget-content {
        padding: 0.875rem;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .summary-stats {
        gap: 0.5rem;
    }
    
    .summary-item {
        padding: 0.625rem;
    }
    
    .empty-state {
        padding: 1.5rem 1rem;
    }
    
    .activity-item {
        padding: 0.75rem;
    }
}

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
    setInterval(updateTime, 60000); // Update every minute
    
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent.replace(/,/g, ''));
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                const displayNumber = Math.floor(Math.min(currentNumber, finalNumber));
                numberElement.textContent = displayNumber.toLocaleString();
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber.toLocaleString();
            }
        }
        
        // Stagger the animations
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.stat-card, .request-card, .student-item, .action-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.style.transform) {
                this.style.transform = 'translateY(-5px)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Progress circles animation
    const progressCircles = document.querySelectorAll('.progress-circle-small');
    progressCircles.forEach(circle => {
        const percentage = parseInt(circle.textContent);
        circle.style.background = `conic-gradient(var(--success) 0% ${percentage}%, var(--bg-gray) 0% 100%)`;
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Solo agregar loading si no es un enlace externo
            if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#')) {
                return; // Permitir navegación normal
            }
            
            this.classList.add('loading');
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
            
            setTimeout(() => {
                this.classList.remove('loading');
                this.innerHTML = originalText;
            }, 2000);
        });
    });
    
    // Add smooth scroll for internal links
    const internalLinks = document.querySelectorAll('a[href^="#"]');
    internalLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add ripple effect to buttons
    const rippleButtons = document.querySelectorAll('.btn');
    rippleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.height, rect.width);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            from {
                transform: scale(0);
                opacity: 1;
            }
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include '../includes/footer.php'; ?>