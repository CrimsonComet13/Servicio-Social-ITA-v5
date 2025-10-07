<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();

// Verificar autenticación y rol
if (!$session->isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

if ($session->getUserRole() !== 'estudiante') {
    header("Location: ../dashboard/" . $session->getUserRole() . ".php");
    exit();
}

$db = Database::getInstance();
$usuario = $session->getUser();

// Verificar que el usuario esté completo
if (!$usuario || !isset($usuario['id'])) {
    // Limpiar sesión corrupta y redirigir
    $session->destroy();
    header("Location: ../auth/login.php");
    exit();
}

// ✅ CORRECCIÓN: Lógica consistente para obtener IDs
function obtenerDatosEstudiante($usuario, $db) {
    $usuarioId = $usuario['id'] ?? $usuario['usuario_id'] ?? null;
    
    if (!$usuarioId) {
        throw new Exception('ID de usuario no válido en sesión');
    }
    
    // Obtener datos del estudiante usando usuario_id
    $estudiante = $db->fetch("
        SELECT e.*, u.email, u.email_verificado
        FROM estudiantes e 
        JOIN usuarios u ON e.usuario_id = u.id 
        WHERE e.usuario_id = ?
    ", [$usuarioId]);
    
    if (!$estudiante) {
        throw new Exception('No se encontraron datos de estudiante para usuario ID: ' . $usuarioId);
    }
    
    return $estudiante;
}

// Obtener datos del estudiante con manejo de errores
try {
    $estudiante = obtenerDatosEstudiante($usuario, $db);
} catch (Exception $e) {
    error_log("Error al obtener datos del estudiante: " . $e->getMessage());
    
    // Intentar reconstruir sesión
    $usuarioId = $usuario['id'] ?? $usuario['usuario_id'] ?? null;
    if ($usuarioId) {
        $datosCompletos = $db->fetch("
            SELECT u.id, u.email, u.tipo_usuario, u.activo, u.email_verificado,
                   e.id as estudiante_id, e.numero_control, e.nombre, e.apellido_paterno, 
                   e.apellido_materno, e.carrera, e.semestre, e.creditos_cursados, 
                   e.telefono, e.estado_servicio, e.horas_completadas
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.id = e.usuario_id
            WHERE u.id = ? AND u.tipo_usuario = 'estudiante'
        ", [$usuarioId]);
        
        if ($datosCompletos && $datosCompletos['estudiante_id']) {
            // Reconstruir sesión con estructura correcta
            $sessionData = [
                'id' => $datosCompletos['id'],
                'usuario_id' => $datosCompletos['id'],
                'email' => $datosCompletos['email'],
                'tipo_usuario' => $datosCompletos['tipo_usuario'],
                'activo' => $datosCompletos['activo'],
                'email_verificado' => $datosCompletos['email_verificado'],
                'perfil' => [
                    'id' => $datosCompletos['estudiante_id'],
                    'numero_control' => $datosCompletos['numero_control'],
                    'nombre' => $datosCompletos['nombre'],
                    'apellido_paterno' => $datosCompletos['apellido_paterno'],
                    'apellido_materno' => $datosCompletos['apellido_materno'],
                    'carrera' => $datosCompletos['carrera'],
                    'semestre' => $datosCompletos['semestre'],
                    'creditos_cursados' => $datosCompletos['creditos_cursados'],
                    'telefono' => $datosCompletos['telefono'],
                    'estado_servicio' => $datosCompletos['estado_servicio'],
                    'horas_completadas' => $datosCompletos['horas_completadas']
                ]
            ];
            
            $session->set('usuario', $sessionData);
            $usuario = $sessionData;
            
            // Usar datos reconstruidos
            $estudiante = [
                'id' => $datosCompletos['estudiante_id'],
                'usuario_id' => $datosCompletos['id'],
                'numero_control' => $datosCompletos['numero_control'],
                'nombre' => $datosCompletos['nombre'],
                'apellido_paterno' => $datosCompletos['apellido_paterno'],
                'apellido_materno' => $datosCompletos['apellido_materno'],
                'carrera' => $datosCompletos['carrera'],
                'semestre' => $datosCompletos['semestre'],
                'creditos_cursados' => $datosCompletos['creditos_cursados'],
                'telefono' => $datosCompletos['telefono'],
                'estado_servicio' => $datosCompletos['estado_servicio'],
                'horas_completadas' => $datosCompletos['horas_completadas'],
                'email' => $datosCompletos['email'],
                'email_verificado' => $datosCompletos['email_verificado']
            ];
            
            $sessionFixed = true;
        } else {
            // No se pudo recuperar, redirigir a login
            $session->destroy();
            header("Location: ../auth/login.php?error=session_corrupted");
            exit();
        }
    } else {
        $session->destroy();
        header("Location: ../auth/login.php?error=invalid_user");
        exit();
    }
} 

// Mostrar mensaje de reconstrucción si fue necesario
$fixMessage = isset($sessionFixed) && $sessionFixed ? [
    'show' => true,
    'message' => 'Sesión reconstruida automáticamente'
] : ['show' => false];

// Obtener solicitud activa con manejo de errores
try {
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
    ", ['estudiante_id' => $estudiante['id']]) ?: null;
} catch (Exception $e) {
    error_log("Error al obtener solicitud activa: " . $e->getMessage());
    $solicitudActiva = null;
}

// Obtener reportes pendientes - SOLO SI HAY SOLICITUD ACTIVA
$reportesPendientes = [];
if ($solicitudActiva && $solicitudActiva['estado'] === 'en_proceso') {
    try {
        $reportesPendientes = $db->fetchAll("
            SELECT r.* 
            FROM reportes_bimestrales r
            WHERE r.solicitud_id = :solicitud_id
            AND r.estado = 'pendiente_evaluacion'
            ORDER BY r.numero_reporte
        ", ['solicitud_id' => $solicitudActiva['id']]) ?: [];
    } catch (Exception $e) {
        error_log("Error al obtener reportes pendientes: " . $e->getMessage());
        $reportesPendientes = [];
    }
}

// Obtener documentos recientes con manejo de errores
$documentos = [];
try {
    // Oficios
    $oficios = $db->fetchAll("
        SELECT 'oficio' as tipo, numero_oficio as numero, fecha_emision as fecha, archivo_path
        FROM oficios_presentacion op
        JOIN solicitudes_servicio s ON op.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
        ORDER BY fecha_emision DESC
        LIMIT 3
    ", ['estudiante_id' => $estudiante['id']]) ?: [];

    // Constancias
    $constancias = $db->fetchAll("
        SELECT 'constancia' as tipo, numero_constancia as numero, fecha_emision as fecha, archivo_path
        FROM constancias
        WHERE estudiante_id = :estudiante_id
        ORDER BY fecha_emision DESC
        LIMIT 3
    ", ['estudiante_id' => $estudiante['id']]) ?: [];

    $documentos = array_merge($oficios, $constancias);
} catch (Exception $e) {
    error_log("Error al obtener documentos: " . $e->getMessage());
    $documentos = [];
}

// Calcular estadísticas
$horasRequeridas = 500;
$horasCompletadas = $estudiante['horas_completadas'] ?? 0;
$progreso = $horasRequeridas > 0 ? min(100, ($horasCompletadas / $horasRequeridas) * 100) : 0;

// Obtener estadísticas adicionales con manejo de errores
$totalReportes = 0;
$reportesAprobados = 0;

try {
    $totalReportesResult = $db->fetch("
        SELECT COUNT(*) as total
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
    ", ['estudiante_id' => $estudiante['id']]) ?: ['total' => 0];
    $totalReportes = $totalReportesResult['total'];

    $reportesAprobadosResult = $db->fetch("
        SELECT COUNT(*) as total
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id AND r.estado = 'aprobado'
    ", ['estudiante_id' => $estudiante['id']]) ?: ['total' => 0];
    $reportesAprobados = $reportesAprobadosResult['total'];
} catch (Exception $e) {
    error_log("Error al obtener estadísticas de reportes: " . $e->getMessage());
}

// Obtener actividades recientes reales
$actividadesRecientes = [];
try {
    $actividadesRecientes = $db->fetchAll("
        SELECT 
            'reporte' as tipo,
            CONCAT('Reporte ', r.numero_reporte, ' - ', r.estado) as titulo,
            CONCAT('Entregado el ', DATE_FORMAT(r.fecha_entrega, '%d/%m/%Y')) as descripcion,
            r.created_at as fecha,
            r.estado
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
        
        UNION ALL
        
        SELECT 
            'solicitud' as tipo,
            CONCAT('Solicitud de servicio - ', s.estado) as titulo,
            CONCAT('Proyecto: ', p.nombre_proyecto) as descripcion,
            s.created_at as fecha,
            s.estado
        FROM solicitudes_servicio s
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        WHERE s.estudiante_id = :estudiante_id
        
        ORDER BY fecha DESC
        LIMIT 5
    ", ['estudiante_id' => $estudiante['id']]) ?: [];
} catch (Exception $e) {
    error_log("Error al obtener actividades recientes: " . $e->getMessage());
    $actividadesRecientes = [];
}

// Funciones helper para el diseño
function getEstadoCssClass($estado) {
    switch($estado) {
        case 'pendiente': 
        case 'solicitud_pendiente': return 'pending';
        case 'aprobada': 
        case 'aprobado': return 'approved';
        case 'en_proceso': return 'in-progress';
        case 'completado': 
        case 'concluido': return 'completed';
        default: return 'pending';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'sin_solicitud': return 'plus-circle';
        case 'pendiente': 
        case 'solicitud_pendiente': return 'hourglass-half';
        case 'aprobada': 
        case 'aprobado': return 'check-circle';
        case 'en_proceso': return 'play-circle';
        case 'completado': 
        case 'concluido': return 'trophy';
        default: return 'question-circle';
    }
}

function getEstadoTitle($estado) {
    switch($estado) {
        case 'sin_solicitud': return 'Sin Solicitud Activa';
        case 'pendiente': 
        case 'solicitud_pendiente': return 'Solicitud en Revisión';
        case 'aprobada': 
        case 'aprobado': return 'Solicitud Aprobada';
        case 'en_proceso': return 'Servicio Social en Proceso';
        case 'completado': 
        case 'concluido': return 'Servicio Social Completado';
        default: return 'Estado del Servicio';
    }
}

function getEstadoTextDashboard($estado) {
    switch($estado) {
        case 'sin_solicitud': return 'Sin Solicitud Activa';
        case 'pendiente': 
        case 'solicitud_pendiente': return 'En Revisión';
        case 'aprobada': 
        case 'aprobado': return 'Aprobada - Lista para comenzar';
        case 'en_proceso': return 'En Proceso - Activo';
        case 'completado': 
        case 'concluido': return 'Completado - Finalizado';
        default: return 'Estado no definido';
    }
}

function getActivityIcon($tipo, $estado = null) {
    switch($tipo) {
        case 'reporte':
            if ($estado === 'aprobado') return 'check';
            if ($estado === 'pendiente_evaluacion') return 'clock';
            return 'file-alt';
        case 'solicitud':
            if ($estado === 'aprobada') return 'check-circle';
            if ($estado === 'pendiente') return 'hourglass-half';
            return 'paper-plane';
        default: return 'info-circle';
    }
}

function getActivityIconClass($tipo, $estado = null) {
    switch($tipo) {
        case 'reporte':
            if ($estado === 'aprobado') return 'success';
            if ($estado === 'pendiente_evaluacion') return 'warning';
            return 'info';
        case 'solicitud':
            if ($estado === 'aprobada') return 'success';
            if ($estado === 'pendiente') return 'warning';
            return 'info';
        default: return 'info';
    }
}

$pageTitle = "Dashboard Estudiante - " . APP_NAME;
$dashboardJS = true;
$chartsJS = true;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- ⭐ ESTRUCTURA PRINCIPAL CORREGIDA PARA SIDEBAR -->
<div class="dashboard-container">
    
    <!-- Mensaje de Fix de Sesión si es necesario -->
    <?php if ($fixMessage['show']): ?>
    <div class="session-fix-alert">
        <div class="session-fix-content">
            <div class="session-fix-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="session-fix-text">
                <strong>Sesión Restaurada:</strong> <?= htmlspecialchars($fixMessage['message']) ?>
                <small>Los datos de tu perfil se han cargado correctamente.</small>
            </div>
            <button class="session-fix-close" onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span class="welcome-text">¡Hola, <?= htmlspecialchars(explode(' ', ($estudiante['nombre'] ?? 'Usuario'))[0]) ?>!</span>
                <span class="welcome-emoji">👋</span>
            </h1>
            <p class="welcome-subtitle">Bienvenido a tu panel de control de servicio social</p>
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

    <!-- STATUS OVERVIEW REDESIGN -->
    <div class="status-overview-redesign">
        <!-- Estado del Servicio - Tarjeta Principal -->


        <!-- Progreso de Horas - Tarjeta Circular -->
        <div class="progress-card">
            <h3 class="progress-title">Progreso de Horas</h3>
            <div class="circular-progress-container">
                <div class="circular-progress-bg" style="--progress: <?= round($progreso) ?>">
                    <div class="circular-progress-inner">
                        <div class="progress-percentage"><?= round($progreso) ?>%</div>
                        <div class="progress-label">Completado</div>
                    </div>
                </div>
            </div>
            <div class="progress-details-list">
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Horas completadas</span>
                    <span class="progress-detail-value"><?= $horasCompletadas ?></span>
                </div>
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Horas restantes</span>
                    <span class="progress-detail-value"><?= max(0, $horasRequeridas - $horasCompletadas) ?></span>
                </div>
                <div class="progress-detail-item">
                    <span class="progress-detail-label">Tiempo estimado</span>
                    <span class="progress-detail-value"><?= ceil(max(0, $horasRequeridas - $horasCompletadas) / 20) ?> semanas</span>
                </div>
            </div>
        </div>

        <!-- Reportes - Tarjeta con Métricas Visuales -->
        <div class="reports-card">
            <h3 class="reports-title">Estado de Reportes</h3>
            <div class="reports-metrics">
                <div class="metric-item total">
                    <div class="metric-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $totalReportes ?></div>
                        <div class="metric-label">Reportes Totales</div>
                    </div>
                </div>
                
                <div class="metric-item pending">
                    <div class="metric-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= count($reportesPendientes) ?></div>
                        <div class="metric-label">Pendientes de Revisión</div>
                    </div>
                </div>
                
                <div class="metric-item approved">
                    <div class="metric-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-number"><?= $reportesAprobados ?></div>
                        <div class="metric-label">Reportes Aprobados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content-area">
        <!-- Left Column -->
        <div class="content-column">
            <!-- Current Status Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i>
                        Estado Actual
                    </h2>
                </div>

                <?php if (!$solicitudActiva): ?>
                <div class="status-panel empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="empty-content">
                        <h3>¡Comienza tu Servicio Social!</h3>
                        <p>Envía tu solicitud para iniciar con tu servicio social en el ITA.</p>
                        <a href="../modules/estudiantes/solicitud.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Crear Solicitud
                        </a>
                    </div>
                </div>

                <?php elseif ($solicitudActiva['estado'] === 'pendiente'): ?>
                <div class="status-panel pending">
                    <div class="status-indicator">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="status-details">
                        <h3>Solicitud en Revisión</h3>
                        <p>Tu solicitud está siendo revisada por el jefe de departamento.</p>
                        <div class="project-info">
                            <p><strong>Proyecto:</strong> <?= htmlspecialchars($solicitudActiva['nombre_proyecto'] ?? 'Sin proyecto') ?></p>
                            <p><strong>Laboratorio:</strong> <?= htmlspecialchars($solicitudActiva['laboratorio'] ?? 'Sin laboratorio') ?></p>
                        </div>
                    </div>
                    <div class="status-actions">
                        <a href="../modules/estudiantes/solicitud-detalle.php?id=<?= $solicitudActiva['id'] ?>" class="btn btn-secondary">
                            Ver Detalles
                        </a>
                    </div>
                </div>

                <?php elseif ($solicitudActiva['estado'] === 'aprobada'): ?>
                <div class="status-panel approved">
                    <div class="status-indicator">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-details">
                        <h3>¡Solicitud Aprobada!</h3>
                        <p>Tu solicitud ha sido aprobada. Ya puedes comenzar con tu servicio social.</p>
                        <div class="project-info">
                            <p><strong>Proyecto:</strong> <?= htmlspecialchars($solicitudActiva['nombre_proyecto'] ?? 'Sin proyecto') ?></p>
                            <p><strong>Laboratorio:</strong> <?= htmlspecialchars($solicitudActiva['laboratorio'] ?? 'Sin laboratorio') ?></p>
                            <p><strong>Supervisor:</strong> <?= htmlspecialchars($solicitudActiva['jefe_lab_nombre'] ?? 'Sin asignar') ?></p>
                        </div>
                    </div>
                    <div class="status-actions">
                        <a href="../modules/estudiantes/documentos.php" class="btn btn-primary">
                            Descargar Oficio
                        </a>
                        <a href="../modules/estudiantes/reportes.php" class="btn btn-secondary">
                            Comenzar
                        </a>
                    </div>
                </div>

                <?php elseif ($solicitudActiva['estado'] === 'en_proceso'): ?>
                <div class="status-panel in-progress">
                    <div class="status-indicator">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="status-details">
                        <h3>Servicio Social en Proceso</h3>
                        <p>Continúa con tu servicio social y mantén tus reportes al día.</p>
                        
                        <?php if (!empty($reportesPendientes)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Tienes <?= count($reportesPendientes) ?> reporte(s) pendiente(s)</strong>
                                <p>Entrega tus reportes bimestrales para mantener tu servicio activo.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="progress-summary">
                            <div class="progress-item">
                                <span>Progreso total</span>
                                <span class="progress-value"><?= round($progreso) ?>%</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-actions">
                        <a href="../modules/estudiantes/reportes.php" class="btn btn-primary">
                            <?= !empty($reportesPendientes) ? 'Entregar Reportes' : 'Gestionar Reportes' ?>
                        </a>
                        <a href="../modules/estudiantes/horas.php" class="btn btn-secondary">
                            Registrar Horas
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activities -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Actividades Recientes
                    </h2>
                    <a href="../modules/estudiantes/actividades.php" class="section-link">Ver todas</a>
                </div>

                <div class="activities-list">
                    <?php if (!empty($actividadesRecientes)): ?>
                        <?php foreach ($actividadesRecientes as $actividad): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= getActivityIconClass($actividad['tipo'], $actividad['estado']) ?>">
                                <i class="fas fa-<?= getActivityIcon($actividad['tipo'], $actividad['estado']) ?>"></i>
                            </div>
                            <div class="activity-content">
                                <h4><?= htmlspecialchars($actividad['titulo']) ?></h4>
                                <p><?= htmlspecialchars($actividad['descripcion']) ?></p>
                                <span class="activity-date"><?= timeAgo($actividad['fecha']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="activity-content">
                                <h4>Dashboard Funcionando Correctamente</h4>
                                <p>Sesión restaurada exitosamente. Datos del estudiante: <?= htmlspecialchars($estudiante['nombre'] ?? 'Sin nombre') ?> (<?= htmlspecialchars($estudiante['numero_control'] ?? 'Sin número') ?>)</p>
                                <span class="activity-date">Ahora</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon info">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-content">
                                <h4>Sistema Listo</h4>
                                <p>El sistema está funcionando correctamente. Puedes comenzar a enviar solicitudes y gestionar tu servicio social.</p>
                                <span class="activity-date">Sistema activo</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="sidebar-column">
            <!-- Progress Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-chart-pie"></i>
                        Tu Progreso
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="circular-progress">
                        <div class="progress-circle" data-percentage="<?= $progreso ?>">
                            <span class="progress-value"><?= round($progreso) ?>%</span>
                        </div>
                    </div>
                    
                    <div class="progress-details">
                        <div class="detail-item">
                            <span class="detail-label">Horas completadas</span>
                            <span class="detail-value"><?= $horasCompletadas ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Horas restantes</span>
                            <span class="detail-value"><?= max(0, $horasRequeridas - $horasCompletadas) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tiempo estimado</span>
                            <span class="detail-value"><?= ceil(max(0, $horasRequeridas - $horasCompletadas) / 5) ?> semanas</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Info Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-id-card"></i>
                        Información Personal
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="student-info">
                        <div class="info-item">
                            <span class="info-label">Número de Control</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['numero_control'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Carrera</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['carrera'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Semestre</span>
                            <span class="info-value"><?= $estudiante['semestre'] ?? 'N/A' ?>°</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Créditos</span>
                            <span class="info-value"><?= $estudiante['creditos_cursados'] ?? 'N/A' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Widget -->
            <?php if ($documentos): ?>
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-file-download"></i>
                        Documentos Recientes
                    </h3>
                    <a href="../modules/estudiantes/documentos.php" class="widget-link">Ver todos</a>
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
                            <a href="<?= UPLOAD_URL . $doc['archivo_path'] ?>" 
                               target="_blank" 
                               class="document-action"
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

            <!-- Quick Links Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-link"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="quick-actions">
                        <a href="../modules/estudiantes/perfil.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="action-text">
                                <span>Mi Perfil</span>
                                <small>Actualizar información</small>
                            </div>
                        </a>
                        
                        <a href="../modules/estudiantes/solicitud.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="action-text">
                                <span>Nueva Solicitud</span>
                                <small>Crear solicitud</small>
                            </div>
                        </a>
                        
                        <a href="../modules/estudiantes/reportes.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="action-text">
                                <span>Mis Reportes</span>
                                <small>Gestionar reportes</small>
                            </div>
                        </a>
                        
                        <a href="../help.php" class="quick-action">
                            <div class="action-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="action-text">
                                <span>Ayuda</span>
                                <small>Obtener soporte</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ⭐ CSS ACTUALIZADO PARA SIDEBAR CORREGIDO -->
<style>
/* Variables CSS - ya están en header.php, pero por si acaso */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
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
    /* Variables críticas ya definidas en header.php */
    --header-height: 80px;
    --sidebar-width: 280px;
}

/* ⭐ DASHBOARD CONTAINER - AJUSTADO PARA SIDEBAR */
.dashboard-container {
    padding: 1.5rem;
    max-width: none;  /* Sin limitación de ancho */
    margin: 0;        /* Sin margin auto que puede centrar */
    width: 100%;      /* Usar todo el ancho disponible */
    /* El margen izquierdo se maneja en main-content desde header.php */
}

/* Session Fix Alert */
.session-fix-alert {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    border: 1px solid #c3e6cb;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    animation: slideInDown 0.5s ease-out;
}

.session-fix-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
}

.session-fix-icon {
    width: 40px;
    height: 40px;
    background: #198754;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.session-fix-text {
    flex: 1;
    color: #155724;
    font-size: 0.95rem;
}

.session-fix-text strong {
    display: block;
    margin-bottom: 0.25rem;
}

.session-fix-text small {
    font-size: 0.85rem;
    opacity: 0.8;
}

.session-fix-close {
    background: none;
    border: none;
    color: #155724;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--radius);
    transition: var(--transition);
    flex-shrink: 0;
}

.session-fix-close:hover {
    background: rgba(21, 87, 36, 0.1);
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

/* Status Overview */
.status-overview-redesign {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Estado del Servicio - Tarjeta Principal */
.service-status-card {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: var(--radius-lg);
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.service-status-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.service-status-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.service-status-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 2rem;
}

.service-status-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    backdrop-filter: blur(10px);
    flex-shrink: 0;
}

.service-status-info {
    flex: 1;
}

.service-status-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.service-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2rem;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
}

.service-project-info {
    background: rgba(255, 255, 255, 0.15);
    padding: 1rem;
    border-radius: var(--radius);
    backdrop-filter: blur(10px);
}

.service-project-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.service-project-lab {
    opacity: 0.9;
    font-size: 0.9rem;
}

/* Estados específicos */
.service-status-card.pending {
    background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
}

.service-status-card.approved {
    background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
}

.service-status-card.in-progress {
    background: linear-gradient(135deg, var(--info) 0%, #60a5fa 100%);
}

.service-status-card.completed {
    background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
}

/* Progreso de Horas */
.progress-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: var(--transition);
}

.progress-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.progress-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
}

.circular-progress-container {
    position: relative;
    width: 160px;
    height: 160px;
    margin-bottom: 1.5rem;
}

.circular-progress-bg {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: conic-gradient(
        var(--success) 0deg,
        var(--success) calc(var(--progress) * 3.6deg),
        var(--bg-gray) calc(var(--progress) * 3.6deg),
        var(--bg-gray) 360deg
    );
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 1s ease-out;
}

.circular-progress-inner {
    width: 120px;
    height: 120px;
    background: var(--bg-white);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
}

.progress-percentage {
    font-size: 2rem;
    font-weight: 800;
    color: var(--success);
    line-height: 1;
}

.progress-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.progress-details-list {
    width: 100%;
}

.progress-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
    transition: var(--transition);
}

.progress-detail-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.progress-detail-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.progress-detail-value {
    font-weight: 700;
    color: var(--text-primary);
}

/* Reportes */
.reports-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    transition: var(--transition);
}

.reports-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.reports-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    text-align: center;
}

.reports-metrics {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.metric-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid transparent;
    transition: var(--transition);
}

.metric-item:hover {
    transform: translateX(5px);
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.metric-item.total {
    border-left-color: var(--info);
}

.metric-item.pending {
    border-left-color: var(--warning);
}

.metric-item.approved {
    border-left-color: var(--success);
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.metric-item.total .metric-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.metric-item.pending .metric-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.metric-item.approved .metric-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.metric-content {
    flex: 1;
}

.metric-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

.metric-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

/* Main Content Area */
.main-content-area {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

/* Content Sections */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
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

.section-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Status Panels */
.status-panel {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    padding: 1.5rem;
    border-radius: var(--radius);
}

.status-panel.empty-state {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 140, 247, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    text-align: center;
    flex-direction: column;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    margin-bottom: 1rem;
}

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.status-panel.pending {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(251, 191, 36, 0.05) 100%);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-panel.approved {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(52, 211, 153, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-panel.in-progress {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(96, 165, 250, 0.05) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

.status-indicator {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.status-panel.pending .status-indicator {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.status-panel.approved .status-indicator {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.status-panel.in-progress .status-indicator {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.status-details {
    flex: 1;
}

.status-details h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.status-details p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.project-info {
    background: rgba(255, 255, 255, 0.5);
    padding: 1rem;
    border-radius: var(--radius);
    margin: 1rem 0;
}

.project-info p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.status-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

/* Alert */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--radius);
    margin: 1rem 0;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #92400e;
    border: 1px solid rgba(245, 158, 11, 0.2);
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
    font-size: 0.9rem;
}

.progress-summary {
    margin-top: 1rem;
}

.progress-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.5);
    border-radius: var(--radius);
}

.progress-item span:first-child {
    color: var(--text-secondary);
}

.progress-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Activities List */
.activities-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius);
    background: var(--bg-light);
    transition: var(--transition);
}

.activity-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.activity-icon.success {
    background: var(--success);
}

.activity-icon.info {
    background: var(--info);
}

.activity-icon.warning {
    background: var(--warning);
}

.activity-content {
    flex: 1;
}

.activity-content h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.activity-content p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
}

.activity-date {
    font-size: 0.75rem;
    color: var(--text-light);
}

/* Widgets */
.widget {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.widget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-light);
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

.widget-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
}

.widget-content {
    padding: 1.5rem;
}

/* Circular Progress */
.circular-progress {
    display: flex;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.progress-circle {
    position: relative;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(var(--success) 0% calc(var(--percentage) * 1%), var(--bg-light) 0% 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress-circle::before {
    content: '';
    position: absolute;
    width: 100px;
    height: 100px;
    background: var(--bg-white);
    border-radius: 50%;
}

.progress-value {
    position: relative;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.progress-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.detail-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.detail-value {
    font-weight: 600;
    color: var(--text-primary);
}

/* Student Info */
.student-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.info-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.info-value {
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
    gap: 0.75rem;
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
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    background: var(--error);
    flex-shrink: 0;
}

.document-info {
    flex: 1;
}

.document-info h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.document-info p {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
}

.document-date {
    font-size: 0.75rem;
    color: var(--text-light);
}

.document-action {
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    text-decoration: none;
    transition: var(--transition);
    flex-shrink: 0;
}

.document-action:hover {
    background: var(--primary);
    color: white;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
}

.quick-action:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.action-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    flex-shrink: 0;
}

.action-text span {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.action-text small {
    font-size: 0.8rem;
    color: var(--text-secondary);
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

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.status-overview-redesign > * {
    animation: slideIn 0.6s ease-out;
}

.status-overview-redesign > *:nth-child(1) {
    animation-delay: 0.1s;
}

.status-overview-redesign > *:nth-child(2) {
    animation-delay: 0.2s;
}

.status-overview-redesign > *:nth-child(3) {
    animation-delay: 0.3s;
}

/* ⭐ RESPONSIVE DESIGN - CRÍTICO PARA SIDEBAR */
@media (max-width: 1200px) {
    .status-overview-redesign {
        grid-template-columns: 1fr;
    }
    
    .service-status-card {
        grid-column: 1;
    }
}

@media (max-width: 1024px) {
    .main-content-area {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .service-status-content {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    /* Dashboard container ajustes móviles */
    .dashboard-container {
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .service-status-card,
    .progress-card,
    .reports-card {
        padding: 1.5rem;
    }
    
    .status-panel {
        flex-direction: column;
        text-align: center;
    }
    
    .status-actions {
        width: 100%;
        justify-content: center;
    }
    
    .session-fix-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .metric-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .progress-circle {
        width: 100px;
        height: 100px;
    }
    
    .progress-circle::before {
        width: 80px;
        height: 80px;
    }
    
    .progress-value {
        font-size: 1.25rem;
    }
    
    .dashboard-container {
        padding: 0.75rem;
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
    setInterval(updateTime, 60000); // Update every minute
    
    // Animación del progreso circular principal
    const progressElement = document.querySelector('.circular-progress-bg');
    if (progressElement) {
        const percentage = parseInt(progressElement.style.getPropertyValue('--progress'));
        
        // Animar el progreso desde 0
        let current = 0;
        const increment = percentage / 60; // 60 frames de animación
        
        function animateProgress() {
            if (current < percentage) {
                current += increment;
                progressElement.style.setProperty('--progress', Math.min(current, percentage));
                requestAnimationFrame(animateProgress);
            }
        }
        
        // Iniciar animación después de un pequeño delay
        setTimeout(() => {
            progressElement.style.setProperty('--progress', 0);
            animateProgress();
        }, 500);
    }
    
    // Auto-hide session fix alert after 5 seconds
    const sessionAlert = document.querySelector('.session-fix-alert');
    if (sessionAlert) {
        setTimeout(() => {
            sessionAlert.style.opacity = '0';
            sessionAlert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                sessionAlert.style.display = 'none';
            }, 300);
        }, 5000);
    }
    
    console.log('✅ Dashboard del estudiante con datos corregidos inicializado');
});
</script>

<?php include '../includes/footer.php'; ?>