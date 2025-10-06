<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// ✅ OBTENER CORRECTAMENTE EL ID DEL JEFE DE LABORATORIO
$jefeLab = $db->fetch("
    SELECT jl.*, u.email 
    FROM jefes_laboratorio jl
    JOIN usuarios u ON jl.usuario_id = u.id
    WHERE jl.usuario_id = ?
", [$usuarioId]);

if (!$jefeLab) {
    flashMessage('Error: Perfil de jefe de laboratorio no encontrado', 'error');
    redirectTo('/dashboard/laboratorio.php');
}

$jefeLabId = $jefeLab['id']; // Este es el ID correcto de la tabla jefes_laboratorio

// Obtener estudiantes activos en el laboratorio
$estado = $_GET['estado'] ?? 'activos';
$whereConditions = ["s.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($estado === 'activos') {
    $whereConditions[] = "s.estado = 'en_proceso'";
} elseif ($estado === 'concluidos') {
    $whereConditions[] = "s.estado = 'concluida'";
} elseif ($estado === 'pendientes') {
    $whereConditions[] = "s.estado = 'aprobada'";
}

$whereClause = implode(' AND ', $whereConditions);

$estudiantes = $db->fetchAll("
    SELECT e.*, 
           u.email as estudiante_email,
           s.id as solicitud_id, 
           s.estado as estado_servicio,
           s.fecha_solicitud,
           s.fecha_inicio_propuesta, 
           s.fecha_fin_propuesta,
           s.fecha_aprobacion,
           p.nombre_proyecto,
           p.horas_requeridas,
           jd.nombre as jefe_depto_nombre,
           jd.departamento,
           -- Calcular horas completadas desde reportes aprobados
           COALESCE((
               SELECT SUM(r.horas_reportadas)
               FROM reportes_bimestrales r
               WHERE r.estudiante_id = e.id 
               AND r.solicitud_id = s.id
               AND r.estado = 'aprobado'
           ), 0) as horas_completadas,
           -- Contar reportes entregados
           (
               SELECT COUNT(*)
               FROM reportes_bimestrales r
               WHERE r.estudiante_id = e.id 
               AND r.solicitud_id = s.id
           ) as reportes_entregados,
           -- Contar reportes pendientes de evaluación
           (
               SELECT COUNT(*)
               FROM reportes_bimestrales r
               WHERE r.estudiante_id = e.id 
               AND r.solicitud_id = s.id
               AND r.estado = 'pendiente_evaluacion'
           ) as reportes_pendientes
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE $whereClause
    ORDER BY e.nombre, e.apellido_paterno
", $params);

// Calcular progreso de horas
foreach ($estudiantes as &$estudiante) {
    $estudiante['progreso'] = min(100, ($estudiante['horas_completadas'] / $estudiante['horas_requeridas']) * 100);
}
unset($estudiante);

// Calcular estadísticas
$totalEstudiantes = count($estudiantes);
$estudiantesActivos = count(array_filter($estudiantes, fn($e) => $e['estado_servicio'] === 'en_proceso'));
$estudiantesConcluidos = count(array_filter($estudiantes, fn($e) => $e['estado_servicio'] === 'concluida'));
$horasTotales = array_sum(array_column($estudiantes, 'horas_completadas'));
$progresoPromedio = $totalEstudiantes > 0 ? array_sum(array_column($estudiantes, 'progreso')) / $totalEstudiantes : 0;

$pageTitle = "Estudiantes - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="header-info">
                    <h1>Estudiantes del Laboratorio</h1>
                    <p>Gestión de estudiantes asignados al laboratorio <?= htmlspecialchars($jefeLab['laboratorio']) ?></p>
                </div>
            </div>
        </div>

        <!-- Estadísticas Resumen -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Estudiantes</h3>
                    <span class="stat-number"><?= $totalEstudiantes ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Activos</h3>
                    <span class="stat-number"><?= $estudiantesActivos ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3>Concluidos</h3>
                    <span class="stat-number"><?= $estudiantesConcluidos ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Horas Totales</h3>
                    <span class="stat-number"><?= $horasTotales ?></span>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <div class="filter-tabs">
                <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                    <i class="fas fa-play-circle"></i>
                    Activos (<?= $estudiantesActivos ?>)
                </a>
                <a href="?estado=pendientes" class="filter-tab <?= $estado === 'pendientes' ? 'active' : '' ?>">
                    <i class="fas fa-hourglass-half"></i>
                    Por Iniciar
                </a>
                <a href="?estado=concluidos" class="filter-tab <?= $estado === 'concluidos' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    Concluidos (<?= $estudiantesConcluidos ?>)
                </a>
                <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i>
                    Todos
                </a>
            </div>
        </div>

        <?php if ($estudiantes): ?>
            <div class="students-grid">
                <?php foreach ($estudiantes as $estudiante): ?>
                <div class="student-card">
                    <div class="student-header">
                        <div class="student-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="student-title">
                            <h3><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h3>
                            <span class="student-control"><?= htmlspecialchars($estudiante['numero_control']) ?></span>
                        </div>
                        <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                            <?= getEstadoText($estudiante['estado_servicio']) ?>
                        </span>
                    </div>
                    
                    <div class="student-info">
                        <div class="info-row">
                            <i class="fas fa-graduation-cap"></i>
                            <span><strong>Carrera:</strong> <?= htmlspecialchars($estudiante['carrera']) ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-layer-group"></i>
                            <span><strong>Semestre:</strong> <?= htmlspecialchars($estudiante['semestre']) ?>°</span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-project-diagram"></i>
                            <span><strong>Proyecto:</strong> <?= htmlspecialchars($estudiante['nombre_proyecto']) ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-building"></i>
                            <span><strong>Departamento:</strong> <?= htmlspecialchars($estudiante['departamento']) ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span><strong>Periodo:</strong> <?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-file-alt"></i>
                            <span><strong>Reportes:</strong> <?= $estudiante['reportes_entregados'] ?> entregados
                                <?php if ($estudiante['reportes_pendientes'] > 0): ?>
                                    <span class="badge-mini warning"><?= $estudiante['reportes_pendientes'] ?> pendientes</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-info">
                            <span class="progress-label">Progreso de horas</span>
                            <span class="progress-value"><?= $estudiante['horas_completadas'] ?> / <?= $estudiante['horas_requeridas'] ?> hrs</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $estudiante['progreso'] ?>%"></div>
                        </div>
                        <div class="progress-text">
                            <?= number_format($estudiante['progreso'], 1) ?>% completado
                        </div>
                    </div>
                    
                    <div class="student-actions">
                        <a href="estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Ver Detalles
                        </a>
                        <?php if ($estudiante['reportes_pendientes'] > 0): ?>
                            <a href="evaluaciones.php?estudiante_id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-exclamation-circle"></i> Evaluar (<?= $estudiante['reportes_pendientes'] ?>)
                            </a>
                        <?php endif; ?>
                        <?php if ($estudiante['estado_servicio'] === 'en_proceso'): ?>
                            <a href="reportes.php?estudiante_id=<?= $estudiante['id'] ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-file-alt"></i> Ver Reportes
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="empty-content">
                    <h3>No hay estudiantes en esta categoría</h3>
                    <p>Los estudiantes asignados a tu laboratorio aparecerán aquí cuando se aprueben sus solicitudes.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Reutilizar estilos de actividades.php y ajustar */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --border: #e5e7eb;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    --radius: 0.5rem;
}

.dashboard-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.student-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    transition: transform 0.3s ease;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.student-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.student-title {
    flex: 1;
}

.student-title h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.student-control {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.student-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.info-row {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.info-row i {
    color: var(--text-secondary);
    width: 16px;
    margin-top: 0.125rem;
}

.badge-mini {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.badge-mini.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.progress-container {
    margin-bottom: 1.5rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.progress-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-value {
    color: var(--text-primary);
    font-weight: 600;
}

.progress-bar {
    height: 8px;
    background: var(--bg-light);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #34d399);
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.student-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-content h3 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.empty-content p {
    color: var(--text-secondary);
}
</style>

<?php include '../../includes/footer.php'; ?>