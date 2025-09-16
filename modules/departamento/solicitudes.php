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
    SELECT s.*, e.nombre as estudiante_nombre, e.numero_control, e.carrera,
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

$pageTitle = "Solicitudes de Servicio Social - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Solicitudes de Servicio Social</h1>
        <p>Gestión de solicitudes del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="filters">
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
                        <td><?= htmlspecialchars($solicitud['estudiante_nombre']) ?></td>
                        <td><?= htmlspecialchars($solicitud['numero_control']) ?></td>
                        <td><?= htmlspecialchars($solicitud['carrera']) ?></td>
                        <td><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></td>
                        <td><?= htmlspecialchars($solicitud['laboratorio'] ?? 'N/A') ?></td>
                        <td><?= formatDate($solicitud['fecha_solicitud']) ?></td>
                        <td>
                            <span class="badge <?= getEstadoBadgeClass($solicitud['estado']) ?>">
                                <?= getEstadoText($solicitud['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="/modules/departamento/solicitud-detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                
                                <?php if ($solicitud['estado'] === 'pendiente'): ?>
                                    <a href="/modules/departamento/aprobar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Aprobar
                                    </a>
                                    <a href="/modules/departamento/rechazar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-error">
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
            <p>No hay solicitudes que coincidan con los filtros</p>
        </div>
    <?php endif; ?>
</div>

<style>
.filters {
    margin-bottom: 2rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s;
}

.filter-tab:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.filter-tab.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-link {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s;
}

.pagination-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.pagination-link.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}
</style>

<?php include '../../includes/footer.php'; ?>