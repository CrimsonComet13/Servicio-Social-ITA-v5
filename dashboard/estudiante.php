<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

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

// Obtener solicitud activa
$solicitudActiva = $db->fetch("
    SELECT s.*, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio,
           jd.nombre as jefe_depto_nombre
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE s.estudiante_id = :estudiante_id 
    AND s.estado IN ('pendiente', 'aprobada', 'en_proceso')
    ORDER BY s.fecha_solicitud DESC
    LIMIT 1
", ['estudiante_id' => $estudiante['id']]);

// Obtener reportes pendientes
$reportesPendientes = [];
if ($solicitudActiva && $solicitudActiva['estado'] === 'en_proceso') {
    $reportesPendientes = $db->fetchAll("
        SELECT r.* 
        FROM reportes_bimestrales r
        WHERE r.solicitud_id = :solicitud_id
        AND r.estado = 'pendiente_evaluacion'
        ORDER BY r.numero_reporte
    ", ['solicitud_id' => $solicitudActiva['id']]);
}

// Obtener documentos recientes
$documentos = [];

// Oficios
$oficios = $db->fetchAll("
    SELECT 'oficio' as tipo, numero_oficio as numero, fecha_emision as fecha, archivo_path
    FROM oficios_presentacion op
    JOIN solicitudes_servicio s ON op.solicitud_id = s.id
    WHERE s.estudiante_id = :estudiante_id
    ORDER BY fecha_emision DESC
    LIMIT 3
", ['estudiante_id' => $estudiante['id']]);

// Constancias
$constancias = $db->fetchAll("
    SELECT 'constancia' as tipo, numero_constancia as numero, fecha_emision as fecha, archivo_path
    FROM constancias
    WHERE estudiante_id = :estudiante_id
    ORDER BY fecha_emision DESC
    LIMIT 3
", ['estudiante_id' => $estudiante['id']]);

$documentos = array_merge($oficios, $constancias);

$pageTitle = "Dashboard Estudiante - " . APP_NAME;
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Bienvenido, <?= htmlspecialchars($estudiante['nombre']) ?></h1>
        <p>Panel de control de servicio social</p>
    </div>

    <!-- Estado actual del servicio social -->
    <div class="status-cards">
        <div class="card">
            <div class="card-header">
                <h3>Estado del Servicio Social</h3>
            </div>
            <div class="card-body">
                <div class="status-info">
                    <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                        <?= getEstadoText($estudiante['estado_servicio']) ?>
                    </span>
                </div>
                
                <?php if ($solicitudActiva): ?>
                    <div class="status-details">
                        <p><strong>Proyecto:</strong> <?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?></p>
                        <?php if ($solicitudActiva['laboratorio']): ?>
                            <p><strong>Laboratorio:</strong> <?= htmlspecialchars($solicitudActiva['laboratorio']) ?></p>
                        <?php endif; ?>
                        <p><strong>Estado:</strong> <?= getEstadoText($solicitudActiva['estado']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Horas completadas -->
        <div class="card">
            <div class="card-header">
                <h3>Progreso de Horas</h3>
            </div>
            <div class="card-body">
                <div class="progress-container">
                    <?php 
                    $horasRequeridas = 500;
                    $horasCompletadas = $estudiante['horas_completadas'] ?? 0;
                    $progreso = min(100, ($horasCompletadas / $horasRequeridas) * 100);
                    ?>
                    <div class="progress-info">
                        <span><?= $horasCompletadas ?> / <?= $horasRequeridas ?> horas</span>
                        <span><?= number_format($progreso, 1) ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progreso ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reportes -->
        <div class="card">
            <div class="card-header">
                <h3>Reportes Bimestrales</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= count($reportesPendientes) ?></div>
                <p>Reportes pendientes</p>
                <?php if ($solicitudActiva && $solicitudActiva['estado'] === 'en_proceso'): ?>
                    <a href="modules/estudiantes/reportes.php" class="btn btn-sm btn-primary">
                        Ver Reportes
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="quick-actions">
        <h2>Acciones Rápidas</h2>
        
        <?php if (!$solicitudActiva): ?>
            <!-- Si no tiene solicitud -->
            <div class="action-card highlight">
                <div class="action-content">
                    <h3>Comenzar Servicio Social</h3>
                    <p>Solicita tu servicio social eligiendo un proyecto disponible en los laboratorios del ITA.</p>
                </div>
                <div class="action-buttons">
                    <a href="modules/estudiantes/solicitud.php" class="btn btn-primary btn-large">
                        <i class="fas fa-paper-plane"></i> Crear Solicitud
                    </a>
                </div>
            </div>
        <?php elseif ($solicitudActiva['estado'] === 'pendiente'): ?>
            <!-- Solicitud pendiente -->
            <div class="action-card">
                <div class="action-content">
                    <h3>Solicitud en Revisión</h3>
                    <p>Tu solicitud está siendo revisada por el jefe de departamento. Te notificaremos cuando sea aprobada.</p>
                </div>
                <div class="action-buttons">
                    <a href="modules/estudiantes/solicitud-detalle.php?id=<?= $solicitudActiva['id'] ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> Ver Solicitud
                    </a>
                </div>
            </div>
        <?php elseif ($solicitudActiva['estado'] === 'aprobada'): ?>
            <!-- Solicitud aprobada -->
            <div class="action-card highlight">
                <div class="action-content">
                    <h3>¡Solicitud Aprobada!</h3>
                    <p>Tu solicitud ha sido aprobada. Puedes descargar tu oficio de presentación y comenzar tu servicio social.</p>
                </div>
                <div class="action-buttons">
                    <a href="modules/estudiantes/documentos.php" class="btn btn-success">
                        <i class="fas fa-download"></i> Descargar Oficio
                    </a>
                </div>
            </div>
        <?php elseif ($solicitudActiva['estado'] === 'en_proceso'): ?>
            <!-- En proceso -->
            <?php if (!empty($reportesPendientes)): ?>
                <div class="action-card highlight">
                    <div class="action-content">
                        <h3>Reportes Pendientes</h3>
                        <p>Tienes reportes bimestrales pendientes de entrega. Mantén tu servicio social al día.</p>
                    </div>
                    <div class="action-buttons">
                        <a href="modules/estudiantes/reportes.php" class="btn btn-warning">
                            <i class="fas fa-exclamation-triangle"></i> Entregar Reportes
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="action-card">
                    <div class="action-content">
                        <h3>Servicio Social en Proceso</h3>
                        <p>Continúa con tu servicio social. Recuerda entregar tus reportes bimestrales a tiempo.</p>
                    </div>
                    <div class="action-buttons">
                        <a href="modules/estudiantes/reportes.php" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> Gestionar Reportes
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Documentos recientes -->
    <?php if ($documentos): ?>
    <div class="recent-section">
        <div class="section-header">
            <h2>Documentos Recientes</h2>
            <a href="modules/estudiantes/documentos.php" class="btn btn-secondary">Ver Todos</a>
        </div>
        
        <div class="documents-grid">
            <?php foreach ($documentos as $doc): ?>
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="document-info">
                    <h4><?= ucfirst($doc['tipo']) ?></h4>
                    <p><?= htmlspecialchars($doc['numero']) ?></p>
                    <small><?= formatDate($doc['fecha']) ?></small>
                </div>
                <div class="document-actions">
                    <?php if ($doc['archivo_path']): ?>
                        <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-success">
                            <i class="fas fa-download"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Links útiles -->
    <div class="useful-links">
        <h2>Enlaces Útiles</h2>
        <div class="links-grid">
            <a href="modules/estudiantes/perfil.php" class="link-card">
                <div class="link-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h4>Mi Perfil</h4>
                <p>Actualiza tu información personal</p>
            </a>
            
            <a href="modules/estudiantes/documentos.php" class="link-card">
                <div class="link-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <h4>Mis Documentos</h4>
                <p>Descarga oficios y constancias</p>
            </a>
            
            <a href="contacto.php" class="link-card">
                <div class="link-icon">
                    <i class="fas fa-help"></i>
                </div>
                <h4>Ayuda</h4>
                <p>Obtén soporte técnico</p>
            </a>
        </div>
    </div>
</div>

<style>
.dashboard-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: calc(100vh - 80px);
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.quick-actions {
    margin-bottom: 2rem;
}

.action-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-card.highlight {
    border-left: 4px solid var(--primary-color);
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
}

.action-content h3 {
    margin: 0 0 0.5rem 0;
    color: var(--secondary-color);
}

.action-content p {
    margin: 0;
    color: #666;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.document-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.document-icon {
    font-size: 1.5rem;
    color: #e74c3c;
}

.document-info {
    flex: 1;
}

.document-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
}

.document-info p {
    margin: 0 0 0.25rem 0;
    font-size: 0.8rem;
    color: #666;
}

.document-info small {
    font-size: 0.75rem;
    color: #999;
}

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.link-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s ease;
    text-align: center;
}

.link-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.link-icon {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.link-card h4 {
    margin: 0 0 0.5rem 0;
    color: var(--secondary-color);
}

.link-card p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

.status-info {
    margin-bottom: 1rem;
}

.status-details p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.recent-section {
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-header h2 {
    margin: 0;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .dashboard-content {
        margin-left: 0;
        padding: 1rem;
    }
    
    .status-cards {
        grid-template-columns: 1fr;
    }
    
    .action-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .documents-grid {
        grid-template-columns: 1fr;
    }
    
    .links-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<?php include '../includes/footer.php'; ?>