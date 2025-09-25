<?php
/**
 * Configuración específica para el sistema de logout
 * Este archivo debe ser incluido en logout.php para asegurar compatibilidad
 */

// Prevenir acceso directo
if (basename($_SERVER['PHP_SELF']) === 'logout-config.php') {
    die('Acceso no permitido');
}

/**
 * Función para detectar la estructura base del proyecto
 */
function getProjectBasePath() {
    $currentPath = __DIR__;
    $possiblePaths = [
        dirname($currentPath), // Un nivel arriba (desde auth/)
        dirname(dirname($currentPath)), // Dos niveles arriba
        $currentPath // Mismo nivel
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/index.php') && file_exists($path . '/config/config.php')) {
            return $path;
        }
    }
    
    // Fallback: asumir estructura estándar
    return dirname(__DIR__);
}

/**
 * Función para obtener la URL base del proyecto
 */
function getProjectBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = $_SERVER['REQUEST_URI'] ?? '';
    
    // Remover archivo actual y directorio auth
    $pathParts = explode('/', trim($path, '/'));
    
    // Remover archivo actual
    if (end($pathParts) && strpos(end($pathParts), '.php') !== false) {
        array_pop($pathParts);
    }
    
    // Remover directorio auth si está presente
    if (end($pathParts) === 'auth') {
        array_pop($pathParts);
    }
    
    $basePath = implode('/', $pathParts);
    return $protocol . $host . '/' . $basePath . (empty($basePath) ? '' : '/');
}

/**
 * Clase simple para manejo de sesiones si SecureSession no está disponible
 */
class SimpleSessionManager {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function isLoggedIn() {
        return !empty($_SESSION['user_id']) || !empty($_SESSION['usuario']);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? $_SESSION['usuario']['id'] ?? null;
    }
    
    public function getUserRole() {
        return $_SESSION['user_role'] ?? $_SESSION['usuario']['tipo_usuario'] ?? 'estudiante';
    }
    
    public function get($key) {
        return $_SESSION[$key] ?? $_SESSION['usuario'][$key] ?? null;
    }
    
    public function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        return session_destroy();
    }
}

/**
 * Función simple de logging si no existe logActivity
 */
function simpleLogActivity($userId, $action, $module = 'system') {
    $logMessage = date('Y-m-d H:i:s') . " - User $userId - $action in $module\n";
    $logFile = getProjectBasePath() . '/logs/activity.log';
    
    // Crear directorio de logs si no existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Escribir log
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Función para limpiar cookies de forma segura
 */
function clearAllSessionCookies() {
    $cookiesToClear = [
        'PHPSESSID',
        'remember_token',
        'user_session',
        'auth_token',
        'ita_social_session',
        'user_preferences',
        'dashboard_settings'
    ];
    
    $paths = ['/', '/servicio_social_ita/', '/auth/', '/dashboard/', '/modules/'];
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    
    foreach ($cookiesToClear as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            // Limpiar en múltiples paths
            foreach ($paths as $path) {
                setcookie($cookieName, '', time() - 3600, $path);
                if ($domain) {
                    setcookie($cookieName, '', time() - 3600, $path, $domain);
                }
            }
            unset($_COOKIE[$cookieName]);
        }
    }
}

/**
 * Función para cargar configuraciones de manera segura
 */
function loadProjectConfig() {
    $basePath = getProjectBasePath();
    $configFiles = [
        $basePath . '/config/config.php',
        $basePath . '/config/session.php',
        $basePath . '/config/functions.php',
        $basePath . '/config/database.php'
    ];
    
    $loadedConfigs = 0;
    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            try {
                include_once $configFile;
                $loadedConfigs++;
            } catch (Exception $e) {
                error_log("Error loading config $configFile: " . $e->getMessage());
            }
        }
    }
    
    return $loadedConfigs > 0;
}

/**
 * Función para obtener o crear instancia de sesión
 */
function getSessionManager() {
    if (class_exists('SecureSession')) {
        try {
            return SecureSession::getInstance();
        } catch (Exception $e) {
            error_log("Error with SecureSession: " . $e->getMessage());
        }
    }
    
    return SimpleSessionManager::getInstance();
}

/**
 * Función de redirección robusta específica para logout
 */
function logoutRedirect($type = 'success') {
    $baseUrl = getProjectBaseUrl();
    
    // Diferentes URLs según el tipo
    $redirectUrls = [
        'success' => $baseUrl . 'index.php?logout=success',
        'forced' => $baseUrl . 'index.php?logout=forced', 
        'error' => $baseUrl . 'index.php?logout=error',
        'timeout' => $baseUrl . 'index.php?logout=timeout',
        'emergency' => $baseUrl . 'index.php?logout=emergency'
    ];
    
    $redirectUrl = $redirectUrls[$type] ?? $redirectUrls['success'];
    
    // Limpiar output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers de limpieza
    if (!headers_sent()) {
        header("Location: $redirectUrl");
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\"");
        exit();
    }
    
    // Fallback con JavaScript
    echo "<!DOCTYPE html><html><head>";
    echo "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($redirectUrl) . "'>";
    echo "<title>Redirecting...</title></head><body>";
    echo "<script>";
    echo "try { localStorage.clear(); sessionStorage.clear(); } catch(e) {}";
    echo "window.location.replace('" . addslashes($redirectUrl) . "');";
    echo "</script>";
    echo "<p>Redirecting to <a href='" . htmlspecialchars($redirectUrl) . "'>logout page</a>...</p>";
    echo "</body></html>";
    exit();
}

/**
 * Función de limpieza completa del sistema
 */
function performCompleteLogoutCleanup($userId = null) {
    $errors = [];
    
    try {
        // 1. Log de la actividad
        if ($userId) {
            if (function_exists('logActivity')) {
                try {
                    logActivity($userId, 'logout', 'auth');
                } catch (Exception $e) {
                    simpleLogActivity($userId, 'logout', 'auth');
                }
            } else {
                simpleLogActivity($userId, 'logout', 'auth');
            }
        }
        
        // 2. Limpiar sesión con el manager apropiado
        $sessionManager = getSessionManager();
        if ($sessionManager && method_exists($sessionManager, 'destroy')) {
            $sessionManager->destroy();
        }
        
        // 3. Limpieza adicional de sesión PHP
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = array();
        @session_destroy();
        
        // 4. Limpiar cookies
        clearAllSessionCookies();
        
        // 5. Headers de limpieza
        if (!headers_sent()) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
            header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\"");
        }
        
        return ['success' => true, 'errors' => $errors];
        
    } catch (Exception $e) {
        error_log("Error in complete logout cleanup: " . $e->getMessage());
        $errors[] = $e->getMessage();
        return ['success' => false, 'errors' => $errors];
    }
}

// Definir constantes si no existen
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistema de Servicio Social ITA');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getProjectBaseUrl());
}

// Log de inicialización
error_log("Logout config loaded - Base Path: " . getProjectBasePath() . " - Base URL: " . getProjectBaseUrl());
?>