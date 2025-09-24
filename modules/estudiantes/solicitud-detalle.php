<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener ID de la solicitud
$solicitudId = $_GET['id'] ?? 0;

// Función helper para htmlspecialchars segura
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Obtener datos del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

// Obtener solicitud con información completa
$solicitud = $db->fetch("
    SELECT s.*, 
           p.nombre_proyecto, p.descripcion as proyecto_descripcion, 
           p.objetivos as proyecto_objetivos, p.horas_requeridas,
           p.requisitos, p.tipo_actividades,
           jd.nombre as jefe_depto_nombre, jd.departamento, 
           jd.telefono as depto_telefono,
           u_depto.email as depto_email,
           jl.nombre as jefe_lab_nombre, jl.laboratorio, 
           jl.especialidad, jl.telefono as lab_telefono,
           u_lab.email as lab_email,
           aprobador.email as aprobador_email,
           e_aprobador.nombre as aprobador_nombre
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    JOIN usuarios u_depto ON jd.usuario_id = u_depto.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u_lab ON jl.usuario_id = u_lab.id
    LEFT JOIN usuarios aprobador ON s.aprobada_por = aprobador.id
    LEFT JOIN estudiantes e_aprobador ON aprobador.id = e_aprobador.usuario_id
    WHERE s.id = :solicitud_id 
    AND s.estudiante_id = :estudiante_id
", [
    'solicitud_id' => $solicitudId,
    'estudiante_id' => $estudiante['id']
]);

// Verificar que la solicitud existe y pertenece al estudiante
if (!$solicitud) {
    flashMessage('Solicitud no encontrada o no autorizada', 'error');
    redirectTo('/dashboard/estudiante.php');
}

