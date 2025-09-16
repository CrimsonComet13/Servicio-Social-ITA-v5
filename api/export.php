<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';
require_once '../pdf/generator.php';

// Verificar que sea una solicitud AJAX
if (!isAjaxRequest()) {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$session = SecureSession::getInstance();
if (!$session->isLoggedIn()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$documentType = $_POST['type'] ?? $_GET['type'] ?? '';
$documentId = $_POST['id'] ?? $_GET['id'] ?? 0;

try {
    switch ($action) {
        case 'generate':
            if (empty($documentType) || empty($documentId)) {
                jsonResponse(['error' => 'Parámetros incompletos'], 400);
            }
            
            // Verificar permisos según el tipo de usuario
            $userRole = $session->getUserRole();
            $allowed = false;
            
            switch ($documentType) {
                case 'oficio':
                    $allowed = in_array($userRole, ['jefe_departamento', 'jefe_laboratorio']);
                    break;
                case 'carta':
                    $allowed = in_array($userRole, ['jefe_departamento', 'jefe_laboratorio']);
                    break;
                case 'constancia':
                    $allowed = in_array($userRole, ['jefe_departamento']);
                    break;
                default:
                    $allowed = false;
            }
            
            if (!$allowed) {
                jsonResponse(['error' => 'No tiene permisos para generar este documento'], 403);
            }
            
            // Generar documento
            $result = generarDocumento($documentType, $documentId);
            
            if ($result) {
                jsonResponse([
                    'success' => true,
                    'document' => $result,
                    'download_url' => UPLOAD_URL . $result['path']
                ]);
            } else {
                jsonResponse(['error' => 'Error al generar el documento'], 500);
            }
            break;
            
        case 'download':
            $filePath = $_GET['file'] ?? '';
            if (empty($filePath)) {
                jsonResponse(['error' => 'Archivo no especificado'], 400);
            }
            
            // Verificar que el archivo existe y es seguro
            $fullPath = UPLOAD_PATH . $filePath;
            if (!file_exists($fullPath) || !is_file($fullPath)) {
                jsonResponse(['error' => 'Archivo no encontrado'], 404);
            }
            
            // Verificar que el usuario tiene acceso al archivo
            // (Aquí se debería implementar una verificación más robusta)
            $allowed = false;
            $userRole = $session->getUserRole();
            
            if ($userRole === 'estudiante') {
                // Verificar si el estudiante es el dueño del documento
                $parts = explode('/', $filePath);
                $documentType = $parts[0];
                
                if ($documentType === 'constancias') {
                    $constancia = $db->fetch("SELECT estudiante_id FROM constancias WHERE archivo_path = ?", [$filePath]);
                    $allowed = $constancia && $constancia['estudiante_id'] == $session->getUser()['id'];
                }
                // Agregar más verificaciones para otros tipos de documentos
            } else {
                // Jefes tienen acceso a todos los documentos
                $allowed = true;
            }
            
            if (!$allowed) {
                jsonResponse(['error' => 'No tiene permisos para acceder a este archivo'], 403);
            }
            
            // Descargar archivo
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
            
        default:
            jsonResponse(['error' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    error_log('Error en export API: ' . $e->getMessage());
    jsonResponse(['error' => 'Error interno del servidor'], 500);
}
?>