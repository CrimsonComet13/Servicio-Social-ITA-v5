<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// ✅ SOLUCIÓN CORRECTA con parámetros nombrados
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
$nombreLaboratorio = $jefeLab['laboratorio'];

// 🎯 Obtener ID de la solicitud
$solicitudId = $_GET['id'] ?? null;

if (!$solicitudId) {
    flashMessage('ID de solicitud no válido', 'error');
    redirectTo('/modules/laboratorio/estudiantes-solicitudes.php');
    exit;
}

// Procesar acciones (aprobar/rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db->beginTransaction();
        
        if ($action === 'aprobar') {
            // Aprobar solicitud
            $db->update('solicitudes_servicio', [
                'estado' => 'en_proceso',
                'fecha_aprobacion_lab' => date('Y-m-d H:i:s')
            ], 'id = :id AND jefe_laboratorio_id = :jefe_id AND estado = :estado_actual', [
                'id' => $solicitudId,
                'jefe_id' => $jefeLabId,
                'estado_actual' => 'pendiente'
            ]);
            
            // Incrementar cupo ocupado del proyecto
            $solicitud = $db->fetch("SELECT proyecto_id FROM solicitudes_servicio WHERE id = :id", ['id' => $solicitudId]);
            if ($solicitud) {
                $db->query("UPDATE proyectos_laboratorio SET cupo_ocupado = cupo_ocupado + 1 WHERE id = :proyecto_id", [
                    'proyecto_id' => $solicitud['proyecto_id']
                ]);
            }
            
            $db->commit();
            flashMessage('Solicitud aprobada exitosamente', 'success');
            redirectTo('/modules/laboratorio/solicitud-detalle.php?id=' . $solicitudId);
            
        } elseif ($action === 'rechazar') {
            $motivo = $_POST['motivo_rechazo'] ?? 'Sin motivo especificado';
            
            // Rechazar solicitud
            $db->update('solicitudes_servicio', [
                'estado' => 'rechazada',
                'fecha_rechazo' => date('Y-m-d H:i:s'),
                'motivo_rechazo' => $motivo
            ], 'id = :id AND jefe_laboratorio_id = :jefe_id AND estado = :estado_actual', [
                'id' => $solicitudId,
                'jefe_id' => $jefeLabId,
                'estado_actual' => 'pendiente'
            ]);
            
            $db->commit();
            flashMessage('Solicitud rechazada', 'info');
            redirectTo('/modules/laboratorio/solicitud-detalle.php?id=' . $solicitudId);
        }
    } catch (Exception $e) {
        $db->rollback();
        flashMessage('Error al procesar la solicitud: ' . $e->getMessage(), 'error');
    }
}

// Obtener información completa de la solicitud
$solicitud = $db->fetch("
    SELECT 
        s.*,
        e.nombre as estudiante_nombre,
        e.apellido_paterno,
        e.apellido_materno,
        e.numero_control,
        e.carrera,
        e.semestre,
        e.telefono as estudiante_telefono,
        e.horas_completadas,
        u.email as estudiante_email,
        p.nombre_proyecto,
        p.descripcion as proyecto_descripcion,
        p.laboratorio_asignado,
        p.cupo_disponible,
        p.cupo_ocupado,
        p.requisitos,
        p.objetivos,
        p.duracion_estimada,
        p.area_conocimiento,
        p.modalidad,
        p.horario,
        jd.nombre as jefe_depto_nombre,
        jd.departamento,
        jd.email as jefe_depto_email,
        jd.telefono as jefe_depto_telefono
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN usuarios u ON e.usuario_id = u.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE s.id = :solicitud_id 
    AND s.jefe_laboratorio_id = :jefe_id
", [
    'solicitud_id' => $solicitudId,
    'jefe_id' => $jefeLabId
]);

if (!$solicitud) {
    flashMessage('Solicitud no encontrada o no tienes permiso para verla', 'error');
    redirectTo('/modules/laboratorio/estudiantes-solicitudes.php');
    exit;
}

// Calcular duración del servicio en meses
$fechaInicio = new DateTime($solicitud['fecha_inicio_propuesta']);
$fechaFin = new DateTime($solicitud['fecha_fin_propuesta']);
$duracionMeses = $fechaInicio->diff($fechaFin)->m + ($fechaInicio->diff($fechaFin)->y * 12);

