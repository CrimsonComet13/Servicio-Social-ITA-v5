<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();

// ✅ Obtener datos del jefe de laboratorio
$jefeLab = $db->fetch("SELECT id, nombre, laboratorio FROM jefes_laboratorio WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeLab) {
    flashMessage('No se encontró el perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
}
$jefeLabId = $jefeLab['id'];

// Filtros
$estado = $_GET['estado'] ?? 'activos';
$search = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'nombre';

// Construir consulta base
$whereConditions = ["s.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

// Filtro por estado
switch ($estado) {
    case 'activos':
        $whereConditions[] = "s.estado = 'en_proceso'";
        break;
    case 'pendientes':
        $whereConditions[] = "s.estado = 'pendiente'";
        break;
    case 'finalizados':
        $whereConditions[] = "s.estado = 'completado'";
        break;
    case 'todos':
        // Sin filtro adicional
        break;
}

// Filtro por búsqueda
if (!empty($search)) {
    $whereConditions[] = "(e.nombre LIKE :search OR e.apellido_paterno LIKE :search OR e.numero_control LIKE :search OR p.nombre_proyecto LIKE :search)";
    $params['search'] = "%$search%";
}

$whereClause = implode(' AND ', $whereConditions);

// Definir orden
$orderClause = match($orderBy) {
    'nombre' => 'e.nombre ASC',
    'numero_control' => 'e.numero_control ASC',
    'horas' => 'e.horas_completadas DESC',
    'proyecto' => 'p.nombre_proyecto ASC',
    default => 'e.nombre ASC'
};

// Obtener estudiantes
$estudiantes = $db->fetchAll("
    SELECT e.*, s.id as solicitud_id, s.estado as estado_solicitud,
           s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           p.nombre_proyecto, p.descripcion as proyecto_descripcion,
           COUNT(DISTINCT r.id) as total_reportes,
           COUNT(DISTINCT CASE WHEN r.estado = 'aprobado' THEN r.id END) as reportes_aprobados,
           COUNT(DISTINCT CASE WHEN r.estado = 'pendiente_evaluacion' THEN r.id END) as reportes_pendientes
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
    WHERE $whereClause
    GROUP BY e.id, s.id, p.id
    ORDER BY $orderClause
", $params);

// Obtener estadísticas generales
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN e.id END) as activos,
        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN e.id END) as pendientes,
        COUNT(DISTINCT CASE WHEN s.estado = 'completado' THEN e.id END) as finalizados,
        COUNT(DISTINCT e.id) as total,
        COALESCE(AVG(e.horas_completadas), 0) as promedio_horas,
        COALESCE(SUM(e.horas_completadas), 0) as total_horas
    FROM estudiantes e
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    WHERE s.jefe_laboratorio_id = :jefe_id
", ['jefe_id' => $jefeLabId]);

if (!$stats) {
    $stats = [
        'activos' => 0,
        'pendientes' => 0,
        'finalizados' => 0,
        'total' => 0,
        'promedio_horas' => 0,
        'total_horas' => 0
    ];
}

$pageTitle = "Estudiantes - " . APP_NAME;
$dashboardJS = true;

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">
                    <span class="welcome-text">Gestión de Estudiantes</span>
                    <span class="welcome-emoji">👨‍🎓</span>
                </h1>
                <p class="welcome-subtitle">Estudiantes asignados al laboratorio <?= htmlspecialchars($jefeLab['laboratorio'] ?? 'Sin laboratorio') ?></p>
            </div>
            <div class="date-section">
                <div class="current-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= formatDate(date('Y-m-d')) ?></span>
                </div>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"><?= date('H:i') ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card activos">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Estudiantes Activos</h3>
                    <div class="stat-number"><?= $stats['activos'] ?></div>
                    <p class="stat-description">En servicio social</p>
                </div>
            </div>

            <div class="stat-card pendientes">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Pendientes</h3>
                    <div class="stat-number"><?= $stats['pendientes'] ?></div>
                    <p class="stat-description">En proceso de aprobación</p>
                </div>
            </div>

            <div class="stat-card horas">
                <div class="stat-icon">
                    <i class="fas fa-business-time"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total de Horas</h3>
                    <div class="stat-number"><?= number_format($stats['total_horas']) ?></div>
                    <p class="stat-description">Horas acumuladas</p>
                </div>
            </div>

            <div class="stat-card promedio">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Promedio</h3>
                    <div class="stat-number"><?= round($stats['promedio_horas']) ?></div>
                    <p class="stat-description">Horas por estudiante</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="content-section">
            <div class="filters-container">
                <div class="filter-tabs">
                    <a href="?estado=activos<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                        <i class="fas fa-user-check"></i>
                        Activos (<?= $stats['activos'] ?>)
                    </a>
                    <a href="?estado=pendientes<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $estado === 'pendientes' ? 'active' : '' ?>">
                        <i class="fas fa-hourglass-half"></i>
                        Pendientes (<?= $stats['pendientes'] ?>)
                    </a>
                    <a href="?estado=finalizados<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $estado === 'finalizados' ? 'active' : '' ?>">
                        <i class="fas fa-check-circle"></i>
                        Finalizados (<?= $stats['finalizados'] ?>)
                    </a>
                    <a href="?estado=todos<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i>
                        Todos (<?= $stats['total'] ?>)
                    </a>
                </div>

                <div class="search-and-sort">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="estado" value="<?= htmlspecialchars($estado) ?>">
                        <div class="search-input-group">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   name="search" 
                                   class="search-input" 
                                   placeholder="Buscar por nombre, número de control o proyecto..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <?php if ($search): ?>
                            <a href="?estado=<?= $estado ?>" class="clear-search" title="Limpiar búsqueda">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="sort-dropdown">
                        <select name="order" onchange="window.location.href='?estado=<?= $estado ?>&search=<?= urlencode($search) ?>&order=' + this.value" class="sort-select">
                            <option value="nombre" <?= $orderBy === 'nombre' ? 'selected' : '' ?>>Ordenar por Nombre</option>
                            <option value="numero_control" <?= $orderBy === 'numero_control' ? 'selected' : '' ?>>Ordenar por No. Control</option>
                            <option value="horas" <?= $orderBy === 'horas' ? 'selected' : '' ?>>Ordenar por Horas</option>
                            <option value="proyecto" <?= $orderBy === 'proyecto' ? 'selected' : '' ?>>Ordenar por Proyecto</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Grid -->
        <?php if (!empty($estudiantes)): ?>
            <div class="students-grid">
                <?php foreach ($estudiantes as $estudiante): 
                    $progreso = min(100, ($estudiante['horas_completadas'] / 500) * 100);
                    $estadoClass = match($estudiante['estado_solicitud']) {
                        'en_proceso' => 'active',
                        'pendiente' => 'pending',
                        'completado' => 'completed',
                        default => 'pending'
                    };
                ?>
                <div class="student-card <?= $estadoClass ?>">
                    <div class="student-card-header">
                        <div class="student-avatar-container">
                            <div class="student-avatar">
                                <?= strtoupper(substr($estudiante['nombre'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="student-status-badge <?= $estadoClass ?>">
                                <i class="fas fa-<?= match($estudiante['estado_solicitud']) {
                                    'en_proceso' => 'play-circle',
                                    'pendiente' => 'clock',
                                    'completado' => 'check-circle',
                                    default => 'question-circle'
                                } ?>"></i>
                            </div>
                        </div>
                        <div class="student-header-info">
                            <h3 class="student-name"><?= htmlspecialchars(($estudiante['nombre'] ?? 'Sin nombre') . ' ' . ($estudiante['apellido_paterno'] ?? '')) ?></h3>
                            <p class="student-control"><?= htmlspecialchars($estudiante['numero_control'] ?? 'Sin número') ?></p>
                            <span class="student-career"><?= htmlspecialchars($estudiante['carrera'] ?? 'Sin carrera') ?></span>
                        </div>
                    </div>

                    <div class="student-card-body">
                        <div class="student-info-grid">
                            <div class="info-item">
                                <i class="fas fa-project-diagram"></i>
                                <div class="info-content">
                                    <span class="info-label">Proyecto</span>
                                    <span class="info-value"><?= htmlspecialchars($estudiante['nombre_proyecto'] ?? 'Sin asignar') ?></span>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="info-content">
                                    <span class="info-label">Periodo</span>
                                    <span class="info-value">
                                        <?= formatDate($estudiante['fecha_inicio_propuesta'] ?? date('Y-m-d')) ?> - 
                                        <?= formatDate($estudiante['fecha_fin_propuesta'] ?? date('Y-m-d')) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-file-alt"></i>
                                <div class="info-content">
                                    <span class="info-label">Reportes</span>
                                    <span class="info-value"><?= $estudiante['reportes_aprobados'] ?>/<?= $estudiante['total_reportes'] ?> aprobados</span>
                                </div>
                            </div>

                            <?php if ($estudiante['reportes_pendientes'] > 0): ?>
                            <div class="info-item alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="info-content">
                                    <span class="info-label">Pendientes</span>
                                    <span class="info-value"><?= $estudiante['reportes_pendientes'] ?> reportes por revisar</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="progress-section">
                            <div class="progress-header-row">
                                <span class="progress-label">Progreso de Horas</span>
                                <span class="progress-percentage"><?= round($progreso) ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= $progreso ?>%"></div>
                            </div>
                            <div class="progress-details-row">
                                <span class="hours-completed"><?= $estudiante['horas_completadas'] ?? 0 ?> horas</span>
                                <span class="hours-remaining"><?= max(0, 500 - ($estudiante['horas_completadas'] ?? 0)) ?> restantes</span>
                            </div>
                        </div>
                    </div>

                    <div class="student-card-footer">
                        <a href="estudiante-detalle.php?id=<?= $estudiante['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i>
                            Ver Detalle
                        </a>
                        <?php if ($estudiante['reportes_pendientes'] > 0): ?>
                        <a href="evaluar-reporte.php?estudiante=<?= $estudiante['id'] ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-clipboard-check"></i>
                            Evaluar (<?= $estudiante['reportes_pendientes'] ?>)
                        </a>
                        <?php endif; ?>
                        <a href="reportes-estudiante.php?id=<?= $estudiante['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-file-alt"></i>
                            Reportes
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="content-section">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="empty-content">
                        <h3><?= $search ? 'No se encontraron resultados' : 'No hay estudiantes' ?></h3>
                        <p>
                            <?php if ($search): ?>
                                No se encontraron estudiantes que coincidan con "<?= htmlspecialchars($search) ?>"
                            <?php else: ?>
                                Aún no hay estudiantes asignados a este laboratorio
                            <?php endif; ?>
                        </p>
                        <?php if ($search): ?>
                        <a href="?estado=<?= $estado ?>" class="btn btn-primary">
                            <i class="fas fa-times"></i>
                            Limpiar Búsqueda
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
    --sidebar-width: 280px;
    --header-height: 70px;
}

/* Main wrapper */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
}

/* Dashboard Container */
.dashboard-container {
    padding: 1.5rem;
    max-width: calc(1400px - var(--sidebar-width));
    margin: 0 auto;
    width: 100%;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.date-section {
    display: flex;
    gap: 1rem;
}

.current-date, .current-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-secondary);
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

.stat-card.activos {
    --gradient-color: var(--success);
}

.stat-card.pendientes {
    --gradient-color: var(--warning);
}

.stat-card.horas {
    --gradient-color: var(--info);
}

.stat-card.promedio {
    --gradient-color: var(--secondary);
}

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

.stat-card.activos .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.pendientes .stat-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-card.horas .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-card.promedio .stat-icon {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

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
    margin: 0;
}

/* Filters Container */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.filters-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    background: var(--bg-light);
    transition: var(--transition);
    border: 2px solid transparent;
}

