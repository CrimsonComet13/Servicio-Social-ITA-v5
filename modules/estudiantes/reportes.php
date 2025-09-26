<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener solicitud activa del estudiante
$solicitudActiva = $db->fetch("
    SELECT s.*, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.estudiante_id = :estudiante_id 
    AND s.estado IN ('aprobada', 'en_proceso')
    LIMIT 1
", ['estudiante_id' => $estudianteId]);

// Obtener reportes del estudiante
$reportes = [];
if ($solicitudActiva) {
    $reportes = $db->fetchAll("
        SELECT r.*, p.nombre_proyecto
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        WHERE s.estudiante_id = :estudiante_id
        ORDER BY r.numero_reporte
    ", ['estudiante_id' => $estudianteId]);
}

// Determinar el próximo reporte a entregar
$proximoReporte = 1;
if (!empty($reportes)) {
    $ultimoReporte = end($reportes);
    $proximoReporte = $ultimoReporte['numero_reporte'] + 1;
    
    // Si ya se entregaron los 3 reportes, no hay próximo
    if ($proximoReporte > 3) {
        $proximoReporte = null;
    }
}

$pageTitle = "Mis Reportes - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <!-- Header Section -->
    <div class="reports-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Reportes Bimestrales</h1>
                <p class="header-subtitle">Gestión y seguimiento de tus reportes de servicio social</p>
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
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="empty-state-content">
                <h3>No tienes una solicitud activa</h3>
                <p>Para poder entregar reportes, primero debes tener una solicitud de servicio social aprobada.</p>
                <div class="empty-state-actions">
                    <a href="../estudiantes/solicitud.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Crear Solicitud
                    </a>
                    <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Ir al Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Service Information Card -->
        <div class="service-info-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Servicio Social
                </h2>
            </div>
            <div class="service-info-grid">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Proyecto</span>
                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Laboratorio</span>
                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['laboratorio'] ?? 'N/A') ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Supervisor</span>
                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['jefe_lab_nombre'] ?? 'N/A') ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Periodo</span>
                        <span class="info-value"><?= formatDate($solicitudActiva['fecha_inicio_propuesta']) ?> - <?= formatDate($solicitudActiva['fecha_fin_propuesta']) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Horas Completadas</span>
                        <span class="info-value"><?= $solicitudActiva['horas_completadas'] ?? 0 ?> / <?= getConfig('horas_servicio_social', 500) ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="info-content">
                        <span class="info-label">Estado</span>
                        <span class="badge <?= getEstadoBadgeClass($solicitudActiva['estado']) ?>">
                            <?= getEstadoText($solicitudActiva['estado']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Report Action -->
        <?php if ($proximoReporte && $solicitudActiva['estado'] === 'en_proceso'): ?>
            <div class="action-card next-report">
                <div class="action-content">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-info">
                        <h3>Próximo Reporte: Bimestre <?= $proximoReporte ?></h3>
                        <p>Entrega tu reporte bimestral para registrar las actividades realizadas y las horas cumplidas durante este periodo.</p>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="../estudiantes/entregar-reporte.php?numero=<?= $proximoReporte ?>" class="btn btn-primary">
                        <i class="fas fa-upload"></i>
                        Entregar Reporte <?= $proximoReporte ?>
                    </a>
                </div>
            </div>
        <?php elseif ($solicitudActiva['estado'] === 'en_proceso'): ?>
            <div class="alert alert-success">
                <i class="fas fa-trophy"></i>
                <div>
                    <strong>¡Felicidades!</strong>
                    <p>Has completado todos los reportes bimestrales. Tu servicio social está próximo a concluir.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reports Section -->
        <div class="reports-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Reportes Entregados
                </h2>
                <div class="section-stats">
                    <span class="stat-item">
                        <i class="fas fa-file-alt"></i>
                        <?= count($reportes) ?> reportes
                    </span>
                </div>
            </div>

            <?php if ($reportes): ?>
                <div class="reports-grid">
                    <?php foreach ($reportes as $reporte): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="report-number">
                                <i class="fas fa-file-alt"></i>
                                <span>Reporte <?= $reporte['numero_reporte'] ?></span>
                            </div>
                            <div class="report-status">
                                <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                    <?= getEstadoText($reporte['estado']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="report-content">
                            <div class="report-info">
                                <div class="info-row">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><strong>Periodo:</strong> <?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <span><strong>Horas:</strong> <?= $reporte['horas_reportadas'] ?> horas</span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-paper-plane"></i>
                                    <span><strong>Entregado:</strong> <?= formatDate($reporte['fecha_entrega']) ?></span>
                                </div>
                                
                                <?php if ($reporte['calificacion']): ?>
                                <div class="info-row">
                                    <i class="fas fa-star"></i>
                                    <span><strong>Calificación:</strong> <?= $reporte['calificacion'] ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($reporte['observaciones_evaluador']): ?>
                            <div class="report-observations">
                                <h4><i class="fas fa-comment"></i> Observaciones:</h4>
                                <p><?= htmlspecialchars($reporte['observaciones_evaluador']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="report-actions">
                            <a href="../estudiantes/reporte-detalle.php?id=<?= $reporte['id'] ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i>
                                Ver Detalles
                            </a>
                            
                            <?php if ($reporte['archivo_path']): ?>
                                <a href="<?= UPLOAD_URL . $reporte['archivo_path'] ?>" target="_blank" class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i>
                                    Descargar
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($reporte['estado'] === 'pendiente_evaluacion'): ?>
                                <span class="status-text">
                                    <i class="fas fa-hourglass-half"></i>
                                    En evaluación
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-reports-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="empty-content">
                        <h3>No hay reportes entregados</h3>
                        <p>Los reportes se habilitan una vez que tu solicitud sea aprobada y esté en proceso.</p>
                        <?php if ($proximoReporte && $solicitudActiva['estado'] === 'en_proceso'): ?>
                        <a href="../estudiantes/entregar-reporte.php?numero=<?= $proximoReporte ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Entregar Primer Reporte
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
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

/* Reports Container */
.reports-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Reports Header */
.reports-header {
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

/* Empty State Card */
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
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.1), rgba(251, 191, 36, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--warning);
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
    gap: 1rem;
    justify-content: center;
}

/* Service Info Card */
.service-info-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    animation: slideIn 0.6s ease-out;
}

.card-header {
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

.service-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    padding: 1.5rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.info-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
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
    display: block;
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.info-value {
    display: block;
    font-size: 0.95rem;
    color: var(--text-primary);
    font-weight: 500;
}

/* Action Card */
.action-card {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(52, 211, 153, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideIn 0.6s ease-out;
}

.action-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), #34d399);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.action-info h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.action-info p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.95rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
}

/* Alert */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    animation: slideIn 0.6s ease-out;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: #064e3b;
}

