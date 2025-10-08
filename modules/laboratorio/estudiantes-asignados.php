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
$filtroProyecto = $_GET['proyecto'] ?? 'todos';
$filtroCarrera = $_GET['carrera'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir la consulta base con filtros
$whereConditions = ["s.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($filtroEstado !== 'todos') {
    $whereConditions[] = "s.estado = :estado";
    $params['estado'] = $filtroEstado;
}

if ($filtroProyecto !== 'todos') {
    $whereConditions[] = "p.id = :proyecto_id";
    $params['proyecto_id'] = $filtroProyecto;
}

if ($filtroCarrera !== 'todos') {
    $whereConditions[] = "e.carrera = :carrera";
    $params['carrera'] = $filtroCarrera;
}

if (!empty($busqueda)) {
    $whereConditions[] = "(e.nombre LIKE :busqueda OR e.apellido_paterno LIKE :busqueda OR e.numero_control LIKE :busqueda)";
    $params['busqueda'] = '%' . $busqueda . '%';
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT e.id) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN e.id END) as estudiantes_activos,
        COUNT(DISTINCT CASE WHEN s.estado = 'concluida' THEN e.id END) as estudiantes_completados,
        COALESCE(SUM(e.horas_completadas), 0) as total_horas,
        COALESCE(AVG(e.horas_completadas), 0) as promedio_horas
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    WHERE s.jefe_laboratorio_id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Obtener lista de estudiantes con filtros
$estudiantes = $db->fetchAll("
    SELECT 
        e.*,
        s.estado as estado_servicio,
        s.fecha_inicio_propuesta,
        s.fecha_fin_propuesta,
        s.id as solicitud_id,
        p.nombre_proyecto,
        p.id as proyecto_id,
        COUNT(DISTINCT r.id) as total_reportes,
        COUNT(DISTINCT CASE WHEN r.estado = 'aprobado' THEN r.id END) as reportes_aprobados,
        MAX(r.fecha_entrega) as ultimo_reporte
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    WHERE $whereClause
    GROUP BY e.id, s.id
    ORDER BY s.fecha_inicio_propuesta DESC
", $params);

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

$pageTitle = "Estudiantes Asignados - " . APP_NAME;
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
                        <i class="fas fa-users"></i>
                        Estudiantes Asignados
                    </h1>
                    <p class="page-subtitle">Gestión de estudiantes del laboratorio <?= htmlspecialchars($nombreLaboratorio) ?></p>
                </div>
                <div class="header-actions">
                    <a href="../../dashboard/jefe_laboratorio.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                    <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-download"></i>
                        Exportar Lista
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Estudiantes</h3>
                    <div class="stat-number"><?= $stats['total_estudiantes'] ?></div>
                    <p class="stat-description">Asignados al laboratorio</p>
                </div>
            </div>

            <div class="stat-card activos">
                <div class="stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Activos</h3>
                    <div class="stat-number"><?= $stats['estudiantes_activos'] ?></div>
                    <p class="stat-description">En proceso</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $stats['total_estudiantes'] > 0 ? round(($stats['estudiantes_activos'] / $stats['total_estudiantes']) * 100) : 0 ?>% del total</span>
                    </div>
                </div>
            </div>

            <div class="stat-card completados">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Completados</h3>
                    <div class="stat-number"><?= $stats['estudiantes_completados'] ?></div>
                    <p class="stat-description">Servicio finalizado</p>
                    <div class="stat-trend">
                        <i class="fas fa-trophy"></i>
                        <span>¡Excelente!</span>
                    </div>
                </div>
            </div>

            <div class="stat-card horas">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Horas</h3>
                    <div class="stat-number"><?= number_format($stats['total_horas']) ?></div>
                    <p class="stat-description">Horas acumuladas</p>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span><?= round($stats['promedio_horas']) ?> hrs promedio</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros y Búsqueda
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
                            <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="aprobada" <?= $filtroEstado === 'aprobada' ? 'selected' : '' ?>>Aprobada</option>
                            <option value="concluida" <?= $filtroEstado === 'concluida' ? 'selected' : '' ?>>Concluida</option>
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

        <!-- Students List -->
        <div class="students-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Lista de Estudiantes
                    <span class="count-badge"><?= count($estudiantes) ?></span>
                </h2>
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid" onclick="cambiarVista('grid')">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list" onclick="cambiarVista('list')">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>

            <?php if (!empty($estudiantes)): ?>
                <div class="students-grid" id="studentsContainer">
                    <?php foreach ($estudiantes as $estudiante): ?>
                    <div class="student-card">
                        <div class="student-header">
                            <div class="student-avatar">
                                <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                            </div>
                            <div class="student-info">
                                <h3><?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?></h3>
                                <p class="student-control"><?= htmlspecialchars($estudiante['numero_control']) ?></p>
                            </div>
                            <div class="student-status">
                                <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                                    <?= getEstadoText($estudiante['estado_servicio']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="student-body">
                            <div class="info-row">
                                <i class="fas fa-graduation-cap"></i>
                                <span><?= htmlspecialchars($estudiante['carrera']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-project-diagram"></i>
                                <span><?= htmlspecialchars($estudiante['nombre_proyecto']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar-alt"></i>
                                <span>
                                    <?= formatDate($estudiante['fecha_inicio_propuesta']) ?> - 
                                    <?= formatDate($estudiante['fecha_fin_propuesta']) ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-flag"></i>
                                <span><?= getEstadoText($estudiante['estado_servicio']) ?></span>
                            </div>
                        </div>

                        <div class="student-stats-row">
                            <div class="stat-mini">
                                <i class="fas fa-clock"></i>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-value"><?= $estudiante['horas_completadas'] ?? 0 ?></span>
                                    <span class="stat-mini-label">Horas</span>
                                </div>
                            </div>
                            <div class="stat-mini">
                                <i class="fas fa-file-alt"></i>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-value"><?= $estudiante['total_reportes'] ?? 0 ?></span>
                                    <span class="stat-mini-label">Reportes</span>
                                </div>
                            </div>
                            <div class="stat-mini">
                                <i class="fas fa-percentage"></i>
                                <div class="stat-mini-content">
                                    <span class="stat-mini-value"><?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%</span>
                                    <span class="stat-mini-label">Avance</span>
                                </div>
                            </div>
                        </div>

                        <div class="progress-bar-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= min(100, round((($estudiante['horas_completadas'] ?? 0) / 500) * 100)) ?>%"></div>
                            </div>
                            <span class="progress-text"><?= $estudiante['horas_completadas'] ?? 0 ?> / 500 horas</span>
                        </div>

                        <div class="student-actions">
                            <a href="estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Ver Perfil
                            </a>
                            <a href="reportes-estudiante.php?id=<?= $estudiante['id'] ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-file-alt"></i> Reportes
                            </a>
                            <a href="evaluar-estudiante.php?id=<?= $estudiante['solicitud_id'] ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Evaluar
                            </a>
                        </div>

                        <?php if ($estudiante['ultimo_reporte']): ?>
                        <div class="last-report-info">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Último reporte: <?= formatDate($estudiante['ultimo_reporte']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>No se encontraron estudiantes</h3>
                    <p>No hay estudiantes que coincidan con los filtros seleccionados.</p>
                    <button class="btn btn-primary" onclick="limpiarFiltros()">
                        <i class="fas fa-redo"></i>
                        Limpiar Filtros
                    </button>
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

.stat-card.total { --gradient-color: var(--primary); }
.stat-card.activos { --gradient-color: var(--secondary); }
.stat-card.completados { --gradient-color: var(--success); }
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

.stat-card.total .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card.activos .stat-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }
.stat-card.completados .stat-icon { background: linear-gradient(135deg, var(--success), #34d399); }
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
    grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr;
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

/* Students Section */
.students-section {
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

.view-toggle {
    display: flex;
    gap: 0.5rem;
}

.view-btn {
    width: 36px;
    height: 36px;
    border: 2px solid var(--border);
    background: var(--bg-white);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-secondary);
}

.view-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.view-btn.active {
    border-color: var(--primary);
    background: var(--primary);
    color: white;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.students-grid.list-view {
    grid-template-columns: 1fr;
}

.student-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 2px solid transparent;
    transition: var(--transition);
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
    border-color: var(--primary);
}

.student-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
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
    font-weight: 700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.student-info {
    flex: 1;
}

.student-info h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.student-control {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.student-status {
    flex-shrink: 0;
}

.student-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.info-row i {
    width: 20px;
    color: var(--primary);
    font-size: 0.875rem;
}

.student-stats-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.stat-mini {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-mini i {
    font-size: 1.25rem;
    color: var(--primary);
}

.stat-mini-content {
    display: flex;
    flex-direction: column;
}

.stat-mini-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-mini-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.progress-bar-container {
    margin-bottom: 1rem;
}

.progress-bar {
    height: 8px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 1rem;
    transition: width 1s ease;
}

.progress-text {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.student-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.last-report-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
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
.badge-info { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }
.badge-secondary { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }

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

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    
    .students-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
        grid-template-columns: 1fr 1fr;
    }
    
    .form-group:last-child {
        grid-column: 1 / -1;
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
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .student-actions {
        flex-direction: column;
    }
    
    .student-actions .btn {
        width: 100%;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .header-actions .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .student-card {
        padding: 1rem;
    }
    
    .student-stats-row {
        flex-direction: column;
        gap: 0.75rem;
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
    const progressFills = document.querySelectorAll('.progress-fill');
    progressFills.forEach((fill, index) => {
        const width = fill.style.width;
        fill.style.width = '0%';
        
        setTimeout(() => {
            fill.style.width = width;
        }, index * 100);
    });
});

function cambiarVista(vista) {
    const container = document.getElementById('studentsContainer');
    const buttons = document.querySelectorAll('.view-btn');
    
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.view === vista) {
            btn.classList.add('active');
        }
    });
    
    if (vista === 'list') {
        container.classList.add('list-view');
    } else {
        container.classList.remove('list-view');
    }
}

function limpiarFiltros() {
    window.location.href = 'estudiantes-asignados.php';
}

function exportToExcel() {
    // Collect all student data
    const students = document.querySelectorAll('.student-card');
    let csv = [];
    
    // Headers
    csv.push(['Nombre', 'No. Control', 'Carrera', 'Proyecto', 'Fecha Inicio', 'Fecha Fin', 'Horas', 'Estado'].join(','));
    
    // Data rows
    students.forEach(card => {
        const nombre = card.querySelector('.student-info h3').textContent.trim();
        const control = card.querySelector('.student-control').textContent.trim();
        const infoRows = card.querySelectorAll('.info-row span');
        const carrera = infoRows[0].textContent.trim();
        const proyecto = infoRows[1].textContent.trim();
        const periodo = infoRows[2].textContent.trim();
        const [fechaInicio, fechaFin] = periodo.split(' - ');
        const horas = card.querySelector('.stat-mini-value').textContent.trim();
        const estado = card.querySelector('.badge').textContent.trim();
        
        csv.push([nombre, control, carrera, proyecto, fechaInicio, fechaFin, horas, estado].map(v => `"${v}"`).join(','));
    });
    
    // Create and download file
    const csvContent = "\uFEFF" + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `estudiantes_laboratorio_<?= date('Y-m-d') ?>.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Auto-submit form on filter change
const filterForm = document.getElementById('filterForm');
if (filterForm) {
    const selects = filterForm.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', () => {
            // Optional: uncomment to auto-submit on change
            // filterForm.submit();
        });
    });
}
</script>

<?php include '../../includes/footer.php'; ?>