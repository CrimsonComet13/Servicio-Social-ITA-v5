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

// Procesar acciones (activar/desactivar proyectos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $proyectoId = $_POST['proyecto_id'] ?? null;
    $action = $_POST['action'];
    
    if ($proyectoId) {
        try {
            if ($action === 'toggle_estado') {
                $nuevoEstado = $_POST['nuevo_estado'];
                
                $db->update('proyectos_laboratorio', [
                    'activo' => $nuevoEstado
                ], 'id = :id AND jefe_laboratorio_id = :jefe_id', [
                    'id' => $proyectoId,
                    'jefe_id' => $jefeLabId
                ]);
                
                $mensaje = $nuevoEstado == 1 ? 'Proyecto activado exitosamente' : 'Proyecto desactivado exitosamente';
                flashMessage($mensaje, 'success');
                redirectTo('/modules/laboratorio/proyectos.php');
            }
        } catch (Exception $e) {
            flashMessage('Error al actualizar el proyecto: ' . $e->getMessage(), 'error');
        }
    }
}

// Procesar filtros
$filtroEstado = $_GET['estado'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir la consulta base con filtros
$whereConditions = ["p.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($filtroEstado !== 'todos') {
    $whereConditions[] = "p.activo = :activo";
    $params['activo'] = $filtroEstado === 'activos' ? 1 : 0;
}

if (!empty($busqueda)) {
    $whereConditions[] = "(p.nombre_proyecto LIKE :busqueda OR p.descripcion LIKE :busqueda OR p.laboratorio_asignado LIKE :busqueda)";
    $params['busqueda'] = '%' . $busqueda . '%';
}

$whereClause = implode(' AND ', $whereConditions);

// Obtener estadísticas de proyectos
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_proyectos,
        COUNT(CASE WHEN p.activo = 1 THEN 1 END) as proyectos_activos,
        COUNT(CASE WHEN p.activo = 0 THEN 1 END) as proyectos_inactivos,
        SUM(p.cupo_disponible) as cupos_totales,
        SUM(p.cupo_ocupado) as cupos_ocupados,
        SUM(p.cupo_disponible - p.cupo_ocupado) as cupos_libres
    FROM proyectos_laboratorio p
    WHERE p.jefe_laboratorio_id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Obtener lista de proyectos con filtros
$proyectos = $db->fetchAll("
    SELECT 
        p.*,
        COUNT(DISTINCT s.id) as total_solicitudes,
        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN s.id END) as solicitudes_pendientes,
        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as estudiantes_activos,
        COUNT(DISTINCT CASE WHEN s.estado = 'concluida' THEN s.id END) as estudiantes_completados
    FROM proyectos_laboratorio p
    LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
    WHERE $whereClause
    GROUP BY p.id
    ORDER BY p.activo DESC, p.created_at DESC
", $params);

$pageTitle = "Gestión de Proyectos - " . APP_NAME;
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
                        <i class="fas fa-project-diagram"></i>
                        Gestión de Proyectos
                    </h1>
                    <p class="page-subtitle">Administración de proyectos del laboratorio <?= htmlspecialchars($nombreLaboratorio) ?></p>
                </div>
                <div class="header-actions">
                    <a href="../../dashboard/jefe_laboratorio.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                    <a href="proyecto-crear.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Nuevo Proyecto
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Proyectos</h3>
                    <div class="stat-number"><?= $stats['total_proyectos'] ?></div>
                    <p class="stat-description">Proyectos registrados</p>
                </div>
            </div>

            <div class="stat-card activos">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Proyectos Activos</h3>
                    <div class="stat-number"><?= $stats['proyectos_activos'] ?></div>
                    <p class="stat-description">Disponibles</p>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i>
                        <span><?= $stats['total_proyectos'] > 0 ? round(($stats['proyectos_activos'] / $stats['total_proyectos']) * 100) : 0 ?>% activos</span>
                    </div>
                </div>
            </div>

            <div class="stat-card cupos">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Cupos Disponibles</h3>
                    <div class="stat-number"><?= $stats['cupos_libres'] ?></div>
                    <p class="stat-description">De <?= $stats['cupos_totales'] ?> totales</p>
                    <div class="stat-trend">
                        <i class="fas fa-user-check"></i>
                        <span><?= $stats['cupos_ocupados'] ?> ocupados</span>
                    </div>
                </div>
            </div>

            <div class="stat-card ocupacion">
                <div class="stat-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Tasa de Ocupación</h3>
                    <div class="stat-number"><?= $stats['cupos_totales'] > 0 ? round(($stats['cupos_ocupados'] / $stats['cupos_totales']) * 100) : 0 ?>%</div>
                    <p class="stat-description">Promedio general</p>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <span><?= $stats['proyectos_activos'] ?> activos</span>
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
                            Buscar Proyecto
                        </label>
                        <input type="text" 
                               id="busqueda" 
                               name="busqueda" 
                               value="<?= htmlspecialchars($busqueda) ?>"
                               placeholder="Nombre, descripción o laboratorio..."
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">
                            <i class="fas fa-toggle-on"></i>
                            Estado
                        </label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : '' ?>>Todos los proyectos</option>
                            <option value="activos" <?= $filtroEstado === 'activos' ? 'selected' : '' ?>>Solo Activos</option>
                            <option value="inactivos" <?= $filtroEstado === 'inactivos' ? 'selected' : '' ?>>Solo Inactivos</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Projects Section -->
        <div class="projects-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Proyectos del Laboratorio
                    <span class="count-badge"><?= count($proyectos) ?></span>
                </h2>
                <div class="view-options">
                    <button class="view-btn active" data-view="grid" onclick="cambiarVista('grid')">
                        <i class="fas fa-th"></i> Grid
                    </button>
                    <button class="view-btn" data-view="list" onclick="cambiarVista('list')">
                        <i class="fas fa-list"></i> Lista
                    </button>
                </div>
            </div>

            <?php if (!empty($proyectos)): ?>
                <div class="projects-grid" id="projectsContainer">
                    <?php foreach ($proyectos as $proyecto): ?>
                    <div class="project-card <?= $proyecto['activo'] ? '' : 'inactive' ?>">
                        <div class="project-header">
                            <div class="project-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="project-status-badge">
                                <?php if ($proyecto['activo']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Activo
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-error">
                                        <i class="fas fa-pause-circle"></i> Inactivo
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="project-body">
                            <h3 class="project-title"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></h3>
                            <p class="project-description"><?= htmlspecialchars(substr($proyecto['descripcion'], 0, 150)) ?><?= strlen($proyecto['descripcion']) > 150 ? '...' : '' ?></p>
                            
                            <div class="project-meta">
                                <div class="meta-item">
                                    <i class="fas fa-flask"></i>
                                    <span><?= htmlspecialchars($proyecto['laboratorio_asignado']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Creado: <?= formatDate($proyecto['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="cupos-section">
                                <div class="cupos-header">
                                    <span class="cupos-label">Cupos</span>
                                    <span class="cupos-value"><?= $proyecto['cupo_ocupado'] ?> / <?= $proyecto['cupo_disponible'] ?></span>
                                </div>
                                <div class="cupos-bar">
                                    <div class="cupos-fill" style="width: <?= $proyecto['cupo_disponible'] > 0 ? round(($proyecto['cupo_ocupado'] / $proyecto['cupo_disponible']) * 100) : 0 ?>%"></div>
                                </div>
                                <div class="cupos-percentage">
                                    <?= $proyecto['cupo_disponible'] > 0 ? round(($proyecto['cupo_ocupado'] / $proyecto['cupo_disponible']) * 100) : 0 ?>% ocupado
                                    <?php if ($proyecto['cupo_ocupado'] >= $proyecto['cupo_disponible']): ?>
                                        <span class="badge badge-error badge-sm">LLENO</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="project-stats">
                                <div class="stat-item">
                                    <i class="fas fa-paper-plane"></i>
                                    <div>
                                        <span class="stat-value"><?= $proyecto['total_solicitudes'] ?></span>
                                        <span class="stat-label">Solicitudes</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <div>
                                        <span class="stat-value"><?= $proyecto['solicitudes_pendientes'] ?></span>
                                        <span class="stat-label">Pendientes</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-user-check"></i>
                                    <div>
                                        <span class="stat-value"><?= $proyecto['estudiantes_activos'] ?></span>
                                        <span class="stat-label">Activos</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-trophy"></i>
                                    <div>
                                        <span class="stat-value"><?= $proyecto['estudiantes_completados'] ?></span>
                                        <span class="stat-label">Completados</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="project-actions">
                            <a href="proyecto-detalle.php?id=<?= $proyecto['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> Ver Detalle
                            </a>
                            <a href="proyecto-editar.php?id=<?= $proyecto['id'] ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <?php if ($proyecto['activo']): ?>
                                <button class="btn btn-warning btn-sm" onclick="toggleEstado(<?= $proyecto['id'] ?>, 0)">
                                    <i class="fas fa-pause"></i> Desactivar
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick="toggleEstado(<?= $proyecto['id'] ?>, 1)">
                                    <i class="fas fa-play"></i> Activar
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!$proyecto['activo']): ?>
                        <div class="inactive-overlay">
                            <i class="fas fa-pause-circle"></i>
                            <span>Proyecto Inactivo</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3>No hay proyectos registrados</h3>
                    <p>Comienza creando tu primer proyecto de servicio social.</p>
                    <a href="proyecto-crear.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Crear Primer Proyecto
                    </a>
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

.stat-card.total { --gradient-color: var(--secondary); }
.stat-card.activos { --gradient-color: var(--success); }
.stat-card.cupos { --gradient-color: var(--primary); }
.stat-card.ocupacion { --gradient-color: var(--warning); }

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

.stat-card.total .stat-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }
.stat-card.activos .stat-icon { background: linear-gradient(135deg, var(--success), #34d399); }
.stat-card.cupos .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.stat-card.ocupacion .stat-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }

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
    grid-template-columns: 2fr 1fr auto;
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

/* Projects Section */
.projects-section {
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
    gap: 0.5rem;
}

.view-btn {
    padding: 0.5rem 1rem;
    border: 2px solid var(--border);
    background: var(--bg-white);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
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

/* Projects Grid */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.projects-grid.list-view {
    grid-template-columns: 1fr;
}

.project-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 2px solid transparent;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
    border-color: var(--primary);
}

.project-card.inactive {
    opacity: 0.7;
}

.project-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.project-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
}

.project-status-badge {
    flex-shrink: 0;
}

.project-body {
    margin-bottom: 1.5rem;
}

.project-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    line-height: 1.3;
}

.project-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
    line-height: 1.6;
}

.project-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.meta-item i {
    width: 16px;
    color: var(--primary);
}

.cupos-section {
    background: var(--bg-white);
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
}

.cupos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.cupos-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.cupos-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.cupos-bar {
    height: 8px;
    background: var(--bg-gray);
    border-radius: 1rem;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.cupos-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 1rem;
    transition: width 1s ease;
}

.cupos-percentage {
    font-size: 0.8rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.project-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    margin-bottom: 1rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.5rem;
}

.stat-item i {
    font-size: 1.25rem;
    color: var(--primary);
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.project-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.inactive-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(239, 68, 68, 0.9), transparent);
    padding: 2rem 1rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-sm {
    padding: 0.125rem 0.5rem;
    font-size: 0.7rem;
}

.badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }

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

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-warning:hover {
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
    
    .projects-grid {
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
        grid-template-columns: 1fr;
    }
    
    .project-stats {
        grid-template-columns: repeat(2, 1fr);
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
    
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .project-actions {
        flex-direction: column;
    }
    
    .project-actions .btn {
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
    
    .project-card {
        padding: 1rem;
    }
    
    .project-stats {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent.replace(/[^\d]/g, ''));
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                const displayNumber = Math.floor(Math.min(currentNumber, finalNumber));
                numberElement.textContent = numberElement.textContent.includes('%') ? 
                    displayNumber + '%' : 
                    displayNumber.toLocaleString();
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = numberElement.textContent.includes('%') ? 
                    finalNumber + '%' : 
                    finalNumber.toLocaleString();
            }
        }
        
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Animate cupos bars
    setTimeout(() => {
        const cuposFills = document.querySelectorAll('.cupos-fill');
        cuposFills.forEach((fill, index) => {
            const width = fill.style.width;
            fill.style.width = '0%';
            
            setTimeout(() => {
                fill.style.width = width;
            }, index * 100);
        });
    }, 500);
});

function limpiarFiltros() {
    window.location.href = 'proyectos.php';
}

function cambiarVista(vista) {
    const container = document.getElementById('projectsContainer');
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

function toggleEstado(proyectoId, nuevoEstado) {
    const mensaje = nuevoEstado === 1 ? 
        '¿Está seguro de que desea activar este proyecto?' : 
        '¿Está seguro de que desea desactivar este proyecto?';
    
    if (confirm(mensaje)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_estado">
            <input type="hidden" name="proyecto_id" value="${proyectoId}">
            <input type="hidden" name="nuevo_estado" value="${nuevoEstado}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>