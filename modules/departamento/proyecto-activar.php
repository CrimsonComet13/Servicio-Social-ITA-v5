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

$projectId = $_GET['id'] ?? null;
if (!$projectId || !is_numeric($projectId)) {
    flashMessage('Proyecto no válido', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

// Verificar que el proyecto pertenece al jefe de departamento
$proyecto = $db->fetch("
    SELECT * FROM proyectos_laboratorio 
    WHERE id = :id AND jefe_departamento_id = :jefe_id
", ['id' => $projectId, 'jefe_id' => $jefeId]);

if (!$proyecto) {
    flashMessage('Proyecto no encontrado', 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

if ($proyecto['activo']) {
    flashMessage('El proyecto ya está activo', 'warning');
    redirectTo('/modules/departamento/proyectos.php');
}

try {
    $db->update('proyectos_laboratorio', ['activo' => 1], 'id = :id', ['id' => $projectId]);
    
    logActivity($usuario['id'], 'activar', 'proyectos', $projectId, [
        'nombre_proyecto' => $proyecto['nombre_proyecto']
    ]);
    
    // Notificar al jefe de laboratorio si está asignado
    if ($proyecto['jefe_laboratorio_id']) {
        $jefeLabData = $db->fetch("SELECT usuario_id, nombre FROM jefes_laboratorio WHERE id = ?", 
                                 [$proyecto['jefe_laboratorio_id']]);
        
        if ($jefeLabData) {
            createNotification(
                $jefeLabData['usuario_id'],
                'Proyecto Activado',
                "El proyecto '{$proyecto['nombre_proyecto']}' ha sido activado y está disponible para estudiantes.",
                'success',
                "/modules/laboratorio/proyecto-detalle.php?id={$projectId}"
            );
        }
    }
    
    flashMessage('Proyecto activado exitosamente', 'success');
} catch (Exception $e) {
    error_log("Error activando proyecto: " . $e->getMessage());
    flashMessage('Error al activar el proyecto', 'error');
}

redirectTo('/modules/departamento/proyectos.php');
?>