<?php
/**
 * Logout System para SERVICIO_SOCIAL_ITA
 * Versión específica para la estructura del proyecto
 */

// Limpiar cualquier output previo
while (ob_get_level()) {
    ob_end_clean();
}

// Configuración básica
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log del inicio del proceso
error_log("=== LOGOUT.PHP INICIADO ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));

// Incluir archivos de configuración de manera segura
$configPaths = [
    '../config/config.php',
    '../config/session.php',
    '../config/functions.php',
    './config/config.php',    // Por si acaso está en el mismo nivel
    './config/session.php',
    './config/functions.php'
];

$configsLoaded = 0;
foreach ($configPaths as $configFile) {
    if (file_exists($configFile)) {
        try {
            require_once $configFile;
            $configsLoaded++;
            error_log("Config loaded: $configFile");
        } catch (Exception $e) {
            error_log("Error loading $configFile: " . $e->getMessage());
        }
    }
}

error_log("Configs loaded: $configsLoaded");

/**
 * Función simple de redirección con múltiples fallbacks
 */
function redirectToIndex($type = 'success') {
    $redirectUrl = '../index.php?logout=' . urlencode($type);
    
    error_log("Redirecting to: $redirectUrl");
    
    // Limpiar output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Método 1: Headers PHP
    if (!headers_sent()) {
        header("Location: $redirectUrl");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        exit;
    }
    
    // Método 2: JavaScript redirect si headers ya fueron enviados
    echo "<!DOCTYPE html><html><head><title>Redirecting...</title></head><body>";
    echo "<script>window.location.replace('$redirectUrl');</script>";
    echo "<meta http-equiv='refresh' content='0;url=$redirectUrl'>";
    echo "<p>Redirecting... <a href='$redirectUrl'>Click here if not redirected automatically</a></p>";
    echo "</body></html>";
    exit;
}

/**
 * Función principal de logout
 */
function performLogout() {
    error_log("Starting logout process");
    
    $errors = [];
    $success = true;
    
    try {
        // 1. Manejar SecureSession si existe la clase
        if (class_exists('SecureSession')) {
            try {
                error_log("Attempting SecureSession logout");
                $session = SecureSession::getInstance();
                
                if (method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
                    $userId = method_exists($session, 'getUserId') ? $session->getUserId() : 'unknown';
                    error_log("Logging out user: $userId");
                    
                    // Intentar log de actividad si existe
                    if (function_exists('logActivity') && $userId !== 'unknown') {
                        try {
                            logActivity($userId, 'logout', 'Sistema de logout');
                        } catch (Exception $logError) {
                            error_log("Log activity error: " . $logError->getMessage());
                        }
                    }
                    
                    if (method_exists($session, 'destroy')) {
                        $session->destroy();
                        error_log("SecureSession destroyed successfully");
                    }
                }
            } catch (Exception $secureSessionError) {
                error_log("SecureSession error: " . $secureSessionError->getMessage());
                $errors[] = "SecureSession error: " . $secureSessionError->getMessage();
            }
        } else {
            error_log("SecureSession class not available");
        }
        
        // 2. Limpiar sesión PHP estándar
        error_log("Starting standard PHP session cleanup");
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionId = session_id();
        error_log("Current session ID: $sessionId");
        
        // Guardar información antes de limpiar
        $userInfo = $_SESSION['user_id'] ?? 'unknown';
        error_log("Clearing session for user: $userInfo");
        
        // Limpiar variables de sesión
        $_SESSION = array();
        
        // Destruir cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            
            $cookieCleared = setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"] ?? '/', 
                $params["domain"] ?? '',
                $params["secure"] ?? false, 
                $params["httponly"] ?? true
            );
            
            error_log("Session cookie cleared: " . ($cookieCleared ? 'yes' : 'no'));
        }
        
        // Destruir sesión
        if (session_destroy()) {
            error_log("PHP session destroyed successfully");
        } else {
            error_log("Failed to destroy PHP session");
            $errors[] = "Failed to destroy PHP session";
        }
        
        // 3. Limpiar cookies del sistema específico
        $systemCookies = [
            'remember_token',
            'user_session', 
            'ita_social_session',
            'auth_token',
            'user_preferences',
            'servicio_social_token',
            'student_auth',
            'dashboard_settings'
        ];
        
        $cookiesCleared = 0;
        foreach ($systemCookies as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                // Limpiar en múltiples paths para asegurar eliminación
                $paths = ['/', '/SERVICIO_SOCIAL_ITA/', '/dashboard/', '/auth/', '/modules/', '/includes/'];
                
                foreach ($paths as $path) {
                    setcookie($cookieName, '', time() - 3600, $path);
                    setcookie($cookieName, '', time() - 3600, $path, $_SERVER['HTTP_HOST']);
                }
                
                unset($_COOKIE[$cookieName]);
                $cookiesCleared++;
            }
        }
        
        error_log("System cookies cleared: $cookiesCleared");
        
        // 4. Limpieza adicional de headers de caché
        if (!headers_sent()) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
        }
        
        error_log("Logout process completed successfully");
        
    } catch (Exception $e) {
        $success = false;
        $errors[] = $e->getMessage();
        error_log("Critical logout error: " . $e->getMessage());
    }
    
    return [
        'success' => $success,
        'errors' => $errors
    ];
}

