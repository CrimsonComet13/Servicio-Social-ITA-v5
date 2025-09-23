<?php
/**
 * Logout System - Versión simplificada y mejorada
 * Sistema de cierre de sesión consistente con rutas relativas
 */

require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpiar output buffer
if (ob_get_level()) {
    ob_end_clean();
}

/**
 * Función para detectar requests AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Función para enviar respuesta JSON limpia
 */
function sendJsonResponse($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función para redireccionar con rutas relativas consistentes
 */
function redirectTo($location, $params = []) {
    if (!headers_sent()) {
        $url = $location;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header("Location: $url");
        exit;
    }
}

/**
 * Función principal de logout
 */
function performLogout() {
    $success = true;
    $errors = [];
    
    try {
        // 1. Obtener instancia de SecureSession y cerrar sesión
        $session = SecureSession::getInstance();
        
        if ($session->isLoggedIn()) {
            $userId = $session->getUserId();
            $userRole = $session->getUserRole();
            
            // Registrar logout en log de actividades si es posible
            try {
                if (function_exists('logActivity') && $userId) {
                    logActivity($userId, 'logout', 'auth');
                }
            } catch (Exception $e) {
                // No es crítico si falla el logging
                error_log("Error logging logout activity: " . $e->getMessage());
            }
            
            // Destruir sesión segura
            $session->destroy();
        }
        
        // 2. Limpiar sesión PHP nativa como respaldo
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Limpiar variables de sesión
        $_SESSION = array();
        
        // Destruir cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Destruir sesión
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // 3. Limpiar cookies adicionales del sistema
        $systemCookies = [
            'remember_token', 
            'user_session', 
            'ita_social_session',
            'auth_token',
            'user_preferences'
        ];
        
        foreach ($systemCookies as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                // Limpiar cookie en diferentes paths
                $paths = ['/', '/dashboard/', '/auth/', '/modules/'];
                foreach ($paths as $path) {
                    setcookie($cookieName, '', time() - 3600, $path);
                }
            }
        }
        
    } catch (Exception $e) {
        $success = false;
        $errors[] = $e->getMessage();
        error_log("Error en logout: " . $e->getMessage());
    }
    
    return [
        'success' => $success,
        'errors' => $errors
    ];
}

// ============================================================================
// PROCESAMIENTO PRINCIPAL
// ============================================================================

try {
    // Manejar requests AJAX
    if (isAjaxRequest()) {
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'logout' || $action === 'confirm_logout') {
                $result = performLogout();
                
                sendJsonResponse([
                    'success' => $result['success'],
                    'message' => $result['success'] 
                        ? 'Sesión cerrada correctamente' 
                        : 'Error durante el logout',
                    'redirect' => '../index.php',
                    'errors' => $result['errors']
                ]);
            }
        } 
        
        // GET AJAX requests
        elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'check':
                    // Verificar estado de sesión
                    $session = SecureSession::getInstance();
                    sendJsonResponse([
                        'logged_in' => $session->isLoggedIn(),
                        'message' => 'Estado de sesión verificado'
                    ]);
                    break;
                    
                case 'force':
                case 'emergency':
                    // Logout forzado
                    $result = performLogout();
                    sendJsonResponse([
                        'success' => true,
                        'message' => 'Logout forzado completado',
                        'redirect' => '../index.php'
                    ]);
                    break;
                    
                default:
                    // Logout AJAX normal
                    $result = performLogout();
                    sendJsonResponse([
                        'success' => $result['success'],
                        'message' => $result['success'] 
                            ? 'Sesión cerrada correctamente' 
                            : 'Error durante el logout',
                        'redirect' => '../index.php'
                    ]);
            }
        }
    }
    
    // Manejar requests normales (no AJAX)
    else {
        $action = $_GET['action'] ?? '';
        
        // Procesar diferentes tipos de logout
        switch ($action) {
            case 'force':
            case 'emergency':
                // Logout forzado - limpiar todo agresivamente
                try {
                    @session_start();
                    $_SESSION = array();
                    @session_destroy();
                    
                    // Limpiar cookies del sistema
                    if (isset($_SERVER['HTTP_COOKIE'])) {
                        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
                        foreach($cookies as $cookie) {
                            $parts = explode('=', $cookie);
                            $name = trim($parts[0]);
                            setcookie($name, '', time() - 3600, '/');
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error en logout forzado: " . $e->getMessage());
                }
                
                redirectTo('../index.php', ['logout' => 'forced']);
                break;
                
            default:
                // Logout normal
                $result = performLogout();
                
                if ($result['success']) {
                    redirectTo('../index.php', ['logout' => 'success']);
                } else {
                    redirectTo('../index.php', ['logout' => 'error']);
                }
        }
    }
    
} catch (Exception $e) {
    error_log("Error crítico en logout: " . $e->getMessage());
    
    if (isAjaxRequest()) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Error crítico durante el logout',
            'redirect' => '../index.php',
            'error' => $e->getMessage()
        ]);
    } else {
        // Logout de emergencia y redirección
        try {
            @session_start();
            $_SESSION = array();
            @session_destroy();
        } catch (Exception $sessionError) {
            error_log("Error en cleanup de emergencia: " . $sessionError->getMessage());
        }
        
        redirectTo('../index.php', ['logout' => 'error']);
    }
}

// Si llegamos aquí sin hacer exit, hay un problema
error_log("WARNING: Llegamos al final de logout.php sin hacer exit");
redirectTo('../index.php', ['logout' => 'fallback']);

?>