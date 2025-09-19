<?php
require_once '../config/config.php';
require_once '../config/database.php'; // Agregar explícitamente
require_once '../config/session.php';
require_once '../config/functions.php';

// Inicializar buffer de salida para evitar problemas con headers
ob_start();

$session = SecureSession::getInstance();

// Verificación robusta de autenticación
if (!$session->isLoggedIn()) {
    // Limpiar cualquier output antes de redireccionar
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: ../auth/login.php");
    exit();
}

// Verificación robusta del rol
$userRole = $session->getUserRole();
if ($userRole !== 'estudiante') {
    // Limpiar cualquier output antes de redireccionar
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Redirigir al dashboard correcto
    if (in_array($userRole, ['jefe_departamento', 'jefe_laboratorio'])) {
        header("Location: ../dashboard/$userRole.php");
    } else {
        // Rol no válido, cerrar sesión
        $session->destroy();
        header("Location: ../auth/login.php");
    }
    exit();
}

$db = Database::getInstance();
$usuario = $session->getUser();

// Verificación robusta del usuario
if (!$usuario || !isset($usuario['id']) || empty($usuario['id'])) {
    error_log("Usuario incompleto en sesión: " . print_r($usuario, true));
    
    // Limpiar cualquier output antes de redireccionar
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Destruir sesión corrupta y redirigir
    $session->destroy();
    header("Location: ../auth/login.php");
    exit();
}

$estudianteId = $usuario['id'];

