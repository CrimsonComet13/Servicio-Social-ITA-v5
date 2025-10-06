<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

// Obtener el nombre del laboratorio
$nombreLaboratorio = $usuario['laboratorio'] ?? 'Sin asignar';

// Procesar acciones rápidas (aprobar/rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $solicitudId = $_POST['solicitud_id'] ?? null;
    $action = $_POST['action'];
    
    if ($solicitudId) {
        try {
            $db->beginTransaction();
            
            if ($action === 'aprobar') {
                // Aprobar solicitud
                $db->update('solicitudes_servicio', [
                    'estado' => 'en_proceso',
                    'fecha_aprobacion_lab' => date('Y-m-d H:i:s')
                ], 'id = :id AND jefe_laboratorio_id = :jefe_id', [
                    'id' => $solicitudId,
                    'jefe_id' => $jefeLabId
                ]);
                
                flashMessage('Solicitud aprobada exitosamente', 'success');
            } elseif ($action === 'rechazar') {
                $motivo = $_POST['motivo_rechazo'] ?? 'Sin motivo especificado';
                
                // Rechazar solicitud
                $db->update('solicitudes_servicio', [
                    'estado' => 'rechazada',
                    'fecha_rechazo' => date('Y-m-d H:i:s'),
                    'motivo_rechazo' => $motivo
                ], 'id = :id AND jefe_laboratorio_id = :jefe_id', [
                    'id' => $solicitudId,
                    'jefe_id' => $jefeLabId
                ]);
                
                flashMessage('Solicitud rechazada', 'info');
            }
            
            $db->commit();
            redirectTo('/modules/laboratorio/estudiantes-solicitudes.php');
        } catch (Exception $e) {
            $db->rollback();
            flashMessage('Error al procesar la solicitud: ' . $e->getMessage(), 'error');
        }
    }
}

// Procesar filtros
$filtroEstado = $_GET['estado'] ?? 'pendiente';
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

