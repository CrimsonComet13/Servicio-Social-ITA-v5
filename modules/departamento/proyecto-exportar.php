<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeDepto = $db->fetch("SELECT id FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}
$jefeId = $jefeDepto['id'];

$formato = $_GET['formato'] ?? 'pdf';
$proyectoId = $_GET['proyecto_id'] ?? null;

// Validar acceso al proyecto
if ($proyectoId) {
    $proyecto = $db->fetch("
        SELECT * FROM proyectos_laboratorio 
        WHERE id = :id AND jefe_departamento_id = :jefe_id
    ", ['id' => $proyectoId, 'jefe_id' => $jefeId]);
    
    if (!$proyecto) {
        flashMessage('Proyecto no encontrado', 'error');
        redirectTo('/modules/departamento/proyectos.php');
    }
}

// Generar reporte
$reporte = generarReporteProyecto($proyectoId ?: null);

if ($formato === 'pdf') {
    // Headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reporte-proyectos-' . date('Y-m-d') . '.pdf"');
    
    // Aquí iría la generación real del PDF (usando Dompdf, TCPDF, etc.)
    echo "PDF Generation would go here - Project: " . ($proyectoId ? $proyecto['nombre_proyecto'] : 'Todos');
    
} elseif ($formato === 'excel') {
    // Headers para Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte-proyectos-' . date('Y-m-d') . '.xls"');
    
    // Generar contenido Excel básico
    echo "Excel Generation would go here";
    
} else {
    flashMessage('Formato no válido', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

exit;
?>