<?php
// logout.php - Página de cierre de sesión mejorada

// Iniciar sesión para acceder a las variables de sesión
session_start();

// Función para registrar actividades (si no existe en otro archivo)
function logActivity($userId, $action, $module) {
    // Conectar a la base de datos
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Verificar conexión
    if ($conn->connect_error) {
        error_log("Error de conexión: " . $conn->connect_error);
        return false;
    }
    
    // Preparar la consulta
    $stmt = $conn->prepare("INSERT INTO user_activities (user_id, action, module, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt === false) {
        error_log("Error preparando consulta: " . $conn->error);
        $conn->close();
        return false;
    }
    
    // Obtener dirección IP y user agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Ejecutar la consulta
    $stmt->bind_param("issss", $userId, $action, $module, $ipAddress, $userAgent);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error registrando actividad: " . $stmt->error);
    }
    
    // Cerrar conexiones
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Función para mostrar mensajes flash (si no existe)
function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Destruir completamente la sesión
session_unset();    // Eliminar todas las variables de sesión
session_destroy();  // Destruir la sesión

// También eliminar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al login con mensaje
flashMessage('Sesión cerrada correctamente', 'success');
header('Location: ../auth/login.php');
exit();
?>