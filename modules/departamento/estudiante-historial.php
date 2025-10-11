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

// Obtener ID del estudiante
$estudianteId = $_GET['id'] ?? null;
if (!$estudianteId) {
    flashMessage('ID de estudiante no especificado', 'error');
    redirectTo('../departamento/estudiantes.php');
}

// Verificar que el estudiante pertenece a este departamento
$estudiante = $db->fetch("
    SELECT e.*, u.email
    FROM estudiantes e
    JOIN usuarios u ON e.usuario_id = u.id
    JOIN solicitudes_servicio s ON e.id = s.estudiante_id
    WHERE e.id = :estudiante_id AND s.jefe_departamento_id = :jefe_id
    LIMIT 1
", ['estudiante_id' => $estudianteId, 'jefe_id' => $jefeId]);

if (!$estudiante) {
    flashMessage('Estudiante no encontrado o no pertenece a tu departamento', 'error');
    redirectTo('../departamento/estudiantes.php');
}

// Obtener información de la solicitud activa
$solicitud = $db->fetch("
    SELECT s.*, p.nombre_proyecto, jl.nombre as jefe_lab_nombre, jl.laboratorio
    FROM solicitudes_servicio s
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
    WHERE s.estudiante_id = :estudiante_id AND s.jefe_departamento_id = :jefe_id
    ORDER BY s.created_at DESC
    LIMIT 1
", ['estudiante_id' => $estudianteId, 'jefe_id' => $jefeId]);

// Obtener reportes bimestrales
$reportesBimestrales = $db->fetchAll("
    SELECT rb.*, u.email as evaluador_email
    FROM reportes_bimestrales rb
    LEFT JOIN usuarios u ON rb.evaluado_por = u.id
    WHERE rb.estudiante_id = :estudiante_id
    ORDER BY rb.numero_reporte ASC
", ['estudiante_id' => $estudianteId]);

// Obtener reporte final
$reporteFinal = $db->fetch("
    SELECT rf.*, u.email as evaluador_email
    FROM reportes_finales rf
    LEFT JOIN usuarios u ON rf.evaluado_por = u.id
    WHERE rf.estudiante_id = :estudiante_id
", ['estudiante_id' => $estudianteId]);

// Obtener oficio de presentación
$oficio = $db->fetch("
    SELECT o.*
    FROM oficios_presentacion o
    WHERE o.solicitud_id = :solicitud_id
", ['solicitud_id' => $solicitud['id'] ?? 0]);

// Obtener carta de terminación
$carta = $db->fetch("
    SELECT c.*
    FROM cartas_terminacion c
    WHERE c.estudiante_id = :estudiante_id
", ['estudiante_id' => $estudianteId]);

// Obtener constancia
$constancia = $db->fetch("
    SELECT c.*
    FROM constancias c
    WHERE c.estudiante_id = :estudiante_id
", ['estudiante_id' => $estudianteId]);

// Construir timeline de eventos
$timeline = [];

// Evento: Solicitud
if ($solicitud) {
    $timeline[] = [
        'fecha' => $solicitud['fecha_solicitud'],
        'tipo' => 'solicitud',
        'titulo' => 'Solicitud de Servicio Social',
        'descripcion' => 'Solicitud enviada para el proyecto: ' . $solicitud['nombre_proyecto'],
        'estado' => $solicitud['estado'],
        'icono' => 'fa-file-alt',
        'color' => 'primary'
    ];
    
    if ($solicitud['fecha_aprobacion']) {
        $timeline[] = [
            'fecha' => date('Y-m-d', strtotime($solicitud['fecha_aprobacion'])),
            'tipo' => 'aprobacion',
            'titulo' => 'Solicitud Aprobada',
            'descripcion' => 'La solicitud fue aprobada por el jefe de departamento',
            'estado' => 'aprobada',
            'icono' => 'fa-check-circle',
            'color' => 'success'
        ];
    }
}

// Evento: Oficio de presentación
if ($oficio) {
    $timeline[] = [
        'fecha' => $oficio['fecha_emision'],
        'tipo' => 'oficio',
        'titulo' => 'Oficio de Presentación',
        'descripcion' => 'Oficio No. ' . $oficio['numero_oficio'] . ' generado',
        'estado' => $oficio['estado'],
        'icono' => 'fa-file-contract',
        'color' => 'info'
    ];
}

// Eventos: Reportes bimestrales
foreach ($reportesBimestrales as $reporte) {
    $timeline[] = [
        'fecha' => $reporte['fecha_entrega'],
        'tipo' => 'reporte_bimestral',
        'titulo' => 'Reporte Bimestral ' . $reporte['numero_reporte'],
        'descripcion' => 'Horas reportadas: ' . $reporte['horas_reportadas'] . ' | Calificación: ' . ($reporte['calificacion'] ?? 'Pendiente'),
        'estado' => $reporte['estado'],
        'icono' => 'fa-clipboard-list',
        'color' => $reporte['estado'] == 'aprobado' ? 'success' : 'warning',
        'id' => $reporte['id']
    ];
}

// Evento: Reporte final
if ($reporteFinal) {
    $timeline[] = [
        'fecha' => $reporteFinal['fecha_entrega'],
        'tipo' => 'reporte_final',
        'titulo' => 'Reporte Final',
        'descripcion' => 'Horas totales: ' . $reporteFinal['horas_totales_cumplidas'] . ' | Calificación: ' . ($reporteFinal['calificacion_final'] ?? 'Pendiente'),
        'estado' => $reporteFinal['estado'],
        'icono' => 'fa-flag-checkered',
        'color' => $reporteFinal['estado'] == 'aprobado' ? 'success' : 'warning',
        'id' => $reporteFinal['id']
    ];
}

// Evento: Carta de terminación
if ($carta) {
    $timeline[] = [
        'fecha' => $carta['fecha_terminacion'],
        'tipo' => 'carta',
        'titulo' => 'Carta de Terminación',
        'descripcion' => 'Carta No. ' . $carta['numero_carta'] . ' | Desempeño: ' . $carta['nivel_desempeno'],
        'estado' => 'generada',
        'icono' => 'fa-certificate',
        'color' => 'success'
    ];
}

// Evento: Constancia
if ($constancia) {
    $timeline[] = [
        'fecha' => $constancia['fecha_emision'],
        'tipo' => 'constancia',
        'titulo' => 'Constancia Final',
        'descripcion' => 'Constancia No. ' . $constancia['numero_constancia'] . ' | Calificación: ' . $constancia['calificacion_final'],
        'estado' => 'emitida',
        'icono' => 'fa-award',
        'color' => 'success'
    ];
}

// Ordenar timeline por fecha descendente
usort($timeline, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// Calcular estadísticas
$totalReportes = count($reportesBimestrales) + ($reporteFinal ? 1 : 0);
$reportesEvaluados = count(array_filter($reportesBimestrales, function($r) { return $r['estado'] == 'aprobado'; }));
if ($reporteFinal && $reporteFinal['estado'] == 'aprobado') $reportesEvaluados++;

$promedioCalificaciones = 0;
$calificaciones = [];
foreach ($reportesBimestrales as $r) {
    if ($r['calificacion']) $calificaciones[] = $r['calificacion'];
}
if ($reporteFinal && $reporteFinal['calificacion_final']) {
    $calificaciones[] = $reporteFinal['calificacion_final'];
}
if (count($calificaciones) > 0) {
    $promedioCalificaciones = array_sum($calificaciones) / count($calificaciones);
}

$pageTitle = "Historial de " . $estudiante['nombre'] . " - " . APP_NAME;
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

/* Header Section */
.page-header {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.student-header-info {
    flex: 1;
}

.student-header-info h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.student-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
}

.student-meta {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.meta-item i {
    color: var(--primary);
}

.header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Stats Cards */
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

.stat-card.success {
    border-left-color: var(--success);
}

.stat-card.warning {
    border-left-color: var(--warning);
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
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-description {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Main Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

/* Timeline */
.timeline-section {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -2.5rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.timeline-marker.primary {
    background: var(--primary);
}

.timeline-marker.success {
    background: var(--success);
}

.timeline-marker.warning {
    background: var(--warning);
}

.timeline-marker.info {
    background: var(--info);
}

.timeline-content {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.25rem;
    border: 1px solid var(--border);
}

.timeline-content:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.timeline-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.timeline-date {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.timeline-description {
    color: var(--text-secondary);
    margin: 0.5rem 0;
}

.timeline-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.timeline-badge.pendiente {
    background: #fef3c7;
    color: #92400e;
}

.timeline-badge.aprobado, .timeline-badge.aprobada {
    background: #d1fae5;
    color: #065f46;
}

.timeline-badge.evaluado {
    background: #dbeafe;
    color: #1e40af;
}

.timeline-badge.rechazado, .timeline-badge.rechazada {
    background: #fee2e2;
    color: #991b1b;
}

.timeline-actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
}

/* Documents Section */
.documents-section {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.documents-grid {
    display: grid;
    gap: 1rem;
}

.document-card {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.document-card:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow);
}

.document-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.document-info {
    flex: 1;
}

.document-title {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.document-meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
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

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.btn-outline:hover {
    background: var(--bg-light);
}

/* Project Info */
.project-info-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1rem;
}

.project-info-card h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
    color: var(--text-secondary);
}

.info-value {
    color: var(--text-primary);
    text-align: right;
}

@media (max-width: 968px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<div class="dashboard-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-top">
            <div class="student-header-info">
                <h1>
                    <div class="student-avatar-large">
                        <?= strtoupper(substr($estudiante['nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <?= htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido_paterno'] . ' ' . $estudiante['apellido_materno']) ?>
                    </div>
                </h1>
                <div class="student-meta">
                    <span class="meta-item">
                        <i class="fas fa-id-card"></i>
                        <?= htmlspecialchars($estudiante['numero_control']) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-graduation-cap"></i>
                        <?= htmlspecialchars($estudiante['carrera']) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($estudiante['email']) ?>
                    </span>
                    <?php if ($estudiante['telefono']): ?>
                    <span class="meta-item">
                        <i class="fas fa-phone"></i>
                        <?= htmlspecialchars($estudiante['telefono']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="estudiantes.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-left"></i> Regresar
                </a>
                <a href="historial-exportar.php?id=<?= $estudianteId ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-label">Horas Completadas</div>
            <div class="stat-value"><?= $estudiante['horas_completadas'] ?></div>
            <div class="stat-description">de 500 horas requeridas</div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Reportes Evaluados</div>
            <div class="stat-value"><?= $reportesEvaluados ?>/<?= $totalReportes ?></div>
            <div class="stat-description">reportes completados</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Promedio de Calificaciones</div>
            <div class="stat-value"><?= number_format($promedioCalificaciones, 1) ?></div>
            <div class="stat-description">calificación promedio</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Estado Actual</div>
            <div class="stat-value" style="font-size: 1.2rem;">
                <?php
                $estadoMap = [
                    'sin_solicitud' => 'Sin Solicitud',
                    'solicitud_pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'en_proceso' => 'En Proceso',
                    'concluido' => 'Concluido',
                    'cancelado' => 'Cancelado'
                ];
                echo $estadoMap[$estudiante['estado_servicio']] ?? $estudiante['estado_servicio'];
                ?>
            </div>
            <div class="stat-description">estado del servicio</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Timeline -->
        <div class="timeline-section">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Línea de Tiempo
            </h2>
            
            <?php if (empty($timeline)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                    No hay eventos registrados en el historial
                </p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($timeline as $evento): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $evento['color'] ?>">
                            <i class="fas <?= $evento['icono'] ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h4 class="timeline-title"><?= htmlspecialchars($evento['titulo']) ?></h4>
                                <span class="timeline-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= formatDate($evento['fecha']) ?>
                                </span>
                            </div>
                            <p class="timeline-description"><?= htmlspecialchars($evento['descripcion']) ?></p>
                            <span class="timeline-badge <?= $evento['estado'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $evento['estado'])) ?>
                            </span>
                            
                            <?php if ($evento['tipo'] == 'reporte_bimestral' && in_array($evento['estado'], ['pendiente_evaluacion', 'revision'])): ?>
                            <div class="timeline-actions">
                                <a href="evaluar-reporte-bimestral.php?id=<?= $evento['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-star"></i> Evaluar
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($evento['tipo'] == 'reporte_final' && in_array($evento['estado'], ['pendiente_evaluacion', 'revision'])): ?>
                            <div class="timeline-actions">
                                <a href="evaluar-reporte-final.php?id=<?= $evento['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-star"></i> Evaluar
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Project Information -->
            <?php if ($solicitud): ?>
            <div class="project-info-card">
                <h3><i class="fas fa-project-diagram"></i> Información del Proyecto</h3>
                <div class="info-row">
                    <span class="info-label">Proyecto:</span>
                    <span class="info-value"><?= htmlspecialchars($solicitud['nombre_proyecto']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Laboratorio:</span>
                    <span class="info-value"><?= htmlspecialchars($solicitud['laboratorio'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Supervisor:</span>
                    <span class="info-value"><?= htmlspecialchars($solicitud['jefe_lab_nombre'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha Inicio:</span>
                    <span class="info-value"><?= formatDate($solicitud['fecha_inicio_propuesta']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha Fin:</span>
                    <span class="info-value"><?= formatDate($solicitud['fecha_fin_propuesta']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <div class="documents-section">
                <h3 class="section-title">
                    <i class="fas fa-folder-open"></i>
                    Documentos
                </h3>
                <div class="documents-grid">
                    <?php if ($oficio): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">Oficio de Presentación</h4>
                            <p class="document-meta">No. <?= htmlspecialchars($oficio['numero_oficio']) ?></p>
                        </div>
                        <?php if ($oficio['archivo_path']): ?>
                        <a href="<?= htmlspecialchars($oficio['archivo_path']) ?>" class="btn btn-sm btn-outline" target="_blank">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($reportesBimestrales as $reporte): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">Reporte Bimestral <?= $reporte['numero_reporte'] ?></h4>
                            <p class="document-meta">
                                <?= formatDate($reporte['fecha_entrega']) ?> | 
                                <?= $reporte['calificacion'] ? number_format($reporte['calificacion'], 1) : 'Sin calificar' ?>
                            </p>
                        </div>
                        <?php if ($reporte['archivo_path']): ?>
                        <a href="<?= htmlspecialchars($reporte['archivo_path']) ?>" class="btn btn-sm btn-outline" target="_blank">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($reporteFinal): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">Reporte Final</h4>
                            <p class="document-meta">
                                <?= formatDate($reporteFinal['fecha_entrega']) ?> | 
                                <?= $reporteFinal['calificacion_final'] ? number_format($reporteFinal['calificacion_final'], 1) : 'Sin calificar' ?>
                            </p>
                        </div>
                        <?php if ($reporteFinal['archivo_path']): ?>
                        <a href="<?= htmlspecialchars($reporteFinal['archivo_path']) ?>" class="btn btn-sm btn-outline" target="_blank">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($carta): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">Carta de Terminación</h4>
                            <p class="document-meta">No. <?= htmlspecialchars($carta['numero_carta']) ?></p>
                        </div>
                        <?php if ($carta['archivo_path']): ?>
                        <a href="<?= htmlspecialchars($carta['archivo_path']) ?>" class="btn btn-sm btn-outline" target="_blank">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($constancia): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">Constancia Final</h4>
                            <p class="document-meta">No. <?= htmlspecialchars($constancia['numero_constancia']) ?></p>
                        </div>
                        <?php if ($constancia['archivo_path']): ?>
                        <a href="<?= htmlspecialchars($constancia['archivo_path']) ?>" class="btn btn-sm btn-outline" target="_blank">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$oficio && empty($reportesBimestrales) && !$reporteFinal && !$carta && !$constancia): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 1rem;">
                        No hay documentos disponibles
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