.filter-tab:hover {
    background: var(--bg-white);
    border-color: var(--primary);
    color: var(--primary);
}

.filter-tab.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.search-and-sort {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-form {
    flex: 1;
}

.search-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 1rem;
    color: var(--text-light);
    font-size: 0.9rem;
}

.search-input {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 3rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.clear-search {
    position: absolute;
    right: 1rem;
    color: var(--text-light);
    cursor: pointer;
    transition: var(--transition);
}

.clear-search:hover {
    color: var(--error);
}

.sort-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-primary);
    background: var(--bg-white);
    cursor: pointer;
    transition: var(--transition);
}

.sort-select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.student-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: var(--transition);
    border: 2px solid transparent;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.student-card.active {
    border-color: rgba(16, 185, 129, 0.3);
}

.student-card.pending {
    border-color: rgba(245, 158, 11, 0.3);
}

.student-card.completed {
    border-color: rgba(139, 92, 246, 0.3);
}

.student-card-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
    border-bottom: 1px solid var(--border-light);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.student-avatar-container {
    position: relative;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    box-shadow: var(--shadow);
}

.student-status-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.7rem;
    border: 2px solid var(--bg-white);
}

.student-status-badge.active {
    background: var(--success);
}

.student-status-badge.pending {
    background: var(--warning);
}

