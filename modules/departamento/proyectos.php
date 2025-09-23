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
$limit = 12;
$offset = ($page - 1) * $limit;

// Construir consulta con filtros
$whereConditions = ["p.jefe_departamento_id = :jefe_id"];
$params = ['jefe_id' => $jefeId];

if ($estado !== 'todos') {
    $whereConditions[] = "p.activo = :activo";
    $params['activo'] = $estado === 'activos' ? 1 : 0;
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener proyectos
$proyectos = $db->fetchAll("
    SELECT p.*, 
           jl.nombre as jefe_lab_nombre, 
           jl.laboratorio,
           COUNT(DISTINCT s.id) as solicitudes_recibidas,
           COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as estudiantes_activos,
           COALESCE(p.cupo_ocupado, 0) as cupo_ocupado
    FROM proyectos_laboratorio p
    LEFT JOIN jefes_laboratorio jl ON p.jefe_laboratorio_id = jl.id
    LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    WHERE $whereClause
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Obtener estadísticas generales
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_proyectos,
        COUNT(CASE WHEN p.activo = 1 THEN 1 END) as proyectos_activos,
        COALESCE(SUM(CASE WHEN p.activo = 1 THEN 1 ELSE 0 END), 0) as activos_count,
        COALESCE(SUM(s.estudiantes_activos), 0) as total_estudiantes_activos
    FROM proyectos_laboratorio p
    LEFT JOIN (
        SELECT proyecto_id, COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as estudiantes_activos
        FROM solicitudes_servicio 
        GROUP BY proyecto_id
    ) s ON p.id = s.proyecto_id
    WHERE p.jefe_departamento_id = :jefe_id
", ['jefe_id' => $jefeId]);

// Obtener total para paginación
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM proyectos_laboratorio p
    WHERE $whereClause
", $params)['total'];

$totalPages = ceil($total / $limit);

$pageTitle = "Gestión de Proyectos - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span class="welcome-text">Gestión de Proyectos</span>
                <span class="welcome-emoji">📋</span>
            </h1>
            <p class="welcome-subtitle">Administra los proyectos de servicio social del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
        </div>
        <div class="header-actions">
            <a href="proyecto-crear.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus"></i>
                <span>Crear Nuevo Proyecto</span>
            </a>
        </div>
    </div>

    <!-- Estadísticas Overview -->
    <div class="statistics-overview">
        <div class="stat-card proyectos">
            <div class="stat-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Total Proyectos</h3>
                <div class="stat-number"><?= $stats['total_proyectos'] ?? 0 ?></div>
                <p class="stat-description">Registrados</p>
                <div class="stat-trend">
                    <i class="fas fa-chart-line"></i>
                    <span>Todos los proyectos</span>
                </div>
            </div>
        </div>

        <div class="stat-card activos">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Proyectos Activos</h3>
                <div class="stat-number"><?= $stats['proyectos_activos'] ?? 0 ?></div>
                <p class="stat-description">En ejecución</p>
                <div class="stat-trend">
                    <i class="fas fa-play-circle"></i>
                    <span>Disponibles</span>
                </div>
            </div>
        </div>

        <div class="stat-card estudiantes">
            <div class="stat-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Estudiantes Activos</h3>
                <div class="stat-number"><?= array_sum(array_column($proyectos, 'estudiantes_activos')) ?></div>
                <p class="stat-description">Participando</p>
                <div class="stat-trend">
                    <i class="fas fa-users"></i>
                    <span>En servicio</span>
                </div>
            </div>
        </div>

        <div class="stat-card solicitudes">
            <div class="stat-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Solicitudes Totales</h3>
                <div class="stat-number"><?= array_sum(array_column($proyectos, 'solicitudes_recibidas')) ?></div>
                <p class="stat-description">Recibidas</p>
                <div class="stat-trend">
                    <i class="fas fa-inbox"></i>
                    <span>Históricas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros Modernos -->
    <div class="filters-section">
        <div class="filters-header">
            <h2 class="filters-title">
                <i class="fas fa-filter"></i>
                Filtrar Proyectos
            </h2>
        </div>
        <div class="filter-tabs">
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                <i class="fas fa-list"></i>
                <span>Todos</span>
                <div class="tab-badge"><?= $stats['total_proyectos'] ?? 0 ?></div>
            </a>
            <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                <i class="fas fa-play-circle"></i>
                <span>Activos</span>
                <div class="tab-badge activos"><?= $stats['proyectos_activos'] ?? 0 ?></div>
            </a>
            <a href="?estado=inactivos" class="filter-tab <?= $estado === 'inactivos' ? 'active' : '' ?>">
                <i class="fas fa-pause-circle"></i>
                <span>Inactivos</span>
                <div class="tab-badge inactivos"><?= ($stats['total_proyectos'] ?? 0) - ($stats['proyectos_activos'] ?? 0) ?></div>
            </a>
        </div>
    </div>

    <!-- Proyectos Grid -->
    <?php if ($proyectos): ?>
        <div class="projects-container">
            <div class="projects-grid">
                <?php foreach ($proyectos as $index => $proyecto): ?>
                <div class="project-card" data-project-id="<?= $proyecto['id'] ?>">
                    <div class="project-header">
                        <div class="project-title-section">
                            <h3 class="project-title"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h3>
                            <div class="project-meta">
                                <span class="meta-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= formatDate($proyecto['created_at']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="project-status">
                            <span class="status-badge <?= $proyecto['activo'] ? 'active' : 'inactive' ?>">
                                <i class="fas fa-<?= $proyecto['activo'] ? 'check-circle' : 'pause-circle' ?>"></i>
                                <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="project-body">
                        <p class="project-description"><?= htmlspecialchars(shortenText($proyecto['descripcion'], 140)) ?></p>
                        
                        <div class="project-details">
                            <div class="detail-row">
                                <div class="detail-item">
                                    <div class="detail-icon laboratorio">
                                        <i class="fas fa-flask"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Laboratorio</span>
                                        <span class="detail-value"><?= htmlspecialchars($proyecto['laboratorio'] ?? 'Sin asignar') ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($proyecto['jefe_lab_nombre']): ?>
                                <div class="detail-item">
                                    <div class="detail-icon supervisor">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Supervisor</span>
                                        <span class="detail-value"><?= htmlspecialchars($proyecto['jefe_lab_nombre']) ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-item">
                                    <div class="detail-icon horas">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Horas</span>
                                        <span class="detail-value"><?= $proyecto['horas_requeridas'] ?> hrs</span>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon cupo">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="detail-content">
                                        <span class="detail-label">Cupo</span>
                                        <span class="detail-value">
                                            <?= $proyecto['cupo_ocupado'] ?>/<?= $proyecto['cupo_disponible'] ?>
                                            <small>(<?= round(($proyecto['cupo_ocupado'] / max(1, $proyecto['cupo_disponible'])) * 100) ?>%)</small>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="project-stats">
                        <div class="stat-item solicitudes">
                            <div class="stat-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number"><?= $proyecto['solicitudes_recibidas'] ?></span>
                                <span class="stat-label">Solicitudes</span>
                            </div>
                        </div>
                        
                        <div class="stat-item estudiantes">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-data">
                                <span class="stat-number"><?= $proyecto['estudiantes_activos'] ?></span>
                                <span class="stat-label">Activos</span>
                            </div>
                        </div>
                        
                        <div class="stat-item progreso">
                            <div class="progress-circle" data-percent="<?= round(($proyecto['cupo_ocupado'] / max(1, $proyecto['cupo_disponible'])) * 100) ?>">
                                <span><?= round(($proyecto['cupo_ocupado'] / max(1, $proyecto['cupo_disponible'])) * 100) ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="project-actions">
                        <div class="actions-primary">
                            <a href="proyecto-detalle.php?id=<?= $proyecto['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i>
                                <span>Ver Detalle</span>
                            </a>
                        </div>
                        <div class="actions-secondary">
                            <a href="proyecto-editar.php?id=<?= $proyecto['id'] ?>" class="btn btn-warning btn-sm" title="Editar proyecto">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if ($proyecto['activo']): ?>
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="toggleProjectStatus(<?= $proyecto['id'] ?>, 'desactivar')"
                                        title="Desactivar proyecto">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm" 
                                        onclick="toggleProjectStatus(<?= $proyecto['id'] ?>, 'activar')"
                                        title="Activar proyecto">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                            
                            <div class="dropdown">
                                <button class="btn btn-ghost btn-sm dropdown-toggle" data-dropdown="project-<?= $proyecto['id'] ?>">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="project-<?= $proyecto['id'] ?>">
                                    <a href="proyecto-duplicar.php?id=<?= $proyecto['id'] ?>" class="dropdown-item">
                                        <i class="fas fa-copy"></i> Duplicar
                                    </a>
                                    <a href="proyecto-reportes.php?id=<?= $proyecto['id'] ?>" class="dropdown-item">
                                        <i class="fas fa-chart-bar"></i> Reportes
                                    </a>
                                    <hr class="dropdown-divider">
                                    <button class="dropdown-item text-danger" onclick="deleteProject(<?= $proyecto['id'] ?>)">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Paginación Moderna -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    <span>Mostrando <?= ($offset + 1) ?> - <?= min($offset + $limit, $total) ?> de <?= $total ?> proyectos</span>
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?estado=<?= $estado ?>&page=1" class="pagination-link first" title="Primera página">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?estado=<?= $estado ?>&page=<?= $page - 1 ?>" class="pagination-link prev" title="Página anterior">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="?estado=<?= $estado ?>&page=1" class="pagination-link">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?estado=<?= $estado ?>&page=<?= $i ?>" 
                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?estado=<?= $estado ?>&page=<?= $totalPages ?>" class="pagination-link"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?estado=<?= $estado ?>&page=<?= $page + 1 ?>" class="pagination-link next" title="Página siguiente">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?estado=<?= $estado ?>&page=<?= $totalPages ?>" class="pagination-link last" title="Última página">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="empty-content">
                <h3>No hay proyectos registrados</h3>
                <p>Crea tu primer proyecto de servicio social para comenzar a recibir solicitudes de estudiantes.</p>
                <div class="empty-actions">
                    <a href="proyecto-crear.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus"></i>
                        <span>Crear Mi Primer Proyecto</span>
                    </a>
                    <a href="../departamento/solicitudes.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Ver Solicitudes</span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Variables CSS - Matching Dashboard */
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

/* Dashboard Container */
.dashboard-container {
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Statistics Overview */
.statistics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--gradient-color), transparent);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.proyectos {
    --gradient-color: #8b5cf6;
}

.stat-card.activos {
    --gradient-color: var(--success);
}

.stat-card.estudiantes {
    --gradient-color: var(--primary);
}

.stat-card.solicitudes {
    --gradient-color: var(--info);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-card.proyectos .stat-icon {
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
}

.stat-card.activos .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.estudiantes .stat-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card.solicitudes .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-content {
    flex: 1;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.75rem 0;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-light);
}

/* Filters Section */
.filters-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.filters-header {
    margin-bottom: 1rem;
}

.filters-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-secondary);
    background: var(--bg-light);
    border: 2px solid transparent;
    transition: var(--transition);
    font-weight: 500;
    position: relative;
}

