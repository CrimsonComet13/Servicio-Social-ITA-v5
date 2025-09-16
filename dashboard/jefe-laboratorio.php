<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

// Obtener estadísticas del laboratorio
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT s.id) as total_estudiantes,
        COUNT(DISTINCT r.id) as total_reportes,
        SUM(r.horas_reportadas) as total_horas,
        COUNT(DISTINCT CASE WHEN r.estado = 'pendiente_evaluacion' THEN r.id END) as reportes_pendientes
    FROM jefes_laboratorio jl
    LEFT JOIN solicitudes_servicio s ON jl.id = s.jefe_laboratorio_id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    WHERE jl.id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Obtener estudiantes activos
$estudiantesActivos = $db->fetchAll("
    SELECT e.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto, s.horas_completadas
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.jefe_laboratorio_id = :jefe_id 
    AND s.estado = 'en_proceso'
    ORDER BY e.nombre
    LIMIT 5
", ['jefe_id' => $jefeLabId]);

// Obtener reportes pendientes de evaluación
$reportesPendientes = $db->fetchAll("
    SELECT r.*, e.nombre as estudiante_nombre, e.numero_control,
           p.nombre_proyecto
    FROM reportes_bimestrales r
    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE s.jefe_laboratorio_id = :jefe_id 
    AND r.estado = 'pendiente_evaluacion'
    ORDER BY r.fecha_entrega DESC
    LIMIT 5
", ['jefe_id' => $jefeLabId]);

$pageTitle = "Dashboard Jefe de Laboratorio - " . APP_NAME;
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?></h1>
        <p>Panel de control del laboratorio <?= htmlspecialchars($usuario['laboratorio']) ?></p>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="status-cards">
        <div class="card">
            <div class="card-header">
                <h3>Estudiantes Activos</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['total_estudiantes'] ?></div>
                <p>Estudiantes en el laboratorio</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Total Horas</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['total_horas'] ?? 0 ?></div>
                <p>Horas reportadas</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Reportes</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['total_reportes'] ?></div>
                <p>Reportes bimestrales</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Pendientes</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['reportes_pendientes'] ?></div>
                <p>Reportes por evaluar</p>
            </div>
        </div>
    </div>

    <!-- Sección de estudiantes activos -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Estudiantes Activos</h2>
            <a href="/modules/laboratorio/estudiantes.php" class="btn btn-primary">Ver Todos</a>
        </div>

        <?php if ($estudiantesActivos): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Proyecto</th>
                            <th>Periodo</th>
                            <th>Horas Cumplidas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantesActivos as $estudiante): ?>
                        <tr>
                            <td><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></td>
                            <td><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></td>
                            <td><?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?></td>
                            <td><?= $estudiante['horas_completadas'] ?> horas</td>
                            <td>
                                <a href="/modules/laboratorio/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-info">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <p>No hay estudiantes activos en el laboratorio</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección de reportes pendientes -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Reportes Pendientes de Evaluación</h2>
            <a href="/modules/laboratorio/evaluaciones.php" class="btn btn-primary">Ver Todos</a>
        </div>

        <?php if ($reportesPendientes): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Proyecto</th>
                            <th>Reporte</th>
                            <th>Fecha Entrega</th>
                            <th>Horas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportesPendientes as $reporte): ?>
                        <tr>
                            <td><?= htmlspecialchars($reporte['estudiante_nombre']) ?></td>
                            <td><?= htmlspecialchars($reporte['nombre_proyecto']) ?></td>
                            <td>Reporte <?= $reporte['numero_reporte'] ?></td>
                            <td><?= formatDate($reporte['fecha_entrega']) ?></td>
                            <td><?= $reporte['horas_reportadas'] ?></td>
                            <td>
                                <a href="/modules/laboratorio/evaluar-reporte.php?id=<?= $reporte['id'] ?>" class="btn btn-sm btn-success">
                                    Evaluar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No hay reportes pendientes de evaluación</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Acciones rápidas -->
    <div class="quick-actions">
        <h2>Acciones Rápidas</h2>
        <div class="actions-grid">
            <a href="/modules/laboratorio/estudiantes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Gestionar Estudiantes</h3>
                <p>Ver y gestionar estudiantes del laboratorio</p>
            </a>

            <a href="/modules/laboratorio/evaluaciones.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Evaluar Reportes</h3>
                <p>Revisar y calificar reportes bimestrales</p>
            </a>

            <a href="/modules/laboratorio/reportes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Ver Reportes</h3>
                <p>Generar reportes y estadísticas</p>
            </a>

            <a href="/modules/laboratorio/perfil.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3>Mi Perfil</h3>
                <p>Actualizar información personal</p>
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>