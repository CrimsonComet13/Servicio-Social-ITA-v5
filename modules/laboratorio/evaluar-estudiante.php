<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// Obtener perfil del jefe de laboratorio
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

$jefeLabId = $jefeLab['id'];

// Obtener ID del reporte o estudiante
$reporteId = $_GET['reporte_id'] ?? null;
$estudianteId = $_GET['estudiante_id'] ?? null;

// Procesar evaluación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evaluar') {
    $reporteId = $_POST['reporte_id'] ?? null;
    $calificacion = $_POST['calificacion'] ?? null;
    $fortalezas = trim($_POST['fortalezas'] ?? '');
    $areasMejora = trim($_POST['areas_mejora'] ?? '');
    $observaciones = trim($_POST['observaciones_evaluador'] ?? '');
    $recomendaciones = trim($_POST['recomendaciones'] ?? '');
    $decision = $_POST['decision'] ?? 'aprobar'; // aprobar o rechazar
    
    // Validar datos requeridos
    if (!$reporteId || !$calificacion || empty($fortalezas)) {
        flashMessage('Por favor completa todos los campos requeridos', 'error');
    } else {
        try {
            $db->beginTransaction();
            
            // Verificar que el reporte pertenece al jefe de laboratorio
            $reporte = $db->fetch("
                SELECT r.*, s.jefe_laboratorio_id
                FROM reportes_bimestrales r
                JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                WHERE r.id = :reporte_id
                AND s.jefe_laboratorio_id = :jefe_id
                AND r.estado = 'pendiente_evaluacion'
            ", [
                'reporte_id' => $reporteId,
                'jefe_id' => $jefeLabId
            ]);
            
            if (!$reporte) {
                throw new Exception('Reporte no encontrado o ya fue evaluado');
            }
            
            $nuevoEstado = $decision === 'rechazar' ? 'rechazado' : 'aprobado';
            
            // Actualizar el reporte con la evaluación
            $db->update('reportes_bimestrales', [
                'estado' => $nuevoEstado,
                'calificacion' => $calificacion,
                'fortalezas' => $fortalezas,
                'areas_mejora' => $areasMejora,
                'observaciones_evaluador' => $observaciones,
                'recomendaciones' => $recomendaciones,
                'evaluado_por' => $jefeLabId,
                'fecha_evaluacion' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $reporteId]);
            
            $db->commit();
            
            $mensaje = $decision === 'rechazar' 
                ? 'Reporte rechazado. El estudiante deberá corregirlo y volverlo a entregar.' 
                : 'Reporte evaluado y aprobado exitosamente';
            
            flashMessage($mensaje, 'success');
            
            // Redirigir según de dónde vino
            if ($estudianteId) {
                redirectTo('/modules/laboratorio/reportes-estudiante.php?id=' . $reporte['estudiante_id']);
            } else {
                redirectTo('/modules/laboratorio/evaluaciones.php');
            }
            
        } catch (Exception $e) {
            $db->rollback();
            flashMessage('Error al procesar la evaluación: ' . $e->getMessage(), 'error');
        }
    }
}

