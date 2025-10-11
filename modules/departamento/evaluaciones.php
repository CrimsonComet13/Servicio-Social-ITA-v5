<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Obtener el jefe de departamento
$jefeDepto = $db->fetch("SELECT id, nombre, departamento FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

// Filtros
$tipoFiltro = $_GET['tipo'] ?? 'todos'; // todos, bimestral, final
$estadoFiltro = $_GET['estado'] ?? 'pendiente'; // pendiente, evaluado, todos

// Obtener reportes bimestrales pendientes
$whereConditionsBimestral = ["s.jefe_departamento_id = :jefe_id"];
$paramsBimestral = ['jefe_id' => $jefeId];

if ($estadoFiltro == 'pendiente') {
    $whereConditionsBimestral[] = "rb.estado IN ('pendiente_evaluacion', 'revision')";
} elseif ($estadoFiltro == 'evaluado') {
    $whereConditionsBimestral[] = "rb.estado IN ('evaluado', 'aprobado')";
}

$whereClauseBimestral = implode(' AND ', $whereConditionsBimestral);

$reportesBimestrales = [];
if ($tipoFiltro == 'todos' || $tipoFiltro == 'bimestral') {
    $reportesBimestrales = $db->fetchAll("
        SELECT rb.*, 
               e.nombre, e.apellido_paterno, e.apellido_materno, e.numero_control, e.carrera,
               p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio,
               u.email as evaluador_email
        FROM reportes_bimestrales rb
        JOIN estudiantes e ON rb.estudiante_id = e.id
        JOIN solicitudes_servicio s ON rb.solicitud_id = s.id
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        LEFT JOIN jefes_laboratorio jl ON rb.jefe_laboratorio_id = jl.id
        LEFT JOIN usuarios u ON rb.evaluado_por = u.id
        WHERE $whereClauseBimestral
        ORDER BY rb.fecha_entrega DESC, rb.created_at DESC
    ", $paramsBimestral);
}

// Obtener reportes finales pendientes
$whereConditionsFinal = ["s.jefe_departamento_id = :jefe_id"];
$paramsFinal = ['jefe_id' => $jefeId];

if ($estadoFiltro == 'pendiente') {
    $whereConditionsFinal[] = "rf.estado IN ('pendiente_evaluacion', 'revision')";
} elseif ($estadoFiltro == 'evaluado') {
    $whereConditionsFinal[] = "rf.estado IN ('evaluado', 'aprobado')";
}

$whereClauseFinal = implode(' AND ', $whereConditionsFinal);

$reportesFinales = [];
if ($tipoFiltro == 'todos' || $tipoFiltro == 'final') {
    $reportesFinales = $db->fetchAll("
        SELECT rf.*, 
               e.nombre, e.apellido_paterno, e.apellido_materno, e.numero_control, e.carrera,
               p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio,
               u.email as evaluador_email
        FROM reportes_finales rf
        JOIN estudiantes e ON rf.estudiante_id = e.id
        JOIN solicitudes_servicio s ON rf.solicitud_id = s.id
        JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
        LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
        LEFT JOIN usuarios u ON rf.evaluado_por = u.id
        WHERE $whereClauseFinal
        ORDER BY rf.fecha_entrega DESC, rf.created_at DESC
    ", $paramsFinal);
}

// Obtener estadísticas
// ✅ CORRECTO - 4 consultas separadas
$statBimestralesPendientes = $db->fetch("
    SELECT COUNT(*) as total
    FROM reportes_bimestrales rb
    JOIN solicitudes_servicio s ON rb.solicitud_id = s.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND rb.estado IN ('pendiente_evaluacion', 'revision')
", ['jefe_id' => $jefeId]);

$statFinalesPendientes = $db->fetch("
    SELECT COUNT(*) as total
    FROM reportes_finales rf
    JOIN solicitudes_servicio s ON rf.solicitud_id = s.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND rf.estado IN ('pendiente_evaluacion', 'revision')
", ['jefe_id' => $jefeId]);

$statBimestralesEvaluados = $db->fetch("
    SELECT COUNT(*) as total
    FROM reportes_bimestrales rb
    JOIN solicitudes_servicio s ON rb.solicitud_id = s.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND rb.estado IN ('evaluado', 'aprobado')
", ['jefe_id' => $jefeId]);

$statFinalesEvaluados = $db->fetch("
    SELECT COUNT(*) as total
    FROM reportes_finales rf
    JOIN solicitudes_servicio s ON rf.solicitud_id = s.id
    WHERE s.jefe_departamento_id = :jefe_id 
    AND rf.estado IN ('evaluado', 'aprobado')
", ['jefe_id' => $jefeId]);

$bimestralesPendientes = $statBimestralesPendientes['total'] ?? 0;
$finalesPendientes = $statFinalesPendientes['total'] ?? 0;
$bimestralesEvaluados = $statBimestralesEvaluados['total'] ?? 0;
$finalesEvaluados = $statFinalesEvaluados['total'] ?? 0;

$totalPendientes = $bimestralesPendientes + $finalesPendientes;
$totalEvaluados = $bimestralesEvaluados + $finalesEvaluados;

$pageTitle = "Evaluaciones - " . APP_NAME;
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
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.page-header {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
}

.stat-card.warning {
    border-left-color: var(--warning);
}

.stat-card.success {
    border-left-color: var(--success);
}

.stat-card.info {
    border-left-color: var(--info);
}

.stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-description {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Filters */
.filters-section {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.filter-group select {
    padding: 0.625rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.95rem;
    color: var(--text-primary);
    background: var(--bg-white);
}

.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Evaluations List */
.evaluations-section {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.evaluations-grid {
    display: grid;
    gap: 1rem;
}

.evaluation-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.evaluation-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.student-info {
    flex: 1;
}

.student-info h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.student-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.meta-item i {
    color: var(--primary);
}

.evaluation-badge {
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-weight: 500;
    white-space: nowrap;
}

.evaluation-badge.bimestral {
    background: #dbeafe;
    color: #1e40af;
}

.evaluation-badge.final {
    background: #fef3c7;
    color: #92400e;
}

.evaluation-body {
    margin-bottom: 1rem;
}

.project-info {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1rem;
}

.project-info h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.project-info p {
    color: var(--text-secondary);
    margin: 0;
    font-size: 0.9rem;
}

.report-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.detail-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.evaluation-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.875rem;
    border-radius: 1rem;
    font-size: 0.85rem;
    font-weight: 500;
}

.evaluation-status.pendiente {
    background: #fef3c7;
    color: #92400e;
}

.evaluation-status.evaluado {
    background: #d1fae5;
    color: #065f46;
}

.evaluation-status.aprobado {
    background: #d1fae5;
    color: #065f46;
}

.evaluation-status.revision {
    background: #fef3c7;
    color: #92400e;
}

.evaluation-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
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

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 4rem;
    color: var(--border);
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .evaluation-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .report-details {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-star"></i>
            Evaluaciones de Reportes
        </h1>
        <p>Gestiona y evalúa los reportes bimestrales y finales de los estudiantes</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card warning">
            <div class="stat-label">Evaluaciones Pendientes</div>
            <div class="stat-value"><?= $totalPendientes ?></div>
            <div class="stat-description">reportes por evaluar</div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Reportes Bimestrales</div>
            <div class="stat-value"><?= $bimestralesPendientes ?></div>
            <div class="stat-description">pendientes de evaluación</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Reportes Finales</div>
            <div class="stat-value"><?= $finalesPendientes?></div>
            <div class="stat-description">pendientes de evaluación</div>
        </div>
        <div class="stat-card success">
            <div class="stat-label">Evaluaciones Completadas</div>
            <div class="stat-value"><?= $totalEvaluados ?></div>
            <div class="stat-description">reportes evaluados</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="tipo">Tipo de Reporte</label>
                    <select name="tipo" id="tipo" onchange="this.form.submit()">
                        <option value="todos" <?= $tipoFiltro == 'todos' ? 'selected' : '' ?>>Todos los reportes</option>
                        <option value="bimestral" <?= $tipoFiltro == 'bimestral' ? 'selected' : '' ?>>Reportes Bimestrales</option>
                        <option value="final" <?= $tipoFiltro == 'final' ? 'selected' : '' ?>>Reportes Finales</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="estado">Estado</label>
                    <select name="estado" id="estado" onchange="this.form.submit()">
                        <option value="pendiente" <?= $estadoFiltro == 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        <option value="evaluado" <?= $estadoFiltro == 'evaluado' ? 'selected' : '' ?>>Evaluados</option>
                        <option value="todos" <?= $estadoFiltro == 'todos' ? 'selected' : '' ?>>Todos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <a href="evaluaciones.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                        <i class="fas fa-redo"></i> Limpiar Filtros
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Evaluations List -->
    <div class="evaluations-section">
        <h2 class="section-title">
            <i class="fas fa-list"></i>
            Reportes
        </h2>

        <?php if (empty($reportesBimestrales) && empty($reportesFinales)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <h3>No hay reportes</h3>
                <p>No se encontraron reportes con los filtros seleccionados</p>
            </div>
        <?php else: ?>
            <div class="evaluations-grid">
                <!-- Reportes Bimestrales -->
                <?php foreach ($reportesBimestrales as $reporte): ?>
                <div class="evaluation-card">
                    <div class="evaluation-header">
                        <div class="student-info">
                            <h3><?= htmlspecialchars($reporte['nombre'] . ' ' . $reporte['apellido_paterno'] . ' ' . $reporte['apellido_materno']) ?></h3>
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
                        <div class="evaluation-badge bimestral">
                            <i class="fas fa-clipboard-list"></i>
                            Reporte Bimestral <?= $reporte['numero_reporte'] ?>
                        </div>
                    </div>

                    <div class="evaluation-body">
                        <div class="project-info">
                            <h4>Proyecto</h4>
                            <p><?= htmlspecialchars($reporte['nombre_proyecto']) ?></p>
                            <?php if ($reporte['laboratorio']): ?>
                            <p style="margin-top: 0.25rem;">
                                <i class="fas fa-flask"></i>
                                <?= htmlspecialchars($reporte['laboratorio']) ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <div class="report-details">
                            <div class="detail-item">
                                <span class="detail-label">Fecha Entrega</span>
                                <span class="detail-value"><?= formatDate($reporte['fecha_entrega']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Horas Reportadas</span>
                                <span class="detail-value"><?= $reporte['horas_reportadas'] ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Horas Acumuladas</span>
                                <span class="detail-value"><?= $reporte['horas_acumuladas'] ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Calificación</span>
                                <span class="detail-value">
                                    <?= $reporte['calificacion'] ? number_format($reporte['calificacion'], 1) : 'Sin calificar' ?>
                                </span>
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <span class="evaluation-status <?= $reporte['estado'] ?>">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                <?= ucfirst(str_replace('_', ' ', $reporte['estado'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="evaluation-actions">
                        <a href="estudiante-historial.php?id=<?= $reporte['estudiante_id'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-history"></i> Ver Historial
                        </a>
                        <?php if (in_array($reporte['estado'], ['pendiente_evaluacion', 'revision'])): ?>
                        <a href="evaluar-reporte-bimestral.php?id=<?= $reporte['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-star"></i> Evaluar Reporte
                        </a>
                        <?php else: ?>
                        <a href="evaluar-reporte-bimestral.php?id=<?= $reporte['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Ver Evaluación
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Reportes Finales -->
                <?php foreach ($reportesFinales as $reporte): ?>
                <div class="evaluation-card">
                    <div class="evaluation-header">
                        <div class="student-info">
                            <h3><?= htmlspecialchars($reporte['nombre'] . ' ' . $reporte['apellido_paterno'] . ' ' . $reporte['apellido_materno']) ?></h3>
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
                        <div class="evaluation-badge final">
                            <i class="fas fa-flag-checkered"></i>
                            Reporte Final
                        </div>
                    </div>

                    <div class="evaluation-body">
                        <div class="project-info">
                            <h4>Proyecto</h4>
                            <p><?= htmlspecialchars($reporte['nombre_proyecto']) ?></p>
                            <?php if ($reporte['laboratorio']): ?>
                            <p style="margin-top: 0.25rem;">
                                <i class="fas fa-flask"></i>
                                <?= htmlspecialchars($reporte['laboratorio']) ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <div class="report-details">
                            <div class="detail-item">
                                <span class="detail-label">Fecha Entrega</span>
                                <span class="detail-value"><?= formatDate($reporte['fecha_entrega']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Horas Totales</span>
                                <span class="detail-value"><?= $reporte['horas_totales_cumplidas'] ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Calificación Final</span>
                                <span class="detail-value">
                                    <?= $reporte['calificacion_final'] ? number_format($reporte['calificacion_final'], 1) : 'Sin calificar' ?>
                                </span>
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <span class="evaluation-status <?= $reporte['estado'] ?>">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                <?= ucfirst(str_replace('_', ' ', $reporte['estado'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="evaluation-actions">
                        <a href="estudiante-historial.php?id=<?= $reporte['estudiante_id'] ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-history"></i> Ver Historial
                        </a>
                        <?php if (in_array($reporte['estado'], ['pendiente_evaluacion', 'revision'])): ?>
                        <a href="evaluar-reporte-final.php?id=<?= $reporte['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-star"></i> Evaluar Reporte
                        </a>
                        <?php else: ?>
                        <a href="evaluar-reporte-final.php?id=<?= $reporte['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> Ver Evaluación
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

