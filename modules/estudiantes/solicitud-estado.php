<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener datos del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

// Obtener solicitud activa con información completa (CORREGIDO: JOINs con usuarios para obtener emails)
$solicitudActiva = $db->fetch("
    SELECT s.*, p.nombre_proyecto, p.descripcion, p.objetivos,
           jl.nombre as jefe_lab_nombre, jl.laboratorio, jl.telefono as lab_telefono, u_lab.email as lab_email,
           jd.nombre as jefe_depto_nombre, jd.departamento, jd.telefono as depto_telefono, u_depto.email as depto_email
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u_lab ON jl.usuario_id = u_lab.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    JOIN usuarios u_depto ON jd.usuario_id = u_depto.id
    WHERE s.estudiante_id = :estudiante_id 
    AND s.estado IN ('pendiente', 'aprobada', 'en_proceso', 'completado')
    ORDER BY s.fecha_solicitud DESC
    LIMIT 1
", ['estudiante_id' => $estudiante['id']]);

// Obtener historial de cambios de estado
$historialEstados = [];
if ($solicitudActiva) {
    $historialEstados = $db->fetchAll("
        SELECT he.*, u.email as usuario_email 
        FROM historial_estados he
        LEFT JOIN usuarios u ON he.usuario_id = u.id
        WHERE he.solicitud_id = :solicitud_id
        ORDER BY he.fecha_cambio DESC
    ", ['solicitud_id' => $solicitudActiva['id']]);
}

// Obtener documentos relacionados
$documentos = [];
if ($solicitudActiva) {
    // Oficios de presentación
    $oficios = $db->fetchAll("
        SELECT 'oficio' as tipo, 'Oficio de Presentación' as nombre, numero_oficio as numero, 
               fecha_emision as fecha, archivo_path, estado
        FROM oficios_presentacion
        WHERE solicitud_id = :solicitud_id
        ORDER BY fecha_emision DESC
    ", ['solicitud_id' => $solicitudActiva['id']]);
    
    $documentos = array_merge($documentos, $oficios);
}

// Obtener reportes relacionados
$reportes = [];
if ($solicitudActiva) {
    $reportes = $db->fetchAll("
        SELECT r.*, DATE_FORMAT(r.fecha_entrega, '%d/%m/%Y') as fecha_entrega_formatted
        FROM reportes_bimestrales r
        WHERE r.solicitud_id = :solicitud_id
        ORDER BY r.numero_reporte
    ", ['solicitud_id' => $solicitudActiva['id']]);
}

// Función para obtener el progreso del timeline
function getTimelineProgress($estado) {
    switch($estado) {
        case 'pendiente': return 25;
        case 'aprobada': return 50;
        case 'en_proceso': return 75;
        case 'completado': return 100;
        default: return 0;
    }
}

// Función para obtener el siguiente paso
function getNextStep($estado) {
    switch($estado) {
        case 'pendiente': return 'Esperar aprobación del jefe de departamento';
        case 'aprobada': return 'Descargar oficio de presentación e iniciar actividades';
        case 'en_proceso': return 'Continuar con reportes bimestrales y registro de horas';
        case 'completado': return 'Proceso finalizado exitosamente';
        default: return 'Crear nueva solicitud';
    }
}

$pageTitle = "Estado de Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="status-container">
    <!-- Header Section -->
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
        <!-- No Active Request State -->
        <div class="empty-state-card">
            <div class="empty-state-icon">
                <i class="fas fa-file-plus"></i>
            </div>
            <div class="empty-state-content">
                <h3>No tienes una solicitud activa</h3>
                <p>Para ver el estado de tu solicitud, primero debes crear una solicitud de servicio social.</p>
                <div class="empty-state-actions">
                    <a href="../estudiantes/solicitud.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Crear Solicitud
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Status Overview -->
        <div class="status-overview">
            <!-- Main Status Card -->
            <div class="main-status-card <?= getEstadoCssClass($solicitudActiva['estado']) ?>">
                <div class="status-card-header">
                    <div class="status-icon">
                        <i class="fas fa-<?= getEstadoIcon($solicitudActiva['estado']) ?>"></i>
                    </div>
                    <div class="status-info">
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
                
                <div class="status-progress">
                    <div class="progress-header">
                        <span>Progreso del proceso</span>
                        <span><?= getTimelineProgress($solicitudActiva['estado']) ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= getTimelineProgress($solicitudActiva['estado']) ?>%"></div>
                    </div>
                </div>

                <div class="next-step">
                    <div class="next-step-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="next-step-content">
                        <h4>Próximo paso</h4>
                        <p><?= getNextStep($solicitudActiva['estado']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Timeline Progress -->
            <div class="timeline-card">
                <div class="timeline-header">
                    <h3>
                        <i class="fas fa-route"></i>
                        Línea de Tiempo
                    </h3>
                </div>
                <div class="timeline-content">
                    <div class="timeline-item <?= in_array($solicitudActiva['estado'], ['pendiente', 'aprobada', 'en_proceso', 'completado']) ? 'completed' : '' ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Solicitud Enviada</h4>
                            <p><?= formatDate($solicitudActiva['fecha_solicitud']) ?></p>
                            <span class="timeline-desc">Tu solicitud fue registrada exitosamente</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?= in_array($solicitudActiva['estado'], ['aprobada', 'en_proceso', 'completado']) ? 'completed' : ($solicitudActiva['estado'] == 'pendiente' ? 'current' : '') ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Revisión de Departamento</h4>
                            <p><?= $solicitudActiva['estado'] == 'pendiente' ? 'En proceso...' : 'Aprobada' ?></p>
                            <span class="timeline-desc">Evaluación por jefe de departamento</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?= in_array($solicitudActiva['estado'], ['en_proceso', 'completado']) ? 'completed' : ($solicitudActiva['estado'] == 'aprobada' ? 'current' : '') ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="timeline-info">
                            <h4>Servicio en Proceso</h4>
                            <p><?= $solicitudActiva['fecha_inicio_propuesta'] ? formatDate($solicitudActiva['fecha_inicio_propuesta']) : 'Pendiente' ?></p>
                            <span class="timeline-desc">Desarrollo de actividades</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?= $solicitudActiva['estado'] == 'completado' ? 'completed' : ($solicitudActiva['estado'] == 'en_proceso' ? 'current' : '') ?>">
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

        <!-- Details Section -->
        <div class="details-section">
            <!-- Project Information -->
            <div class="detail-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-project-diagram"></i>
                        Información del Proyecto
                    </h3>
                </div>
                <div class="card-content">
                    <div class="project-info">
                        <div class="project-header">
                            <h4><?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?></h4>
                            <span class="project-lab"><?= htmlspecialchars($solicitudActiva['laboratorio'] ?? 'No asignado') ?></span>
                        </div>
                        
                        <?php if ($solicitudActiva['descripcion']): ?>
                        <div class="project-section">
                            <h5>Descripción</h5>
                            <p><?= htmlspecialchars($solicitudActiva['descripcion']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($solicitudActiva['objetivos']): ?>
                        <div class="project-section">
                            <h5>Objetivos</h5>
                            <p><?= htmlspecialchars($solicitudActiva['objetivos']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="project-dates">
                            <div class="date-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Inicio: <?= formatDate($solicitudActiva['fecha_inicio_propuesta']) ?></span>
                            </div>
                            <div class="date-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>Fin: <?= formatDate($solicitudActiva['fecha_fin_propuesta']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contacts Information -->
            <div class="detail-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-address-book"></i>
                        Contactos
                    </h3>
                </div>
                <div class="card-content">
                    <div class="contacts-grid">
                        <div class="contact-item">
                            <div class="contact-header">
                                <div class="contact-icon departamento">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="contact-info">
                                    <h4>Jefe de Departamento</h4>
                                    <p><?= htmlspecialchars($solicitudActiva['jefe_depto_nombre']) ?></p>
                                </div>
                            </div>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-building"></i>
                                    <span><?= htmlspecialchars($solicitudActiva['departamento']) ?></span>
                                </div>
                                <?php if ($solicitudActiva['depto_email']): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:<?= htmlspecialchars($solicitudActiva['depto_email']) ?>"><?= htmlspecialchars($solicitudActiva['depto_email']) ?></a>
                                </div>
                                <?php endif; ?>
                                <?php if ($solicitudActiva['depto_telefono']): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?= htmlspecialchars($solicitudActiva['depto_telefono']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($solicitudActiva['jefe_lab_nombre']): ?>
                        <div class="contact-item">
                            <div class="contact-header">
                                <div class="contact-icon laboratorio">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <div class="contact-info">
                                    <h4>Supervisor de Laboratorio</h4>
                                    <p><?= htmlspecialchars($solicitudActiva['jefe_lab_nombre']) ?></p>
                                </div>
                            </div>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-microscope"></i>
                                    <span><?= htmlspecialchars($solicitudActiva['laboratorio']) ?></span>
                                </div>
                                <?php if ($solicitudActiva['lab_email']): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:<?= htmlspecialchars($solicitudActiva['lab_email']) ?>"><?= htmlspecialchars($solicitudActiva['lab_email']) ?></a>
                                </div>
                                <?php endif; ?>
                                <?php if ($solicitudActiva['lab_telefono']): ?>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span><?= htmlspecialchars($solicitudActiva['lab_telefono']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources Section -->
        <div class="resources-section">
            <!-- Documents -->
            <?php if ($documentos): ?>
            <div class="resource-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-file-download"></i>
                        Documentos
                    </h3>
                    <span class="count-badge"><?= count($documentos) ?></span>
                </div>
                <div class="card-content">
                    <div class="documents-list">
                        <?php foreach ($documentos as $doc): ?>
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4><?= htmlspecialchars($doc['nombre']) ?></h4>
                                <p><?= htmlspecialchars($doc['numero']) ?></p>
                                <span class="document-date"><?= formatDate($doc['fecha']) ?></span>
                            </div>
                            <div class="document-actions">
                                <?php if ($doc['archivo_path']): ?>
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

            <!-- Reports -->
            <?php if ($reportes): ?>
            <div class="resource-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-file-alt"></i>
                        Reportes
                    </h3>
                    <span class="count-badge"><?= count($reportes) ?></span>
                </div>
                <div class="card-content">
                    <div class="reports-grid">
                        <?php foreach ($reportes as $reporte): ?>
                        <div class="report-item">
                            <div class="report-header">
                                <div class="report-number">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Reporte <?= $reporte['numero_reporte'] ?></span>
                                </div>
                                <div class="report-status">
                                    <span class="status-badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                        <?= getEstadoText($reporte['estado']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="report-info">
                                <div class="report-detail">
                                    <i class="fas fa-calendar"></i>
                                    <span><?= $reporte['fecha_entrega_formatted'] ?></span>
                                </div>
                                <div class="report-detail">
                                    <i class="fas fa-clock"></i>
                                    <span><?= $reporte['horas_reportadas'] ?> horas</span>
                                </div>
                                <?php if ($reporte['calificacion']): ?>
                                <div class="report-detail">
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

        <!-- Action Buttons -->
        <div class="action-section">
            <?php if ($solicitudActiva['estado'] == 'aprobada'): ?>
            <a href="../estudiantes/documentos.php" class="btn btn-primary btn-lg">
                <i class="fas fa-download"></i>
                Descargar Oficio de Presentación
            </a>
            <?php endif; ?>
            
            <?php if ($solicitudActiva['estado'] == 'en_proceso'): ?>
            <a href="../estudiantes/reportes.php" class="btn btn-primary btn-lg">
                <i class="fas fa-file-alt"></i>
                Gestionar Reportes
            </a>
            <a href="../estudiantes/horas.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-clock"></i>
                Registrar Horas
            </a>
            <?php endif; ?>
            
            <a href="../estudiantes/reportes.php" class="btn btn-info btn-lg">
                <i class="fas fa-eye"></i>
                Ver Todos los Reportes
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
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

/* Status Container */
.status-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Status Header */
.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.header-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.header-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Empty State */
.empty-state-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 3rem;
    text-align: center;
    animation: slideIn 0.6s ease-out;
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--primary);
    margin: 0 auto 1.5rem;
}

.empty-state-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-state-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.empty-state-actions {
    display: flex;
    justify-content: center;
}

/* Status Overview */
.status-overview {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Main Status Card */
.main-status-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: slideIn 0.6s ease-out;
}

.main-status-card.pending {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(251, 191, 36, 0.05));
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.main-status-card.approved {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(52, 211, 153, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.main-status-card.in-progress {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(96, 165, 250, 0.05));
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.main-status-card.completed {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(129, 140, 248, 0.05));
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.status-card-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
}

.status-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.main-status-card.pending .status-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.main-status-card.approved .status-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.main-status-card.in-progress .status-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.main-status-card.completed .status-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.status-info {
    flex: 1;
}

.status-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.main-status-card.pending .status-badge {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.main-status-card.approved .status-badge {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.main-status-card.in-progress .status-badge {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.main-status-card.completed .status-badge {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.status-description {
    color: var(--text-secondary);
    margin: 0;
}

.status-progress {
    padding: 0 2rem 1.5rem;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.progress-bar {
    height: 8px;
    background: var(--bg-gray);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #34d399);
    border-radius: 4px;
    transition: width 1s ease-out;
}

.next-step {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
}

.next-step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.next-step-content h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.next-step-content p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.9rem;
}

/* Timeline Card */
.timeline-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    animation: slideIn 0.6s ease-out 0.2s both;
}

.timeline-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.timeline-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-content {
    padding: 1.5rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item::after {
    content: '';
    position: absolute;
    left: 20px;
    top: 50px;
    width: 2px;
    height: calc(100% - 10px);
    background: var(--border);
}

.timeline-item:last-child::after {
    display: none;
}

.timeline-marker {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-gray);
    color: var(--text-secondary);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.timeline-item.completed .timeline-marker {
    background: var(--success);
    color: white;
}

.timeline-item.current .timeline-marker {
    background: var(--primary);
    color: white;
    animation: pulse 2s infinite;
}

.timeline-info {
    flex: 1;
}

.timeline-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.timeline-info p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    font-weight: 500;
}

.timeline-desc {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Details Section */
.details-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.detail-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    animation: slideIn 0.6s ease-out 0.4s both;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-content {
    padding: 1.5rem;
}

/* Project Info */
.project-info {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.project-header h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.project-lab {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--bg-light);
    border-radius: 2rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.project-section h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.project-section p {
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

.project-dates {
    display: flex;
    gap: 1rem;
}

.date-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.date-item i {
    color: var(--primary);
}

/* Contacts */
.contacts-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.contact-item {
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.contact-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.contact-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.contact-icon.departamento {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.contact-icon.laboratorio {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.contact-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.contact-info p {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.contact-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.contact-detail i {
    width: 16px;
    color: var(--text-light);
}

.contact-detail a {
    color: var(--primary);
    text-decoration: none;
}

.contact-detail a:hover {
    text-decoration: underline;
}

/* Resources Section */
.resources-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.resource-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    animation: slideIn 0.6s ease-out 0.6s both;
}

.count-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
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
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.document-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: var(--error);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.document-info {
    flex: 1;
}

.document-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.document-info p {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
}

.document-date {
    font-size: 0.75rem;
    color: var(--text-light);
}

.document-actions {
    display: flex;
    gap: 0.5rem;
}

.unavailable {
    color: var(--text-light);
    font-size: 0.8rem;
    font-style: italic;
}

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.report-item {
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.report-number {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.report-number i {
    color: var(--primary);
}

.report-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.report-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.report-detail i {
    width: 16px;
    color: var(--text-light);
}

/* Action Section */
.action-section {
    display: flex;
    gap: 1rem;
    justify-content: center;
    animation: slideIn 0.6s ease-out 0.8s both;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
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

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
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
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active, .status-badge.aprobada, .status-badge.aprobado {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.pending, .status-badge.pendiente, .status-badge.pendiente_evaluacion {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-badge.completed, .status-badge.completado, .status-badge.en_proceso {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
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

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.05);
        opacity: 0.8;
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .status-overview,
    .details-section,
    .resources-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .status-container {
        padding: 1rem;
    }
    
    .status-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .status-card-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .action-section {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .contact-header {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .document-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .document-actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
}

/* Focus improvements for accessibility */
.btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bar
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 500);
    });
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.detail-card, .resource-card, .document-item, .report-item, .contact-item');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'var(--shadow-lg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Solo agregar loading si no es un enlace externo
            if (this.getAttribute('href') && 
                (this.getAttribute('href').startsWith('#') || 
                 this.hasAttribute('download') ||
                 this.getAttribute('target') === '_blank')) {
                return; // Permitir navegación/descarga normal
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
    
    // Timeline scroll animation
    const timelineItems = document.querySelectorAll('.timeline-item');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, {
            threshold: 0.1
        });
        
        timelineItems.forEach(item => observer.observe(item));
    }
    
    // Copy contact info functionality
    const contactDetails = document.querySelectorAll('.contact-detail');
    contactDetails.forEach(detail => {
        const email = detail.querySelector('a[href^="mailto:"]');
        if (email) {
            email.addEventListener('click', function(e) {
                // Optional: Add copy to clipboard functionality
                const emailText = this.textContent;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(emailText).then(() => {
                        // Show temporary success message
                        const originalText = this.textContent;
                        this.textContent = 'Copiado!';
                        setTimeout(() => {
                            this.textContent = originalText;
                        }, 1000);
                    });
                }
            });
        }
    });
    
    // Enhanced document download tracking
    const downloadButtons = document.querySelectorAll('a[download]');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Track downloads
            console.log('Document downloaded:', this.href);
            
            // Show success notification
            showNotification('Descarga iniciada', 'success');
        });
    });
    
    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle"></i>
            <span>${message}</span>
        `;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--${type === 'success' ? 'success' : 'info'});
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(20px)';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Auto-refresh status every 5 minutes
    setInterval(() => {
        // Check for status updates
        const statusElement = document.querySelector('.main-status-card');
        if (statusElement && statusElement.classList.contains('pending')) {
            // Optional: Add auto-refresh for pending status
            console.log('Checking for status updates...');
        }
    }, 300000); // 5 minutes
});
</script>

<?php include '../../includes/footer.php'; ?>