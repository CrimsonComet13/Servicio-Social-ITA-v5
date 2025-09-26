<?php
// Evitar acceso directo
defined('APP_NAME') or die('Acceso restringido');

// Configurar variables básicas de usuario
if (!isset($session)) {
    if (class_exists('SecureSession')) {
        $session = SecureSession::getInstance();
    }
}

if (!isset($usuario) && isset($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
    $usuario = [
        'nombre' => method_exists($session, 'get') ? $session->get('nombre') : 'Usuario',
        'email' => method_exists($session, 'get') ? $session->get('email') : 'usuario@ita.mx',
        'avatar' => method_exists($session, 'get') ? $session->get('avatar') : null
    ];
}

// Funciones auxiliares simplificadas
function getSafeUserDisplayName($usuario, $maxLength = 20) {
    if (!is_array($usuario)) return 'Usuario';
    $name = $usuario['nombre'] ?? $usuario['email'] ?? 'Usuario';
    if (strlen($name) > $maxLength) {
        $name = substr($name, 0, $maxLength) . '...';
    }
    return htmlspecialchars($name);
}

function getSafeUserDisplayEmail($usuario) {
    if (!is_array($usuario)) return 'usuario@ita.mx';
    return htmlspecialchars($usuario['email'] ?? 'usuario@ita.mx');
}

function getUserRole() {
    global $session;
    if (isset($session) && method_exists($session, 'getUserRole')) {
        return $session->getUserRole();
    }
    return 'estudiante';
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    
    <!-- Meta tags básicos -->
    <meta name="description" content="Sistema de Gestión de Servicio Social - Instituto Tecnológico de Aguascalientes">
    <meta name="author" content="Instituto Tecnológico de Aguascalientes">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/logo-ita.png">
    
    <!-- CSS del Header -->
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #8b8cf7;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --bg-white: #ffffff;
            --bg-light: #f8fafc;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s ease;
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-light);
            overflow-x: hidden;
        }

        /* Header principal */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
            transition: var(--transition);
        }

        .header-container {
            height: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            background: var(--bg-light);
        }

        .hamburger-line {
            width: 24px;
            height: 2px;
            background: var(--text-primary);
            margin: 3px 0;
            transition: var(--transition);
            border-radius: 1px;
        }

        /* Brand */
        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .brand-logo img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .brand-info {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin: 0;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Notifications */
        .notification-dropdown {
            position: relative;
        }

        .notification-trigger {
            position: relative;
            width: 44px;
            height: 44px;
            background: none;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .notification-trigger:hover {
            background: var(--bg-light);
            color: var(--primary);
        }

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--error);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .notification-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 350px;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1001;
        }

        .notification-dropdown.active .notification-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0.5rem 0;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .notification-item:hover {
            background: var(--bg-light);
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.success { background: var(--success); }
        .notification-icon.info { background: var(--info); }
        .notification-icon.warning { background: var(--warning); }

        .notification-content h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .notification-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
            max-width: 200px;
        }

        .user-trigger:hover {
            background: var(--bg-light);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            text-align: left;
            min-width: 0;
        }

        .user-name {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-chevron {
            font-size: 0.75rem;
            color: var(--text-light);
            transition: var(--transition);
        }

        .user-dropdown.active .user-chevron {
            transform: rotate(180deg);
        }

        .user-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 280px;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1001;
        }

        .user-dropdown.active .user-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-menu-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            text-align: center;
        }

        .user-details {
            margin-top: 1rem;
        }

        .user-details h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .user-details p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .role-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
        }

        .user-menu-nav {
            padding: 0.5rem 0;
        }

        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 2rem;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .user-menu-item:hover {
            background: var(--bg-light);
        }

        .user-menu-item.logout {
            color: var(--error);
            border-top: 1px solid var(--border);
        }

        .user-menu-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .user-menu-item i {
            width: 16px;
            text-align: center;
        }

        /* Modal de logout */
        .logout-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            min-width: 400px;
            max-width: 90vw;
            padding: 2rem;
            text-align: center;
        }

        .logout-modal.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .logout-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1099;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .logout-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .logout-modal h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .logout-modal p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .logout-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-primary);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--bg-white);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error), #f87171);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Main Content */
        .main-content {
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
        }

        /* Flash Messages */
        .flash-message {
            position: fixed;
            top: calc(var(--header-height) + 1rem);
            right: 2rem;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 1rem 1.5rem;
            z-index: 1050;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
        }

        .flash-message.success {
            border-left: 4px solid var(--success);
        }

        .flash-message.error {
            border-left: 4px solid var(--error);
        }

        .flash-message.warning {
            border-left: 4px solid var(--warning);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .logged-in .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
                gap: 1rem;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .brand-info {
                display: none;
            }

            .user-info {
                display: none;
            }

            .notification-menu,
            .user-menu {
                width: 300px;
                max-width: calc(100vw - 2rem);
            }

            .logout-modal {
                min-width: 300px;
                margin: 1rem;
            }
        }

        @media (max-width: 480px) {
            .header-container {
                padding: 0 0.75rem;
                gap: 0.5rem;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
            }

            .brand-logo img {
                width: 24px;
                height: 24px;
            }

            .brand-title {
                font-size: 1.25rem;
            }

            .notification-menu,
            .user-menu {
                width: 280px;
            }

            .logout-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?= (isset($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) ? 'logged-in' : 'guest' ?>">
    
    <?php if (isset($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()): ?>
    <!-- Header para usuarios autenticados -->
    <header class="app-header">
        <div class="header-container">
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menú">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            
            <!-- Logo y Brand -->
            <div class="header-brand">
                <div class="brand-logo">
                    <img src="../assets/images/logo-ita.png" alt="Logo ITA">
                </div>
                <div class="brand-info">
                    <h1 class="brand-title">ITA Social</h1>
                    <span class="brand-subtitle">Sistema de Servicio Social</span>
                </div>
            </div>

            <!-- Acciones del header -->
            <div class="header-actions">
                <!-- Notifications -->
                <div class="notification-dropdown">
                    <button class="notification-trigger" id="notificationTrigger" aria-label="Notificaciones">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationBadge">3</span>
                    </button>
                    
                    <div class="notification-menu" id="notificationMenu">
                        <div class="notification-header">
                            <h3>Notificaciones</h3>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item">
                                <div class="notification-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>Reporte Aprobado</h4>
                                    <p>Tu reporte bimestral ha sido aprobado</p>
                                    <span class="notification-time">Hace 2 horas</span>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon info">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>Recordatorio</h4>
                                    <p>Próximo reporte vence en 5 días</p>
                                    <span class="notification-time">Hace 1 día</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Dropdown -->
                <div class="user-dropdown" id="userDropdown">
                    <button class="user-trigger" id="userTrigger" aria-label="Perfil de usuario">
                        <div class="user-avatar">
                            <?php if (isset($usuario['avatar']) && $usuario['avatar']): ?>
                                <img src="<?= UPLOAD_URL . $usuario['avatar'] ?>" alt="Avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= getSafeUserDisplayName($usuario) ?></span>
                            <span class="user-role"><?= ucfirst(str_replace('_', ' ', getUserRole())) ?></span>
                        </div>
                        <i class="fas fa-chevron-down user-chevron"></i>
                    </button>
                    
                    <div class="user-menu" id="userMenu">
                        <div class="user-menu-header">
                            <div class="user-avatar large" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <?php if (isset($usuario['avatar']) && $usuario['avatar']): ?>
                                    <img src="<?= UPLOAD_URL . $usuario['avatar'] ?>" alt="Avatar">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <h3><?= getSafeUserDisplayName($usuario, 30) ?></h3>
                                <p><?= getSafeUserDisplayEmail($usuario) ?></p>
                                <span class="role-badge"><?= ucfirst(str_replace('_', ' ', getUserRole())) ?></span>
                            </div>
                        </div>
                        
                        <nav class="user-menu-nav">
                            
                            <a href="../help.php" class="user-menu-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Ayuda</span>
                            </a>
                            <button type="button" onclick="showLogoutModal()" class="user-menu-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Cerrar Sesión</span>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Modal de Confirmación de Logout -->
    <div class="logout-modal" id="logoutModal">
        <h3>¿Cerrar Sesión?</h3>
        <p>¿Estás seguro de que deseas cerrar tu sesión?</p>
        <div class="logout-actions">
            <button type="button" class="btn btn-secondary" onclick="closeLogoutModal()">
                <i class="fas fa-times"></i>
                Cancelar
            </button>
            <button type="button" class="btn btn-danger" onclick="executeLogout()" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                Cerrar Sesión
            </button>
        </div>
    </div>
    
    <!-- Overlay del Modal -->
    <div class="logout-modal-overlay" id="logoutModalOverlay" onclick="closeLogoutModal()"></div>
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <?php if ($flash = getFlashMessage()): ?>
    <div class="flash-message <?= $flash['type'] ?>" id="flashMessage">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
            <button onclick="document.getElementById('flashMessage').remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">

<script>
// ================================
// SISTEMA DE LOGOUT SIMPLIFICADO
// ================================

// Variables globales
let logoutInProgress = false;

// Función para mostrar modal de logout
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    const overlay = document.getElementById('logoutModalOverlay');
    
    if (modal && overlay) {
        modal.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Cerrar dropdown de usuario
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown) {
            userDropdown.classList.remove('active');
        }
    }
}

// Función para cerrar modal de logout
function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    const overlay = document.getElementById('logoutModalOverlay');
    
    if (modal && overlay) {
        modal.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Función principal de logout simplificada
async function executeLogout() {
    if (logoutInProgress) return;
    
    logoutInProgress = true;
    const logoutBtn = document.getElementById('logoutBtn');
    
    try {
        // Mostrar estado de carga
        if (logoutBtn) {
            logoutBtn.disabled = true;
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cerrando...';
        }
        
        // Calcular URL de logout de manera simple
        const currentPath = window.location.pathname;
        const pathSegments = currentPath.split('/');
        
        // Remover archivo actual y llegar a la raíz del proyecto
        while (pathSegments.length > 0 && pathSegments[pathSegments.length - 1] !== 'servicio_social_ita') {
            pathSegments.pop();
        }
        
        if (pathSegments.length === 0 || pathSegments[pathSegments.length - 1] !== 'servicio_social_ita') {
            // Fallback: asumir que estamos en subcarpeta
            pathSegments.push('servicio_social_ita');
        }
        
        const baseUrl = window.location.origin + pathSegments.join('/') + '/';
        const logoutUrl = baseUrl + 'auth/logout.php';
        
        console.log('Ejecutando logout en:', logoutUrl);
        
        // Intentar logout AJAX
        try {
            const response = await fetch(logoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=ajax',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success) {
                        handleLogoutSuccess(result.redirect || baseUrl + 'index.php');
                        return;
                    }
                }
            }
        } catch (ajaxError) {
            console.warn('AJAX logout falló:', ajaxError);
        }
        
        // Fallback: redirección directa
        console.log('Usando fallback de redirección directa');
        clearLocalData();
        window.location.href = logoutUrl + '?action=force';
        
    } catch (error) {
        console.error('Error en logout:', error);
        handleLogoutError();
    }
}