.filter-tab:hover {
    color: var(--primary);
    background: var(--bg-white);
    border-color: var(--border);
    transform: translateY(-1px);
}

.filter-tab.active {
    color: var(--primary);
    background: var(--bg-white);
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.tab-badge {
    background: var(--text-light);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
}

.tab-badge.activos {
    background: var(--success);
}

.tab-badge.inactivos {
    background: var(--text-light);
}

.filter-tab.active .tab-badge {
    background: var(--primary);
}

/* Projects Container */
.projects-container {
    margin-bottom: 2rem;
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

/* Project Card */
.project-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--border-light);
    animation: fadeInUp 0.6s ease-out;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.project-header {
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.project-title-section {
    flex: 1;
}

.project-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    line-height: 1.3;
}

.project-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-light);
}

.meta-date {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.project-status {
    flex-shrink: 0;
}

.status-badge {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.status-badge.active {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border: 1px solid #10b981;
}

.status-badge.inactive {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: #374151;
    border: 1px solid #9ca3af;
}

.project-body {
    padding: 1.5rem;
}

.project-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0 0 1.5rem 0;
}

.project-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.detail-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.detail-icon.laboratorio {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

.detail-icon.supervisor {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.detail-icon.horas {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.detail-icon.cupo {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.detail-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.125rem;
}

.detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

.detail-value small {
    font-size: 0.75rem;
    color: var(--text-light);
    font-weight: 500;
}

/* Project Stats */
.project-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-item .stat-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary);
    font-size: 0.8rem;
    flex-shrink: 0;
}

.stat-item.solicitudes .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-item.estudiantes .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-data {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Progress Circle */
.progress-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-primary);
    position: relative;
    background: conic-gradient(var(--primary) 0% var(--percentage, 0%), var(--bg-gray) 0% 100%);
}

.progress-circle::before {
    content: '';
    position: absolute;
    inset: 4px;
    border-radius: 50%;
    background: var(--bg-light);
}

.progress-circle span {
    position: relative;
    z-index: 1;
}

/* Project Actions */
.project-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: var(--bg-white);
    border-top: 1px solid var(--border-light);
    gap: 1rem;
}