// Obtener documentos relacionados
$documentos = [];
$oficios = $db->fetchAll("
    SELECT 'oficio' as tipo, 'Oficio de Presentación' as nombre,
           numero_oficio as numero, fecha_emision as fecha,
           archivo_path, estado,
           'file-contract' as icono, 'primary' as color
    FROM oficios_presentacion
    WHERE solicitud_id = :solicitud_id
    ORDER BY fecha_emision DESC
", ['solicitud_id' => $solicitud['id']]);

$documentos = array_merge($documentos, $oficios);

// Obtener reportes relacionados
$reportes = $db->fetchAll("
    SELECT r.*, 
           DATE_FORMAT(r.fecha_entrega, '%d/%m/%Y') as fecha_entrega_formatted,
           DATE_FORMAT(r.fecha_evaluacion, '%d/%m/%Y %H:%i') as fecha_evaluacion_formatted
    FROM reportes_bimestrales r
    WHERE r.solicitud_id = :solicitud_id
    ORDER BY r.numero_reporte
", ['solicitud_id' => $solicitud['id']]);

// Obtener historial de cambios (si existe la tabla)
$historial = [];
try {
    $historial = $db->fetchAll("
        SELECT la.*, u.email as usuario_email,
               CASE 
                   WHEN u.tipo_usuario = 'estudiante' THEN e.nombre
                   WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre
                   WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
                   ELSE u.email
               END as usuario_nombre
        FROM log_actividades la
        LEFT JOIN usuarios u ON la.usuario_id = u.id
        LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
        LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id AND u.tipo_usuario = 'jefe_departamento'
        LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id AND u.tipo_usuario = 'jefe_laboratorio'
        WHERE la.modulo = 'solicitudes' 
        AND la.registro_afectado_id = :solicitud_id
        ORDER BY la.created_at DESC
        LIMIT 20
    ", ['solicitud_id' => $solicitud['id']]);
} catch (Exception $e) {
    // Si no existe la tabla de log, crear historial básico
    $historial = [
        [
            'id' => 1,
            'accion' => 'crear_solicitud',
            'usuario_nombre' => $estudiante['nombre'],
            'detalles' => '{"estado": "pendiente"}',
            'created_at' => $solicitud['created_at']
        ]
    ];
    if ($solicitud['estado'] !== 'pendiente') {
        $historial[] = [
            'id' => 2,
            'accion' => 'cambio_estado',
            'usuario_nombre' => $solicitud['aprobador_nombre'] ?? 'Sistema',
            'detalles' => '{"estado_anterior": "pendiente", "estado_nuevo": "' . $solicitud['estado'] . '"}',
            'created_at' => $solicitud['fecha_aprobacion'] ?? $solicitud['updated_at']
        ];
    }
}

// Funciones helper
function getEstadoCssClass($estado) {
    switch($estado) {
        case 'pendiente': return 'pending';
        case 'aprobada': return 'approved';
        case 'rechazada': return 'rejected';
        case 'en_proceso': return 'in-progress';
        case 'concluida': return 'completed';
        case 'cancelada': return 'cancelled';
        default: return 'pending';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'pendiente': return 'hourglass-half';
        case 'aprobada': return 'check-circle';
        case 'rechazada': return 'times-circle';
        case 'en_proceso': return 'play-circle';
        case 'concluida': return 'trophy';
        case 'cancelada': return 'ban';
        default: return 'question-circle';
    }
}

function getEstadoTitle($estado) {
    switch($estado) {
        case 'pendiente': return 'Pendiente de Aprobación';
        case 'aprobada': return 'Solicitud Aprobada';
        case 'rechazada': return 'Solicitud Rechazada';
        case 'en_proceso': return 'Servicio Social en Proceso';
        case 'concluida': return 'Servicio Social Concluido';
        case 'cancelada': return 'Solicitud Cancelada';
        default: return 'Estado Desconocido';
    }
}

function getAccionText($accion) {
    switch($accion) {
        case 'crear_solicitud': return 'Solicitud creada';
        case 'cambio_estado': return 'Cambio de estado';
        case 'aprobar_solicitud': return 'Solicitud aprobada';
        case 'rechazar_solicitud': return 'Solicitud rechazada';
        case 'generar_oficio': return 'Oficio generado';
        case 'entregar_reporte': return 'Reporte entregado';
        case 'evaluar_reporte': return 'Reporte evaluado';
        default: return ucfirst(str_replace('_', ' ', $accion));
    }
}

$pageTitle = "Detalle de Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="detail-container">
    <!-- Header Section -->
    <div class="detail-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Detalle de Solicitud</h1>
                <p class="header-subtitle">Información completa de tu solicitud de servicio social</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="reportes.php" class="btn btn-primary">
                <i class="fas fa-file-alt"></i>
                Ver Reportes
            </a>
            <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Status Card Principal -->
    <div class="status-card-main <?= getEstadoCssClass($solicitud['estado']) ?>">
        <div class="status-background">
            <div class="status-circle"></div>
            <div class="status-pattern"></div>
        </div>
        <div class="status-content">
            <div class="status-icon-large">
                <i class="fas fa-<?= getEstadoIcon($solicitud['estado']) ?>"></i>
            </div>
            <div class="status-info">
                <h2 class="status-title"><?= getEstadoTitle($solicitud['estado']) ?></h2>
                <div class="status-badge">
                    <i class="fas fa-circle"></i>
                    <span><?= getEstadoText($solicitud['estado']) ?></span>
                </div>
                <div class="status-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Solicitado el <?= formatDate($solicitud['fecha_solicitud']) ?></span>
                    </div>
                    <?php if ($solicitud['fecha_aprobacion']): ?>
                    <div class="meta-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Procesado el <?= formatDate($solicitud['fecha_aprobacion']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($solicitud['estado'] === 'rechazada' && $solicitud['motivo_rechazo']): ?>
        <div class="rejection-notice">
            <div class="rejection-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="rejection-content">
                <h4>Motivo del rechazo:</h4>
                <p><?= safe_html($solicitud['motivo_rechazo']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Información Principal -->
    <div class="main-content-grid">
        <!-- Información del Proyecto -->
        <div class="info-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-project-diagram"></i>
                    Información del Proyecto
                </h3>
            </div>
            <div class="card-content">
                <div class="project-header">
                    <h4 class="project-title"><?= safe_html($solicitud['nombre_proyecto']) ?></h4>
                    <span class="project-badge"><?= safe_html($solicitud['laboratorio'] ?? 'Sin laboratorio') ?></span>
                </div>
                
                <?php if ($solicitud['proyecto_descripcion']): ?>
                <div class="project-section">
                    <h5><i class="fas fa-align-left"></i> Descripción</h5>
                    <p><?= safe_html($solicitud['proyecto_descripcion']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($solicitud['proyecto_objetivos']): ?>
                <div class="project-section">
                    <h5><i class="fas fa-bullseye"></i> Objetivos</h5>
                    <p><?= safe_html($solicitud['proyecto_objetivos']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($solicitud['tipo_actividades']): ?>
                <div class="project-section">
                    <h5><i class="fas fa-tasks"></i> Tipo de Actividades</h5>
                    <p><?= safe_html($solicitud['tipo_actividades']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($solicitud['requisitos']): ?>
                <div class="project-section">
                    <h5><i class="fas fa-list-ul"></i> Requisitos</h5>
                    <p><?= safe_html($solicitud['requisitos']) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="project-metrics">
                    <div class="metric-item">
                        <i class="fas fa-clock"></i>
                        <div class="metric-content">
                            <span class="metric-label">Horas Requeridas</span>
                            <span class="metric-value"><?= $solicitud['horas_requeridas'] ?> hrs</span>
                        </div>
                    </div>
                    <div class="metric-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="metric-content">
                            <span class="metric-label">Período Propuesto</span>
                            <span class="metric-value"><?= formatDate($solicitud['fecha_inicio_propuesta']) ?> - <?= formatDate($solicitud['fecha_fin_propuesta']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de Supervisores -->
        <div class="info-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-users"></i>
                    Supervisores Asignados
                </h3>
            </div>
            <div class="card-content">
                <!-- Jefe de Departamento -->
                <div class="supervisor-card departamento">
                    <div class="supervisor-header">
                        <div class="supervisor-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="supervisor-info">
                            <h4>Jefe de Departamento</h4>
                            <p><?= safe_html($solicitud['jefe_depto_nombre']) ?></p>
                        </div>
                    </div>
                    <div class="supervisor-details">
                        <div class="detail-row">
                            <i class="fas fa-building"></i>
                            <span><?= safe_html($solicitud['departamento']) ?></span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?= safe_html($solicitud['depto_email']) ?>"><?= safe_html($solicitud['depto_email']) ?></a>
                        </div>
                        <?php if ($solicitud['depto_telefono']): ?>
                        <div class="detail-row">
                            <i class="fas fa-phone"></i>
                            <span><?= safe_html($solicitud['depto_telefono']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Jefe de Laboratorio -->
                <?php if ($solicitud['jefe_lab_nombre']): ?>
                <div class="supervisor-card laboratorio">
                    <div class="supervisor-header">
                        <div class="supervisor-avatar">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="supervisor-info">
                            <h4>Supervisor de Laboratorio</h4>
                            <p><?= safe_html($solicitud['jefe_lab_nombre']) ?></p>
                        </div>
                    </div>
                    <div class="supervisor-details">
                        <div class="detail-row">
                            <i class="fas fa-microscope"></i>
                            <span><?= safe_html($solicitud['laboratorio']) ?></span>
                        </div>
                        <?php if ($solicitud['especialidad']): ?>
                        <div class="detail-row">
                            <i class="fas fa-star"></i>
                            <span><?= safe_html($solicitud['especialidad']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($solicitud['lab_email']): ?>
                        <div class="detail-row">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?= safe_html($solicitud['lab_email']) ?>"><?= safe_html($solicitud['lab_email']) ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if ($solicitud['lab_telefono']): ?>
                        <div class="detail-row">
                            <i class="fas fa-phone"></i>
                            <span><?= safe_html($solicitud['lab_telefono']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Información de la Solicitud -->
    <div class="solicitud-info-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-file-signature"></i>
                Detalles de la Solicitud
            </h3>
        </div>
        <div class="card-content">
            <div class="solicitud-grid">
                <div class="solicitud-section">
                    <h5><i class="fas fa-comment-dots"></i> Motivo de la Solicitud</h5>
                    <div class="solicitud-text">
                        <?= safe_html($solicitud['motivo_solicitud']) ?>
                    </div>
                </div>
                
                <?php if ($solicitud['observaciones_estudiante']): ?>
                <div class="solicitud-section">
                    <h5><i class="fas fa-sticky-note"></i> Observaciones del Estudiante</h5>
                    <div class="solicitud-text">
                        <?= safe_html($solicitud['observaciones_estudiante']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($solicitud['observaciones_jefe']): ?>
                <div class="solicitud-section">
                    <h5><i class="fas fa-user-check"></i> Observaciones del Supervisor</h5>
                    <div class="solicitud-text">
                        <?= safe_html($solicitud['observaciones_jefe']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="solicitud-metadata">
                <div class="metadata-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Fecha de solicitud: <?= formatDate($solicitud['fecha_solicitud']) ?></span>
                </div>
                <div class="metadata-item">
                    <i class="fas fa-clock"></i>
                    <span>Última actualización: <?= formatDate($solicitud['updated_at']) ?></span>
                </div>
                <?php if ($solicitud['aprobador_nombre']): ?>
                <div class="metadata-item">
                    <i class="fas fa-user-check"></i>
                    <span>Procesado por: <?= safe_html($solicitud['aprobador_nombre']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Documentos y Reportes -->
    <div class="resources-grid">
        <!-- Documentos -->
        <?php if ($documentos): ?>
        <div class="resource-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-file-download"></i>
                    Documentos Oficiales
                </h3>
                <span class="resource-count"><?= count($documentos) ?> documento(s)</span>
            </div>
            <div class="card-content">
                <div class="documents-list">
                    <?php foreach ($documentos as $doc): ?>
                    <div class="document-item">
                        <div class="document-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h4><?= safe_html($doc['nombre']) ?></h4>
                            <p><?= safe_html($doc['numero']) ?></p>
                            <span class="document-date"><?= formatDate($doc['fecha']) ?></span>
                        </div>
                        <div class="document-status">
                            <span class="status-badge <?= $doc['estado'] ?>">
                                <?= ucfirst($doc['estado']) ?>
                            </span>
                        </div>
                        <div class="document-actions">
                            <?php if ($doc['archivo_path']): ?>
                            <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" download class="btn btn-sm btn-success">
                                <i class="fas fa-download"></i>
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
        <div class="resource-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-file-alt"></i>
                    Reportes Bimestrales
                </h3>
                <span class="resource-count"><?= count($reportes) ?> reporte(s)</span>
            </div>
            <div class="card-content">
                <div class="reports-list">
                    <?php foreach ($reportes as $reporte): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <div class="report-number">
                                <i class="fas fa-file-alt"></i>
                                <span>Reporte <?= $reporte['numero_reporte'] ?></span>
                            </div>
                            <div class="report-status">
                                <span class="status-badge <?= $reporte['estado'] ?>">
                                    <?= getEstadoText($reporte['estado']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="report-details">
                            <div class="report-metric">
                                <i class="fas fa-calendar"></i>
                                <span><?= $reporte['fecha_entrega_formatted'] ?></span>
                            </div>
                            <div class="report-metric">
                                <i class="fas fa-clock"></i>
                                <span><?= $reporte['horas_reportadas'] ?> horas</span>
                            </div>
                            <?php if ($reporte['calificacion']): ?>
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
    </div>

    <!-- Historial de Actividades -->
    <?php if ($historial): ?>
    <div class="history-card">
        <div class="card-header">
            <h3>
                <i class="fas fa-history"></i>
                Historial de Actividades
            </h3>
            <span class="resource-count"><?= count($historial) ?> evento(s)</span>
        </div>
        <div class="card-content">
            <div class="timeline">
                <?php foreach ($historial as $evento): ?>
                <div class="timeline-item">
                    <div class="timeline-marker">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <h4><?= getAccionText($evento['accion']) ?></h4>
                            <span class="timeline-date"><?= formatDate($evento['created_at']) ?></span>
                        </div>
                        <div class="timeline-details">
                            <span class="timeline-user">Por: <?= safe_html($evento['usuario_nombre'] ?? 'Sistema') ?></span>
                            <?php if (isset($evento['detalles']) && $evento['detalles']): ?>
                            <?php $detalles = is_string($evento['detalles']) ? json_decode($evento['detalles'], true) : $evento['detalles']; ?>
                            <?php if ($detalles && is_array($detalles)): ?>
                            <div class="timeline-extra">
                                <?php foreach ($detalles as $key => $value): ?>
                                    <?php if (is_string($value) || is_numeric($value)): ?>
                                    <span class="detail-item"><?= ucfirst(str_replace('_', ' ', $key)) ?>: <?= safe_html($value) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
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

<style>
/* Variables CSS */
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
}

/* Container Principal */
.detail-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.detail-header {
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

.detail-header::before {
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

/* Status Card Principal */
.status-card-main {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    position: relative;
    margin-bottom: 2rem;
    animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.status-card-main.pending {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.03) 0%, rgba(251, 191, 36, 0.05) 100%);
    border: 1px solid rgba(245, 158, 11, 0.1);
}

.status-card-main.approved {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, rgba(52, 211, 153, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.1);
}

.status-card-main.rejected {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.03) 0%, rgba(248, 113, 113, 0.05) 100%);
    border: 1px solid rgba(239, 68, 68, 0.1);
}

.status-card-main.in-progress {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(96, 165, 250, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.status-card-main.completed {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(129, 140, 248, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

.status-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.status-circle {
    position: absolute;
    top: -30%;
    right: -15%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.status-pattern {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100px;
    background-image: radial-gradient(circle at 2px 2px, rgba(99, 102, 241, 0.1) 1px, transparent 0);
    background-size: 20px 20px;
}

.status-content {
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

.status-card-main.pending .status-icon-large {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.status-card-main.approved .status-icon-large {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.status-card-main.rejected .status-icon-large {
    background: linear-gradient(135deg, var(--error), var(--error-light));
}

.status-card-main.in-progress .status-icon-large {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.status-card-main.completed .status-icon-large {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.status-info {
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

.status-card-main.pending .status-badge {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-card-main.approved .status-badge {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-card-main.rejected .status-badge {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-card-main.in-progress .status-badge {
    background: rgba(59, 130, 246, 0.15);
    color: var(--info);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.status-card-main.completed .status-badge {
    background: rgba(99, 102, 241, 0.15);
    color: var(--primary);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.status-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.meta-item i {
    color: var(--text-light);
}

/* Rejection Notice */
.rejection-notice {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 2rem 2.5rem;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(248, 113, 113, 0.03));
    border-top: 1px solid rgba(239, 68, 68, 0.1);
}

.rejection-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--error);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.rejection-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.rejection-content p {
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

/* Main Content Grid */
.main-content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Info Cards */
.info-card,
.solicitud-info-card,
.resource-card,
.history-card {
    background: var(--bg-white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    transition: var(--transition);
}

.info-card:hover,
.solicitud-info-card:hover,
.resource-card:hover,
.history-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem 2rem 1rem;
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-white) 100%);
    border-bottom: 1px solid var(--border-light);
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.resource-count {
    font-size: 0.85rem;
    color: var(--text-light);
    font-weight: 500;
}

.card-content {
    padding: 2rem;
}

/* Project Info */
.project-header {
    margin-bottom: 2rem;
}

.project-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    line-height: 1.3;
}

.project-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 2rem;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.project-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.project-section:last-child {
    margin-bottom: 0;
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
}

.project-metrics {
    display: flex;
    gap: 1.5rem;
    margin-top: 2rem;
}

.metric-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    flex: 1;
}

.metric-item i {
    color: var(--primary);
    font-size: 1.25rem;
}

.metric-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.metric-label {
    font-size: 0.8rem;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.metric-value {
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 600;
}

/* Supervisores */
.supervisor-card {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.supervisor-card:last-child {
    margin-bottom: 0;
}

.supervisor-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
    transform: translateX(5px);
}

.supervisor-card.departamento {
    border-left-color: var(--primary);
}

.supervisor-card.laboratorio {
    border-left-color: var(--info);
}

.supervisor-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.supervisor-avatar {
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

.supervisor-card.departamento .supervisor-avatar {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.supervisor-card.laboratorio .supervisor-avatar {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.supervisor-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.supervisor-info p {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.supervisor-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    padding: 0.5rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.detail-row:hover {
    background: rgba(99, 102, 241, 0.05);
}

.detail-row i {
    width: 20px;
    color: var(--text-light);
    text-align: center;
}

.detail-row a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.detail-row a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Solicitud Info */
.solicitud-info-card {
    margin-bottom: 2rem;
}

.solicitud-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    margin-bottom: 2rem;
}

.solicitud-section h5 {
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

.solicitud-text {
    background: var(--bg-light);
    padding: 1.5rem;
    border-radius: var(--radius);
    color: var(--text-secondary);
    line-height: 1.6;
    border-left: 4px solid var(--primary);
}

.solicitud-metadata {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-light);
}

.metadata-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.metadata-item i {
    color: var(--text-light);
    width: 16px;
    text-align: center;
}

/* Resources Grid */
.resources-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Documents List */
.documents-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    border-left: 4px solid var(--error);
}

.document-item:hover {
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

.document-status {
    display: flex;
    align-items: center;
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

/* Reports List */
.reports-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.report-item {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    border-left: 4px solid var(--info);
}

.report-item:hover {
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

.report-details {
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

/* Timeline */
.timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 20px;
    bottom: 20px;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius);
    transition: var(--transition);
    position: relative;
}

.timeline-item:hover {
    background: var(--bg-light);
}

.timeline-marker {
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

.timeline-content {
    flex: 1;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.5rem;
}

.timeline-header h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-date {
    font-size: 0.8rem;
    color: var(--text-light);
    white-space: nowrap;
}

.timeline-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.timeline-user {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.timeline-extra {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.detail-item {
    font-size: 0.8rem;
    color: var(--text-light);
    background: var(--bg-light);
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    display: inline-block;
    width: fit-content;
}

/* Status Badges */
.status-badge {
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

.status-badge.pendiente_evaluacion,
.status-badge.pendiente {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border-color: rgba(245, 158, 11, 0.2);
}

.status-badge.evaluado,
.status-badge.aprobada,
.status-badge.aprobado {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-color: rgba(16, 185, 129, 0.2);
}

.status-badge.rechazado,
.status-badge.rechazada {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border-color: rgba(239, 68, 68, 0.2);
}

.status-badge.en_proceso {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border-color: rgba(59, 130, 246, 0.2);
}

.status-badge.completado,
.status-badge.concluida {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    border-color: rgba(99, 102, 241, 0.2);
}

.status-badge.generado {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-color: rgba(16, 185, 129, 0.2);
}

.status-badge.entregado {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border-color: rgba(59, 130, 246, 0.2);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.5rem 1rem;
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
    background: rgba(99, 102, 241, 0.05);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), var(--success-light));
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), var(--info-light));
    color: white;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animations */
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

/* Responsive Design */
@media (max-width: 1200px) {
    .main-content-grid,
    .resources-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1024px) {
    .detail-container {
        padding: 1rem;
    }
    
    .status-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .project-metrics {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .detail-header {
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
    
    .supervisor-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .document-item,
    .report-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .document-actions,
    .report-details {
        justify-content: center;
    }
    
    .timeline::before {
        left: 20px;
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
    
    .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

/* Mejoras de accesibilidad */
.btn:focus-visible,
.detail-row a:focus-visible {
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
    // Configuración de animaciones
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
    const animatedElements = document.querySelectorAll('.info-card, .resource-card, .history-card');
    animatedElements.forEach(el => {
        el.style.animationPlayState = 'paused';
        animationObserver.observe(el);
    });

    // Efectos de hover mejorados
    const interactiveCards = document.querySelectorAll(
        '.supervisor-card, .document-item, .report-item, .timeline-item'
    );
    
    interactiveCards.forEach(card => {
        card.addEventListener('mouseenter', function(e) {
            this.style.transform = 'translateY(-2px) translateX(5px)';
            this.style.boxShadow = 'var(--shadow-lg)';
            
            // Efecto ripple
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
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });

    // Funcionalidad de copia de emails
    const emailLinks = document.querySelectorAll('a[href^="mailto:"]');
    emailLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const email = this.textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(email).then(() => {
                    showNotification('Email copiado al portapapeles', 'success');
                    
                    const originalText = this.textContent;
                    this.textContent = '✓ Copiado';
                    this.style.color = 'var(--success)';
                    
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.color = '';
                    }, 2000);
                });
            } else {
                window.location.href = this.href;
            }
        });
        
        link.setAttribute('title', 'Click para copiar email');
    });

    // Estados de carga para botones
    const actionButtons = document.querySelectorAll('.btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && (
                this.hasAttribute('download') ||
                this.getAttribute('target') === '_blank' ||
                href.startsWith('#')
            )) {
                return;
            }
            
            if (href && !href.startsWith('mailto:')) {
                this.style.pointerEvents = 'none';
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
                
                setTimeout(() => {
                    this.style.pointerEvents = '';
                    this.innerHTML = originalContent;
                }, 2000);
            }
        });
    });

    // Sistema de notificaciones
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
        
        document.body.appendChild(notification);
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

    // Agregar estilos de animación
    const notificationStyles = document.createElement('style');
    notificationStyles.textContent = `
        @keyframes slideInNotification {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideOutNotification {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
        
        @keyframes ripple {
            from { transform: scale(0); opacity: 1; }
            to { transform: scale(4); opacity: 0; }
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

    // Mensaje de bienvenida
    setTimeout(() => {
        showNotification('Detalles de solicitud cargados correctamente', 'success');
    }, 1000);
});
</script>

<?php include '../../includes/footer.php'; ?>