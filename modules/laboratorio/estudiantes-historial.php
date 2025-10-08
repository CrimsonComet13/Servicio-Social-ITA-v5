<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// ✅ SOLUCIÓN CORRECTA con parámetros nombrados
$jefeLab = $db->fetch("
    SELECT jl.id, jl.nombre, jl.laboratorio, jl.especialidad, jl.telefono, jl.extension
    FROM jefes_laboratorio jl
    WHERE jl.usuario_id = :usuario_id
    AND jl.activo = 1
", ['usuario_id' => $usuarioId]);

if (!$jefeLab) {
    flashMessage('Error: No se encontró tu perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
    exit;
}

$jefeLabId = $jefeLab['id']; // Será 1
$nombreLaboratorio = $jefeLab['laboratorio']; // Será "Laboratorio de Redes"

// 🎯 A partir de aquí usa $jefeLabId en todas las consultas
// Procesar filtros
$filtroEstado = $_GET['estado'] ?? 'todos';
$filtroAnio = $_GET['anio'] ?? 'todos';
$filtroCarrera = $_GET['carrera'] ?? 'todos';
$filtroProyecto = $_GET['proyecto'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir la consulta base con filtros
$whereConditions = ["s.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($filtroEstado !== 'todos') {
    $whereConditions[] = "s.estado = :estado";
    $params['estado'] = $filtroEstado;
}

if ($filtroAnio !== 'todos') {
    $whereConditions[] = "YEAR(s.fecha_inicio_propuesta) = :anio";
    $params['anio'] = $filtroAnio;
}

if ($filtroCarrera !== 'todos') {
    $whereConditions[] = "e.carrera = :carrera";
    $params['carrera'] = $filtroCarrera;
}

if ($filtroProyecto !== 'todos') {
    $whereConditions[] = "p.id = :proyecto_id";
    $params['proyecto_id'] = $filtroProyecto;
}

if (!empty($busqueda)) {
    $whereConditions[] = "(e.nombre LIKE :busqueda OR e.apellido_paterno LIKE :busqueda OR e.numero_control LIKE :busqueda)";
    $params['busqueda'] = '%' . $busqueda . '%';
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas históricas
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT e.id) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN s.estado = 'concluida' THEN e.id END) as completados,
        COUNT(DISTINCT CASE WHEN s.estado = 'cancelada' THEN e.id END) as cancelados,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN e.id END) as activos,
        COALESCE(AVG(CASE WHEN s.estado = 'concluida' THEN e.horas_completadas END), 0) as promedio_horas_completados,
        COALESCE(SUM(e.horas_completadas), 0) as total_horas
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    WHERE s.jefe_laboratorio_id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Obtener historial de estudiantes con filtros
$estudiantes = $db->fetchAll("
    SELECT 
        e.*,
        s.estado as estado_servicio,
        s.fecha_inicio_propuesta,
        s.fecha_fin_propuesta,
        s.fecha_solicitud,
        s.id as solicitud_id,
        p.nombre_proyecto,
        p.id as proyecto_id,
        COUNT(DISTINCT r.id) as total_reportes,
        COUNT(DISTINCT CASE WHEN r.estado = 'aprobado' THEN r.id END) as reportes_aprobados,
        MAX(r.fecha_entrega) as ultimo_reporte,
        AVG(CASE WHEN r.calificacion IS NOT NULL THEN r.calificacion END) as promedio_calificacion
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    WHERE $whereClause
    GROUP BY e.id, s.id
    ORDER BY 
        CASE s.estado 
            WHEN 'en_proceso' THEN 1
            WHEN 'concluida' THEN 2
            WHEN 'cancelada' THEN 3
            ELSE 4
        END,
        s.fecha_inicio_propuesta DESC
", $params);

// Obtener lista de años para el filtro
$anios = $db->fetchAll("
    SELECT DISTINCT YEAR(s.fecha_inicio_propuesta) as anio
    FROM solicitudes_servicio s
    WHERE s.jefe_laboratorio_id = :jefe_id
    ORDER BY anio DESC
", ['jefe_id' => $jefeLabId]);

// Obtener lista de proyectos para el filtro
$proyectos = $db->fetchAll("
    SELECT DISTINCT p.id, p.nombre_proyecto
    FROM proyectos_laboratorio p
    JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    WHERE s.jefe_laboratorio_id = :jefe_id
    ORDER BY p.nombre_proyecto
", ['jefe_id' => $jefeLabId]);

// Obtener lista de carreras para el filtro
$carreras = $db->fetchAll("
    SELECT DISTINCT e.carrera
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    WHERE s.jefe_laboratorio_id = :jefe_id
    ORDER BY e.carrera
", ['jefe_id' => $jefeLabId]);

$pageTitle = "Historial de Estudiantes - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-text">
                    <h1 class="page-title">
                        <i class="fas fa-history"></i>
                        Historial de Estudiantes
                    </h1>
                    <p class="page-subtitle">Historial completo de estudiantes del laboratorio <?= htmlspecialchars($nombreLaboratorio) ?></p>
                </div>
                <div class="header-actions">
                    <a href="../../dashboard/jefe_laboratorio.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                    <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-download"></i>
                        Exportar Historial
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Histórico</h3>
                    <div class="stat-number"><?= $stats['total_estudiantes'] ?></div>
                    <p class="stat-description">Estudiantes totales</p>
                </div>
            </div>

            <div class="stat-card completados">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Completados</h3>
                    <div class="stat-number"><?= $stats['completados'] ?></div>
                    <p class="stat-description">Servicio finalizado</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $stats['total_estudiantes'] > 0 ? round(($stats['completados'] / $stats['total_estudiantes']) * 100) : 0 ?>% de éxito</span>
                    </div>
                </div>
            </div>

            <div class="stat-card activos">
                <div class="stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Activos</h3>
                    <div class="stat-number"><?= $stats['activos'] ?></div>
                    <p class="stat-description">En proceso</p>
                    <div class="stat-trend">
                        <i class="fas fa-user-clock"></i>
                        <span>Actualmente</span>
                    </div>
                </div>
            </div>

            <div class="stat-card horas">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Promedio Horas</h3>
                    <div class="stat-number"><?= round($stats['promedio_horas_completados']) ?></div>
                    <p class="stat-description">Horas por estudiante</p>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span><?= number_format($stats['total_horas']) ?> hrs totales</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros de Búsqueda
                </h2>
                <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarFiltros()">
                    <i class="fas fa-redo"></i>
                    Limpiar Filtros
                </button>
            </div>
            
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="busqueda">
                            <i class="fas fa-search"></i>
                            Buscar Estudiante
                        </label>
                        <input type="text" 
                               id="busqueda" 
                               name="busqueda" 
                               value="<?= htmlspecialchars($busqueda) ?>"
                               placeholder="Nombre, apellido o número de control..."
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">
                            <i class="fas fa-flag"></i>
                            Estado
                        </label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                            <option value="en_proceso" <?= $filtroEstado === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                            <option value="concluida" <?= $filtroEstado === 'concluida' ? 'selected' : '' ?>>Concluido</option>
                            <option value="cancelada" <?= $filtroEstado === 'cancelada' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="anio">
                            <i class="fas fa-calendar"></i>
                            Año
                        </label>
                        <select id="anio" name="anio" class="form-control">
                            <option value="todos">Todos los años</option>
                            <?php foreach ($anios as $anio): ?>
                            <option value="<?= $anio['anio'] ?>" <?= $filtroAnio == $anio['anio'] ? 'selected' : '' ?>>
                                <?= $anio['anio'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="proyecto">
                            <i class="fas fa-project-diagram"></i>
                            Proyecto
                        </label>
                        <select id="proyecto" name="proyecto" class="form-control">
                            <option value="todos">Todos los proyectos</option>
                            <?php foreach ($proyectos as $proyecto): ?>
                            <option value="<?= $proyecto['id'] ?>" <?= $filtroProyecto == $proyecto['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proyecto['nombre_proyecto']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="carrera">
                            <i class="fas fa-graduation-cap"></i>
                            Carrera
                        </label>
                        <select id="carrera" name="carrera" class="form-control">
                            <option value="todos">Todas las carreras</option>
                            <?php foreach ($carreras as $carrera): ?>
                            <option value="<?= htmlspecialchars($carrera['carrera']) ?>" <?= $filtroCarrera === $carrera['carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($carrera['carrera']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Timeline Section -->
        <div class="timeline-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-timeline"></i>
                    Línea de Tiempo
                    <span class="count-badge"><?= count($estudiantes) ?></span>
                </h2>
                <div class="view-options">
                    <span class="view-label">Ordenar por:</span>
                    <select id="sortOrder" class="form-control-inline" onchange="ordenarResultados(this.value)">
                        <option value="reciente">Más reciente</option>
                        <option value="antiguo">Más antiguo</option>
                        <option value="nombre">Nombre (A-Z)</option>
                        <option value="horas">Más horas</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($estudiantes)): ?>
                <div class="timeline-container" id="timelineContainer">
                    <?php 
                    $currentYear = null;
                    foreach ($estudiantes as $estudiante): 
                        $year = date('Y', strtotime($estudiante['fecha_inicio_propuesta']));
                        if ($currentYear !== $year && $filtroAnio === 'todos'):
                            $currentYear = $year;
                    ?>
                        <div class="year-divider">
                            <span class="year-label"><?= $currentYear ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="timeline-item" data-year="<?= $year ?>" data-horas="<?= $estudiante['horas_completadas'] ?>" data-nombre="<?= htmlspecialchars($estudiante['nombre']) ?>">
                        <div class="timeline-marker <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                            <i class="fas <?= getEstadoIcon($estudiante['estado_servicio']) ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="student-card-history">
                                <div class="student-header-history">
                                    <div class="student-info-main">
                                        <div class="student-avatar">
                                            <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                                        </div>
                                        <div class="student-details">
                                            <h3><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h3>
                                            <p class="student-meta">
                                                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($estudiante['numero_control']) ?></span>
                                                <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($estudiante['carrera']) ?></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="student-status-history">
                                        <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                                            <?= getEstadoText($estudiante['estado_servicio']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="student-body-history">
                                    <div class="info-grid">
                                        <div class="info-item-small">
                                            <i class="fas fa-project-diagram"></i>
                                            <div>
                                                <label>Proyecto</label>
                                                <span><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></span>
                                            </div>
                                        </div>
                                        <div class="info-item-small">
                                            <i class="fas fa-calendar-alt"></i>
                                            <div>
                                                <label>Período</label>
                                                <span><?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - <?= formatDate($estudiante['fecha_fin_propuesta']) ?></span>
                                            </div>
                                        </div>
                                        <div class="info-item-small">
                                            <i class="fas fa-clock"></i>
                                            <div>
                                                <label>Horas Completadas</label>
                                                <span class="hours-highlight"><?= $estudiante['horas_completadas'] ?? 0 ?> hrs</span>
                                            </div>
                                        </div>
                                        <div class="info-item-small">
                                            <i class="fas fa-file-alt"></i>
                                            <div>
                                                <label>Reportes</label>
                                                <span><?= $estudiante['reportes_aprobados'] ?>/<?= $estudiante['total_reportes'] ?> aprobados</span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($estudiante['promedio_calificacion']): ?>
                                    <div class="calificacion-badge">
                                        <i class="fas fa-star"></i>
                                        <span>Calificación Promedio: <strong><?= number_format($estudiante['promedio_calificacion'], 1) ?></strong></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="progress-section">
                                        <div class="progress-bar-history">
                                            <div class="progress-fill" style="width: <?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>% completado</span>
                                    </div>
                                </div>

                                <div class="student-actions-history">
                                    <a href="estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Ver Perfil Completo
                                    </a>
                                    <a href="reportes-estudiante.php?id=<?= $estudiante['id'] ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-file-alt"></i> Ver Reportes
                                    </a>
                                    <?php if ($estudiante['estado_servicio'] === 'concluida'): ?>
                                    <button class="btn btn-success btn-sm" onclick="generarConstancia(<?= $estudiante['solicitud_id'] ?>)">
                                        <i class="fas fa-certificate"></i> Constancia
                                    </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($estudiante['ultimo_reporte']): ?>
                                <div class="last-activity">
                                    <i class="fas fa-clock"></i>
                                    <span>Última actividad: <?= formatDate($estudiante['ultimo_reporte']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>No hay registros en el historial</h3>
                    <p>No se encontraron estudiantes que coincidan con los filtros seleccionados.</p>
                    <?php if ($filtroEstado !== 'todos' || $filtroAnio !== 'todos' || $filtroCarrera !== 'todos' || $filtroProyecto !== 'todos' || !empty($busqueda)): ?>
                    <button class="btn btn-primary" onclick="limpiarFiltros()">
                        <i class="fas fa-redo"></i>
                        Limpiar Filtros
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
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

/* Variables CSS */
:root {
    --primary: #4caf50;
    --primary-light: #66bb6a;
    --secondary: #2196f3;
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
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
}

.header-text {
    flex: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.page-title i {
    color: var(--primary);
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-shrink: 0;
}

/* Statistics Overview */
.statistics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
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

.stat-card.total { --gradient-color: var(--info); }
.stat-card.completados { --gradient-color: var(--success); }
.stat-card.activos { --gradient-color: var(--primary); }
.stat-card.horas { --gradient-color: var(--warning); }

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

.stat-card.total .stat-icon { background: linear-gradient(135deg, var(--info), #60a5fa); }
.stat-card.completados .stat-icon { background: linear-gradient(135deg, var(--success), #34d399); }
.stat-card.activos .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card.horas .stat-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }

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
    color: var(--success);
}

/* Filters Section */
.filters-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
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

.filter-form {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr repeat(4, 1fr) auto;
    gap: 1.5rem;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.form-control-inline {
    padding: 0.5rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: var(--bg-white);
    cursor: pointer;
}

/* Timeline Section */
.timeline-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.count-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.9rem;
    margin-left: 0.5rem;
}

.view-options {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.view-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Timeline Container */
.timeline-container {
    position: relative;
    padding-left: 2rem;
}

.timeline-container::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--primary), var(--primary-light), var(--border));
}

.year-divider {
    position: relative;
    margin: 2rem 0;
    text-align: center;
}

.year-label {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 2rem;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: var(--shadow-lg);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
    padding-left: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 1.5rem;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    box-shadow: var(--shadow);
    z-index: 1;
}

.timeline-marker.badge-success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.timeline-marker.badge-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.timeline-marker.badge-error {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.timeline-content {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: var(--transition);
    border: 2px solid transparent;
}

.timeline-item:hover .timeline-content {
    transform: translateX(5px);
    background: var(--bg-white);
    border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}

/* Student Card History */
.student-card-history {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.student-header-history {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.student-info-main {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
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
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.student-details h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.student-status-history {
    flex-shrink: 0;
}

.student-body-history {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item-small {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.info-item-small i {
    width: 20px;
    color: var(--primary);
    font-size: 1rem;
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.info-item-small label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.info-item-small span {
    font-size: 0.9rem;
    color: var(--text-primary);
}

.hours-highlight {
    font-weight: 700 !important;
    color: var(--primary) !important;
}

.calificacion-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(102, 187, 106, 0.1));
    border-left: 4px solid var(--primary);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-primary);
}

.calificacion-badge i {
    color: var(--warning);
    font-size: 1rem;
}

.progress-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.progress-bar-history {
    height: 10px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 1rem;
    transition: width 1s ease;
}

.progress-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-align: right;
}

.student-actions-history {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.last-activity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-primary { background: rgba(76, 175, 80, 0.1); color: var(--primary); }
.badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.badge-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--text-light);
    margin: 0 auto 1.5rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    line-height: 1.6;
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
    font-size: 0.8rem;
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
    border-color: var(--primary);
    color: var(--primary);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animations */
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

.timeline-item {
    animation: slideIn 0.6s ease-out;
}

.timeline-item:nth-child(1) { animation-delay: 0.1s; }
.timeline-item:nth-child(2) { animation-delay: 0.2s; }
.timeline-item:nth-child(3) { animation-delay: 0.3s; }
.timeline-item:nth-child(4) { animation-delay: 0.4s; }
.timeline-item:nth-child(5) { animation-delay: 0.5s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    
    .filter-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1024px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline-container {
        padding-left: 1.5rem;
    }
    
    .timeline-container::before {
        left: 10px;
    }
    
    .timeline-marker {
        left: -1.5rem;
        width: 25px;
        height: 25px;
        font-size: 0.75rem;
    }
    
    .student-header-history {
        flex-direction: column;
    }
    
    .student-actions-history {
        flex-direction: column;
    }
    
    .student-actions-history .btn {
        width: 100%;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .timeline-content {
        padding: 1rem;
    }
    
    .student-info-main {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent.replace(/,/g, ''));
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                numberElement.textContent = Math.floor(Math.min(currentNumber, finalNumber)).toLocaleString();
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber.toLocaleString();
            }
        }
        
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Animate progress bars
    setTimeout(() => {
        const progressFills = document.querySelectorAll('.progress-fill');
        progressFills.forEach((fill, index) => {
            const width = fill.style.width;
            fill.style.width = '0%';
            
            setTimeout(() => {
                fill.style.width = width;
            }, index * 100);
        });
    }, 500);
});

function limpiarFiltros() {
    window.location.href = 'estudiantes-historial.php';
}

function ordenarResultados(orden) {
    const container = document.getElementById('timelineContainer');
    const items = Array.from(container.querySelectorAll('.timeline-item'));
    
    items.sort((a, b) => {
        switch(orden) {
            case 'reciente':
                return parseInt(b.dataset.year) - parseInt(a.dataset.year);
            case 'antiguo':
                return parseInt(a.dataset.year) - parseInt(b.dataset.year);
            case 'nombre':
                return a.dataset.nombre.localeCompare(b.dataset.nombre);
            case 'horas':
                return parseInt(b.dataset.horas) - parseInt(a.dataset.horas);
            default:
                return 0;
        }
    });
    
    // Reorder items
    items.forEach(item => container.appendChild(item));
}

function exportToExcel() {
    const items = document.querySelectorAll('.timeline-item');
    let csv = [];
    
    // Headers
    csv.push(['Nombre', 'No. Control', 'Carrera', 'Proyecto', 'Fecha Inicio', 'Fecha Fin', 'Horas', 'Reportes Aprobados', 'Total Reportes', 'Calificación Promedio', 'Estado'].join(','));
    
    // Data rows
    items.forEach(item => {
        const nombre = item.querySelector('.student-details h3').textContent.trim();
        const metaSpans = item.querySelectorAll('.student-meta span');
        const control = metaSpans[0].textContent.trim().replace(/.*\s/, '');
        const carrera = metaSpans[1].textContent.trim().replace(/.*\s/, '');
        
        const infoItems = item.querySelectorAll('.info-item-small span:not(label)');
        const proyecto = infoItems[0].textContent.trim();
        const periodo = infoItems[1].textContent.trim();
        const [fechaInicio, fechaFin] = periodo.split(' - ');
        const horas = infoItems[2].textContent.trim().replace(' hrs', '');
        const reportes = infoItems[3].textContent.trim();
        const [aprobados, total] = reportes.split('/');
        
        const calificacionBadge = item.querySelector('.calificacion-badge strong');
        const calificacion = calificacionBadge ? calificacionBadge.textContent.trim() : 'N/A';
        
        const estado = item.querySelector('.badge').textContent.trim();
        
        csv.push([nombre, control, carrera, proyecto, fechaInicio, fechaFin, horas, aprobados, total, calificacion, estado].map(v => `"${v}"`).join(','));
    });
    
    // Create and download file
    const csvContent = "\uFEFF" + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `historial_estudiantes_laboratorio_<?= date('Y-m-d') ?>.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function generarConstancia(solicitudId) {
    alert('Generando constancia de servicio social para la solicitud #' + solicitudId + '\n\nEsta funcionalidad generará un documento PDF con la constancia oficial.');
    // En producción, esto redirigiría a un endpoint que genere el PDF
    // window.open('/api/constancia.php?solicitud=' + solicitudId, '_blank');
}
</script>

<?php 
// Funciones auxiliares para los iconos de estado
function getEstadoIcon($estado) {
    $icons = [
        'en_proceso' => 'fa-spinner',
        'concluida' => 'fa-check',
        'cancelada' => 'fa-times',
        'pendiente' => 'fa-clock'
    ];
    return $icons[$estado] ?? 'fa-question';
}

include '../../includes/footer.php'; 
?>