.actions-primary {
    flex: 1;
}

.actions-secondary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Dropdown */
.dropdown {
    position: relative;
}

.dropdown-toggle {
    background: none !important;
    border: 1px solid var(--border) !important;
    color: var(--text-secondary) !important;
}

.dropdown-toggle:hover {
    border-color: var(--primary) !important;
    color: var(--primary) !important;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--bg-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    min-width: 160px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: var(--transition);
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: var(--bg-light);
    color: var(--text-primary);
}

.dropdown-item.text-danger {
    color: var(--error);
}

.dropdown-item.text-danger:hover {
    background: #fef2f2;
    color: var(--error);
}

.dropdown-divider {
    height: 1px;
    background: var(--border);
    border: none;
    margin: 0.5rem 0;
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

.btn-sm {
    padding: 0.5rem 0.875rem;
    font-size: 0.85rem;
}

.btn-lg {
    padding: 1rem 1.75rem;
    font-size: 1rem;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--text-primary);
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid transparent;
}

.btn-ghost:hover {
    background: var(--bg-light);
    color: var(--text-primary);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border-light);
}

.pagination-info {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.pagination {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-secondary);
    background: var(--bg-white);
    border: 1px solid var(--border);
    font-weight: 500;
    transition: var(--transition);
}

.pagination-link:hover {
    color: var(--primary);
    border-color: var(--primary);
    background: var(--bg-light);
    transform: translateY(-1px);
}

