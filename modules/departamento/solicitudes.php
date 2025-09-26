<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Procesar filtros
$estado = $_GET['estado'] ?? 'todos';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = ["s.jefe_departamento_id = :jefe_id"];
$params = ['jefe_id' => $jefeId];

if ($estado !== 'todos') {
    $whereConditions[] = "s.estado = :estado";
    $params['estado'] = $estado;
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener solicitudes
$solicitudes = $db->fetchAll("
    SELECT s.*, e.nombre as estudiante_nombre, e.apellido_paterno, e.numero_control, e.carrera,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE $whereClause
    ORDER BY s.fecha_solicitud DESC
    LIMIT $limit OFFSET $offset
", $params);

// Obtener total para paginación
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM solicitudes_servicio s
    WHERE $whereClause
", $params)['total'];

$totalPages = ceil($total / $limit);

// Obtener estadísticas para las cards
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN s.estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN s.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN s.estado = 'rechazada' THEN 1 END) as rechazadas,
        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as en_proceso,
        COUNT(CASE WHEN s.fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recientes
    FROM solicitudes_servicio s
    WHERE s.jefe_departamento_id = :jefe_id
", ['jefe_id' => $jefeId]);

$pageTitle = "Solicitudes de Servicio Social - " . APP_NAME;
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
    --purple-color: #7c3aed;
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
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: var(--gray-800);
    line-height: 1.6;
    min-height: 100vh;
}

.dashboard-content {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.dashboard-header {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--primary-color);
    animation: fadeInUp 0.6s ease-out;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(30%, -30%);
}

.dashboard-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.dashboard-header h1::before {
    content: '\f0f6';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.dashboard-header p {
    color: var(--gray-600);
    font-size: 1.1rem;
    margin: 0;
    position: relative;
    z-index: 1;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    position: relative;
    z-index: 1;
}

.quick-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--primary-color);
    color: var(--white);
    text-decoration: none;
    border-radius: var(--radius);
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.quick-action-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

.stat-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
}

.stat-card.warning::before {
    background: linear-gradient(90deg, var(--warning-color), #f59e0b);
}

.stat-card.success::before {
    background: linear-gradient(90deg, var(--success-color), #10b981);
}

.stat-card.danger::before {
    background: linear-gradient(90deg, var(--danger-color), #ef4444);
}

.stat-card.info::before {
    background: linear-gradient(90deg, var(--info-color), #06b6d4);
}

.stat-card.purple::before {
    background: linear-gradient(90deg, var(--purple-color), #8b5cf6);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--white);
    background: var(--primary-color);
}

.stat-icon.warning { background: var(--warning-color); }
.stat-icon.success { background: var(--success-color); }
.stat-icon.danger { background: var(--danger-color); }
.stat-icon.info { background: var(--info-color); }
.stat-icon.purple { background: var(--purple-color); }

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
}

.stat-subtitle {
    font-size: 0.85rem;
    color: var(--gray-500);
}

/* Filters */
.filters-container {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
    flex-wrap: wrap;
    gap: 1rem;
}

.filters-title {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filters-title::before {
    content: '\f0b0';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--primary-color);
}

.search-box {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input {
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.9rem;
    width: 300px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--gray-600);
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    background: var(--white);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-tab::before {
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
}

.filter-tab:nth-child(1)::before { content: '\f03a'; }
.filter-tab:nth-child(2)::before { content: '\f017'; color: var(--warning-color); }
.filter-tab:nth-child(3)::before { content: '\f058'; color: var(--success-color); }
.filter-tab:nth-child(4)::before { content: '\f06a'; color: var(--danger-color); }
.filter-tab:nth-child(5)::before { content: '\f252'; color: var(--info-color); }

.filter-tab:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.filter-tab.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
    box-shadow: var(--shadow);
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.6s both;
}

.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.table-title::before {
    content: '\f0f6';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--primary-color);
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
}

.data-table th {
    background: var(--gray-50);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    border-bottom: 2px solid var(--gray-200);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
    vertical-align: middle;
}

.data-table tbody tr {
    transition: all 0.2s ease;
}

.data-table tbody tr:hover {
    background: var(--gray-50);
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Student Profile */
.student-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-weight: 600;
    font-size: 0.875rem;
    position: relative;
}

.student-avatar::after {
    content: '\f501';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    bottom: -2px;
    right: -2px;
    background: var(--success-color);
    color: var(--white);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    font-size: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--white);
}

.student-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 0.125rem 0;
}

.student-info p {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0;
}

/* Priority Indicator */
.priority-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.priority-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--warning-color);
}

