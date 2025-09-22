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
           COUNT(s.id) as solicitudes_recibidas,
           COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as estudiantes_activos
    FROM proyectos_laboratorio p
    LEFT JOIN jefes_laboratorio jl ON p.jefe_laboratorio_id = jl.id
    LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    WHERE $whereClause
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

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

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Gestión de Proyectos</h1>
        <p>Administra los proyectos de servicio social del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
        <div class="header-actions">
            <a href="/servicio_social_ita/modules/departamento/proyecto-crear.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crear Nuevo Proyecto
            </a>
        </div>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-info">
                <h3><?= $total ?></h3>
                <p>Total de Proyectos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= count(array_filter($proyectos, fn($p) => $p['activo'])) ?></h3>
                <p>Proyectos Activos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($proyectos, 'estudiantes_activos')) ?></h3>
                <p>Estudiantes Activos</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters">
        <div class="filter-tabs">
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todos (<?= $total ?>)
            </a>
            <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                Activos
            </a>
            <a href="?estado=inactivos" class="filter-tab <?= $estado === 'inactivos' ? 'active' : '' ?>">
                Inactivos
            </a>
        </div>
    </div>

    <?php if ($proyectos): ?>
        <div class="projects-grid">
            <?php foreach ($proyectos as $proyecto): ?>
            <div class="project-card">
                <div class="project-header">
                    <h3><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h3>
                    <div class="project-status">
                        <span class="badge <?= $proyecto['activo'] ? 'badge-success' : 'badge-secondary' ?>">
                            <?= $proyecto['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                </div>
                
                <div class="project-info">
                    <p class="project-description"><?= htmlspecialchars(shortenText($proyecto['descripcion'], 120)) ?></p>
                    
                    <div class="project-details">
                        <div class="detail-item">
                            <i class="fas fa-flask"></i>
                            <span><?= htmlspecialchars($proyecto['laboratorio'] ?? 'Sin asignar') ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?= $proyecto['horas_requeridas'] ?> horas</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-users"></i>
                            <span><?= $proyecto['cupo_ocupado'] ?>/<?= $proyecto['cupo_disponible'] ?> estudiantes</span>
                        </div>
                    </div>
                </div>
                
                <div class="project-stats">
                    <div class="stat">
                        <span class="stat-number"><?= $proyecto['solicitudes_recibidas'] ?></span>
                        <span class="stat-label">Solicitudes</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number"><?= $proyecto['estudiantes_activos'] ?></span>
                        <span class="stat-label">Activos</span>
                    </div>
                </div>
                
                <div class="project-actions">
                    <a href="/servicio_social_ita/modules/departamento/proyecto-detalle.php?id=<?= $proyecto['id'] ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                    <a href="/servicio_social_ita/modules/departamento/proyecto-editar.php?id=<?= $proyecto['id'] ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <?php if ($proyecto['activo']): ?>
                        <a href="/servicio_social_ita/modules/departamento/proyecto-desactivar.php?id=<?= $proyecto['id'] ?>" class="btn btn-sm btn-secondary" onclick="return confirm('¿Desactivar este proyecto?')">
                            <i class="fas fa-pause"></i> Desactivar
                        </a>
                    <?php else: ?>
                        <a href="/servicio_social_ita/modules/departamento/proyecto-activar.php?id=<?= $proyecto['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-play"></i> Activar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
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
            <i class="fas fa-project-diagram"></i>
            <h3>No hay proyectos registrados</h3>
            <p>Crea tu primer proyecto de servicio social</p>
            <a href="/servicio_social_ita/modules/departamento/proyecto-crear.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crear Proyecto
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>