.pagination-link.active {
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.pagination-ellipsis {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    color: var(--text-light);
    font-weight: 700;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.empty-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-light), var(--border-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--text-light);
    margin: 0 auto 2rem;
}

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.empty-content p {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0 0 2rem 0;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
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

@keyframes countUp {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.stat-number {
    animation: countUp 0.8s ease-out;
}

.statistics-overview > * {
    animation: slideIn 0.6s ease-out;
}

.statistics-overview > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-overview > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-overview > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-overview > *:nth-child(4) { animation-delay: 0.4s; }

.project-card:nth-child(1) { animation-delay: 0.1s; }
.project-card:nth-child(2) { animation-delay: 0.2s; }
.project-card:nth-child(3) { animation-delay: 0.3s; }
.project-card:nth-child(4) { animation-delay: 0.4s; }
.project-card:nth-child(5) { animation-delay: 0.5s; }
.project-card:nth-child(6) { animation-delay: 0.6s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .projects-grid {
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    }
    
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 1024px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .welcome-title {
        font-size: 1.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .filters-section {
        padding: 1.25rem;
        margin-bottom: 1.25rem;
    }
    
    .filter-tabs {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .filter-tab {
        justify-content: center;
        padding: 1rem 1.5rem;
    }
    
    .project-card {
        margin-bottom: 1rem;
    }
    
    .project-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.25rem 1.25rem 1rem;
    }
    
    .project-actions {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
        padding: 1.25rem;
    }
    
    .actions-secondary {
        justify-content: center;
        gap: 1rem;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .pagination {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .empty-state {
        padding: 3rem 1.5rem;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .empty-actions .btn {
        width: 100%;
        max-width: 300px;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .project-header,
    .project-body,
    .project-actions {
        padding: 1rem;
    }
    
    .project-stats {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .stat-item {
        justify-content: center;
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .pagination-link {
        min-width: 35px;
        height: 35px;
        font-size: 0.85rem;
    }
    
    .btn-lg {
        padding: 0.875rem 1.5rem;
        font-size: 0.95rem;
    }
    
    .empty-icon {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    :root {
        --border: #000000;
        --text-primary: #000000;
        --bg-white: #ffffff;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent);
        let currentNumber = 0;
        const increment = Math.max(1, Math.floor(finalNumber / 30));
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                numberElement.textContent = Math.min(currentNumber, finalNumber);
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber;
            }
        }
        
        setTimeout(() => {
            animateNumber();
        }, index * 150);
    });
    
    // Progress circles
    const progressCircles = document.querySelectorAll('.progress-circle');
    progressCircles.forEach(circle => {
        const percentage = parseInt(circle.dataset.percent || circle.textContent);
        circle.style.setProperty('--percentage', percentage + '%');
        
        // Animate the circle
        circle.style.background = `conic-gradient(var(--primary) 0% 0%, var(--bg-gray) 0% 100%)`;
        
        setTimeout(() => {
            circle.style.background = `conic-gradient(var(--primary) 0% ${percentage}%, var(--bg-gray) 0% 100%)`;
        }, 500);
    });
    
    // Dropdown functionality
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdownId = this.dataset.dropdown;
            const menu = document.getElementById(dropdownId);
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                if (otherMenu !== menu) {
                    otherMenu.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            menu.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Card hover effects
    const projectCards = document.querySelectorAll('.project-card');
    projectCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Smooth scroll for pagination
    const paginationLinks = document.querySelectorAll('.pagination-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add loading state
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';
        });
    });
    
    // Filter tab animations
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Add loading indicator
            const icon = this.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';
            
            setTimeout(() => {
                icon.className = originalClass;
            }, 1000);
        });
    });
    
    // Intersection Observer for card animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe project cards for staggered animations
    projectCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        cardObserver.observe(card);
    });
    
    // Toast notifications for actions
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'var(--success)' : 'var(--error)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: var(--transition);
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Add CSS for toast
    const toastStyle = document.createElement('style');
    toastStyle.textContent = `
        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
    `;
    document.head.appendChild(toastStyle);
});

