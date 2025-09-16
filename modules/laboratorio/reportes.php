<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

// Procesar filtros de reportes
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-3 month'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipoReporte = $_GET['tipo_reporte'] ?? 'estudiantes';

// Obtener datos para reportes
$reporteData = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    switch ($tipoReporte) {
        case 'estudiantes':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.nombre,
                    e.numero_control,
                    e.carrera,
                    p.nombre_proyecto,
                    s.fecha_inicio_propuesta,
                    s.fecha_fin_propuesta,
                    s.horas_completadas,
                    s.estado
                FROM estudiantes e
                JOIN solicitudes_servicio s ON e.id = s.estudiante_id
                JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
                WHERE s.jefe_laboratorio_id = :jefe_id
                ORDER BY e.nombre
            ", ['jefe_id' => $jefeLabId]);
            break;
            
        case 'horas':
            $reporteData = $db->fetchAll("
                SELECT 
                    e.nombre,
                    e.numero_control,
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
                    e.numero_control,
                    r.numero_reporte,
                    r.calificacion,
                    r.estado,
                    r.fecha_evaluacion,
                    r.observaciones_evaluador
                FROM reportes_bimestrales r
                JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                JOIN estudiantes e ON s.estudiante_id = e.id
                WHERE s.jefe_laboratorio_id = :jefe_id
                AND r.evaluado_por = :jefe_id
                ORDER BY r.fecha_evaluacion DESC
            ", ['jefe_id' => $jefeLabId]);
            break;
    }
}

