<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

// Obtener estudiantes activos en el laboratorio
$estado = $_GET['estado'] ?? 'activos';
$whereConditions = ["s.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($estado === 'activos') {
    $whereConditions[] = "s.estado = 'en_proceso'";
} elseif ($estado === 'concluidos') {
    $whereConditions[] = "s.estado = 'concluida'";
} elseif ($estado === 'todos') {
    // No additional condition
}

$whereClause = implode(' AND ', $whereConditions);

$estudiantes = $db->fetchAll("
    SELECT e.*, s.id as solicitud_id, s.estado as estado_servicio,
           s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           s.horas_completadas, p.nombre_proyecto
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE $whereClause
    ORDER BY e.nombre
", $params);

// Calcular progreso de horas
foreach ($estudiantes as &$estudiante) {
    $estudiante['horas_requeridas'] = 500; // Valor por defecto
    $estudiante['progreso'] = min(100, ($estudiante['horas_completadas'] / $estudiante['horas_requeridas']) * 100);
}
unset($estudiante);

$pageTitle = "Estudiantes - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Estudiantes del Laboratorio</h1>
        <p>Gestión de estudiantes asignados al laboratorio <?= htmlspecialchars($usuario['laboratorio']) ?></p>
    </div>

    <!-- Filtros -->
    <div class="filters">
        <div class="filter-tabs">
            <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                Activos (<?= count(array_filter($estudiantes, fn($e) => $e['estado_servicio'] === 'en_proceso')) ?>)
            </a>
            <a href="?estado=concluidos" class="filter-tab <?= $estado === 'concluidos' ? 'active' : '' ?>">
                Concluidos (<?= count(array_filter($estudiantes, fn($e) => $e['estado_servicio'] === 'concluida')) ?>)
            </a>
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todos
            </a>
        </div>
    </div>

    <?php if ($estudiantes): ?>
        <div class="students-grid">
            <?php foreach ($estudiantes as $estudiante): ?>
            <div class="student-card">
                <div class="student-header">
                    <h3><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></h3>
                    <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                        <?= getEstadoText($estudiante['estado_servicio']) ?>
                    </span>
                </div>
                
                <div class="student-info">
                    <p><strong>No. Control:</strong> <?= htmlspecialchars($estudiante['numero_control']) ?></p>
                    <p><strong>Carrera:</strong> <?= htmlspecialchars($estudiante['carrera']) ?></p>
                    <p><strong>Proyecto:</strong> <?= htmlspecialchars($estudiante['nombre_proyecto']) ?></p>
                    <p><strong>Periodo:</strong> <?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?></p>
                </div>
                
                <div class="progress-container">
                    <div class="progress-info">
                        <span>Progreso de horas</span>
                        <span><?= $estudiante['horas_completadas'] ?> / <?= $estudiante['horas_requeridas'] ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $estudiante['progreso'] ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?= number_format($estudiante['progreso'], 1) ?>% completado
                    </div>
                </div>
                
                <div class="student-actions">
                    <a href="/modules/laboratorio/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> Ver Detalles
                    </a>
                    <a href="/modules/laboratorio/reportes.php?estudiante_id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-file-alt"></i> Reportes
                    </a>
                    <?php if ($estudiante['estado_servicio'] === 'en_proceso'): ?>
                        <a href="/modules/laboratorio/registrar-horas.php?estudiante_id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-clock"></i> Registrar Horas
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-graduate"></i>
            <p>No hay estudiantes asignados a este laboratorio</p>
            <p>Los estudiantes pueden solicitar realizar su servicio social en este laboratorio</p>
        </div>
    <?php endif; ?>

    <!-- Resumen -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="summary-content">
                <h3>Total Estudiantes</h3>
                <div class="summary-number"><?= count($estudiantes) ?></div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-content">
                <h3>Horas Totales</h3>
                <div class="summary-number"><?= array_sum(array_column($estudiantes, 'horas_completadas')) ?></div>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="summary-content">
                <h3>Promedio de Progreso</h3>
                <div class="summary-number">
                    <?= count($estudiantes) ? number_format(array_sum(array_column($estudiantes, 'progreso')) / count($estudiantes), 1) : 0 ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.student-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
}

.student-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.student-header h3 {
    margin: 0;
    flex: 1;
}

.student-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.student-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.summary-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.summary-icon {
    font-size: 2rem;
    color: var(--primary-color);
}

.summary-content h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
}

.summary-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--secondary-color);
}
</style>

<?php include '../../includes/footer.php'; ?>