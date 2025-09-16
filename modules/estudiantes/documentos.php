<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener documentos del estudiante
$documentos = [];

// Oficios de presentación
$oficios = $db->fetchAll("
    SELECT op.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta
    FROM oficios_presentacion op
    JOIN solicitudes_servicio s ON op.solicitud_id = s.id
    WHERE s.estudiante_id = :estudiante_id
    ORDER BY op.fecha_emision DESC
", ['estudiante_id' => $estudianteId]);

foreach ($oficios as $oficio) {
    $documentos[] = [
        'tipo' => 'Oficio de Presentación',
        'numero' => $oficio['numero_oficio'],
        'fecha' => $oficio['fecha_emision'],
        'archivo' => $oficio['archivo_path'],
        'estado' => $oficio['estado'],
        'icono' => 'file-contract'
    ];
}

// Cartas de terminación
$cartas = $db->fetchAll("
    SELECT ct.*
    FROM cartas_terminacion ct
    WHERE ct.estudiante_id = :estudiante_id
    ORDER BY ct.fecha_terminacion DESC
", ['estudiante_id' => $estudianteId]);

foreach ($cartas as $carta) {
    $documentos[] = [
        'tipo' => 'Carta de Terminación',
        'numero' => $carta['numero_carta'],
        'fecha' => $carta['fecha_terminacion'],
        'archivo' => $carta['archivo_path'],
        'estado' => 'generado',
        'icono' => 'file-signature'
    ];
}

// Constancias
$constancias = $db->fetchAll("
    SELECT c.*
    FROM constancias c
    WHERE c.estudiante_id = :estudiante_id
    ORDER BY c.fecha_emision DESC
", ['estudiante_id' => $estudianteId]);

foreach ($constancias as $constancia) {
    $documentos[] = [
        'tipo' => 'Constancia de Liberación',
        'numero' => $constancia['numero_constancia'],
        'fecha' => $constancia['fecha_emision'],
        'archivo' => $constancia['archivo_path'],
        'estado' => $constancia['enviado_servicios_escolares'] ? 'enviado' : 'generado',
        'icono' => 'file-certificate'
    ];
}

$pageTitle = "Mis Documentos - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Mis Documentos</h1>
        <p>Gestión de documentos generados durante tu servicio social</p>
    </div>

    <?php if ($documentos): ?>
        <div class="documents-grid">
            <?php foreach ($documentos as $documento): ?>
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-<?= $documento['icono'] ?>"></i>
                </div>
                
                <div class="document-info">
                    <h3><?= $documento['tipo'] ?></h3>
                    <p class="document-number"><?= $documento['numero'] ?></p>
                    <p class="document-date"><?= formatDate($documento['fecha']) ?></p>
                    <span class="badge <?= $documento['estado'] === 'generado' ? 'badge-success' : 'badge-info' ?>">
                        <?= $documento['estado'] === 'generado' ? 'Disponible' : ucfirst($documento['estado']) ?>
                    </span>
                </div>
                
                <div class="document-actions">
                    <?php if ($documento['archivo']): ?>
                        <a href="<?= UPLOAD_URL . $documento['archivo'] ?>" target="_blank" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        <a href="<?= UPLOAD_URL . $documento['archivo'] ?>" download class="btn btn-sm btn-success">
                            <i class="fas fa-download"></i> Descargar
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Documento no disponible</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="document-stats">
            <div class="stat-item">
                <h3>Total Documentos</h3>
                <div class="stat-number"><?= count($documentos) ?></div>
            </div>
            
            <div class="stat-item">
                <h3>Oficios de Presentación</h3>
                <div class="stat-number"><?= count($oficios) ?></div>
            </div>
            
            <div class="stat-item">
                <h3>Cartas de Terminación</h3>
                <div class="stat-number"><?= count($cartas) ?></div>
            </div>
            
            <div class="stat-item">
                <h3>Constancias</h3>
                <div class="stat-number"><?= count($constancias) ?></div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>No tienes documentos generados</p>
            <p>Los documentos se generarán automáticamente durante tu servicio social</p>
        </div>
    <?php endif; ?>
</div>

<style>
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.document-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
}

.document-icon {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
    text-align: center;
}

.document-info {
    flex: 1;
    margin-bottom: 1rem;
}

.document-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
}

.document-number {
    font-size: 0.9rem;
    color: #666;
    margin: 0.25rem 0;
}

.document-date {
    font-size: 0.9rem;
    color: #666;
    margin: 0.25rem 0;
}

.document-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.document-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-item {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1rem;
    text-align: center;
}

.stat-item h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}
</style>

<?php include '../../includes/footer.php'; ?>