$pageTitle = "Reportes de Laboratorio - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-content">
    <div class="dashboard-header">
        <h1>Reportes del Laboratorio</h1>
        <p>Generación de reportes del laboratorio <?= htmlspecialchars($usuario['laboratorio']) ?></p>
    </div>

    <!-- Filtros de reportes -->
    <div class="filters">
        <form method="GET" class="report-filter-form">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="tipo_reporte">Tipo de Reporte</label>
                    <select id="tipo_reporte" name="tipo_reporte">
                        <option value="estudiantes" <?= $tipoReporte === 'estudiantes' ? 'selected' : '' ?>>Estudiantes Activos</option>
                        <option value="horas" <?= $tipoReporte === 'horas' ? 'selected' : '' ?>>Registro de Horas</option>
                        <option value="evaluaciones" <?= $tipoReporte === 'evaluaciones' ? 'selected' : '' ?>>Evaluaciones Realizadas</option>
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
                    <button type="button" class="btn btn-secondary" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
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
                        'estudiantes' => 'Estudiantes del Laboratorio',
                        'horas' => 'Registro de Horas Bimestrales',
                        'evaluaciones' => 'Evaluaciones Realizadas'
                    ];
                    echo $titulos[$tipoReporte] ?? 'Reporte';
                    ?>
                </h2>
                <p>Laboratorio: <?= htmlspecialchars($usuario['laboratorio']) ?></p>
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
                                case 'estudiantes':
                                    $headers = ['Estudiante', 'No. Control', 'Carrera', 'Proyecto', 'Fecha Inicio', 'Fecha Fin', 'Horas', 'Estado'];
                                    break;
                                case 'horas':
                                    $headers = ['Estudiante', 'No. Control', 'Reporte', 'Periodo', 'Horas Reportadas', 'Horas Acumuladas', 'Calificación', 'Estado'];
                                    break;
                                case 'evaluaciones':
                                    $headers = ['Estudiante', 'No. Control', 'Reporte', 'Calificación', 'Estado', 'Fecha Evaluación', 'Observaciones'];
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
                                case 'estudiantes':
                                    echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                                    echo "<td>{$fila['numero_control']}</td>";
                                    echo "<td>" . htmlspecialchars($fila['carrera']) . "</td>";
                                    echo "<td>" . htmlspecialchars($fila['nombre_proyecto']) . "</td>";
                                    echo "<td>" . formatDate($fila['fecha_inicio_propuesta']) . "</td>";
                                    echo "<td>" . formatDate($fila['fecha_fin_propuesta']) . "</td>";
                                    echo "<td>{$fila['horas_completadas']}</td>";
                                    echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                    break;
                                    
                                case 'horas':
                                    echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                                    echo "<td>{$fila['numero_control']}</td>";
                                    echo "<td>Reporte {$fila['numero_reporte']}</td>";
                                    echo "<td>" . formatDate($fila['periodo_inicio']) . " - " . formatDate($fila['periodo_fin']) . "</td>";
                                    echo "<td>{$fila['horas_reportadas']}</td>";
                                    echo "<td>{$fila['horas_acumuladas']}</td>";
                                    echo "<td>" . ($fila['calificacion'] ?: 'N/A') . "</td>";
                                    echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                    break;
                                    
                                case 'evaluaciones':
                                    echo "<td>" . htmlspecialchars($fila['nombre']) . "</td>";
                                    echo "<td>{$fila['numero_control']}</td>";
                                    echo "<td>Reporte {$fila['numero_reporte']}</td>";
                                    echo "<td>" . ($fila['calificacion'] ?: 'N/A') . "</td>";
                                    echo "<td><span class='badge " . getEstadoBadgeClass($fila['estado']) . "'>" . getEstadoText($fila['estado']) . "</span></td>";
                                    echo "<td>" . ($fila['fecha_evaluacion'] ? formatDateTime($fila['fecha_evaluacion']) : 'N/A') . "</td>";
                                    echo "<td>" . ($fila['observaciones_evaluador'] ? htmlspecialchars(substr($fila['observaciones_evaluador'], 0, 50) . '...') : 'N/A') . "</td>";
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
                    
                    if ($tipoReporte === 'estudiantes') {
                        $horasTotales = array_sum(array_column($reporteData, 'horas_completadas'));
                        $estudiantesActivos = count(array_filter($reporteData, fn($e) => $e['estado'] === 'en_proceso'));
                        
                        echo "<div class='summary-item'><h4>Total Estudiantes</h4><div class='summary-number'>$total</div></div>";
                        echo "<div class='summary-item'><h4>Estudiantes Activos</h4><div class='summary-number'>$estudiantesActivos</div></div>";
                        echo "<div class='summary-item'><h4>Horas Totales</h4><div class='summary-number'>$horasTotales</div></div>";
                        
                    } elseif ($tipoReporte === 'horas') {
                        $horasReportadas = array_sum(array_column($reporteData, 'horas_reportadas'));
                        $promedioCalificacion = array_sum(array_column($reporteData, 'calificacion')) / count(array_filter($reporteData, fn($r) => $r['calificacion']));
                        
                        echo "<div class='summary-item'><h4>Total Reportes</h4><div class='summary-number'>$total</div></div>";
                        echo "<div class='summary-item'><h4>Horas Reportadas</h4><div class='summary-number'>$horasReportadas</div></div>";
                        echo "<div class='summary-item'><h4>Calificación Promedio</h4><div class='summary-number'>" . number_format($promedioCalificacion, 1) . "</div></div>";
                        
                    } elseif ($tipoReporte === 'evaluaciones') {
                        $calificacionPromedio = array_sum(array_column($reporteData, 'calificacion')) / count(array_filter($reporteData, fn($r) => $r['calificacion']));
                        
                        echo "<div class='summary-item'><h4>Total Evaluaciones</h4><div class='summary-number'>$total</div></div>";
                        echo "<div class='summary-item'><h4>Calificación Promedio</h4><div class='summary-number'>" . number_format($calificacionPromedio, 1) . "</div></div>";
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
function exportToPDF() {
    // En un sistema real, esto enviaría una solicitud al servidor para generar un PDF
    alert('Función de exportación a PDF en desarrollo. En un sistema real, esto generaría un documento PDF descargable.');
    
    // Ejemplo de implementación real:
    // window.open('/api/export.php?type=pdf&report=<?= $tipoReporte ?>&start=<?= $fechaInicio ?>&end=<?= $fechaFin ?>', '_blank');
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