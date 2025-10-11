<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();

// Obtener el jefe de laboratorio
$jefeLab = $db->fetch("SELECT id, nombre, laboratorio FROM jefes_laboratorio WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeLab) {
    flashMessage('No se encontró el perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
}
$jefeLabId = $jefeLab['id'];

// Obtener ID del reporte
$reporteId = $_GET['id'] ?? null;
if (!$reporteId) {
    flashMessage('ID de reporte no especificado', 'error');
    redirectTo('evaluaciones.php');
}

// Obtener información del reporte
$reporte = $db->fetch("
    SELECT rb.*, 
           e.nombre, e.apellido_paterno, e.apellido_materno, e.numero_control, e.carrera, e.telefono,
           p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio,
           s.fecha_inicio_propuesta, s.fecha_fin_propuesta,
           u.email as evaluador_email
    FROM reportes_bimestrales rb
    JOIN estudiantes e ON rb.estudiante_id = e.id
    JOIN solicitudes_servicio s ON rb.solicitud_id = s.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON rb.jefe_laboratorio_id = jl.id
    LEFT JOIN usuarios u ON rb.evaluado_por = u.id
    WHERE rb.id = :reporte_id AND s.jefe_laboratorio_id = :jefe_id
", ['reporte_id' => $reporteId, 'jefe_id' => $jefeLabId]);

if (!$reporte) {
    flashMessage('Reporte no encontrado o no tienes permisos para evaluarlo', 'error');
    redirectTo('evaluaciones.php');
}

// Procesar formulario de evaluación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluar'])) {
    $calificacion = floatval($_POST['calificacion'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fortalezas = trim($_POST['fortalezas'] ?? '');
    $areas_mejora = trim($_POST['areas_mejora'] ?? '');
    $accion = $_POST['accion'] ?? 'evaluar'; // evaluar, aprobar, rechazar
    
    // Validaciones
    $errores = [];
    
    if ($calificacion < 0 || $calificacion > 10) {
        $errores[] = 'La calificación debe estar entre 0 y 10';
    }
    
    if (empty($observaciones)) {
        $errores[] = 'Las observaciones son obligatorias';
    }
    
    if (empty($errores)) {
        // Determinar el nuevo estado
        $nuevoEstado = 'evaluado';
        if ($accion == 'aprobar') {
            $nuevoEstado = 'aprobado';
        } elseif ($accion == 'rechazar') {
            $nuevoEstado = 'rechazado';
        }
        
        // Actualizar el reporte
        $updated = $db->execute("
            UPDATE reportes_bimestrales 
            SET estado = :estado,
                calificacion = :calificacion,
                observaciones_evaluador = :observaciones,
                fortalezas = :fortalezas,
                areas_mejora = :areas_mejora,
                evaluado_por = :evaluado_por,
                fecha_evaluacion = NOW()
            WHERE id = :reporte_id
        ", [
            'estado' => $nuevoEstado,
            'calificacion' => $calificacion,
            'observaciones' => $observaciones,
            'fortalezas' => $fortalezas,
            'areas_mejora' => $areas_mejora,
            'evaluado_por' => $usuario['id'],
            'reporte_id' => $reporteId
        ]);
        
        if ($updated) {
            // Registrar en log de actividades
            $db->execute("
                INSERT INTO log_actividades (usuario_id, accion, modulo, registro_afectado_id, detalles)
                VALUES (:usuario_id, :accion, :modulo, :registro_id, :detalles)
            ", [
                'usuario_id' => $usuario['id'],
                'accion' => 'evaluar_reporte_bimestral',
                'modulo' => 'evaluaciones',
                'registro_id' => $reporteId,
                'detalles' => json_encode([
                    'estudiante_id' => $reporte['estudiante_id'],
                    'numero_reporte' => $reporte['numero_reporte'],
                    'calificacion' => $calificacion,
                    'estado' => $nuevoEstado
                ])
            ]);
            
            // Crear notificación para el estudiante
            $db->execute("
                INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, url_accion)
                VALUES (:usuario_id, :titulo, :mensaje, :tipo, :url)
            ", [
                'usuario_id' => $reporte['estudiante_id'],
                'titulo' => 'Reporte Bimestral Evaluado',
                'mensaje' => 'Tu reporte bimestral ' . $reporte['numero_reporte'] . ' ha sido evaluado. Calificación: ' . $calificacion,
                'tipo' => 'success',
                'url' => '/dashboard/estudiante.php'
            ]);
            
            flashMessage('Reporte evaluado exitosamente', 'success');
            redirectTo('evaluaciones.php');
        } else {
            flashMessage('Error al guardar la evaluación', 'error');
        }
    } else {
        foreach ($errores as $error) {
            flashMessage($error, 'error');
        }
    }
}

