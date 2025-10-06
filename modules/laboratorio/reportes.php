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

// Procesar filtros de reportes
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipoReporte = $_GET['tipo_reporte'] ?? 'estudiantes';

// Obtener datos para reportes
$reporteData = [];
$reporteGenerado = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $reporteGenerado = true;
    
    switch ($tipoReporte) {
        case 'estudiantes':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.nombre,
                    e.apellido_paterno,
                    e.numero_control,
                    e.carrera,
                    p.nombre_proyecto,
                    s.fecha_inicio_propuesta,
                    s.fecha_fin_propuesta,
                    e.horas_completadas,
                    s.estado
                FROM estudiantes e
                JOIN solicitudes_servicio s ON e.id = s.estudiante_id
                JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
                WHERE s.jefe_laboratorio_id = :jefe_id
                AND s.fecha_inicio_propuesta BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY e.nombre
            ", [
                'jefe_id' => $jefeLabId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            break;
            
        case 'horas':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.nombre,
                    e.apellido_paterno,
                    e.numero_control,
                    e.carrera,
                    r.numero_reporte,
                    r.periodo_inicio,
                    r.periodo_fin,
                    r.horas_reportadas,
                    r.horas_acumuladas,
                    r.calificacion,
                    r.estado
                FROM reportes_bimestrales r
                JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE s.jefe_laboratorio_id = :jefe_id
                AND r.fecha_entrega BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY r.fecha_entrega DESC
            ", [
                'jefe_id' => $jefeLabId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            break;
            
        case 'evaluaciones':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.nombre,
                    e.apellido_paterno,
                    e.numero_control,
                    e.carrera,
                    r.numero_reporte,
                    r.calificacion,
                    r.estado,
                    r.fecha_evaluacion,
                    r.observaciones_evaluador
                FROM reportes_bimestrales r
                JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE s.jefe_laboratorio_id = :jefe_id
                AND r.evaluado_por = :jefe_id_eval
                AND r.fecha_evaluacion BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY r.fecha_evaluacion DESC
            ", [
                'jefe_id' => $jefeLabId,
                'jefe_id_eval' => $jefeLabId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            break;
    }
}

// Calcular totales para resumen
$resumenData = [];
if (!empty($reporteData)) {
    switch ($tipoReporte) {
        case 'estudiantes':
            $resumenData = [
                'total_estudiantes' => count($reporteData),
                'estudiantes_activos' => count(array_filter($reporteData, fn($e) => $e['estado'] === 'en_proceso')),
                'total_horas' => array_sum(array_column($reporteData, 'horas_completadas')),
                'promedio_horas' => count($reporteData) > 0 ? array_sum(array_column($reporteData, 'horas_completadas')) / count($reporteData) : 0
            ];
            break;
        case 'horas':
            $reportesConCalif = array_filter($reporteData, fn($r) => $r['calificacion']);
            $resumenData = [
                'total_reportes' => count($reporteData),
                'horas_reportadas' => array_sum(array_column($reporteData, 'horas_reportadas')),
                'promedio_calificacion' => !empty($reportesConCalif) ? array_sum(array_column($reportesConCalif, 'calificacion')) / count($reportesConCalif) : 0,
                'pendientes_evaluacion' => count(array_filter($reporteData, fn($r) => $r['estado'] === 'pendiente_evaluacion'))
            ];
            break;
        case 'evaluaciones':
            $reportesConCalif = array_filter($reporteData, fn($r) => $r['calificacion']);
            $resumenData = [
                'total_evaluaciones' => count($reporteData),
                'aprobadas' => count(array_filter($reporteData, fn($r) => $r['estado'] === 'aprobado')),
                'promedio_calificacion' => !empty($reportesConCalif) ? array_sum(array_column($reportesConCalif, 'calificacion')) / count($reportesConCalif) : 0,
                'rechazadas' => count(array_filter($reporteData, fn($r) => $r['estado'] === 'rechazado'))
            ];
            break;
    }
}