// Si se proporciona reporte_id, cargar ese reporte específico
if ($reporteId) {
    $reporte = $db->fetch("
        SELECT 
            r.*,
            e.id as estudiante_id,
            e.nombre as estudiante_nombre,
            e.apellido_paterno,
            e.apellido_materno,
            e.numero_control,
            e.carrera,
            e.semestre,
            e.telefono as estudiante_telefono,
            u.email as estudiante_email,
            s.id as solicitud_id,
            s.estado as estado_servicio,
            p.nombre_proyecto,
            p.horas_requeridas,
            CASE 
                WHEN r.numero_reporte = '1' THEN 'Primer Reporte Bimestral'
                WHEN r.numero_reporte = '2' THEN 'Segundo Reporte Bimestral'
                WHEN r.numero_reporte = '3' THEN 'Tercer Reporte Bimestral'
            END as nombre_reporte
        FROM reportes_bimestrales r
        JOIN estudiantes e ON r.estudiante_id = e.id
        JOIN usuarios u ON e.usuario_id = u.id
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        WHERE r.id = :reporte_id
        AND s.jefe_laboratorio_id = :jefe_id
        AND r.estado = 'pendiente_evaluacion'
    ", [
        'reporte_id' => $reporteId,
        'jefe_id' => $jefeLabId
    ]);
    
    if (!$reporte) {
        flashMessage('Reporte no encontrado o ya fue evaluado', 'error');
        redirectTo('/modules/laboratorio/evaluaciones.php');
        exit;
    }
    
    $reportes = [$reporte];
    $estudianteId = $reporte['estudiante_id'];
    
} elseif ($estudianteId) {
    // Cargar todos los reportes pendientes del estudiante
    $reportes = $db->fetchAll("
        SELECT 
            r.*,
            e.id as estudiante_id,
            e.nombre as estudiante_nombre,
            e.apellido_paterno,
            e.apellido_materno,
            e.numero_control,
            e.carrera,
            e.semestre,
            e.telefono as estudiante_telefono,
            u.email as estudiante_email,
            s.id as solicitud_id,
            s.estado as estado_servicio,
            p.nombre_proyecto,
            p.horas_requeridas,
            CASE 
                WHEN r.numero_reporte = '1' THEN 'Primer Reporte Bimestral'
                WHEN r.numero_reporte = '2' THEN 'Segundo Reporte Bimestral'
                WHEN r.numero_reporte = '3' THEN 'Tercer Reporte Bimestral'
            END as nombre_reporte
        FROM reportes_bimestrales r
        JOIN estudiantes e ON r.estudiante_id = e.id
        JOIN usuarios u ON e.usuario_id = u.id
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        WHERE e.id = :estudiante_id
        AND s.jefe_laboratorio_id = :jefe_id
        AND r.estado = 'pendiente_evaluacion'
        ORDER BY r.numero_reporte ASC
    ", [
        'estudiante_id' => $estudianteId,
        'jefe_id' => $jefeLabId
    ]);
    
    if (empty($reportes)) {
        flashMessage('No hay reportes pendientes de evaluación para este estudiante', 'info');
        redirectTo('/modules/laboratorio/estudiantes-asignados.php');
        exit;
    }
    
} else {
    flashMessage('Debe especificar un reporte o estudiante para evaluar', 'error');
    redirectTo('/modules/laboratorio/evaluaciones.php');
    exit;
}

// Obtener información del estudiante (usar el primer reporte)
$estudianteInfo = $reportes[0];