$soloLectura = !in_array($reporte['estado'], ['pendiente_evaluacion', 'revision']);

$pageTitle = "Evaluar Reporte Bimestral - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
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
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 0.5rem;
}

body {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.dashboard-content {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.page-header {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--warning);
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header p {
    color: var(--text-secondary);
    margin: 0;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

/* Student Info Card */
.student-info-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.student-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.student-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: 700;
}

.student-details h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.student-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.meta-item i {
    color: var(--warning);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-value {
    font-size: 1.05rem;
    font-weight: 500;
    color: var(--text-primary);
}

/* Report Content Card */
.report-content-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.content-section {
    margin-bottom: 2rem;
}

.content-section:last-child {
    margin-bottom: 0;
}

.content-section h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.content-section p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

/* Evaluation Form */
.evaluation-form-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group label .required {
    color: var(--error);
}

.form-group input[type="number"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.95rem;
    color: var(--text-primary);
    font-family: inherit;
}

.form-group input[type="number"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--warning);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-light);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-error {
    background: var(--error);
    color: white;
}

.btn-error:hover {
    background: #dc2626;
}

.btn-secondary {
    background: var(--text-secondary);
    color: white;
}

.btn-secondary:hover {
    background: var(--text-primary);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.btn-outline:hover {
    background: var(--bg-light);
}

/* Sidebar Cards */
.sidebar-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.sidebar-card h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 500;
}

.status-badge.pendiente_evaluacion {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.evaluado {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.aprobado {
    background: #d1fae5;
    color: #065f46;
}

.alert {
    padding: 1rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border-left: 4px solid var(--info);
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid var(--warning);
}

@media (max-width: 968px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-star"></i>
            Evaluar Reporte Bimestral <?= $reporte['numero_reporte'] ?>
        </h1>
        <p>Evaluación del reporte bimestral de <?= htmlspecialchars($reporte['nombre'] . ' ' . $reporte['apellido_paterno']) ?></p>
    </div>

    <?php if ($soloLectura): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Este reporte ya ha sido evaluado. La información se muestra en modo de solo lectura.
    </div>
    <?php endif; ?>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div>
            <!-- Student Info -->
            <div class="student-info-card">
                <div class="student-header">
                    <div class="student-avatar">
                        <?= strtoupper(substr($reporte['nombre'], 0, 1)) ?>
                    </div>
                    <div class="student-details">
                        <h2><?= htmlspecialchars($reporte['nombre'] . ' ' . $reporte['apellido_paterno'] . ' ' . $reporte['apellido_materno']) ?></h2>
                        <div class="student-meta">
                            <span class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <?= htmlspecialchars($reporte['numero_control']) ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-graduation-cap"></i>
                                <?= htmlspecialchars($reporte['carrera']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Proyecto</span>
                        <span class="info-value"><?= htmlspecialchars($reporte['nombre_proyecto']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Laboratorio</span>
                        <span class="info-value"><?= htmlspecialchars($reporte['laboratorio'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Periodo</span>
                        <span class="info-value">
                            <?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Report Content -->
            <div class="report-content-card">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Contenido del Reporte
                </h2>

                <div class="content-section">
                    <h3>Actividades Realizadas</h3>
                    <p><?= nl2br(htmlspecialchars($reporte['actividades_realizadas'])) ?></p>
                </div>

                <?php if ($reporte['logros_obtenidos']): ?>
                <div class="content-section">
                    <h3>Logros Obtenidos</h3>
                    <p><?= nl2br(htmlspecialchars($reporte['logros_obtenidos'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($reporte['dificultades_encontradas']): ?>
                <div class="content-section">
                    <h3>Dificultades Encontradas</h3>
                    <p><?= nl2br(htmlspecialchars($reporte['dificultades_encontradas'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Evaluation Form -->
            <div class="evaluation-form-card">
                <h2 class="section-title">
                    <i class="fas fa-clipboard-check"></i>
                    <?= $soloLectura ? 'Evaluación Registrada' : 'Formulario de Evaluación' ?>
                </h2>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="calificacion">
                            Calificación <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="calificacion" 
                            name="calificacion" 
                            min="0" 
                            max="10" 
                            step="0.1" 
                            value="<?= $reporte['calificacion'] ?? '' ?>"
                            <?= $soloLectura ? 'readonly' : 'required' ?>
                        >
                        <small>Calificación de 0 a 10</small>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">
                            Observaciones y Comentarios <span class="required">*</span>
                        </label>
                        <textarea 
                            id="observaciones" 
                            name="observaciones" 
                            <?= $soloLectura ? 'readonly' : 'required' ?>
                        ><?= htmlspecialchars($reporte['observaciones_evaluador'] ?? '') ?></textarea>
                        <small>Proporciona retroalimentación detallada sobre el desempeño del estudiante</small>
                    </div>

                    <div class="form-group">
                        <label for="fortalezas">
                            Fortalezas Identificadas
                        </label>
                        <textarea 
                            id="fortalezas" 
                            name="fortalezas"
                            <?= $soloLectura ? 'readonly' : '' ?>
                        ><?= htmlspecialchars($reporte['fortalezas'] ?? '') ?></textarea>
                        <small>Aspectos positivos y puntos fuertes del estudiante</small>
                    </div>

                    <div class="form-group">
                        <label for="areas_mejora">
                            Áreas de Mejora
                        </label>
                        <textarea 
                            id="areas_mejora" 
                            name="areas_mejora"
                            <?= $soloLectura ? 'readonly' : '' ?>
                        ><?= htmlspecialchars($reporte['areas_mejora'] ?? '') ?></textarea>
                        <small>Aspectos que el estudiante puede mejorar</small>
                    </div>

                    <?php if (!$soloLectura): ?>
                    <div class="form-actions">
                        <button type="submit" name="evaluar" value="1" class="btn btn-primary" onclick="document.querySelector('input[name=accion]').value='evaluar'">
                            <i class="fas fa-save"></i>
                            Guardar Evaluación
                        </button>
                        <button type="submit" name="evaluar" value="1" class="btn btn-success" onclick="document.querySelector('input[name=accion]').value='aprobar'">
                            <i class="fas fa-check"></i>
                            Aprobar Reporte
                        </button>
                        <button type="submit" name="evaluar" value="1" class="btn btn-error" onclick="if(!confirm('¿Estás seguro de rechazar este reporte?')) return false; document.querySelector('input[name=accion]').value='rechazar'">
                            <i class="fas fa-times"></i>
                            Rechazar Reporte
                        </button>
                        <a href="evaluaciones.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Cancelar
                        </a>
                    </div>
                    <input type="hidden" name="accion" value="evaluar">
                    <?php else: ?>
                    <div class="form-actions">
                        <a href="evaluaciones.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Volver a Evaluaciones
                        </a>
                        <a href="estudiante-historial.php?id=<?= $reporte['estudiante_id'] ?>" class="btn btn-outline">
                            <i class="fas fa-history"></i>
                            Ver Historial Completo
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Report Stats -->
            <div class="sidebar-card">
                <h3>Información del Reporte</h3>
                <div class="stat-item">
                    <span class="stat-label">Número de Reporte</span>
                    <span class="stat-value"><?= $reporte['numero_reporte'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Fecha de Entrega</span>
                    <span class="stat-value"><?= formatDate($reporte['fecha_entrega']) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Horas Reportadas</span>
                    <span class="stat-value"><?= $reporte['horas_reportadas'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Horas Acumuladas</span>
                    <span class="stat-value"><?= $reporte['horas_acumuladas'] ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Estado</span>
                    <span class="status-badge <?= $reporte['estado'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $reporte['estado'])) ?>
                    </span>
                </div>
            </div>

            <?php if ($reporte['evaluado_por']): ?>
            <!-- Evaluation Info -->
            <div class="sidebar-card">
                <h3>Información de Evaluación</h3>
                <div class="stat-item">
                    <span class="stat-label">Evaluado por</span>
                    <span class="stat-value" style="font-size: 0.9rem;"><?= htmlspecialchars($reporte['evaluador_email']) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Fecha de Evaluación</span>
                    <span class="stat-value" style="font-size: 0.9rem;">
                        <?= $reporte['fecha_evaluacion'] ? date('d/m/Y H:i', strtotime($reporte['fecha_evaluacion'])) : 'N/A' ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="sidebar-card">
                <h3>Acciones</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="estudiante-historial.php?id=<?= $reporte['estudiante_id'] ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                        <i class="fas fa-history"></i>
                        Ver Historial
                    </a>
                    <?php if ($reporte['archivo_path']): ?>
                    <a href="<?= htmlspecialchars($reporte['archivo_path']) ?>" class="btn btn-outline" style="width: 100%; justify-content: center;" target="_blank">
                        <i class="fas fa-download"></i>
                        Descargar Reporte
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>