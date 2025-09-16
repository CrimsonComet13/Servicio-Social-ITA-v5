<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Obtener estadísticas del departamento
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT s.id) as total_solicitudes,
        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN s.id END) as solicitudes_pendientes,
        COUNT(DISTINCT e.id) as total_estudiantes,
        COUNT(DISTINCT jl.id) as total_laboratorios,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as servicios_activos,
        SUM(s.horas_completadas) as horas_totales
    FROM jefes_departamento jd
    LEFT JOIN solicitudes_servicio s ON jd.id = s.jefe_departamento_id
    LEFT JOIN estudiantes e ON s.estudiante_id = e.id
    LEFT JOIN jefes_laboratorio jl ON jd.id = jl.jefe_departamento_id
    WHERE jd.id = :jefe_id
", ['jefe_id' => $jefeId]);

// Obtener solicitudes pendientes recientes
$solicitudesPendientes = $db->fetchAll("
    SELECT s.*, e.nombre as estudiante_nombre, e.numero_control, e.carrera,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND s.estado = 'pendiente'
    ORDER BY s.fecha_solicitud DESC
    LIMIT 5
", ['jefe_id' => $jefeId]);

// Obtener estudiantes activos recientes
$estudiantesActivos = $db->fetchAll("
    SELECT e.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND s.estado = 'en_proceso'
    ORDER BY s.fecha_inicio_propuesta DESC
    LIMIT 5
", ['jefe_id' => $jefeId]);

$pageTitle = "Dashboard Jefe de Departamento - " . APP_NAME;
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?></h1>
        <p>Panel de control del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
    </div>

    <!-- Tarjetas de estadísticas -->
    <div class="status-cards">
        <div class="card">
            <div class="card-header">
                <h3>Solicitudes Totales</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['total_solicitudes'] ?></div>
                <p>Solicitudes recibidas</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Pendientes</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['solicitudes_pendientes'] ?></div>
                <p>Solicitudes por revisar</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Estudiantes Activos</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['servicios_activos'] ?></div>
                <p>Servicios en proceso</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Horas Totales</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['horas_totales'] ?></div>
                <p>Horas cumplidas</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Laboratorios</h3>
            </div>
            <div class="card-body">
                <div class="stat-number"><?= $stats['total_laboratorios'] ?></div>
                <p>Laboratorios registrados</p>
            </div>
        </div>
    </div>

    <!-- Sección de solicitudes pendientes -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Solicitudes Pendientes</h2>
            <a href="/modules/departamento/solicitudes.php" class="btn btn-primary">Ver Todas</a>
        </div>

        <?php if ($solicitudesPendientes): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>No. Control</th>
                            <th>Carrera</th>
                            <th>Proyecto</th>
                            <th>Fecha Solicitud</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudesPendientes as $solicitud): ?>
                        <tr>
                            <td><?= htmlspecialchars($solicitud['estudiante_nombre']) ?></td>
                            <td><?= htmlspecialchars($solicitud['numero_control']) ?></td>
                            <td><?= htmlspecialchars($solicitud['carrera']) ?></td>
                            <td><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></td>
                            <td><?= formatDate($solicitud['fecha_solicitud']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/modules/departamento/solicitud-detalle.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="/modules/departamento/aprobar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Aprobar
                                    </a>
                                    <a href="/modules/departamento/rechazar-solicitud.php?id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-error">
                                        <i class="fas fa-times"></i> Rechazar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>No hay solicitudes pendientes</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección de estudiantes activos -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Estudiantes Activos</h2>
            <a href="/modules/departamento/estudiantes.php" class="btn btn-primary">Ver Todos</a>
        </div>

        <?php if ($estudiantesActivos): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Proyecto</th>
                            <th>Laboratorio</th>
                            <th>Periodo</th>
                            <th>Horas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantesActivos as $estudiante): ?>
                        <tr>
                            <td><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno']) ?></td>
                            <td><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></td>
                            <td><?= htmlspecialchars($estudiante['laboratorio'] ?? 'N/A') ?></td>
                            <td><?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?></td>
                            <td><?= $estudiante['horas_completadas'] ?></td>
                            <td>
                                <a href="/modules/departamento/estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver
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
                <p>No hay estudiantes activos</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Acciones rápidas -->
    <div class="quick-actions">
        <h2>Acciones Rápidas</h2>
        <div class="actions-grid">
            <a href="/modules/departamento/solicitudes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>Gestionar Solicitudes</h3>
                <p>Revisar y aprobar solicitudes de servicio social</p>
            </a>

            <a href="/modules/departamento/estudiantes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3>Ver Estudiantes</h3>
                <p>Gestionar estudiantes del departamento</p>
            </a>

            <a href="/modules/departamento/laboratorios.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3>Gestionar Laboratorios</h3>
                <p>Administrar jefes de laboratorio</p>
            </a>

            <a href="/modules/departamento/reportes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h3>Generar Reportes</h3>
                <p>Crear reportes y estadísticas</p>
            </a>

            <a href="/modules/departamento/configuracion.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Configuración</h3>
                <p>Configurar parámetros del sistema</p>
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>