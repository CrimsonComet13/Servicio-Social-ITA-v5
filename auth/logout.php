<?php
/**
 * Sistema de Logout Simplificado y Robusto
 * Versión mejorada para SERVICIO_SOCIAL_ITA
 */

// Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpiar cualquier output buffer existente
while (ob_get_level()) {
    ob_end_clean();
}

// Incluir configuración específica de logout
require_once __DIR__ . '/logout-config.php';

// Función principal de logout usando las utilidades de configuración
function executeLogout() {
    // Obtener sesión manager apropiado
    $sessionManager = getSessionManager();
    $userId = null;
    
    // Obtener ID de usuario para logging
    if ($sessionManager && method_exists($sessionManager, 'isLoggedIn') && $sessionManager->isLoggedIn()) {
        $userId = method_exists($sessionManager, 'getUserId') ? $sessionManager->getUserId() : null;
    }
    
    // Ejecutar limpieza completa usando las funciones de configuración
    $result = performCompleteLogoutCleanup($userId);
    
    return $result;
}

// ============================================
// PROCESAMIENTO PRINCIPAL
// ============================================

try {
    // Cargar configuraciones del proyecto
    $configLoaded = loadProjectConfig();
    if (!$configLoaded) {
        error_log("ADVERTENCIA: No se pudieron cargar las configuraciones del proyecto");
    }
    
    // Obtener acción
    $action = $_GET['action'] ?? $_POST['action'] ?? 'logout';
    
    // Log del intento de logout
    error_log("Logout attempt - Action: $action, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Procesar según la acción
    switch ($action) {
        case 'check':
            // Para verificaciones AJAX
            $sessionManager = getSessionManager();
            $loggedIn = false;
            
            if ($sessionManager && method_exists($sessionManager, 'isLoggedIn')) {
                $loggedIn = $sessionManager->isLoggedIn();
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'logged_in' => $loggedIn,
                'message' => 'Status checked'
            ]);
            exit;
            
        case 'ajax':
            // Para logout AJAX
            $result = executeLogout();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Logout successful' : 'Logout with errors',
                'errors' => $result['errors'],
                'redirect' => getProjectBaseUrl() . 'index.php?logout=success'
            ]);
            exit;
            
        case 'force':
        case 'emergency':
            // Logout agresivo usando la función de configuración
            $result = performCompleteLogoutCleanup();
            logoutRedirect('forced');
            break;
            
        default:
            // Logout estándar
            $result = executeLogout();
            
            if ($result['success']) {
                logoutRedirect('success');
            } else {
                logoutRedirect('error');
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("Error crítico en logout.php: " . $e->getMessage());
    
    // Limpieza de emergencia usando función de configuración
    try {
        performCompleteLogoutCleanup();
    } catch (Exception $cleanupError) {
        error_log("Error en limpieza de emergencia: " . $cleanupError->getMessage());
    }
    
    // Redirección de emergencia
    logoutRedirect('error');
}

// Fallback final usando función de configuración
error_log("ADVERTENCIA: Llegó al final de logout.php sin redirección apropiada");
logoutRedirect('fallback');
?>