// Obtener historial de reportes evaluados del estudiante
$reportesAnteriores = $db->fetchAll("
    SELECT r.*, 
        CASE 
            WHEN r.numero_reporte = '1' THEN 'Primer Reporte Bimestral'
            WHEN r.numero_reporte = '2' THEN 'Segundo Reporte Bimestral'
            WHEN r.numero_reporte = '3' THEN 'Tercer Reporte Bimestral'
        END as nombre_reporte
    FROM reportes_bimestrales r
    WHERE r.estudiante_id = :estudiante_id
    AND r.estado IN ('aprobado', 'rechazado')
    ORDER BY r.numero_reporte ASC
", ['estudiante_id' => $estudianteInfo['estudiante_id']]);

// Calcular promedio de calificaciones anteriores
$calificacionesAnteriores = array_filter(array_column($reportesAnteriores, 'calificacion'), fn($c) => $c !== null);
$promedioAnterior = !empty($calificacionesAnteriores) ? array_sum($calificacionesAnteriores) / count($calificacionesAnteriores) : null;

// Seleccionar el reporte actual a evaluar (el primero de la lista)
$reporteActual = $reportes[0];
$reportesPendientesCount = count($reportes);

$pageTitle = "Evaluar Reporte - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="dashboard-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../../dashboard/jefe_laboratorio.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <i class="fas fa-chevron-right"></i>
            <a href="evaluaciones.php">
                <i class="fas fa-star"></i> Evaluaciones
            </a>
            <i class="fas fa-chevron-right"></i>
            <span>Evaluar Reporte</span>
        </div>

        <!-- Header Section -->
        <div class="evaluation-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="header-info">
                    <h1>Evaluación de Reporte Bimestral</h1>
                    <p class="header-subtitle">
                        <span class="badge badge-warning">
                            <i class="fas fa-hourglass-half"></i>
                            Pendiente de Evaluación
                        </span>
                        <?php if ($reportesPendientesCount > 1): ?>
                        <span class="separator">•</span>
                        <span class="pending-count">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= $reportesPendientesCount ?> reportes pendientes de este estudiante
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="reportes-estudiante.php?id=<?= $estudianteInfo['estudiante_id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>
        </div>

        <!-- Student Info Card -->
        <div class="student-info-card">
            <div class="student-info-header">
                <div class="student-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="student-details">
                    <h2><?= htmlspecialchars($estudianteInfo['estudiante_nombre'] . ' ' . $estudianteInfo['apellido_paterno'] . ' ' . $estudianteInfo['apellido_materno']) ?></h2>
                    <div class="student-meta">
                        <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($estudianteInfo['numero_control']) ?></span>
                        <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($estudianteInfo['carrera']) ?></span>
                        <span><i class="fas fa-project-diagram"></i> <?= htmlspecialchars($estudianteInfo['nombre_proyecto']) ?></span>
                    </div>
                </div>
                <?php if ($promedioAnterior !== null): ?>
                <div class="student-performance">
                    <div class="performance-label">Promedio Anterior</div>
                    <div class="performance-value"><?= number_format($promedioAnterior, 1) ?></div>
                    <div class="performance-badge">
                        <?php if ($promedioAnterior >= 9): ?>
                            <span class="badge-excellent">Excelente</span>
                        <?php elseif ($promedioAnterior >= 8): ?>
                            <span class="badge-good">Muy Bueno</span>
                        <?php elseif ($promedioAnterior >= 7): ?>
                            <span class="badge-average">Bueno</span>
                        <?php else: ?>
                            <span class="badge-needs-improvement">Necesita Mejorar</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-grid">
            <!-- Main Content - Report Details -->
            <div class="content-main">
                <!-- Report Details Card -->
                <div class="report-details-card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-file-alt"></i>
                            <?= $reporteActual['nombre_reporte'] ?>
                        </h2>
                        <span class="report-number">Reporte #<?= $reporteActual['numero_reporte'] ?></span>
                    </div>
                    
                    <div class="card-body">
                        <!-- Report Metadata -->
                        <div class="report-metadata">
                            <div class="metadata-grid">
                                <div class="metadata-item">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <span class="metadata-label">Período del Reporte</span>
                                        <span class="metadata-value">
                                            <?= formatDate($reporteActual['periodo_inicio']) ?> - <?= formatDate($reporteActual['periodo_fin']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="metadata-item">
                                    <i class="fas fa-upload"></i>
                                    <div>
                                        <span class="metadata-label">Fecha de Entrega</span>
                                        <span class="metadata-value"><?= formatDate($reporteActual['fecha_entrega']) ?></span>
                                    </div>
                                </div>
                                <div class="metadata-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <span class="metadata-label">Horas Reportadas</span>
                                        <span class="metadata-value highlight"><?= $reporteActual['horas_reportadas'] ?> hrs</span>
                                    </div>
                                </div>
                                <div class="metadata-item">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <span class="metadata-label">Horas Acumuladas</span>
                                        <span class="metadata-value highlight"><?= $reporteActual['horas_acumuladas'] ?> hrs</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Content -->
                        <div class="report-content">
                            <div class="content-section">
                                <h3><i class="fas fa-tasks"></i> Actividades Realizadas</h3>
                                <div class="content-text">
                                    <?= nl2br(htmlspecialchars($reporteActual['actividades_realizadas'])) ?>
                                </div>
                            </div>

                            <?php if ($reporteActual['logros_obtenidos']): ?>
                            <div class="content-section">
                                <h3><i class="fas fa-trophy"></i> Logros Obtenidos</h3>
                                <div class="content-text">
                                    <?= nl2br(htmlspecialchars($reporteActual['logros_obtenidos'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($reporteActual['dificultades_encontradas']): ?>
                            <div class="content-section">
                                <h3><i class="fas fa-exclamation-triangle"></i> Dificultades Encontradas</h3>
                                <div class="content-text">
                                    <?= nl2br(htmlspecialchars($reporteActual['dificultades_encontradas'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($reporteActual['aprendizajes']): ?>
                            <div class="content-section">
                                <h3><i class="fas fa-lightbulb"></i> Aprendizajes</h3>
                                <div class="content-text">
                                    <?= nl2br(htmlspecialchars($reporteActual['aprendizajes'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Evaluation Form Card -->
                <div class="evaluation-form-card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-clipboard-check"></i>
                            Evaluación del Reporte
                        </h2>
                        <span class="required-note">* Campos obligatorios</span>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" id="evaluationForm" class="evaluation-form">
                            <input type="hidden" name="action" value="evaluar">
                            <input type="hidden" name="reporte_id" value="<?= $reporteActual['id'] ?>">
                            <input type="hidden" name="estudiante_id" value="<?= $estudianteInfo['estudiante_id'] ?>">
                            <input type="hidden" name="decision" id="decisionInput" value="aprobar">

                            <!-- Calificación -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="fas fa-star"></i>
                                    <h3>Calificación Numérica *</h3>
                                </div>
                                <div class="grade-selector">
                                    <div class="grade-slider-container">
                                        <input 
                                            type="range" 
                                            id="calificacion" 
                                            name="calificacion" 
                                            min="1" 
                                            max="10" 
                                            step="0.5" 
                                            value="8"
                                            required
                                            class="grade-slider"
                                        >
                                        <div class="grade-display">
                                            <span class="grade-value" id="gradeValue">8.0</span>
                                            <span class="grade-label" id="gradeLabel">Muy Bueno</span>
                                        </div>
                                    </div>
                                    <div class="grade-scale">
                                        <div class="scale-item">
                                            <span class="scale-number">1-5</span>
                                            <span class="scale-label">Insuficiente</span>
                                        </div>
                                        <div class="scale-item">
                                            <span class="scale-number">6-7</span>
                                            <span class="scale-label">Suficiente</span>
                                        </div>
                                        <div class="scale-item">
                                            <span class="scale-number">7-8</span>
                                            <span class="scale-label">Bueno</span>
                                        </div>
                                        <div class="scale-item">
                                            <span class="scale-number">8-9</span>
                                            <span class="scale-label">Muy Bueno</span>
                                        </div>
                                        <div class="scale-item">
                                            <span class="scale-number">9-10</span>
                                            <span class="scale-label">Excelente</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fortalezas -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="fas fa-thumbs-up"></i>
                                    <h3>Fortalezas Identificadas *</h3>
                                </div>
                                <div class="form-help">
                                    Menciona los aspectos positivos del desempeño del estudiante, lo que hizo bien y debe continuar haciendo.
                                </div>
                                <textarea 
                                    id="fortalezas" 
                                    name="fortalezas" 
                                    class="form-control"
                                    rows="5"
                                    required
                                    maxlength="1000"
                                    placeholder="Ej: Demuestra iniciativa en la resolución de problemas, buena comunicación con el equipo, cumplimiento puntual de tareas..."
                                ></textarea>
                                <div class="char-counter">
                                    <span id="fortalezasCounter">0</span> / 1000 caracteres
                                </div>
                            </div>

                            <!-- Áreas de Mejora -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>Áreas de Mejora</h3>
                                </div>
                                <div class="form-help">
                                    Señala aspectos que el estudiante puede mejorar en su desempeño. Sé constructivo y específico.
                                </div>
                                <textarea 
                                    id="areas_mejora" 
                                    name="areas_mejora" 
                                    class="form-control"
                                    rows="5"
                                    maxlength="1000"
                                    placeholder="Ej: Puede mejorar la documentación de sus procesos, desarrollar más autonomía en tareas complejas..."
                                ></textarea>
                                <div class="char-counter">
                                    <span id="areasMejoraCounter">0</span> / 1000 caracteres
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="fas fa-comment"></i>
                                    <h3>Observaciones Generales</h3>
                                </div>
                                <div class="form-help">
                                    Comentarios adicionales sobre el reporte o el desempeño del estudiante durante este período.
                                </div>
                                <textarea 
                                    id="observaciones_evaluador" 
                                    name="observaciones_evaluador" 
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Comentarios adicionales sobre el desempeño o el reporte..."
                                ></textarea>
                                <div class="char-counter">
                                    <span id="observacionesCounter">0</span> / 1000 caracteres
                                </div>
                            </div>

                            <!-- Recomendaciones -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class="fas fa-lightbulb"></i>
                                    <h3>Recomendaciones para el Siguiente Período</h3>
                                </div>
                                <div class="form-help">
                                    Sugerencias y orientaciones para el siguiente reporte o para continuar mejorando.
                                </div>
                                <textarea 
                                    id="recomendaciones" 
                                    name="recomendaciones" 
                                    class="form-control"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Ej: Enfocar esfuerzos en ampliar conocimientos en X área, participar más activamente en reuniones de equipo..."
                                ></textarea>
                                <div class="char-counter">
                                    <span id="recomendacionesCounter">0</span> / 1000 caracteres
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions">
                                <button type="button" class="btn btn-error" onclick="confirmarRechazo()">
                                    <i class="fas fa-times-circle"></i>
                                    Rechazar Reporte
                                </button>
                                <button type="button" class="btn btn-success" onclick="confirmarAprobacion()">
                                    <i class="fas fa-check-circle"></i>
                                    Aprobar y Guardar Evaluación
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar - Additional Info -->
            <div class="content-sidebar">
                <!-- Evaluation Guidelines -->
                <div class="sidebar-card guidelines-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Guía de Evaluación
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="guideline-item">
                            <div class="guideline-icon excellent">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="guideline-content">
                                <h4>9.0 - 10.0 (Excelente)</h4>
                                <p>Supera todas las expectativas, muestra iniciativa excepcional y alto compromiso.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <div class="guideline-icon good">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="guideline-content">
                                <h4>8.0 - 8.9 (Muy Bueno)</h4>
                                <p>Cumple y supera las expectativas, demuestra compromiso y calidad en su trabajo.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <div class="guideline-icon average">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="guideline-content">
                                <h4>7.0 - 7.9 (Bueno)</h4>
                                <p>Cumple con las expectativas, realiza su trabajo de manera satisfactoria.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <div class="guideline-icon needs-improvement">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="guideline-content">
                                <h4>6.0 - 6.9 (Suficiente)</h4>
                                <p>Cumple mínimamente, necesita mejorar en varios aspectos.</p>
                            </div>
                        </div>
                        <div class="guideline-item">
                            <div class="guideline-icon insufficient">
                                <i class="fas fa-times"></i>
                            </div>
                            <div class="guideline-content">
                                <h4>< 6.0 (Insuficiente)</h4>
                                <p>No cumple con las expectativas mínimas, requiere atención inmediata.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Reports -->
                <?php if (!empty($reportesAnteriores)): ?>
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-history"></i>
                            Reportes Anteriores
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="previous-reports-list">
                            <?php foreach ($reportesAnteriores as $prevReport): ?>
                            <div class="previous-report-item">
                                <div class="report-header-mini">
                                    <span class="report-number-mini">#<?= $prevReport['numero_reporte'] ?></span>
                                    <span class="badge <?= getEstadoBadgeClass($prevReport['estado']) ?>">
                                        <?= getEstadoText($prevReport['estado']) ?>
                                    </span>
                                </div>
                                <div class="report-info-mini">
                                    <?php if ($prevReport['calificacion']): ?>
                                    <div class="info-row-mini">
                                        <i class="fas fa-star"></i>
                                        <span>Calificación: <strong><?= number_format($prevReport['calificacion'], 1) ?></strong></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-row-mini">
                                        <i class="fas fa-clock"></i>
                                        <span><?= $prevReport['horas_reportadas'] ?> hrs reportadas</span>
                                    </div>
                                    <div class="info-row-mini">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= formatDate($prevReport['fecha_evaluacion']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($promedioAnterior !== null): ?>
                        <div class="average-display">
                            <i class="fas fa-chart-bar"></i>
                            <span>Promedio: <strong><?= number_format($promedioAnterior, 1) ?></strong></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tips -->
                <div class="sidebar-card tips-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-lightbulb"></i>
                            Consejos para Evaluar
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="tip-item">
                            <i class="fas fa-check-circle"></i>
                            <p>Sé específico en tus comentarios para que el estudiante sepa qué mejorar.</p>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-balance-scale"></i>
                            <p>Balancea críticas constructivas con reconocimiento de fortalezas.</p>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-comments"></i>
                            <p>Usa un lenguaje claro y profesional que motive al estudiante.</p>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-bullseye"></i>
                            <p>Enfócate en comportamientos observables y resultados concretos.</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Reports Alert -->
                <?php if ($reportesPendientesCount > 1): ?>
                <div class="sidebar-card alert-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-bell"></i>
                            Reportes Pendientes
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="alert-message">
                            Este estudiante tiene <strong><?= $reportesPendientesCount ?> reportes</strong> pendientes de evaluación.
                        </p>
                        <p class="alert-note">
                            Después de evaluar este reporte, podrás continuar con los siguientes.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmación -->
<div id="confirmModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">
                <i class="fas fa-check-circle"></i>
                Confirmar Evaluación
            </h3>
        </div>
        <div class="modal-body">
            <div class="confirmation-message" id="modalMessage">
                ¿Estás seguro de que deseas aprobar este reporte con la calificación y comentarios proporcionados?
            </div>
            <div class="evaluation-summary" id="evaluationSummary"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <button type="button" class="btn btn-success" id="confirmButton" onclick="submitForm()">
                <i class="fas fa-check"></i>
                Confirmar
            </button>
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
    max-width: calc(1600px - var(--sidebar-width));
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
        max-width: 1600px;
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
    max-width: 1600px;
    margin: 0 auto;
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.breadcrumb a {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb a:hover {
    color: var(--primary);
}

.breadcrumb i.fa-chevron-right {
    font-size: 0.7rem;
}

.breadcrumb span {
    color: var(--text-primary);
    font-weight: 500;
}

/* Evaluation Header */
.evaluation-header {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}

.header-info h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
}

.header-subtitle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.95rem;
    opacity: 0.95;
}

.separator {
    opacity: 0.6;
}

.pending-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Student Info Card */
.student-info-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.student-info-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    flex-shrink: 0;
    box-shadow: var(--shadow-lg);
}

.student-details {
    flex: 1;
}

.student-details h2 {
    margin: 0 0 0.75rem 0;
    font-size: 1.5rem;
    color: var(--text-primary);
}

.student-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.student-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.student-performance {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    min-width: 120px;
}

.performance-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.performance-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.performance-badge span {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-excellent { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-good { background: rgba(33, 150, 243, 0.1); color: var(--info); }
.badge-average { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.badge-needs-improvement { background: rgba(239, 68, 68, 0.1); color: var(--error); }

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
}

.content-main {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.content-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Cards */
.report-details-card,
.evaluation-form-card,
.sidebar-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 2px solid var(--border-light);
}

.card-header h2,
.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-weight: 600;
    color: var(--text-primary);
}

.card-header h2 {
    font-size: 1.25rem;
}

.card-header h3 {
    font-size: 1rem;
}

.card-header i {
    color: var(--primary);
}

.report-number {
    background: var(--primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.required-note {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.card-body {
    padding: 2rem;
}

/* Report Metadata */
.report-metadata {
    margin-bottom: 2rem;
}

.metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.metadata-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.metadata-item i {
    font-size: 1.25rem;
    color: var(--primary);
    margin-top: 0.25rem;
}

.metadata-item > div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.metadata-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.metadata-value {
    font-size: 0.95rem;
    color: var(--text-primary);
    font-weight: 500;
}

.metadata-value.highlight {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary);
}

/* Report Content */
.report-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.content-section {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.content-section h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    color: var(--text-primary);
}

.content-section h3 i {
    color: var(--primary);
}

.content-text {
    color: var(--text-secondary);
    line-height: 1.7;
}

/* Evaluation Form */
.evaluation-form {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.section-header i {
    font-size: 1.25rem;
    color: var(--primary);
}

.section-header h3 {
    margin: 0;
    font-size: 1.125rem;
    color: var(--text-primary);
    font-weight: 600;
}

.form-help {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
    padding: 0.75rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 3px solid var(--info);
}

.form-control {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    font-family: inherit;
    transition: var(--transition);
    resize: vertical;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.char-counter {
    text-align: right;
    font-size: 0.85rem;
    color: var(--text-light);
}

/* Grade Selector */
.grade-selector {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding: 2rem;
    background: var(--bg-light);
    border-radius: var(--radius-lg);
}

.grade-slider-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.grade-slider {
    width: 100%;
    height: 10px;
    border-radius: 5px;
    background: linear-gradient(90deg, var(--error), var(--warning), var(--success));
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}

.grade-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    border: 4px solid var(--primary);
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
}

.grade-slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}

.grade-slider::-moz-range-thumb {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    border: 4px solid var(--primary);
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
}

.grade-slider::-moz-range-thumb:hover {
    transform: scale(1.2);
}

.grade-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.grade-value {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}

.grade-label {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.grade-scale {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 1rem;
}

.scale-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0.75rem;
    background: var(--bg-white);
    border-radius: var(--radius);
}

.scale-number {
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.scale-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border-light);
}

/* Sidebar Cards */
.guidelines-card .card-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.guideline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.guideline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.guideline-icon.excellent { background: linear-gradient(135deg, var(--success), #34d399); }
.guideline-icon.good { background: linear-gradient(135deg, var(--info), #60a5fa); }
.guideline-icon.average { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.guideline-icon.needs-improvement { background: linear-gradient(135deg, #f59e0b, #fb923c); }
.guideline-icon.insufficient { background: linear-gradient(135deg, var(--error), #f87171); }

.guideline-content h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: var(--text-primary);
}

.guideline-content p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Previous Reports */
.previous-reports-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.previous-report-item {
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.report-header-mini {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.report-number-mini {
    font-weight: 700;
    color: var(--text-primary);
}

.report-info-mini {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-row-mini {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.info-row-mini i {
    color: var(--primary);
    width: 14px;
}

.average-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    margin-top: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    border: 2px solid var(--primary);
    font-size: 0.95rem;
    color: var(--text-primary);
}

/* Tips Card */
.tips-card .card-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tip-item {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.tip-item i {
    color: var(--primary);
    font-size: 1.125rem;
    flex-shrink: 0;
}

.tip-item p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Alert Card */
.alert-card {
    background: rgba(245, 158, 11, 0.05);
    border: 2px solid var(--warning);
}

.alert-message {
    margin: 0 0 0.75rem 0;
    color: var(--text-primary);
    line-height: 1.6;
}

.alert-note {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-style: italic;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
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

.modal-body {
    padding: 2rem;
}

.confirmation-message {
    font-size: 1.125rem;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.evaluation-summary {
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 600;
    color: var(--text-secondary);
}

.summary-value {
    color: var(--text-primary);
    font-weight: 500;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
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

.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
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

.report-details-card,
.evaluation-form-card,
.sidebar-card {
    animation: slideIn 0.4s ease-out;
}

.report-details-card { animation-delay: 0.1s; }
.evaluation-form-card { animation-delay: 0.2s; }

/* Responsive Design */
@media (max-width: 1400px) {
    .content-grid {
        grid-template-columns: 1fr 350px;
    }
}

@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .content-sidebar {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
}

@media (max-width: 1024px) {
    .evaluation-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .student-info-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .metadata-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .evaluation-header,
    .student-info-card,
    .card-body {
        padding: 1.5rem;
    }
    
    .student-meta {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .grade-scale {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .evaluation-header {
        padding: 1rem;
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .student-avatar {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }
    
    .student-details h2 {
        font-size: 1.25rem;
    }
    
    .grade-value {
        font-size: 2.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grade slider functionality
    const gradeSlider = document.getElementById('calificacion');
    const gradeValue = document.getElementById('gradeValue');
    const gradeLabel = document.getElementById('gradeLabel');
    
    function updateGradeDisplay(value) {
        gradeValue.textContent = parseFloat(value).toFixed(1);
        
        if (value >= 9) {
            gradeLabel.textContent = 'Excelente';
            gradeLabel.style.color = 'var(--success)';
        } else if (value >= 8) {
            gradeLabel.textContent = 'Muy Bueno';
            gradeLabel.style.color = 'var(--info)';
        } else if (value >= 7) {
            gradeLabel.textContent = 'Bueno';
            gradeLabel.style.color = 'var(--warning)';
        } else if (value >= 6) {
            gradeLabel.textContent = 'Suficiente';
            gradeLabel.style.color = '#f59e0b';
        } else {
            gradeLabel.textContent = 'Insuficiente';
            gradeLabel.style.color = 'var(--error)';
        }
    }
    
    gradeSlider.addEventListener('input', function() {
        updateGradeDisplay(this.value);
    });
    
    // Character counters
    const textareas = {
        'fortalezas': 'fortalezasCounter',
        'areas_mejora': 'areasMejoraCounter',
        'observaciones_evaluador': 'observacionesCounter',
        'recomendaciones': 'recomendacionesCounter'
    };
    
    Object.keys(textareas).forEach(textareaId => {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(textareas[textareaId]);
        
        if (textarea && counter) {
            textarea.addEventListener('input', function() {
                counter.textContent = this.value.length;
                
                if (this.value.length >= 900) {
                    counter.style.color = 'var(--error)';
                } else if (this.value.length >= 800) {
                    counter.style.color = 'var(--warning)';
                } else {
                    counter.style.color = 'var(--text-light)';
                }
            });
        }
    });
    
    // Initialize displays
    updateGradeDisplay(gradeSlider.value);
});

function confirmarAprobacion() {
    const calificacion = document.getElementById('calificacion').value;
    const fortalezas = document.getElementById('fortalezas').value.trim();
    
    if (!fortalezas) {
        alert('Por favor, completa el campo de Fortalezas antes de aprobar.');
        document.getElementById('fortalezas').focus();
        return;
    }
    
    document.getElementById('decisionInput').value = 'aprobar';
    
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmButton = document.getElementById('confirmButton');
    const evaluationSummary = document.getElementById('evaluationSummary');
    
    modalTitle.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar Aprobación';
    modalMessage.textContent = '¿Estás seguro de que deseas aprobar este reporte con la siguiente evaluación?';
    confirmButton.className = 'btn btn-success';
    confirmButton.innerHTML = '<i class="fas fa-check"></i> Aprobar Reporte';
    
    evaluationSummary.innerHTML = `
        <div class="summary-item">
            <span class="summary-label">Calificación:</span>
            <span class="summary-value"><strong>${parseFloat(calificacion).toFixed(1)}</strong></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Fortalezas:</span>
            <span class="summary-value">${fortalezas.substring(0, 100)}${fortalezas.length > 100 ? '...' : ''}</span>
        </div>
    `;
    
    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function confirmarRechazo() {
    const fortalezas = document.getElementById('fortalezas').value.trim();
    const areasMejora = document.getElementById('areas_mejora').value.trim();
    
    if (!fortalezas || !areasMejora) {
        alert('Para rechazar un reporte, debes completar tanto las Fortalezas como las Áreas de Mejora, explicando claramente qué debe corregir el estudiante.');
        if (!fortalezas) document.getElementById('fortalezas').focus();
        else document.getElementById('areas_mejora').focus();
        return;
    }
    
    if (!confirm('¿Estás seguro de que deseas RECHAZAR este reporte?\n\nEl estudiante deberá corregirlo y volverlo a entregar.')) {
        return;
    }
    
    document.getElementById('decisionInput').value = 'rechazar';
    
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmButton = document.getElementById('confirmButton');
    const evaluationSummary = document.getElementById('evaluationSummary');
    
    modalTitle.innerHTML = '<i class="fas fa-times-circle"></i> Confirmar Rechazo';
    modalMessage.textContent = 'El reporte será rechazado y el estudiante deberá corregirlo. ¿Confirmas esta acción?';
    confirmButton.className = 'btn btn-error';
    confirmButton.innerHTML = '<i class="fas fa-times"></i> Rechazar Reporte';
    
    evaluationSummary.innerHTML = `
        <div class="summary-item">
            <span class="summary-label">Acción:</span>
            <span class="summary-value"><strong>RECHAZAR REPORTE</strong></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Áreas de Mejora:</span>
            <span class="summary-value">${areasMejora.substring(0, 100)}${areasMejora.length > 100 ? '...' : ''}</span>
        </div>
    `;
    
    document.getElementById('confirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModal() {
    document.getElementById('confirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function submitForm() {
    const form = document.getElementById('evaluationForm');
    const confirmButton = document.getElementById('confirmButton');
    
    confirmButton.disabled = true;
    confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
    form.submit();
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});

// Prevenir pérdida de datos
let formChanged = false;
document.getElementById('evaluationForm').addEventListener('input', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

console.log('✅ Página de evaluación de estudiante inicializada');
</script>

<?php
// Helper function for estado icons
function getEstadoIcon($estado) {
    $icons = [
        'pendiente_evaluacion' => 'hourglass-half',
        'aprobado' => 'check-circle',
        'rechazado' => 'times-circle'
    ];
    return $icons[$estado] ?? 'question-circle';
}

include '../../includes/footer.php';
?>