try {
    // Obtener datos del estudiante con manejo de errores
    $estudiante = $db->fetch("
        SELECT e.*, u.email 
        FROM estudiantes e 
        JOIN usuarios u ON e.usuario_id = u.id 
        WHERE e.usuario_id = ?
    ", [$estudianteId]);

    // Verificar que se encontró el estudiante
    if (!$estudiante) {
        error_log("No se encontró estudiante para usuario ID: $estudianteId");
        throw new Exception("Datos del estudiante no encontrados");
    }

    // Obtener solicitud activa
    $solicitudActiva = $db->fetch("
        SELECT s.*, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio,
               jd.nombre as jefe_depto_nombre
        FROM solicitudes_servicio s
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
        JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
        WHERE s.estudiante_id = :estudiante_id 
        AND s.estado IN ('pendiente', 'aprobada', 'en_proceso')
        ORDER BY s.fecha_solicitud DESC
        LIMIT 1
    ", ['estudiante_id' => $estudiante['id']]);

    // Obtener reportes pendientes solo si hay solicitud activa
    $reportesPendientes = [];
    if ($solicitudActiva && $solicitudActiva['estado'] === 'en_proceso') {
        $reportesPendientes = $db->fetchAll("
            SELECT r.* 
            FROM reportes_bimestrales r
            WHERE r.solicitud_id = :solicitud_id
            AND r.estado = 'pendiente_evaluacion'
            ORDER BY r.numero_reporte
        ", ['solicitud_id' => $solicitudActiva['id']]);
    }

    // Obtener documentos recientes
    $documentos = [];

    // Oficios
    $oficios = $db->fetchAll("
        SELECT 'oficio' as tipo, numero_oficio as numero, fecha_emision as fecha, archivo_path
        FROM oficios_presentacion op
        JOIN solicitudes_servicio s ON op.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
        ORDER BY fecha_emision DESC
        LIMIT 3
    ", ['estudiante_id' => $estudiante['id']]);

    // Constancias
    $constancias = $db->fetchAll("
        SELECT 'constancia' as tipo, numero_constancia as numero, fecha_emision as fecha, archivo_path
        FROM constancias
        WHERE estudiante_id = :estudiante_id
        ORDER BY fecha_emision DESC
        LIMIT 3
    ", ['estudiante_id' => $estudiante['id']]);

    $documentos = array_merge($oficios, $constancias);

    // Calcular estadísticas
    $horasRequeridas = 500;
    $horasCompletadas = $estudiante['horas_completadas'] ?? 0;
    $progreso = min(100, ($horasCompletadas / $horasRequeridas) * 100);

    // Obtener estadísticas adicionales
    $totalReportesResult = $db->fetch("
        SELECT COUNT(*) as total
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
    ", ['estudiante_id' => $estudiante['id']]);
    $totalReportes = $totalReportesResult ? $totalReportesResult['total'] : 0;

    $reportesAprobadosResult = $db->fetch("
        SELECT COUNT(*) as total
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id AND r.estado = 'aprobado'
    ", ['estudiante_id' => $estudiante['id']]);
    $reportesAprobados = $reportesAprobadosResult ? $reportesAprobadosResult['total'] : 0;

} catch (Exception $e) {
    error_log("Error en dashboard estudiante: " . $e->getMessage());
    
    // Limpiar cualquier output antes de redireccionar
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // En caso de error, mostrar mensaje y redirigir
    // Guardar mensaje flash en la sesión
    $_SESSION['flash_error'] = 'Error al cargar el dashboard. Inténtalo nuevamente.';
    header("Location: ../auth/login.php");
    exit();
}

$pageTitle = "Dashboard Estudiante - " . APP_NAME;
$dashboardJS = true;
$chartsJS = true;

include '../includes/header.php';
include '../includes/sidebar.php';

// Limpiar buffer de salida después de incluir archivos
ob_end_flush();
?>
<!-- EL RESTO DEL HTML Y CSS VA IGUAL QUE EN EL ARCHIVO ORIGINAL -->

<div class="dashboard-container">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="dashboard-title">
                    ¡Hola, <?= htmlspecialchars(explode(' ', $estudiante['nombre'])[0]) ?>! 👋
                </h1>
                <p class="dashboard-subtitle">
                    Bienvenido a tu panel de control de servicio social. Aquí puedes ver tu progreso y gestionar todas tus actividades.
                </p>
            </div>
            <div class="header-actions">
                <div class="quick-info">
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= formatDate(date('Y-m-d')) ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span id="currentTime"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="stats-grid">
        <!-- Estado del servicio -->
        <div class="stat-card primary">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-badge">
                    <span class="badge <?= getEstadoBadgeClass($estudiante['estado_servicio']) ?>">
                        <?= getEstadoText($estudiante['estado_servicio']) ?>
                    </span>
                </div>
            </div>
            <div class="stat-content">
                <h3>Estado del Servicio</h3>
                <?php if ($solicitudActiva): ?>
                    <p class="stat-detail"><?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?></p>
                    <?php if ($solicitudActiva['laboratorio']): ?>
                        <p class="stat-sub"><?= htmlspecialchars($solicitudActiva['laboratorio']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="stat-detail">Sin solicitud activa</p>
                    <p class="stat-sub">¡Comienza tu servicio social!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Horas completadas -->
        <div class="stat-card success">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?= $horasCompletadas ?></div>
            </div>
            <div class="stat-content">
                <h3>Horas Completadas</h3>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progreso ?>%" data-percentage="<?= round($progreso, 1) ?>"></div>
                    </div>
                    <div class="progress-info">
                        <span><?= $horasCompletadas ?> / <?= $horasRequeridas ?> horas</span>
                        <span class="progress-percentage"><?= number_format($progreso, 1) ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reportes -->
        <div class="stat-card warning">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number"><?= count($reportesPendientes) ?></div>
            </div>
            <div class="stat-content">
                <h3>Reportes Pendientes</h3>
                <p class="stat-detail"><?= $reportesAprobados ?> de <?= $totalReportes ?> aprobados</p>
                <?php if ($solicitudActiva && $solicitudActiva['estado'] === 'en_proceso'): ?>
                    <a href="../modules/estudiantes/reportes.php" class="stat-action">
                        <i class="fas fa-arrow-right"></i>
                        Ver Reportes
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calificación promedio -->
        <div class="stat-card info">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number">8.5</div>
            </div>
            <div class="stat-content">
                <h3>Calificación Promedio</h3>
                <div class="rating-stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                </div>
                <p class="stat-detail">Excelente desempeño</p>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="main-grid">
        <!-- Left Column -->
        <div class="main-content">
            <!-- Estado actual y acciones -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i>
                        Estado Actual
                    </h2>
                </div>

                <?php if (!$solicitudActiva): ?>
                    <!-- Sin solicitud -->
                    <div class="action-card highlight">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <div class="action-text">
                                <h3>¡Comienza tu Servicio Social!</h3>
                                <p>Es hora de dar el primer paso. Solicita tu servicio social eligiendo un proyecto en los laboratorios del ITA.</p>
                                <div class="action-benefits">
                                    <div class="benefit-item">
                                        <i class="fas fa-check"></i>
                                        <span>Proceso 100% digital</span>
                                    </div>
                                    <div class="benefit-item">
                                        <i class="fas fa-check"></i>
                                        <span>Seguimiento en tiempo real</span>
                                    </div>
                                    <div class="benefit-item">
                                        <i class="fas fa-check"></i>
                                        <span>Múltiples proyectos disponibles</span>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="../modules/estudiantes/solicitud.php" class="btn btn-primary btn-large">
                                    <i class="fas fa-paper-plane"></i>
                                    Crear Solicitud
                                </a>
                                <a href="../help/como-empezar.php" class="btn btn-secondary">
                                    <i class="fas fa-question-circle"></i>
                                    ¿Cómo empezar?
                                </a>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($solicitudActiva['estado'] === 'pendiente'): ?>
                    <!-- Solicitud pendiente -->
                    <div class="action-card pending">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="action-text">
                                <h3>Solicitud en Revisión</h3>
                                <p>Tu solicitud está siendo revisada por el jefe de departamento. Te notificaremos tan pronto sea aprobada.</p>
                                <div class="timeline">
                                    <div class="timeline-item completed">
                                        <i class="fas fa-check"></i>
                                        <span>Solicitud enviada</span>
                                    </div>
                                    <div class="timeline-item active">
                                        <i class="fas fa-clock"></i>
                                        <span>En revisión</span>
                                    </div>
                                    <div class="timeline-item">
                                        <i class="fas fa-thumbs-up"></i>
                                        <span>Aprobación</span>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="../modules/estudiantes/solicitud-detalle.php?id=<?= $solicitudActiva['id'] ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i>
                                    Ver Solicitud
                                </a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($solicitudActiva['estado'] === 'aprobada'): ?>
                    <!-- Solicitud aprobada -->
                    <div class="action-card success">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="action-text">
                                <h3>¡Solicitud Aprobada!</h3>
                                <p>¡Felicidades! Tu solicitud ha sido aprobada. Descarga tu oficio de presentación y comienza tu servicio social.</p>
                                <div class="success-info">
                                    <div class="info-row">
                                        <span class="info-label">Proyecto:</span>
                                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['nombre_proyecto']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Laboratorio:</span>
                                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['laboratorio']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Supervisor:</span>
                                        <span class="info-value"><?= htmlspecialchars($solicitudActiva['jefe_lab_nombre']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="../modules/estudiantes/documentos.php" class="btn btn-success">
                                    <i class="fas fa-download"></i>
                                    Descargar Oficio
                                </a>
                                <a href="../modules/estudiantes/reportes.php" class="btn btn-primary">
                                    <i class="fas fa-play"></i>
                                    Comenzar Servicio
                                </a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($solicitudActiva['estado'] === 'en_proceso'): ?>
                    <!-- En proceso -->
                    <div class="action-card active">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="action-text">
                                <h3>Servicio Social en Proceso</h3>
                                <p>¡Excelente! Estás en el proceso activo de tu servicio social. Mantén tu progreso al día.</p>
                                
                                <?php if (!empty($reportesPendientes)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>
                                            <strong>Tienes <?= count($reportesPendientes) ?> reporte(s) pendiente(s)</strong>
                                            <p>Recuerda entregar tus reportes bimestrales a tiempo para mantener tu servicio social activo.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="progress-summary">
                                    <div class="progress-item">
                                        <span class="progress-label">Progreso total</span>
                                        <div class="progress-visual">
                                            <div class="mini-progress">
                                                <div class="mini-fill" style="width: <?= $progreso ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?= number_format($progreso, 1) ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <?php if (!empty($reportesPendientes)): ?>
                                    <a href="../modules/estudiantes/reportes.php" class="btn btn-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Entregar Reportes
                                    </a>
                                <?php else: ?>
                                    <a href="../modules/estudiantes/reportes.php" class="btn btn-primary">
                                        <i class="fas fa-file-alt"></i>
                                        Gestionar Reportes
                                    </a>
                                <?php endif; ?>
                                <a href="../modules/estudiantes/horas.php" class="btn btn-secondary">
                                    <i class="fas fa-clock"></i>
                                    Registrar Horas
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actividades recientes -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Actividades Recientes
                    </h2>
                    <a href="../modules/estudiantes/actividades.php" class="section-action">Ver todas</a>
                </div>

                <div class="timeline-container">
                    <div class="timeline-item">
                        <div class="timeline-marker success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Reporte Bimestral Aprobado</h4>
                            <p>Tu cuarto reporte bimestral ha sido aprobado con calificación de 9.0</p>
                            <span class="timeline-date">Hace 2 días</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker info">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Horas Registradas</h4>
                            <p>Registraste 40 horas de actividades del 1-15 de noviembre</p>
                            <span class="timeline-date">Hace 5 días</span>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker warning">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Reporte Entregado</h4>
                            <p>Enviaste tu cuarto reporte bimestral para revisión</p>
                            <span class="timeline-date">Hace 1 semana</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="sidebar-content">
            <!-- Progreso visual -->
            <div class="widget-card">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-chart-pie"></i>
                        Tu Progreso
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="circular-progress">
                        <svg class="progress-ring" width="120" height="120">
                            <circle class="progress-ring-circle" 
                                    cx="60" cy="60" r="54"
                                    style="--progress: <?= $progreso ?>">
                            </circle>
                        </svg>
                        <div class="progress-text">
                            <span class="progress-number"><?= number_format($progreso, 1) ?>%</span>
                            <span class="progress-label">Completado</span>
                        </div>
                    </div>
                    
                    <div class="progress-details">
                        <div class="detail-item">
                            <span class="detail-label">Horas restantes</span>
                            <span class="detail-value"><?= max(0, $horasRequeridas - $horasCompletadas) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tiempo promedio</span>
                            <span class="detail-value">5 hrs/sem</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tiempo estimado</span>
                            <span class="detail-value"><?= ceil(max(0, $horasRequeridas - $horasCompletadas) / 5) ?> semanas</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documentos recientes -->
            <?php if ($documentos): ?>
            <div class="widget-card">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-file-download"></i>
                        Documentos
                    </h3>
                    <a href="../modules/estudiantes/documentos.php" class="widget-action">Ver todos</a>
                </div>
                <div class="widget-content">
                    <div class="documents-list">
                        <?php foreach (array_slice($documentos, 0, 3) as $doc): ?>
                        <div class="document-item">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4><?= ucfirst($doc['tipo']) ?></h4>
                                <p><?= htmlspecialchars($doc['numero']) ?></p>
                                <span class="document-date"><?= formatDate($doc['fecha']) ?></span>
                            </div>
                            <?php if ($doc['archivo_path']): ?>
                            <a href="<?= (defined('UPLOAD_URL') ? UPLOAD_URL : '../uploads/') . $doc['archivo_path'] ?>" 
                               target="_blank" 
                               class="document-download"
                               title="Descargar">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enlaces rápidos -->
            <div class="widget-card">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-link"></i>
                        Enlaces Rápidos
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="quick-links">
                        <a href="../modules/estudiantes/perfil.php" class="quick-link">
                            <div class="link-icon primary">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="link-text">
                                <span>Mi Perfil</span>
                                <small>Actualizar información personal</small>
                            </div>
                        </a>
                        
                        <a href="../modules/estudiantes/solicitud.php" class="quick-link">
                            <div class="link-icon success">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="link-text">
                                <span>Nueva Solicitud</span>
                                <small>Crear solicitud de servicio</small>
                            </div>
                        </a>
                        
                        <a href="../help.php" class="quick-link">
                            <div class="link-icon info">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="link-text">
                                <span>Ayuda</span>
                                <small>Obtener soporte técnico</small>
                            </div>
                        </a>
                        
                        <a href="../contacto.php" class="quick-link">
                            <div class="link-icon warning">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="link-text">
                                <span>Contacto</span>
                                <small>Enviar mensaje</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Container */
.dashboard-container {
    padding: 1rem;
    min-height: calc(100vh - var(--header-height));
    background: var(--bg-light);
    overflow-x: hidden;
    max-width: 100vw;
    box-sizing: border-box;
}

/* Dashboard Header */
.dashboard-header {
    margin-bottom: 2rem;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-text {
    flex: 1;
    min-width: 300px;
}

.dashboard-title {
    font-size: clamp(1.5rem, 4vw, 2.5rem);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    line-height: 1.2;
    word-break: break-word;
}

.dashboard-subtitle {
    font-size: clamp(0.9rem, 2.5vw, 1.125rem);
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.quick-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
    background: var(--bg-white);
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    white-space: nowrap;
}

.info-item i {
    color: var(--primary);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
    width: 100%;
    max-width: 100%;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    min-width: 0;
    max-width: 100%;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.stat-card.primary::before {
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.stat-card.success::before {
    background: linear-gradient(90deg, var(--success), #34d399);
}

.stat-card.warning::before {
    background: linear-gradient(90deg, var(--warning), #fbbf24);
}

.stat-card.info::before {
    background: linear-gradient(90deg, var(--info), #60a5fa);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    box-shadow: var(--shadow);
    flex-shrink: 0;
}

.stat-card.success .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.warning .stat-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-card.info .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-content h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.stat-detail {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0.5rem 0;
    font-weight: 500;
    word-break: break-word;
}

.stat-sub {
    font-size: 0.8rem;
    color: var(--text-light);
    margin: 0.25rem 0;
}

.stat-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    margin-top: 0.75rem;
    transition: var(--transition);
}

.stat-action:hover {
    color: var(--primary-dark);
    transform: translateX(3px);
}

/* Progress Bar */
.progress-container {
    margin-top: 1rem;
}

.progress-bar {
    height: 8px;
    background: var(--bg-light);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.75rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #34d399);
    border-radius: 4px;
    transition: width 1s ease-out;
    position: relative;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.progress-percentage {
    font-weight: 600;
    color: var(--success);
}

/* Rating Stars */
.rating-stars {
    display: flex;
    gap: 0.25rem;
    margin: 0.5rem 0;
    color: #fbbf24;
}

/* Badges */
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge.active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.badge.completed {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

/* Main Grid */
.main-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    width: 100%;
    max-width: 100%;
}

/* Content Sections */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: clamp(1.25rem, 3vw, 1.5rem);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-title i {
    color: var(--primary);
}

.section-action {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
    flex-shrink: 0;
}

.section-action:hover {
    text-decoration: underline;
}

/* Action Cards */
.action-card {
    background: var(--bg-white);
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary);
}

.action-card.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 140, 247, 0.05) 100%);
    border-color: rgba(99, 102, 241, 0.3);
}

.action-card.highlight::before {
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

.action-card.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(52, 211, 153, 0.05) 100%);
    border-color: rgba(16, 185, 129, 0.3);
}

.action-card.success::before {
    background: var(--success);
}

.action-card.pending {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(251, 191, 36, 0.05) 100%);
    border-color: rgba(245, 158, 11, 0.3);
}

.action-card.pending::before {
    background: var(--warning);
}

.action-card.active {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(96, 165, 250, 0.05) 100%);
    border-color: rgba(59, 130, 246, 0.3);
}

.action-card.active::before {
    background: var(--info);
}

.action-content {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    flex-wrap: wrap;
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    flex-shrink: 0;
    box-shadow: var(--shadow);
}

.action-card.success .action-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.action-card.pending .action-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.action-card.active .action-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.action-text {
    flex: 1;
    min-width: 250px;
}

.action-text h3 {
    font-size: clamp(1.125rem, 3vw, 1.5rem);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.action-text p {
    font-size: 0.95rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.action-benefits {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 1.5rem 0;
}

.benefit-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.benefit-item i {
    color: var(--success);
    font-size: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
    width: 100%;
}

.action-buttons .btn {
    flex: 1;
    min-width: 140px;
    text-align: center;
    justify-content: center;
}

/* Timeline */
.timeline {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.timeline-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
    position: relative;
    padding-right: 1rem;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    right: -0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1rem;
    height: 2px;
    background: var(--border);
}

.timeline-item.completed {
    color: var(--success);
}

.timeline-item.completed::after {
    background: var(--success);
}

.timeline-item.active {
    color: var(--warning);
}

.timeline-item.active::after {
    background: var(--warning);
}

/* Success Info */
.success-info {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.info-value {
    font-size: 0.875rem;
    color: var(--text-primary);
    font-weight: 600;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin: 1.5rem 0;
    border: 1px solid;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border-color: rgba(245, 158, 11, 0.3);
}

.alert i {
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.alert strong {
    display: block;
    margin-bottom: 0.25rem;
}

.alert p {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.4;
}

/* Progress Summary */
.progress-summary {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
    margin: 1.5rem 0;
}

.progress-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-visual {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.mini-progress {
    width: 100px;
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}

.mini-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #34d399);
    border-radius: 3px;
    transition: width 1s ease-out;
}

.progress-text {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--success);
    min-width: 40px;
}

/* Timeline Container */
.timeline-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.timeline-container .timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0;
    position: relative;
}

.timeline-container .timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 16px;
    top: 40px;
    bottom: -24px;
    width: 2px;
    background: var(--border-light);
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    color: white;
    flex-shrink: 0;
    box-shadow: var(--shadow-sm);
}

.timeline-marker.success {
    background: var(--success);
}

.timeline-marker.info {
    background: var(--info);
}

.timeline-marker.warning {
    background: var(--warning);
}

.timeline-content {
    flex: 1;
    padding-top: 0.25rem;
}

.timeline-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.timeline-content p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.5;
    margin: 0 0 0.5rem 0;
}

.timeline-date {
    font-size: 0.75rem;
    color: var(--text-light);
    font-weight: 500;
}

/* Widget Cards - Sidebar Content */
.sidebar-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
    max-width: 100%;
}

.widget-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 0 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.widget-title i {
    color: var(--primary);
    font-size: 0.9rem;
}

.widget-action {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: var(--transition);
    flex-shrink: 0;
}

.widget-action:hover {
    text-decoration: underline;
}

.widget-content {
    padding: 0 1.5rem 1.5rem 1.5rem;
}

/* Circular Progress */
.circular-progress {
    position: relative;
    display: flex;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.progress-ring {
    transform: rotate(-90deg);
    width: 100px;
    height: 100px;
}

.progress-ring-circle {
    fill: none;
    stroke: var(--border-light);
    stroke-width: 6;
    stroke-dasharray: 282.743;
    stroke-dashoffset: calc(282.743 - (282.743 * var(--progress)) / 100);
    transition: stroke-dashoffset 1.5s ease-out;
}

.progress-ring-circle {
    stroke: var(--success);
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-number {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.progress-text .progress-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 0.25rem;
}

/* Progress Details */
.progress-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.85rem;
}

.detail-label {
    color: var(--text-secondary);
}

.detail-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Documents List */
.documents-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.document-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.document-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    background: var(--error);
    flex-shrink: 0;
}

.document-info {
    flex: 1;
    min-width: 0;
}

.document-info h4 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.document-info p {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.document-date {
    font-size: 0.7rem;
    color: var(--text-light);
}

.document-download {
    width: 28px;
    height: 28px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    text-decoration: none;
    transition: var(--transition);
    flex-shrink: 0;
    font-size: 0.8rem;
}

.document-download:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

/* Quick Links */
.quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
}

.quick-link:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
    transform: translateX(5px);
}

.link-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: white;
    flex-shrink: 0;
}

.link-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.link-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.link-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.link-icon.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.link-text {
    flex: 1;
}

.link-text span {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.link-text small {
    font-size: 0.7rem;
    color: var(--text-secondary);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
    box-shadow: var(--shadow);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
    box-shadow: var(--shadow);
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
    box-shadow: var(--shadow);
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

/* Responsive Design */
@media (min-width: 1200px) {
    .dashboard-container {
        padding: 2rem;
    }
    
    .main-grid {
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }
    
    .header-content {
        gap: 2rem;
    }
}

@media (min-width: 768px) and (max-width: 1199px) {
    .dashboard-container {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }
    
    .action-content {
        gap: 1.5rem;
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
}

@media (max-width: 767px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .dashboard-header {
        margin-bottom: 1.5rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .quick-info {
        align-items: flex-start;
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-header {
        margin-bottom: 0.75rem;
        gap: 0.75rem;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.125rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .content-section {
        padding: 1.25rem;
        margin-bottom: 1.25rem;
    }
    
    .action-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .action-icon {
        align-self: center;
    }
    
    .action-text {
        min-width: auto;
    }
    
    .action-buttons {
        justify-content: center;
        gap: 0.5rem;
    }
    
    .action-buttons .btn {
        min-width: 120px;
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
    }
    
    .timeline {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .timeline-item:not(:last-child)::after {
        display: none;
    }
    
    .timeline-container {
        gap: 1rem;
    }
    
    .timeline-container .timeline-item:not(:last-child)::before {
        bottom: -16px;
    }
    
    .timeline-marker {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
    
    .circular-progress {
        margin-bottom: 1rem;
    }
    
    .progress-ring {
        width: 80px;
        height: 80px;
    }
    
    .progress-ring-circle {
        stroke-dasharray: 226.195;
        stroke-dashoffset: calc(226.195 - (226.195 * var(--progress)) / 100);
    }
    
    .progress-number {
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .dashboard-title {
        font-size: 1.5rem;
    }
    
    .dashboard-subtitle {
        font-size: 0.9rem;
    }
    
    .info-item {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .stat-number {
        font-size: 1.25rem;
    }
    
    .content-section {
        padding: 1rem;
    }
    
    .section-title {
        font-size: 1.125rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        min-width: auto;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .widget-header {
        padding: 1rem 1rem 0 1rem;
    }
    
    .widget-content {
        padding: 0 1rem 1rem 1rem;
    }
    
    .document-item {
        padding: 0.75rem;
        gap: 0.75rem;
    }
    
    .document-icon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
    }
    
    .document-download {
        width: 26px;
        height: 26px;
        font-size: 0.7rem;
    }
    
    .link-icon {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
    
    .quick-link {
        padding: 0.75rem;
        gap: 0.75rem;
    }
    
    .link-text span {
        font-size: 0.8rem;
    }
    
    .link-text small {
        font-size: 0.65rem;
    }
}

/* Utility Classes */
.text-center {
    text-align: center !important;
}

.text-left {
    text-align: left !important;
}

.text-right {
    text-align: right !important;
}

.d-none {
    display: none !important;
}

.d-block {
    display: block !important;
}

.d-flex {
    display: flex !important;
}

.w-100 {
    width: 100% !important;
}

.overflow-hidden {
    overflow: hidden !important;
}

.position-relative {
    position: relative !important;
}

/* Print Styles */
@media print {
    .dashboard-container {
        padding: 0;
        background: white;
    }
    
    .action-buttons,
    .quick-info,
    .widget-action,
    .section-action,
    .document-download,
    .quick-links {
        display: none !important;
    }
    
    .stat-card,
    .action-card,
    .widget-card,
    .content-section {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ccc;
    }
}

/* Dark mode support (for future implementation) */
@media (prefers-color-scheme: dark) {
    .dashboard-container {
        background: #1a1a1a;
    }
    
    .stat-card,
    .action-card,
    .widget-card,
    .content-section {
        background: #2d2d2d;
        border-color: #404040;
        color: #e5e5e5;
    }
}

/* Reduced motion for accessibility */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Animation Classes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

.animate-slide-in-right {
    animation: slideInRight 0.6s ease-out;
}

/* Loading states */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

.skeleton {
    background: linear-gradient(90deg, var(--bg-light) 25%, var(--border-light) 50%, var(--bg-light) 75%);
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }
    
    updateTime();
    setInterval(updateTime, 1000);
    
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-fill');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const percentage = progressBar.dataset.percentage || progressBar.style.width;
                progressBar.style.width = percentage;
                observer.unobserve(progressBar);
            }
        });
    });
    
    progressBars.forEach(bar => {
        bar.style.width = '0%';
        observer.observe(bar);
    });
    
    // Animate circular progress
    const circularProgress = document.querySelector('.progress-ring-circle');
    if (circularProgress) {
        setTimeout(() => {
            circularProgress.style.strokeDashoffset = circularProgress.style.strokeDashoffset;
        }, 500);
    }
    
    // Add click effects to cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('a')) {
                this.style.transform = 'translateY(-8px) scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            }
        });
    });
    
    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.href && this.href.includes('modules/')) {
                this.classList.add('loading');
                this.style.pointerEvents = 'none';
                
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
                
                // Reset after 3 seconds in case navigation fails
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.style.pointerEvents = '';
                    this.innerHTML = originalText;
                }, 3000);
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Add hover effects to timeline items
    const timelineItems = document.querySelectorAll('.timeline-container .timeline-item');
    timelineItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Progressive enhancement for document downloads
    const downloadLinks = document.querySelectorAll('.document-download');
    downloadLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            this.style.transform = 'scale(1.2)';
            setTimeout(() => {
                this.style.transform = '';
            }, 200);
        });
    });
    
    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.stat-card, .action-card, .widget-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease-out';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add typing effect to dashboard title
    const title = document.querySelector('.dashboard-title');
    if (title) {
        const text = title.textContent;
        title.textContent = '';
        let i = 0;
        
        function typeWriter() {
            if (i < text.length) {
                title.textContent += text.charAt(i);
                i++;
                setTimeout(typeWriter, 50);
            }
        }
        
        setTimeout(typeWriter, 300);
    }
});
</script>

<?php include '../includes/footer.php'; ?>