$pageTitle = "Reportes del Laboratorio - " . APP_NAME;
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
                        <i class="fas fa-chart-line"></i>
                        Reportes del Laboratorio
                    </h1>
                    <p class="page-subtitle">Análisis y generación de reportes del laboratorio <?= htmlspecialchars($nombreLaboratorio) ?></p>
                </div>
                <div class="header-actions">
                    <a href="../../dashboard/jefe_laboratorio.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                    <?php if ($reporteGenerado && !empty($reporteData)): ?>
                    <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-download"></i>
                        Exportar Excel
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Report Type Selection -->
        <div class="report-types-section">
            <h2 class="section-title">
                <i class="fas fa-chart-pie"></i>
                Selecciona el Tipo de Reporte
            </h2>
            
            <div class="report-types-grid">
                <div class="report-type-card <?= $tipoReporte === 'estudiantes' ? 'active' : '' ?>" 
                     onclick="selectReportType('estudiantes')">
                    <div class="report-type-icon estudiantes">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="report-type-content">
                        <h3>Estudiantes del Laboratorio</h3>
                        <p>Análisis completo de los estudiantes asignados al laboratorio</p>
                        <div class="report-type-features">
                            <span><i class="fas fa-check"></i> Lista de estudiantes</span>
                            <span><i class="fas fa-check"></i> Horas completadas</span>
                            <span><i class="fas fa-check"></i> Estado de servicio</span>
                        </div>
                    </div>
                </div>

                <div class="report-type-card <?= $tipoReporte === 'horas' ? 'active' : '' ?>" 
                     onclick="selectReportType('horas')">
                    <div class="report-type-icon horas">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="report-type-content">
                        <h3>Registro de Horas</h3>
                        <p>Detalle de horas reportadas y calificaciones bimestrales</p>
                        <div class="report-type-features">
                            <span><i class="fas fa-check"></i> Reportes bimestrales</span>
                            <span><i class="fas fa-check"></i> Horas acumuladas</span>
                            <span><i class="fas fa-check"></i> Calificaciones</span>
                        </div>
                    </div>
                </div>

                <div class="report-type-card <?= $tipoReporte === 'evaluaciones' ? 'active' : '' ?>" 
                     onclick="selectReportType('evaluaciones')">
                    <div class="report-type-icon evaluaciones">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="report-type-content">
                        <h3>Evaluaciones Realizadas</h3>
                        <p>Historial de evaluaciones y retroalimentación a estudiantes</p>
                        <div class="report-type-features">
                            <span><i class="fas fa-check"></i> Evaluaciones completas</span>
                            <span><i class="fas fa-check"></i> Promedio general</span>
                            <span><i class="fas fa-check"></i> Observaciones</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h2 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Configurar Filtros del Reporte
                </h2>
            </div>
            
            <form method="GET" class="report-filter-form" id="reportForm">
                <input type="hidden" name="tipo_reporte" id="tipoReporteInput" value="<?= $tipoReporte ?>">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="fecha_inicio">
                            <i class="fas fa-calendar-alt"></i>
                            Fecha de Inicio
                        </label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= $fechaInicio ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">
                            <i class="fas fa-calendar-check"></i>
                            Fecha de Fin
                        </label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= $fechaFin ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-generate" id="generateBtn">
                            <i class="fas fa-chart-line"></i>
                            <span class="btn-text">Generar Reporte</span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Generando...
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <?php if ($reporteGenerado): ?>
            <?php if (!empty($reporteData)): ?>
                <!-- Summary Statistics -->
                <div class="summary-section">
                    <h2 class="section-title">
                        <i class="fas fa-analytics"></i>
                        Resumen Ejecutivo
                    </h2>
                    
                    <div class="summary-grid">
                        <?php
                        $summaryItems = [];
                        switch ($tipoReporte) {
                            case 'estudiantes':
                                $summaryItems = [
                                    ['title' => 'Total Estudiantes', 'value' => $resumenData['total_estudiantes'], 'icon' => 'user-graduate', 'color' => 'primary'],
                                    ['title' => 'Activos', 'value' => $resumenData['estudiantes_activos'], 'icon' => 'check-circle', 'color' => 'success'],
                                    ['title' => 'Total Horas', 'value' => number_format($resumenData['total_horas']), 'icon' => 'clock', 'color' => 'info'],
                                    ['title' => 'Promedio Horas', 'value' => number_format($resumenData['promedio_horas'], 1), 'icon' => 'chart-bar', 'color' => 'secondary']
                                ];
                                break;
                            case 'horas':
                                $summaryItems = [
                                    ['title' => 'Total Reportes', 'value' => $resumenData['total_reportes'], 'icon' => 'clipboard-list', 'color' => 'primary'],
                                    ['title' => 'Horas Reportadas', 'value' => number_format($resumenData['horas_reportadas']), 'icon' => 'clock', 'color' => 'success'],
                                    ['title' => 'Calificación Prom.', 'value' => number_format($resumenData['promedio_calificacion'], 1), 'icon' => 'star', 'color' => 'info'],
                                    ['title' => 'Pendientes', 'value' => $resumenData['pendientes_evaluacion'], 'icon' => 'hourglass-half', 'color' => 'warning']
                                ];
                                break;
                            case 'evaluaciones':
                                $summaryItems = [
                                    ['title' => 'Total Evaluaciones', 'value' => $resumenData['total_evaluaciones'], 'icon' => 'check-circle', 'color' => 'primary'],
                                    ['title' => 'Aprobadas', 'value' => $resumenData['aprobadas'], 'icon' => 'thumbs-up', 'color' => 'success'],
                                    ['title' => 'Calificación Prom.', 'value' => number_format($resumenData['promedio_calificacion'], 1), 'icon' => 'star', 'color' => 'info'],
                                    ['title' => 'Rechazadas', 'value' => $resumenData['rechazadas'], 'icon' => 'times-circle', 'color' => 'error']
                                ];
                                break;
                        }
                        
                        foreach ($summaryItems as $item): ?>
                        <div class="summary-card <?= $item['color'] ?>">
                            <div class="summary-icon">
                                <i class="fas fa-<?= $item['icon'] ?>"></i>
                            </div>
                            <div class="summary-content">
                                <h3 class="summary-title"><?= $item['title'] ?></h3>
                                <div class="summary-number"><?= $item['value'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Chart Visualization -->
                <div class="chart-section">
                    <h2 class="section-title">
                        <i class="fas fa-chart-area"></i>
                        Visualización Gráfica
                    </h2>
                    
                    <div class="chart-container">
                        <canvas id="reportChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h2 class="section-title">
                            <i class="fas fa-table"></i>
                            Datos Detallados
                        </h2>
                        <div class="table-actions">
                            <button class="btn btn-secondary btn-sm" onclick="toggleTable()">
                                <i class="fas fa-expand-alt"></i>
                                Expandir Tabla
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i>
                                Exportar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive" id="tableContainer">
                        <table class="data-table" id="report-table">
                            <thead>
                                <tr>
                                    <?php
                                    $headers = [];
                                    switch ($tipoReporte) {
                                        case 'estudiantes':
                                            $headers = ['Estudiante', 'No. Control', 'Carrera', 'Proyecto', 'Fecha Inicio', 'Fecha Fin', 'Horas', 'Estado'];
                                            break;
                                        case 'horas':
                                            $headers = ['Estudiante', 'No. Control', 'Carrera', 'Reporte', 'Periodo', 'Horas', 'Acumuladas', 'Calificación', 'Estado'];
                                            break;
                                        case 'evaluaciones':
                                            $headers = ['Estudiante', 'No. Control', 'Carrera', 'Reporte', 'Calificación', 'Estado', 'Fecha Eval.', 'Observaciones'];
                                            break;
                                    }
                                    
                                    foreach ($headers as $header) {
                                        echo "<th>$header</th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($reporteData as $fila) {
                                    echo "<tr>";
                                    $nombreCompleto = htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido_paterno']);
                                    
                                    switch ($tipoReporte) {
                                        case 'estudiantes':
                                            echo "<td class='font-weight-bold'>{$nombreCompleto}</td>";
                                            echo "<td>{$fila['numero_control']}</td>";
                                            echo "<td>" . htmlspecialchars($fila['carrera']) . "</td>";
                                            echo "<td>" . htmlspecialchars($fila['nombre_proyecto']) . "</td>";
                                            echo "<td>" . formatDate($fila['fecha_inicio_propuesta']) . "</td>";
                                            echo "<td>" . formatDate($fila['fecha_fin_propuesta']) . "</td>";
                                            echo "<td><span class='badge badge-success'>{$fila['horas_completadas']} hrs</span></td>";
                                            echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                            break;
                                            
                                        case 'horas':
                                            echo "<td class='font-weight-bold'>{$nombreCompleto}</td>";
                                            echo "<td>{$fila['numero_control']}</td>";
                                            echo "<td>" . htmlspecialchars($fila['carrera']) . "</td>";
                                            echo "<td><span class='badge badge-info'>Reporte {$fila['numero_reporte']}</span></td>";
                                            echo "<td>" . formatDate($fila['periodo_inicio']) . " - " . formatDate($fila['periodo_fin']) . "</td>";
                                            echo "<td><span class='badge badge-primary'>{$fila['horas_reportadas']} hrs</span></td>";
                                            echo "<td><span class='badge badge-success'>{$fila['horas_acumuladas']} hrs</span></td>";
                                            echo "<td>" . ($fila['calificacion'] ? "<span class='badge badge-warning'><strong>{$fila['calificacion']}</strong></span>" : '<span class="text-muted">N/A</span>') . "</td>";
                                            echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                            break;
                                            
                                        case 'evaluaciones':
                                            echo "<td class='font-weight-bold'>{$nombreCompleto}</td>";
                                            echo "<td>{$fila['numero_control']}</td>";
                                            echo "<td>" . htmlspecialchars($fila['carrera']) . "</td>";
                                            echo "<td><span class='badge badge-info'>Reporte {$fila['numero_reporte']}</span></td>";
                                            echo "<td>" . ($fila['calificacion'] ? "<span class='badge badge-warning'><strong>{$fila['calificacion']}</strong></span>" : '<span class="text-muted">N/A</span>') . "</td>";
                                            echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                            echo "<td>" . ($fila['fecha_evaluacion'] ? formatDateTime($fila['fecha_evaluacion']) : '<span class="text-muted">N/A</span>') . "</td>";
                                            echo "<td class='observaciones-cell'>" . ($fila['observaciones_evaluador'] ? htmlspecialchars(substr($fila['observaciones_evaluador'], 0, 50) . '...') : '<span class="text-muted">Sin observaciones</span>') . "</td>";
                                            break;
                                    }
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-data-section">
                    <div class="no-data-content">
                        <div class="no-data-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>No se encontraron datos</h3>
                        <p>No hay información disponible para el período seleccionado.</p>
                        <p>Intenta ajustar las fechas o verificar que existan registros en el sistema.</p>
                        <button class="btn btn-primary" onclick="adjustFilters()">
                            <i class="fas fa-filter"></i>
                            Ajustar Filtros
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3>¡Comienza a Generar Reportes!</h3>
                    <p>Selecciona el tipo de reporte y las fechas para comenzar el análisis.</p>
                    <div class="welcome-steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-text">Selecciona el tipo de reporte</div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-text">Configura las fechas</div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-text">Genera y analiza</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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

/* Section Titles */
.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
}

.section-title i {
    color: var(--primary);
}

/* Report Types Section */
.report-types-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.report-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.report-type-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.report-type-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.report-type-card.active {
    border-color: var(--primary);
    background: var(--bg-white);
    box-shadow: var(--shadow-lg);
}

.report-type-card.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary), var(--primary-light));
}

