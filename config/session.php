<?php
require_once 'config.php';
require_once 'database.php';

// Configuración de sesiones seguras
class SecureSession {
    private static $instance = null;
    
    private function __construct() {
        // Configurar parámetros de sesión seguros
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => false, // Cambiar a true en producción
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_name('ITA_SERVICIO_SOCIAL');
        session_start();
        
        // Regenerar ID de sesión periódicamente para prevenir fixation
        if (!isset($_SESSION['canary'])) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }
        
        if ($_SESSION['canary'] < time() - 300) {
            session_regenerate_id(true);
            $_SESSION['canary'] = time();
        }
        
        // Verificar timeout de sesión
        if (isset($_SESSION['LAST_ACTIVITY']) && 
            (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            session_start();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    public function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    public function destroy() {
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', 0, '/');
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['usuario']) && !empty($_SESSION['usuario']);
    }
    
    public function getUser() {
        return $this->get('usuario');
    }
    
    public function getUserRole() {
        $user = $this->getUser();
        return $user ? $user['tipo_usuario'] : null;
    }
    
    public function checkRole($requiredRole) {
        $userRole = $this->getUserRole();
        return $userRole === $requiredRole;
    }
    
    public function requireRole($requiredRole, $redirectUrl = '/auth/login.php') {
        if (!$this->isLoggedIn() || !$this->checkRole($requiredRole)) {
            if ($this->isLoggedIn()) {
                flashMessage('No tiene permisos para acceder a esta sección', 'error');
            } else {
                flashMessage('Debe iniciar sesión para acceder a esta sección', 'warning');
            }
            redirectTo($redirectUrl);
        }
    }
    
    public function requireLogin($redirectUrl = '/auth/login.php') {
        if (!$this->isLoggedIn()) {
            flashMessage('Debe iniciar sesión para acceder a esta sección', 'warning');
            redirectTo($redirectUrl);
        }
    }
}

// Inicializar la sesión segura
$session = SecureSession::getInstance();

// Función para verificar intentos de login y prevenir fuerza bruta
function checkLoginAttempts($email) {
    $db = Database::getInstance();
    $ip = getClientIP();
    $now = time();
    
    // Limpiar intentos antiguos
    $db->query("DELETE FROM login_attempts WHERE timestamp < :old_time", 
               ['old_time' => date('Y-m-d H:i:s', $now - LOCKOUT_TIME)]);
    
    // Contar intentos recientes
    $attempts = $db->fetch("SELECT COUNT(*) as count FROM login_attempts 
                           WHERE ip_address = :ip OR email = :email", 
                           ['ip' => $ip, 'email' => $email]);
    
    return $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt($email, $success = false) {
    $db = Database::getInstance();
    $ip = getClientIP();
    
    if ($success) {
        // Limpiar intentos anteriores en éxito
        $db->query("DELETE FROM login_attempts WHERE ip_address = :ip OR email = :email", 
                   ['ip' => $ip, 'email' => $email]);
    } else {
        // Registrar intento fallido
        $db->insert('login_attempts', [
            'email' => $email,
            'ip_address' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Crear tabla para intentos de login si no existe
function createLoginAttemptsTable() {
    $db = Database::getInstance();
    
    $db->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            timestamp DATETIME NOT NULL,
            INDEX idx_email (email),
            INDEX idx_ip (ip_address),
            INDEX idx_timestamp (timestamp)
        )
    ");
}

// Ejecutar la creación de la tabla al incluir este archivo
createLoginAttemptsTable();
?>