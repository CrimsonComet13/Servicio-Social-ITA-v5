<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
// TODO: Add role validation, for example, department head
// $session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Get student ID from URL
$estudianteId = $_GET['id'] ?? 0;

// Helper function for safe htmlspecialchars
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get student data
$estudiante = $db->fetch("
    SELECT e.*, u.email, u.created_at as fecha_registro
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.id = ?
", [$estudianteId]);

// Redirect if student not found
if (!$estudiante) {
    flashMessage('Estudiante no encontrado.', 'error');
    redirectTo('/modules/departamento/lista-estudiantes.php');
}

// Get related data
$solicitudes = $db->fetchAll("SELECT * FROM solicitudes_servicio WHERE estudiante_id = ? ORDER BY created_at DESC", [$estudianteId]);
$reportes = $db->fetchAll("SELECT * FROM reportes_bimestrales WHERE estudiante_id = ? ORDER BY created_at DESC", [$estudianteId]);
$documentos = $db->fetchAll("SELECT * FROM oficios_presentacion WHERE solicitud_id IN (SELECT id FROM solicitudes_servicio WHERE estudiante_id = ?) ORDER BY created_at DESC", [$estudianteId]);

$pageTitle = "Detalle del Estudiante - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
        <div class="detail-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="header-info">
                    <h1 class="header-title"><?= safe_html($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></h1>
                    <p class="header-subtitle">Información detallada del estudiante y su progreso.</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="#" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir Kardex</a>
                <a href="/servicio_social_ita/modules/departamento/estudiantes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a la Lista
                </a>
            </div>
        </div>

        <div class="main-content-grid">
            <!-- Columna Izquierda -->
            <div class="left-column">
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-id-card"></i> Información Académica</h3>
                    </div>
                    <div class="card-content">
                        <div class="info-grid two-columns">
                            <div class="info-item"><label>No. de Control:</label><span><?= safe_html($estudiante['numero_control']) ?></span></div>
                            <div class="info-item"><label>Carrera:</label><span><?= safe_html($estudiante['carrera']) ?></span></div>
                            <div class="info-item"><label>Semestre:</label><span><?= safe_html($estudiante['semestre']) ?></span></div>
                            <div class="info-item"><label>Créditos:</label><span><?= safe_html($estudiante['creditos_cursados']) ?></span></div>
                        </div>
                    </div>
                </div>
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-address-book"></i> Información de Contacto</h3>
                    </div>
                    <div class="card-content">
                        <div class="info-grid two-columns">
                            <div class="info-item"><label>Email:</label><a href="mailto:<?= safe_html($estudiante['email']) ?>"><?= safe_html($estudiante['email']) ?></a></div>
                            <div class="info-item"><label>Teléfono:</label><span><?= safe_html($estudiante['telefono'], 'No especificado') ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha -->
            <div class="right-column">
                <div class="info-card status-card">
                    <div class="card-header">
                        <h3><i class="fas fa-tasks"></i> Progreso del Servicio</h3>
                    </div>
                    <div class="card-content">
                        <div class="progress-overview">
                            <div class="progress-item">
                                <span class="progress-label">Estado General</span>
                                <span class="status-badge <?= strtolower(safe_html($estudiante['estado_servicio'])) ?>"><?= safe_html($estudiante['estado_servicio']) ?></span>
                            </div>
                            <div class="progress-item">
                                <span class="progress-label">Horas Cumplidas</span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= ($estudiante['horas_completadas'] / 500) * 100 ?>%;"></div>
                                </div>
                                <span class="progress-value"><?= safe_html($estudiante['horas_completadas']) ?> / 500 hrs</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestañas de Actividad -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-link active" data-tab="solicitudes"><i class="fas fa-file-alt"></i> Solicitudes</button>
                <button class="tab-link" data-tab="reportes"><i class="fas fa-file-invoice"></i> Reportes</button>
                <button class="tab-link" data-tab="documentos"><i class="fas fa-file-contract"></i> Documentos</button>
            </div>
            <div id="solicitudes" class="tab-content active">
                <!-- Contenido de Solicitudes -->
            </div>
            <div id="reportes" class="tab-content">
                <!-- Contenido de Reportes -->
            </div>
            <div id="documentos" class="tab-content">
                <!-- Contenido de Documentos -->
            </div>
        </div>
    </div>
</div>

<style>
/* General Styles */
.dashboard-container { padding: 2rem; }
.detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
.header-content { display: flex; align-items: center; gap: 1.5rem; }
.header-icon { font-size: 2rem; color: var(--primary); }
.header-title { font-size: 2rem; font-weight: 700; }
.header-subtitle { color: var(--text-secondary); }
.header-actions { display: flex; gap: 1rem; }

/* Grid Layout */
.main-content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }

/* Info Cards */
.info-card { background: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
.card-header h3 { font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-content { padding: 1.5rem; }
.info-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
.info-grid.two-columns { grid-template-columns: 1fr 1fr; }
.info-item { display: flex; flex-direction: column; }
.info-item label { font-weight: 600; color: var(--text-secondary); margin-bottom: 0.25rem; }
.info-item span, .info-item a { color: var(--text-primary); }

/* Status Badge */
.status-badge { padding: 0.25rem 0.75rem; border-radius: var(--radius); font-weight: 600; text-transform: capitalize; }
.status-badge.pendiente { background-color: var(--warning-light); color: var(--warning); }
.status-badge.en_proceso { background-color: var(--info-light); color: var(--info); }
.status-badge.concluido { background-color: var(--success-light); color: var(--success); }

/* Progress Bar */
.progress-bar { background-color: var(--bg-light); border-radius: var(--radius); height: 0.5rem; overflow: hidden; margin: 0.5rem 0; }
.progress-fill { background-color: var(--primary); height: 100%; border-radius: var(--radius); transition: width 0.5s ease; }
.progress-value { font-size: 0.875rem; color: var(--text-secondary); }

/* Tabs */
.tabs-container { margin-top: 2rem; }
.tabs-nav { display: flex; border-bottom: 1px solid var(--border); }
.tab-link { padding: 1rem 1.5rem; background: none; border: none; cursor: pointer; font-size: 1rem; font-weight: 600; color: var(--text-secondary); position: relative; }
.tab-link.active { color: var(--primary); }
.tab-link.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--primary); }
.tab-content { padding: 2rem; background: var(--bg-white); border-radius: 0 0 var(--radius-lg) var(--radius-lg); display: none; }
.tab-content.active { display: block; }

/* Resource List */
.resource-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem; }
.resource-item a { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: var(--radius); transition: background-color 0.3s; }
.resource-item a:hover { background-color: var(--bg-light); }
.resource-item i { color: var(--primary); }

/* Responsive */
@media (max-width: 1024px) {
    .main-content-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .detail-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .tabs-nav { flex-wrap: wrap; }
}
</style>

<script>
// Scripts se agregarán aquí
</script>

<?php include '../../includes/footer.php'; ?>

