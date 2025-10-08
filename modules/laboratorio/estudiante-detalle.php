<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// Obtener ID del estudiante
$estudianteId = $_GET['id'] ?? null;

if (!$estudianteId) {
    flashMessage('ID de estudiante no especificado', 'error');
    redirectTo('/modules/laboratorio/estudiantes-asignados.php');
    exit;
}

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
        s.motivo_solicitud,
        s.observaciones_estudiante,
        s.observaciones_jefe,
        p.id as proyecto_id,
        p.nombre_proyecto,
        p.descripcion as proyecto_descripcion,
        p.horas_requeridas,
        p.tipo_actividades,
        p.objetivos,
        p.modalidad,
        p.horario,
        jd.id as jefe_departamento_id,
        jd.nombre as jefe_depto_nombre,
        jd.email as jefe_depto_email,
        jd.departamento,
        jd.telefono as jefe_depto_telefono
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

// Calcular horas completadas desde reportes aprobados
$horasData = $db->fetch("
    SELECT 
        COALESCE(SUM(CASE WHEN r.estado = 'aprobado' THEN r.horas_reportadas ELSE 0 END), 0) as horas_completadas,
        COALESCE(SUM(r.horas_reportadas), 0) as horas_reportadas_total
    FROM reportes_bimestrales r
    WHERE r.estudiante_id = :estudiante_id
    AND r.solicitud_id = :solicitud_id
", [
    'estudiante_id' => $estudianteId,
    'solicitud_id' => $estudiante['solicitud_id']
]);

$estudiante['horas_completadas'] = $horasData['horas_completadas'];
$estudiante['horas_reportadas_total'] = $horasData['horas_reportadas_total'];
$estudiante['progreso'] = min(100, ($estudiante['horas_completadas'] / $estudiante['horas_requeridas']) * 100);

// Obtener reportes del estudiante
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

// Calcular estadísticas de reportes
$totalReportes = count($reportes);
$reportesAprobados = count(array_filter($reportes, fn($r) => $r['estado'] === 'aprobado'));
$reportesPendientes = count(array_filter($reportes, fn($r) => $r['estado'] === 'pendiente_evaluacion'));
$reportesRechazados = count(array_filter($reportes, fn($r) => $r['estado'] === 'rechazado'));

// Calcular calificación promedio
$calificaciones = array_filter(array_column($reportes, 'calificacion'), fn($c) => $c !== null);
$promedioCalificacion = !empty($calificaciones) ? array_sum($calificaciones) / count($calificaciones) : null;