// Obtener reportes si existen
$reportes = $db->fetchAll("
    SELECT *
    FROM reportes_bimestrales
    WHERE solicitud_id = :solicitud_id
    ORDER BY numero_reporte ASC
", ['solicitud_id' => $solicitudId]);

// Timeline de eventos
$timeline = [];

// Evento: Solicitud creada
$timeline[] = [
    'fecha' => $solicitud['fecha_solicitud'],
    'tipo' => 'creacion',
    'titulo' => 'Solicitud Creada',
    'descripcion' => 'La solicitud fue creada por el Jefe de Departamento',
    'icono' => 'fa-plus-circle',
    'color' => 'info'
];

// Evento: Aprobación (si existe)
if ($solicitud['fecha_aprobacion_lab']) {
    $timeline[] = [
        'fecha' => $solicitud['fecha_aprobacion_lab'],
        'tipo' => 'aprobacion',
        'titulo' => 'Solicitud Aprobada',
        'descripcion' => 'La solicitud fue aprobada por el Jefe de Laboratorio',
        'icono' => 'fa-check-circle',
        'color' => 'success'
    ];
}

// Evento: Rechazo (si existe)
if ($solicitud['fecha_rechazo']) {
    $timeline[] = [
        'fecha' => $solicitud['fecha_rechazo'],
        'tipo' => 'rechazo',
        'titulo' => 'Solicitud Rechazada',
        'descripcion' => 'La solicitud fue rechazada',
        'icono' => 'fa-times-circle',
        'color' => 'error'
    ];
}

// Ordenar timeline por fecha
usort($timeline, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

$pageTitle = "Detalle de Solicitud - " . APP_NAME;
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
                        <i class="fas fa-file-alt"></i>
                        Solicitud #<?= $solicitud['id'] ?>
                    </h1>
                    <p class="page-subtitle">
                        <span class="badge <?= getEstadoBadgeClass($solicitud['estado']) ?>">
                            <i class="fas fa-<?= getEstadoIcon($solicitud['estado']) ?>"></i>
                            <?= getEstadoText($solicitud['estado']) ?>
                        </span>
                        <span class="separator">•</span>
                        Recibida el <?= formatDate($solicitud['fecha_solicitud']) ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="estudiantes-solicitudes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver a Solicitudes
                    </a>
                    <?php if ($solicitud['estado'] === 'pendiente'): ?>
                        <button type="button" class="btn btn-success" onclick="aprobarSolicitud()">
                            <i class="fas fa-check"></i>
                            Aprobar Solicitud
                        </button>
                        <button type="button" class="btn btn-error" onclick="mostrarModalRechazo()">
                            <i class="fas fa-times"></i>
                            Rechazar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alert for pending action -->
        <?php if ($solicitud['estado'] === 'pendiente'): ?>
        <div class="alert-banner warning">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h3 class="alert-title">Acción Requerida</h3>
                <p class="alert-description">Esta solicitud está pendiente de tu revisión. Por favor, aprueba o rechaza la solicitud.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Left Column - Main Content -->
            <div class="content-main">
                <!-- Student Information Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-graduate"></i>
                            Información del Estudiante
                        </h2>
                        <a href="estudiante-detalle.php?id=<?= $solicitud['estudiante_id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i>
                            Ver Perfil Completo
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <div class="student-profile-header">
                            <div class="student-avatar-large">
                                <?= strtoupper(substr($solicitud['estudiante_nombre'], 0, 1)) ?>
                            </div>
                            <div class="student-profile-info">
                                <h3 class="student-name-large">
                                    <?= htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?>
                                </h3>
                                <p class="student-meta-large">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($solicitud['numero_control']) ?></span>
                                    <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($solicitud['carrera']) ?></span>
                                    <span><i class="fas fa-layer-group"></i> <?= $solicitud['semestre'] ?>° Semestre</span>
                                </p>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-envelope"></i>
                                    Correo Electrónico
                                </div>
                                <div class="info-value">
                                    <a href="mailto:<?= htmlspecialchars($solicitud['estudiante_email']) ?>">
                                        <?= htmlspecialchars($solicitud['estudiante_email']) ?>
                                    </a>
                                </div>
                            </div>

                            <?php if (!empty($solicitud['estudiante_telefono'])): ?>
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-phone"></i>
                                    Teléfono
                                </div>
                                <div class="info-value">
                                    <a href="tel:<?= htmlspecialchars($solicitud['estudiante_telefono']) ?>">
                                        <?= htmlspecialchars($solicitud['estudiante_telefono']) ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-clock"></i>
                                    Horas Completadas
                                </div>
                                <div class="info-value">
                                    <span class="badge badge-success"><?= $solicitud['horas_completadas'] ?? 0 ?> hrs</span>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-percentage"></i>
                                    Progreso General
                                </div>
                                <div class="info-value">
                                    <span class="badge badge-info"><?= min(100, round((($solicitud['horas_completadas'] ?? 0) / 500) * 100)) ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Information Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-project-diagram"></i>
                            Proyecto Asignado
                        </h2>
                        <a href="proyecto-detalle.php?id=<?= $solicitud['proyecto_id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i>
                            Ver Proyecto Completo
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <div class="project-header-detail">
                            <div class="project-icon-large">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="project-info-large">
                                <h3 class="project-name-large"><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></h3>
                                <p class="project-meta-large">
                                    <span><i class="fas fa-flask"></i> <?= htmlspecialchars($solicitud['laboratorio_asignado']) ?></span>
                                    <span><i class="fas fa-book"></i> <?= htmlspecialchars($solicitud['area_conocimiento'] ?? '') ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="info-section">
                            <h3 class="section-subtitle">Descripción del Proyecto</h3>
                            <p class="project-description"><?= nl2br(htmlspecialchars($solicitud['proyecto_descripcion'])) ?></p>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-users"></i>
                                    Cupos Disponibles
                                </div>
                                <div class="info-value">
                                    <?= $solicitud['cupo_ocupado'] ?> / <?= $solicitud['cupo_disponible'] ?>
                                    <?php if ($solicitud['cupo_ocupado'] >= $solicitud['cupo_disponible']): ?>
                                        <span class="badge badge-error badge-sm">LLENO</span>
                                    <?php else: ?>
                                        <span class="badge badge-success badge-sm">DISPONIBLE</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-clock"></i>
                                    Duración Estimada
                                </div>
                                <div class="info-value"><?= htmlspecialchars($solicitud['duracion_estimada']) ?></div>
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
                                    echo htmlspecialchars($modalidades[$solicitud['modalidad']] ?? $solicitud['modalidad']);
                                    ?>
                                </div>
                            </div>

                            <?php if (!empty($solicitud['horario'])): ?>
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Horario
                                </div>
                                <div class="info-value"><?= htmlspecialchars($solicitud['horario'] ?? '') ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($solicitud['objetivos'])): ?>
                        <div class="info-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-bullseye"></i>
                                Objetivos del Proyecto
                            </h3>
                            <p class="project-objectives"><?= nl2br(htmlspecialchars($solicitud['objetivos'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($solicitud['requisitos'])): ?>
                        <div class="info-section">
                            <h3 class="section-subtitle">
                                <i class="fas fa-check-square"></i>
                                Requisitos para el Estudiante
                            </h3>
                            <p class="project-requirements"><?= nl2br(htmlspecialchars($solicitud['requisitos'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service Period Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-calendar-check"></i>
                            Período de Servicio Social
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="period-visual">
                            <div class="period-item">
                                <div class="period-icon start">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="period-content">
                                    <div class="period-label">Fecha de Inicio</div>
                                    <div class="period-date"><?= formatDate($solicitud['fecha_inicio_propuesta']) ?></div>
                                </div>
                            </div>

                            <div class="period-connector">
                                <div class="period-line"></div>
                                <div class="period-duration">
                                    <i class="fas fa-clock"></i>
                                    <?= $duracionMeses ?> meses
                                </div>
                            </div>

                            <div class="period-item">
                                <div class="period-icon end">
                                    <i class="fas fa-flag-checkered"></i>
                                </div>
                                <div class="period-content">
                                    <div class="period-label">Fecha de Finalización</div>
                                    <div class="period-date"><?= formatDate($solicitud['fecha_fin_propuesta']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="period-summary">
                            <div class="summary-item">
                                <i class="fas fa-calendar"></i>
                                <span>Total: <?= $duracionMeses ?> meses de servicio social</span>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-business-time"></i>
                                <span>Equivalente a 500 horas de servicio</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rejection Reason (if rejected) -->
                <?php if ($solicitud['estado'] === 'rechazada' && !empty($solicitud['motivo_rechazo'])): ?>
                <div class="info-card rejection-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Motivo del Rechazo
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="rejection-content">
                            <div class="rejection-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="rejection-text">
                                <p><?= nl2br(htmlspecialchars($solicitud['motivo_rechazo'])) ?></p>
                                <?php if ($solicitud['fecha_rechazo']): ?>
                                <small class="rejection-date">
                                    <i class="fas fa-calendar"></i>
                                    Rechazada el <?= formatDateTime($solicitud['fecha_rechazo']) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reports Section (if approved) -->
                <?php if ($solicitud['estado'] === 'en_proceso' && !empty($reportes)): ?>
                <div class="info-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-file-alt"></i>
                            Reportes Bimestrales
                            <span class="count-badge"><?= count($reportes) ?></span>
                        </h2>
                        <a href="reportes-estudiante.php?id=<?= $solicitud['estudiante_id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-list"></i>
                            Ver Todos
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <div class="reports-list">
                            <?php foreach ($reportes as $reporte): ?>
                            <div class="report-item">
                                <div class="report-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="report-info">
                                    <h4>Reporte <?= $reporte['numero_reporte'] ?></h4>
                                    <p class="report-meta">
                                        <span><i class="fas fa-calendar"></i> <?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?></span>
                                        <span><i class="fas fa-clock"></i> <?= $reporte['horas_reportadas'] ?> hrs</span>
                                    </p>
                                </div>
                                <div class="report-status">
                                    <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                        <?= getEstadoText($reporte['estado']) ?>
                                    </span>
                                    <?php if ($reporte['calificacion']): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-star"></i> <?= $reporte['calificacion'] ?>
                                    </span>
                                    <?php endif; ?>
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
                <!-- Department Head Card -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-user-tie"></i>
                            Jefe de Departamento
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="contact-info">
                            <div class="contact-avatar">
                                <?= strtoupper(substr($solicitud['jefe_depto_nombre'], 0, 1)) ?>
                            </div>
                            <div class="contact-details">
                                <h4><?= htmlspecialchars($solicitud['jefe_depto_nombre']) ?></h4>
                                <p class="contact-department"><?= htmlspecialchars($solicitud['departamento']) ?></p>
                            </div>
                        </div>
                        <div class="contact-methods">
                            <a href="mailto:<?= htmlspecialchars($solicitud['jefe_depto_email']) ?>" class="contact-method">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($solicitud['jefe_depto_email']) ?></span>
                            </a>
                            <?php if (!empty($solicitud['jefe_depto_telefono'])): ?>
                            <a href="tel:<?= htmlspecialchars($solicitud['jefe_depto_telefono']) ?>" class="contact-method">
                                <i class="fas fa-phone"></i>
                                <span><?= htmlspecialchars($solicitud['jefe_depto_telefono']) ?></span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Status Information -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-info-circle"></i>
                            Estado de la Solicitud
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="status-display">
                            <div class="status-badge-large <?= getEstadoBadgeClass($solicitud['estado']) ?>">
                                <i class="fas fa-<?= getEstadoIcon($solicitud['estado']) ?>"></i>
                                <span><?= getEstadoText($solicitud['estado']) ?></span>
                            </div>
                            <p class="status-description">
                                <?php
                                $descripciones = [
                                    'pendiente' => 'Esta solicitud está esperando tu revisión y aprobación.',
                                    'en_proceso' => 'El estudiante está realizando actualmente su servicio social.',
                                    'concluida' => 'El servicio social ha sido completado exitosamente.',
                                    'rechazada' => 'Esta solicitud ha sido rechazada.',
                                    'cancelada' => 'Esta solicitud ha sido cancelada.'
                                ];
                                echo $descripciones[$solicitud['estado']] ?? 'Estado desconocido';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-history"></i>
                            Historial de Eventos
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($timeline as $evento): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?= $evento['color'] ?>">
                                    <i class="fas <?= $evento['icono'] ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title"><?= $evento['titulo'] ?></div>
                                    <div class="timeline-description"><?= $evento['descripcion'] ?></div>
                                    <div class="timeline-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= formatDateTime($evento['fecha']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if ($solicitud['estado'] === 'pendiente'): ?>
                <div class="sidebar-card actions-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-bolt"></i>
                            Acciones Rápidas
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <button type="button" class="btn btn-success btn-block" onclick="aprobarSolicitud()">
                                <i class="fas fa-check"></i>
                                Aprobar Solicitud
                            </button>
                            <button type="button" class="btn btn-error btn-block" onclick="mostrarModalRechazo()">
                                <i class="fas fa-times"></i>
                                Rechazar Solicitud
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Metadata -->
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3 class="card-title-sm">
                            <i class="fas fa-info-circle"></i>
                            Información Adicional
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="metadata-list">
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-hashtag"></i>
                                    ID Solicitud
                                </span>
                                <span class="metadata-value"><?= $solicitud['id'] ?></span>
                            </div>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-calendar-plus"></i>
                                    Fecha de Solicitud
                                </span>
                                <span class="metadata-value"><?= formatDate($solicitud['fecha_solicitud']) ?></span>
                            </div>
                            <?php if ($solicitud['fecha_aprobacion_lab']): ?>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-calendar-check"></i>
                                    Fecha de Aprobación
                                </span>
                                <span class="metadata-value"><?= formatDate($solicitud['fecha_aprobacion_lab']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-user"></i>
                                    Estudiante ID
                                </span>
                                <span class="metadata-value"><?= $solicitud['estudiante_id'] ?></span>
                            </div>
                            <div class="metadata-item">
                                <span class="metadata-label">
                                    <i class="fas fa-project-diagram"></i>
                                    Proyecto ID
                                </span>
                                <span class="metadata-value"><?= $solicitud['proyecto_id'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rechazo -->
<div id="modalRechazo" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-times-circle"></i>
                Rechazar Solicitud
            </h3>
            <button type="button" class="close-modal" onclick="cerrarModalRechazo()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="formRechazo">
            <input type="hidden" name="action" value="rechazar">
            
            <div class="modal-body">
                <div class="alert-modal warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Esta acción rechazará la solicitud del estudiante. Asegúrate de proporcionar un motivo claro y específico.</p>
                </div>

                <div class="form-group">
                    <label for="motivo_rechazo">
                        <i class="fas fa-comment"></i>
                        Motivo del Rechazo *
                    </label>
                    <textarea 
                        id="motivo_rechazo" 
                        name="motivo_rechazo" 
                        class="form-control" 
                        rows="6" 
                        required
                        placeholder="Explica de manera clara y constructiva por qué se rechaza esta solicitud..."></textarea>
                    <small class="form-help">Este motivo será visible para el estudiante y el jefe de departamento</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalRechazo()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-error">
                    <i class="fas fa-check"></i>
                    Confirmar Rechazo
                </button>
            </div>
        </form>
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
    flex-wrap: wrap;
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
.sidebar-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.rejection-card {
    border: 2px solid var(--error);
}

.actions-card {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.05), rgba(16, 185, 129, 0.05));
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

.card-title i,
.card-title-sm i {
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

/* Student Profile */
.student-profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-light);
}

.student-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 2rem;
    flex-shrink: 0;
    box-shadow: var(--shadow-lg);
}

.student-profile-info {
    flex: 1;
}

.student-name-large {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.student-meta-large {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0;
}

.student-meta-large span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Project Header */
.project-header-detail {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border-light);
}

.project-icon-large {
    width: 70px;
    height: 70px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.project-info-large {
    flex: 1;
}

.project-name-large {
    font-size: 1.375rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    line-height: 1.3;
}

.project-meta-large {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0;
}

.project-meta-large span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.info-value a {
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.info-value a:hover {
    color: var(--primary-light);
    text-decoration: underline;
}

/* Period Visual */
.period-visual {
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 2rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.period-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.period-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.period-icon.start {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.period-icon.end {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.period-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.period-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.period-date {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
}

.period-connector {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.period-line {
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--success));
    border-radius: 1rem;
}

.period-duration {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-white);
    border-radius: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    border: 2px solid var(--primary);
}

.period-summary {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.summary-item i {
    color: var(--primary);
}

/* Rejection Content */
.rejection-content {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--radius);
}

.rejection-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--error);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.rejection-text {
    flex: 1;
}

.rejection-text p {
    color: var(--text-primary);
    line-height: 1.7;
    margin: 0 0 0.75rem 0;
}

.rejection-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-light);
}

/* Reports List */
.reports-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.report-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.report-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.report-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    color: white;
    flex-shrink: 0;
}

.report-info {
    flex: 1;
}

.report-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.report-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
}

.report-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.report-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

/* Contact Info */
.contact-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.contact-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.contact-details h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.contact-department {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
}

.contact-methods {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.contact-method {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-secondary);
    font-size: 0.85rem;
    transition: var(--transition);
}

.contact-method:hover {
    background: var(--bg-white);
    color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.contact-method i {
    width: 16px;
    color: var(--primary);
}

/* Status Display */
.status-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
    padding: 1rem;
}

.status-badge-large {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    width: 100%;
}

.status-badge-large.badge-warning {
    background: rgba(245, 158, 11, 0.1);
}

.status-badge-large.badge-success {
    background: rgba(16, 185, 129, 0.1);
}

.status-badge-large.badge-error {
    background: rgba(239, 68, 68, 0.1);
}

.status-badge-large.badge-primary {
    background: rgba(76, 175, 80, 0.1);
}

.status-badge-large i {
    font-size: 2rem;
}

.status-badge-large span {
    font-size: 1.125rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
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
    left: 11px;
    top: 30px;
    bottom: -16px;
    width: 2px;
    background: var(--border);
}

.timeline-marker {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    flex-shrink: 0;
    z-index: 1;
}

.timeline-marker.info {
    background: var(--info);
}

.timeline-marker.success {
    background: var(--success);
}

.timeline-marker.error {
    background: var(--error);
}

.timeline-content {
    flex: 1;
    padding-bottom: 0.5rem;
}

.timeline-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.timeline-description {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.timeline-date {
    font-size: 0.75rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-block {
    width: 100%;
    justify-content: center;
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

.badge-sm {
    padding: 0.125rem 0.5rem;
    font-size: 0.7rem;
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

.badge-info {
    background: rgba(33, 150, 243, 0.1);
    color: var(--secondary);
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

.btn-error {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-error:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-content {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: var(--shadow-lg);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.close-modal {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--bg-gray);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-secondary);
}

.close-modal:hover {
    background: var(--error);
    color: white;
}

.modal-body {
    padding: 1.5rem;
}

.alert-modal {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
}

.alert-modal.warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid var(--warning);
    color: var(--text-primary);
}

.alert-modal i {
    color: var(--warning);
    font-size: 1.25rem;
    flex-shrink: 0;
}

.alert-modal p {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    font-family: inherit;
    background: var(--bg-white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.form-help {
    display: block;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
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

.content-main > *,
.content-sidebar > * {
    animation: slideIn 0.4s ease-out;
}

.content-main > *:nth-child(1) {
    animation-delay: 0.1s;
}

.content-main > *:nth-child(2) {
    animation-delay: 0.15s;
}

.content-main > *:nth-child(3) {
    animation-delay: 0.2s;
}

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
        justify-content: flex-start;
    }

    .period-visual {
        flex-direction: column;
        align-items: flex-start;
    }

    .period-connector {
        width: 100%;
        flex-direction: row;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }

    .page-title {
        font-size: 1.5rem;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .student-profile-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .student-meta-large,
    .project-meta-large {
        flex-direction: column;
        gap: 0.5rem;
    }

    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .header-actions .btn {
        width: 100%;
    }

    .period-visual {
        padding: 1rem;
    }

    .period-item {
        flex-direction: column;
        text-align: center;
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

    .student-avatar-large {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .student-name-large {
        font-size: 1.25rem;
    }

    .project-icon-large {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .project-name-large {
        font-size: 1.125rem;
    }

    .modal-content {
        width: 95%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate elements on page load
    const animatedElements = document.querySelectorAll('.content-main > *, .content-sidebar > *');
    animatedElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';

        setTimeout(() => {
            element.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
    });
});

function aprobarSolicitud() {
    if (confirm('¿Está seguro de que desea aprobar esta solicitud?\n\nEl estudiante podrá comenzar su servicio social en el proyecto asignado.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="aprobar">';
        document.body.appendChild(form);
        form.submit();
    }
}

function mostrarModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Focus on textarea
    setTimeout(() => {
        document.getElementById('motivo_rechazo').focus();
    }, 100);
}

function cerrarModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('formRechazo').reset();
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalRechazo')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalRechazo();
    }
});

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalRechazo();
    }
});

// Form validation
document.getElementById('formRechazo')?.addEventListener('submit', function(e) {
    const motivo = document.getElementById('motivo_rechazo').value.trim();

    if (motivo.length < 10) {
        e.preventDefault();
        alert('Por favor, proporciona un motivo más detallado (mínimo 10 caracteres).');
        return false;
    }

    if (!confirm('¿Confirmas que deseas rechazar esta solicitud?\n\nEsta acción no se puede deshacer.')) {
        e.preventDefault();
        return false;
    }
});

console.log('✅ Página de detalle de solicitud inicializada');
</script>

<?php
// Funciones auxiliares para los iconos de estado
function getEstadoIcon($estado)
{
    $icons = [
        'pendiente' => 'hourglass-half',
        'en_proceso' => 'play-circle',
        'concluida' => 'check-circle',
        'rechazada' => 'times-circle',
        'cancelada' => 'ban'
    ];
    return $icons[$estado] ?? 'question-circle';
}

include '../../includes/footer.php';
?>