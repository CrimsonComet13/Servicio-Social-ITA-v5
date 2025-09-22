<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

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

if (!$proyecto['activo']) {
    flashMessage('El proyecto ya está inactivo', 'warning');
    redirectTo('/modules/departamento/proyectos.php');
}

// Verificar si hay estudiantes activos
$estudiantesActivos = $db->fetch("
    SELECT COUNT(*) as total 
    FROM solicitudes_servicio 
    WHERE proyecto_id = :proyecto_id AND estado = 'en_proceso'
", ['proyecto_id' => $projectId])['total'];

if ($estudiantesActivos > 0) {
    flashMessage("No se puede desactivar el proyecto. Hay {$estudiantesActivos} estudiante(s) activo(s)", 'error');
    redirectTo('/modules/departamento/proyectos.php');
}

// Verificar si hay solicitudes pendientes
$solicitudesPendientes = $db->fetch("
    SELECT COUNT(*) as total 
    FROM solicitudes_servicio 
    WHERE proyecto_id = :proyecto_id AND estado = 'pendiente'
", ['proyecto_id' => $projectId])['total'];

try {
    $db->beginTransaction();
    
    // Si hay solicitudes pendientes, rechazarlas automáticamente
    if ($solicitudesPendientes > 0) {
        $db->update('solicitudes_servicio', 
            [
                'estado' => 'rechazada',
                'motivo_rechazo' => 'Proyecto desactivado por el departamento'
            ], 
            'proyecto_id = :proyecto_id AND estado = :estado', 
            ['proyecto_id' => $projectId, 'estado' => 'pendiente']
        );
        
        // Notificar a estudiantes con solicitudes rechazadas
        $estudiantes = $db->fetchAll("
            SELECT e.usuario_id, e.nombre 
            FROM estudiantes e
            JOIN solicitudes_servicio s ON e.id = s.estudiante_id
            WHERE s.proyecto_id = :proyecto_id AND s.estado = 'rechazada'
        ", ['proyecto_id' => $projectId]);
        
        foreach ($estudiantes as $estudiante) {
            createNotification(
                $estudiante['usuario_id'],
                'Solicitud Rechazada',
                "Tu solicitud para el proyecto '{$proyecto['nombre_proyecto']}' ha sido rechazada porque el proyecto fue desactivado.",
                'error'
            );
        }
    }
    
    // Desactivar el proyecto
    $db->update('proyectos_laboratorio', ['activo' => 0], 'id = :id', ['id' => $projectId]);
    
    logActivity($usuario['id'], 'desactivar', 'proyectos', $projectId, [
        'nombre_proyecto' => $proyecto['nombre_proyecto'],
        'solicitudes_rechazadas' => $solicitudesPendientes
    ]);
    
    // Notificar al jefe de laboratorio si está asignado
    if ($proyecto['jefe_laboratorio_id']) {
        $jefeLabData = $db->fetch("SELECT usuario_id, nombre FROM jefes_laboratorio WHERE id = ?", 
                                 [$proyecto['jefe_laboratorio_id']]);
        
        if ($jefeLabData) {
            createNotification(
                $jefeLabData['usuario_id'],
                'Proyecto Desactivado',
                "El proyecto '{$proyecto['nombre_proyecto']}' ha sido desactivado.",
                'warning'
            );
        }
    }
    
    $db->commit();
    
    $mensaje = 'Proyecto desactivado exitosamente';
    if ($solicitudesPendientes > 0) {
        $mensaje .= ". Se rechazaron {$solicitudesPendientes} solicitudes pendientes";
    }
    flashMessage($mensaje, 'success');
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Error desactivando proyecto: " . $e->getMessage());
    flashMessage('Error al desactivar el proyecto', 'error');
}

redirectTo('/modules/departamento/proyectos.php');
?>