.report-type-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    margin-bottom: 1rem;
}

.report-type-icon.estudiantes {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.report-type-icon.horas {
    background: linear-gradient(135deg, var(--secondary), #42a5f5);
}

.report-type-icon.evaluaciones {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.report-type-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.report-type-content p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
    line-height: 1.5;
}

.report-type-features {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.report-type-features span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.report-type-features i {
    color: var(--success);
    font-size: 0.75rem;
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

.report-filter-form {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

.form-group input {
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.btn-generate {
    width: 100%;
    justify-content: center;
}

/* Summary Section */
.summary-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.summary-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.summary-card.primary { border-left-color: var(--primary); }
.summary-card.success { border-left-color: var(--success); }
.summary-card.warning { border-left-color: var(--warning); }
.summary-card.error { border-left-color: var(--error); }
.summary-card.info { border-left-color: var(--info); }
.summary-card.secondary { border-left-color: var(--secondary); }

.summary-icon {
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

.summary-card.primary .summary-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
.summary-card.success .summary-icon { background: linear-gradient(135deg, var(--success), #34d399); }
.summary-card.warning .summary-icon { background: linear-gradient(135deg, var(--warning), #fbbf24); }
.summary-card.error .summary-icon { background: linear-gradient(135deg, var(--error), #f87171); }
.summary-card.info .summary-icon { background: linear-gradient(135deg, var(--info), #60a5fa); }
.summary-card.secondary .summary-icon { background: linear-gradient(135deg, var(--secondary), #42a5f5); }

.summary-content {
    flex: 1;
}

.summary-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.summary-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

/* Chart Section */
.chart-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.chart-container {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 2rem;
    position: relative;
    min-height: 400px;
}

/* Table Section */
.table-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.table-actions {
    display: flex;
    gap: 0.75rem;
}

.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-white);
}

.data-table th {
    background: var(--bg-gray);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

.data-table tr:hover {
    background: var(--bg-light);
}

.font-weight-bold {
    font-weight: 600;
    color: var(--text-primary);
}

.observaciones-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.text-muted {
    color: var(--text-light);
    font-style: italic;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-primary { background: rgba(76, 175, 80, 0.1); color: var(--primary); }
.badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.badge-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }
.badge-info { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }
.badge-secondary { background: rgba(33, 150, 243, 0.1); color: var(--secondary); }

/* Welcome Section */
.welcome-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
}

.welcome-content {
    max-width: 600px;
    margin: 0 auto;
}

.welcome-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    margin: 0 auto 1.5rem;
}

.welcome-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.welcome-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.welcome-steps {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 2rem;
}

.step {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.step-text {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* No Data Section */
.no-data-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
}

.no-data-content {
    max-width: 500px;
    margin: 0 auto;
}

.no-data-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--text-light);
    margin: 0 auto 1.5rem;
}

.no-data-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.no-data-content p {
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
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

.summary-number {
    animation: countUp 0.8s ease-out;
}

.summary-grid > * {
    animation: slideIn 0.6s ease-out;
}

.summary-grid > *:nth-child(1) { animation-delay: 0.1s; }
.summary-grid > *:nth-child(2) { animation-delay: 0.2s; }
.summary-grid > *:nth-child(3) { animation-delay: 0.3s; }
.summary-grid > *:nth-child(4) { animation-delay: 0.4s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .report-types-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
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
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .table-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-card {
        flex-direction: column;
        text-align: center;
    }
    
    .summary-icon {
        margin-bottom: 1rem;
    }
    
    .welcome-steps {
        flex-direction: column;
        gap: 1rem;
    }
    
    .data-table {
        font-size: 0.875rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .table-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .report-types-section,
    .filters-section,
    .summary-section,
    .chart-section,
    .table-section,
    .welcome-section,
    .no-data-section {
        padding: 1.5rem 1rem;
    }
    
    .chart-container {
        padding: 1rem;
    }
    
    .welcome-icon,
    .no-data-icon {
        width: 70px;
        height: 70px;
        font-size: 1.75rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global variables
let reportChart = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize chart if data exists
    <?php if ($reporteGenerado && !empty($reporteData)): ?>
    initializeChart();
    <?php endif; ?>
    
    // Animate summary numbers
    const summaryNumbers = document.querySelectorAll('.summary-number');
    summaryNumbers.forEach((numberElement, index) => {
        const finalNumber = parseFloat(numberElement.textContent.replace(/,/g, ''));
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                if (finalNumber % 1 === 0) {
                    numberElement.textContent = Math.floor(Math.min(currentNumber, finalNumber)).toLocaleString();
                } else {
                    numberElement.textContent = Math.min(currentNumber, finalNumber).toLocaleString('es-ES', {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1
                    });
                }
                requestAnimationFrame(animateNumber);
            } else {
                if (finalNumber % 1 === 0) {
                    numberElement.textContent = finalNumber.toLocaleString();
                } else {
                    numberElement.textContent = finalNumber.toLocaleString('es-ES', {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1
                    });
                }
            }
        }
        
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Form submission loading state
    const form = document.getElementById('reportForm');
    const generateBtn = document.getElementById('generateBtn');
    
    if (form && generateBtn) {
        const btnText = generateBtn.querySelector('.btn-text');
        const btnLoader = generateBtn.querySelector('.btn-loader');
        
        form.addEventListener('submit', function(e) {
            generateBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
        });
    }
});

function selectReportType(tipo) {
    // Update hidden input
    document.getElementById('tipoReporteInput').value = tipo;
    
    // Update active state
    document.querySelectorAll('.report-type-card').forEach(card => {
        card.classList.remove('active');
    });
    
    event.target.closest('.report-type-card').classList.add('active');
}

function initializeChart() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    // Destroy existing chart
    if (reportChart) {
        reportChart.destroy();
    }
    
    const chartData = <?= json_encode($reporteData) ?>;
    const tipoReporte = '<?= $tipoReporte ?>';
    
    let labels = [];
    let datasets = [];
    
    switch(tipoReporte) {
        case 'estudiantes':
            labels = chartData.map(item => item.nombre + ' ' + item.apellido_paterno);
            datasets = [
                {
                    label: 'Horas Completadas',
                    data: chartData.map(item => item.horas_completadas),
                    backgroundColor: [
                        'rgba(76, 175, 80, 0.8)',
                        'rgba(33, 150, 243, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }
            ];
            break;
            
        case 'horas':
            labels = chartData.map(item => 'Reporte ' + item.numero_reporte + ' - ' + item.nombre.split(' ')[0]);
            datasets = [
                {
                    label: 'Horas Reportadas',
                    data: chartData.map(item => item.horas_reportadas),
                    borderColor: 'rgb(33, 150, 243)',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Horas Acumuladas',
                    data: chartData.map(item => item.horas_acumuladas),
                    borderColor: 'rgb(76, 175, 80)',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4
                }
            ];
            break;
            
        case 'evaluaciones':
            const calificaciones = chartData.filter(item => item.calificacion);
            labels = calificaciones.map(item => item.nombre.split(' ')[0] + ' - R' + item.numero_reporte);
            datasets = [
                {
                    label: 'Calificaciones',
                    data: calificaciones.map(item => item.calificacion),
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderColor: 'rgb(245, 158, 11)',
                    borderWidth: 2
                }
            ];
            break;
    }
    
    const chartType = tipoReporte === 'estudiantes' ? 'doughnut' : (tipoReporte === 'horas' ? 'line' : 'bar');
    
    reportChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: getChartTitle(tipoReporte),
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: chartType !== 'doughnut' ? {
                y: {
                    beginAtZero: true
                }
            } : {}
        }
    });
}

function getChartTitle(tipo) {
    switch(tipo) {
        case 'estudiantes': return 'Distribución de Horas por Estudiante';
        case 'horas': return 'Tendencia de Horas Reportadas';
        case 'evaluaciones': return 'Calificaciones por Evaluación';
        default: return 'Datos del Reporte';
    }
}

function toggleTable() {
    const container = document.getElementById('tableContainer');
    const btn = event.target;
    
    if (container.style.maxHeight) {
        container.style.maxHeight = '';
        container.style.overflow = 'auto';
        btn.innerHTML = '<i class="fas fa-compress-alt"></i> Contraer Tabla';
    } else {
        container.style.maxHeight = '400px';
        container.style.overflow = 'auto';
        btn.innerHTML = '<i class="fas fa-expand-alt"></i> Expandir Tabla';
    }
}

function exportToExcel() {
    const table = document.getElementById('report-table');
    if (!table) {
        alert('No hay datos para exportar');
        return;
    }
    
    let csv = [];
    
    // Headers
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length; i++) {
        headers.push('"' + table.rows[0].cells[i].textContent.trim() + '"');
    }
    csv.push(headers.join(','));
    
    // Data rows
    for (let i = 1; i < table.rows.length; i++) {
        const row = [];
        for (let j = 0; j < table.rows[i].cells.length; j++) {
            row.push('"' + table.rows[i].cells[j].textContent.trim() + '"');
        }
        csv.push(row.join(','));
    }
    
    // Create and download file
    const csvContent = "\uFEFF" + csv.join('\n'); // UTF-8 BOM for Excel
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `reporte_laboratorio_<?= $tipoReporte ?>_<?= date('Y-m-d') ?>.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function adjustFilters() {
    document.querySelector('.filters-section').scrollIntoView({ 
        behavior: 'smooth' 
    });
    
    // Focus on first filter input
    setTimeout(() => {
        document.getElementById('fecha_inicio').focus();
    }, 500);
}
</script>

<?php include '../../includes/footer.php'; ?>