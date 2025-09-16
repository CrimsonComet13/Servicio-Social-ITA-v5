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

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userRole = $session->getUserRole();
$userId = $session->getUser()['id'];

$db = Database::getInstance();

switch ($action) {
    case 'upload_file':
        $tipo = $_POST['tipo'] ?? '';
        $id = $_POST['id'] ?? 0;
        
        if (empty($tipo) || empty($id) || empty($_FILES)) {
            jsonResponse(['error' => 'Parámetros incompletos'], 400);
        }
        
        try {
            $filePath = '';
            $directory = '';
            
            // Determinar directorio según el tipo de archivo
            switch ($tipo) {
                case 'reporte_bimestral':
                    $directory = 'reportes';
                    // Verificar permisos
                    $reporte = $db->fetch("
                        SELECT r.* 
                        FROM reportes_bimestrales r
                        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                        WHERE r.id = :id 
                        AND s.estudiante_id = :estudiante_id
                    ", ['id' => $id, 'estudiante_id' => $userId]);
                    
                    if (!$reporte) {
                        jsonResponse(['error' => 'No tiene permisos para subir este archivo'], 403);
                    }
                    break;
                    
                case 'solicitud':
                    $directory = 'solicitudes';
                    // Verificar permisos
                    $solicitud = $db->fetch("
                        SELECT * FROM solicitudes_servicio 
                        WHERE id = :id 
                        AND estudiante_id = :estudiante_id
                    ", ['id' => $id, 'estudiante_id' => $userId]);
                    
                    if (!$solicitud) {
                        jsonResponse(['error' => 'No tiene permisos para subir este archivo'], 403);
                    }
                    break;
                    
                default:
                    jsonResponse(['error' => 'Tipo de archivo no válido'], 400);
            }
            
            // Subir archivo
            $file = $_FILES['archivo'];
            $filePath = uploadFile($file, $directory);
            
            // Actualizar base de datos según el tipo
            switch ($tipo) {
                case 'reporte_bimestral':
                    $db->update('reportes_bimestrales', 
                        ['archivo_path' => $filePath],
                        'id = :id',
                        ['id' => $id]
                    );
                    break;
                    
                case 'solicitud':
                    $db->update('solicitudes_servicio', 
                        ['archivo_solicitud' => $filePath],
                        'id = :id',
                        ['id' => $id]
                    );
                    break;
            }
            
            jsonResponse([
                'success' => true,
                'file_path' => $filePath,
                'file_url' => UPLOAD_URL . $filePath
            ]);
            
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;
        
    case 'delete_file':
        $filePath = $_POST['file_path'] ?? '';
        $tipo = $_POST['tipo'] ?? '';
        $id = $_POST['id'] ?? 0;
        
        if (empty($filePath) || empty($tipo) || empty($id)) {
            jsonResponse(['error' => 'Parámetros incompletos'], 400);
        }
        
        try {
            // Verificar permisos según el tipo
            $allowed = false;
            
            switch ($tipo) {
                case 'reporte_bimestral':
                    $reporte = $db->fetch("
                        SELECT r.* 
                        FROM reportes_bimestrales r
                        JOIN solicitudes_servicio s ON r.solicitud_id = s.id
                        WHERE r.id = :id 
                        AND (s.estudiante_id = :user_id OR r.evaluado_por = :user_id)
                    ", ['id' => $id, 'user_id' => $userId]);
                    
                    $allowed = (bool)$reporte;
                    break;
                    
                case 'solicitud':
                    $solicitud = $db->fetch("
                        SELECT * FROM solicitudes_servicio 
                        WHERE id = :id 
                        AND estudiante_id = :user_id
                    ", ['id' => $id, 'user_id' => $userId]);
                    
                    $allowed = (bool)$solicitud;
                    break;
                    
                default:
                    $allowed = false;
            }
            
            if (!$allowed) {
                jsonResponse(['error' => 'No tiene permisos para eliminar este archivo'], 403);
            }
            
            // Eliminar archivo físico
            if (deleteFile($filePath)) {
                // Actualizar base de datos
                switch ($tipo) {
                    case 'reporte_bimestral':
                        $db->update('reportes_bimestrales', 
                            ['archivo_path' => null],
                            'id = :id',
                            ['id' => $id]
                        );
                        break;
                        
                    case 'solicitud':
                        $db->update('solicitudes_servicio', 
                            ['archivo_solicitud' => null],
                            'id = :id',
                            ['id' => $id]
                        );
                        break;
                }
                
                jsonResponse(['success' => true]);
            } else {
                jsonResponse(['error' => 'Error al eliminar el archivo'], 500);
            }
            
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
?>