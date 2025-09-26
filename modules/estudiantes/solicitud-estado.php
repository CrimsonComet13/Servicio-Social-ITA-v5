<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Función helper para htmlspecialchars segura
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Obtener datos del estudiante con valores por defecto
$estudiante = $db->fetch("
    SELECT e.*, u.email,
           COALESCE(e.nombre, 'Usuario') as nombre,
           COALESCE(e.apellido_paterno, '') as apellido_paterno,
           COALESCE(e.carrera, 'Sin carrera') as carrera,
           COALESCE(e.numero_control, 'Sin número') as numero_control
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

// Obtener solicitud activa con información completa y valores por defecto
$solicitudActiva = $db->fetch("
    SELECT s.*, 
           COALESCE(p.nombre_proyecto, 'Sin nombre') as nombre_proyecto, 
           COALESCE(p.descripcion, '') as descripcion, 
           COALESCE(p.objetivos, '') as objetivos,
           COALESCE(jl.nombre, 'Sin asignar') as jefe_lab_nombre, 
           COALESCE(jl.laboratorio, 'Sin laboratorio') as laboratorio, 
           COALESCE(jl.telefono, '') as lab_telefono, 
           COALESCE(u_lab.email, '') as lab_email,
           COALESCE(jd.nombre, 'Sin asignar') as jefe_depto_nombre, 
           COALESCE(jd.departamento, 'Sin departamento') as departamento, 
           COALESCE(jd.telefono, '') as depto_telefono, 
           COALESCE(u_depto.email, '') as depto_email
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u_lab ON jl.usuario_id = u_lab.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    JOIN usuarios u_depto ON jd.usuario_id = u_depto.id
    WHERE s.estudiante_id = :estudiante_id 
    AND s.estado IN ('pendiente', 'aprobada', 'en_proceso', 'completado', 'concluida')
    ORDER BY s.fecha_solicitud DESC
    LIMIT 1
", ['estudiante_id' => $estudiante['id']]);

// Obtener historial de cambios de estado (con manejo de errores)
$historialEstados = [];
if ($solicitudActiva) {
    try {
        // Verificar si la tabla existe
        $tableExists = $db->fetch("SHOW TABLES LIKE 'historial_estados'");
        
        if ($tableExists) {
            $historialEstados = $db->fetchAll("
                SELECT he.*, u.email as usuario_email,
                       CASE 
                           WHEN u.tipo_usuario = 'estudiante' THEN e.nombre
                           WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre
                           WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
                           ELSE u.email
                       END as usuario_nombre
                FROM historial_estados he
                LEFT JOIN usuarios u ON he.usuario_id = u.id
                LEFT JOIN estudiantes e ON u.id = e.usuario_id
                LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id
                LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id
                WHERE he.solicitud_id = :solicitud_id
                ORDER BY he.fecha_cambio DESC
            ", ['solicitud_id' => $solicitudActiva['id']]);
        } else {
            // Crear historial simulado
            $historialEstados = [
                [
                    'id' => 1,
                    'estado_anterior' => null,
                    'estado_nuevo' => 'pendiente',
                    'usuario_email' => null,
                    'usuario_nombre' => 'Sistema',
                    'comentarios' => 'Solicitud creada',
                    'fecha_cambio' => $solicitudActiva['fecha_solicitud'] . ' 00:00:00'
                ]
            ];
            
            if (in_array($solicitudActiva['estado'], ['aprobada', 'en_proceso', 'concluida'])) {
                $historialEstados[] = [
                    'id' => 2,
                    'estado_anterior' => 'pendiente',
                    'estado_nuevo' => 'aprobada',
                    'usuario_email' => null,
                    'usuario_nombre' => 'Jefe de Departamento',
                    'comentarios' => 'Solicitud aprobada',
                    'fecha_cambio' => $solicitudActiva['fecha_aprobacion'] ?? $solicitudActiva['updated_at']
                ];
            }
            
            if (in_array($solicitudActiva['estado'], ['en_proceso', 'concluida'])) {
                $historialEstados[] = [
                    'id' => 3,
                    'estado_anterior' => 'aprobada',
                    'estado_nuevo' => 'en_proceso',
                    'usuario_email' => null,
                    'usuario_nombre' => 'Sistema',
                    'comentarios' => 'Servicio social iniciado',
                    'fecha_cambio' => $solicitudActiva['fecha_inicio_propuesta'] . ' 00:00:00'
                ];
            }
            
            usort($historialEstados, function($a, $b) {
                return strtotime($b['fecha_cambio']) - strtotime($a['fecha_cambio']);
            });
        }
    } catch (PDOException $e) {
        error_log("Error al obtener historial de estados: " . $e->getMessage());
        $historialEstados = [];
    }
}

// Obtener documentos relacionados con valores seguros
$documentos = [];
if ($solicitudActiva) {
    $oficios = $db->fetchAll("
        SELECT 'oficio' as tipo, 'Oficio de Presentación' as nombre, 
               COALESCE(numero_oficio, 'Sin número') as numero, 
               fecha_emision as fecha, archivo_path, 
               COALESCE(estado, 'generado') as estado
        FROM oficios_presentacion
        WHERE solicitud_id = :solicitud_id
        ORDER BY fecha_emision DESC
    ", ['solicitud_id' => $solicitudActiva['id']]);
    
    $documentos = array_merge($documentos, $oficios);
}

// Obtener reportes relacionados con valores seguros
$reportes = [];
if ($solicitudActiva) {
    $reportes = $db->fetchAll("
        SELECT r.*, 
               DATE_FORMAT(r.fecha_entrega, '%d/%m/%Y') as fecha_entrega_formatted,
               COALESCE(r.calificacion, 0) as calificacion,
               COALESCE(r.horas_reportadas, 0) as horas_reportadas,
               COALESCE(r.estado, 'pendiente_evaluacion') as estado
        FROM reportes_bimestrales r
        WHERE r.solicitud_id = :solicitud_id
        ORDER BY r.numero_reporte
    ", ['solicitud_id' => $solicitudActiva['id']]);
}

// Funciones helper
function getTimelineProgress($estado) {
    switch($estado) {
        case 'pendiente': return 25;
        case 'aprobada': return 50;
        case 'en_proceso': return 75;
        case 'completado':
        case 'concluida': return 100;
        default: return 0;
    }
}

function getNextStep($estado) {
    switch($estado) {
        case 'pendiente': return 'Esperar aprobación del jefe de departamento';
        case 'aprobada': return 'Descargar oficio de presentación e iniciar actividades';
        case 'en_proceso': return 'Continuar con reportes bimestrales y registro de horas';
        case 'completado':
        case 'concluida': return 'Proceso finalizado exitosamente';
        default: return 'Crear nueva solicitud';
    }
}

function getEstadoCssClass($estado) {
    switch($estado) {
        case 'pendiente': return 'pending';
        case 'aprobada': return 'approved';
        case 'en_proceso': return 'in-progress';
        case 'completado':
        case 'concluida': return 'completed';
        case 'rechazada': return 'rejected';
        default: return 'pending';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'pendiente': return 'hourglass-half';
        case 'aprobada': return 'check-circle';
        case 'en_proceso': return 'play-circle';
        case 'completado':
        case 'concluida': return 'trophy';
        case 'rechazada': return 'times-circle';
        default: return 'question-circle';
    }
}

function getEstadoTitle($estado) {
    switch($estado) {
        case 'pendiente': return 'Solicitud en Revisión';
        case 'aprobada': return 'Solicitud Aprobada';
        case 'en_proceso': return 'Servicio Social en Proceso';
        case 'completado':
        case 'concluida': return 'Servicio Social Completado';
        case 'rechazada': return 'Solicitud Rechazada';
        default: return 'Estado del Servicio';
    }
}




$pageTitle = "Estado de Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="status-container">
    <!-- Header Section Mejorado -->
    <div class="status-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Estado de Solicitud</h1>
                <p class="header-subtitle">Seguimiento detallado de tu proceso de servicio social</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <?php if (!$solicitudActiva): ?>
        <!-- Estado Vacío Mejorado -->
        <div class="empty-state-card-modern">
            <div class="empty-state-background">
                <div class="empty-circle circle-1"></div>
                <div class="empty-circle circle-2"></div>
                <div class="empty-circle circle-3"></div>
            </div>
            <div class="empty-state-content">
                <div class="empty-state-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3>¡Comienza tu Servicio Social!</h3>
                <p>No tienes una solicitud activa. Crea tu primera solicitud para comenzar tu proceso de servicio social en el ITA.</p>
                <div class="empty-state-actions">
                    <a href="servicio_social_ita/modules/estudiantes/solicitud.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i>
                        Crear Solicitud
                    </a>
                    <a href="servicio_social_ita/modules/dashboard/estudiante.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-home"></i>
                        Ir al Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Status Overview Mejorado -->
        <div class="status-overview-modern">
            <!-- Tarjeta Principal de Estado -->
            <div class="main-status-card <?= getEstadoCssClass($solicitudActiva['estado']) ?>">
                <div class="status-card-background">
                    <div class="status-bg-circle"></div>
                    <div class="status-bg-dots"></div>
                </div>
                <div class="status-card-content">
                    <div class="status-icon-large">
                        <i class="fas fa-<?= getEstadoIcon($solicitudActiva['estado']) ?>"></i>
                    </div>
                    <div class="status-main-info">
                        <h2 class="status-title"><?= getEstadoTitle($solicitudActiva['estado']) ?></h2>
                        <div class="status-badge">
                            <i class="fas fa-circle"></i>
                            <span><?= getEstadoText($solicitudActiva['estado']) ?></span>
                        </div>
                        <p class="status-description">
                            Solicitud creada el <?= formatDate($solicitudActiva['fecha_solicitud']) ?>
                        </p>
                    </div>
                </div>
                
                <div class="status-progress-section">
                    <div class="progress-info">
                        <span class="progress-label">Progreso del proceso</span>
                        <span class="progress-percentage"><?= getTimelineProgress($solicitudActiva['estado']) ?>%</span>
                    </div>
                    <div class="progress-bar-modern">
                        <div class="progress-fill" style="width: <?= getTimelineProgress($solicitudActiva['estado']) ?>%"></div>
                    </div>
                </div>

                <div class="next-step-section">
                    <div class="next-step-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="next-step-content">
                        <h4>Próximo paso</h4>
                        <p><?= getNextStep($solicitudActiva['estado']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Timeline Moderno -->
            <div class="timeline-card-modern">
                <div class="timeline-header">
                    <h3>
                        <i class="fas fa-route"></i>
                        Línea de Tiempo del Proceso
                    </h3>
                </div>
                <div class="timeline-content">
                    <div class="timeline-item modern <?= in_array($solicitudActiva['estado'], ['pendiente', 'aprobada', 'en_proceso', 'completado', 'concluida']) ? 'completed' : '' ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Solicitud Enviada</h4>
                            <p><?= formatDate($solicitudActiva['fecha_solicitud']) ?></p>
                            <span class="timeline-desc">Tu solicitud fue registrada exitosamente</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item modern <?= in_array($solicitudActiva['estado'], ['aprobada', 'en_proceso', 'completado', 'concluida']) ? 'completed' : ($solicitudActiva['estado'] == 'pendiente' ? 'current' : '') ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Revisión de Departamento</h4>
                            <p><?= $solicitudActiva['estado'] == 'pendiente' ? 'En proceso...' : 'Aprobada' ?></p>
                            <span class="timeline-desc">Evaluación por jefe de departamento</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item modern <?= in_array($solicitudActiva['estado'], ['en_proceso', 'completado', 'concluida']) ? 'completed' : ($solicitudActiva['estado'] == 'aprobada' ? 'current' : '') ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Servicio en Proceso</h4>
                            <p><?= $solicitudActiva['fecha_inicio_propuesta'] ? formatDate($solicitudActiva['fecha_inicio_propuesta']) : 'Pendiente' ?></p>
                            <span class="timeline-desc">Desarrollo de actividades</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item modern <?= in_array($solicitudActiva['estado'], ['completado', 'concluida']) ? 'completed' : ($solicitudActiva['estado'] == 'en_proceso' ? 'current' : '') ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Finalización</h4>
                            <p><?= $solicitudActiva['fecha_fin_propuesta'] ? formatDate($solicitudActiva['fecha_fin_propuesta']) : 'Estimado' ?></p>
                            <span class="timeline-desc">Conclusión del servicio social</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Información Detallada -->
        <div class="details-section-modern">
            <!-- Información del Proyecto -->
            <div class="detail-card modern">
                <div class="card-header-modern">
                    <div class="card-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3>Información del Proyecto</h3>
                </div>
                <div class="card-content">
                    <div class="project-info-modern">
                        <div class="project-header">
                            <h4><?= safe_html($solicitudActiva['nombre_proyecto']) ?></h4>
                            <span class="project-lab-badge"><?= safe_html($solicitudActiva['laboratorio']) ?></span>
                        </div>
                        
                        <?php if (!empty($solicitudActiva['descripcion'])): ?>
                        <div class="project-section">
                            <h5><i class="fas fa-align-left"></i> Descripción</h5>
                            <p><?= safe_html($solicitudActiva['descripcion']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($solicitudActiva['objetivos'])): ?>
                        <div class="project-section">
                            <h5><i class="fas fa-bullseye"></i> Objetivos</h5>
                            <p><?= safe_html($solicitudActiva['objetivos']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="project-dates">
                            <div class="date-item">
                                <i class="fas fa-calendar-plus"></i>
                                <div class="date-content">
                                    <span class="date-label">Fecha de Inicio</span>
                                    <span class="date-value"><?= formatDate($solicitudActiva['fecha_inicio_propuesta']) ?></span>
                                </div>
                            </div>
                            <div class="date-item">
                                <i class="fas fa-calendar-check"></i>
                                <div class="date-content">
                                    <span class="date-label">Fecha de Fin</span>
                                    <span class="date-value"><?= formatDate($solicitudActiva['fecha_fin_propuesta']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contactos -->
            <div class="detail-card modern">
                <div class="card-header-modern">
                    <div class="card-icon">
                        <i class="fas fa-address-book"></i>
                    </div>
                    <h3>Contactos de Supervisión</h3>
                </div>
                <div class="card-content">
                    <div class="contacts-grid-modern">
                        <div class="contact-card departamento">
                            <div class="contact-header">
                                <div class="contact-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="contact-info">
                                    <h4>Jefe de Departamento</h4>
                                    <p><?= safe_html($solicitudActiva['jefe_depto_nombre']) ?></p>
                                </div>
                            </div>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-building"></i>
                                    <span><?= safe_html($solicitudActiva['departamento']) ?></span>
                                </div>
                                <?php if (!empty($solicitudActiva['depto_email'])): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:<?= safe_html($solicitudActiva['depto_email']) ?>"><?= safe_html($solicitudActiva['depto_email']) ?></a>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($solicitudActiva['depto_telefono'])): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?= safe_html($solicitudActiva['depto_telefono']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($solicitudActiva['jefe_lab_nombre'])): ?>
                        <div class="contact-card laboratorio">
                            <div class="contact-header">
                                <div class="contact-avatar">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <div class="contact-info">
                                    <h4>Supervisor de Laboratorio</h4>
                                    <p><?= safe_html($solicitudActiva['jefe_lab_nombre']) ?></p>
                                </div>
                            </div>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-microscope"></i>
                                    <span><?= safe_html($solicitudActiva['laboratorio']) ?></span>
                                </div>
                                <?php if (!empty($solicitudActiva['lab_email'])): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:<?= safe_html($solicitudActiva['lab_email']) ?>"><?= safe_html($solicitudActiva['lab_email']) ?></a>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($solicitudActiva['lab_telefono'])): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?= safe_html($solicitudActiva['lab_telefono']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recursos y Documentos -->
        <div class="resources-section-modern">
            <!-- Documentos -->
            <?php if ($documentos): ?>
            <div class="resource-card modern">
                <div class="resource-header">
                    <div class="resource-icon">
                        <i class="fas fa-file-download"></i>
                    </div>
                    <div class="resource-title-section">
                        <h3>Documentos Oficiales</h3>
                        <span class="resource-count"><?= count($documentos) ?> documento(s)</span>
                    </div>
                </div>
                <div class="resource-content">
                    <div class="documents-grid">
                        <?php foreach ($documentos as $doc): ?>
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4><?= safe_html($doc['nombre']) ?></h4>
                                <p><?= safe_html($doc['numero']) ?></p>
                                <span class="document-date"><?= formatDate($doc['fecha']) ?></span>
                            </div>
                            <div class="document-actions">
                                <?php if (!empty($doc['archivo_path'])): ?>
                                <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                                <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" download class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i>
                                    Descargar
                                </a>
                                <?php else: ?>
                                <span class="unavailable">No disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reportes -->
            <?php if ($reportes): ?>
            <div class="resource-card modern">
                <div class="resource-header">
                    <div class="resource-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="resource-title-section">
                        <h3>Reportes Bimestrales</h3>
                        <span class="resource-count"><?= count($reportes) ?> reporte(s)</span>
                    </div>
                </div>
                <div class="resource-content">
                    <div class="reports-grid">
                        <?php foreach ($reportes as $reporte): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <div class="report-number">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Reporte <?= $reporte['numero_reporte'] ?></span>
                                </div>
                                <div class="report-status">
                                    <span class="status-badge modern <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                        <?= getEstadoText($reporte['estado']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="report-metrics">
                                <div class="report-metric">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= $reporte['fecha_entrega_formatted'] ?></span>
                                </div>
                                <div class="report-metric">
                                    <i class="fas fa-clock"></i>
                                    <span><?= $reporte['horas_reportadas'] ?> horas</span>
                                </div>
                                <?php if ($reporte['calificacion'] > 0): ?>
                                <div class="report-metric">
                                    <i class="fas fa-star"></i>
                                    <span>Calificación: <?= $reporte['calificacion'] ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historial -->
            <?php if (!empty($historialEstados)): ?>
            <div class="resource-card modern full-width">
                <div class="resource-header">
                    <div class="resource-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="resource-title-section">
                        <h3>Historial de Cambios</h3>
                        <span class="resource-count"><?= count($historialEstados) ?> evento(s)</span>
                    </div>
                </div>
                <div class="resource-content">
                    <div class="history-timeline">
                        <?php foreach ($historialEstados as $cambio): ?>
                        <div class="history-item">
                            <div class="history-marker">
                                <i class="fas fa-<?= getEstadoIcon($cambio['estado_nuevo']) ?>"></i>
                            </div>
                            <div class="history-content">
                                <div class="history-header">
                                    <h4><?= safe_html($cambio['comentarios'] ?? 'Cambio de estado') ?></h4>
                                    <span class="history-date"><?= formatDate($cambio['fecha_cambio']) ?></span>
                                </div>
                                <div class="history-details">
                                    <?php if (!empty($cambio['usuario_nombre'])): ?>
                                    <span class="history-user">Por: <?= safe_html($cambio['usuario_nombre']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($cambio['motivo_cambio'])): ?>
                                    <span class="history-reason"><?= safe_html($cambio['motivo_cambio']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Botones de Acción -->
        <div class="action-section-modern">
            <?php if ($solicitudActiva['estado'] == 'aprobada'): ?>
            <a href="../estudiantes/documentos.php" class="btn btn-primary btn-lg modern">
                <i class="fas fa-download"></i>
                <div class="btn-content">
                    <span>Descargar Oficio</span>
                    <small>Oficio de presentación</small>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($solicitudActiva['estado'] == 'en_proceso'): ?>
            <a href="../estudiantes/reportes.php" class="btn btn-primary btn-lg modern">
                <i class="fas fa-file-alt"></i>
                <div class="btn-content">
                    <span>Gestionar Reportes</span>
                    <small>Crear y revisar reportes</small>
                </div>
            </a>
            <a href="../estudiantes/horas.php" class="btn btn-secondary btn-lg modern">
                <i class="fas fa-clock"></i>
                <div class="btn-content">
                    <span>Registrar Horas</span>
                    <small>Control de actividades</small>
                </div>
            </a>
            <?php endif; ?>
            
            <a href="../estudiantes/reportes.php" class="btn btn-info btn-lg modern">
                <i class="fas fa-eye"></i>
                <div class="btn-content">
                    <span>Ver Todos los Reportes</span>
                    <small>Historial completo</small>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
/* Variables CSS basadas en el dashboard */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --success-light: #34d399;
    --warning: #f59e0b;
    --warning-light: #fbbf24;
    --error: #ef4444;
    --error-light: #f87171;
    --info: #3b82f6;
    --info-light: #60a5fa;
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
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Container Principal */
.status-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
    min-height: calc(100vh - 80px);
}

/* Header Mejorado */
.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 2rem;
    background: linear-gradient(135deg, var(--bg-white) 0%, var(--bg-light) 100%);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.status-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    opacity: 0.05;
    border-radius: 50%;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    position: relative;
    z-index: 2;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: var(--shadow-lg);
}

.header-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    background: linear-gradient(135deg, var(--text-primary), var(--primary));
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.header-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 1rem;
    position: relative;
    z-index: 2;
}

/* Estado Vacío Moderno */
.empty-state-card-modern {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    padding: 4rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    animation: slideInScale 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.empty-state-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.empty-circle {
    position: absolute;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    opacity: 0.05;
    animation: float 6s ease-in-out infinite;
}

.circle-1 {
    width: 150px;
    height: 150px;
    top: -75px;
    left: -75px;
    animation-delay: 0s;
}

.circle-2 {
    width: 100px;
    height: 100px;
    top: 20%;
    right: -50px;
    animation-delay: 2s;
}

.circle-3 {
    width: 200px;
    height: 200px;
    bottom: -100px;
    right: 20%;
    animation-delay: 4s;
}

.empty-state-content {
    position: relative;
    z-index: 2;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    margin: 0 auto 2rem;
    box-shadow: var(--shadow-xl);
    animation: pulse 2s infinite;
}

.empty-state-content h3 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.empty-state-content p {
    color: var(--text-secondary);
    margin-bottom: 2.5rem;
    font-size: 1.1rem;
    line-height: 1.6;
}

.empty-state-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Status Overview Moderno */
.status-overview-modern {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2.5rem;
}

/* Tarjeta Principal de Estado */
.main-status-card {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    position: relative;
    animation: slideInLeft 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.main-status-card.pending {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.03) 0%, rgba(251, 191, 36, 0.05) 100%);
    border: 1px solid rgba(245, 158, 11, 0.1);
}

.main-status-card.approved {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, rgba(52, 211, 153, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.1);
}

.main-status-card.in-progress {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(96, 165, 250, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.main-status-card.completed {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(129, 140, 248, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

.status-card-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.status-bg-circle {
    position: absolute;
    top: -30%;
    right: -15%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.status-bg-dots {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100px;
    background-image: radial-gradient(circle at 2px 2px, rgba(99, 102, 241, 0.1) 1px, transparent 0);
    background-size: 20px 20px;
}

.status-card-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 2.5rem;
}

.status-icon-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    flex-shrink: 0;
    box-shadow: var(--shadow-lg);
}

.main-status-card.pending .status-icon-large {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.main-status-card.approved .status-icon-large {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.main-status-card.in-progress .status-icon-large {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.main-status-card.completed .status-icon-large {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.status-main-info {
    flex: 1;
}

.status-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    line-height: 1.2;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    border-radius: 2rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
}

.main-status-card.pending .status-badge {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.main-status-card.approved .status-badge {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.main-status-card.in-progress .status-badge {
    background: rgba(59, 130, 246, 0.15);
    color: var(--info);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.main-status-card.completed .status-badge {
    background: rgba(99, 102, 241, 0.15);
    color: var(--primary);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.status-description {
    color: var(--text-secondary);
    margin: 0;
    font-size: 1rem;
    line-height: 1.5;
}

.status-progress-section {
    position: relative;
    z-index: 2;
    padding: 0 2.5rem 1.5rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.progress-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.progress-percentage {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.progress-bar-modern {
    height: 12px;
    background: var(--bg-gray);
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), var(--success-light));
    border-radius: 6px;
    position: relative;
    transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
    animation: shimmer 2s infinite;
}

.next-step-section {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem 2.5rem;
    background: linear-gradient(135deg, var(--bg-light) 0%, rgba(255, 255, 255, 0.8) 100%);
    border-top: 1px solid var(--border-light);
    backdrop-filter: blur(10px);
}

.next-step-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    box-shadow: var(--shadow);
}

.next-step-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.next-step-content p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 1rem;
    line-height: 1.5;
}

/* Timeline Moderno */
.timeline-card-modern {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    animation: slideInRight 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.timeline-header {
    padding: 2rem 2rem 1rem;
    border-bottom: 1px solid var(--border-light);
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-white) 100%);
}

.timeline-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.timeline-content {
    padding: 2rem;
}

.timeline-item.modern {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    padding: 1rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.timeline-item.modern:hover {
    background: var(--bg-light);
    transform: translateX(5px);
}

.timeline-item.modern:last-child {
    margin-bottom: 0;
}

.timeline-item.modern::after {
    content: '';
    position: absolute;
    left: 25px;
    top: 70px;
    width: 2px;
    height: calc(100% + 1rem);
    background: linear-gradient(to bottom, var(--border), transparent);
}

.timeline-item.modern:last-child::after {
    display: none;
}

.timeline-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-gray);
    color: var(--text-secondary);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    transition: var(--transition);
    border: 3px solid var(--bg-white);
    box-shadow: var(--shadow);
}

.timeline-item.modern.completed .timeline-marker {
    background: linear-gradient(135deg, var(--success), var(--success-light));
    color: white;
    transform: scale(1.1);
}

.timeline-item.modern.current .timeline-marker {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    animation: pulse 2s infinite;
    transform: scale(1.1);
}

.timeline-info {
    flex: 1;
}

.timeline-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.timeline-info p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    font-weight: 500;
}

.timeline-desc {
    font-size: 0.85rem;
    color: var(--text-light);
    font-style: italic;
}

/* Sección de Detalles Moderna */
.details-section-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2.5rem;
}

.detail-card.modern {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    transition: var(--transition);
    animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.detail-card.modern:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.card-header-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 2rem 2rem 1rem;
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-white) 100%);
    border-bottom: 1px solid var(--border-light);
}

.card-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    box-shadow: var(--shadow);
}

.card-header-modern h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* Información del Proyecto Moderna */
.project-info-modern {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.project-header {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.project-header h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.3;
}

.project-lab-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    border-radius: 2rem;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    align-self: flex-start;
}

.project-section {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.project-section h5 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.project-section p {
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.6;
    font-size: 0.95rem;
}

.project-dates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.date-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.date-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.date-item i {
    color: var(--primary);
    font-size: 1.25rem;
    width: 20px;
    text-align: center;
}

.date-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-label {
    font-size: 0.8rem;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.date-value {
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 600;
}

/* Contactos Modernos */
.contacts-grid-modern {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.contact-card {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.contact-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.contact-card.departamento {
    border-left-color: var(--primary);
}

.contact-card.laboratorio {
    border-left-color: var(--info);
}

.contact-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.contact-avatar {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: var(--shadow);
}

.contact-card.departamento .contact-avatar {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.contact-card.laboratorio .contact-avatar {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.contact-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.contact-info p {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.contact-detail {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    padding: 0.5rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.contact-detail:hover {
    background: rgba(99, 102, 241, 0.05);
}

.contact-detail i {
    width: 20px;
    color: var(--text-light);
    text-align: center;
}

.contact-detail a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.contact-detail a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Recursos Modernos */
.resources-section-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2.5rem;
}

.resource-card.modern {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    transition: var(--transition);
    animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.resource-card.modern:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.resource-card.modern.full-width {
    grid-column: 1 / -1;
}

.resource-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 2rem 2rem 1rem;
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-white) 100%);
    border-bottom: 1px solid var(--border-light);
}

.resource-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    box-shadow: var(--shadow);
}

.resource-title-section {
    flex: 1;
}

.resource-title-section h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.resource-count {
    font-size: 0.85rem;
    color: var(--text-light);
    font-weight: 500;
}

.resource-content {
    padding: 2rem;
}

/* Documentos Grid */
.documents-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.document-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    border-left: 4px solid var(--error);
}

.document-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.document-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--error), var(--error-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    font-size: 1.25rem;
    box-shadow: var(--shadow);
}

.document-info {
    flex: 1;
}

.document-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.document-info p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
}

.document-date {
    font-size: 0.8rem;
    color: var(--text-light);
}

.document-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.unavailable {
    color: var(--text-light);
    font-size: 0.85rem;
    font-style: italic;
    padding: 0.5rem 1rem;
    background: var(--bg-gray);
    border-radius: var(--radius);
}

/* Reportes Grid */
.reports-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.report-card {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    border-left: 4px solid var(--info);
}

.report-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.report-number {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

.report-number i {
    color: var(--primary);
}

.report-metrics {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.report-metric {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.report-metric i {
    width: 16px;
    color: var(--text-light);
    text-align: center;
}

/* Historial Timeline */
.history-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.history-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius);
    transition: var(--transition);
    position: relative;
}

.history-item:hover {
    background: var(--bg-light);
}

.history-item::after {
    content: '';
    position: absolute;
    left: 25px;
    top: 50px;
    width: 2px;
    height: calc(100% + 0.5rem);
    background: var(--border);
}

.history-item:last-child::after {
    display: none;
}

.history-marker {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    box-shadow: var(--shadow);
}

.history-content {
    flex: 1;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.5rem;
}

.history-header h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.history-date {
    font-size: 0.8rem;
    color: var(--text-light);
    white-space: nowrap;
}

.history-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.history-user,
.history-reason {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.history-user {
    font-weight: 500;
}

/* Status Badges Modernos */
.status-badge.modern {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid;
}

.status-badge.modern.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border-color: rgba(245, 158, 11, 0.2);
}

.status-badge.modern.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-color: rgba(16, 185, 129, 0.2);
}

.status-badge.modern.in-progress {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border-color: rgba(59, 130, 246, 0.2);
}

.status-badge.modern.completed {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    border-color: rgba(99, 102, 241, 0.2);
}

.status-badge.modern.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border-color: rgba(239, 68, 68, 0.2);
}

/* Botones de Acción Modernos */
.action-section-modern {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) 0.3s both;
    flex-wrap: wrap;
}

.btn.modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 2rem;
    border-radius: var(--radius-lg);
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.btn.modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn.modern:hover::before {
    left: 100%;
}

.btn.modern:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-xl);
}

.btn-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.btn-content span {
    font-size: 1rem;
    font-weight: 600;
}

.btn-content small {
    font-size: 0.8rem;
    opacity: 0.8;
    font-weight: 400;
}

/* Animaciones */
@keyframes slideInScale {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .status-overview-modern,
    .details-section-modern,
    .resources-section-modern {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1024px) {
    .status-container {
        padding: 1rem;
    }
    
    .status-card-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .project-dates {
        grid-template-columns: 1fr;
    }
    
    .action-section-modern {
        flex-direction: column;
        align-items: center;
    }
    
    .btn.modern {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .status-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
    
    .status-title {
        font-size: 1.5rem;
    }
    
    .contacts-grid-modern {
        gap: 1rem;
    }
    
    .contact-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .document-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .document-actions {
        justify-content: center;
    }
    
    .empty-state-actions {
        flex-direction: column;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .header-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .status-icon-large {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
    
    .empty-state-card-modern {
        padding: 2rem 1rem;
    }
    
    .empty-state-icon {
        width: 100px;
        height: 100px;
        font-size: 2.5rem;
    }
}

/* Mejoras de accesibilidad */
.btn:focus-visible,
.contact-detail a:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Modo de contraste alto */
@media (prefers-contrast: high) {
    :root {
        --border: #000000;
        --text-secondary: #000000;
        --bg-light: #ffffff;
    }
}

/* Modo de movimiento reducido */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración de animaciones mejoradas
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    // Observer para animaciones de entrada
    const animationObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observar elementos con animación
    const animatedElements = document.querySelectorAll('.detail-card.modern, .resource-card.modern');
    animatedElements.forEach(el => {
        el.style.animationPlayState = 'paused';
        animationObserver.observe(el);
    });

    // Animación mejorada de la barra de progreso
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        
        // Animar con delay escalonado
        setTimeout(() => {
            bar.style.width = width;
            
            // Agregar efecto de brillo después de la animación
            setTimeout(() => {
                bar.classList.add('progress-complete');
            }, 1500);
        }, 800);
    });

    // Contador animado para métricas
    const counters = document.querySelectorAll('.report-metric span, .resource-count');
    counters.forEach(counter => {
        const text = counter.textContent;
        const number = parseInt(text);
        
        if (!isNaN(number) && number > 0) {
            let current = 0;
            const increment = number / 20;
            const timer = setInterval(() => {
                current += increment;
                if (current >= number) {
                    current = number;
                    clearInterval(timer);
                }
                counter.textContent = text.replace(number.toString(), Math.floor(current).toString());
            }, 50);
        }
    });

    // Efectos de hover mejorados
    const interactiveCards = document.querySelectorAll(
        '.contact-card, .document-card, .report-card, .timeline-item.modern, .history-item'
    );
    
    interactiveCards.forEach(card => {
        card.addEventListener('mouseenter', function(e) {
            this.style.transform = 'translateY(-2px) translateX(5px)';
            this.style.boxShadow = 'var(--shadow-lg)';
            
            // Efecto ripple en el punto de hover
            const ripple = document.createElement('div');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(99, 102, 241, 0.1);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 0;
            `;
            
            const rippleContainer = this.querySelector('.card-content') || this;
            rippleContainer.style.position = 'relative';
            rippleContainer.style.overflow = 'hidden';
            rippleContainer.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });

    // Funcionalidad mejorada de copia de contactos
    const contactLinks = document.querySelectorAll('.contact-detail a[href^="mailto:"]');
    contactLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const email = this.textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(email).then(() => {
                    showNotification('Email copiado al portapapeles', 'success');
                    
                    // Efecto visual en el enlace
                    const originalText = this.textContent;
                    this.textContent = '✓ Copiado';
                    this.style.color = 'var(--success)';
                    
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.color = '';
                    }, 2000);
                });
            } else {
                // Fallback para navegadores sin clipboard API
                window.location.href = this.href;
            }
        });
        
        // Tooltip mejorado
        link.setAttribute('title', 'Click para copiar email');
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        link.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Estados de carga mejorados para botones
    const actionButtons = document.querySelectorAll('.btn.modern');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Solo agregar loading si no es descarga o enlace externo
            const href = this.getAttribute('href');
            if (href && (
                this.hasAttribute('download') ||
                this.getAttribute('target') === '_blank' ||
                href.startsWith('#')
            )) {
                return;
            }
            
            e.preventDefault();
            
            // Efecto de loading
            const originalContent = this.innerHTML;
            this.style.pointerEvents = 'none';
            this.innerHTML = `
                <i class="fas fa-spinner fa-spin"></i>
                <div class="btn-content">
                    <span>Cargando...</span>
                    <small>Por favor espera</small>
                </div>
            `;
            
            // Simular navegación después del loading
            setTimeout(() => {
                window.location.href = href;
            }, 1500);
        });
    });

    // Sistema de notificaciones mejorado
    function showNotification(message, type = 'info', duration = 4000) {
        const notification = document.createElement('div');
        notification.className = `notification-modern ${type}`;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        notification.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            </div>
            <div class="notification-content">
                <span>${message}</span>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-left: 4px solid var(--${type === 'success' ? 'success' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info'});
            color: var(--text-primary);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 1rem;
            max-width: 400px;
            animation: slideInNotification 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        `;
        
        // Botón de cerrar
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: var(--transition);
        `;
        
        closeBtn.addEventListener('click', () => removeNotification(notification));
        closeBtn.addEventListener('mouseenter', function() {
            this.style.background = 'var(--bg-gray)';
            this.style.color = 'var(--text-primary)';
        });
        closeBtn.addEventListener('mouseleave', function() {
            this.style.background = 'none';
            this.style.color = 'var(--text-light)';
        });
        
        document.body.appendChild(notification);
        
        // Auto-remove
        setTimeout(() => removeNotification(notification), duration);
    }
    
    function removeNotification(notification) {
        notification.style.animation = 'slideOutNotification 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Agregar estilos de animación para notificaciones
    const notificationStyles = document.createElement('style');
    notificationStyles.textContent = `
        @keyframes slideInNotification {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutNotification {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        @keyframes ripple {
            from {
                transform: scale(0);
                opacity: 1;
            }
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .progress-complete::after {
            animation-duration: 1s;
        }
        
        .notification-modern .notification-icon {
            width: 20px;
            text-align: center;
        }
        
        .notification-modern .notification-content {
            flex: 1;
            font-weight: 500;
        }
    `;
    document.head.appendChild(notificationStyles);

    // Auto-refresh para estados pendientes
    const statusCard = document.querySelector('.main-status-card');
    if (statusCard && statusCard.classList.contains('pending')) {
        let refreshCount = 0;
        const maxRefresh = 10;
        
        const autoRefresh = setInterval(() => {
            refreshCount++;
            console.log(`Verificando actualizaciones... (${refreshCount}/${maxRefresh})`);
            
            if (refreshCount >= maxRefresh) {
                clearInterval(autoRefresh);
                showNotification('Auto-actualización detenida', 'info');
            }
        }, 30000); // Cada 30 segundos
    }

    // Lazy loading para imágenes y contenido pesado
    const lazyElements = document.querySelectorAll('[data-lazy]');
    const lazyObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                element.classList.add('loaded');
                lazyObserver.unobserve(element);
            }
        });
    });
    
    lazyElements.forEach(el => lazyObserver.observe(el));
    
    // Mensaje de bienvenida inicial
    setTimeout(() => {
        showNotification('Estado de solicitud cargado correctamente', 'success');
    }, 1000);
});
</script>

<?php include '../../includes/footer.php'; ?>