// Manejar logout exitoso
function handleLogoutSuccess(redirectUrl) {
    console.log('Logout exitoso, redirigiendo a:', redirectUrl);
    
    clearLocalData();
    closeLogoutModal();
    
    // Mostrar mensaje temporal
    showTempMessage('Sesión cerrada exitosamente', 'success');
    
    // Redirigir después de un momento
    setTimeout(() => {
        window.location.href = redirectUrl;
    }, 1000);
}

// Manejar error en logout
function handleLogoutError() {
    console.error('Error en logout, usando método de emergencia');
    
    clearLocalData();
    
    // Calcular URL base para emergencia
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/');
    
    while (pathSegments.length > 0 && pathSegments[pathSegments.length - 1] !== 'servicio_social_ita') {
        pathSegments.pop();
    }
    
    if (pathSegments.length === 0) {
        pathSegments.push('servicio_social_ita');
    }
    
    const baseUrl = window.location.origin + pathSegments.join('/') + '/';
    
    // Redirección de emergencia
    window.location.href = baseUrl + 'auth/logout.php?action=emergency';
}

// Limpiar datos locales
function clearLocalData() {
    try {
        const keysToRemove = [
            'user_preferences', 'dashboard_cache', 'form_drafts',
            'auth_token', 'user_session', 'ita_social_session', 'remember_token'
        ];
        
        keysToRemove.forEach(key => {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.warn('Error removiendo', key, ':', e);
            }
        });
        
        try {
            sessionStorage.clear();
        } catch (e) {
            console.warn('Error limpiando sessionStorage:', e);
        }
        
        console.log('Datos locales limpiados');
    } catch (error) {
        console.error('Error limpiando datos locales:', error);
    }
}