.priority-dot.high { background: var(--danger-color); }
.priority-dot.medium { background: var(--warning-color); }
.priority-dot.low { background: var(--success-color); }

.priority-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
}

/* Time Indicator */
.time-indicator {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8rem;
    color: var(--gray-600);
}

.time-indicator::before {
    content: '\f017';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--gray-400);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge::before {
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
}

.badge.estado-pendiente,
.badge.pendiente {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge.estado-pendiente::before,
.badge.pendiente::before {
    content: '\f017';
}

.badge.estado-aprobada,
.badge.aprobada {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.badge.estado-aprobada::before,
.badge.aprobada::before {
    content: '\f058';
}

.badge.estado-rechazada,
.badge.rechazada {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.badge.estado-rechazada::before,
.badge.rechazada::before {
    content: '\f06a';
}

.badge.estado-en_proceso,
.badge.en-proceso {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.badge.estado-en_proceso::before,
.badge.en-proceso::before {
    content: '\f252';
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.375rem 0.625rem;
    font-size: 0.75rem;
}

.btn-info {
    background: var(--info-color);
    color: var(--white);
}

.btn-info:hover {
    background: #0e7490;
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
    box-shadow: var(--shadow);
}

.btn-error,
.btn-danger {
    background: var(--danger-color);
    color: var(--white);
}

.btn-error:hover,
.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
    animation: fadeInUp 0.6s ease-out 0.8s both;
}

.pagination-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--gray-600);
    font-weight: 500;
    transition: all 0.3s ease;
    background: var(--white);
}

.pagination-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.pagination-link.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
    box-shadow: var(--shadow);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.6s both;
}

