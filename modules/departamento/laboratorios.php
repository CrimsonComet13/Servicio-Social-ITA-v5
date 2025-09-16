<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Procesar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    switch ($action) {
        case 'approve':
            // Aprobar jefe de laboratorio
            $db->update('jefes_laboratorio', [
                'activo' => true
            ], 'id = :id AND jefe_departamento_id = :jefe_id', [
                'id' => $id,
                'jefe_id' => $jefeId
            ]);
            
            // Activar usuario
            $jefeLab = $db->fetch("SELECT usuario_id FROM jefes_laboratorio WHERE id = ?", [$id]);
            if ($jefeLab) {
                $db->update('usuarios', [
                    'activo' => true,
                    'email_verificado' => true
                ], 'id = :id', ['id' => $jefeLab['usuario_id']]);
            }
            
            flashMessage('Jefe de laboratorio aprobado correctamente', 'success');
            break;
            
        case 'reject':
            // Rechazar jefe de laboratorio
            $db->update('jefes_laboratorio', [
                'activo' => false
            ], 'id = :id AND jefe_departamento_id = :jefe_id', [
                'id' => $id,
                'jefe_id' => $jefeId
            ]);
            flashMessage('Jefe de laboratorio rechazado', 'success');
            break;
            
        case 'delete':
            // Eliminar jefe de laboratorio
            $db->delete('jefes_laboratorio', 
                'id = :id AND jefe_departamento_id = :jefe_id', 
                ['id' => $id, 'jefe_id' => $jefeId]
            );
            flashMessage('Jefe de laboratorio eliminado', 'success');
            break;
    }
    
    redirectTo('/modules/departamento/laboratorios.php');
}

// Obtener jefes de laboratorio
$estado = $_GET['estado'] ?? 'activos';
$whereConditions = ["jl.jefe_departamento_id = :jefe_id"];
$params = ['jefe_id' => $jefeId];

if ($estado === 'pendientes') {
    $whereConditions[] = "jl.activo = FALSE";
} elseif ($estado === 'activos') {
    $whereConditions[] = "jl.activo = TRUE";
} elseif ($estado === 'inactivos') {
    $whereConditions[] = "jl.activo = FALSE";
}

$whereClause = implode(' AND ', $whereConditions);

$jefesLaboratorio = $db->fetchAll("
    SELECT jl.*, u.email, u.activo as usuario_activo
    FROM jefes_laboratorio jl
    JOIN usuarios u ON jl.usuario_id = u.id
    WHERE $whereClause
    ORDER BY jl.nombre
", $params);

$pageTitle = "Gestión de Jefes de Laboratorio - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Gestión de Jefes de Laboratorio</h1>
        <p>Administración de jefes de laboratorio del departamento</p>
    </div>

    <!-- Filtros -->
    <div class="filters">
        <div class="filter-tabs">
            <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                Activos (<?= count(array_filter($jefesLaboratorio, fn($j) => $j['activo'])) ?>)
            </a>
            <a href="?estado=pendientes" class="filter-tab <?= $estado === 'pendientes' ? 'active' : '' ?>">
                Pendientes (<?= count(array_filter($jefesLaboratorio, fn($j) => !$j['activo'])) ?>)
            </a>
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todos
            </a>
        </div>
    </div>

    <?php if ($jefesLaboratorio): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Laboratorio</th>
                        <th>Especialidad</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jefesLaboratorio as $jefe): ?>
                    <tr>
                        <td><?= htmlspecialchars($jefe['nombre']) ?></td>
                        <td><?= htmlspecialchars($jefe['email']) ?></td>
                        <td><?= htmlspecialchars($jefe['laboratorio']) ?></td>
                        <td><?= htmlspecialchars($jefe['especialidad'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($jefe['telefono'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge <?= $jefe['activo'] ? 'badge-success' : 'badge-warning' ?>">
                                <?= $jefe['activo'] ? 'Activo' : 'Pendiente' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if (!$jefe['activo']): ?>
                                    <a href="?action=approve&id=<?= $jefe['id'] ?>" class="btn btn-sm btn-success" 
                                       onclick="return confirm('¿Aprobar este jefe de laboratorio?')">Aprobar</a>
                                    <a href="?action=reject&id=<?= $jefe['id'] ?>" class="btn btn-sm btn-error" 
                                       onclick="return confirm('¿Rechazar este jefe de laboratorio?')">Rechazar</a>
                                <?php else: ?>
                                    <a href="/modules/departamento/jefe-editar.php?id=<?= $jefe['id'] ?>" class="btn btn-sm btn-info">Editar</a>
                                    <a href="?action=delete&id=<?= $jefe['id'] ?>" class="btn btn-sm btn-error" 
                                       onclick="return confirm('¿Eliminar este jefe de laboratorio?')">Eliminar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No hay jefes de laboratorio registrados</p>
            <p>Los jefes de laboratorio pueden registrarse desde la página de registro</p>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Jefes</h3>
            <div class="stat-number"><?= count($jefesLaboratorio) ?></div>
        </div>
        <div class="stat-card">
            <h3>Activos</h3>
            <div class="stat-number"><?= count(array_filter($jefesLaboratorio, fn($j) => $j['activo'])) ?></div>
        </div>
        <div class="stat-card">
            <h3>Pendientes</h3>
            <div class="stat-number"><?= count(array_filter($jefesLaboratorio, fn($j) => !$j['activo'])) ?></div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>