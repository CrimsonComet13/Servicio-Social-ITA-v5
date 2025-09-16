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

// Obtener estudiantes
$estudiantes = $db->fetchAll("
    SELECT e.*, s.estado as estado_servicio, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           s.horas_completadas, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
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

// Obtener estadísticas
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as en_proceso,
        COUNT(CASE WHEN s.estado = 'aprobada' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN s.estado = 'concluida' THEN 1 END) as concluidas,
        SUM(s.horas_completadas) as horas_totales
    FROM solicitudes_servicio s
    WHERE s.jefe_departamento_id = :jefe_id
", ['jefe_id' => $jefeId]);

$pageTitle = "Estudiantes - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Estudiantes del Departamento</h1>
        <p>Gestión de estudiantes del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Estudiantes</h3>
            <div class="stat-number"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card">
            <h3>En Proceso</h3>
            <div class="stat-number"><?= $stats['en_proceso'] ?></div>
        </div>
        <div class="stat-card">
            <h3>Aprobadas</h3>
            <div class="stat-number"><?= $stats['aprobadas'] ?></div>
        </div>
        <div class="stat-card">
            <h3>Concluidas</h3>
            <div class="stat-number"><?= $stats['concluidas'] ?></div>
        </div>
        <div class="stat-card">
            <h3>Horas Totales</h3>
            <div class="stat-number"><?= $stats['horas_totales'] ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters">
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
                        <td><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></td>
                        <td><?= htmlspecialchars($estudiante['numero_control']) ?></td>
                        <td><?= htmlspecialchars($estudiante['carrera']) ?></td>
                        <td><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></td>
                        <td><?= htmlspecialchars($estudiante['laboratorio'] ?? 'N/A') ?></td>
                        <td><?= $estudiante['horas_completadas'] ?></td>
                        <td>
                            <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
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
            <p>No hay estudiantes que coincidan con los filtros</p>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}
</style>

<?php include '../../includes/footer.php'; ?>