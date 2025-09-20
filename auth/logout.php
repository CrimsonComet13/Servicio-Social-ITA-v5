<?php
/**
 * Logout Completo - Versión mejorada que funciona correctamente
 * Maneja logout normal, forzado, AJAX y redirecciones
 */

require_once '../config/config.php';
require_once '../config/session.php';

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpiar cualquier output buffer existente
while (ob_get_level()) {
    ob_end_clean();
}

// Función para detectar AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Función para enviar JSON limpio
function sendCleanJson($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para calcular URLs absolutas
function getAbsoluteUrls() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Calcular el directorio base del proyecto
    $scriptPath = $_SERVER['SCRIPT_NAME']; // /ruta/auth/logout.php
    $basePath = dirname(dirname($scriptPath)); // /ruta
    $baseUrl = $protocol . $host . $basePath . '/';
    
    return [
        'base' => $baseUrl,
        'index' => $baseUrl . 'index.php',
        'login' => $baseUrl . 'auth/login.php'
    ];
}

// Función de logout completa que funciona con SecureSession
function performCompleteLogout() {
    try {
        error_log("Iniciando logout completo para IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // 1. Obtener instancia de SecureSession y destruir
        $session = SecureSession::getInstance();
        
        if ($session->isLoggedIn()) {
            $userRole = $session->getUserRole();
            error_log("Cerrando sesión para usuario con rol: $userRole");
            $session->destroy();
            error_log("SecureSession destruida exitosamente");
        } else {
            error_log("SecureSession ya estaba inactiva");
        }
        
        // 2. Limpiar sesión PHP nativa como respaldo
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Guardar ID de sesión para logging
        $oldSessionId = session_id();
        
        // Limpiar todas las variables de sesión
        $_SESSION = array();
        
        // Destruir cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir sesión PHP
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            error_log("Sesión PHP destruida: $oldSessionId");
        }
        
        // 3. Limpiar cookies adicionales del sistema
        $cookiesToClear = [
            'remember_token', 
            'user_session', 
            'ita_social_session',
            'auth_token',
            'user_preferences'
        ];
        
        foreach ($cookiesToClear as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                // Limpiar para diferentes paths posibles
                setcookie($cookie, '', time() - 3600, '/');
                setcookie($cookie, '', time() - 3600, '/dashboard/');
                setcookie($cookie, '', time() - 3600, '/auth/');
                setcookie($cookie, '', time() - 3600, '/modules/');
                error_log("Cookie limpiada: $cookie");
            }
        }
        
        error_log("Logout completado exitosamente");
        return true;
        
    } catch (Exception $e) {
        error_log("Error en performCompleteLogout: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// PROCESAMIENTO PRINCIPAL
try {
    // Obtener URLs absolutas
    $urls = getAbsoluteUrls();
    
    // Procesar peticiones POST (AJAX)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'confirm_logout') {
            error_log("Procesando logout AJAX confirmado");
            
            $logoutResult = performCompleteLogout();
            
            if ($logoutResult) {
                sendCleanJson([
                    'success' => true,
                    'message' => 'Sesión cerrada correctamente',
                    'redirect' => $urls['index'],
                    'timestamp' => time(),
                    'debug' => [
                        'method' => 'ajax_post',
                        'base_url' => $urls['base']
                    ]
                ]);
            } else {
                sendCleanJson([
                    'success' => false,
                    'message' => 'Error durante el logout, pero sesión limpiada',
                    'redirect' => $urls['index'],
                    'timestamp' => time()
                ]);
            }
        } else {
            sendCleanJson([
                'success' => false,
                'message' => 'Acción no válida',
                'redirect' => $urls['index']
            ]);
        }
    }
    
    // Procesar peticiones GET
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'check':
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'logged_in' => false,
                        'message' => 'Verificación de estado de logout',
                        'timestamp' => time(),
                        'urls' => $urls
                    ]);
                } else {
                    header("Location: " . $urls['index'] . "?logout=check");
                    exit;
                }
                break;
                
            case 'force':
            case 'immediate':
                error_log("Procesando logout forzado/inmediato");
                performCompleteLogout();
                
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'success' => true,
                        'message' => 'Logout forzado completado',
                        'redirect' => $urls['index']
                    ]);
                } else {
                    header("Location: " . $urls['index'] . "?logout=forced");
                    exit;
                }
                break;
                
            case 'test':
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'success' => true,
                        'message' => 'Test de logout funcionando correctamente',
                        'server_info' => [
                            'php_version' => PHP_VERSION,
                            'timestamp' => date('Y-m-d H:i:s'),
                            'headers_sent' => headers_sent(),
                            'session_status' => session_status(),
                            'base_url' => $urls['base']
                        ],
                        'urls' => $urls
                    ]);
                } else {
                    header("Location: " . $urls['index'] . "?logout=test");
                    exit;
                }
                break;
                
            case 'emergency':
                error_log("Procesando logout de emergencia");
                
                // Logout de emergencia más agresivo
                try {
                    $session = SecureSession::getInstance();
                    $session->destroy();
                } catch (Exception $e) {
                    error_log("Error en emergency logout SecureSession: " . $e->getMessage());
                }
                
                // Destruir sesión PHP sin importar el estado
                @session_start();
                $_SESSION = array();
                @session_destroy();
                
                // Limpiar todas las cookies posibles
                if (isset($_SERVER['HTTP_COOKIE'])) {
                    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
                    foreach($cookies as $cookie) {
                        $parts = explode('=', $cookie);
                        $name = trim($parts[0]);
                        setcookie($name, '', time() - 3600, '/');
                    }
                }
                
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'success' => true,
                        'message' => 'Logout de emergencia completado',
                        'redirect' => $urls['index']
                    ]);
                } else {
                    header("Location: " . $urls['index'] . "?logout=emergency");
                    exit;
                }
                break;
        }
    }
    
    // Si llegamos aquí sin salir, hacer logout normal
    error_log("Ejecutando logout normal (GET sin acción específica)");
    performCompleteLogout();
    
    // Redirigir a index.php
    header("Location: " . $urls['index'] . "?logout=success");
    exit;
    
} catch (Exception $e) {
    error_log("Error crítico en logout: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isAjaxRequest()) {
        sendCleanJson([
            'success' => false,
            'message' => 'Error crítico en logout, redirigiendo a página principal',
            'redirect' => $urls['index'] ?? '/index.php',
            'error' => $e->getMessage()
        ]);
    } else {
        // Logout de emergencia
        try {
            @session_start();
            $_SESSION = array();
            @session_destroy();
        } catch (Exception $sessionError) {
            error_log("Error en emergency session cleanup: " . $sessionError->getMessage());
        }
        
        $urls = getAbsoluteUrls();
        header('Location: ' . $urls['index'] . '?logout=error');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrar Sesión - ITA Social</title>
    <meta http-equiv="refresh" content="3;url=<?= $urls['index'] ?? '/index.php' ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        p {
            color: #6b7280;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
            margin: 0 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        
        .redirect-info {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #9ca3af;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .loading-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 8px;
        }
        
        .loading-dots span {
            width: 4px;
            height: 4px;
            background: #9ca3af;
            border-radius: 50%;
            animation: loadingDots 1.4s infinite ease-in-out both;
        }
        
        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes loadingDots {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 2rem;
                margin: 1rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                display: block;
                margin: 0.5rem 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✓</div>
        <h1>Sesión Cerrada Exitosamente</h1>
        <p>Tu sesión se ha cerrado correctamente. Gracias por usar el sistema de servicio social del ITA.</p>
        
        <a href="<?= $urls['index'] ?? '/index.php' ?>" class="btn">
            <i class="fas fa-home"></i>
            Ir a Página Principal
        </a>
        <a href="<?= $urls['login'] ?? '/auth/login.php' ?>" class="btn btn-secondary">
            <i class="fas fa-sign-in-alt"></i>
            Iniciar Sesión
        </a>
        
        <div class="redirect-info">
            <div style="display: flex; align-items: center; justify-content: center;">
                <span>Redirigiendo automáticamente en 3 segundos</span>
                <div class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Contador de redirección
        let countdown = 3;
        const redirectInfo = document.querySelector('.redirect-info span');
        
        const updateCountdown = () => {
            if (countdown > 0) {
                redirectInfo.textContent = `Redirigiendo automáticamente en ${countdown} segundo${countdown !== 1 ? 's' : ''}`;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                redirectInfo.textContent = 'Redirigiendo ahora...';
                window.location.href = '<?= $urls["index"] ?? "/index.php" ?>';
            }
        };
        
        // Iniciar countdown después de 1 segundo
        setTimeout(updateCountdown, 1000);
        
        // Redirección de seguridad después de 5 segundos
        setTimeout(function() {
            window.location.href = '<?= $urls["index"] ?? "/index.php" ?>';
        }, 5000);
        
        // Limpiar cualquier dato local del navegador
        try {
            // Limpiar datos específicos del sistema
            const keysToRemove = [
                'user_preferences',
                'dashboard_cache',
                'form_drafts',
                'auth_token',
                'user_session',
                'ita_social_session',
                'remember_token'
            ];
            
            keysToRemove.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch(e) {
                    console.warn('Error removing localStorage key:', key, e);
                }
            });
            
            // Limpiar sessionStorage
            sessionStorage.clear();
            
            console.log('Datos locales limpiados exitosamente');
        } catch(e) {
            console.warn('Error limpiando storage:', e);
        }
        
        // Prevenir uso del botón de retroceso
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function () {
            window.history.go(1);
        };
    </script>
</body>
</html>