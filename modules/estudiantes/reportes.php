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

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Reportes Bimestrales</h1>
        <p>Gestión de reportes de servicio social</p>
    </div>

    <?php if (!$solicitudActiva): ?>
        <div class="alert alert-info">
            <h3>No tienes una solicitud activa</h3>
            <p>Para poder entregar reportes, primero debes tener una solicitud de servicio social aprobada.</p>
            <div class="alert-actions">
                <a href="/modules/estudiantes/solicitud.php" class="btn btn-primary">
                    Crear Solicitud
                </a>
                <a href="/dashboard/estudiante.php" class="btn btn-secondary">
                    Volver al Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Información del servicio social -->
        <div class="info-card">
            <h3>Información del Servicio Social</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Proyecto:</strong> <?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?>
                </div>
                <div class="info-item">
                    <strong>Laboratorio:</strong> <?= htmlspecialchars($solicitudActiva['laboratorio'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <strong>Jefe de Laboratorio:</strong> <?= htmlspecialchars($solicitudActiva['jefe_lab_nombre'] ?? 'N/A') ?>
                </div>
                <div class="info-item">
                    <strong>Periodo:</strong> <?= formatDate($solicitudActiva['fecha_inicio_propuesta']) ?> - <?= formatDate($solicitudActiva['fecha_fin_propuesta']) ?>
                </div>
                <div class="info-item">
                    <strong>Horas Completadas:</strong> <?= $solicitudActiva['horas_completadas'] ?> / <?= getConfig('horas_servicio_social', 500) ?>
                </div>
                <div class="info-item">
                    <strong>Estado:</strong> 
                    <span class="badge <?= getEstadoBadgeClass($solicitudActiva['estado']) ?>">
                        <?= getEstadoText($solicitudActiva['estado']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Próximo reporte -->
        <?php if ($proximoReporte && $solicitudActiva['estado'] === 'en_proceso'): ?>
            <div class="action-card">
                <div class="action-content">
                    <h3>Próximo Reporte: Bimestre <?= $proximoReporte ?></h3>
                    <p>Entrega tu reporte bimestral para registrar las actividades realizadas y las horas cumplidas.</p>
                </div>
                <div class="action-buttons">
                    <a href="/modules/estudiantes/entregar-reporte.php?numero=<?= $proximoReporte ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Entregar Reporte
                    </a>
                </div>
            </div>
        <?php elseif ($solicitudActiva['estado'] === 'en_proceso'): ?>
            <div class="alert alert-success">
                <h3>¡Felicidades!</h3>
                <p>Has completado todos los reportes bimestrales. Tu servicio social está próximo a concluir.</p>
            </div>
        <?php endif; ?>

        <!-- Lista de reportes -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Reportes Entregados</h2>
            </div>

            <?php if ($reportes): ?>
                <div class="reportes-grid">
                    <?php foreach ($reportes as $reporte): ?>
                    <div class="reporte-card">
                        <div class="reporte-header">
                            <h3>Reporte Bimestral <?= $reporte['numero_reporte'] ?></h3>
                            <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                <?= getEstadoText($reporte['estado']) ?>
                            </span>
                        </div>
                        
                        <div class="reporte-info">
                            <p><strong>Periodo:</strong> <?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?></p>
                            <p><strong>Horas Reportadas:</strong> <?= $reporte['horas_reportadas'] ?></p>
                            <p><strong>Fecha de Entrega:</strong> <?= formatDate($reporte['fecha_entrega']) ?></p>
                            
                            <?php if ($reporte['calificacion']): ?>
                                <p><strong>Calificación:</strong> <?= $reporte['calificacion'] ?></p>
                            <?php endif; ?>
                            
                            <?php if ($reporte['observaciones_evaluador']): ?>
                                <p><strong>Observaciones:</strong> <?= htmlspecialchars($reporte['observaciones_evaluador']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reporte-actions">
                            <a href="/modules/estudiantes/reporte-detalle.php?id=<?= $reporte['id'] ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Ver Detalles
                            </a>
                            
                            <?php if ($reporte['archivo_path']): ?>
                                <a href="<?= UPLOAD_URL . $reporte['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-success">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($reporte['estado'] === 'pendiente_evaluacion'): ?>
                                <span class="text-muted">En espera de evaluación</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No has entregado ningún reporte aún</p>
                    <p>Los reportes se habilitan una vez que tu solicitud sea aprobada y esté en proceso</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.reportes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.reporte-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
}

.reporte-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.reporte-header h3 {
    margin: 0;
}

.reporte-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.reporte-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.action-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-content h3 {
    margin: 0 0 0.5rem 0;
    color: var(--secondary-color);
}

.action-content p {
    margin: 0;
    color: #666;
}
</style>

<?php include '../../includes/footer.php'; ?>