// ====================================================================
// PROCESAMIENTO PRINCIPAL
// ====================================================================

try {
    // Obtener parámetros
    $action = $_GET['action'] ?? $_POST['action'] ?? 'logout';
    
    error_log("Processing action: $action");
    
    // Procesar según el tipo de logout
    switch ($action) {
        case 'force':
        case 'emergency':
            error_log("Processing emergency/force logout");
            
            // Logout de emergencia - más agresivo
            try {
                // Forzar inicio de sesión si no está activa
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                
                // Limpiar todo
                $_SESSION = array();
                
                // Destruir sesión agresivamente
                @session_destroy();
                
                // Limpiar cookies de manera agresiva
                if (isset($_SERVER['HTTP_COOKIE'])) {
                    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
                    foreach ($cookies as $cookie) {
                        $parts = explode('=', $cookie);
                        $name = trim($parts[0]);
                        if ($name) {
                            // Limpiar en múltiples paths
                            $paths = ['/', '/SERVICIO_SOCIAL_ITA/', '/dashboard/', '/auth/', '/modules/'];
                            foreach ($paths as $path) {
                                setcookie($name, '', time() - 3600, $path);
                            }
                        }
                    }
                }
                
                error_log("Emergency logout completed");
                
            } catch (Exception $emergencyError) {
                error_log("Emergency logout error: " . $emergencyError->getMessage());
            }
            
            // Redireccionar con mensaje de logout forzado
            redirectToIndex('forced');
            break;
            
        case 'check':
            // Verificar estado de sesión (para AJAX)
            $isLoggedIn = false;
            
            try {
                if (class_exists('SecureSession')) {
                    $session = SecureSession::getInstance();
                    $isLoggedIn = method_exists($session, 'isLoggedIn') ? $session->isLoggedIn() : false;
                } else {
                    // Fallback: verificar sesión PHP estándar
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $isLoggedIn = !empty($_SESSION['user_id']);
                }
            } catch (Exception $checkError) {
                error_log("Session check error: " . $checkError->getMessage());
                $isLoggedIn = false;
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'logged_in' => $isLoggedIn,
                'message' => 'Session status checked'
            ]);
            exit;
            
        case 'logout':
        case 'confirm_logout':
        default:
            error_log("Processing normal logout");
            
            // Logout normal
            $result = performLogout();
            
            if ($result['success']) {
                redirectToIndex('success');
            } else {
                error_log("Logout failed with errors: " . implode(', ', $result['errors']));
                redirectToIndex('error');
            }
            break;
    }
    
} catch (Exception $criticalError) {
    error_log("CRITICAL ERROR in logout.php: " . $criticalError->getMessage());
    
    // Cleanup de emergencia absoluta
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = array();
        @session_destroy();
        
        // Limpiar cookies críticas
        $criticalCookies = ['PHPSESSID', 'user_session', 'auth_token'];
        foreach ($criticalCookies as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                setcookie($cookie, '', time() - 3600, '/');
            }
        }
        
    } catch (Exception $finalError) {
        error_log("Final cleanup error: " . $finalError->getMessage());
    }
    
    // Redirección de emergencia final
    redirectToIndex('error');
}

// Si llegamos aquí, algo salió muy mal
error_log("WARNING: Reached end of logout.php without proper exit");
redirectToIndex('fallback');

?>