// Obtener estadísticas de solicitudes
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_solicitudes,
        COUNT(CASE WHEN s.estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as aprobadas,
        COUNT(CASE WHEN s.estado = 'rechazada' THEN 1 END) as rechazadas,
        COUNT(CASE WHEN DATE(s.fecha_solicitud) = CURDATE() THEN 1 END) as nuevas_hoy
    FROM solicitudes_servicio s
    WHERE s.jefe_laboratorio_id = :jefe_id
", ['jefe_id' => $jefeLabId]);

// Obtener lista de solicitudes con filtros
$solicitudes = $db->fetchAll("
    SELECT 
        s.*,
        e.nombre,
        e.apellido_paterno,
        e.apellido_materno,
        e.numero_control,
        e.carrera,
        e.semestre,
        p.nombre_proyecto,
        p.descripcion as proyecto_descripcion,
        p.cupo_disponible,
        p.cupo_ocupado
    FROM solicitudes_servicio s
    JOIN estudiantes e ON s.estudiante_id = e.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE $whereClause
    ORDER BY 
        CASE s.estado 
            WHEN 'pendiente' THEN 1 
            WHEN 'en_proceso' THEN 2 
            ELSE 3 
        END,
        s.fecha_solicitud DESC
", $params);

// Obtener lista de proyectos para el filtro
$proyectos = $db->fetchAll("
    SELECT DISTINCT p.id, p.nombre_proyecto, p.cupo_disponible, p.cupo_ocupado
    FROM proyectos_laboratorio p
    WHERE p.jefe_laboratorio_id = :jefe_id AND p.activo = 1
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

$pageTitle = "Solicitudes de Estudiantes - " . APP_NAME;
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
                        <i class="fas fa-file-alt"></i>
                        Solicitudes de Estudiantes
                    </h1>
                    <p class="page-subtitle">Gestión de solicitudes del laboratorio <?= htmlspecialchars($nombreLaboratorio) ?></p>
                </div>
                <div class="header-actions">
                    <a href="../../dashboard/jefe_laboratorio.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="statistics-overview">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Total Solicitudes</h3>
                    <div class="stat-number"><?= $stats['total_solicitudes'] ?></div>
                    <p class="stat-description">Todas las solicitudes</p>
                </div>
            </div>

            <div class="stat-card pendientes">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Pendientes</h3>
                    <div class="stat-number"><?= $stats['pendientes'] ?></div>
                    <p class="stat-description">Por revisar</p>
                    <?php if ($stats['pendientes'] > 0): ?>
                    <div class="stat-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Requiere atención</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card aprobadas">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Aprobadas</h3>
                    <div class="stat-number"><?= $stats['aprobadas'] ?></div>
                    <p class="stat-description">En proceso</p>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span>Activas</span>
                    </div>
                </div>
            </div>

            <div class="stat-card nuevas">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-title">Nuevas Hoy</h3>
                    <div class="stat-number"><?= $stats['nuevas_hoy'] ?></div>
                    <p class="stat-description">Recibidas hoy</p>
                    <div class="stat-trend">
                        <i class="fas fa-calendar-day"></i>
                        <span>Último día</span>
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
                            <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="en_proceso" <?= $filtroEstado === 'en_proceso' ? 'selected' : '' ?>>Aprobada</option>
                            <option value="rechazada" <?= $filtroEstado === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
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
                                <?= htmlspecialchars($proyecto['nombre_proyecto']) ?> (<?= $proyecto['cupo_ocupado'] ?>/<?= $proyecto['cupo_disponible'] ?>)
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

        <!-- Solicitudes List -->
        <div class="solicitudes-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-inbox"></i>
                    Solicitudes Recibidas
                    <span class="count-badge"><?= count($solicitudes) ?></span>
                </h2>
            </div>

            <?php if (!empty($solicitudes)): ?>
                <div class="solicitudes-list">
                    <?php foreach ($solicitudes as $solicitud): ?>
                    <div class="solicitud-card <?= $solicitud['estado'] === 'pendiente' ? 'pendiente-highlight' : '' ?>">
                        <div class="solicitud-header">
                            <div class="student-info-main">
                                <div class="student-avatar">
                                    <?= strtoupper(substr($solicitud['nombre'], 0, 1)) ?>
                                </div>
                                <div class="student-details">
                                    <h3><?= htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?></h3>
                                    <p class="student-meta">
                                        <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($solicitud['numero_control']) ?></span>
                                        <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($solicitud['carrera']) ?></span>
                                        <span><i class="fas fa-layer-group"></i> <?= $solicitud['semestre'] ?>° Semestre</span>
                                    </p>
                                </div>
                            </div>
                            <div class="solicitud-status">
                                <span class="badge <?= getEstadoBadgeClass($solicitud['estado']) ?>">
                                    <?= getEstadoText($solicitud['estado']) ?>
                                </span>
                                <span class="solicitud-fecha">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatDate($solicitud['fecha_solicitud']) ?>
                                </span>
                                <?php if (date('Y-m-d', strtotime($solicitud['fecha_solicitud'])) === date('Y-m-d')): ?>
                                <span class="nuevo-badge">
                                    <i class="fas fa-star"></i> NUEVO
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="solicitud-body">
                            <div class="proyecto-info-box">
                                <div class="proyecto-header">
                                    <div class="proyecto-icon">
                                        <i class="fas fa-project-diagram"></i>
                                    </div>
                                    <div class="proyecto-content">
                                        <h4><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></h4>
                                        <p><?= htmlspecialchars(substr($solicitud['proyecto_descripcion'], 0, 150)) ?>...</p>
                                        <div class="proyecto-stats">
                                            <span class="proyecto-stat">
                                                <i class="fas fa-users"></i>
                                                <?= $solicitud['cupo_ocupado'] ?>/<?= $solicitud['cupo_disponible'] ?> lugares ocupados
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="periodo-info">
                                <div class="info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <div>
                                        <label>Periodo Propuesto</label>
                                        <span><?= formatDate($solicitud['fecha_inicio_propuesta']) ?> - <?= formatDate($solicitud['fecha_fin_propuesta']) ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <label>Duración</label>
                                        <span>
                                            <?php 
                                            $inicio = new DateTime($solicitud['fecha_inicio_propuesta']);
                                            $fin = new DateTime($solicitud['fecha_fin_propuesta']);
                                            $diff = $inicio->diff($fin);
                                            echo $diff->m + ($diff->y * 12) . ' meses';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-file-alt"></i>
                                    <div>
                                        <label>Estado del Servicio</label>
                                        <span><?= getEstadoText($solicitud['estado']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="solicitud-actions">
                            <?php if ($solicitud['estado'] === 'pendiente'): ?>
                                <button type="button" 
                                        class="btn btn-success btn-sm" 
                                        onclick="aprobarSolicitud(<?= $solicitud['id'] ?>)">
                                    <i class="fas fa-check"></i> Aprobar
                                </button>
                                <button type="button" 
                                        class="btn btn-error btn-sm" 
                                        onclick="mostrarModalRechazo(<?= $solicitud['id'] ?>)">
                                    <i class="fas fa-times"></i> Rechazar
                                </button>
                                <a href="solicitud-detalle.php?id=<?= $solicitud['id'] ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </a>
                            <?php else: ?>
                                <a href="solicitud-detalle.php?id=<?= $solicitud['id'] ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </a>
                                <?php if ($solicitud['estado'] === 'en_proceso'): ?>
                                <a href="estudiante-detalle.php?estudiante_id=<?= $solicitud['estudiante_id'] ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-user"></i> Ver Estudiante
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($solicitud['estado'] === 'rechazada' && !empty($solicitud['motivo_rechazo'])): ?>
                        <div class="rechazo-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Motivo del rechazo:</strong>
                                <p><?= htmlspecialchars($solicitud['motivo_rechazo']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>No hay solicitudes</h3>
                    <p>No se encontraron solicitudes que coincidan con los filtros seleccionados.</p>
                    <?php if ($filtroEstado !== 'todos' || $filtroProyecto !== 'todos' || $filtroCarrera !== 'todos' || !empty($busqueda)): ?>
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

<!-- Modal de Rechazo -->
<div id="modalRechazo" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle"></i> Rechazar Solicitud</h3>
            <button type="button" class="close-modal" onclick="cerrarModalRechazo()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="formRechazo">
            <input type="hidden" name="action" value="rechazar">
            <input type="hidden" name="solicitud_id" id="rechazo_solicitud_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="motivo_rechazo">
                        <i class="fas fa-comment"></i>
                        Motivo del Rechazo *
                    </label>
                    <textarea 
                        id="motivo_rechazo" 
                        name="motivo_rechazo" 
                        class="form-control" 
                        rows="4" 
                        required
                        placeholder="Explique las razones por las cuales rechaza esta solicitud..."></textarea>
                    <small>Este motivo será visible para el estudiante</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalRechazo()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-error">
                    <i class="fas fa-check"></i> Confirmar Rechazo
                </button>
            </div>
        </form>
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

.stat-card.total { --gradient-color: var(--primary); }
.stat-card.pendientes { --gradient-color: var(--warning); }
.stat-card.aprobadas { --gradient-color: var(--success); }
.stat-card.nuevas { --gradient-color: var(--secondary); }

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
.stat-card.pendientes .stat-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.stat-card.aprobadas .stat-icon { background: linear-gradient(135deg, var(--success), #34d399); }
.stat-card.nuevas .stat-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }

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

.stat-trend, .stat-alert {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.stat-trend { color: var(--success); }
.stat-alert { color: var(--warning); }

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

/* Solicitudes Section */
.solicitudes-section {
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

/* Solicitudes List */
.solicitudes-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.solicitud-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 2px solid transparent;
    transition: var(--transition);
}

.solicitud-card.pendiente-highlight {
    border-color: var(--warning);
    background: linear-gradient(to right, rgba(245, 158, 11, 0.05), var(--bg-light));
}

.solicitud-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.solicitud-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border);
}

.student-info-main {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.student-details h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.solicitud-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.solicitud-fecha {
    font-size: 0.85rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.nuevo-badge {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.solicitud-body {
    margin-bottom: 1.5rem;
}

.proyecto-info-box {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
}

.proyecto-header {
    display: flex;
    gap: 1rem;
}

.proyecto-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.proyecto-content {
    flex: 1;
}

.proyecto-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.proyecto-content p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.75rem 0;
    line-height: 1.5;
}

.proyecto-stats {
    display: flex;
    gap: 1rem;
}

.proyecto-stat {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.periodo-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.info-item i {
    width: 20px;
    color: var(--primary);
    font-size: 1rem;
    margin-top: 0.25rem;
}

.info-item label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.info-item span {
    font-size: 0.95rem;
    color: var(--text-primary);
}

.solicitud-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.rechazo-info {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: rgba(239, 68, 68, 0.05);
    border-left: 4px solid var(--error);
    border-radius: var(--radius);
}

.rechazo-info i {
    color: var(--error);
    font-size: 1.25rem;
}

.rechazo-info strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.rechazo-info p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
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

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: var(--shadow-lg);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.close-modal {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--bg-gray);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-secondary);
}

.close-modal:hover {
    background: var(--error);
    color: white;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body textarea {
    width: 100%;
    min-height: 120px;
    resize: vertical;
}

.modal-body small {
    display: block;
    margin-top: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
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

.btn-error {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-error:hover {
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
    
    .solicitud-header {
        flex-direction: column;
    }
    
    .solicitud-status {
        align-items: flex-start;
    }
    
    .periodo-info {
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
    
    .solicitud-actions {
        flex-direction: column;
    }
    
    .solicitud-actions .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .solicitud-card {
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
});

function limpiarFiltros() {
    window.location.href = 'estudiantes-solicitudes.php';
}

function aprobarSolicitud(solicitudId) {
    if (confirm('¿Está seguro de que desea aprobar esta solicitud?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="aprobar">
            <input type="hidden" name="solicitud_id" value="${solicitudId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function mostrarModalRechazo(solicitudId) {
    document.getElementById('rechazo_solicitud_id').value = solicitudId;
    document.getElementById('modalRechazo').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalRechazo() {
    document.getElementById('modalRechazo').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('formRechazo').reset();
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalRechazo')?.addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalRechazo();
    }
});

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalRechazo();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>