<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeDepto = $db->fetch("SELECT id FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

// Validar ID de la solicitud
$solicitudId = $_GET['id'] ?? null;
if (!$solicitudId || !is_numeric($solicitudId)) {
    flashMessage('Solicitud no válida', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Obtener datos de la solicitud
$solicitud = $db->fetch("
    SELECT s.*, e.nombre as estudiante_nombre, e.apellido_paterno, 
           p.nombre_proyecto, e.usuario_id as estudiante_usuario_id
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.id = :solicitud_id AND s.jefe_departamento_id = :jefe_id AND s.estado = 'pendiente'
", ['solicitud_id' => $solicitudId, 'jefe_id' => $jefeId]);

if (!$solicitud) {
    flashMessage('Solicitud no encontrada o no está pendiente', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Procesar rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    if (empty($motivo_rechazo)) {
        flashMessage('El motivo de rechazo es obligatorio', 'error');
    } else {
        try {
            $db->beginTransaction();
            
            // Rechazar solicitud
            $updateResult = $db->update('solicitudes_servicio', [
                'estado' => 'rechazada',
                'motivo_rechazo' => $motivo_rechazo,
                'observaciones_jefe' => $observaciones
            ], 'id = :id', ['id' => $solicitudId]);
            
            if (!$updateResult) {
                throw new Exception('Error al actualizar la solicitud');
            }
            
            // Actualizar estado del estudiante
            $db->update('estudiantes', [
                'estado_servicio' => 'sin_solicitud'
            ], 'id = :id', ['id' => $solicitud['estudiante_id']]);
            
            // Registrar en historial
            $db->insert('historial_estados', [
                'solicitud_id' => $solicitudId,
                'estado_anterior' => 'pendiente',
                'estado_nuevo' => 'rechazada',
                'usuario_id' => $usuario['id'],
                'comentarios' => 'Solicitud rechazada: ' . $motivo_rechazo . ($observaciones ? '. Observaciones: ' . $observaciones : ''),
                'fecha_cambio' => date('Y-m-d H:i:s')
            ]);
            
            // Notificar al estudiante
            $db->insert('notificaciones', [
                'usuario_id' => $solicitud['estudiante_usuario_id'],
                'titulo' => 'Solicitud Rechazada',
                'mensaje' => 'Tu solicitud de servicio social ha sido rechazada. Revisa los comentarios y considera hacer una nueva solicitud.',
                'tipo' => 'error',
                'url_accion' => '/modules/estudiantes/solicitud-estado.php'
            ]);
            
            // Log de actividad
            logActivity($usuario['id'], 'rechazar_solicitud', 'solicitudes', $solicitudId, [
                'estudiante' => $solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno'],
                'proyecto' => $solicitud['nombre_proyecto'],
                'motivo' => $motivo_rechazo
            ]);
            
            $db->commit();
            
            flashMessage('Solicitud rechazada exitosamente', 'success');
            redirectTo('/modules/departamento/solicitudes.php');
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error al rechazar solicitud: " . $e->getMessage());
            flashMessage('Error al rechazar la solicitud: ' . $e->getMessage(), 'error');
            redirectTo('/modules/departamento/solicitudes.php');
        }
    }
}

$pageTitle = "Rechazar Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- El resto del HTML sigue igual -->

<div class="main-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-times-circle"></i>
                        Rechazar Solicitud #<?= $solicitudId ?>
                    </h1>
                    <p class="page-subtitle">Confirmar rechazo de solicitud de servicio social</p>
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
                            <span class="detail-label">Fecha de Solicitud:</span>
                            <span class="detail-value"><?= formatDate($solicitud['fecha_solicitud']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Rechazo -->
            <div class="info-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-exclamation-triangle"></i>
                        Confirmar Rechazo
                    </h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="motivo_rechazo" class="required">Motivo de Rechazo *</label>
                            <textarea id="motivo_rechazo" name="motivo_rechazo" rows="4" 
                                      placeholder="Explique claramente el motivo del rechazo..."
                                      class="form-control" required></textarea>
                            <small class="form-text">Este motivo será visible para el estudiante y es obligatorio.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="observaciones">Observaciones Adicionales (Opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="3" 
                                      placeholder="Comentarios adicionales o sugerencias..."
                                      class="form-control"></textarea>
                            <small class="form-text">Estas observaciones serán visibles para el estudiante.</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Advertencia:</strong> Al rechazar esta solicitud:
                            <ul>
                                <li>El estudiante recibirá una notificación de rechazo</li>
                                <li>El estado del estudiante volverá a "Sin solicitud"</li>
                                <li>Esta acción no se puede deshacer</li>
                                <li>El estudiante deberá crear una nueva solicitud si desea intentarlo nuevamente</li>
                            </ul>
                        </div>

                        <div class="form-actions">
                            <a href="/modules/departamento/solicitud-detalle.php?id=<?= $solicitudId ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas rechazar esta solicitud? Esta acción no se puede deshacer.')">
                                <i class="fas fa-times"></i> Confirmar Rechazo
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

.form-group label.required::after {
    content: ' *';
    color: #dc2626;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
    resize: vertical;
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

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #92400e;
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

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const motivoTextarea = document.getElementById('motivo_rechazo');
    
    form.addEventListener('submit', function(e) {
        if (!motivoTextarea.value.trim()) {
            e.preventDefault();
            alert('Por favor, ingresa el motivo del rechazo.');
            motivoTextarea.focus();
            return false;
        }
        
        // Validación adicional de longitud mínima
        if (motivoTextarea.value.trim().length < 10) {
            e.preventDefault();
            alert('El motivo del rechazo debe tener al menos 10 caracteres.');
            motivoTextarea.focus();
            return false;
        }
    });
    
    // Efecto de contador de caracteres
    const charCount = document.createElement('div');
    charCount.className = 'char-count';
    charCount.style.cssText = 'font-size: 0.8rem; color: #6b7280; text-align: right; margin-top: 0.25rem;';
    motivoTextarea.parentNode.appendChild(charCount);
    
    function updateCharCount() {
        const count = motivoTextarea.value.length;
        charCount.textContent = `${count} caracteres (mínimo 10)`;
        
        if (count < 10) {
            charCount.style.color = '#dc2626';
        } else if (count < 50) {
            charCount.style.color = '#d97706';
        } else {
            charCount.style.color = '#059669';
        }
    }
    
    motivoTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Inicializar
});
</script>

<?php include '../../includes/footer.php'; ?>