// Obtener historial de estados
$historial = $db->fetchAll("
    SELECT 
        h.*,
        u.email as usuario_email,
        CASE 
            WHEN jd.id IS NOT NULL THEN jd.nombre
            WHEN jl.id IS NOT NULL THEN jl.nombre
            ELSE 'Sistema'
        END as usuario_nombre
    FROM historial_estados h
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id
    LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id
    WHERE h.solicitud_id = :solicitud_id
    ORDER BY h.fecha_cambio DESC
", ['solicitud_id' => $estudiante['solicitud_id']]);

// Obtener documentos generados
$documentos = [];

// Verificar oficio de presentación
$oficioExiste = $db->fetch("
    SELECT o.*, u.email as generador_email
    FROM oficios_presentacion o
    LEFT JOIN usuarios u ON o.generado_por = u.id
    WHERE o.solicitud_id = :solicitud_id
    LIMIT 1
", ['solicitud_id' => $estudiante['solicitud_id']]);

if ($oficioExiste) {
    $documentos[] = [
        'tipo' => 'Oficio de Presentación',
        'numero' => $oficioExiste['numero_oficio'],
        'fecha' => $oficioExiste['fecha_emision'],
        'estado' => $oficioExiste['estado'],
        'icono' => 'fa-file-alt'
    ];
}

// Calcular días transcurridos y restantes
$fechaInicio = new DateTime($estudiante['fecha_inicio_propuesta']);
$fechaFin = new DateTime($estudiante['fecha_fin_propuesta']);
$fechaActual = new DateTime();

$diasTranscurridos = max(0, $fechaInicio->diff($fechaActual)->days);
$diasRestantes = max(0, $fechaActual->diff($fechaFin)->days);
$diasTotales = $fechaInicio->diff($fechaFin)->days;
$porcentajeTiempo = min(100, ($diasTranscurridos / $diasTotales) * 100);

$pageTitle = "Detalle del Estudiante - " . APP_NAME;
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
            <span><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></span>
        </div>

        <!-- Student Header Card -->
        <div class="student-header-card">
            <div class="student-header-content">
                <div class="student-avatar-large">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="student-header-info">
                    <div class="student-name-section">
                        <h1><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h1>
                        <span class="badge-large <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                            <i class="fas <?= getEstadoIcon($estudiante['estado_servicio']) ?>"></i>
                            <?= getEstadoText($estudiante['estado_servicio']) ?>
                        </span>
                    </div>
                    <div class="student-meta-info">
                        <div class="meta-item">
                            <i class="fas fa-id-card"></i>
                            <span><?= htmlspecialchars($estudiante['numero_control']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($estudiante['estudiante_email']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($estudiante['telefono'] ?? 'No especificado') ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?= htmlspecialchars($estudiante['carrera']) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-layer-group"></i>
                            <span><?= $estudiante['semestre'] ?>° Semestre</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="quick-actions">
                <a href="reportes-estudiante.php?id=<?= $estudianteId ?>" class="btn btn-primary">
                    <i class="fas fa-file-alt"></i>
                    Ver Reportes
                </a>
                <?php if ($reportesPendientes > 0): ?>
                <a href="evaluar-estudiante.php?id=<?= $estudiante['solicitud_id'] ?>" class="btn btn-warning">
                    <i class="fas fa-star"></i>
                    Evaluar (<?= $reportesPendientes ?>)
                </a>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="statistics-grid">
            <div class="stat-card-detail hours">
                <div class="stat-card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Horas Completadas</h3>
                    <div class="stat-card-number"><?= $estudiante['horas_completadas'] ?></div>
                    <div class="stat-card-detail-text">de <?= $estudiante['horas_requeridas'] ?> horas requeridas</div>
                    <div class="mini-progress">
                        <div class="mini-progress-bar" style="width: <?= $estudiante['progreso'] ?>%"></div>
                    </div>
                    <div class="stat-card-percentage"><?= number_format($estudiante['progreso'], 1) ?>% completado</div>
                </div>
            </div>

            <div class="stat-card-detail reports">
                <div class="stat-card-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Reportes</h3>
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
                    </div>
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
                </div>
            </div>

            <div class="stat-card-detail time">
                <div class="stat-card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-card-content">
                    <h3>Tiempo Transcurrido</h3>
                    <div class="stat-card-number"><?= $diasTranscurridos ?></div>
                    <div class="stat-card-detail-text">de <?= $diasTotales ?> días totales</div>
                    <div class="mini-progress">
                        <div class="mini-progress-bar" style="width: <?= $porcentajeTiempo ?>%"></div>
                    </div>
                    <div class="stat-card-percentage">
                        <?= $diasRestantes ?> días restantes
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-button active" data-tab="general">
                    <i class="fas fa-info-circle"></i>
                    Información General
                </button>
                <button class="tab-button" data-tab="proyecto">
                    <i class="fas fa-project-diagram"></i>
                    Proyecto
                </button>
                <button class="tab-button" data-tab="reportes">
                    <i class="fas fa-file-alt"></i>
                    Reportes (<?= $totalReportes ?>)
                </button>
                <button class="tab-button" data-tab="historial">
                    <i class="fas fa-history"></i>
                    Historial
                </button>
                <button class="tab-button" data-tab="documentos">
                    <i class="fas fa-folder"></i>
                    Documentos (<?= count($documentos) ?>)
                </button>
            </div>

            <!-- Tab: Información General -->
            <div class="tab-content active" id="tab-general">
                <div class="content-grid">
                    <!-- Información Académica -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                        </div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Carrera:</span>
                                <span class="info-value"><?= htmlspecialchars($estudiante['carrera']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Semestre:</span>
                                <span class="info-value"><?= $estudiante['semestre'] ?>°</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Créditos Cursados:</span>
                                <span class="info-value"><?= $estudiante['creditos_cursados'] ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Número de Control:</span>
                                <span class="info-value"><?= htmlspecialchars($estudiante['numero_control']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><i class="fas fa-address-card"></i> Información de Contacto</h3>
                        </div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Email Institucional:</span>
                                <span class="info-value">
                                    <a href="mailto:<?= htmlspecialchars($estudiante['estudiante_email']) ?>">
                                        <?= htmlspecialchars($estudiante['estudiante_email']) ?>
                                    </a>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Teléfono:</span>
                                <span class="info-value"><?= htmlspecialchars($estudiante['telefono'] ?? 'No especificado') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Último Acceso:</span>
                                <span class="info-value">
                                    <?= $estudiante['ultimo_acceso'] ? formatDateTime($estudiante['ultimo_acceso']) : 'Nunca' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Servicio Social -->
                    <div class="info-card full-width">
                        <div class="info-card-header">
                            <h3><i class="fas fa-briefcase"></i> Información del Servicio Social</h3>
                        </div>
                        <div class="info-card-body">
                            <div class="info-grid">
                                <div class="info-row">
                                    <span class="info-label">Estado del Servicio:</span>
                                    <span class="info-value">
                                        <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                                            <?= getEstadoText($estudiante['estado_servicio']) ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Fecha de Solicitud:</span>
                                    <span class="info-value"><?= formatDate($estudiante['fecha_solicitud']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Fecha de Aprobación:</span>
                                    <span class="info-value"><?= $estudiante['fecha_aprobacion'] ? formatDateTime($estudiante['fecha_aprobacion']) : 'N/A' ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Periodo de Servicio:</span>
                                    <span class="info-value">
                                        <?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Departamento:</span>
                                    <span class="info-value"><?= htmlspecialchars($estudiante['departamento']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Jefe de Departamento:</span>
                                    <span class="info-value"><?= htmlspecialchars($estudiante['jefe_depto_nombre']) ?></span>
                                </div>
                            </div>

                            <?php if ($estudiante['motivo_solicitud']): ?>
                            <div class="info-section">
                                <h4>Motivo de la Solicitud:</h4>
                                <p class="text-content"><?= nl2br(htmlspecialchars($estudiante['motivo_solicitud'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($estudiante['observaciones_estudiante']): ?>
                            <div class="info-section">
                                <h4>Observaciones del Estudiante:</h4>
                                <p class="text-content"><?= nl2br(htmlspecialchars($estudiante['observaciones_estudiante'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($estudiante['observaciones_jefe']): ?>
                            <div class="info-section">
                                <h4>Observaciones del Jefe de Departamento:</h4>
                                <p class="text-content"><?= nl2br(htmlspecialchars($estudiante['observaciones_jefe'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Proyecto -->
            <div class="tab-content" id="tab-proyecto">
                <div class="project-detail-card">
                    <div class="project-header">
                        <div class="project-icon-large">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="project-info">
                            <h2><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></h2>
                            <p class="project-meta">
                                <span><i class="fas fa-flask"></i> <?= htmlspecialchars($jefeLab['laboratorio']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $estudiante['horas_requeridas'] ?> horas requeridas</span>
                                <span><i class="fas fa-map-marker-alt"></i> Modalidad: <?= ucfirst($estudiante['modalidad']) ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="project-body">
                        <div class="project-section">
                            <h3><i class="fas fa-align-left"></i> Descripción del Proyecto</h3>
                            <p><?= nl2br(htmlspecialchars($estudiante['proyecto_descripcion'])) ?></p>
                        </div>

                        <?php if ($estudiante['objetivos']): ?>
                        <div class="project-section">
                            <h3><i class="fas fa-bullseye"></i> Objetivos</h3>
                            <p><?= nl2br(htmlspecialchars($estudiante['objetivos'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($estudiante['tipo_actividades']): ?>
                        <div class="project-section">
                            <h3><i class="fas fa-tasks"></i> Tipo de Actividades</h3>
                            <p><?= nl2br(htmlspecialchars($estudiante['tipo_actividades'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($estudiante['horario']): ?>
                        <div class="project-section">
                            <h3><i class="fas fa-calendar-check"></i> Horario</h3>
                            <p><?= nl2br(htmlspecialchars($estudiante['horario'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab: Reportes -->
            <div class="tab-content" id="tab-reportes">
                <?php if (!empty($reportes)): ?>
                    <div class="reportes-timeline">
                        <?php foreach ($reportes as $reporte): ?>
                        <div class="reporte-timeline-item">
                            <div class="timeline-marker <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                <i class="fas <?= getEstadoIcon($reporte['estado']) ?>"></i>
                            </div>
                            <div class="reporte-card">
                                <div class="reporte-header">
                                    <div class="reporte-title">
                                        <h4><?= $reporte['nombre_reporte'] ?></h4>
                                        <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                            <?= getEstadoText($reporte['estado']) ?>
                                        </span>
                                    </div>
                                    <div class="reporte-dates">
                                        <span class="reporte-date">
                                            <i class="fas fa-calendar"></i>
                                            Periodo: <?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?>
                                        </span>
                                        <span class="reporte-date">
                                            <i class="fas fa-upload"></i>
                                            Entregado: <?= formatDate($reporte['fecha_entrega']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="reporte-body">
                                    <div class="reporte-stats-row">
                                        <div class="reporte-stat">
                                            <i class="fas fa-clock"></i>
                                            <div>
                                                <span class="stat-value"><?= $reporte['horas_reportadas'] ?> hrs</span>
                                                <span class="stat-label">Reportadas</span>
                                            </div>
                                        </div>
                                        <div class="reporte-stat">
                                            <i class="fas fa-chart-line"></i>
                                            <div>
                                                <span class="stat-value"><?= $reporte['horas_acumuladas'] ?> hrs</span>
                                                <span class="stat-label">Acumuladas</span>
                                            </div>
                                        </div>
                                        <?php if ($reporte['calificacion']): ?>
                                        <div class="reporte-stat">
                                            <i class="fas fa-star"></i>
                                            <div>
                                                <span class="stat-value"><?= number_format($reporte['calificacion'], 1) ?></span>
                                                <span class="stat-label">Calificación</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="reporte-content">
                                        <div class="content-section">
                                            <h5><i class="fas fa-tasks"></i> Actividades Realizadas</h5>
                                            <p><?= nl2br(htmlspecialchars($reporte['actividades_realizadas'])) ?></p>
                                        </div>

                                        <?php if ($reporte['logros_obtenidos']): ?>
                                        <div class="content-section">
                                            <h5><i class="fas fa-trophy"></i> Logros Obtenidos</h5>
                                            <p><?= nl2br(htmlspecialchars($reporte['logros_obtenidos'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['dificultades_encontradas']): ?>
                                        <div class="content-section">
                                            <h5><i class="fas fa-exclamation-triangle"></i> Dificultades Encontradas</h5>
                                            <p><?= nl2br(htmlspecialchars($reporte['dificultades_encontradas'])) ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($reporte['estado'] === 'aprobado' || $reporte['estado'] === 'evaluado'): ?>
                                    <div class="evaluacion-section">
                                        <h5><i class="fas fa-clipboard-check"></i> Evaluación</h5>
                                        
                                        <?php if ($reporte['fortalezas']): ?>
                                        <div class="evaluacion-item success">
                                            <strong>Fortalezas:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['fortalezas'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['areas_mejora']): ?>
                                        <div class="evaluacion-item warning">
                                            <strong>Áreas de Mejora:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['areas_mejora'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['observaciones_evaluador']): ?>
                                        <div class="evaluacion-item info">
                                            <strong>Observaciones del Evaluador:</strong>
                                            <p><?= nl2br(htmlspecialchars($reporte['observaciones_evaluador'])) ?></p>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($reporte['fecha_evaluacion']): ?>
                                        <div class="evaluacion-meta">
                                            <i class="fas fa-user-check"></i>
                                            Evaluado el <?= formatDateTime($reporte['fecha_evaluacion']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="reporte-actions">
                                    <?php if ($reporte['estado'] === 'pendiente_evaluacion'): ?>
                                    <a href="evaluar-reporte.php?id=<?= $reporte['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-star"></i> Evaluar Reporte
                                    </a>
                                    <?php endif; ?>
                                    <a href="ver-reporte.php?id=<?= $reporte['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eye"></i> Ver Detalle
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Sin Reportes</h3>
                        <p>El estudiante aún no ha entregado ningún reporte bimestral.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Historial -->
            <div class="tab-content" id="tab-historial">
                <?php if (!empty($historial)): ?>
                    <div class="historial-timeline">
                        <?php foreach ($historial as $evento): ?>
                        <div class="historial-item">
                            <div class="historial-marker">
                                <i class="fas fa-circle"></i>
                            </div>
                            <div class="historial-content">
                                <div class="historial-header">
                                    <h4>Cambio de Estado</h4>
                                    <span class="historial-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= formatDateTime($evento['fecha_cambio']) ?>
                                    </span>
                                </div>
                                <div class="historial-body">
                                    <div class="estado-change">
                                        <?php if ($evento['estado_anterior']): ?>
                                        <span class="badge <?= getEstadoBadgeClass($evento['estado_anterior']) ?>">
                                            <?= getEstadoText($evento['estado_anterior']) ?>
                                        </span>
                                        <i class="fas fa-arrow-right"></i>
                                        <?php endif; ?>
                                        <span class="badge <?= getEstadoBadgeClass($evento['estado_nuevo']) ?>">
                                            <?= getEstadoText($evento['estado_nuevo']) ?>
                                        </span>
                                    </div>
                                    <?php if ($evento['comentarios']): ?>
                                    <p class="historial-comment">
                                        <i class="fas fa-comment"></i>
                                        <?= htmlspecialchars($evento['comentarios']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if ($evento['usuario_nombre']): ?>
                                    <p class="historial-user">
                                        <i class="fas fa-user"></i>
                                        Realizado por: <?= htmlspecialchars($evento['usuario_nombre']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Sin Historial</h3>
                        <p>No hay eventos registrados en el historial.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Documentos -->
            <div class="tab-content" id="tab-documentos">
                <?php if (!empty($documentos)): ?>
                    <div class="documentos-grid">
                        <?php foreach ($documentos as $doc): ?>
                        <div class="documento-card">
                            <div class="documento-icon">
                                <i class="fas <?= $doc['icono'] ?>"></i>
                            </div>
                            <div class="documento-info">
                                <h4><?= htmlspecialchars($doc['tipo']) ?></h4>
                                <p class="documento-numero"><?= htmlspecialchars($doc['numero']) ?></p>
                                <p class="documento-fecha">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatDate($doc['fecha']) ?>
                                </p>
                                <span class="badge <?= getEstadoBadgeClass($doc['estado']) ?>">
                                    <?= ucfirst($doc['estado']) ?>
                                </span>
                            </div>
                            <div class="documento-actions">
                                <button class="btn btn-primary btn-sm">
                                    <i class="fas fa-download"></i> Descargar
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>Sin Documentos</h3>
                        <p>No se han generado documentos para este estudiante aún.</p>
                    </div>
                <?php endif; ?>
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

.student-name-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.student-name-section h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.badge-large {
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
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

.stat-card-detail.hours { --card-color: var(--primary); }
.stat-card-detail.reports { --card-color: var(--secondary); }
.stat-card-detail.grade { --card-color: var(--warning); }
.stat-card-detail.time { --card-color: var(--info); }

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

.stat-card-detail.hours .stat-card-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card-detail.reports .stat-card-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }
.stat-card-detail.grade .stat-card-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.stat-card-detail.time .stat-card-icon { background: linear-gradient(135deg, var(--info), #60a5fa); }

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

/* Tabs */
.tabs-container {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.tabs-nav {
    display: flex;
    border-bottom: 2px solid var(--border-light);
    overflow-x: auto;
}

.tab-button {
    flex: 1;
    min-width: 150px;
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.tab-button::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.tab-button:hover {
    background: var(--bg-light);
    color: var(--text-primary);
}

.tab-button.active {
    color: var(--primary);
    background: var(--bg-light);
}

.tab-button.active::after {
    transform: scaleX(1);
}

.tab-content {
    display: none;
    padding: 2rem;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}

.info-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    overflow: hidden;
}

.info-card.full-width {
    grid-column: 1 / -1;
}

.info-card-header {
    background: var(--bg-white);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
}

.info-card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card-body {
    padding: 1.5rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.info-value {
    color: var(--text-primary);
    text-align: right;
}

.info-value a {
    color: var(--primary);
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.info-section h4 {
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.text-content {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

/* Project Detail */
.project-detail-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.project-header {
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    color: white;
    padding: 2rem;
    display: flex;
    gap: 1.5rem;
}

.project-icon-large {
    width: 80px;
    height: 80px;
    border-radius: var(--radius);
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}

.project-info h2 {
    margin: 0 0 0.75rem 0;
    font-size: 1.75rem;
}

.project-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.95rem;
    opacity: 0.9;
}

.project-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.project-body {
    padding: 2rem;
}

.project-section {
    margin-bottom: 2rem;
}

.project-section:last-child {
    margin-bottom: 0;
}

.project-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.project-section p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

/* Reportes Timeline */
.reportes-timeline {
    position: relative;
    padding-left: 2rem;
}

.reportes-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--primary), var(--primary-light), var(--border));
}

.reporte-timeline-item {
    position: relative;
    margin-bottom: 2rem;
    padding-left: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 1.5rem;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    box-shadow: var(--shadow);
    z-index: 1;
}

.timeline-marker.badge-success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.timeline-marker.badge-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.timeline-marker.badge-error {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.reporte-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: var(--transition);
}

.reporte-timeline-item:hover .reporte-card {
    transform: translateX(5px);
    background: var(--bg-white);
    box-shadow: var(--shadow-lg);
}

.reporte-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.reporte-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.reporte-title h4 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.reporte-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.reporte-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.reporte-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.reporte-stats-row {
    display: flex;
    gap: 1.5rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.reporte-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.reporte-stat i {
    font-size: 1.25rem;
    color: var(--primary);
}

.reporte-stat .stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.reporte-stat .stat-label {
    display: block;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.reporte-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.content-section {
    background: var(--bg-white);
    padding: 1rem;
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.content-section h5 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
    color: var(--text-primary);
}

.content-section p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

.evaluacion-section {
    background: var(--bg-white);
    padding: 1.5rem;
    border-radius: var(--radius);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.evaluacion-section h5 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
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
    border-color: var(--secondary);
}

.evaluacion-item strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.evaluacion-item p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.6;
}

.evaluacion-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-light);
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

.reporte-actions {
    display: flex;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Historial Timeline */
.historial-timeline {
    position: relative;
    padding-left: 2rem;
}

.historial-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.historial-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 2rem;
}

.historial-marker {
    position: absolute;
    left: -2rem;
    top: 0.5rem;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary);
    border: 3px solid var(--bg-white);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.historial-marker i {
    font-size: 0.5rem;
    color: white;
}

.historial-content {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
}

.historial-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.historial-header h4 {
    margin: 0;
    font-size: 1rem;
    color: var(--text-primary);
}

.historial-date {
    font-size: 0.85rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.estado-change {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.estado-change i {
    color: var(--text-secondary);
}

.historial-comment,
.historial-user {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0.5rem 0 0 0;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.historial-comment i,
.historial-user i {
    margin-top: 0.125rem;
    flex-shrink: 0;
}

/* Documentos Grid */
.documentos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.documento-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: var(--transition);
}

.documento-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.documento-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.documento-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.documento-numero {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.documento-fecha {
    font-size: 0.85rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.documento-actions {
    display: flex;
    gap: 0.5rem;
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
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
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
.badge-info { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }

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

.statistics-grid > * {
    animation: slideIn 0.6s ease-out;
}

.statistics-grid > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-grid > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-grid > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-grid > *:nth-child(4) { animation-delay: 0.4s; }

/* Print Styles */
@media print {
    .quick-actions,
    .tabs-nav,
    .breadcrumb,
    .reporte-actions,
    .documento-actions {
        display: none !important;
    }
    
    .tab-content {
        display: block !important;
    }
    
    .main-wrapper {
        margin-left: 0;
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
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
    
    .statistics-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    
    .student-name-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .statistics-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs-nav {
        flex-wrap: nowrap;
        overflow-x: auto;
    }
    
    .tab-button {
        min-width: 120px;
    }
    
    .tab-content {
        padding: 1rem;
    }
    
    .reporte-stats-row {
        flex-direction: column;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .student-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 2rem;
    }
    
    .student-name-section h1 {
        font-size: 1.5rem;
    }
    
    .quick-actions {
        flex-direction: column;
    }
    
    .project-header {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tabs functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.dataset.tab;
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            button.classList.add('active');
            document.getElementById(`tab-${tabId}`).classList.add('active');
            
            // Save active tab to localStorage
            localStorage.setItem('activeTab', tabId);
        });
    });
    
    // Restore active tab from localStorage
    const savedTab = localStorage.getItem('activeTab');
    if (savedTab) {
        const savedButton = document.querySelector(`[data-tab="${savedTab}"]`);
        if (savedButton) {
            savedButton.click();
        }
    }
    
    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.mini-progress-bar, .progress-fill');
        progressBars.forEach((bar, index) => {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = width;
            }, index * 100);
        });
    }, 300);
    
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-card-number');
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
            }, index * 200);
        }
    });
});
</script>

<?php 
// Helper function for estado icons
function getEstadoIcon($estado) {
    $icons = [
        'en_proceso' => 'fa-spinner',
        'concluida' => 'fa-check',
        'cancelada' => 'fa-times',
        'pendiente' => 'fa-clock',
        'aprobada' => 'fa-check-circle',
        'rechazada' => 'fa-times-circle',
        'aprobado' => 'fa-check',
        'pendiente_evaluacion' => 'fa-hourglass-half',
        'rechazado' => 'fa-times'
    ];
    return $icons[$estado] ?? 'fa-question';
}

include '../../includes/footer.php'; 
?>