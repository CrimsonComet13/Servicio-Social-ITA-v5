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

// Inicializar variables
$error = null;
$success = null;

// Validar ID de la solicitud
$solicitudId = $_GET['id'] ?? null;
if (!$solicitudId || !is_numeric($solicitudId)) {
    flashMessage('Solicitud no válida', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Obtener datos completos de la solicitud
$solicitud = $db->fetch("
    SELECT s.*, 
           e.nombre as estudiante_nombre, e.apellido_paterno, e.apellido_materno, 
           e.numero_control, e.carrera, e.semestre, e.creditos_cursados, 
           e.telefono as estudiante_telefono, e.horas_completadas,
           u_est.email as estudiante_email,
           p.nombre_proyecto, p.descripcion as proyecto_descripcion, 
           p.objetivos, p.tipo_actividades, p.horas_requeridas, p.requisitos,
           jl.nombre as jefe_lab_nombre, jl.laboratorio, jl.telefono as lab_telefono, 
           jl.extension as lab_extension,
           u_lab.email as lab_email,
           jd.nombre as jefe_depto_nombre, jd.departamento
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN usuarios u_est ON e.usuario_id = u_est.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u_lab ON jl.usuario_id = u_lab.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE s.id = :solicitud_id AND s.jefe_departamento_id = :jefe_id
", ['solicitud_id' => $solicitudId, 'jefe_id' => $jefeId]);

if (!$solicitud) {
    flashMessage('Solicitud no encontrada', 'error');
    redirectTo('/modules/departamento/solicitudes.php');
}

// Obtener historial de estados
$historialEstados = $db->fetchAll("
    SELECT he.*, u.email as usuario_email, 
           CASE 
               WHEN u.tipo_usuario = 'estudiante' THEN e.nombre
               WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre  
               WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
               ELSE u.email
           END as usuario_nombre
    FROM historial_estados he
    LEFT JOIN usuarios u ON he.usuario_id = u.id
    LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
    LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id AND u.tipo_usuario = 'jefe_departamento'
    LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id AND u.tipo_usuario = 'jefe_laboratorio'
    WHERE he.solicitud_id = :solicitud_id
    ORDER BY he.fecha_cambio DESC
", ['solicitud_id' => $solicitudId]);

// Procesar acciones de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $observaciones_jefe = trim($_POST['observaciones'] ?? ''); // CORREGIDO: usar nombre consistente
    $motivo_rechazo = trim($_POST['motivo_rechazo'] ?? '');
    
    // Debug temporal - remover en producción
    error_log("Procesando solicitud: ID=$solicitudId, Accion=$accion, Estado actual=" . $solicitud['estado']);
    
    if (in_array($accion, ['aprobar', 'rechazar']) && $solicitud['estado'] === 'pendiente') {
        try {
            $db->beginTransaction();
            
            if ($accion === 'aprobar') {
                // Aprobar solicitud
                $updateResult = $db->update('solicitudes_servicio', [
                    'estado' => 'aprobada',
                    'observaciones_jefe' => $observaciones_jefe, // CORREGIDO: usar variable correcta
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
                
                // Crear oficio de presentación
                $numeroOficio = generateNumeroOficio();
                $db->insert('oficios_presentacion', [
                    'solicitud_id' => $solicitudId,
                    'numero_oficio' => $numeroOficio,
                    'fecha_emision' => date('Y-m-d'),
                    'generado_por' => $usuario['id'],
                    'estado' => 'generado'
                ]);
                
                // Registrar en historial
                insertHistorialEstado($solicitudId, 'pendiente', 'aprobada', $usuario['id'], 'Solicitud aprobada por jefe de departamento');
                
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
                
                $success = 'Solicitud aprobada exitosamente';
                
            } else { // rechazar
                if (empty($motivo_rechazo)) {
                    throw new Exception('El motivo de rechazo es obligatorio');
                }
                
                // Rechazar solicitud
                $updateResult = $db->update('solicitudes_servicio', [
                    'estado' => 'rechazada',
                    'motivo_rechazo' => $motivo_rechazo,
                    'observaciones_jefe' => $observaciones_jefe // CORREGIDO: usar variable correcta
                ], 'id = :id', ['id' => $solicitudId]);
                
                if (!$updateResult) {
                    throw new Exception('Error al actualizar la solicitud');
                }
                
                // Actualizar estado del estudiante
                $db->update('estudiantes', [
                    'estado_servicio' => 'sin_solicitud'
                ], 'id = :id', ['id' => $solicitud['estudiante_id']]);
                
                // Registrar en historial
                insertHistorialEstado($solicitudId, 'pendiente', 'rechazada', $usuario['id'], 'Solicitud rechazada: ' . $motivo_rechazo);
                
                // Notificar al estudiante
                createNotification(
                    $solicitud['estudiante_id'],
                    'Solicitud Rechazada',
                    'Tu solicitud de servicio social ha sido rechazada. Revisa los comentarios y considera hacer una nueva solicitud.',
                    'error',
                    '/modules/estudiantes/solicitud-estado.php'
                );
                
                $success = 'Solicitud rechazada exitosamente';
            }
            
            // Log de actividad
            logActivity($usuario['id'], $accion . '_solicitud', 'solicitudes', $solicitudId, [
                'estudiante' => $solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno'],
                'proyecto' => $solicitud['nombre_proyecto']
            ]);
            
            $db->commit();
            
            // Recargar datos de la solicitud después de la actualización
            $solicitud = $db->fetch("
                SELECT s.*, 
                       e.nombre as estudiante_nombre, e.apellido_paterno, e.apellido_materno, 
                       e.numero_control, e.carrera, e.semestre, e.creditos_cursados, 
                       e.telefono as estudiante_telefono, e.horas_completadas,
                       u_est.email as estudiante_email,
                       p.nombre_proyecto, p.descripcion as proyecto_descripcion, 
                       p.objetivos, p.tipo_actividades, p.horas_requeridas, p.requisitos,
                       jl.nombre as jefe_lab_nombre, jl.laboratorio, jl.telefono as lab_telefono, 
                       jl.extension as lab_extension,
                       u_lab.email as lab_email,
                       jd.nombre as jefe_depto_nombre, jd.departamento
                FROM solicitudes_servicio s
                JOIN estudiantes e ON s.estudiante_id = e.id
                JOIN usuarios u_est ON e.usuario_id = u_est.id
                JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
                LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
                LEFT JOIN usuarios u_lab ON jl.usuario_id = u_lab.id
                JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
                WHERE s.id = :solicitud_id AND s.jefe_departamento_id = :jefe_id
            ", ['solicitud_id' => $solicitudId, 'jefe_id' => $jefeId]);
            
            // Recargar historial
            $historialEstados = $db->fetchAll("
                SELECT he.*, u.email as usuario_email, 
                       CASE 
                           WHEN u.tipo_usuario = 'estudiante' THEN e.nombre
                           WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre  
                           WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
                           ELSE u.email
                       END as usuario_nombre
                FROM historial_estados he
                LEFT JOIN usuarios u ON he.usuario_id = u.id
                LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
                LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id AND u.tipo_usuario = 'jefe_departamento'
                LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id AND u.tipo_usuario = 'jefe_laboratorio'
                WHERE he.solicitud_id = :solicitud_id
                ORDER BY he.fecha_cambio DESC
            ", ['solicitud_id' => $solicitudId]);
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error al procesar la solicitud: ' . $e->getMessage();
            error_log("Error procesando solicitud $solicitudId: " . $e->getMessage());
        }
    } else {
        $error = 'Acción no válida o solicitud no se encuentra en estado pendiente';
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

// Helper functions para el estado
function getEstadoCssClass($estado) {
    switch($estado) {
        case 'pendiente': return 'pending';
        case 'aprobada': return 'approved';
        case 'rechazada': return 'rejected';
        case 'en_proceso': return 'in-progress';
        case 'concluida': return 'completed';
        default: return 'pending';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'pendiente': return 'clock';
        case 'aprobada': return 'check-circle';
        case 'rechazada': return 'times-circle';
        case 'en_proceso': return 'cogs';
        case 'concluida': return 'trophy';
        default: return 'circle';
    }
}

function getEstadoTitle($estado) {
    switch($estado) {
        case 'pendiente': return 'Solicitud Pendiente';
        case 'aprobada': return 'Solicitud Aprobada';
        case 'rechazada': return 'Solicitud Rechazada';
        case 'en_proceso': return 'Servicio en Proceso';
        case 'concluida': return 'Servicio Concluido';
        default: return 'Estado Desconocido';
    }
}

// Función helper para formatear el estado del texto


$pageTitle = "Detalle de Solicitud - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">
                    <i class="fas fa-file-alt"></i>
                    Detalle de Solicitud #<?= $solicitudId ?>
                </h1>
                <p class="page-subtitle">Información completa y gestión de la solicitud de servicio social</p>
            </div>
            <div class="header-actions">
                <a href="/modules/departamento/solicitudes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Solicitudes
                </a>
                <?php if ($solicitud['estado'] === 'aprobada'): ?>
                <a href="/modules/departamento/generar-oficio.php?solicitud_id=<?= $solicitudId ?>" class="btn btn-info">
                    <i class="fas fa-file-pdf"></i>
                    Ver Oficio
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="status-overview">
        <div class="main-status-card <?= getEstadoCssClass($solicitud['estado']) ?>">
            <div class="status-card-header">
                <div class="status-icon">
                    <i class="fas fa-<?= getEstadoIcon($solicitud['estado']) ?>"></i>
                </div>
                <div class="status-info">
                    <h2 class="status-title"><?= getEstadoTitle($solicitud['estado']) ?></h2>
                    <div class="status-badge">
                        <i class="fas fa-circle"></i>
                        <span><?= getEstadoText($solicitud['estado']) ?></span>
                    </div>
                    <p class="status-description">
                        Solicitud recibida el <?= formatDate($solicitud['fecha_solicitud']) ?>
                    </p>
                </div>
            </div>
            
            <div class="status-meta">
                <div class="meta-item">
                    <span class="meta-label">Periodo Propuesto:</span>
                    <span class="meta-value">
                        <?= formatDate($solicitud['fecha_inicio_propuesta']) ?> - 
                        <?= formatDate($solicitud['fecha_fin_propuesta']) ?>
                    </span>
                </div>
                <?php if ($solicitud['fecha_aprobacion']): ?>
                <div class="meta-item">
                    <span class="meta-label">Fecha de Aprobación:</span>
                    <span class="meta-value"><?= formatDateTime($solicitud['fecha_aprobacion']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="quick-stats">
            <div class="stat-item">
                <div class="stat-icon horas">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= $solicitud['horas_requeridas'] ?></span>
                    <span class="stat-label">Horas Requeridas</span>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon progreso">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-number"><?= $solicitud['horas_completadas'] ?></span>
                    <span class="stat-label">Horas Completadas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Student Information -->
        <div class="info-card student-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-user-graduate"></i>
                    Información del Estudiante
                </h3>
                <div class="student-avatar">
                    <?= strtoupper(substr($solicitud['estudiante_nombre'], 0, 1) . substr($solicitud['apellido_paterno'], 0, 1)) ?>
                </div>
            </div>
            <div class="card-content">
                <div class="student-profile">
                    <div class="profile-header">
                        <h4><?= htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?></h4>
                        <span class="student-id"><?= htmlspecialchars($solicitud['numero_control']) ?></span>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-graduation-cap"></i>
                                <span class="detail-label">Carrera:</span>
                                <span class="detail-value"><?= htmlspecialchars($solicitud['carrera']) ?></span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-layer-group"></i>
                                <span class="detail-label">Semestre:</span>
                                <span class="detail-value"><?= htmlspecialchars($solicitud['semestre']) ?>º</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-award"></i>
                                <span class="detail-label">Créditos:</span>
                                <span class="detail-value"><?= htmlspecialchars($solicitud['creditos_cursados']) ?></span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-envelope"></i>
                                <span class="detail-label">Email:</span>
                                <span class="detail-value">
                                    <a href="mailto:<?= htmlspecialchars($solicitud['estudiante_email']) ?>">
                                        <?= htmlspecialchars($solicitud['estudiante_email']) ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($solicitud['estudiante_telefono']): ?>
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span class="detail-label">Teléfono:</span>
                                <span class="detail-value"><?= htmlspecialchars($solicitud['estudiante_telefono']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Information -->
        <div class="info-card project-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-project-diagram"></i>
                    Proyecto Asignado
                </h3>
                <?php if ($solicitud['laboratorio']): ?>
                <span class="lab-badge"><?= htmlspecialchars($solicitud['laboratorio']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-content">
                <div class="project-info">
                    <h4 class="project-title"><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></h4>
                    
                    <?php if ($solicitud['proyecto_descripcion']): ?>
                    <div class="project-section">
                        <h5>Descripción</h5>
                        <p><?= nl2br(htmlspecialchars($solicitud['proyecto_descripcion'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['objetivos']): ?>
                    <div class="project-section">
                        <h5>Objetivos</h5>
                        <p><?= nl2br(htmlspecialchars($solicitud['objetivos'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['tipo_actividades']): ?>
                    <div class="project-section">
                        <h5>Tipo de Actividades</h5>
                        <p><?= nl2br(htmlspecialchars($solicitud['tipo_actividades'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['jefe_lab_nombre']): ?>
                    <div class="supervisor-info">
                        <h5>Supervisor Asignado</h5>
                        <div class="supervisor-card">
                            <div class="supervisor-details">
                                <h6><?= htmlspecialchars($solicitud['jefe_lab_nombre']) ?></h6>
                                <p><?= htmlspecialchars($solicitud['laboratorio']) ?></p>
                                <?php if ($solicitud['lab_email']): ?>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($solicitud['lab_email']) ?></p>
                                <?php endif; ?>
                                <?php if ($solicitud['lab_telefono']): ?>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($solicitud['lab_telefono']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Request Details -->
        <div class="info-card request-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-clipboard-list"></i>
                    Detalles de la Solicitud
                </h3>
            </div>
            <div class="card-content">
                <div class="request-details">
                    <div class="detail-section">
                        <h5>Motivo de la Solicitud</h5>
                        <div class="motivo-box">
                            <?= nl2br(htmlspecialchars($solicitud['motivo_solicitud'])) ?>
                        </div>
                    </div>
                    
                    <?php if ($solicitud['observaciones_estudiante']): ?>
                    <div class="detail-section">
                        <h5>Observaciones del Estudiante</h5>
                        <div class="observaciones-box">
                            <?= nl2br(htmlspecialchars($solicitud['observaciones_estudiante'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['observaciones_jefe']): ?>
                    <div class="detail-section">
                        <h5>Observaciones del Jefe de Departamento</h5>
                        <div class="observaciones-box admin">
                            <?= nl2br(htmlspecialchars($solicitud['observaciones_jefe'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($solicitud['motivo_rechazo']): ?>
                    <div class="detail-section">
                        <h5>Motivo de Rechazo</h5>
                        <div class="motivo-rechazo-box">
                            <?= nl2br(htmlspecialchars($solicitud['motivo_rechazo'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Panel -->
        <?php if ($solicitud['estado'] === 'pendiente'): ?>
        <div class="action-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-tasks"></i>
                    Acciones Disponibles
                </h3>
            </div>
            <div class="card-content">
                <div class="action-buttons">
                    <button type="button" class="btn btn-success btn-lg" onclick="showApprovalModal()">
                        <i class="fas fa-check"></i>
                        Aprobar Solicitud
                    </button>
                    <button type="button" class="btn btn-danger btn-lg" onclick="showRejectionModal()">
                        <i class="fas fa-times"></i>
                        Rechazar Solicitud
                    </button>
                </div>
                
                <div class="action-note">
                    <i class="fas fa-info-circle"></i>
                    <p>Revisa cuidadosamente toda la información antes de tomar una decisión. Esta acción no se puede deshacer.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- History Section -->
    <?php if ($historialEstados): ?>
    <div class="history-section">
        <div class="section-header">
            <h2>
                <i class="fas fa-history"></i>
                Historial de Estados
            </h2>
        </div>
        <div class="timeline">
            <?php foreach ($historialEstados as $index => $historial): ?>
            <div class="timeline-item <?= $index === 0 ? 'current' : 'completed' ?>">
                <div class="timeline-marker">
                    <i class="fas fa-<?= getEstadoIcon($historial['estado_nuevo']) ?>"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-status"><?= getEstadoText($historial['estado_nuevo']) ?></span>
                        <span class="timeline-date"><?= formatDateTime($historial['fecha_cambio']) ?></span>
                    </div>
                    <div class="timeline-body">
                        <p><strong><?= htmlspecialchars($historial['usuario_nombre'] ?? 'Sistema') ?></strong></p>
                        <?php if (isset($historial['observaciones']) && $historial['observaciones']): ?>
                        <p><?= htmlspecialchars($historial['observaciones']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<!-- Approval Modal -->
<div class="modal" id="approvalModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-check-circle"></i>
                Aprobar Solicitud
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('approvalModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="confirmation-text">
                    <p>¿Estás seguro de que deseas aprobar esta solicitud de servicio social?</p>
                    <div class="confirmation-details">
                        <strong>Estudiante:</strong> <?= htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['apellido_paterno']) ?><br>
                        <strong>Proyecto:</strong> <?= htmlspecialchars($solicitud['nombre_proyecto']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observaciones_aprobacion">Observaciones (Opcional)</label>
                    <textarea id="observaciones_aprobacion" name="observaciones" rows="4" 
                              placeholder="Agregue comentarios adicionales para el estudiante..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="accion" value="aprobar">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approvalModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirmar Aprobación
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal" id="rejectionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-times-circle"></i>
                Rechazar Solicitud
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="warning-text">
                    <p>¿Estás seguro de que deseas rechazar esta solicitud?</p>
                    <div class="warning-note">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Esta acción no se puede deshacer y el estudiante deberá crear una nueva solicitud.</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motivo_rechazo" class="required">Motivo de Rechazo *</label>
                    <textarea id="motivo_rechazo" name="motivo_rechazo" rows="4" 
                              placeholder="Explique claramente el motivo del rechazo..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="observaciones_rechazo">Observaciones Adicionales</label>
                    <textarea id="observaciones_rechazo" name="observaciones" rows="3" 
                              placeholder="Comentarios adicionales o sugerencias..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="accion" value="rechazar">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectionModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Confirmar Rechazo
                </button>
            </div>
        </form>
    </div>
</div>

<style>
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
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
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

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: var(--success);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: var(--error);
}

/* Dashboard Container */
.dashboard-container {
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
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
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.page-title i {
    color: var(--primary);
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-shrink: 0;
}

/* Status Overview */
.status-overview {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

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

.main-status-card.rejected {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(248, 113, 113, 0.05));
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.main-status-card.in-progress {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(96, 165, 250, 0.05));
    border: 1px solid rgba(59, 130, 246, 0.2);
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

.main-status-card.rejected .status-icon {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.main-status-card.in-progress .status-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
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

.main-status-card.rejected .status-badge {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
}

.main-status-card.in-progress .status-badge {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.status-description {
    color: var(--text-secondary);
    margin: 0;
}

.status-meta {
    padding: 0 2rem 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.meta-label {
    color: var(--text-secondary);
}

.meta-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Quick Stats */
.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.stat-item {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.6s ease-out 0.2s both;
}

.stat-icon {
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

.stat-icon.horas {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-icon.progreso {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-content {
    flex: 1;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
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

/* Student Card */
.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
}

.student-profile {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.profile-header h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.student-id {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--bg-light);
    border-radius: 2rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.profile-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-row {
    display: flex;
    gap: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    flex: 1;
}

.detail-item i {
    width: 16px;
    color: var(--text-light);
}

.detail-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.detail-value {
    color: var(--text-primary);
}

.detail-value a {
    color: var(--primary);
    text-decoration: none;
}

.detail-value a:hover {
    text-decoration: underline;
}

/* Project Card */
.lab-badge {
    background: var(--info);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.project-info {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.project-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
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

.supervisor-info h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.supervisor-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
}

.supervisor-details h6 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.supervisor-details p {
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    font-size: 0.85rem;
}

.supervisor-details p i {
    width: 16px;
    color: var(--text-light);
    margin-right: 0.25rem;
}

/* Request Card */
.request-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-section h5 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.motivo-box,
.observaciones-box,
.motivo-rechazo-box {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.observaciones-box.admin {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: var(--primary);
}

.motivo-rechazo-box {
    background: rgba(239, 68, 68, 0.05);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: var(--error);
}

/* Action Card */
.action-card {
    grid-column: span 2;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    animation: slideIn 0.6s ease-out 0.6s both;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.action-note {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid var(--info);
}

.action-note i {
    color: var(--info);
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.action-note p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

/* History Section */
.history-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 2rem;
    animation: slideIn 0.6s ease-out 0.8s both;
}

.section-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.section-header h2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    position: relative;
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

.timeline-content {
    flex: 1;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.timeline-status {
    font-weight: 600;
    color: var(--text-primary);
}

.timeline-date {
    font-size: 0.85rem;
    color: var(--text-light);
}

.timeline-body p {
    margin: 0 0 0.25rem 0;
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

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover:not(:disabled) {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-danger {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-danger:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-info:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    animation: fadeIn 0.3s ease-out;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease-out;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.modal-close:hover {
    color: var(--text-primary);
    background: var(--bg-light);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border-light);
}

.confirmation-text p {
    font-size: 1rem;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.confirmation-details {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    line-height: 1.5;
}

.warning-text p {
    font-size: 1rem;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.warning-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(239, 68, 68, 0.05);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--radius);
    color: var(--error);
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.form-group label.required::after {
    content: ' *';
    color: var(--error);
}

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    resize: vertical;
    transition: var(--transition);
    font-family: inherit;
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
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
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .action-card {
        grid-column: span 1;
    }
}

@media (max-width: 1024px) {
    .status-overview {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .status-card-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .card-content,
    .modal-body,
    .modal-footer {
        padding: 1rem;
    }
    
    .status-card-header {
        padding: 1.5rem 1rem;
    }
    
    .status-meta {
        padding: 0 1rem 1.5rem;
    }
    
    .modal-content {
        width: 95%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to cards
    const cards = document.querySelectorAll('.info-card, .stat-item');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = 'var(--shadow-lg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
});

function showApprovalModal() {
    const modal = document.getElementById('approvalModal');
    modal.classList.add('active');
    modal.style.display = 'flex';
    
    // Focus on textarea después de que el modal sea visible
    setTimeout(() => {
        const textarea = modal.querySelector('#observaciones_aprobacion');
        if (textarea) textarea.focus();
    }, 150);
}

function showRejectionModal() {
    const modal = document.getElementById('rejectionModal');
    modal.classList.add('active');
    modal.style.display = 'flex';
    
    // Focus on required textarea
    setTimeout(() => {
        const textarea = modal.querySelector('#motivo_rechazo');
        if (textarea) textarea.focus();
    }, 150);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    
    // Animate out
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
        modal.style.opacity = '';
        
        // Reset form
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            // Restaurar estilos de validación
            const fields = form.querySelectorAll('input, textarea, select');
            fields.forEach(field => {
                field.style.borderColor = '';
            });
        }
    }, 300);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--${type === 'success' ? 'success' : type === 'error' ? 'error' : 'info'});
        color: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        z-index: 1001;
        animation: slideIn 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        max-width: 400px;
        word-wrap: break-word;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        const modalId = e.target.id;
        if (modalId) {
            closeModal(modalId);
        }
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            closeModal(activeModal.id);
        }
    }
});

// Copy email functionality
document.addEventListener('click', function(e) {
    if (e.target.matches('a[href^="mailto:"]')) {
        e.preventDefault();
        const email = e.target.textContent;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(email).then(() => {
                showNotification('Email copiado al portapapeles', 'success');
            }).catch(() => {
                // Fallback si falla
                window.location.href = e.target.href;
            });
        } else {
            // Fallback para navegadores sin clipboard API
            window.location.href = e.target.href;
        }
    }
});

// Mejorar el manejo de formularios
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        // Verificar campos requeridos
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = 'var(--error)';
                field.focus();
                isValid = false;
            } else {
                field.style.borderColor = '';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Por favor complete todos los campos requeridos', 'error');
            return;
        }
        
        // Aplicar estado de carga
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        submitBtn.disabled = true;
        
        // En caso de error, restaurar el botón después de un tiempo
        setTimeout(() => {
            if (submitBtn.disabled) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }, 10000);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>