.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--gray-500);
    font-size: 1rem;
    margin: 0;
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

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.pulse {
    animation: pulse 2s infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-content {
        padding: 1rem;
    }

    .dashboard-header {
        padding: 1.5rem;
    }

    .dashboard-header h1 {
        font-size: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .filters-header {
        flex-direction: column;
        align-items: stretch;
    }

    .search-input {
        width: 100%;
    }

    .filter-tabs {
        justify-content: center;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn {
        justify-content: center;
        width: 100%;
    }

    .data-table {
        font-size: 0.8rem;
    }

    .data-table th,
    .data-table td {
        padding: 0.5rem;
    }

    .quick-actions {
        flex-direction: column;
    }
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
<div class="main-wrapper">
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Solicitudes de Servicio Social</h1>
        <p>Gestión y seguimiento de solicitudes del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
        <div class="quick-actions">
            <a href="../../modules/departamento/reportes.php" class="quick-action-btn">
                <i class="fas fa-chart-bar"></i>
                Generar Reporte
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='?estado=todos'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Total Solicitudes</div>
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-subtitle">Registradas en el sistema</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
        </div>

        <div class="stat-card warning" onclick="window.location.href='?estado=pendiente'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Pendientes</div>
                    <div class="stat-number"><?= $stats['pendientes'] ?></div>
                    <div class="stat-subtitle">Requieren revisión</div>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

        <div class="stat-card success" onclick="window.location.href='?estado=aprobada'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Aprobadas</div>
                    <div class="stat-number"><?= $stats['aprobadas'] ?></div>
                    <div class="stat-subtitle">Listas para iniciar</div>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="stat-card danger" onclick="window.location.href='?estado=rechazada'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Rechazadas</div>
                    <div class="stat-number"><?= $stats['rechazadas'] ?></div>
                    <div class="stat-subtitle">No aprobadas</div>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>

        <div class="stat-card info" onclick="window.location.href='?estado=en_proceso'">
            <div class="stat-header">
                <div>
                    <div class="stat-title">En Proceso</div>
                    <div class="stat-number"><?= $stats['en_proceso'] ?></div>
                    <div class="stat-subtitle">Servicio activo</div>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-cogs"></i>
                </div>
            </div>
        </div>

        <div class="stat-card purple">
            <div class="stat-header">
                <div>
                    <div class="stat-title">Recientes</div>
                    <div class="stat-number"><?= $stats['recientes'] ?></div>
                    <div class="stat-subtitle">Últimos 30 días</div>
                </div>
                <div class="stat-icon purple">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-container">
        <div class="filters-header">
            <div class="filters-title">
                Filtrar solicitudes
            </div>
            <div class="search-box">
                <input type="text" class="search-input" placeholder="Buscar por estudiante, número de control o proyecto...">
                <button class="btn btn-info">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todas (<?= $total ?>)
            </a>
            <a href="?estado=pendiente" class="filter-tab <?= $estado === 'pendiente' ? 'active' : '' ?>">
                Pendientes
            </a>
            <a href="?estado=aprobada" class="filter-tab <?= $estado === 'aprobada' ? 'active' : '' ?>">
                Aprobadas
            </a>
            <a href="?estado=rechazada" class="filter-tab <?= $estado === 'rechazada' ? 'active' : '' ?>">
                Rechazadas
            </a>
            <a href="?estado=en_proceso" class="filter-tab <?= $estado === 'en_proceso' ? 'active' : '' ?>">
                En Proceso
            </a>
        </div>
    </div>

    <?php if ($solicitudes): ?>
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Lista de Solicitudes</h2>
                <div class="table-actions">
                    <button class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i>
                        Exportar
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>No. Control</th>
                            <th>Carrera</th>
                            <th>Proyecto</th>
                            <th>Laboratorio</th>
                            <th>Fecha Solicitud</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td>
                                <div class="student-profile">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($solicitud['estudiante_nombre'], 0, 1) . substr($solicitud['apellido_paterno'] ?? '', 0, 1)) ?>
                                    </div>
                                    <div class="student-info">
                                        <h4><?= htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . ($solicitud['apellido_paterno'] ?? '')) ?></h4>
                                        <p><?= htmlspecialchars($solicitud['numero_control']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($solicitud['numero_control']) ?></td>
                            <td><?= htmlspecialchars($solicitud['carrera']) ?></td>
                            <td><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></td>
                            <td><?= htmlspecialchars($solicitud['laboratorio'] ?? 'N/A') ?></td>
                            <td>
                                <div class="time-indicator">
                                    <?= formatDate($solicitud['fecha_solicitud']) ?>
                                </div>
                                <div class="priority-indicator">
                                    <?php 
                                    $daysDiff = (time() - strtotime($solicitud['fecha_solicitud'])) / (60 * 60 * 24);
                                    if ($daysDiff > 7): ?>
                                        <span class="priority-dot high"></span>
                                        <span class="priority-text">Urgente</span>
                                    <?php elseif ($daysDiff > 3): ?>
                                        <span class="priority-dot medium"></span>
                                        <span class="priority-text">Media</span>
                                    <?php else: ?>
                                        <span class="priority-dot low"></span>
                                        <span class="priority-text">Reciente</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge estado-<?= $solicitud['estado'] ?>">
                                    <?= getEstadoText($solicitud['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/servicio_social_ita/modules/departamento/solicitud-detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    
                                    <?php if ($solicitud['estado'] === 'pendiente'): ?>
                                        <a href="/modules/departamento/aprobar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Aprobar
                                        </a>
                                        <a href="/modules/departamento/rechazar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-danger">
                                            <i class="fas fa-times"></i> Rechazar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?estado=<?= $estado ?>&page=<?= $page - 1 ?>" class="pagination-link">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?estado=<?= $estado ?>&page=<?= $i ?>" class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?estado=<?= $estado ?>&page=<?= $page + 1 ?>" class="pagination-link">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>No hay solicitudes</h3>
            <p>No hay solicitudes que coincidan con los filtros seleccionados</p>
        </div>
    <?php endif; ?>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Búsqueda en tiempo real
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Efectos hover en stats cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-6px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Animaciones de aparición escalonada
    const animatedElements = document.querySelectorAll('[class*="fadeInUp"]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });

    animatedElements.forEach(el => {
        observer.observe(el);
    });

    // Efectos en botones
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Auto-refresh para solicitudes pendientes (opcional)
    if (window.location.search.includes('pendiente')) {
        const pendingBadges = document.querySelectorAll('.badge.pendiente');
        pendingBadges.forEach(badge => {
            badge.classList.add('pulse');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>