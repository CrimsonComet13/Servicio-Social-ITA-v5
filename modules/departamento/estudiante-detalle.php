<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Verificar que el usuario sea jefe de departamento
$jefeDepto = $db->fetch("SELECT id FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

// Get student ID from URL
$estudianteId = $_GET['id'] ?? 0;

// Helper function for safe htmlspecialchars
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get student data with complete information
$estudiante = $db->fetch("
    SELECT e.*, u.email, u.created_at as fecha_registro, u.ultimo_acceso
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.id = ?
", [$estudianteId]);

// Redirect if student not found
if (!$estudiante) {
    flashMessage('Estudiante no encontrado.', 'error');
    redirectTo('/modules/departamento/estudiantes.php');
}

// Verificar que el estudiante pertenece al departamento del jefe
$perteneceDepto = $db->fetch("
    SELECT COUNT(*) as count 
    FROM solicitudes_servicio 
    WHERE estudiante_id = ? AND jefe_departamento_id = ?
", [$estudianteId, $jefeId])['count'];

if ($perteneceDepto == 0) {
    flashMessage('No tiene permisos para ver este estudiante.', 'error');
    redirectTo('/modules/departamento/estudiantes.php');
}

// Get related data with more details
$solicitudes = $db->fetchAll("
    SELECT s.*, p.nombre_proyecto, p.descripcion as proyecto_descripcion,
           jl.nombre as jefe_lab_nombre, jl.laboratorio,
           u.email as aprobador_email
    FROM solicitudes_servicio s
    LEFT JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u ON s.aprobada_por = u.id
    WHERE s.estudiante_id = ? 
    ORDER BY s.created_at DESC
", [$estudianteId]);

$reportes = $db->fetchAll("
    SELECT r.*, jl.nombre as evaluador_nombre, jl.laboratorio
    FROM reportes_bimestrales r
    LEFT JOIN jefes_laboratorio jl ON r.jefe_laboratorio_id = jl.id
    WHERE r.estudiante_id = ? 
    ORDER BY r.numero_reporte ASC, r.created_at DESC
", [$estudianteId]);

$documentos = $db->fetchAll("
    SELECT o.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto
    FROM oficios_presentacion o
    JOIN solicitudes_servicio s ON o.solicitud_id = s.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.estudiante_id = ?
    ORDER BY o.created_at DESC
", [$estudianteId]);

// Get carta de terminación if exists
$cartaTerminacion = $db->fetch("
    SELECT * FROM cartas_terminacion 
    WHERE estudiante_id = ?
    ORDER BY created_at DESC
    LIMIT 1
", [$estudianteId]);

// Get constancia if exists
$constancia = $db->fetch("
    SELECT * FROM constancias 
    WHERE estudiante_id = ?
    ORDER BY created_at DESC
    LIMIT 1
", [$estudianteId]);

$pageTitle = "Detalle del Estudiante - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
:root {
    --primary-color: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #3b82f6;
    --secondary-color: #64748b;
    --success-color: #059669;
    --warning-color: #d97706;
    --danger-color: #dc2626;
    --info-color: #0891b2;
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-400: #94a3b8;
    --gray-500: #64748b;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    --white: #ffffff;
    --radius: 12px;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --sidebar-width: 280px;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: var(--gray-800);
    line-height: 1.6;
    min-height: 100vh;
}

.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    transition: margin-left 0.3s ease;
}

.dashboard-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.detail-header {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--primary-color);
    animation: fadeInUp 0.6s ease-out;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.header-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 2rem;
    box-shadow: var(--shadow-lg);
}

.header-info {
    flex: 1;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.5rem 0;
}

.header-subtitle {
    color: var(--gray-600);
    margin: 0;
    font-size: 1rem;
}

.header-meta {
    display: flex;
    gap: 2rem;
    margin-top: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-500);
    font-size: 0.9rem;
}

.meta-item i {
    color: var(--primary-color);
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Main Content Grid */
.main-content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

/* Info Cards */
.info-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.info-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.card-header {
    padding: 1.5rem;
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-200);
}

.card-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h3 i {
    color: var(--primary-color);
}

.card-content {
    padding: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.info-grid.two-columns {
    grid-template-columns: 1fr 1fr;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label {
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item span,
.info-item a {
    color: var(--gray-800);
    font-size: 1rem;
    padding: 0.5rem 0;
}

.info-item a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.2s;
}

.info-item a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Status Card */
.status-card {
    grid-column: 1 / -1;
}

.progress-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.progress-item {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.progress-label {
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-weight: 600;
    text-transform: capitalize;
    font-size: 0.9rem;
}

.status-badge::before {
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
}

.status-badge.sin_solicitud {
    background: var(--gray-200);
    color: var(--gray-700);
}

.status-badge.sin_solicitud::before {
    content: '\f017';
}

.status-badge.solicitud_pendiente,
.status-badge.pendiente {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.solicitud_pendiente::before,
.status-badge.pendiente::before {
    content: '\f017';
}

.status-badge.aprobado,
.status-badge.aprobada {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.aprobado::before,
.status-badge.aprobada::before {
    content: '\f058';
}

.status-badge.en_proceso {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.en_proceso::before {
    content: '\f110';
}

.status-badge.concluido,
.status-badge.concluida {
    background: #dcfce7;
    color: #166534;
}

.status-badge.concluido::before,
.status-badge.concluida::before {
    content: '\f00c';
}

.status-badge.cancelado,
.status-badge.cancelada {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.cancelado::before,
.status-badge.cancelada::before {
    content: '\f00d';
}

/* Progress Bar */
.progress-bar {
    background-color: var(--gray-200);
    border-radius: 9999px;
    height: 1.5rem;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.progress-fill {
    background: linear-gradient(90deg, var(--success-color), #10b981);
    height: 100%;
    border-radius: 9999px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 0.75rem;
    color: var(--white);
    font-weight: 600;
    font-size: 0.875rem;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.3),
        transparent
    );
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-value {
    font-size: 1rem;
    color: var(--gray-700);
    font-weight: 600;
    margin-top: 0.5rem;
    display: block;
}

/* Tabs */
.tabs-container {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

.tabs-nav {
    display: flex;
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-200);
    overflow-x: auto;
}

.tab-link {
    flex: 1;
    padding: 1.25rem 1.5rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-600);
    position: relative;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.tab-link:hover {
    background: var(--white);
    color: var(--primary-color);
}

.tab-link.active {
    color: var(--primary-color);
    background: var(--white);
}

.tab-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--primary-color);
}

.tab-content {
    padding: 2rem;
    display: none;
    animation: fadeIn 0.3s ease-out;
}

.tab-content.active {
    display: block;
}

/* Timeline/List Items */
.timeline-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.timeline-item {
    padding: 1.5rem;
    border-left: 3px solid var(--gray-200);
    margin-left: 1rem;
    position: relative;
    transition: all 0.3s ease;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -0.625rem;
    top: 1.5rem;
    width: 1rem;
    height: 1rem;
    background: var(--white);
    border: 3px solid var(--primary-color);
    border-radius: 50%;
}

.timeline-item:hover {
    background: var(--gray-50);
    border-left-color: var(--primary-color);
}

.timeline-item-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.timeline-item-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}

.timeline-item-meta {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.meta-badge i {
    color: var(--primary-color);
}

.timeline-item-content {
    color: var(--gray-700);
    line-height: 1.6;
}

.timeline-item-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 1rem;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--radius);
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    white-space: nowrap;
}

.btn-primary {
    background: var(--primary-color);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background: var(--gray-300);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.btn-success {
    background: var(--success-color);
    color: var(--white);
}

.btn-success:hover {
    background: #047857;
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: var(--info-color);
    color: var(--white);
}

.btn-info:hover {
    background: #0e7490;
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state p {
    margin: 0;
    font-size: 1.1rem;
}

/* Alert Box */
.alert-box {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    display: flex;
    align-items: start;
    gap: 1rem;
}

.alert-box i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.alert-box.info {
    background: #dbeafe;
    color: #1e40af;
    border-left: 4px solid var(--info-color);
}

.alert-box.success {
    background: #dcfce7;
    color: #166534;
    border-left: 4px solid var(--success-color);
}

.alert-box.warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid var(--warning-color);
}

/* Data Grid */
.data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.data-card {
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.data-card-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.data-card-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
}

/* Animations */
@keyframes fadeInUp {
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

/* Responsive */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }

    .main-content-grid {
        grid-template-columns: 1fr;
    }

    .info-grid.two-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }

    .detail-header {
        padding: 1.5rem;
    }

    .header-content {
        flex-direction: column;
        text-align: center;
    }

    .header-title {
        font-size: 1.5rem;
    }

    .header-actions {
        justify-content: center;
        width: 100%;
    }

    .btn {
        flex: 1;
        justify-content: center;
    }

    .tabs-nav {
        flex-wrap: wrap;
    }

    .tab-link {
        flex: 1 1 auto;
        min-width: 120px;
    }

    .tab-content {
        padding: 1rem;
    }

    .progress-overview {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header -->
        <div class="detail-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="header-info">
                    <h1 class="header-title"><?= safe_html($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h1>
                    <p class="header-subtitle"><?= safe_html($estudiante['carrera']) ?> - <?= safe_html($estudiante['numero_control']) ?></p>
                    <div class="header-meta">
                        <span class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            Registrado: <?= date('d/m/Y', strtotime($estudiante['fecha_registro'])) ?>
                        </span>
                        <?php if ($estudiante['ultimo_acceso']): ?>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i>
                            Último acceso: <?= date('d/m/Y H:i', strtotime($estudiante['ultimo_acceso'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <a href="/servicio_social_ita/modules/departamento/estudiantes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a la Lista
                </a>
                <?php if ($estudiante['estado_servicio'] === 'concluido' && $constancia): ?>
                    <a href="#" class="btn btn-success" onclick="alert('Funcionalidad de impresión en desarrollo')">
                        <i class="fas fa-print"></i>
                        Imprimir Constancia
                    </a>
                <?php elseif ($estudiante['estado_servicio'] === 'en_proceso'): ?>
                    <a href="#" class="btn btn-primary" onclick="alert('Funcionalidad de Kardex en desarrollo')">
                        <i class="fas fa-file-alt"></i>
                        Ver Kardex
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-content-grid">
            <!-- Información Académica -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>No. de Control:</label>
                            <span><?= safe_html($estudiante['numero_control']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Carrera:</label>
                            <span><?= safe_html($estudiante['carrera']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Semestre Actual:</label>
                            <span><?= safe_html($estudiante['semestre']) ?>°</span>
                        </div>
                        <div class="info-item">
                            <label>Créditos Cursados:</label>
                            <span><?= safe_html($estudiante['creditos_cursados']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de Contacto -->
            <div class="info-card">
                <div class="card-header">
                    <h3><i class="fas fa-address-book"></i> Información de Contacto</h3>
                </div>
                <div class="card-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Email:</label>
                            <a href="mailto:<?= safe_html($estudiante['email']) ?>"><?= safe_html($estudiante['email']) ?></a>
                        </div>
                        <div class="info-item">
                            <label>Teléfono:</label>
                            <span><?= safe_html($estudiante['telefono'], 'No especificado') ?></span>
                        </div>
                        <?php if ($estudiante['fecha_inicio_servicio']): ?>
                        <div class="info-item">
                            <label>Inicio de Servicio:</label>
                            <span><?= date('d/m/Y', strtotime($estudiante['fecha_inicio_servicio'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($estudiante['fecha_fin_servicio']): ?>
                        <div class="info-item">
                            <label>Fin de Servicio:</label>
                            <span><?= date('d/m/Y', strtotime($estudiante['fecha_fin_servicio'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Progreso del Servicio -->
            <div class="info-card status-card">
                <div class="card-header">
                    <h3><i class="fas fa-tasks"></i> Progreso del Servicio Social</h3>
                </div>
                <div class="card-content">
                    <div class="progress-overview">
                        <div class="progress-item">
                            <span class="progress-label">Estado General</span>
                            <span class="status-badge <?= strtolower(safe_html($estudiante['estado_servicio'])) ?>">
                                <?= ucfirst(str_replace('_', ' ', safe_html($estudiante['estado_servicio']))) ?>
                            </span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Horas Cumplidas</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(100, ($estudiante['horas_completadas'] / 500) * 100) ?>%;">
                                    <?= round(($estudiante['horas_completadas'] / 500) * 100, 1) ?>%
                                </div>
                            </div>
                            <span class="progress-value"><?= safe_html($estudiante['horas_completadas']) ?> / 500 hrs</span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Reportes Bimestrales</span>
                            <span class="progress-value"><?= count($reportes) ?> de 3 entregados</span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Solicitudes</span>
                            <span class="progress-value"><?= count($solicitudes) ?> registrada(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Container -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-link active" data-tab="solicitudes">
                    <i class="fas fa-file-alt"></i> Solicitudes (<?= count($solicitudes) ?>)
                </button>
                <button class="tab-link" data-tab="reportes">
                    <i class="fas fa-file-invoice"></i> Reportes (<?= count($reportes) ?>)
                </button>
                <button class="tab-link" data-tab="documentos">
                    <i class="fas fa-file-contract"></i> Documentos (<?= count($documentos) ?>)
                </button>
                <?php if ($cartaTerminacion || $constancia): ?>
                <button class="tab-link" data-tab="finalizacion">
                    <i class="fas fa-award"></i> Finalización
                </button>
                <?php endif; ?>
            </div>

            <!-- Tab: Solicitudes -->
            <div id="solicitudes" class="tab-content active">
                <?php if ($solicitudes): ?>
                    <ul class="timeline-list">
                        <?php foreach ($solicitudes as $solicitud): ?>
                        <li class="timeline-item">
                            <div class="timeline-item-header">
                                <div>
                                    <h4 class="timeline-item-title"><?= safe_html($solicitud['nombre_proyecto']) ?></h4>
                                    <div class="timeline-item-meta">
                                        <span class="meta-badge">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?>
                                        </span>
                                        <?php if ($solicitud['laboratorio']): ?>
                                        <span class="meta-badge">
                                            <i class="fas fa-flask"></i>
                                            <?= safe_html($solicitud['laboratorio']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($solicitud['jefe_lab_nombre']): ?>
                                        <span class="meta-badge">
                                            <i class="fas fa-user"></i>
                                            <?= safe_html($solicitud['jefe_lab_nombre']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge <?= strtolower($solicitud['estado']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $solicitud['estado'])) ?>
                                </span>
                            </div>
                            
                            <?php if ($solicitud['proyecto_descripcion']): ?>
                            <div class="timeline-item-content">
                                <p><strong>Descripción del proyecto:</strong></p>
                                <p><?= safe_html($solicitud['proyecto_descripcion']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($solicitud['motivo_solicitud']): ?>
                            <div class="timeline-item-content">
                                <p><strong>Motivo de la solicitud:</strong></p>
                                <p><?= safe_html($solicitud['motivo_solicitud']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="data-grid" style="margin-top: 1rem;">
                                <div class="data-card">
                                    <div class="data-card-label">Periodo Propuesto</div>
                                    <div class="data-card-value">
                                        <?= date('d/m/Y', strtotime($solicitud['fecha_inicio_propuesta'])) ?> - 
                                        <?= date('d/m/Y', strtotime($solicitud['fecha_fin_propuesta'])) ?>
                                    </div>
                                </div>
                                <?php if ($solicitud['estado'] === 'aprobada' && $solicitud['fecha_aprobacion']): ?>
                                <div class="data-card">
                                    <div class="data-card-label">Fecha de Aprobación</div>
                                    <div class="data-card-value"><?= date('d/m/Y H:i', strtotime($solicitud['fecha_aprobacion'])) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($solicitud['observaciones_jefe']): ?>
                            <div class="alert-box info" style="margin-top: 1rem;">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Observaciones del Jefe de Departamento:</strong>
                                    <p style="margin: 0.5rem 0 0 0;"><?= safe_html($solicitud['observaciones_jefe']) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($solicitud['motivo_rechazo']): ?>
                            <div class="alert-box warning" style="margin-top: 1rem;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Motivo de Rechazo:</strong>
                                    <p style="margin: 0.5rem 0 0 0;"><?= safe_html($solicitud['motivo_rechazo']) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No hay solicitudes registradas para este estudiante</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Reportes -->
            <div id="reportes" class="tab-content">
                <?php if ($reportes): ?>
                    <ul class="timeline-list">
                        <?php foreach ($reportes as $reporte): ?>
                        <li class="timeline-item">
                            <div class="timeline-item-header">
                                <div>
                                    <h4 class="timeline-item-title">Reporte Bimestral #<?= $reporte['numero_reporte'] ?></h4>
                                    <div class="timeline-item-meta">
                                        <span class="meta-badge">
                                            <i class="fas fa-calendar"></i>
                                            Periodo: <?= date('d/m/Y', strtotime($reporte['periodo_inicio'])) ?> - <?= date('d/m/Y', strtotime($reporte['periodo_fin'])) ?>
                                        </span>
                                        <span class="meta-badge">
                                            <i class="fas fa-clock"></i>
                                            <?= $reporte['horas_reportadas'] ?> hrs
                                        </span>
                                        <?php if ($reporte['laboratorio']): ?>
                                        <span class="meta-badge">
                                            <i class="fas fa-flask"></i>
                                            <?= safe_html($reporte['laboratorio']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge <?= strtolower($reporte['estado']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $reporte['estado'])) ?>
                                </span>
                            </div>
                            
                            <div class="data-grid" style="margin-top: 1rem;">
                                <div class="data-card">
                                    <div class="data-card-label">Horas Reportadas</div>
                                    <div class="data-card-value"><?= $reporte['horas_reportadas'] ?> hrs</div>
                                </div>
                                <div class="data-card">
                                    <div class="data-card-label">Horas Acumuladas</div>
                                    <div class="data-card-value"><?= $reporte['horas_acumuladas'] ?> hrs</div>
                                </div>
                                <div class="data-card">
                                    <div class="data-card-label">Fecha de Entrega</div>
                                    <div class="data-card-value"><?= date('d/m/Y', strtotime($reporte['fecha_entrega'])) ?></div>
                                </div>
                                <?php if ($reporte['calificacion']): ?>
                                <div class="data-card">
                                    <div class="data-card-label">Calificación</div>
                                    <div class="data-card-value"><?= $reporte['calificacion'] ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-item-content" style="margin-top: 1rem;">
                                <p><strong>Actividades Realizadas:</strong></p>
                                <p><?= nl2br(safe_html($reporte['actividades_realizadas'])) ?></p>
                                
                                <?php if ($reporte['logros_obtenidos']): ?>
                                <p style="margin-top: 1rem;"><strong>Logros Obtenidos:</strong></p>
                                <p><?= nl2br(safe_html($reporte['logros_obtenidos'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($reporte['dificultades_encontradas']): ?>
                                <p style="margin-top: 1rem;"><strong>Dificultades Encontradas:</strong></p>
                                <p><?= nl2br(safe_html($reporte['dificultades_encontradas'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($reporte['observaciones_evaluador']): ?>
                            <div class="alert-box success" style="margin-top: 1rem;">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong>Observaciones del Evaluador:</strong>
                                    <p style="margin: 0.5rem 0 0 0;"><?= nl2br(safe_html($reporte['observaciones_evaluador'])) ?></p>
                                    <?php if ($reporte['evaluador_nombre']): ?>
                                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; color: var(--gray-600);">
                                        <i class="fas fa-user"></i> <?= safe_html($reporte['evaluador_nombre']) ?>
                                        <?php if ($reporte['fecha_evaluacion']): ?>
                                        - <?= date('d/m/Y H:i', strtotime($reporte['fecha_evaluacion'])) ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($reporte['archivo_path']): ?>
                            <div class="timeline-item-footer">
                                <a href="<?= safe_html($reporte['archivo_path']) ?>" class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-download"></i> Descargar Reporte
                                </a>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>No hay reportes registrados para este estudiante</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Documentos -->
            <div id="documentos" class="tab-content">
                <?php if ($documentos): ?>
                    <ul class="timeline-list">
                        <?php foreach ($documentos as $doc): ?>
                        <li class="timeline-item">
                            <div class="timeline-item-header">
                                <div>
                                    <h4 class="timeline-item-title">Oficio de Presentación</h4>
                                    <div class="timeline-item-meta">
                                        <span class="meta-badge">
                                            <i class="fas fa-hashtag"></i>
                                            <?= safe_html($doc['numero_oficio']) ?>
                                        </span>
                                        <span class="meta-badge">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d/m/Y', strtotime($doc['fecha_emision'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="status-badge <?= strtolower($doc['estado']) ?>">
                                    <?= ucfirst($doc['estado']) ?>
                                </span>
                            </div>
                            
                            <div class="timeline-item-content">
                                <p><strong>Proyecto:</strong> <?= safe_html($doc['nombre_proyecto']) ?></p>
                                <?php if ($doc['fecha_entrega']): ?>
                                <p><strong>Fecha de Entrega:</strong> <?= date('d/m/Y', strtotime($doc['fecha_entrega'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($doc['archivo_path']): ?>
                            <div class="timeline-item-footer">
                                <a href="<?= safe_html($doc['archivo_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Ver Oficio
                                </a>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-contract"></i>
                        <p>No hay documentos registrados para este estudiante</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Finalización -->
            <?php if ($cartaTerminacion || $constancia): ?>
            <div id="finalizacion" class="tab-content">
                <?php if ($cartaTerminacion): ?>
                <div class="info-card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-certificate"></i> Carta de Terminación</h3>
                    </div>
                    <div class="card-content">
                        <div class="data-grid">
                            <div class="data-card">
                                <div class="data-card-label">Número de Carta</div>
                                <div class="data-card-value"><?= safe_html($cartaTerminacion['numero_carta']) ?></div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Fecha de Terminación</div>
                                <div class="data-card-value"><?= date('d/m/Y', strtotime($cartaTerminacion['fecha_terminacion'])) ?></div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Horas Cumplidas</div>
                                <div class="data-card-value"><?= $cartaTerminacion['horas_cumplidas'] ?> hrs</div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Nivel de Desempeño</div>
                                <div class="data-card-value"><?= safe_html($cartaTerminacion['nivel_desempeno']) ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <p><strong>Periodo de Servicio:</strong></p>
                            <p><?= safe_html($cartaTerminacion['periodo_servicio']) ?></p>
                            
                            <p style="margin-top: 1rem;"><strong>Actividades Principales:</strong></p>
                            <p><?= nl2br(safe_html($cartaTerminacion['actividades_principales'])) ?></p>
                            
                            <?php if ($cartaTerminacion['observaciones']): ?>
                            <p style="margin-top: 1rem;"><strong>Observaciones:</strong></p>
                            <p><?= nl2br(safe_html($cartaTerminacion['observaciones'])) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($cartaTerminacion['archivo_path']): ?>
                        <div style="margin-top: 1.5rem;">
                            <a href="<?= safe_html($cartaTerminacion['archivo_path']) ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Ver Carta de Terminación
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($constancia): ?>
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-award"></i> Constancia Final</h3>
                    </div>
                    <div class="card-content">
                        <div class="data-grid">
                            <div class="data-card">
                                <div class="data-card-label">Número de Constancia</div>
                                <div class="data-card-value"><?= safe_html($constancia['numero_constancia']) ?></div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Fecha de Emisión</div>
                                <div class="data-card-value"><?= date('d/m/Y', strtotime($constancia['fecha_emision'])) ?></div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Calificación Final</div>
                                <div class="data-card-value"><?= $constancia['calificacion_final'] ?></div>
                            </div>
                            <div class="data-card">
                                <div class="data-card-label">Nivel de Desempeño</div>
                                <div class="data-card-value"><?= safe_html($constancia['nivel_desempeno']) ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <p><strong>Periodo Completo:</strong></p>
                            <p><?= safe_html($constancia['periodo_completo']) ?></p>
                            
                            <p style="margin-top: 1rem;"><strong>Horas Cumplidas:</strong> <?= $constancia['horas_cumplidas'] ?> hrs</p>
                        </div>
                        
                        <?php if ($constancia['enviado_servicios_escolares']): ?>
                        <div class="alert-box success" style="margin-top: 1.5rem;">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Enviado a Servicios Escolares</strong>
                                <p style="margin: 0.5rem 0 0 0;">
                                    Fecha de envío: <?= date('d/m/Y H:i', strtotime($constancia['fecha_envio_escolares'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($constancia['archivo_path']): ?>
                        <div style="margin-top: 1.5rem;">
                            <a href="<?= safe_html($constancia['archivo_path']) ?>" class="btn btn-success" target="_blank">
                                <i class="fas fa-file-pdf"></i> Ver Constancia Final
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });
    
    // Animate progress bar
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        const width = progressFill.style.width;
        progressFill.style.width = '0%';
        setTimeout(() => {
            progressFill.style.width = width;
        }, 300);
    }
    
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
    
    // Smooth scroll for any anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>