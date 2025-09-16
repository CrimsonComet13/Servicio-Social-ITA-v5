<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Verificar que sea una solicitud AJAX
if (!isAjaxRequest()) {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$session = SecureSession::getInstance();
if (!$session->isLoggedIn()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$action = $_GET['action'] ?? '';
$userRole = $session->getUserRole();
$userId = $session->getUser()['id'];

$db = Database::getInstance();

switch ($action) {
    case 'stats':
        // Obtener estadísticas según el rol del usuario
        $stats = [];
        
        switch ($userRole) {
            case 'estudiante':
                $estudianteId = $session->getUser()['id'];
                $stats = $db->fetch("
                    SELECT 
                        COUNT(s.id) as total_solicitudes,
                        COUNT(CASE WHEN s.estado = 'en_proceso' THEN 1 END) as solicitudes_activas,
                        SUM(s.horas_completadas) as horas_completadas,
                        (SELECT COUNT(*) FROM reportes_bimestrales r 
                         JOIN solicitudes_servicio s2 ON r.solicitud_id = s2.id 
                         WHERE s2.estudiante_id = :estudiante_id) as total_reportes
                    FROM solicitudes_servicio s
                    WHERE s.estudiante_id = :estudiante_id
                ", ['estudiante_id' => $estudianteId]);
                break;
                
            case 'jefe_laboratorio':
                $jefeLabId = $session->getUser()['id'];
                $stats = $db->fetch("
                    SELECT 
                        COUNT(DISTINCT s.estudiante_id) as total_estudiantes,
                        COUNT(DISTINCT r.id) as total_reportes,
                        SUM(r.horas_reportadas) as horas_totales,
                        COUNT(DISTINCT CASE WHEN r.estado = 'pendiente_evaluacion' THEN r.id END) as reportes_pendientes
                    FROM jefes_laboratorio jl
                    LEFT JOIN solicitudes_servicio s ON jl.id = s.jefe_laboratorio_id
                    LEFT JOIN reportes_bimestrales r ON s.id = r.solicitud_id
                    WHERE jl.id = :jefe_id
                ", ['jefe_id' => $jefeLabId]);
                break;
                
            case 'jefe_departamento':
                $jefeId = $session->getUser()['id'];
                $stats = $db->fetch("
                    SELECT 
                        COUNT(DISTINCT s.id) as total_solicitudes,
                        COUNT(DISTINCT CASE WHEN s.estado = 'pendiente' THEN s.id END) as solicitudes_pendientes,
                        COUNT(DISTINCT e.id) as total_estudiantes,
                        COUNT(DISTINCT jl.id) as total_laboratorios,
                        COUNT(DISTINCT CASE WHEN s.estado = 'en_proceso' THEN s.id END) as servicios_activos,
                        SUM(s.horas_completadas) as horas_totales
                    FROM jefes_departamento jd
                    LEFT JOIN solicitudes_servicio s ON jd.id = s.jefe_departamento_id
                    LEFT JOIN estudiantes e ON s.estudiante_id = e.id
                    LEFT JOIN jefes_laboratorio jl ON jd.id = jl.jefe_departamento_id
                    WHERE jd.id = :jefe_id
                ", ['jefe_id' => $jefeId]);
                break;
        }
        
        jsonResponse(['stats' => $stats]);
        break;
        
    case 'notifications':
        // Obtener notificaciones recientes
        $notifications = $db->fetchAll("
            SELECT * FROM notificaciones 
            WHERE usuario_id = :user_id 
            AND leida = FALSE
            ORDER BY fecha_evento DESC 
            LIMIT 10
        ", ['user_id' => $userId]);
        
        jsonResponse(['notifications' => $notifications]);
        break;
        
    case 'mark_notification_read':
        // Marcar notificación como leída
        $notificationId = $_POST['notification_id'] ?? 0;
        
        if ($notificationId) {
            $db->update('notificaciones', 
                ['leida' => TRUE], 
                'id = :id AND usuario_id = :user_id', 
                ['id' => $notificationId, 'user_id' => $userId]
            );
        }
        
        jsonResponse(['success' => true]);
        break;
        
    case 'recent_activity':
        // Obtener actividad reciente según el rol
        $activity = [];
        
        switch ($userRole) {
            case 'estudiante':
                $estudianteId = $session->getUser()['id'];
                $activity = $db->fetchAll("
                    SELECT 'solicitud' as tipo, s.estado, s.fecha_solicitud as fecha, 
                           p.nombre_proyecto as descripcion
                    FROM solicitudes_servicio s
                    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
                    WHERE s.estudiante_id = :estudiante_id
                    UNION
                    SELECT 'reporte' as tipo, r.estado, r.fecha_entrega as fecha,
                           CONCAT('Reporte ', r.numero_reporte) as descripcion
                    FROM reportes_bimestrales r
                    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                    WHERE s.estudiante_id = :estudiante_id
                    ORDER BY fecha DESC
                    LIMIT 5
                ", ['estudiante_id' => $estudianteId]);
                break;
                
            case 'jefe_laboratorio':
                $jefeLabId = $session->getUser()['id'];
                $activity = $db->fetchAll("
                    SELECT 'evaluacion' as tipo, r.estado, r.fecha_evaluacion as fecha,
                           CONCAT('Evaluación reporte ', r.numero_reporte, ' - ', e.nombre) as descripcion
                    FROM reportes_bimestrales r
                    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                    JOIN estudiantes e ON s.estudiante_id = e.id
                    WHERE r.evaluado_por = :jefe_id
                    ORDER BY fecha DESC
                    LIMIT 5
                ", ['jefe_id' => $jefeLabId]);
                break;
                
            case 'jefe_departamento':
                $jefeId = $session->getUser()['id'];
                $activity = $db->fetchAll("
                    SELECT 'solicitud' as tipo, s.estado, s.fecha_solicitud as fecha,
                           CONCAT('Solicitud de ', e.nombre) as descripcion
                    FROM solicitudes_servicio s
                    JOIN estudiantes e ON s.estudiante_id = e.id
                    WHERE s.jefe_departamento_id = :jefe_id
                    ORDER BY fecha DESC
                    LIMIT 5
                ", ['jefe_id' => $jefeId]);
                break;
        }
        
        jsonResponse(['activity' => $activity]);
        break;
        
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
?>