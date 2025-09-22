<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Procesar filtros de reportes
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipoReporte = $_GET['tipo_reporte'] ?? 'estadisticas';

// Obtener datos para reportes
$reporteData = [];
$reporteGenerado = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $reporteGenerado = true;
    
    switch ($tipoReporte) {
        case 'estadisticas':
            $reporteData = $db->fetchAll("
                SELECT 
                    DATE(s.fecha_solicitud) as fecha,
                    COUNT(*) as total_solicitudes,
                    COUNT(CASE WHEN s.estado = 'aprobada' THEN 1 END) as aprobadas,
                    COUNT(CASE WHEN s.estado = 'rechazada' THEN 1 END) as rechazadas,
                    COUNT(CASE WHEN s.estado = 'pendiente' THEN 1 END) as pendientes
                FROM solicitudes_servicio s
                WHERE s.jefe_departamento_id = :jefe_id
                AND s.fecha_solicitud BETWEEN :fecha_inicio AND :fecha_fin
                GROUP BY DATE(s.fecha_solicitud)
                ORDER BY fecha
            ", [
                'jefe_id' => $jefeId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin . ' 23:59:59'
            ]);
            break;
            
        case 'horas':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.carrera,
                    COUNT(DISTINCT s.estudiante_id) as total_estudiantes,
                    COALESCE(SUM(e.horas_completadas), 0) as horas_totales,
                    COALESCE(AVG(e.horas_completadas), 0) as horas_promedio
                FROM solicitudes_servicio s
                JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE s.jefe_departamento_id = :jefe_id
                AND s.estado IN ('en_proceso', 'concluida')
                GROUP BY e.carrera
                ORDER BY horas_totales DESC
            ", ['jefe_id' => $jefeId]);
            break;
            
        case 'laboratorios':
            $reporteData = $db->fetchAll("
                SELECT 
                    jl.laboratorio,
                    jl.nombre as jefe_laboratorio,
                    COUNT(DISTINCT p.id) as total_proyectos,
                    COUNT(DISTINCT s.estudiante_id) as total_estudiantes,
                    COALESCE(SUM(e.horas_completadas), 0) as horas_totales
                FROM jefes_laboratorio jl
                LEFT JOIN proyectos_laboratorio p ON jl.id = p.jefe_laboratorio_id
                LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
                LEFT JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE jl.jefe_departamento_id = :jefe_id
                GROUP BY jl.id
                ORDER BY horas_totales DESC
            ", ['jefe_id' => $jefeId]);
            break;
    }
}

// Calcular totales para resumen
$resumenData = [];
if (!empty($reporteData)) {
    switch ($tipoReporte) {
        case 'estadisticas':
            $resumenData = [
                'total_solicitudes' => array_sum(array_column($reporteData, 'total_solicitudes')),
                'total_aprobadas' => array_sum(array_column($reporteData, 'aprobadas')),
                'total_rechazadas' => array_sum(array_column($reporteData, 'rechazadas')),
                'total_pendientes' => array_sum(array_column($reporteData, 'pendientes'))
            ];
            break;
        case 'horas':
            $resumenData = [
                'total_estudiantes' => array_sum(array_column($reporteData, 'total_estudiantes')),
                'total_horas' => array_sum(array_column($reporteData, 'horas_totales')),
                'promedio_general' => count($reporteData) > 0 ? array_sum(array_column($reporteData, 'horas_promedio')) / count($reporteData) : 0
            ];
            break;
        case 'laboratorios':
            $resumenData = [
                'total_laboratorios' => count($reporteData),
                'total_proyectos' => array_sum(array_column($reporteData, 'total_proyectos')),
                'total_estudiantes' => array_sum(array_column($reporteData, 'total_estudiantes')),
                'total_horas' => array_sum(array_column($reporteData, 'horas_totales'))
            ];
            break;
    }
}

