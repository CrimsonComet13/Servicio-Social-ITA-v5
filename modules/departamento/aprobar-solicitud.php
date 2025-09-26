<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Validar ID de la solicitud
$solicitudId = $_GET['id'] ?? null;
if (!$solicitudId || !is_numeric($solicitudId)) {
    flashMessage('Solicitud no válida', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Obtener datos de la solicitud
$solicitud = $db->fetch("
    SELECT s.*, e.nombre as estudiante_nombre, e.apellido_paterno, 
           p.nombre_proyecto, p.cupo_disponible, p.cupo_ocupado
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.id = :solicitud_id AND s.jefe_departamento_id = :jefe_id AND s.estado = 'pendiente'
", ['solicitud_id' => $solicitudId, 'jefe_id' => $jefeId]);

if (!$solicitud) {
    flashMessage('Solicitud no encontrada o no está pendiente de aprobación', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Verificar que haya cupo disponible
if ($solicitud['cupo_ocupado'] >= $solicitud['cupo_disponible']) {
    flashMessage('No hay cupo disponible en este proyecto', 'error');
    redirectTo('/modules/departamento/solicitud-detalle.php?id=' . $solicitudId);
}

// Procesar aprobación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    try {
        $db->beginTransaction();
        
        // Aprobar solicitud
        $updateResult = $db->update('solicitudes_servicio', [
            'estado' => 'aprobada',
            'observaciones_jefe' => $observaciones,
            'aprobada_por' => $usuario['id'],
            'fecha_aprobacion' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $solicitudId]);
        
        if (!$updateResult) {
            throw new Exception('Error al actualizar la solicitud');
        }
        
        // Actualizar estado del estudiante
        $db->update('estudiantes', [
            'estado_servicio' => 'aprobado'
        ], 'id = :id', ['id' => $solicitud['estudiante_id']]);
        
        // Incrementar cupo ocupado del proyecto
        $db->query("
            UPDATE proyectos_laboratorio 
            SET cupo_ocupado = cupo_ocupado + 1 
            WHERE id = :proyecto_id
        ", ['proyecto_id' => $solicitud['proyecto_id']]);
        
        // Generar número de oficio
        $numeroOficio = generateNumeroOficio();
        
        // Crear oficio de presentación
        $db->insert('oficios_presentacion', [
            'solicitud_id' => $solicitudId,
            'numero_oficio' => $numeroOficio,
            'fecha_emision' => date('Y-m-d'),
            'generado_por' => $usuario['id'],
            'estado' => 'generado'
        ]);
        
        // Registrar en historial
        insertHistorialEstado($solicitudId, 'pendiente', 'aprobada', $usuario['id'], 
                            'Solicitud aprobada por jefe de departamento' . ($observaciones ? '. Observaciones: ' . $observaciones : ''));
        
        // Notificar al estudiante
        createNotification(
            $solicitud['estudiante_id'],
            'Solicitud Aprobada',
            'Tu solicitud de servicio social ha sido aprobada. Ya puedes descargar tu oficio de presentación.',
            'success',
            '/modules/estudiantes/documentos.php'
        );
        
        // Notificar al jefe de laboratorio si existe
        if ($solicitud['jefe_laboratorio_id']) {
            createNotification(
                $solicitud['jefe_laboratorio_id'],
                'Nuevo Estudiante Asignado',
                "El estudiante {$solicitud['estudiante_nombre']} {$solicitud['apellido_paterno']} iniciará servicio social en tu laboratorio.",
                'info',
                "/modules/laboratorio/estudiante-detalle.php?id={$solicitud['estudiante_id']}"
            );
        }
        
        // Log de actividad
        logActivity($usuario['id'], 'aprobar_solicitud', 'solicitudes', $solicitudId, [
            'estudiante' => $solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno'],
            'proyecto' => $solicitud['nombre_proyecto']
        ]);
        
        $db->commit();
        
        flashMessage('Solicitud aprobada exitosamente', 'success');
        redirectTo('/modules/departamento/solicitud-detalle.php?id=' . $solicitudId);
        
    } catch (Exception $e) {
        $db->rollback();
        flashMessage('Error al aprobar la solicitud: ' . $e->getMessage(), 'error');
        redirectTo('/modules/departamento/solicitud-detalle.php?id=' . $solicitudId);
    }
}

// Funciones helper
function insertHistorialEstado($solicitudId, $estadoAnterior, $estadoNuevo, $usuarioId, $observaciones = null) {
    global $db;
    $db->insert('historial_estados', [
        'solicitud_id' => $solicitudId,
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo' => $estadoNuevo,
        'usuario_id' => $usuarioId,
        'observaciones' => $observaciones,
        'fecha_cambio' => date('Y-m-d H:i:s')
    ]);
}

$pageTitle = "Aprobar Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-check-circle"></i>
                        Aprobar Solicitud #<?= $solicitudId ?>
                    </h1>
                    <p class="page-subtitle">Confirmar aprobación de solicitud de servicio social</p>
                </div>
                <div class="header-actions">
                    <a href="/modules/departamento/solicitudes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver a Solicitudes
                    </a>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Información de la Solicitud -->
            <div class="info-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Resumen de la Solicitud
                    </h3>
                </div>
                <div class="card-content">
                    <div class="summary-details">
                        <div class="detail-item">
                            <span class="detail-label">Estudiante:</span>
                            <span class="detail-value"><?= htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Proyecto:</span>
                            <span class="detail-value"><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cupo del Proyecto:</span>
                            <span class="detail-value"><?= $solicitud['cupo_ocupado'] + 1 ?>/<?= $solicitud['cupo_disponible'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Fecha de Solicitud:</span>
                            <span class="detail-value"><?= formatDate($solicitud['fecha_solicitud']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Aprobación -->
            <div class="info-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-clipboard-check"></i>
                        Confirmar Aprobación
                    </h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="observaciones">Observaciones (Opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="4" 
                                      placeholder="Agregue comentarios o observaciones para el estudiante..."
                                      class="form-control"></textarea>
                            <small class="form-text">Estas observaciones serán visibles para el estudiante.</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> Al aprobar esta solicitud:
                            <ul>
                                <li>El estudiante recibirá una notificación de aprobación</li>
                                <li>Se generará automáticamente el oficio de presentación</li>
                                <li>El cupo del proyecto se actualizará</li>
                                <li>El jefe de laboratorio será notificado (si está asignado)</li>
                            </ul>
                        </div>

                        <div class="form-actions">
                            <a href="/modules/departamento/solicitud-detalle.php?id=<?= $solicitudId ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Confirmar Aprobación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    padding: 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

.dashboard-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
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
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    font-size: 1.1rem;
    color: #6b7280;
    margin: 0;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.info-card {
    background: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #f3f4f6;
    background: #f9fafb;
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.card-content {
    padding: 1.5rem;
}

.summary-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 0.5rem;
}

.detail-label {
    font-weight: 600;
    color: #6b7280;
}

.detail-value {
    font-weight: 500;
    color: #374151;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-text {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #1e40af;
}

.alert ul {
    margin: 0.5rem 0 0 1rem;
    padding: 0;
}

.alert li {
    margin-bottom: 0.25rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>