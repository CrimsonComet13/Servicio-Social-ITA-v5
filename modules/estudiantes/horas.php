<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
if (!$session->isLoggedIn() || $session->getUserRole() !== 'estudiante') {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$db = Database::getInstance();
$usuario_id = $session->getUser()['id'];
$pageTitle = "Mi Progreso de Horas - " . APP_NAME;

// Obtener datos del estudiante y su progreso
try {
    $estudiante = $db->fetch("
        SELECT e.*, u.email
        FROM estudiantes e 
        JOIN usuarios u ON e.usuario_id = u.id 
        WHERE e.usuario_id = :usuario_id
    ", ['usuario_id' => $usuario_id]);

    if (!$estudiante) {
        throw new Exception("No se encontraron datos del estudiante.");
    }

    $horasRequeridas = 500;
    $horasCompletadas = $estudiante['horas_completadas'] ?? 0;
    $horasRestantes = max(0, $horasRequeridas - $horasCompletadas);
    $progresoPorcentaje = $horasRequeridas > 0 ? min(100, ($horasCompletadas / $horasRequeridas) * 100) : 0;

    // Obtener el detalle de horas por reporte
    $detalleHoras = $db->fetchAll("
        SELECT 
            r.id, 
            r.numero_reporte, 
            r.horas_reportadas, 
            r.fecha_entrega,
            r.estado,
            r.observaciones_estudiante,
            r.observaciones_responsable
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
        ORDER BY r.numero_reporte ASC
    ", ['estudiante_id' => $estudiante['id']]);

    // Obtener horas del reporte final (si existe)
    $reporteFinal = $db->fetch("
        SELECT 
            rf.id, 
            'Final' as numero_reporte, 
            NULL as horas_reportadas, 
            rf.fecha_entrega,
            rf.estado,
            NULL as observaciones_estudiante,
            NULL as observaciones_responsable
        FROM reportes_finales rf
        JOIN solicitudes_servicio s ON rf.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
    ", ['estudiante_id' => $estudiante['id']]);

    if ($reporteFinal) {
        $detalleHoras[] = $reporteFinal;
    }

    // Calcular estadísticas
    $horasAprobadas = 0;
    $horasPendientes = 0;
    foreach ($detalleHoras as $detalle) {
        if ($detalle['estado'] === 'aprobado' && $detalle['horas_reportadas']) {
            $horasAprobadas += $detalle['horas_reportadas'];
        } elseif (in_array($detalle['estado'], ['pendiente_evaluacion', 'pendiente']) && $detalle['horas_reportadas']) {
            $horasPendientes += $detalle['horas_reportadas'];
        }
    }

    // Calcular promedio de horas por semana
    $totalReportes = count($detalleHoras);
    $semanasTranscurridas = $totalReportes * 8; // Aproximadamente 8 semanas por bimestre
    $promedioSemanal = $semanasTranscurridas > 0 ? round($horasCompletadas / $semanasTranscurridas, 1) : 0;
    $semanasEstimadas = $horasRestantes > 0 && $promedioSemanal > 0 ? ceil($horasRestantes / $promedioSemanal) : 0;

} catch (Exception $e) {
    error_log("Error al cargar datos de horas: " . $e->getMessage());
    $error_message = "Error al cargar los datos: " . $e->getMessage();
    $estudiante = null;
    $detalleHoras = [];
    $horasCompletadas = 0;
    $horasRestantes = 0;
    $progresoPorcentaje = 0;
    $horasAprobadas = 0;
    $horasPendientes = 0;
    $promedioSemanal = 0;
    $semanasEstimadas = 0;
}

// Funciones helper actualizadas para coincidir con estudiante.php
function getEstadoCssClass($estado) {
    switch($estado) {
        case 'aprobado': return 'approved';
        case 'pendiente_evaluacion':
        case 'pendiente': return 'pending';
        case 'rechazado': return 'rejected';
        case 'en_revision': return 'in-progress';
        default: return 'pending';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'aprobado': return 'check-circle';
        case 'pendiente_evaluacion':
        case 'pendiente': return 'clock';
        case 'rechazado': return 'times-circle';
        case 'en_revision': return 'search';
        default: return 'question-circle';
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- ⭐ ESTRUCTURA ACTUALIZADA CON DISEÑO COHERENTE -->
<div class="dashboard-container">
    
    <!-- Page Header Actualizado -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span class="welcome-text">Mi Progreso de Horas</span>
                <span class="welcome-emoji">📊</span>
            </h1>
            <p class="welcome-subtitle">Seguimiento detallado de tus horas de servicio social</p>
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

    <?php if (isset($error_message)): ?>
        <div class="alert-error">
            <div class="alert-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="alert-content">
                <strong>Error al cargar datos</strong>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        </div>
    <?php elseif ($estudiante): ?>

    <!-- Progress Overview - Rediseñado -->
    <div class="status-overview-redesign">
        
        <!-- Main Progress Card -->
        <div class="progress-card">
            <h3 class="progress-title">Progreso General</h3>
            <div class="circular-progress-container">
                <div class="circular-progress-bg" style="--progress: <?= round($progresoPorcentaje) ?>">
                    <div class="circular-progress-inner">
                        <div class="progress-percentage"><?= round($progresoPorcentaje) ?>%</div>
                        <div class="progress-label">Completado</div>
                    </div>
                </div>
            </div>
            <div class="progress-details-list">
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Horas requeridas</span>
                    <span class="progress-detail-value"><?= $horasRequeridas ?></span>
                </div>
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Horas completadas</span>
                    <span class="progress-detail-value success"><?= $horasCompletadas ?></span>
                </div>
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Horas restantes</span>
                    <span class="progress-detail-value warning"><?= $horasRestantes ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Rediseñadas -->
        <div class="reports-card">
            <h3 class="reports-title">Estado de Horas</h3>
            <div class="reports-metrics">
                
                <!-- Approved Hours -->
                <div class="metric-item approved">
                    <div class="metric-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $horasAprobadas ?></div>
                        <div class="metric-label">Horas Aprobadas</div>
                    </div>
                </div>

                <!-- Pending Hours -->
                <div class="metric-item pending">
                    <div class="metric-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $horasPendientes ?></div>
                        <div class="metric-label">Horas Pendientes</div>
                    </div>
                </div>

                <!-- Average Hours -->
                <div class="metric-item total">
                    <div class="metric-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $promedioSemanal ?></div>
                        <div class="metric-label">Horas/Semana</div>
                    </div>
                </div>

                <!-- Estimated Time -->
                <div class="metric-item info">
                    <div class="metric-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $semanasEstimadas ?></div>
                        <div class="metric-label">Semanas Estimadas</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-rocket"></i>
                Acciones Rápidas
            </h2>
        </div>
        <div class="quick-actions-grid">
            <a href="reportes.php" class="quick-action-card primary">
                <div class="action-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="action-content">
                    <h3>Gestionar Reportes</h3>
                    <p>Entregar y revisar reportes bimestrales</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="solicitud.php" class="quick-action-card success">
                <div class="action-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="action-content">
                    <h3>Nueva Solicitud</h3>
                    <p>Crear nueva solicitud de servicio</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="documentos.php" class="quick-action-card info">
                <div class="action-icon">
                    <i class="fas fa-file-download"></i>
                </div>
                <div class="action-content">
                    <h3>Documentos</h3>
                    <p>Descargar oficios y constancias</p>
                </div>
                <div class="action-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Timeline Section - Rediseñada -->
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Historial de Reportes
            </h2>
            <div class="section-subtitle">
                Detalle cronológico de tus reportes de horas
            </div>
        </div>

        <?php if (empty($detalleHoras)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="empty-content">
                    <h3>Aún no has entregado reportes</h3>
                    <p>Comienza entregando tu primer reporte bimestral para llevar el control de tus horas.</p>
                    <a href="reportes.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Entregar Primer Reporte
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="timeline-redesign">
                <?php foreach ($detalleHoras as $index => $detalle): 
                    $isFinal = $detalle['numero_reporte'] === 'Final';
                    $reporteLabel = $isFinal ? 'Reporte Final' : 'Reporte Bimestral #' . $detalle['numero_reporte'];
                    $estado = $detalle['estado'] ?? 'desconocido';
                    $estadoClass = getEstadoCssClass($estado);
                    $estadoIcon = getEstadoIcon($estado);
                    $isLast = $index === count($detalleHoras) - 1;
                ?>
                <div class="timeline-item <?= $estadoClass ?> <?= $isLast ? 'last' : '' ?>">
                    <div class="timeline-marker">
                        <div class="timeline-icon">
                            <i class="fas fa-<?= $estadoIcon ?>"></i>
                        </div>
                        <?php if (!$isLast): ?>
                        <div class="timeline-line"></div>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <h4 class="timeline-title"><?= htmlspecialchars($reporteLabel) ?></h4>
                            <div class="timeline-meta">
                                <span class="timeline-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatDate($detalle['fecha_entrega']) ?>
                                </span>
                                <span class="badge <?= $estadoClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $estado)) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="timeline-body">
                            <?php if ($detalle['horas_reportadas']): ?>
                            <div class="timeline-stat">
                                <i class="fas fa-clock"></i>
                                <strong><?= htmlspecialchars($detalle['horas_reportadas']) ?> horas</strong> reportadas
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($detalle['observaciones_responsable'])): ?>
                                <div class="timeline-note supervisor">
                                    <div class="note-header">
                                        <i class="fas fa-user-tie"></i>
                                        <strong>Observación del Supervisor</strong>
                                    </div>
                                    <p><?= htmlspecialchars($detalle['observaciones_responsable']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($detalle['observaciones_estudiante'])): ?>
                                <div class="timeline-note student">
                                    <div class="note-header">
                                        <i class="fas fa-user-edit"></i>
                                        <strong>Tu Autoevaluación</strong>
                                    </div>
                                    <p><?= htmlspecialchars($detalle['observaciones_estudiante']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>

</div>

<!-- ⭐ CSS ACTUALIZADO COHERENTE CON ESTUDIANTE.PHP -->
<style>
/* Reutilizar variables y estilos base del dashboard */
.dashboard-container {
    padding: 1.5rem;
    max-width: none;
    margin: 0;
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

/* Status Overview Redesign */
.status-overview-redesign {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Progress Card */
.progress-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: var(--transition);
}

.progress-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.progress-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
}

.circular-progress-container {
    position: relative;
    width: 160px;
    height: 160px;
    margin-bottom: 1.5rem;
}

.circular-progress-bg {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: conic-gradient(
        var(--success) 0deg,
        var(--success) calc(var(--progress) * 3.6deg),
        var(--bg-gray) calc(var(--progress) * 3.6deg),
        var(--bg-gray) 360deg
    );
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 1s ease-out;
}

.circular-progress-inner {
    width: 120px;
    height: 120px;
    background: var(--bg-white);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
}

.progress-percentage {
    font-size: 2rem;
    font-weight: 800;
    color: var(--success);
    line-height: 1;
}

.progress-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.progress-details-list {
    width: 100%;
}

.progress-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
    transition: var(--transition);
}

.progress-detail-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.progress-detail-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.progress-detail-value {
    font-weight: 700;
    color: var(--text-primary);
}

.progress-detail-value.success {
    color: var(--success);
}

.progress-detail-value.warning {
    color: var(--warning);
}

/* Reports Card */
.reports-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    transition: var(--transition);
}

.reports-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.reports-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    text-align: center;
}

.reports-metrics {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.metric-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid transparent;
    transition: var(--transition);
}

.metric-item:hover {
    transform: translateX(5px);
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.metric-item.approved {
    border-left-color: var(--success);
}

.metric-item.pending {
    border-left-color: var(--warning);
}

.metric-item.total {
    border-left-color: var(--info);
}

.metric-item.info {
    border-left-color: var(--primary);
}

.metric-icon {
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

.metric-item.approved .metric-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.metric-item.pending .metric-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.metric-item.total .metric-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.metric-item.info .metric-icon {
    background: linear-gradient(135deg, var(--primary), #818cf8);
}

.metric-content {
    flex: 1;
}

.metric-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

.metric-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

/* Quick Actions Section */
.quick-actions-section {
    margin-bottom: 2rem;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.quick-action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.quick-action-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.quick-action-card.primary {
    border-left-color: var(--primary);
}

.quick-action-card.success {
    border-left-color: var(--success);
}

.quick-action-card.info {
    border-left-color: var(--info);
}

.action-icon {
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

.quick-action-card.primary .action-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.quick-action-card.success .action-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.quick-action-card.info .action-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.action-content {
    flex: 1;
}

.action-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.action-content p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.action-arrow {
    color: var(--text-light);
    font-size: 1.25rem;
    transition: var(--transition);
}

.quick-action-card:hover .action-arrow {
    color: var(--primary);
    transform: translateX(5px);
}

/* Content Section */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.section-header {
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
    margin: 0 0 0.5rem 0;
}

.section-subtitle {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    margin: 0 auto 1.5rem;
}

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

/* Timeline Redesign */
.timeline-redesign {
    position: relative;
}

.timeline-item {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem 0;
    position: relative;
}

.timeline-item:not(.last)::after {
    content: '';
    position: absolute;
    left: 24px;
    top: 70px;
    bottom: -1.5rem;
    width: 2px;
    background: var(--border);
}

.timeline-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
}

.timeline-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.timeline-item.approved .timeline-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.timeline-item.pending .timeline-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.timeline-item.in-progress .timeline-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.timeline-item.rejected .timeline-icon {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.timeline-content {
    flex: 1;
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    transition: var(--transition);
}

.timeline-content:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.timeline-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.timeline-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}

.badge.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.badge.in-progress {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.badge.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.timeline-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.timeline-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: var(--text-primary);
}

.timeline-note {
    padding: 1rem;
    border-radius: var(--radius);
    font-size: 0.9rem;
}

.timeline-note.supervisor {
    background: rgba(59, 130, 246, 0.05);
    border-left: 3px solid var(--info);
}

.timeline-note.student {
    background: rgba(16, 185, 129, 0.05);
    border-left: 3px solid var(--success);
}

.note-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.timeline-note.supervisor .note-header {
    color: var(--info);
}

.timeline-note.student .note-header {
    color: var(--success);
}

.timeline-note p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Alert Styles */
.alert-error {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    background: var(--error);
    flex-shrink: 0;
}

.alert-content strong {
    display: block;
    color: var(--error);
    margin-bottom: 0.25rem;
}

.alert-content p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .status-overview-redesign {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .timeline-meta {
        align-items: flex-start;
    }
    
    .progress-card,
    .reports-card {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .circular-progress-container {
        width: 140px;
        height: 140px;
    }
    
    .circular-progress-inner {
        width: 100px;
        height: 100px;
    }
    
    .progress-percentage {
        font-size: 1.75rem;
    }
    
    .metric-item {
        flex-direction: column;
        text-align: center;
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
    
    // Animate progress circles
    const progressElements = document.querySelectorAll('.circular-progress-bg');
    progressElements.forEach(progressElement => {
        const percentage = parseInt(progressElement.style.getPropertyValue('--progress'));
        
        let current = 0;
        const increment = percentage / 60;
        
        function animateProgress() {
            if (current < percentage) {
                current += increment;
                progressElement.style.setProperty('--progress', Math.min(current, percentage));
                requestAnimationFrame(animateProgress);
            }
        }
        
        setTimeout(() => {
            progressElement.style.setProperty('--progress', 0);
            animateProgress();
        }, 500);
    });
    
    // Add hover effects to timeline items
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    console.log('✅ Página de horas actualizada con diseño moderno');
});
</script>

<?php include '../../includes/footer.php'; ?>