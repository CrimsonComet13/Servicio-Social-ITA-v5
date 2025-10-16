<?php
session_start();
include_once '../../config/database.php';
include_once '../../models/ReporteBimestral.php';
include_once '../../models/ReporteFinal.php';
include_once '../../models/Usuario.php';

// Verificar si el usuario está logueado y tiene el rol adecuado
if (!isset($_SESSION['user_id']) || (!in_array($_SESSION['user_type'], ['jefe_departamento', 'jefe_laboratorio', 'estudiante'])) ) {
    header('Location: ../../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$reporte_bimestral = new ReporteBimestral($db);
$reporte_final = new ReporteFinal($db);
$usuario = new Usuario($db);

$report_id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: Reporte no especificado.');
$report_type = isset($_GET['type']) ? $_GET['type'] : die('ERROR: Tipo de reporte no especificado.');

$report_details = null;

if ($report_type == 'bimestral') {
    $reporte_bimestral->id = $report_id;
    $report_details = $reporte_bimestral->readOne();
} elseif ($report_type == 'final') {
    $reporte_final->id = $report_id;
    $report_details = $reporte_final->readOne();
} else {
    die('ERROR: Tipo de reporte inválido.');
}

if (!$report_details) {
    die('ERROR: Reporte no encontrado.');
}

// Obtener información del estudiante y del evaluador (si existe)
$estudiante_info = $usuario->readOneById($report_details['estudiante_id']);
$evaluador_info = null;
if (isset($report_details['evaluado_por']) && !empty($report_details['evaluado_por'])) {
    $evaluador_info = $usuario->readOneById($report_details['evaluado_por']);
}

$page_title = "Detalle de Reporte";
include_once '../../includes/header.php';
?>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h1>Detalle de Reporte <?php echo ucfirst($report_type); ?> #<?php echo htmlspecialchars($report_id); ?></h1>
        </div>
        <div class="card-body">
            <p><strong>Estudiante:</strong> <?php echo htmlspecialchars($estudiante_info['email']); ?></p>
            <p><strong>Periodo:</strong> <?php echo htmlspecialchars($report_details['periodo_inicio']) . ' - ' . htmlspecialchars($report_details['periodo_fin']); ?></p>
            <p><strong>Horas Reportadas:</strong> <?php echo htmlspecialchars($report_details['horas_reportadas']); ?></p>
            <p><strong>Horas Acumuladas:</strong> <?php echo htmlspecialchars($report_details['horas_acumuladas']); ?></p>
            <p><strong>Actividades Realizadas:</strong> <?php echo htmlspecialchars($report_details['actividades_realizadas']); ?></p>
            <?php if (!empty($report_details['logros_obtenidos'])): ?>
                <p><strong>Logros Obtenidos:</strong> <?php echo htmlspecialchars($report_details['logros_obtenidos']); ?></p>
            <?php endif; ?>
            <?php if (!empty($report_details['dificultades_encontradas'])): ?>
                <p><strong>Dificultades Encontradas:</strong> <?php echo htmlspecialchars($report_details['dificultades_encontradas']); ?></p>
            <?php endif; ?>
            <p><strong>Estado:</strong> <?php echo htmlspecialchars($report_details['estado']); ?></p>
            <?php if (!empty($report_details['calificacion'])): ?>
                <p><strong>Calificación:</strong> <?php echo htmlspecialchars($report_details['calificacion']); ?></p>
            <?php endif; ?>
            <?php if (!empty($report_details['observaciones_evaluador'])): ?>
                <p><strong>Observaciones del Evaluador:</strong> <?php echo htmlspecialchars($report_details['observaciones_evaluador']); ?></p>
            <?php endif; ?>
            <?php if ($evaluador_info): ?>
                <p><strong>Evaluado por:</strong> <?php echo htmlspecialchars($evaluador_info['email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($report_details['fecha_evaluacion'])): ?>
                <p><strong>Fecha de Evaluación:</strong> <?php echo htmlspecialchars($report_details['fecha_evaluacion']); ?></p>
            <?php endif; ?>
            
            <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