.student-status-badge.completed {
    background: var(--secondary);
}

.student-header-info {
    flex: 1;
}

.student-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.student-control {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.student-career {
    display: inline-block;
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
}

.student-card-body {
    padding: 1.5rem;
}

.student-info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.info-item {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}

.info-item i {
    width: 18px;
    color: var(--primary);
    font-size: 0.9rem;
    margin-top: 0.125rem;
}

.info-item.alert i {
    color: var(--warning);
}

.info-content {
    flex: 1;
}

.info-label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-light);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.125rem;
}

.info-value {
    display: block;
    font-size: 0.875rem;
    color: var(--text-primary);
    font-weight: 500;
}

.progress-section {
    background: var(--bg-light);
    padding: 1rem;
    border-radius: var(--radius);
}

.progress-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.progress-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-percentage {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--success);
}

.progress-bar-container {
    height: 8px;
    background: var(--bg-white);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #34d399);
    border-radius: 4px;
    transition: width 1s ease-out;
}

.progress-details-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-light);
}

.student-card-footer {
    padding: 1rem 1.5rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

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

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin: 0 0 1.5rem 0;
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
    font-size: 0.85rem;
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

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animaciones */
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

.students-grid > * {
    animation: slideIn 0.4s ease-out;
}

.students-grid > *:nth-child(1) { animation-delay: 0.05s; }
.students-grid > *:nth-child(2) { animation-delay: 0.1s; }
.students-grid > *:nth-child(3) { animation-delay: 0.15s; }
.students-grid > *:nth-child(4) { animation-delay: 0.2s; }
.students-grid > *:nth-child(5) { animation-delay: 0.25s; }
.students-grid > *:nth-child(6) { animation-delay: 0.3s; }

/* Responsive Design */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .dashboard-container {
        max-width: 1400px;
    }
    
    .students-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .search-and-sort {
        flex-direction: column;
    }
    
    .search-form {
        width: 100%;
    }
    
    .sort-select {
        width: 100%;
    }
    
    .filter-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .student-card-footer {
        flex-direction: column;
    }
    
    .student-card-footer .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
    }
    
    updateTime();
    setInterval(updateTime, 60000);
    
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-bar-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
    
    // Auto-submit search form on input
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
    
    console.log('✅ Página de estudiantes inicializada');
});
</script>

<?php include '../../includes/footer.php'; ?>