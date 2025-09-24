<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Función helper para htmlspecialchars segura
function safe_html($value, $default = '') {
    if ($value === null || $value === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Obtener datos del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

// Obtener filtros de URL
$filtroTipo = $_GET['tipo'] ?? 'todos';
$filtroFecha = $_GET['fecha'] ?? 'todos';
$filtroEstado = $_GET['estado'] ?? 'todos';

// Construir array de actividades combinadas
$actividades = [];

// 1. Actividades del log del sistema
try {
    $logActividades = $db->fetchAll("
        SELECT la.*, 'log_sistema' as fuente,
               u.email as usuario_email,
               CASE 
                   WHEN u.tipo_usuario = 'estudiante' THEN e.nombre
                   WHEN u.tipo_usuario = 'jefe_departamento' THEN jd.nombre
                   WHEN u.tipo_usuario = 'jefe_laboratorio' THEN jl.nombre
                   ELSE u.email
               END as usuario_nombre,
               u.tipo_usuario
        FROM log_actividades la
        LEFT JOIN usuarios u ON la.usuario_id = u.id
        LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
        LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id AND u.tipo_usuario = 'jefe_departamento'
        LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id AND u.tipo_usuario = 'jefe_laboratorio'
        WHERE (la.usuario_id = :estudiante_id OR 
               (la.modulo IN ('solicitudes', 'reportes', 'documentos') AND 
                la.registro_afectado_id IN (
                    SELECT s.id FROM solicitudes_servicio s WHERE s.estudiante_id = :estudiante_id2
                )))
        ORDER BY la.created_at DESC
        LIMIT 50
    ", [
        'estudiante_id' => $estudianteId,
        'estudiante_id2' => $estudiante['id']
    ]);

    foreach ($logActividades as $log) {
        $actividades[] = [
            'id' => 'log_' . $log['id'],
            'tipo' => 'log_sistema',
            'subtipo' => $log['accion'],
            'titulo' => getAccionTitle($log['accion']),
            'descripcion' => getAccionDescription($log['accion'], $log['detalles']),
            'usuario' => $log['usuario_nombre'] ?? 'Sistema',
            'usuario_tipo' => $log['tipo_usuario'] ?? 'sistema',
            'fecha' => $log['created_at'],
            'estado' => 'completado',
            'icono' => getAccionIcon($log['accion']),
            'color' => getAccionColor($log['accion']),
            'metadata' => [
                'modulo' => $log['modulo'],
                'ip_address' => $log['ip_address'],
                'detalles' => $log['detalles']
            ]
        ];
    }
} catch (Exception $e) {
    // Si no existe la tabla de log, continuar sin logs
    error_log("Error al obtener log de actividades: " . $e->getMessage());
}

// 2. Solicitudes de servicio social
$solicitudes = $db->fetchAll("
    SELECT s.*, p.nombre_proyecto, jd.nombre as jefe_nombre,
           'solicitud' as fuente
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
    WHERE s.estudiante_id = :estudiante_id
    ORDER BY s.created_at DESC
", ['estudiante_id' => $estudiante['id']]);

foreach ($solicitudes as $solicitud) {
    $actividades[] = [
        'id' => 'solicitud_' . $solicitud['id'],
        'tipo' => 'solicitud',
        'subtipo' => 'crear_solicitud',
        'titulo' => 'Solicitud de Servicio Social Creada',
        'descripcion' => 'Solicitud para el proyecto: ' . $solicitud['nombre_proyecto'],
        'usuario' => $estudiante['nombre'],
        'usuario_tipo' => 'estudiante',
        'fecha' => $solicitud['created_at'],
        'estado' => $solicitud['estado'],
        'icono' => 'file-plus',
        'color' => 'primary',
        'metadata' => [
            'solicitud_id' => $solicitud['id'],
            'proyecto' => $solicitud['nombre_proyecto'],
            'jefe' => $solicitud['jefe_nombre'],
            'fecha_inicio' => $solicitud['fecha_inicio_propuesta'],
            'fecha_fin' => $solicitud['fecha_fin_propuesta']
        ]
    ];

    // Agregar evento de aprobación/rechazo si aplica
    if ($solicitud['estado'] !== 'pendiente' && $solicitud['fecha_aprobacion']) {
        $actividades[] = [
            'id' => 'solicitud_estado_' . $solicitud['id'],
            'tipo' => 'solicitud',
            'subtipo' => 'cambio_estado',
            'titulo' => 'Solicitud ' . ucfirst($solicitud['estado']),
            'descripcion' => 'Estado cambiado a: ' . getEstadoText($solicitud['estado']),
            'usuario' => $solicitud['jefe_nombre'],
            'usuario_tipo' => 'jefe_departamento',
            'fecha' => $solicitud['fecha_aprobacion'],
            'estado' => $solicitud['estado'],
            'icono' => getEstadoIcon($solicitud['estado']),
            'color' => getEstadoColor($solicitud['estado']),
            'metadata' => [
                'solicitud_id' => $solicitud['id'],
                'estado_anterior' => 'pendiente',
                'estado_nuevo' => $solicitud['estado'],
                'motivo_rechazo' => $solicitud['motivo_rechazo']
            ]
        ];
    }
}

// 3. Reportes bimestrales
$reportes = $db->fetchAll("
    SELECT r.*, p.nombre_proyecto, jl.nombre as jefe_lab_nombre,
           'reporte' as fuente
    FROM reportes_bimestrales r
    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON r.jefe_laboratorio_id = jl.id
    WHERE r.estudiante_id = :estudiante_id
    ORDER BY r.created_at DESC
", ['estudiante_id' => $estudiante['id']]);

foreach ($reportes as $reporte) {
    // Evento de entrega
    $actividades[] = [
        'id' => 'reporte_entrega_' . $reporte['id'],
        'tipo' => 'reporte',
        'subtipo' => 'entregar_reporte',
        'titulo' => 'Reporte Bimestral #' . $reporte['numero_reporte'] . ' Entregado',
        'descripcion' => 'Entrega de reporte del ' . formatDate($reporte['periodo_inicio']) . ' al ' . formatDate($reporte['periodo_fin']),
        'usuario' => $estudiante['nombre'],
        'usuario_tipo' => 'estudiante',
        'fecha' => $reporte['created_at'],
        'estado' => $reporte['estado'],
        'icono' => 'file-upload',
        'color' => 'info',
        'metadata' => [
            'reporte_id' => $reporte['id'],
            'numero_reporte' => $reporte['numero_reporte'],
            'horas_reportadas' => $reporte['horas_reportadas'],
            'periodo_inicio' => $reporte['periodo_inicio'],
            'periodo_fin' => $reporte['periodo_fin'],
            'proyecto' => $reporte['nombre_proyecto']
        ]
    ];

    // Evento de evaluación si aplica
    if ($reporte['fecha_evaluacion'] && $reporte['evaluado_por']) {
        $actividades[] = [
            'id' => 'reporte_evaluacion_' . $reporte['id'],
            'tipo' => 'reporte',
            'subtipo' => 'evaluar_reporte',
            'titulo' => 'Reporte #' . $reporte['numero_reporte'] . ' Evaluado',
            'descripcion' => 'Calificación obtenida: ' . ($reporte['calificacion'] ?? 'Pendiente'),
            'usuario' => $reporte['jefe_lab_nombre'] ?? 'Supervisor',
            'usuario_tipo' => 'jefe_laboratorio',
            'fecha' => $reporte['fecha_evaluacion'],
            'estado' => $reporte['estado'],
            'icono' => 'check-circle',
            'color' => $reporte['estado'] === 'aprobado' ? 'success' : ($reporte['estado'] === 'rechazado' ? 'error' : 'warning'),
            'metadata' => [
                'reporte_id' => $reporte['id'],
                'numero_reporte' => $reporte['numero_reporte'],
                'calificacion' => $reporte['calificacion'],
                'observaciones' => $reporte['observaciones_evaluador']
            ]
        ];
    }
}

// 4. Documentos oficiales
$documentos = $db->fetchAll("
    SELECT op.*, s.estudiante_id, 'oficio' as tipo_doc, 'documento' as fuente,
           jd.nombre as generado_por_nombre
    FROM oficios_presentacion op
    JOIN solicitudes_servicio s ON op.solicitud_id = s.id
    LEFT JOIN usuarios u ON op.generado_por = u.id
    LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id
    WHERE s.estudiante_id = :estudiante_id
    ORDER BY op.created_at DESC
", ['estudiante_id' => $estudiante['id']]);

foreach ($documentos as $documento) {
    $actividades[] = [
        'id' => 'documento_' . $documento['id'],
        'tipo' => 'documento',
        'subtipo' => 'generar_documento',
        'titulo' => 'Oficio de Presentación Generado',
        'descripcion' => 'Número: ' . $documento['numero_oficio'],
        'usuario' => $documento['generado_por_nombre'] ?? 'Sistema',
        'usuario_tipo' => 'jefe_departamento',
        'fecha' => $documento['created_at'],
        'estado' => $documento['estado'],
        'icono' => 'file-contract',
        'color' => 'success',
        'metadata' => [
            'documento_id' => $documento['id'],
            'numero_oficio' => $documento['numero_oficio'],
            'fecha_emision' => $documento['fecha_emision'],
            'archivo_path' => $documento['archivo_path']
        ]
    ];
}

// 5. Constancias y cartas
$constancias = $db->fetchAll("
    SELECT c.*, 'constancia' as tipo_doc, 'documento' as fuente,
           jd.nombre as generado_por_nombre
    FROM constancias c
    LEFT JOIN usuarios u ON c.generado_por = u.id
    LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id
    WHERE c.estudiante_id = :estudiante_id
    ORDER BY c.created_at DESC
", ['estudiante_id' => $estudiante['id']]);

foreach ($constancias as $constancia) {
    $actividades[] = [
        'id' => 'constancia_' . $constancia['id'],
        'tipo' => 'documento',
        'subtipo' => 'generar_constancia',
        'titulo' => 'Constancia de Liberación Generada',
        'descripcion' => 'Número: ' . $constancia['numero_constancia'],
        'usuario' => $constancia['generado_por_nombre'] ?? 'Sistema',
        'usuario_tipo' => 'jefe_departamento',
        'fecha' => $constancia['created_at'],
        'estado' => $constancia['enviado_servicios_escolares'] ? 'enviado' : 'generado',
        'icono' => 'file-certificate',
        'color' => 'primary',
        'metadata' => [
            'constancia_id' => $constancia['id'],
            'numero_constancia' => $constancia['numero_constancia'],
            'calificacion_final' => $constancia['calificacion_final'],
            'horas_cumplidas' => $constancia['horas_cumplidas'],
            'enviado_escolares' => $constancia['enviado_servicios_escolares']
        ]
    ];
}

// Ordenar actividades por fecha (más recientes primero)
usort($actividades, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// Aplicar filtros
$actividadesFiltradas = $actividades;

if ($filtroTipo !== 'todos') {
    $actividadesFiltradas = array_filter($actividadesFiltradas, function($actividad) use ($filtroTipo) {
        return $actividad['tipo'] === $filtroTipo;
    });
}

if ($filtroEstado !== 'todos') {
    $actividadesFiltradas = array_filter($actividadesFiltradas, function($actividad) use ($filtroEstado) {
        return $actividad['estado'] === $filtroEstado;
    });
}

if ($filtroFecha !== 'todos') {
    $fechaLimite = '';
    switch ($filtroFecha) {
        case 'hoy':
            $fechaLimite = date('Y-m-d');
            break;
        case 'semana':
            $fechaLimite = date('Y-m-d', strtotime('-1 week'));
            break;
        case 'mes':
            $fechaLimite = date('Y-m-d', strtotime('-1 month'));
            break;
        case 'trimestre':
            $fechaLimite = date('Y-m-d', strtotime('-3 months'));
            break;
    }
    
    if ($fechaLimite) {
        $actividadesFiltradas = array_filter($actividadesFiltradas, function($actividad) use ($fechaLimite, $filtroFecha) {
            $fechaActividad = date('Y-m-d', strtotime($actividad['fecha']));
            return $filtroFecha === 'hoy' ? $fechaActividad === $fechaLimite : $fechaActividad >= $fechaLimite;
        });
    }
}

// Reindexar array
$actividadesFiltradas = array_values($actividadesFiltradas);

// Estadísticas
$totalActividades = count($actividades);
$actividadesHoy = count(array_filter($actividades, function($a) {
    return date('Y-m-d', strtotime($a['fecha'])) === date('Y-m-d');
}));
$actividadesSemana = count(array_filter($actividades, function($a) {
    return strtotime($a['fecha']) >= strtotime('-1 week');
}));

// Funciones helper
function getAccionTitle($accion) {
    switch($accion) {
        case 'crear_solicitud': return 'Solicitud Creada';
        case 'actualizar_solicitud': return 'Solicitud Actualizada';
        case 'aprobar_solicitud': return 'Solicitud Aprobada';
        case 'rechazar_solicitud': return 'Solicitud Rechazada';
        case 'entregar_reporte': return 'Reporte Entregado';
        case 'evaluar_reporte': return 'Reporte Evaluado';
        case 'generar_oficio': return 'Oficio Generado';
        case 'actualizar_perfil': return 'Perfil Actualizado';
        case 'cambio_password': return 'Contraseña Cambiada';
        case 'login': return 'Inicio de Sesión';
        case 'logout': return 'Cierre de Sesión';
        default: return ucfirst(str_replace('_', ' ', $accion));
    }
}

function getAccionDescription($accion, $detalles = null) {
    $detallesArray = is_string($detalles) ? json_decode($detalles, true) : $detalles;
    
    switch($accion) {
        case 'crear_solicitud': 
            return 'Se creó una nueva solicitud de servicio social';
        case 'aprobar_solicitud': 
            return 'La solicitud fue aprobada por el jefe de departamento';
        case 'rechazar_solicitud': 
            return 'La solicitud fue rechazada';
        case 'entregar_reporte': 
            return 'Se entregó un reporte bimestral para evaluación';
        case 'evaluar_reporte': 
            return 'Se evaluó y calificó un reporte bimestral';
        case 'generar_oficio': 
            return 'Se generó el oficio oficial de presentación';
        case 'login': 
            return 'Acceso al sistema registrado';
        default: 
            return 'Actividad realizada en el sistema';
    }
}

function getAccionIcon($accion) {
    switch($accion) {
        case 'crear_solicitud': return 'file-plus';
        case 'aprobar_solicitud': return 'check-circle';
        case 'rechazar_solicitud': return 'times-circle';
        case 'entregar_reporte': return 'file-upload';
        case 'evaluar_reporte': return 'star';
        case 'generar_oficio': return 'file-contract';
        case 'actualizar_perfil': return 'user-edit';
        case 'cambio_password': return 'key';
        case 'login': return 'sign-in-alt';
        case 'logout': return 'sign-out-alt';
        default: return 'circle';
    }
}

function getAccionColor($accion) {
    switch($accion) {
        case 'crear_solicitud': return 'primary';
        case 'aprobar_solicitud': return 'success';
        case 'rechazar_solicitud': return 'error';
        case 'entregar_reporte': return 'info';
        case 'evaluar_reporte': return 'warning';
        case 'generar_oficio': return 'success';
        case 'actualizar_perfil': return 'info';
        case 'login': return 'success';
        case 'logout': return 'secondary';
        default: return 'secondary';
    }
}

function getEstadoIcon($estado) {
    switch($estado) {
        case 'pendiente': return 'hourglass-half';
        case 'aprobada': return 'check-circle';
        case 'rechazada': return 'times-circle';
        case 'en_proceso': return 'play-circle';
        case 'concluida': return 'trophy';
        case 'completado': return 'check';
        case 'aprobado': return 'check-circle';
        case 'rechazado': return 'times-circle';
        default: return 'circle';
    }
}

function getEstadoColor($estado) {
    switch($estado) {
        case 'pendiente': return 'warning';
        case 'aprobada': case 'aprobado': case 'completado': return 'success';
        case 'rechazada': case 'rechazado': return 'error';
        case 'en_proceso': return 'info';
        case 'concluida': return 'primary';
        default: return 'secondary';
    }
}

function getTipoDisplayName($tipo) {
    switch($tipo) {
        case 'solicitud': return 'Solicitudes';
        case 'reporte': return 'Reportes';
        case 'documento': return 'Documentos';
        case 'log_sistema': return 'Sistema';
        default: return ucfirst($tipo);
    }
}

$pageTitle = "Historial de Actividades - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="activities-container">
    <!-- Header Section -->
    <div class="activities-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Historial de Actividades</h1>
                <p class="header-subtitle">Registro completo de todas tus actividades en el servicio social</p>
            </div>
        </div>
        <div class="header-actions">
            <button onclick="exportActivities()" class="btn btn-info">
                <i class="fas fa-download"></i>
                Exportar
            </button>
            <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
                <h3>Total de Actividades</h3>
                <span class="stat-number" data-target="<?= $totalActividades ?>"><?= $totalActividades ?></span>
                <span class="stat-label">registradas</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3>Actividades Hoy</h3>
                <span class="stat-number" data-target="<?= $actividadesHoy ?>"><?= $actividadesHoy ?></span>
                <span class="stat-label">eventos</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3>Esta Semana</h3>
                <span class="stat-number" data-target="<?= $actividadesSemana ?>"><?= $actividadesSemana ?></span>
                <span class="stat-label">actividades</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-filter"></i>
            </div>
            <div class="stat-content">
                <h3>Filtradas</h3>
                <span class="stat-number" data-target="<?= count($actividadesFiltradas) ?>"><?= count($actividadesFiltradas) ?></span>
                <span class="stat-label">mostradas</span>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-card">
        <div class="filters-header">
            <h3>
                <i class="fas fa-sliders-h"></i>
                Filtros de Búsqueda
            </h3>
            <button onclick="clearAllFilters()" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
                Limpiar Filtros
            </button>
        </div>
        <form method="GET" class="filters-form" id="filtersForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="tipo" class="filter-label">
                        <i class="fas fa-tag"></i>
                        Tipo de Actividad
                    </label>
                    <select name="tipo" id="tipo" class="filter-select">
                        <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todas las actividades</option>
                        <option value="solicitud" <?= $filtroTipo === 'solicitud' ? 'selected' : '' ?>>Solicitudes</option>
                        <option value="reporte" <?= $filtroTipo === 'reporte' ? 'selected' : '' ?>>Reportes</option>
                        <option value="documento" <?= $filtroTipo === 'documento' ? 'selected' : '' ?>>Documentos</option>
                        <option value="log_sistema" <?= $filtroTipo === 'log_sistema' ? 'selected' : '' ?>>Sistema</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="fecha" class="filter-label">
                        <i class="fas fa-calendar"></i>
                        Período
                    </label>
                    <select name="fecha" id="fecha" class="filter-select">
                        <option value="todos" <?= $filtroFecha === 'todos' ? 'selected' : '' ?>>Todo el tiempo</option>
                        <option value="hoy" <?= $filtroFecha === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                        <option value="semana" <?= $filtroFecha === 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="mes" <?= $filtroFecha === 'mes' ? 'selected' : '' ?>>Este mes</option>
                        <option value="trimestre" <?= $filtroFecha === 'trimestre' ? 'selected' : '' ?>>Últimos 3 meses</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="estado" class="filter-label">
                        <i class="fas fa-flag"></i>
                        Estado
                    </label>
                    <select name="estado" id="estado" class="filter-select">
                        <option value="todos" <?= $filtroEstado === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="completado" <?= $filtroEstado === 'completado' ? 'selected' : '' ?>>Completado</option>
                        <option value="aprobado" <?= $filtroEstado === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                        <option value="rechazado" <?= $filtroEstado === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Activities Timeline -->
    <div class="activities-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-stream"></i>
                Línea de Tiempo de Actividades
            </h2>
            <div class="section-info">
                <span class="results-count">
                    Mostrando <?= count($actividadesFiltradas) ?> de <?= $totalActividades ?> actividades
                </span>
            </div>
        </div>

        <?php if (empty($actividadesFiltradas)): ?>
        <!-- Empty State -->
        <div class="empty-state-card">
            <div class="empty-state-icon">
                <i class="fas fa-search"></i>
            </div>
            <div class="empty-state-content">
                <h3>No se encontraron actividades</h3>
                <p>No hay actividades que coincidan con los filtros seleccionados.</p>
                <button onclick="clearAllFilters()" class="btn btn-primary">
                    <i class="fas fa-times"></i>
                    Limpiar Filtros
                </button>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Activities Timeline -->
        <div class="timeline-container">
            <div class="timeline-line"></div>
            
            <?php 
            $fechaAnterior = '';
            foreach ($actividadesFiltradas as $index => $actividad): 
                $fechaActividad = date('Y-m-d', strtotime($actividad['fecha']));
                $mostrarFecha = $fechaActividad !== $fechaAnterior;
                $fechaAnterior = $fechaActividad;
            ?>
            
            <!-- Date Separator -->
            <?php if ($mostrarFecha): ?>
            <div class="date-separator">
                <div class="date-line"></div>
                <div class="date-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= formatDate($fechaActividad) ?></span>
                </div>
                <div class="date-line"></div>
            </div>
            <?php endif; ?>
            
            <!-- Activity Item -->
            <div class="activity-item" data-type="<?= $actividad['tipo'] ?>" data-state="<?= $actividad['estado'] ?>">
                <div class="activity-marker <?= $actividad['color'] ?>">
                    <i class="fas fa-<?= $actividad['icono'] ?>"></i>
                </div>
                
                <div class="activity-card">
                    <div class="activity-header">
                        <div class="activity-title-section">
                            <h4 class="activity-title"><?= safe_html($actividad['titulo']) ?></h4>
                            <div class="activity-meta">
                                <span class="activity-type-badge <?= $actividad['tipo'] ?>">
                                    <?= getTipoDisplayName($actividad['tipo']) ?>
                                </span>
                                <span class="activity-time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('H:i', strtotime($actividad['fecha'])) ?>
                                </span>
                                <span class="activity-user">
                                    <i class="fas fa-user"></i>
                                    <?= safe_html($actividad['usuario']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="activity-status">
                            <span class="status-badge <?= $actividad['estado'] ?>">
                                <i class="fas fa-<?= getEstadoIcon($actividad['estado']) ?>"></i>
                                <?= ucfirst($actividad['estado']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="activity-content">
                        <p class="activity-description"><?= safe_html($actividad['descripcion']) ?></p>
                        
                        <?php if (!empty($actividad['metadata'])): ?>
                        <div class="activity-metadata">
                            <button class="metadata-toggle" onclick="toggleMetadata(this)">
                                <i class="fas fa-chevron-down"></i>
                                Ver detalles
                            </button>
                            <div class="metadata-content" style="display: none;">
                                <div class="metadata-grid">
                                    <?php foreach ($actividad['metadata'] as $key => $value): ?>
                                        <?php if (!empty($value) && !in_array($key, ['detalles', 'ip_address'])): ?>
                                        <div class="metadata-item">
                                            <span class="metadata-label"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span>
                                            <span class="metadata-value">
                                                <?php if (in_array($key, ['fecha_inicio', 'fecha_fin', 'periodo_inicio', 'periodo_fin', 'fecha_emision'])): ?>
                                                    <?= formatDate($value) ?>
                                                <?php elseif ($key === 'archivo_path' && !empty($value)): ?>
                                                    <a href="<?= UPLOAD_URL . $value ?>" target="_blank" class="file-link">
                                                        <i class="fas fa-file"></i> Ver archivo
                                                    </a>
                                                <?php else: ?>
                                                    <?= safe_html($value) ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Activity Actions -->
                    <div class="activity-actions">
                        <?php if ($actividad['tipo'] === 'solicitud' && isset($actividad['metadata']['solicitud_id'])): ?>
                        <a href="solicitud-detalle.php?id=<?= $actividad['metadata']['solicitud_id'] ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                            Ver Solicitud
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($actividad['tipo'] === 'reporte' && isset($actividad['metadata']['reporte_id'])): ?>
                        <a href="reporte-detalle.php?id=<?= $actividad['metadata']['reporte_id'] ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                            Ver Reporte
                        </a>
                        <?php endif; ?>
                        
                        <?php if (isset($actividad['metadata']['archivo_path']) && !empty($actividad['metadata']['archivo_path'])): ?>
                        <a href="<?= UPLOAD_URL . $actividad['metadata']['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-success">
                            <i class="fas fa-download"></i>
                            Descargar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Load More Button -->
        <?php if (count($actividadesFiltradas) >= 20): ?>
        <div class="load-more-section">
            <button onclick="loadMoreActivities()" class="btn btn-secondary btn-lg" id="loadMoreBtn">
                <i class="fas fa-plus"></i>
                Cargar Más Actividades
            </button>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --success-light: #34d399;
    --warning: #f59e0b;
    --warning-light: #fbbf24;
    --error: #ef4444;
    --error-light: #f87171;
    --info: #3b82f6;
    --info-light: #60a5fa;
    --secondary: #6b7280;
    --secondary-light: #9ca3af;
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
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Container Principal */
.activities-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
    min-height: calc(100vh - 80px);
}

/* Header */
.activities-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 2rem;
    background: linear-gradient(135deg, var(--bg-white) 0%, var(--bg-light) 100%);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.activities-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    opacity: 0.05;
    border-radius: 50%;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    position: relative;
    z-index: 2;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: var(--shadow-lg);
}

.header-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.header-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 1rem;
    position: relative;
    z-index: 2;
}

/* Statistics Overview */
.stats-overview {
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
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    animation: slideIn 0.6s ease-out;
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
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
    opacity: 0;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card:hover::before {
    opacity: 1;
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
    box-shadow: var(--shadow);
}

.stat-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-icon.success {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.stat-icon.info {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.stat-icon.warning {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    display: block;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Filters Card */
.filters-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    animation: slideIn 0.6s ease-out 0.2s both;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-light);
}

.filters-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.filters-form {
    padding: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 1.5rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-label i {
    color: var(--text-secondary);
    width: 16px;
    text-align: center;
}

.filter-select {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: var(--bg-white);
    color: var(--text-primary);
    transition: var(--transition);
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Activities Section */
.activities-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    animation: slideIn 0.6s ease-out 0.4s both;
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem 2rem 1rem;
    border-bottom: 1px solid var(--border-light);
    background: linear-gradient(135deg, var(--bg-light) 0%, var(--bg-white) 100%);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.section-info {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.results-count {
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: 2rem;
    font-weight: 500;
}

/* Empty State */
.empty-state-card {
    padding: 4rem 2rem;
    text-align: center;
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(156, 163, 175, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--text-light);
    margin: 0 auto 1.5rem;
}

.empty-state-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-state-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

/* Timeline Container */
.timeline-container {
    padding: 2rem;
    position: relative;
}

.timeline-line {
    position: absolute;
    left: 50px;
    top: 2rem;
    bottom: 2rem;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
    opacity: 0.3;
}

/* Date Separator */
.date-separator {
    display: flex;
    align-items: center;
    margin: 2rem 0;
    position: relative;
    z-index: 2;
}

.date-line {
    flex: 1;
    height: 1px;
    background: var(--border);
}

.date-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--bg-white);
    color: var(--text-secondary);
    border: 2px solid var(--border);
    border-radius: 2rem;
    font-weight: 600;
    font-size: 0.9rem;
    margin: 0 1rem;
    box-shadow: var(--shadow-sm);
}

/* Activity Item */
.activity-item {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
    z-index: 2;
}

.activity-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    position: relative;
    z-index: 3;
    box-shadow: var(--shadow);
    border: 3px solid var(--bg-white);
}

.activity-marker.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.activity-marker.success {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.activity-marker.warning {
    background: linear-gradient(135deg, var(--warning), var(--warning-light));
}

.activity-marker.error {
    background: linear-gradient(135deg, var(--error), var(--error-light));
}

.activity-marker.info {
    background: linear-gradient(135deg, var(--info), var(--info-light));
}

.activity-marker.secondary {
    background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
}

/* Activity Card */
.activity-card {
    flex: 1;
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}

.activity-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-light);
}

.activity-title-section {
    flex: 1;
}

.activity-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    line-height: 1.3;
}

.activity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.activity-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.activity-type-badge.solicitud {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.activity-type-badge.reporte {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.activity-type-badge.documento {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.activity-type-badge.log_sistema {
    background: rgba(107, 114, 128, 0.1);
    color: var(--secondary);
}

.activity-time,
.activity-user {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: var(--text-light);
}

.activity-time i,
.activity-user i {
    width: 12px;
    text-align: center;
}

.activity-status {
    flex-shrink: 0;
}

.activity-content {
    padding: 1.5rem;
}

.activity-description {
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

/* Activity Metadata */
.activity-metadata {
    border-top: 1px solid var(--border-light);
    padding-top: 1rem;
}

.metadata-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: none;
    border: none;
    color: var(--primary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.metadata-toggle:hover {
    color: var(--primary-dark);
}

.metadata-toggle.active i {
    transform: rotate(180deg);
}

.metadata-content {
    margin-top: 1rem;
    animation: slideDown 0.3s ease-out;
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.metadata-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.metadata-label {
    font-size: 0.8rem;
    color: var(--text-light);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.metadata-value {
    font-size: 0.9rem;
    color: var(--text-primary);
    font-weight: 500;
}

.file-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.file-link:hover {
    text-decoration: underline;
}

/* Activity Actions */
.activity-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid;
}

.status-badge.pendiente {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border-color: rgba(245, 158, 11, 0.2);
}

.status-badge.completado,
.status-badge.aprobado {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-color: rgba(16, 185, 129, 0.2);
}

.status-badge.rechazado {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border-color: rgba(239, 68, 68, 0.2);
}

.status-badge.en_proceso {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
    border-color: rgba(59, 130, 246, 0.2);
}

.status-badge.generado,
.status-badge.enviado {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    border-color: rgba(99, 102, 241, 0.2);
}

/* Load More Section */
.load-more-section {
    padding: 2rem;
    text-align: center;
    border-top: 1px solid var(--border-light);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

.btn-lg {
    padding: 1.125rem 2rem;
    font-size: 1rem;
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
    background: rgba(99, 102, 241, 0.05);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), var(--success-light));
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), var(--info-light));
    color: white;
}

.btn-info:hover {
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

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filters-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .filter-actions {
        grid-column: 1 / -1;
        justify-self: center;
    }
}

@media (max-width: 1024px) {
    .activities-container {
        padding: 1rem;
    }
    
    .activities-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .stats-overview {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .timeline-line {
        left: 25px;
    }
    
    .activity-item {
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-overview {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .activity-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .activity-meta {
        gap: 0.5rem;
    }
    
    .metadata-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .header-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .timeline-container {
        padding: 1rem;
    }
    
    .timeline-line {
        left: 25px;
    }
    
    .activity-item {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .activity-marker {
        align-self: center;
    }
}

/* Mejoras de accesibilidad */
.btn:focus-visible,
.filter-select:focus-visible,
.metadata-toggle:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Modo de contraste alto */
@media (prefers-contrast: high) {
    :root {
        --border: #000000;
        --text-secondary: #000000;
        --bg-light: #ffffff;
    }
}

/* Modo de movimiento reducido */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar contadores animados
    initAnimatedCounters();
    
    // Configurar filtros automáticos
    setupAutoFilters();
    
    // Configurar toggles de metadata
    setupMetadataToggles();
    
    // Configurar lazy loading de actividades
    setupLazyLoading();
    
    // Agregar efectos de hover
    setupHoverEffects();
});

// Contadores animados
function initAnimatedCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-target'));
                animateCounter(counter, target);
                observer.unobserve(counter);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => observer.observe(counter));
}

function animateCounter(element, target) {
    let current = 0;
    const increment = target / 30;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 50);
}

// Filtros automáticos
function setupAutoFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Auto-submit del formulario cuando cambie un filtro
            if (this.form) {
                this.form.submit();
            }
        });
    });
}

// Metadata toggles
function setupMetadataToggles() {
    window.toggleMetadata = function(button) {
        const content = button.parentElement.querySelector('.metadata-content');
        const icon = button.querySelector('i');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            button.classList.add('active');
            button.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar detalles';
        } else {
            content.style.display = 'none';
            button.classList.remove('active');
            button.innerHTML = '<i class="fas fa-chevron-down"></i> Ver detalles';
        }
    };
}

// Lazy loading
function setupLazyLoading() {
    let isLoading = false;
    let hasMore = true;
    let currentOffset = 0;
    
    window.loadMoreActivities = function() {
        if (isLoading || !hasMore) return;
        
        isLoading = true;
        const loadBtn = document.getElementById('loadMoreBtn');
        const originalText = loadBtn.innerHTML;
        
        loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
        loadBtn.disabled = true;
        
        // Simular carga (en implementación real, hacer AJAX request)
        setTimeout(() => {
            // Aquí iría la lógica para cargar más actividades
            console.log('Cargando más actividades...');
            
            isLoading = false;
            loadBtn.innerHTML = originalText;
            loadBtn.disabled = false;
            
            // Simular que no hay más datos después de 2 cargas
            currentOffset++;
            if (currentOffset >= 2) {
                hasMore = false;
                loadBtn.style.display = 'none';
                
                const message = document.createElement('p');
                message.textContent = 'No hay más actividades que mostrar';
                message.style.cssText = 'text-align: center; color: var(--text-light); margin-top: 1rem;';
                loadBtn.parentElement.appendChild(message);
            }
        }, 1500);
    };
}

// Efectos de hover
function setupHoverEffects() {
    const activityCards = document.querySelectorAll('.activity-card');
    
    activityCards.forEach(card => {
        card.addEventListener('mouseenter', function(e) {
            // Efecto ripple
            const ripple = document.createElement('div');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(99, 102, 241, 0.1);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 0;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

// Limpiar todos los filtros
window.clearAllFilters = function() {
    const form = document.getElementById('filtersForm');
    const selects = form.querySelectorAll('select');
    
    selects.forEach(select => {
        select.value = 'todos';
    });
    
    form.submit();
};

// Exportar actividades
window.exportActivities = function() {
    const button = event.target.closest('.btn');
    const originalText = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
    button.disabled = true;
    
    // Simular exportación
    setTimeout(() => {
        // Aquí iría la lógica real de exportación
        console.log('Exportando actividades...');
        
        // Mostrar notificación de éxito
        showNotification('Actividades exportadas correctamente', 'success');
        
        button.innerHTML = originalText;
        button.disabled = false;
    }, 2000);
};

// Sistema de notificaciones
function showNotification(message, type = 'info', duration = 4000) {
    const notification = document.createElement('div');
    notification.className = `notification-modern ${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
        </div>
        <div class="notification-content">
            <span>${message}</span>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--bg-white);
        border: 1px solid var(--border);
        border-left: 4px solid var(--${type === 'success' ? 'success' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info'});
        color: var(--text-primary);
        padding: 1rem 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 1rem;
        max-width: 400px;
        animation: slideInNotification 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.style.cssText = `
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 50%;
        transition: var(--transition);
    `;
    
    closeBtn.addEventListener('click', () => removeNotification(notification));
    
    document.body.appendChild(notification);
    setTimeout(() => removeNotification(notification), duration);
}

function removeNotification(notification) {
    notification.style.animation = 'slideOutNotification 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// Agregar estilos de animación
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInNotification {
        from { opacity: 0; transform: translateX(100%); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutNotification {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    @keyframes ripple {
        from { transform: scale(0); opacity: 1; }
        to { transform: scale(4); opacity: 0; }
    }
    
    .notification-modern .notification-icon {
        width: 20px;
        text-align: center;
    }
    
    .notification-modern .notification-content {
        flex: 1;
        font-weight: 500;
    }
`;
document.head.appendChild(notificationStyles);
</script>

<?php include '../../includes/footer.php'; ?>