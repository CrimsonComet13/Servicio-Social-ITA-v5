<?php
/**
 * Logout Standalone - Sin dependencias externas
 * Versión aislada para resolver problemas de JSON
 */

// Configuración crítica
error_reporting(0);
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
    // Asegurar que no hay headers enviados
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función de logout básica
function performBasicLogout() {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Limpiar variables de sesión
    $_SESSION = array();
    
    // Destruir cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sesión
    session_destroy();
    
    // Limpiar cookies adicionales
    $cookiesToClear = ['remember_token', 'user_session', 'ita_social_session'];
    foreach ($cookiesToClear as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', time() - 3600, '/');
        }
    }
    
    return true;
}

// PROCESAMIENTO PRINCIPAL
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
        // Petición AJAX POST
        $action = $_POST['action'] ?? '';
        
        if ($action === 'confirm_logout') {
            $logoutResult = performBasicLogout();
            
            sendCleanJson([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
                'redirect' => '../auth/login.php',
                'timestamp' => time()
            ]);
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'check':
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'logged_in' => false,
                        'message' => 'Verificación desde logout standalone',
                        'timestamp' => time()
                    ]);
                }
                break;
                
            case 'force':
            case 'immediate':
                performBasicLogout();
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'success' => true,
                        'message' => 'Logout forzado completado',
                        'redirect' => '../auth/login.php'
                    ]);
                } else {
                    header('Location: ../auth/login.php?forced=1');
                    exit;
                }
                break;
                
            case 'test':
                if (isAjaxRequest()) {
                    sendCleanJson([
                        'success' => true,
                        'message' => 'Test JSON funcionando correctamente',
                        'server_info' => [
                            'php_version' => PHP_VERSION,
                            'timestamp' => date('Y-m-d H:i:s'),
                            'headers_sent' => headers_sent()
                        ]
                    ]);
                }
                break;
        }
    }
    
    // Si llegamos aquí sin salir, mostrar página de logout normal
    performBasicLogout();
    
} catch (Exception $e) {
    if (isAjaxRequest()) {
        sendCleanJson([
            'success' => false,
            'message' => 'Error en logout: ' . $e->getMessage(),
            'redirect' => '../auth/login.php'
        ]);
    } else {
        // Logout de emergencia
        session_start();
        session_destroy();
        header('Location: ../auth/login.php?error=1');
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
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
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
            padding: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
        }
        p {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✓</div>
        <h1>Sesión Cerrada</h1>
        <p>Tu sesión se ha cerrado correctamente. Gracias por usar el sistema de servicio social del ITA.</p>
        <a href="../auth/login.php" class="btn">Iniciar Sesión</a>
    </div>
</body>
</html>