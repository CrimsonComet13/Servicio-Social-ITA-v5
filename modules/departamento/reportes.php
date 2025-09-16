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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
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
                    SUM(s.horas_completadas) as horas_totales,
                    AVG(s.horas_completadas) as horas_promedio
                FROM solicitudes_servicio s
                JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE s.jefe_departamento_id = :jefe_id
                AND s.estado = 'concluida'
                GROUP BY e.carrera
                ORDER BY horas_totales DESC
            ", ['jefe_id' => $jefeId]);
            break;
            
        case 'laboratorios':
            $reporteData = $db->fetchAll("
                SELECT 
                    jl.laboratorio,
                    jl.nombre as jefe_laboratorio,
                    COUNT(DISTINCT s.id) as total_proyectos,
                    COUNT(DISTINCT s.estudiante_id) as total_estudiantes,
                    SUM(s.horas_completadas) as horas_totales
                FROM jefes_laboratorio jl
                LEFT JOIN proyectos_laboratorio p ON jl.id = p.jefe_laboratorio_id
                LEFT JOIN solicitudes_servicio s ON p.id = s.proyecto_id
                WHERE jl.jefe_departamento_id = :jefe_id
                GROUP BY jl.id
                ORDER BY horas_totales DESC
            ", ['jefe_id' => $jefeId]);
            break;
    }
}

$pageTitle = "Reportes - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Reportes y Estadísticas</h1>
        <p>Generación de reportes del departamento <?= htmlspecialchars($usuario['departamento']) ?></p>
    </div>

    <!-- Filtros de reportes -->
    <div class="filters">
        <form method="GET" class="report-filter-form">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="tipo_reporte">Tipo de Reporte</label>
                    <select id="tipo_reporte" name="tipo_reporte">
                        <option value="estadisticas" <?= $tipoReporte === 'estadisticas' ? 'selected' : '' ?>>Estadísticas de Solicitudes</option>
                        <option value="horas" <?= $tipoReporte === 'horas' ? 'selected' : '' ?>>Horas por Carrera</option>
                        <option value="laboratorios" <?= $tipoReporte === 'laboratorios' ? 'selected' : '' ?>>Desempeño por Laboratorio</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= $fechaInicio ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_fin">Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" value="<?= $fechaFin ?>">
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    <button type="button" class="btn btn-secondary" onclick="exportToExcel()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Resultados del reporte -->
    <div class="report-results">
        <?php if (!empty($reporteData)): ?>
            <div class="report-header">
                <h2>
                    <?php
                    $titulos = [
                        'estadisticas' => 'Estadísticas de Solicitudes',
                        'horas' => 'Horas de Servicio Social por Carrera',
                        'laboratorios' => 'Desempeño por Laboratorio'
                    ];
                    echo $titulos[$tipoReporte] ?? 'Reporte';
                    ?>
                </h2>
                <p>Período: <?= formatDate($fechaInicio) ?> - <?= formatDate($fechaFin) ?></p>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="report-table">
                    <thead>
                        <tr>
                            <?php
                            // Encabezados dinámicos según el tipo de reporte
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
                                    echo "<td>{$fila['total_solicitudes']}</td>";
                                    echo "<td>{$fila['aprobadas']}</td>";
                                    echo "<td>{$fila['rechazadas']}</td>";
                                    echo "<td>{$fila['pendientes']}</td>";
                                    break;
                                    
                                case 'horas':
                                    echo "<td>" . htmlspecialchars($fila['carrera']) . "</td>";
                                    echo "<td>{$fila['total_estudiantes']}</td>";
                                    echo "<td>{$fila['horas_totales']}</td>";
                                    echo "<td>" . number_format($fila['horas_promedio'], 1) . "</td>";
                                    break;
                                    
                                case 'laboratorios':
                                    echo "<td>" . htmlspecialchars($fila['laboratorio']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fila['jefe_laboratorio']) . "</td>";
                                    echo "<td>{$fila['total_proyectos']}</td>";
                                    echo "<td>{$fila['total_estudiantes']}</td>";
                                    echo "<td>{$fila['horas_totales']}</td>";
                                    break;
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Resumen estadístico -->
            <div class="report-summary">
                <h3>Resumen Estadístico</h3>
                <div class="summary-grid">
                    <?php
                    $total = count($reporteData);
                    $sumas = [];
                    
                    if ($tipoReporte === 'estadisticas') {
                        $sumas['solicitudes'] = array_sum(array_column($reporteData, 'total_solicitudes'));
                        $sumas['aprobadas'] = array_sum(array_column($reporteData, 'aprobadas'));
                        $sumas['rechazadas'] = array_sum(array_column($reporteData, 'rechazadas'));
                        $sumas['pendientes'] = array_sum(array_column($reporteData, 'pendientes'));
                    } elseif ($tipoReporte === 'horas') {
                        $sumas['estudiantes'] = array_sum(array_column($reporteData, 'total_estudiantes'));
                        $sumas['horas'] = array_sum(array_column($reporteData, 'horas_totales'));
                    } elseif ($tipoReporte === 'laboratorios') {
                        $sumas['proyectos'] = array_sum(array_column($reporteData, 'total_proyectos'));
                        $sumas['estudiantes'] = array_sum(array_column($reporteData, 'total_estudiantes'));
                        $sumas['horas'] = array_sum(array_column($reporteData, 'horas_totales'));
                    }
                    
                    foreach ($sumas as $key => $value) {
                        echo "<div class='summary-item'>";
                        echo "<h4>" . ucfirst(str_replace('_', ' ', $key)) . "</h4>";
                        echo "<div class='summary-number'>$value</div>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Selecciona los filtros y genera un reporte</p>
                <p>Los datos aparecerán aquí después de generar el reporte</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToExcel() {
    // Crear un libro de trabajo
    const table = document.getElementById('report-table');
    let csv = [];
    
    // Obtener encabezados
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length; i++) {
        headers.push(table.rows[0].cells[i].textContent);
    }
    csv.push(headers.join(','));
    
    // Obtener datos
    for (let i = 1; i < table.rows.length; i++) {
        const row = [];
        for (let j = 0; j < table.rows[i].cells.length; j++) {
            row.push(table.rows[i].cells[j].textContent);
        }
        csv.push(row.join(','));
    }
    
    // Crear archivo CSV
    const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
    const encodedUri = encodeURI(csvContent);
    
    // Crear enlace de descarga
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'reporte_<?= $tipoReporte ?>_<?= date('Y-m-d') ?>.csv');
    document.body.appendChild(link);
    
    // Descargar
    link.click();
    document.body.removeChild(link);
}
</script>

<style>
.report-filter-form {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.report-results {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem;
}

.report-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.report-header h2 {
    margin: 0 0 0.5rem 0;
    color: var(--secondary-color);
}

.report-summary {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.summary-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: var(--radius);
}

.summary-item h4 {
    margin: 0 0 0.5rem 0;
    color: #666;
    font-size: 0.9rem;
}

.summary-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
}
</style>

<?php include '../../includes/footer.php'; ?>