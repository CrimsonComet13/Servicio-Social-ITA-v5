<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Verificar que sea una solicitud AJAX
if (!isAjaxRequest()) {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'check_email':
        $email = $_POST['email'] ?? '';
        if (empty($email)) {
            jsonResponse(['error' => 'Email requerido'], 400);
        }
        
        if (!validateEmail($email)) {
            jsonResponse(['valid' => false, 'message' => 'Formato de email inválido']);
        }
        
        $db = Database::getInstance();
        $exists = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
        
        jsonResponse([
            'valid' => !$exists,
            'message' => $exists ? 'Email ya registrado' : 'Email disponible'
        ]);
        break;
        
    case 'check_numero_control':
        $numeroControl = $_POST['numero_control'] ?? '';
        if (empty($numeroControl)) {
            jsonResponse(['error' => 'Número de control requerido'], 400);
        }
        
        if (!validateNumeroControl($numeroControl)) {
            jsonResponse(['valid' => false, 'message' => 'El número de control debe tener 8 dígitos']);
        }
        
        $db = Database::getInstance();
        $exists = $db->fetch("SELECT id FROM estudiantes WHERE numero_control = ?", [$numeroControl]);
        
        jsonResponse([
            'valid' => !$exists,
            'message' => $exists ? 'Número de control ya registrado' : 'Número de control disponible'
        ]);
        break;
        
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            jsonResponse(['error' => 'Email y contraseña requeridos'], 400);
        }
        
        // Verificar intentos de login
        if (checkLoginAttempts($email)) {
            jsonResponse(['error' => 'Demasiados intentos fallidos. Espere 15 minutos.'], 429);
        }
        
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Login exitoso
            recordLoginAttempt($email, true);
            
            // Actualizar último acceso
            $db->update('usuarios', 
                       ['ultimo_acceso' => date('Y-m-d H:i:s')], 
                       'id = :id', 
                       ['id' => $user['id']]);
            
            // Obtener datos específicos según el tipo de usuario
            $userData = [];
            switch ($user['tipo_usuario']) {
                case 'estudiante':
                    $userData = getEstudianteData($user['id']);
                    break;
                case 'jefe_departamento':
                    $userData = getJefeDepartamentoData($user['id']);
                    break;
                case 'jefe_laboratorio':
                    $userData = getJefeLaboratorioData($user['id']);
                    break;
            }
            
            // Iniciar sesión
            session_start();
            $_SESSION['usuario'] = array_merge($user, $userData);
            
            // Registrar actividad
            logActivity($user['id'], 'login', 'auth');
            
            jsonResponse([
                'success' => true,
                'redirect' => "/dashboard/{$user['tipo_usuario']}.php",
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'tipo_usuario' => $user['tipo_usuario'],
                    'nombre' => $userData['nombre'] ?? $user['email']
                ]
            ]);
        } else {
            // Login fallido
            recordLoginAttempt($email, false);
            jsonResponse(['error' => 'Credenciales incorrectas'], 401);
        }
        break;
        
    case 'logout':
        session_start();
        if (isset($_SESSION['usuario'])) {
            logActivity($_SESSION['usuario']['id'], 'logout', 'auth');
            session_destroy();
        }
        jsonResponse(['success' => true, 'redirect' => '/auth/login.php']);
        break;
        
    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
?>