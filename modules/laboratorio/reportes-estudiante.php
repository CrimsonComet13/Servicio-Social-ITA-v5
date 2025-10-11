<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// Obtener perfil del jefe de laboratorio
$jefeLab = $db->fetch("
    SELECT jl.id, jl.nombre, jl.laboratorio, jl.especialidad, jl.telefono, jl.extension
    FROM jefes_laboratorio jl
    WHERE jl.usuario_id = :usuario_id
    AND jl.activo = 1
", ['usuario_id' => $usuarioId]);

if (!$jefeLab) {
    flashMessage('Error: No se encontró tu perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
    exit;
}

$jefeLabId = $jefeLab['id'];

// Obtener ID del estudiante
$estudianteId = $_GET['id'] ?? null;

if (!$estudianteId) {
    flashMessage('ID de estudiante no especificado', 'error');
    redirectTo('/modules/laboratorio/estudiantes-asignados.php');
    exit;
}

// Obtener información completa del estudiante
$estudiante = $db->fetch("
    SELECT 
        e.*,
        u.email as estudiante_email,
        u.ultimo_acceso,
        s.id as solicitud_id,
        s.estado as estado_servicio,
        s.fecha_solicitud,
        s.fecha_inicio_propuesta,
        s.fecha_fin_propuesta,
        s.fecha_aprobacion,
        p.id as proyecto_id,
        p.nombre_proyecto,
        p.horas_requeridas,
        jd.nombre as jefe_depto_nombre,
        jd.departamento
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE e.id = :estudiante_id
    AND s.jefe_laboratorio_id = :jefe_lab_id
    ORDER BY s.fecha_solicitud DESC
    LIMIT 1
", [
    'estudiante_id' => $estudianteId,
    'jefe_lab_id' => $jefeLabId
]);

if (!$estudiante) {
    flashMessage('Estudiante no encontrado o no tienes acceso a su información', 'error');
    redirectTo('/modules/laboratorio/estudiantes-asignados.php');
    exit;
}

// Obtener todos los reportes del estudiante
$reportes = $db->fetchAll("
    SELECT 
        r.*,
        CASE 
            WHEN r.numero_reporte = '1' THEN 'Primer Reporte Bimestral'
            WHEN r.numero_reporte = '2' THEN 'Segundo Reporte Bimestral'
            WHEN r.numero_reporte = '3' THEN 'Tercer Reporte Bimestral'
        END as nombre_reporte
    FROM reportes_bimestrales r
    WHERE r.estudiante_id = :estudiante_id
    AND r.solicitud_id = :solicitud_id
    ORDER BY r.numero_reporte ASC
", [
    'estudiante_id' => $estudianteId,
    'solicitud_id' => $estudiante['solicitud_id']
]);

// Calcular estadísticas
$totalReportes = count($reportes);
$reportesEntregados = count($reportes);
$reportesAprobados = count(array_filter($reportes, fn($r) => $r['estado'] === 'aprobado'));
$reportesPendientes = count(array_filter($reportes, fn($r) => $r['estado'] === 'pendiente_evaluacion'));
$reportesRechazados = count(array_filter($reportes, fn($r) => $r['estado'] === 'rechazado'));

// Calcular horas y calificaciones
$horasTotales = array_sum(array_column($reportes, 'horas_reportadas'));
$horasAprobadas = array_sum(array_map(function($r) {
    return $r['estado'] === 'aprobado' ? $r['horas_reportadas'] : 0;
}, $reportes));

$calificaciones = array_filter(array_column($reportes, 'calificacion'), fn($c) => $c !== null);
$promedioCalificacion = !empty($calificaciones) ? array_sum($calificaciones) / count($calificaciones) : null;

// Calcular progreso
$progreso = $estudiante['horas_requeridas'] > 0 ? 
    min(100, ($horasAprobadas / $estudiante['horas_requeridas']) * 100) : 0;

