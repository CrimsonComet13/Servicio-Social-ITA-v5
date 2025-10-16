<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
if (!$session->isLoggedIn() || $session->getUserRole() !== 'estudiante') {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$db = Database::getInstance();
$usuario_id = $session->getUser()['id'];
$pageTitle = "Mi Progreso de Horas";

// Obtener datos del estudiante y su progreso
try {
    $estudiante = $db->fetch("
        SELECT e.*, u.email
        FROM estudiantes e 
        JOIN usuarios u ON e.usuario_id = u.id 
        WHERE e.usuario_id = :usuario_id
    ", ['usuario_id' => $usuario_id]);

    if (!$estudiante) {
        throw new Exception("No se encontraron datos del estudiante.");
    }

    $horasRequeridas = 500; // Asumiendo el valor estándar
    $horasCompletadas = $estudiante['horas_completadas'] ?? 0;
    $progresoPorcentaje = $horasRequeridas > 0 ? min(100, ($horasCompletadas / $horasRequeridas) * 100) : 0;

    // Obtener el detalle de horas por reporte
    $detalleHoras = $db->fetchAll("
        SELECT 
            r.id, 
            r.numero_reporte, 
            r.horas_reportadas, 
            r.fecha_entrega,
            r.estado
        FROM reportes_bimestrales r
        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
        ORDER BY r.numero_reporte ASC
    ", ['estudiante_id' => $estudiante['id']]);

    // Obtener horas del reporte final (si existe)
    $reporteFinal = $db->fetch("
        SELECT 
            rf.id, 
            'Final' as numero_reporte, 
            NULL as horas_reportadas, 
            rf.fecha_entrega,
            rf.estado
        FROM reportes_finales rf
        JOIN solicitudes_servicio s ON rf.solicitud_id = s.id
        WHERE s.estudiante_id = :estudiante_id
    ", ['estudiante_id' => $estudiante['id']]);

    if ($reporteFinal) {
        $detalleHoras[] = $reporteFinal;
    }

} catch (Exception $e) {
    error_log("Error al cargar datos de horas: " . $e->getMessage());
    $error_message = "Error al cargar los datos: " . $e->getMessage();
    $estudiante = null;
    $detalleHoras = [];
    $horasCompletadas = 0;
    $progresoPorcentaje = 0;
}

include '../../includes/header.php';
include '../../includes/sidebar.php'; // Incluir el sidebar para el diseño del dashboard
?>

<div class="main-content">
    <div class="container mt-5">
        <h2 class="mb-4 text-primary"><i class="fas fa-clock"></i> Mi Progreso de Horas de Servicio Social</h2>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= $error_message ?>
            </div>
        <?php elseif ($estudiante): ?>
            
            <!-- Tarjeta de Resumen de Progreso -->
            <div class="card shadow-lg mb-5">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Resumen General</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded">
                                <h5 class="text-muted">Horas Requeridas</h5>
                                <p class="h3 text-primary"><?= $horasRequeridas ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded">
                                <h5 class="text-muted">Horas Completadas</h5>
                                <p class="h3 text-success"><?= $horasCompletadas ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded">
                                <h5 class="text-muted">Progreso</h5>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                         role="progressbar" 
                                         style="width: <?= $progresoPorcentaje ?>%;" 
                                         aria-valuenow="<?= $progresoPorcentaje ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= round($progresoPorcentaje, 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalle de Horas Reportadas -->
            <div class="card shadow-lg">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">Detalle de Horas por Reporte</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($detalleHoras)): ?>
                        <div class="alert alert-warning" role="alert">
                            Aún no has entregado ningún reporte de horas.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Reporte</th>
                                        <th>Horas Reportadas</th>
                                        <th>Fecha de Entrega</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalleHoras as $detalle): 
                                        $isFinal = $detalle['numero_reporte'] === 'Final';
                                        $reporteLabel = $isFinal ? 'Reporte Final' : 'Bimestre ' . $detalle['numero_reporte'];
                                        $estadoClass = getEstadoCssClass($detalle['estado'] ?? 'desconocido');
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($reporteLabel) ?></td>
                                            <td><?= htmlspecialchars($detalle['horas_reportadas']) ?></td>
                                            <td><?= formatDate($detalle['fecha_entrega']) ?></td>
                                            <td><span class="badge <?= $estadoClass ?>"><?= ucfirst($detalle['estado'] ?? 'Desconocido') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
