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
            r.observaciones
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
            NULL as observaciones
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

// Funciones helper


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

<div class="horas-container">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="header-text">
                <h1 class="page-title">Mi Progreso de Horas</h1>
                <p class="page-subtitle">Seguimiento detallado de tus horas de servicio social</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="reportes.php" class="btn btn-primary">
                <i class="fas fa-file-alt"></i>
                Gestionar Reportes
            </a>
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

    <!-- Progress Overview Cards -->
    <div class="progress-overview">
        
        <!-- Main Progress Card -->
        <div class="overview-card main-progress">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-chart-line"></i>
                    Progreso General
                </h3>
            </div>
            <div class="card-body-custom">
                <div class="circular-progress-large">
                    <div class="progress-circle-large" style="--progress: <?= round($progresoPorcentaje) ?>">
                        <div class="progress-inner-large">
                            <div class="progress-percentage-large"><?= round($progresoPorcentaje) ?>%</div>
                            <div class="progress-label-large">Completado</div>
                        </div>
                    </div>
                </div>
                <div class="progress-stats-grid">
                    <div class="stat-item primary">
                        <div class="stat-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $horasRequeridas ?></div>
                            <div class="stat-label">Horas Requeridas</div>
                        </div>
                    </div>
                    <div class="stat-item success">
                        <div class="stat-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $horasCompletadas ?></div>
                            <div class="stat-label">Horas Completadas</div>
                        </div>
                    </div>
                    <div class="stat-item warning">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $horasRestantes ?></div>
                            <div class="stat-label">Horas Restantes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            
            <!-- Approved Hours Card -->
            <div class="stat-card success-card">
                <div class="stat-card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-value"><?= $horasAprobadas ?></div>
                    <div class="stat-card-label">Horas Aprobadas</div>
                    <div class="stat-card-detail">
                        Validadas por tu supervisor
                    </div>
                </div>
            </div>

            <!-- Pending Hours Card -->
            <div class="stat-card warning-card">
                <div class="stat-card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-value"><?= $horasPendientes ?></div>
                    <div class="stat-card-label">Horas Pendientes</div>
                    <div class="stat-card-detail">
                        En proceso de evaluación
                    </div>
                </div>
            </div>

            <!-- Average Card -->
            <div class="stat-card info-card">
                <div class="stat-card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-value"><?= $promedioSemanal ?></div>
                    <div class="stat-card-label">Horas/Semana</div>
                    <div class="stat-card-detail">
                        Promedio semanal actual
                    </div>
                </div>
            </div>

            <!-- Estimated Time Card -->
            <div class="stat-card primary-card">
                <div class="stat-card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-value"><?= $semanasEstimadas ?></div>
                    <div class="stat-card-label">Semanas Estimadas</div>
                    <div class="stat-card-detail">
                        Para completar servicio
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Timeline Section -->
    <div class="timeline-section">
        <div class="section-header-custom">
            <h2 class="section-title-custom">
                <i class="fas fa-history"></i>
                Historial de Reportes
            </h2>
            <div class="section-subtitle-custom">
                Detalle cronológico de tus reportes de horas
            </div>
        </div>

        <?php if (empty($detalleHoras)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No hay reportes registrados</h3>
                <p>Aún no has entregado ningún reporte de horas. Comienza a registrar tu progreso.</p>
                <a href="reportes.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Crear Primer Reporte
                </a>
            </div>
        <?php else: ?>
            
            <div class="timeline">
                <?php 
                $totalReportes = count($detalleHoras);
                foreach ($detalleHoras as $index => $detalle): 
                    $isFinal = $detalle['numero_reporte'] === 'Final';
                    $reporteLabel = $isFinal ? 'Reporte Final' : 'Bimestre ' . $detalle['numero_reporte'];
                    $estadoBadge = getEstadoBadgeClass($detalle['estado'] ?? 'desconocido');
                    $estadoIcon = getEstadoIcon($detalle['estado'] ?? 'desconocido');
                    $estadoText = getEstadoText($detalle['estado'] ?? 'desconocido');
                    $isLast = ($index === $totalReportes - 1);
                ?>
                
                <div class="timeline-item <?= $detalle['estado'] === 'aprobado' ? 'completed' : '' ?> <?= $isLast ? 'last' : '' ?>">
                    <div class="timeline-marker">
                        <div class="timeline-icon">
                            <i class="fas fa-<?= $estadoIcon ?>"></i>
                        </div>
                    </div>
                    
                    <div class="timeline-content">
                        <div class="timeline-card">
                            <div class="timeline-card-header">
                                <div class="timeline-card-title">
                                    <h4><?= htmlspecialchars($reporteLabel) ?></h4>
                                    <span class="badge <?= $estadoBadge ?>">
                                        <i class="fas fa-<?= $estadoIcon ?>"></i>
                                        <?= $estadoText ?>
                                    </span>
                                </div>
                                <div class="timeline-card-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatDate($detalle['fecha_entrega']) ?>
                                </div>
                            </div>
                            
                            <div class="timeline-card-body">
                                <div class="timeline-info-grid">
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Horas Reportadas</div>
                                            <div class="info-value">
                                                <?= $detalle['horas_reportadas'] ? htmlspecialchars($detalle['horas_reportadas']) . ' horas' : 'N/A' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($detalle['observaciones'])): ?>
                                    <div class="info-item full-width">
                                        <div class="info-icon">
                                            <i class="fas fa-comment"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Observaciones</div>
                                            <div class="info-value observation">
                                                <?= htmlspecialchars($detalle['observaciones']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($detalle['estado'] === 'aprobado'): ?>
                            <div class="timeline-card-footer success">
                                <i class="fas fa-check-circle"></i>
                                <span>Reporte aprobado y horas validadas</span>
                            </div>
                            <?php elseif ($detalle['estado'] === 'pendiente_evaluacion'): ?>
                            <div class="timeline-card-footer warning">
                                <i class="fas fa-hourglass-half"></i>
                                <span>En espera de evaluación por tu supervisor</span>
                            </div>
                            <?php elseif ($detalle['estado'] === 'rechazado'): ?>
                            <div class="timeline-card-footer danger">
                                <i class="fas fa-times-circle"></i>
                                <span>Reporte rechazado - Revisa las observaciones</span>
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

<style>
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --success: #10b981;
    --success-light: #34d399;
    --warning: #f59e0b;
    --warning-light: #fbbf24;
    --error: #ef4444;
    --info: #3b82f6;
    --info-light: #60a5fa;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    --border: #e5e7eb;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
}

