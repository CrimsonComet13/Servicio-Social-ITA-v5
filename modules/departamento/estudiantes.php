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

// Obtener estudiantes - CORREGIDO: e.horas_completadas en lugar de s.horas_completadas
$estudiantes = $db->fetchAll("
    SELECT e.*, s.estado as estado_servicio, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           e.horas_completadas, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE $whereClause
    ORDER BY e.nombre
    LIMIT $limit OFFSET $offset
", $params);

// Obtener total para paginación
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM solicitudes_servicio s
    WHERE $whereClause
", $params)['total'];

$totalPages = ceil($total / $limit);

// Obtener estadísticas - CORREGIDO: SUM(e.horas_completadas) en lugar de SUM(s.horas_completadas)
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as en_proceso,
        COUNT(CASE WHEN s.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN s.estado = 'concluida' THEN 1 END) as concluidas,
        COALESCE(SUM(e.horas_completadas), 0) as horas_totales
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    WHERE s.jefe_departamento_id = :jefe_id
", ['jefe_id' => $jefeId]);

$departamentoInfo = $db->fetch("
    SELECT departamento 
    FROM jefes_departamento 
    WHERE id = :jefe_id
", ['jefe_id' => $jefeId]);

$departamento = $departamentoInfo['departamento'] ?? 'No especificado';


$pageTitle = "Estudiantes - " . APP_NAME;
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
}

.dashboard-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.dashboard-header h1::before {
    content: '\f501';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.dashboard-header p {
    color: var(--gray-600);
    font-size: 1.1rem;
    margin: 0;
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

.stat-card:nth-child(2)::before {
    background: linear-gradient(90deg, var(--warning-color), #f59e0b);
}

.stat-card:nth-child(3)::before {
    background: linear-gradient(90deg, var(--success-color), #10b981);
}

.stat-card:nth-child(4)::before {
    background: linear-gradient(90deg, var(--info-color), #06b6d4);
}

.stat-card h3 {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-card h3::after {
    content: '\f007';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: var(--white);
    background: var(--primary-color);
}

.stat-card:nth-child(2) h3::after {
    content: '\f017';
    background: var(--warning-color);
}

.stat-card:nth-child(3) h3::after {
    content: '\f058';
    background: var(--success-color);
}

.stat-card:nth-child(4) h3::after {
    content: '\f091';
    background: var(--info-color);
}

.stat-card:nth-child(5) h3::after {
    content: '\f252';
    background: var(--secondary-color);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
    display: block;
}

.stat-card::after {
    content: attr(data-label);
    font-size: 0.85rem;
    color: var(--gray-500);
    display: block;
}

/* Filters */
.filters {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.4s both;
}

/* Filters Header */
.filters-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
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
.filter-tab:nth-child(2)::before { content: '\f017'; }
.filter-tab:nth-child(3)::before { content: '\f058'; }
.filter-tab:nth-child(4)::before { content: '\f091'; }

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
.table-responsive {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease-out 0.6s both;
}

/* Table Header */
.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    background: var(--gray-50);
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
    content: '\f0ce';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--primary-color);
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

/* Progress Bar */
.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    width: 60px;
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success-color), #10b981);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
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

.badge.estado-en_proceso,
.badge.en-proceso {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.badge.estado-en_proceso::before,
.badge.en-proceso::before {
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

.badge.estado-concluida,
.badge.concluida {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #93c5fd;
}

.badge.estado-concluida::before,
.badge.concluida::before {
    content: '\f091';
}

.badge.estado-pendiente,
.badge.pendiente {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.badge.estado-pendiente::before,
.badge.pendiente::before {
    content: '\f017';
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
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

.empty-state p {
    color: var(--gray-500);
    font-size: 1.1rem;
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
    <div class="dashboard-content">
 <div class="dashboard-header">
    <h1>Estudiantes del Departamento</h1>
    <p>Gestión de estudiantes del departamento <?= htmlspecialchars($departamento) ?></p>
</div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card" data-label="Registrados en el sistema">
            <h3>Total Estudiantes</h3>
            <div class="stat-number"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card" data-label="Realizando servicio">
            <h3>En Proceso</h3>
            <div class="stat-number"><?= $stats['en_proceso'] ?></div>
        </div>
        <div class="stat-card" data-label="Solicitudes aprobadas">
            <h3>Aprobadas</h3>
            <div class="stat-number"><?= $stats['aprobadas'] ?></div>
        </div>
        <div class="stat-card" data-label="Servicio completado">
            <h3>Concluidas</h3>
            <div class="stat-number"><?= $stats['concluidas'] ?></div>
        </div>
        <div class="stat-card" data-label="Horas completadas">
            <h3>Horas Totales</h3>
            <div class="stat-number"><?= $stats['horas_totales'] ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters">
        <div class="filters-header">
            <div class="filters-title">
                Filtrar estudiantes
            </div>
        </div>
        <div class="filter-tabs">
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todos (<?= $total ?>)
            </a>
            <a href="?estado=en_proceso" class="filter-tab <?= $estado === 'en_proceso' ? 'active' : '' ?>">
                En Proceso
            </a>
            <a href="?estado=aprobada" class="filter-tab <?= $estado === 'aprobada' ? 'active' : '' ?>">
                Aprobadas
            </a>
            <a href="?estado=concluida" class="filter-tab <?= $estado === 'concluida' ? 'active' : '' ?>">
                Concluidas
            </a>
        </div>
    </div>

    <?php if ($estudiantes): ?>
        <div class="table-responsive">
            <div class="table-header">
                <h2 class="table-title">Lista de Estudiantes</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>No. Control</th>
                        <th>Carrera</th>
                        <th>Proyecto</th>
                        <th>Laboratorio</th>
                        <th>Horas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $estudiante): ?>
                    <tr>
                        <td>
                            <div class="student-profile">
                                <div class="student-avatar">
                                    <?= strtoupper(substr($estudiante['nombre'], 0, 1) . substr($estudiante['apellido_paterno'], 0, 1)) ?>
                                </div>
                                <div class="student-info">
                                    <h4><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></h4>
                                    <p><?= htmlspecialchars($estudiante['numero_control']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($estudiante['numero_control']) ?></td>
                        <td><?= htmlspecialchars($estudiante['carrera']) ?></td>
                        <td><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></td>
                        <td><?= htmlspecialchars($estudiante['laboratorio'] ?? 'N/A') ?></td>
                        <td>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min(100, ($estudiante['horas_completadas'] / 500) * 100) ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $estudiante['horas_completadas'] ?>/500</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge estado-<?= $estudiante['estado_servicio'] ?>">
                                <?= getEstadoText($estudiante['estado_servicio']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="/modules/departamento/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                
                                <?php if ($estudiante['estado_servicio'] === 'en_proceso'): ?>
                                    <a href="/modules/departamento/generar-constancia.php?estudiante_id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-file-pdf"></i> Constancia
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            <i class="fas fa-user-graduate"></i>
            <p>No hay estudiantes que coincidan con los filtros seleccionados</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animación de barras de progreso
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 300 + (index * 100));
    });

    // Efecto hover en las filas de la tabla
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.boxShadow = 'var(--shadow-lg)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.boxShadow = 'none';
        });
    });

    // Tooltip simple para botones
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>