$pageTitle = "Reportes y Estadísticas - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">
                    <i class="fas fa-chart-line"></i>
                    Reportes y Estadísticas
                </h1>
                <p class="page-subtitle">Análisis y generación de reportes del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
            </div>
            <div class="header-actions">
                <a href="../../dashboard/jefe_departamento.php" class="btn btn-secondary">
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
            <div class="report-type-card <?= $tipoReporte === 'estadisticas' ? 'active' : '' ?>" 
                 onclick="selectReportType('estadisticas')">
                <div class="report-type-icon estadisticas">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="report-type-content">
                    <h3>Estadísticas de Solicitudes</h3>
                    <p>Análisis de solicitudes por fecha con estados de aprobación</p>
                    <div class="report-type-features">
                        <span><i class="fas fa-check"></i> Solicitudes por día</span>
                        <span><i class="fas fa-check"></i> Estados de aprobación</span>
                        <span><i class="fas fa-check"></i> Tendencias temporales</span>
                    </div>
                </div>
            </div>

            <div class="report-type-card <?= $tipoReporte === 'horas' ? 'active' : '' ?>" 
                 onclick="selectReportType('horas')">
                <div class="report-type-icon horas">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="report-type-content">
                    <h3>Horas por Carrera</h3>
                    <p>Análisis de horas de servicio social agrupadas por carrera</p>
                    <div class="report-type-features">
                        <span><i class="fas fa-check"></i> Horas por carrera</span>
                        <span><i class="fas fa-check"></i> Estudiantes activos</span>
                        <span><i class="fas fa-check"></i> Promedios de horas</span>
                    </div>
                </div>
            </div>

            <div class="report-type-card <?= $tipoReporte === 'laboratorios' ? 'active' : '' ?>" 
                 onclick="selectReportType('laboratorios')">
                <div class="report-type-icon laboratorios">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="report-type-content">
                    <h3>Desempeño por Laboratorio</h3>
                    <p>Evaluación del rendimiento de cada laboratorio</p>
                    <div class="report-type-features">
                        <span><i class="fas fa-check"></i> Proyectos activos</span>
                        <span><i class="fas fa-check"></i> Estudiantes asignados</span>
                        <span><i class="fas fa-check"></i> Horas acumuladas</span>
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
                        case 'estadisticas':
                            $summaryItems = [
                                ['title' => 'Total Solicitudes', 'value' => $resumenData['total_solicitudes'], 'icon' => 'clipboard-list', 'color' => 'primary'],
                                ['title' => 'Aprobadas', 'value' => $resumenData['total_aprobadas'], 'icon' => 'check-circle', 'color' => 'success'],
                                ['title' => 'Rechazadas', 'value' => $resumenData['total_rechazadas'], 'icon' => 'times-circle', 'color' => 'error'],
                                ['title' => 'Pendientes', 'value' => $resumenData['total_pendientes'], 'icon' => 'clock', 'color' => 'warning']
                            ];
                            break;
                        case 'horas':
                            $summaryItems = [
                                ['title' => 'Total Estudiantes', 'value' => $resumenData['total_estudiantes'], 'icon' => 'user-graduate', 'color' => 'primary'],
                                ['title' => 'Total Horas', 'value' => number_format($resumenData['total_horas']), 'icon' => 'clock', 'color' => 'success'],
                                ['title' => 'Promedio General', 'value' => number_format($resumenData['promedio_general'], 1), 'icon' => 'chart-bar', 'color' => 'info'],
                                ['title' => 'Carreras Activas', 'value' => count($reporteData), 'icon' => 'graduation-cap', 'color' => 'secondary']
                            ];
                            break;
                        case 'laboratorios':
                            $summaryItems = [
                                ['title' => 'Total Laboratorios', 'value' => $resumenData['total_laboratorios'], 'icon' => 'flask', 'color' => 'primary'],
                                ['title' => 'Total Proyectos', 'value' => $resumenData['total_proyectos'], 'icon' => 'project-diagram', 'color' => 'secondary'],
                                ['title' => 'Total Estudiantes', 'value' => $resumenData['total_estudiantes'], 'icon' => 'users', 'color' => 'success'],
                                ['title' => 'Total Horas', 'value' => number_format($resumenData['total_horas']), 'icon' => 'clock', 'color' => 'info']
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
                                    case 'estadisticas':
                                        $headers = ['Fecha', 'Total Solicitudes', 'Aprobadas', 'Rechazadas', 'Pendientes'];
                                        break;
                                    case 'horas':
                                        $headers = ['Carrera', 'Total Estudiantes', 'Horas Totales', 'Horas Promedio'];
                                        break;
                                    case 'laboratorios':
                                        $headers = ['Laboratorio', 'Jefe de Laboratorio', 'Total Proyectos', 'Total Estudiantes', 'Horas Totales'];
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
                                switch ($tipoReporte) {
                                    case 'estadisticas':
                                        echo "<td>" . formatDate($fila['fecha']) . "</td>";
                                        echo "<td><span class='badge badge-primary'>{$fila['total_solicitudes']}</span></td>";
                                        echo "<td><span class='badge badge-success'>{$fila['aprobadas']}</span></td>";
                                        echo "<td><span class='badge badge-error'>{$fila['rechazadas']}</span></td>";
                                        echo "<td><span class='badge badge-warning'>{$fila['pendientes']}</span></td>";
                                        break;
                                        
                                    case 'horas':
                                        echo "<td class='font-weight-bold'>" . htmlspecialchars($fila['carrera']) . "</td>";
                                        echo "<td><span class='badge badge-primary'>{$fila['total_estudiantes']}</span></td>";
                                        echo "<td><span class='badge badge-success'>" . number_format($fila['horas_totales']) . "</span></td>";
                                        echo "<td><span class='badge badge-info'>" . number_format($fila['horas_promedio'], 1) . "</span></td>";
                                        break;
                                        
                                    case 'laboratorios':
                                        echo "<td class='font-weight-bold'>" . htmlspecialchars($fila['laboratorio']) . "</td>";
                                        echo "<td>" . htmlspecialchars($fila['jefe_laboratorio']) . "</td>";
                                        echo "<td><span class='badge badge-secondary'>{$fila['total_proyectos']}</span></td>";
                                        echo "<td><span class='badge badge-primary'>{$fila['total_estudiantes']}</span></td>";
                                        echo "<td><span class='badge badge-success'>" . number_format($fila['horas_totales']) . "</span></td>";
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

<style>
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

.report-type-icon.estadisticas {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.report-type-icon.horas {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.report-type-icon.laboratorios {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
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
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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
.summary-card.secondary .summary-icon { background: linear-gradient(135deg, var(--secondary), #a78bfa); }

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

.badge-primary { background: rgba(99, 102, 241, 0.1); color: var(--primary); }
.badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.badge-error { background: rgba(239, 68, 68, 0.1); color: var(--error); }
.badge-info { background: rgba(59, 130, 246, 0.1); color: var(--info); }
.badge-secondary { background: rgba(139, 92, 246, 0.1); color: var(--secondary); }

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
    
    // Add hover effects
    const cards = document.querySelectorAll('.report-type-card, .summary-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.style.transform) {
                this.style.transform = 'translateY(-5px)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
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
        case 'estadisticas':
            labels = chartData.map(item => new Date(item.fecha).toLocaleDateString('es-ES'));
            datasets = [
                {
                    label: 'Total Solicitudes',
                    data: chartData.map(item => item.total_solicitudes),
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Aprobadas',
                    data: chartData.map(item => item.aprobadas),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Rechazadas',
                    data: chartData.map(item => item.rechazadas),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }
            ];
            break;
            
        case 'horas':
            labels = chartData.map(item => item.carrera);
            datasets = [
                {
                    label: 'Horas Totales',
                    data: chartData.map(item => item.horas_totales),
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }
            ];
            break;
            
        case 'laboratorios':
            labels = chartData.map(item => item.laboratorio);
            datasets = [
                {
                    label: 'Estudiantes',
                    data: chartData.map(item => item.total_estudiantes),
                    backgroundColor: 'rgba(99, 102, 241, 0.8)'
                },
                {
                    label: 'Proyectos',
                    data: chartData.map(item => item.total_proyectos),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)'
                }
            ];
            break;
    }
    
    const chartType = tipoReporte === 'horas' ? 'doughnut' : (tipoReporte === 'estadisticas' ? 'line' : 'bar');
    
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
                    text: getChartTitle(tipoReporte)
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
        case 'estadisticas': return 'Tendencia de Solicitudes por Fecha';
        case 'horas': return 'Distribución de Horas por Carrera';
        case 'laboratorios': return 'Comparativo por Laboratorio';
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
        link.setAttribute('download', `reporte_<?= $tipoReporte ?>_<?= date('Y-m-d') ?>.csv`);
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