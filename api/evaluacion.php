<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
if (!$session->isLoggedIn() || $session->getUserRole() !== 'estudiante') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

$db = Database::getInstance();
$estudiante_id = $session->getUser()['id'];

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_reporte'], $data['tipo_reporte'], $data['actividades_realizadas'], $data['calificaciones'], $data['observaciones'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit();
}

$id_reporte = $data['id_reporte'];
$tipo_reporte = $data['tipo_reporte'];
$actividades_realizadas = $data['actividades_realizadas'];
$observaciones = $data['observaciones'];
$calificaciones = $data['calificaciones'];

try {
    $db->beginTransaction();

    // Paso 1: Actualizar el reporte (bimestral o final)
    $tabla_reportes = '';
    if ($tipo_reporte === 'bimestral') {
        $tabla_reportes = 'reportes_bimestrales';
    } elseif ($tipo_reporte === 'final') {
        $tabla_reportes = 'reportes_finales';
    } else {
        throw new Exception('Tipo de reporte inválido.');
    }

    // Verificar que el reporte pertenece al estudiante
    $reporte_existente = $db->fetch("SELECT id, solicitud_id FROM $tabla_reportes WHERE id = :id AND estado_evaluacion_estudiante = 'pendiente'", ['id' => $id_reporte]);
    if (!$reporte_existente) {
        throw new Exception('Reporte no encontrado o no pendiente de entrega.');
    }

    // Actualizar actividades realizadas y estado del reporte
    $db->update($tabla_reportes, [
        'actividades_realizadas' => $actividades_realizadas,
        'fecha_entrega' => date('Y-m-d H:i:s'),
        'estado' => 'entregado', // Cambiar el estado a entregado
        'estado_evaluacion_estudiante' => 'entregado_pendiente_revision' // Nuevo estado para indicar que el estudiante ya entregó
    ], 'id = :id', ['id' => $id_reporte]);

    // Paso 2: Guardar la autoevaluación en una tabla 'evaluaciones_estudiante'
    // Primero, eliminar evaluaciones anteriores para este reporte y estudiante si existen
    $db->delete('evaluaciones_estudiante', 'reporte_id = :reporte_id AND tipo_reporte = :tipo_reporte AND estudiante_id = :estudiante_id', [
        'reporte_id' => $id_reporte,
        'tipo_reporte' => $tipo_reporte,
        'estudiante_id' => $estudiante_id
    ]);

    $total_calificacion = 0;
    $num_criterios = 0;
    foreach ($calificaciones as $criterio_id => $calificacion) {
        $db->insert('evaluaciones_estudiante', [
            'reporte_id' => $id_reporte,
            'tipo_reporte' => $tipo_reporte,
            'estudiante_id' => $estudiante_id,
            'criterio_id' => $criterio_id,
            'calificacion' => $calificacion,
            'observaciones' => $observaciones, // Las observaciones generales se guardan con cada criterio, o se puede crear un campo aparte
            'fecha_evaluacion' => date('Y-m-d H:i:s')
        ]);
        $total_calificacion += (int)$calificacion;
        $num_criterios++;
    }

    // Paso 3: Calcular el promedio y actualizar el reporte
    $calificacion_final = $num_criterios > 0 ? round($total_calificacion / $num_criterios, 2) : 0;
    $db->update($tabla_reportes, [
        'calificacion_estudiante' => $calificacion_final
    ], 'id = :id', ['id' => $id_reporte]);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Reporte y autoevaluación guardados con éxito.', 'calificacion_final' => $calificacion_final]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el reporte: ' . $e->getMessage()]);
}

?>