$pageTitle = "Reportes de " . $estudiante['nombre'] . " - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../../dashboard/jefe_laboratorio.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <i class="fas fa-chevron-right"></i>
            <a href="estudiantes-asignados.php">
                <i class="fas fa-users"></i> Estudiantes
            </a>
            <i class="fas fa-chevron-right"></i>
            <a href="estudiante-detalle.php?id=<?= $estudianteId ?>">
                <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?>
            </a>
            <i class="fas fa-chevron-right"></i>
            <span>Reportes</span>
        </div>

        <!-- Student Header Card -->
        <div class="student-header-card">
            <div class="student-header-content">
                <div class="student-avatar-large">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="student-header-info">
                    <div class="student-name-section">
                        <h1>Reportes Bimestrales</h1>
                        <h2 class="student-name"><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h2>
                    </div>
                    <div class="student-meta-info">
                        <div class="meta-item">
                            <i class="fas fa-id-card"></i>
                            <span><?= htmlspecialchars($estudiante['numero_control']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?= htmlspecialchars($estudiante['carrera']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-project-diagram"></i>
                            <span><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-file-alt"></i>
                            <span><?= $totalReportes ?> reportes entregados</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="quick-actions">
                <a href="estudiante-detalle.php?id=<?= $estudianteId ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Perfil
                </a>
                <?php if ($reportesPendientes > 0): ?>
                <a href="evaluar-reporte.php?estudiante_id=<?= $estudianteId ?>" class="btn btn-warning">
                    <i class="fas fa-star"></i>
                    Evaluar Pendientes (<?= $reportesPendientes ?>)
                </a>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <!-- Alert for pending evaluations -->
        <?php if ($reportesPendientes > 0): ?>
        <div class="alert-banner warning">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h3 class="alert-title">Evaluaciones Pendientes</h3>
                <p class="alert-description">
                    Hay <?= $reportesPendientes ?> <?= $reportesPendientes === 1 ? 'reporte pendiente' : 'reportes pendientes' ?> de evaluación. 
                    Por favor, revisa y evalúa los reportes para que el estudiante pueda continuar.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Grid -->
        <div class="statistics-grid">
            <div class="stat-card-detail total-reports">
                <div class="stat-card-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Total de Reportes</h3>
                    <div class="stat-card-number"><?= $totalReportes ?></div>
                    <div class="stat-card-detail-text">reportes entregados</div>
                    <div class="reports-breakdown">
                        <span class="breakdown-item success">
                            <i class="fas fa-check-circle"></i> <?= $reportesAprobados ?> aprobados
                        </span>
                        <?php if ($reportesPendientes > 0): ?>
                        <span class="breakdown-item warning">
                            <i class="fas fa-hourglass-half"></i> <?= $reportesPendientes ?> pendientes
                        </span>
                        <?php endif; ?>
                        <?php if ($reportesRechazados > 0): ?>
                        <span class="breakdown-item error">
                            <i class="fas fa-times-circle"></i> <?= $reportesRechazados ?> rechazados
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="stat-card-detail hours">
                <div class="stat-card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Horas Reportadas</h3>
                    <div class="stat-card-number"><?= $horasAprobadas ?></div>
                    <div class="stat-card-detail-text">de <?= $estudiante['horas_requeridas'] ?> horas requeridas</div>
                    <div class="mini-progress">
                        <div class="mini-progress-bar" style="width: <?= $progreso ?>%"></div>
                    </div>
                    <div class="stat-card-percentage"><?= number_format($progreso, 1) ?>% completado</div>
                </div>
            </div>

            <div class="stat-card-detail grade">
                <div class="stat-card-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Calificación Promedio</h3>
                    <div class="stat-card-number">
                        <?= $promedioCalificacion !== null ? number_format($promedioCalificacion, 1) : 'N/A' ?>
                    </div>
                    <div class="stat-card-detail-text">
                        <?php if ($promedioCalificacion !== null): ?>
                            <?php if ($promedioCalificacion >= 9): ?>
                                <span class="performance-badge excellent">Excelente</span>
                            <?php elseif ($promedioCalificacion >= 8): ?>
                                <span class="performance-badge good">Muy Bueno</span>
                            <?php elseif ($promedioCalificacion >= 7): ?>
                                <span class="performance-badge average">Bueno</span>
                            <?php else: ?>
                                <span class="performance-badge needs-improvement">Necesita Mejorar</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="performance-badge">Sin evaluar</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($calificaciones)): ?>
                    <div class="stat-card-detail-text" style="margin-top: 0.5rem;">
                        Basado en <?= count($calificaciones) ?> evaluaciones
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card-detail pending">
                <div class="stat-card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Estado General</h3>
                    <div class="stat-card-number"><?= $reportesAprobados ?>/<?= $totalReportes ?></div>
                    <div class="stat-card-detail-text">reportes aprobados</div>
                    <?php if ($reportesPendientes > 0): ?>
                    <div class="alert-mini warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $reportesPendientes ?> pendiente<?= $reportesPendientes > 1 ? 's' : '' ?>
                    </div>
                    <?php else: ?>
                    <div class="alert-mini success">
                        <i class="fas fa-check-circle"></i>
                        Todo al corriente
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reports Timeline -->
        <div class="reports-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Historial de Reportes
                </h2>
                <div class="section-info">
                    <span class="info-badge">
                        <i class="fas fa-calendar"></i>
                        Período: <?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($reportes)): ?>
                <div class="reportes-timeline">
                    <?php foreach ($reportes as $index => $reporte): ?>
                    <div class="reporte-timeline-item" data-reporte-id="<?= $reporte['id'] ?>">
                        <div class="timeline-marker <?= getEstadoBadgeClass($reporte['estado']) ?>">
                            <div class="marker-number"><?= $reporte['numero_reporte'] ?></div>
                            <div class="marker-icon">
                                <i class="fas <?= getEstadoIcon($reporte['estado']) ?>"></i>
                            </div>
                        </div>
                        
                        <div class="reporte-card">
                            <div class="reporte-header">
                                <div class="reporte-title-section">
                                    <h3 class="reporte-title"><?= $reporte['nombre_reporte'] ?></h3>
                                    <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                        <i class="fas <?= getEstadoIcon($reporte['estado']) ?>"></i>
                                        <?= getEstadoText($reporte['estado']) ?>
                                    </span>
                                </div>
                                <div class="reporte-dates">
                                    <div class="date-item">
                                        <i class="fas fa-calendar"></i>
                                        <div>
                                            <span class="date-label">Periodo</span>
                                            <span class="date-value"><?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?></span>
                                        </div>
                                    </div>
                                    <div class="date-item">
                                        <i class="fas fa-upload"></i>
                                        <div>
                                            <span class="date-label">Entregado</span>
                                            <span class="date-value"><?= formatDate($reporte['fecha_entrega']) ?></span>
                                        </div>
                                    </div>
                                    <?php if ($reporte['fecha_evaluacion']): ?>
                                    <div class="date-item">
                                        <i class="fas fa-user-check"></i>
                                        <div>
                                            <span class="date-label">Evaluado</span>
                                            <span class="date-value"><?= formatDate($reporte['fecha_evaluacion']) ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="reporte-body">
                                <!-- Stats Row -->
                                <div class="reporte-stats-row">
                                    <div class="reporte-stat">
                                        <div class="stat-icon hours">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div>
                                            <span class="stat-value"><?= $reporte['horas_reportadas'] ?></span>
                                            <span class="stat-label">Horas Reportadas</span>
                                        </div>
                                    </div>
                                    <div class="reporte-stat">
                                        <div class="stat-icon accumulated">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div>
                                            <span class="stat-value"><?= $reporte['horas_acumuladas'] ?></span>
                                            <span class="stat-label">Horas Acumuladas</span>
                                        </div>
                                    </div>
                                    <?php if ($reporte['calificacion']): ?>
                                    <div class="reporte-stat">
                                        <div class="stat-icon grade">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div>
                                            <span class="stat-value"><?= number_format($reporte['calificacion'], 1) ?></span>
                                            <span class="stat-label">Calificación</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="reporte-stat">
                                        <div class="stat-icon progress">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                        <div>
                                            <span class="stat-value"><?= number_format(min(100, ($reporte['horas_acumuladas'] / $estudiante['horas_requeridas']) * 100), 1) ?>%</span>
                                            <span class="stat-label">Progreso Total</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content Sections -->
                                <div class="reporte-content">
                                    <div class="content-section">
                                        <h4><i class="fas fa-tasks"></i> Actividades Realizadas</h4>
                                        <p><?= nl2br(htmlspecialchars($reporte['actividades_realizadas'])) ?></p>
                                    </div>

                                    <?php if ($reporte['logros_obtenidos']): ?>
                                    <div class="content-section">
                                        <h4><i class="fas fa-trophy"></i> Logros Obtenidos</h4>
                                        <p><?= nl2br(htmlspecialchars($reporte['logros_obtenidos'])) ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($reporte['dificultades_encontradas']): ?>
                                    <div class="content-section">
                                        <h4><i class="fas fa-exclamation-triangle"></i> Dificultades Encontradas</h4>
                                        <p><?= nl2br(htmlspecialchars($reporte['dificultades_encontradas'])) ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($reporte['aprendizajes']): ?>
                                    <div class="content-section">
                                        <h4><i class="fas fa-lightbulb"></i> Aprendizajes</h4>
                                        <p><?= nl2br(htmlspecialchars($reporte['aprendizajes'])) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Evaluation Section -->
                                <?php if ($reporte['estado'] === 'aprobado' || $reporte['estado'] === 'rechazado'): ?>
                                <div class="evaluacion-section <?= $reporte['estado'] === 'aprobado' ? 'approved' : 'rejected' ?>">
                                    <div class="evaluacion-header">
                                        <h4>
                                            <i class="fas fa-clipboard-check"></i>
                                            Evaluación del Jefe de Laboratorio
                                        </h4>
                                        <?php if ($reporte['fecha_evaluacion']): ?>
                                        <span class="evaluacion-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= formatDateTime($reporte['fecha_evaluacion']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="evaluacion-content">
                                        <?php if ($reporte['fortalezas']): ?>
                                        <div class="evaluacion-item success">
                                            <strong><i class="fas fa-thumbs-up"></i> Fortalezas:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['fortalezas'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['areas_mejora']): ?>
                                        <div class="evaluacion-item warning">
                                            <strong><i class="fas fa-chart-line"></i> Áreas de Mejora:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['areas_mejora'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['observaciones_evaluador']): ?>
                                        <div class="evaluacion-item info">
                                            <strong><i class="fas fa-comment"></i> Observaciones Generales:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['observaciones_evaluador'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['recomendaciones']): ?>
                                        <div class="evaluacion-item primary">
                                            <strong><i class="fas fa-lightbulb"></i> Recomendaciones:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['recomendaciones'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="reporte-actions">
                                <?php if ($reporte['estado'] === 'pendiente_evaluacion'): ?>
                                <a href="evaluar-reporte.php?id=<?= $reporte['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-star"></i> Evaluar Reporte
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-secondary" onclick="toggleReporteDetail(<?= $reporte['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                    <span class="toggle-text">Ver Detalle Completo</span>
                                </button>
                                <?php if ($reporte['estado'] === 'aprobado'): ?>
                                <button class="btn btn-secondary" onclick="printReporte(<?= $reporte['id'] ?>)">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Section -->
                <div class="summary-card">
                    <div class="summary-header">
                        <h3><i class="fas fa-chart-bar"></i> Resumen General del Desempeño</h3>
                    </div>
                    <div class="summary-body">
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-icon total">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-value"><?= $totalReportes ?></span>
                                    <span class="summary-label">Reportes Entregados</span>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-icon approved">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-value"><?= $reportesAprobados ?></span>
                                    <span class="summary-label">Reportes Aprobados</span>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-icon hours">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-value"><?= $horasAprobadas ?></span>
                                    <span class="summary-label">Horas Acreditadas</span>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-icon grade">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="summary-content">
                                    <span class="summary-value">
                                        <?= $promedioCalificacion !== null ? number_format($promedioCalificacion, 1) : 'N/A' ?>
                                    </span>
                                    <span class="summary-label">Promedio General</span>
                                </div>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div class="progress-info-detailed">
                                <div class="progress-label-detailed">
                                    <i class="fas fa-chart-line"></i>
                                    Progreso Total del Servicio Social
                                </div>
                                <div class="progress-value-detailed">
                                    <?= $horasAprobadas ?> / <?= $estudiante['horas_requeridas'] ?> horas
                                    <span class="progress-percentage-detailed">(<?= number_format($progreso, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="progress-bar-detailed">
                                <div class="progress-fill-detailed" style="width: <?= $progreso ?>%">
                                    <span class="progress-text-inner"><?= number_format($progreso, 1) ?>%</span>
                                </div>
                            </div>
                            <div class="progress-milestones">
                                <div class="milestone <?= $progreso >= 25 ? 'reached' : '' ?>">
                                    <div class="milestone-marker">25%</div>
                                    <div class="milestone-label">1er Cuarto</div>
                                </div>
                                <div class="milestone <?= $progreso >= 50 ? 'reached' : '' ?>">
                                    <div class="milestone-marker">50%</div>
                                    <div class="milestone-label">Mitad</div>
                                </div>
                                <div class="milestone <?= $progreso >= 75 ? 'reached' : '' ?>">
                                    <div class="milestone-marker">75%</div>
                                    <div class="milestone-label">3er Cuarto</div>
                                </div>
                                <div class="milestone <?= $progreso >= 100 ? 'reached' : '' ?>">
                                    <div class="milestone-marker">100%</div>
                                    <div class="milestone-label">Completado</div>
                                </div>
                            </div>
                        </div>

                        <?php if ($promedioCalificacion !== null): ?>
                        <div class="performance-analysis">
                            <h4><i class="fas fa-analytics"></i> Análisis de Desempeño</h4>
                            <div class="performance-content">
                                <div class="performance-indicator">
                                    <div class="indicator-gauge">
                                        <div class="gauge-fill" style="width: <?= ($promedioCalificacion / 10) * 100 ?>%"></div>
                                    </div>
                                    <div class="indicator-labels">
                                        <span>0</span>
                                        <span>5</span>
                                        <span>10</span>
                                    </div>
                                </div>
                                <div class="performance-text">
                                    <?php if ($promedioCalificacion >= 9): ?>
                                    <p class="performance-excellent">
                                        <i class="fas fa-trophy"></i>
                                        <strong>Desempeño Excelente:</strong> El estudiante ha demostrado un rendimiento sobresaliente, 
                                        cumpliendo y superando las expectativas del proyecto. Continúa con este nivel de compromiso.
                                    </p>
                                    <?php elseif ($promedioCalificacion >= 8): ?>
                                    <p class="performance-good">
                                        <i class="fas fa-thumbs-up"></i>
                                        <strong>Muy Buen Desempeño:</strong> El estudiante muestra un rendimiento consistente y de calidad. 
                                        Sigue trabajando en mantener este nivel de excelencia.
                                    </p>
                                    <?php elseif ($promedioCalificacion >= 7): ?>
                                    <p class="performance-average">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Buen Desempeño:</strong> El estudiante cumple con los requisitos del proyecto. 
                                        Hay oportunidades para mejorar y destacar en las siguientes evaluaciones.
                                    </p>
                                    <?php else: ?>
                                    <p class="performance-needs-improvement">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Necesita Mejorar:</strong> El estudiante debe poner mayor atención a los comentarios 
                                        y recomendaciones para elevar su nivel de desempeño.
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Sin Reportes Aún</h3>
                    <p>El estudiante aún no ha entregado ningún reporte bimestral.</p>
                    <p class="empty-note">Los reportes aparecerán aquí una vez que el estudiante los envíe para evaluación.</p>
                </div>
            <?php endif; ?>
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

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.breadcrumb a {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb a:hover {
    color: var(--primary);
}

.breadcrumb i.fa-chevron-right {
    font-size: 0.7rem;
}

.breadcrumb span {
    color: var(--text-primary);
    font-weight: 500;
}

/* Student Header Card */
.student-header-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.student-header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
}

.student-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}

.student-header-info {
    flex: 1;
}

.student-name-section h1 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    font-weight: 600;
    opacity: 0.9;
}

.student-name {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
}

.student-meta-info {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.meta-item i {
    opacity: 0.8;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Alert Banner */
.alert-banner {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    border-left: 4px solid;
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.alert-banner.warning {
    background: rgba(245, 158, 11, 0.1);
    border-left-color: var(--warning);
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.alert-banner.warning .alert-icon {
    background: var(--warning);
    color: white;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.alert-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

/* Statistics Grid */
.statistics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card-detail {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    gap: 1rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card-detail::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--card-color);
}

.stat-card-detail:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card-detail.total-reports { --card-color: var(--secondary); }
.stat-card-detail.hours { --card-color: var(--primary); }
.stat-card-detail.grade { --card-color: var(--warning); }
.stat-card-detail.pending { --card-color: var(--info); }

.stat-card-icon {
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

.stat-card-detail.total-reports .stat-card-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }
.stat-card-detail.hours .stat-card-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card-detail.grade .stat-card-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.stat-card-detail.pending .stat-card-icon { background: linear-gradient(135deg, var(--info), #60a5fa); }

.stat-card-content {
    flex: 1;
}

.stat-card-content h3 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-card-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-card-detail-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
}

.mini-progress {
    height: 6px;
    background: var(--bg-gray);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.mini-progress-bar {
    height: 100%;
    background: var(--card-color);
    transition: width 1s ease;
}

.stat-card-percentage {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.reports-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.breakdown-item {
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.breakdown-item.success { color: var(--success); }
.breakdown-item.warning { color: var(--warning); }
.breakdown-item.error { color: var(--error); }

.performance-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.performance-badge.excellent { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.performance-badge.good { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }
.performance-badge.average { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.performance-badge.needs-improvement { background: rgba(239, 68, 68, 0.1); color: var(--error); }

.alert-mini {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.alert-mini.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.alert-mini.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

/* Reports Section */
.reports-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-light);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-title i {
    color: var(--primary);
}

.section-info {
    display: flex;
    gap: 1rem;
}

.info-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* Reportes Timeline */
.reportes-timeline {
    position: relative;
    padding-left: 3rem;
}

.reportes-timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--primary), var(--primary-light), var(--border));
    border-radius: 2px;
}

.reporte-timeline-item {
    position: relative;
    margin-bottom: 3rem;
    padding-left: 2rem;
}

.reporte-timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -3rem;
    top: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    z-index: 1;
}

.marker-number {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    box-shadow: var(--shadow-lg);
    border: 4px solid var(--bg-white);
}

.marker-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    box-shadow: var(--shadow);
}

.timeline-marker.badge-success .marker-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.timeline-marker.badge-warning .marker-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.timeline-marker.badge-error .marker-icon {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.timeline-marker.badge-primary .marker-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.reporte-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 2rem;
    transition: var(--transition);
    border: 2px solid transparent;
}

.reporte-timeline-item:hover .reporte-card {
    transform: translateX(5px);
    background: var(--bg-white);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.reporte-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border);
}

.reporte-title-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.reporte-title {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text-primary);
    font-weight: 600;
}

.reporte-dates {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.date-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.date-item i {
    color: var(--primary);
    font-size: 1.125rem;
    margin-top: 0.125rem;
}

.date-item > div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.date-value {
    font-size: 0.9rem;
    color: var(--text-primary);
    font-weight: 500;
}

.reporte-body {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.reporte-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.reporte-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    color: white;
    flex-shrink: 0;
}

.stat-icon.hours { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-icon.accumulated { background: linear-gradient(135deg, var(--success), #34d399); }
.stat-icon.grade { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.stat-icon.progress { background: linear-gradient(135deg, var(--info), #60a5fa); }

.reporte-stat > div {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.reporte-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.content-section {
    background: var(--bg-white);
    padding: 1.5rem;
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.content-section h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    color: var(--text-primary);
}

.content-section h4 i {
    color: var(--primary);
}

.content-section p {
    color: var(--text-secondary);
    line-height: 1.7;
    margin: 0;
}

.evaluacion-section {
    background: var(--bg-white);
    padding: 2rem;
    border-radius: var(--radius-lg);
    border: 2px solid var(--border);
}

.evaluacion-section.approved {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.02);
}

.evaluacion-section.rejected {
    border-color: var(--error);
    background: rgba(239, 68, 68, 0.02);
}

.evaluacion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.evaluacion-header h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.evaluacion-date {
    font-size: 0.85rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.evaluacion-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.evaluacion-item {
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 4px solid;
}

.evaluacion-item.success {
    background: rgba(16, 185, 129, 0.05);
    border-color: var(--success);
}

.evaluacion-item.warning {
    background: rgba(245, 158, 11, 0.05);
    border-color: var(--warning);
}

.evaluacion-item.info {
    background: rgba(33, 150, 243, 0.05);
    border-color: var(--info);
}

.evaluacion-item.primary {
    background: rgba(76, 175, 80, 0.05);
    border-color: var(--primary);
}

.evaluacion-item strong {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.evaluacion-item p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.6;
}

.reporte-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

/* Summary Card */
.summary-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-top: 3rem;
    border: 2px solid var(--primary);
}

.summary-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.summary-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.375rem;
    color: var(--text-primary);
}

.summary-header h3 i {
    color: var(--primary);
}

.summary-body {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
}

.summary-item {
    background: var(--bg-white);
    padding: 1.5rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}

.summary-item:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.summary-icon.total { background: linear-gradient(135deg, var(--secondary), #42a5f5); }
.summary-icon.approved { background: linear-gradient(135deg, var(--success), #34d399); }
.summary-icon.hours { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.summary-icon.grade { background: linear-gradient(135deg, var(--warning), #fbbf24); }

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.summary-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.progress-section {
    background: var(--bg-white);
    padding: 2rem;
    border-radius: var(--radius);
}

.progress-info-detailed {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.progress-label-detailed {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-value-detailed {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-percentage-detailed {
    font-weight: 700;
    color: var(--primary);
}

.progress-bar-detailed {
    height: 30px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
    position: relative;
    margin-bottom: 1.5rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.progress-fill-detailed {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 1rem;
    transition: width 1.5s ease;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 0.75rem;
    position: relative;
    overflow: hidden;
}

.progress-fill-detailed::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-text-inner {
    font-size: 0.85rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    z-index: 1;
}

.progress-milestones {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.milestone {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.milestone-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--bg-gray);
    color: var(--text-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    border: 3px solid var(--border);
    transition: var(--transition);
}

.milestone.reached .milestone-marker {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
    border-color: var(--success);
    box-shadow: var(--shadow);
}

.milestone-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.milestone.reached .milestone-label {
    color: var(--success);
}

.performance-analysis {
    background: var(--bg-white);
    padding: 2rem;
    border-radius: var(--radius);
}

.performance-analysis h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1.5rem 0;
    font-size: 1.125rem;
    color: var(--text-primary);
}

.performance-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.performance-indicator {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.indicator-gauge {
    height: 20px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
    position: relative;
}

.gauge-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--error), var(--warning), var(--success));
    border-radius: 1rem;
    transition: width 1s ease;
}

.indicator-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-light);
    padding: 0 0.5rem;
}

.performance-text {
    padding: 1.5rem;
    border-radius: var(--radius);
    border-left: 4px solid;
}

.performance-text p {
    margin: 0;
    line-height: 1.7;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.performance-text i {
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.performance-excellent {
    background: rgba(16, 185, 129, 0.05);
    border-color: var(--success);
    color: var(--text-primary);
}

.performance-excellent i {
    color: var(--success);
}

.performance-good {
    background: rgba(33, 150, 243, 0.05);
    border-color: var(--info);
    color: var(--text-primary);
}

.performance-good i {
    color: var(--info);
}

.performance-average {
    background: rgba(245, 158, 11, 0.05);
    border-color: var(--warning);
    color: var(--text-primary);
}

.performance-average i {
    color: var(--warning);
}

.performance-needs-improvement {
    background: rgba(239, 68, 68, 0.05);
    border-color: var(--error);
    color: var(--text-primary);
}

.performance-needs-improvement i {
    color: var(--error);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--text-light);
    margin: 0 auto 1.5rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-state p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 0.5rem;
}

.empty-note {
    font-size: 0.9rem;
    color: var(--text-light);
    font-style: italic;
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

.badge-primary { background: rgba(76, 175, 80, 0.1); color: var(--primary); }
.badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.badge-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }

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

.statistics-grid > *,
.reporte-timeline-item {
    animation: slideIn 0.6s ease-out;
}

.statistics-grid > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-grid > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-grid > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-grid > *:nth-child(4) { animation-delay: 0.4s; }

/* Print Styles */
@media print {
    .quick-actions,
    .breadcrumb,
    .reporte-actions,
    .alert-banner {
        display: none !important;
    }
    
    .main-wrapper {
        margin-left: 0;
    }
    
    .reporte-card {
        page-break-inside: avoid;
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 1024px) {
    .student-header-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .quick-actions {
        width: 100%;
        flex-direction: row;
    }
    
    .reporte-stats-row {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .student-header-card {
        padding: 1.5rem;
    }
    
    .student-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .statistics-grid {
        grid-template-columns: 1fr;
    }
    
    .reportes-timeline {
        padding-left: 2rem;
    }
    
    .reportes-timeline::before {
        left: 15px;
    }
    
    .timeline-marker {
        left: -2rem;
    }
    
    .marker-number {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .reporte-card {
        padding: 1.5rem;
    }
    
    .reporte-dates {
        grid-template-columns: 1fr;
    }
    
    .reporte-stats-row {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-milestones {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .student-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 2rem;
    }
    
    .student-name {
        font-size: 1.25rem;
    }
    
    .quick-actions {
        flex-direction: column;
    }
    
    .reporte-title {
        font-size: 1.25rem;
    }
    
    .reporte-actions {
        flex-direction: column;
    }
    
    .reporte-actions .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.mini-progress-bar, .progress-fill-detailed, .gauge-fill');
        progressBars.forEach((bar, index) => {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = width;
            }, index * 100);
        });
    }, 300);
    
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-card-number, .stat-value, .summary-value');
    statNumbers.forEach((numberElement, index) => {
        const text = numberElement.textContent.trim();
        
        // Check if it's a number
        if (!isNaN(parseFloat(text))) {
            const finalNumber = parseFloat(text);
            let currentNumber = 0;
            const increment = finalNumber / 30;
            
            function animateNumber() {
                if (currentNumber < finalNumber) {
                    currentNumber += increment;
                    const displayNumber = Math.min(currentNumber, finalNumber);
                    
                    // Handle decimal numbers
                    if (text.includes('.')) {
                        numberElement.textContent = displayNumber.toFixed(1);
                    } else {
                        numberElement.textContent = Math.floor(displayNumber);
                    }
                    
                    requestAnimationFrame(animateNumber);
                } else {
                    numberElement.textContent = text;
                }
            }
            
            setTimeout(() => {
                animateNumber();
            }, index * 100);
        }
    });
    
    // Add smooth scroll to reporte cards when clicking on them
    const reporteCards = document.querySelectorAll('.reporte-card');
    reporteCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons or links
            if (e.target.closest('.btn') || e.target.closest('a')) {
                return;
            }
            
            // Smooth scroll to card
            card.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            
            // Add highlight effect
            card.style.transition = 'all 0.3s ease';
            card.style.transform = 'scale(1.02)';
            setTimeout(() => {
                card.style.transform = '';
            }, 300);
        });
    });
});

function toggleReporteDetail(reporteId) {
    const reporteItem = document.querySelector(`[data-reporte-id="${reporteId}"]`);
    const content = reporteItem.querySelector('.reporte-content');
    const evaluacion = reporteItem.querySelector('.evaluacion-section');
    const toggleBtn = reporteItem.querySelector('.reporte-actions .btn-secondary');
    const toggleText = toggleBtn.querySelector('.toggle-text');
    
    if (content.style.display === 'none') {
        content.style.display = 'flex';
        if (evaluacion) evaluacion.style.display = 'block';
        toggleText.textContent = 'Ocultar Detalle';
        toggleBtn.querySelector('i').className = 'fas fa-eye-slash';
    } else {
        content.style.display = 'none';
        if (evaluacion) evaluacion.style.display = 'none';
        toggleText.textContent = 'Ver Detalle Completo';
        toggleBtn.querySelector('i').className = 'fas fa-eye';
    }
}

function printReporte(reporteId) {
    const reporteItem = document.querySelector(`[data-reporte-id="${reporteId}"]`);
    const printWindow = window.open('', '_blank');
    
    const styles = `
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                padding: 2rem;
                color: #1f2937;
            }
            h3, h4 { color: #6366f1; }
            .reporte-card { background: white; }
            .reporte-actions { display: none; }
            .content-section { 
                margin-bottom: 1.5rem;
                padding: 1rem;
                border-left: 4px solid #6366f1;
                background: #f9fafb;
            }
        </style>
    `;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reporte Bimestral - Impresión</title>
            ${styles}
        </head>
        <body>
            ${reporteItem.innerHTML}
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                }
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

console.log('✅ Página de reportes del estudiante inicializada');
</script>

<?php
// Helper function for estado icons
function getEstadoIcon($estado) {
    $icons = [
        'pendiente_evaluacion' => 'hourglass-half',
        'aprobado' => 'check-circle',
        'rechazado' => 'times-circle',
        'en_revision' => 'search'
    ];
    return $icons[$estado] ?? 'question-circle';
}

include '../../includes/footer.php';
?>