.alert i {
    font-size: 1.5rem;
    margin-top: 0.125rem;
}

.alert strong {
    display: block;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.alert p {
    margin: 0;
}

/* Reports Section */
.reports-section {
    animation: slideIn 0.6s ease-out 0.2s both;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
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

.section-stats {
    display: flex;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Reports Grid */
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
}

/* Report Card */
.report-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: var(--transition);
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--bg-light);
    border-bottom: 1px solid var(--border-light);
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

.report-content {
    padding: 1.5rem;
}

.report-info {
    margin-bottom: 1rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.info-row i {
    width: 16px;
    color: var(--text-secondary);
    flex-shrink: 0;
}

.info-row:last-child {
    margin-bottom: 0;
}

.report-observations {
    background: var(--bg-light);
    padding: 1rem;
    border-radius: var(--radius);
    margin-top: 1rem;
}

.report-observations h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.report-observations p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.report-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
    flex-wrap: wrap;
}

.status-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-style: italic;
}

/* Empty Reports State */
.empty-reports-state {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 3rem;
    text-align: center;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary);
    margin: 0 auto 1.5rem;
}

.empty-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge.active, .badge.aprobada, .badge.aprobado {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.badge.pending, .badge.pendiente, .badge.pendiente_evaluacion {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.badge.completed, .badge.completado, .badge.en_proceso {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.badge.rejected, .badge.rechazada, .badge.rechazado {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
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

/* Responsive Design */
@media (max-width: 1024px) {
    .service-info-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .reports-grid {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
    
    .action-card {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }
    
    .action-content {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .reports-container {
        padding: 1rem;
    }
    
    .reports-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .service-info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .empty-state-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: center;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .report-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
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
    
    .service-info-card,
    .action-card,
    .report-card {
        margin-left: -1rem;
        margin-right: -1rem;
        border-radius: 0;
    }
}

/* Loading states */
.btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Focus improvements for accessibility */
.btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.report-card:focus-within {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
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
    // Add hover effects to cards
    const cards = document.querySelectorAll('.report-card, .info-item');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
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
    
    // Animate report cards with stagger effect
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach((card, index) => {
        card.style.animationDelay = `${0.1 * index}s`;
        card.style.animation = 'slideIn 0.6s ease-out both';
    });
    
    // Add status indicator animations
    const badges = document.querySelectorAll('.badge');
    badges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Smooth scroll for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add intersection observer for animations
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
        
        const animateElements = document.querySelectorAll('.service-info-card, .action-card, .reports-section');
        animateElements.forEach(el => observer.observe(el));
    }
    
    // Auto-refresh page data every 5 minutes (for status updates)
    setInterval(() => {
        // Check if there are pending reports that might have been updated
        const pendingReports = document.querySelectorAll('.badge.pendiente_evaluacion');
        if (pendingReports.length > 0) {
            // Optional: Add a subtle notification that data might be outdated
            console.log('Checking for report status updates...');
        }
    }, 300000); // 5 minutes
});
</script>

<?php include '../../includes/footer.php'; ?>