// Global functions for project actions
function toggleProjectStatus(projectId, action) {
    const confirmation = action === 'desactivar' 
        ? '¿Estás seguro de que deseas desactivar este proyecto?' 
        : '¿Estás seguro de que deseas activar este proyecto?';
    
    if (confirm(confirmation)) {
        // Add loading state to the card
        const card = document.querySelector(`[data-project-id="${projectId}"]`);
        if (card) {
            card.style.opacity = '0.7';
            card.style.pointerEvents = 'none';
        }
        
        // Simulate API call
        setTimeout(() => {
            window.location.href = `proyecto-${action}.php?id=${projectId}`;
        }, 500);
    }
}

function deleteProject(projectId) {
    if (confirm('⚠️ ¿Estás seguro de que deseas eliminar este proyecto?\n\nEsta acción no se puede deshacer y se perderán todos los datos relacionados.')) {
        const card = document.querySelector(`[data-project-id="${projectId}"]`);
        if (card) {
            card.style.transition = 'all 0.5s ease';
            card.style.transform = 'scale(0.8)';
            card.style.opacity = '0';
            
            setTimeout(() => {
                window.location.href = `proyecto-eliminar.php?id=${projectId}`;
            }, 500);
        }
    }
}
</script>

<?php include '../../includes/footer.php'; ?>