// Mostrar mensaje temporal
function showTempMessage(message, type = 'info') {
    const messageEl = document.createElement('div');
    messageEl.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        font-weight: 500;
    `;
    messageEl.textContent = message;
    
    document.body.appendChild(messageEl);
    
    setTimeout(() => {
        if (messageEl.parentNode) {
            messageEl.parentNode.removeChild(messageEl);
        }
    }, 3000);
}

// Event listeners cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.app-sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('mobile-menu-open');
        });
    }
    
    // Toggle de dropdown de notificaciones
    const notificationTrigger = document.getElementById('notificationTrigger');
    const notificationDropdown = notificationTrigger?.parentElement;
    
    if (notificationTrigger) {
        notificationTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('active');
            // Cerrar user menu si está abierto
            document.querySelector('.user-dropdown')?.classList.remove('active');
        });
    }
    
    // Toggle de dropdown de usuario
    const userTrigger = document.getElementById('userTrigger');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userTrigger && userDropdown) {
        userTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
            // Cerrar notification menu si está abierto
            document.querySelector('.notification-dropdown')?.classList.remove('active');
        });
    }
    
    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', function() {
        if (userDropdown) {
            userDropdown.classList.remove('active');
        }
        document.querySelector('.notification-dropdown')?.classList.remove('active');
    });
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogoutModal();
        }
    });
    
    // Auto-hide flash messages
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.remove();
        }, 5000);
    }
    
    // Header scroll effect
    let lastScrollY = window.scrollY;
    const header = document.querySelector('.app-header');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 10) {
            header?.classList.add('scrolled');
        } else {
            header?.classList.remove('scrolled');
        }
        lastScrollY = window.scrollY;
    });
    
    console.log('Sistema de logout simplificado iniciado');
});

// Función de emergencia accesible globalmente
window.emergencyLogout = function() {
    clearLocalData();
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/');
    
    while (pathSegments.length > 0 && pathSegments[pathSegments.length - 1] !== 'servicio_social_ita') {
        pathSegments.pop();
    }
    
    if (pathSegments.length === 0) {
        pathSegments.push('servicio_social_ita');
    }
    
    const baseUrl = window.location.origin + pathSegments.join('/') + '/';
    window.location.href = baseUrl + 'auth/logout.php?action=emergency';
};

// Prevenir múltiples llamadas de logout
const originalExecuteLogout = window.executeLogout;
window.executeLogout = function() {
    if (logoutInProgress) {
        console.log('Logout ya en progreso');
        return;
    }
    return originalExecuteLogout();
};

console.log('Sistema de logout cargado correctamente');
</script>