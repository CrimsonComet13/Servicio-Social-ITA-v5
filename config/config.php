<?php
// Configuración principal del sistema
define('APP_NAME', 'Sistema de Servicio Social ITA');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true);

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'servicio_social_ita');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de rutas
define('BASE_PATH', __DIR__ . '/../');
define('BASE_URL', 'http://localhost/servicio_social_ita/');
define('UPLOAD_PATH', BASE_PATH . 'assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// Configuración de seguridad
define('SESSION_TIMEOUT', 3600); // 1 hora
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Configuración de archivos
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf']);

// Configuración de email
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@itaguascalientes.edu.mx');
define('FROM_NAME', 'Sistema de Servicio Social ITA');

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de errores
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>