.horas-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border);
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: var(--shadow-lg);
}

.header-text {
    flex: 1;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    color: var(--text-secondary);
    margin: 0;
    font-size: 1.1rem;
}

/* Alert */
.alert-error {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
}

.alert-icon {
    width: 40px;
    height: 40px;
    background: var(--error);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.alert-content strong {
    display: block;
    color: var(--error);
    margin-bottom: 0.5rem;
}

.alert-content p {
    color: var(--text-secondary);
    margin: 0;
}

/* Progress Overview */
.progress-overview {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Main Progress Card */
.overview-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.main-progress {
    grid-column: 1 / -1;
}

.card-header-custom {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.card-title-custom {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.card-body-custom {
    padding: 2rem;
}

/* Circular Progress Large */
.circular-progress-large {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

.progress-circle-large {
    position: relative;
    width: 200px;
    height: 200px;
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
    transition: all 1s ease-out;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.progress-inner-large {
    width: 160px;
    height: 160px;
    background: var(--bg-white);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.progress-percentage-large {
    font-size: 3rem;
    font-weight: 800;
    color: var(--success);
    line-height: 1;
}

.progress-label-large {
    font-size: 1rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

/* Progress Stats Grid */
.progress-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    border-left: 4px solid transparent;
    transition: var(--transition);
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-item.primary {
    border-left-color: var(--primary);
}

.stat-item.success {
    border-left-color: var(--success);
}

.stat-item.warning {
    border-left-color: var(--warning);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-item.primary .stat-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-item.success .stat-icon {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.stat-item.warning .stat-icon {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Statistics Cards */
.stats-cards {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: var(--transition);
    border-top: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.success-card {
    border-top-color: var(--success);
}

.stat-card.warning-card {
    border-top-color: var(--warning);
}

.stat-card.info-card {
    border-top-color: var(--info);
}

.stat-card.primary-card {
    border-top-color: var(--primary);
}

.stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    margin-bottom: 1rem;
}

.success-card .stat-card-icon {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.warning-card .stat-card-icon {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.info-card .stat-card-icon {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.primary-card .stat-card-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-card-label {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.stat-card-detail {
    font-size: 0.85rem;
    color: var(--text-light);
}

/* Timeline Section */
.timeline-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
}

.section-header-custom {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid var(--border);
}

.section-title-custom {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.section-subtitle-custom {
    color: var(--text-secondary);
    font-size: 1rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    margin: 0 auto 2rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 3rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-item.last {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -3rem;
    top: 0;
}

.timeline-icon {
    width: 60px;
    height: 60px;
    background: var(--bg-white);
    border: 4px solid var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary);
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
}

.timeline-item.completed .timeline-icon {
    background: var(--success);
    border-color: var(--success);
    color: white;
}

.timeline-content {
    padding-left: 1rem;
}

.timeline-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: var(--transition);
}

.timeline-card:hover {
    transform: translateX(10px);
    box-shadow: var(--shadow-lg);
}

.timeline-card-header {
    background: var(--bg-white);
    padding: 1.5rem;
    border-bottom: 2px solid var(--border);
}

.timeline-card-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.timeline-card-title h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-card-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.timeline-card-body {
    padding: 1.5rem;
}

.timeline-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.info-value.observation {
    font-size: 0.95rem;
    font-weight: 400;
    line-height: 1.5;
}

.timeline-card-footer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.timeline-card-footer.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.timeline-card-footer.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.timeline-card-footer.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.badge-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.badge-secondary {
    background: rgba(107, 114, 128, 0.1);
    color: var(--text-secondary);
    border: 1px solid rgba(107, 114, 128, 0.2);
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
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .progress-overview {
        grid-template-columns: 1fr;
    }
    
    .progress-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .horas-container {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-content {
        width: 100%;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .stats-cards {
        grid-template-columns: 1fr;
    }
    
    .progress-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline {
        padding-left: 2rem;
    }
    
    .timeline::before {
        left: 20px;
    }
    
    .timeline-marker {
        left: -2rem;
    }
    
    .timeline-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .timeline-info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.5rem;
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .circular-progress-large {
        margin-bottom: 1.5rem;
    }
    
    .progress-circle-large {
        width: 150px;
        height: 150px;
    }
    
    .progress-inner-large {
        width: 120px;
        height: 120px;
    }
    
    .progress-percentage-large {
        font-size: 2rem;
    }
    
    .stat-card-value {
        font-size: 2rem;
    }
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

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.progress-overview > * {
    animation: slideInUp 0.6s ease-out;
}

.timeline-item {
    animation: fadeIn 0.5s ease-out;
}

.timeline-item:nth-child(1) { animation-delay: 0.1s; }
.timeline-item:nth-child(2) { animation-delay: 0.2s; }
.timeline-item:nth-child(3) { animation-delay: 0.3s; }
.timeline-item:nth-child(4) { animation-delay: 0.4s; }
.timeline-item:nth-child(5) { animation-delay: 0.5s; }

/* Print Styles */
@media print {
    .page-header,
    .header-actions,
    .btn {
        display: none;
    }
    
    .horas-container {
        padding: 0;
    }
    
    .timeline-card {
        break-inside: avoid;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animación del progreso circular
    const progressCircle = document.querySelector('.progress-circle-large');
    if (progressCircle) {
        const percentage = parseInt(progressCircle.style.getPropertyValue('--progress'));
        
        // Animar desde 0
        let current = 0;
        const increment = percentage / 80;
        
        function animateProgress() {
            if (current < percentage) {
                current += increment;
                progressCircle.style.setProperty('--progress', Math.min(current, percentage));
                requestAnimationFrame(animateProgress);
            }
        }
        
        setTimeout(() => {
            progressCircle.style.setProperty('--progress', 0);
            animateProgress();
        }, 300);
    }
    
    // Smooth scroll para timeline items
    const timelineItems = document.querySelectorAll('.timeline-item');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateX(0)';
            }
        });
    }, {
        threshold: 0.1
    });
    
    timelineItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        item.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(item);
    });
    
    console.log('✅ Módulo de horas inicializado correctamente');
});
</script>

<?php include '../../includes/footer.php'; ?>