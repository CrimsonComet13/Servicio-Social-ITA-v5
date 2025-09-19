<?php
// Evitar acceso directo
defined('APP_NAME') or die('Acceso restringido');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    
    <!-- Meta tags -->
    <meta name="description" content="Sistema de Gestión de Servicio Social - Instituto Tecnológico de Aguascalientes">
    <meta name="keywords" content="ITA, servicio social, estudiantes, laboratorio, departamento">
    <meta name="author" content="Instituto Tecnológico de Aguascalientes">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/logo-ita.png">
    
    <!-- PWA Meta -->
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body class="<?= isset($session) && $session->isLoggedIn() ? 'logged-in' : 'guest' ?>">
    <?php if (isset($session) && $session->isLoggedIn()): ?>
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
            
            <!-- Search Bar (opcional, para futuras funcionalidades) -->
            <div class="header-search">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Buscar..." class="search-input" id="globalSearch">
                </div>
            </div>
            
            <!-- Notifications y User -->
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
                            <button class="mark-all-read">Marcar todas como leídas</button>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
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
                        <div class="notification-footer">
                            <a href="../modules/<?= $session->getUserRole() ?>/notificaciones.php" class="view-all-notifications">
                                Ver todas las notificaciones
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- User Dropdown -->
                <div class="user-dropdown">
                    <button class="user-trigger" id="userTrigger" aria-label="Perfil de usuario">
                        <div class="user-avatar">
                            <?php if (isset($usuario['avatar']) && $usuario['avatar']): ?>
                                <img src="<?= UPLOAD_URL . $usuario['avatar'] ?>" alt="Avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($usuario['nombre'] ?? $usuario['email']) ?></span>
                            <span class="user-role"><?= ucfirst(str_replace('_', ' ', $session->getUserRole())) ?></span>
                        </div>
                        <i class="fas fa-chevron-down user-chevron"></i>
                    </button>
                    
                    <div class="user-menu" id="userMenu">
                        <div class="user-menu-header">
                            <div class="user-avatar large">
                                <?php if (isset($usuario['avatar']) && $usuario['avatar']): ?>
                                    <img src="<?= UPLOAD_URL . $usuario['avatar'] ?>" alt="Avatar">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <h3><?= htmlspecialchars($usuario['nombre'] ?? $usuario['email']) ?></h3>
                                <p><?= htmlspecialchars($usuario['email']) ?></p>
                                <span class="role-badge"><?= ucfirst(str_replace('_', ' ', $session->getUserRole())) ?></span>
                            </div>
                        </div>
                        
                        <nav class="user-menu-nav">
                            <a href="../dashboard/<?= $session->getUserRole() ?>.php" class="user-menu-item">
                                <i class="fas fa-home"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="../modules/<?= $session->getUserRole() ?>/perfil.php" class="user-menu-item">
                                <i class="fas fa-user-cog"></i>
                                <span>Mi Perfil</span>
                            </a>
                            <a href="../modules/<?= $session->getUserRole() ?>/configuracion.php" class="user-menu-item">
                                <i class="fas fa-cog"></i>
                                <span>Configuración</span>
                            </a>
                            <div class="user-menu-divider"></div>
                            <a href="../help.php" class="user-menu-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Ayuda</span>
                            </a>
                            <!-- LOGOUT MEJORADO CON AJAX -->
                            <button type="button" onclick="performSecureLogout()" class="user-menu-item logout" id="logoutButton">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Cerrar Sesión</span>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Overlay for mobile -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <!-- Modal de Confirmación de Logout -->
    <div class="logout-modal" id="logoutModal">
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h3>¿Cerrar Sesión?</h3>
                <p>¿Estás seguro de que deseas cerrar tu sesión?</p>
            </div>
            <div class="logout-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeLogoutModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmLogout()" id="confirmLogoutBtn">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </button>
            </div>
        </div>
    </div>
    
    <!-- Overlay del Modal -->
    <div class="logout-modal-overlay" id="logoutModalOverlay"></div>
    <?php endif; ?>
    
    <main class="main-content">
        
        <style>
        :root {
            --primary: #6366f1;
            --primary-light: #8b8cf7;
            --primary-dark: #4f46e5;
            --secondary: #1f2937;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #3b82f6;
            --bg-dark: #0f1419;
            --bg-darker: #1a202c;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --header-height: 80px;
            --sidebar-width: 280px;
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

        /* Header Styles */
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

        .mobile-menu-toggle.active .hamburger-line:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .mobile-menu-toggle.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-toggle.active .hamburger-line:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
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

        /* Search */
        .header-search {
            flex: 1;
            max-width: 400px;
            margin: 0 2rem;
        }

        .search-container {
            position: relative;
            width: 100%;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.95rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--bg-white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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

        .mark-all-read {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: var(--transition);
        }

        .mark-all-read:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--bg-light);
        }

        .notification-item.unread {
            background: rgba(99, 102, 241, 0.05);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary);
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

        .notification-icon.success {
            background: var(--success);
        }

        .notification-icon.info {
            background: var(--info);
        }

        .notification-icon.warning {
            background: var(--warning);
        }

        .notification-icon.error {
            background: var(--error);
        }

        .notification-content {
            flex: 1;
        }

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

        .notification-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .view-all-notifications {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .view-all-notifications:hover {
            text-decoration: underline;
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

        .user-avatar.large {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
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
        }

        .user-menu-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .user-menu-item i {
            width: 16px;
            text-align: center;
        }

        .user-menu-divider {
            height: 1px;
            background: var(--border);
            margin: 0.5rem 0;
        }

        /* Mobile Overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .mobile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* LOGOUT MODAL STYLES */
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
            background: rgba(0, 0, 0, 0.7);
            z-index: 1099;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .logout-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .logout-modal-content {
            padding: 2rem;
        }

        .logout-modal-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--error), #f87171);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem auto;
            animation: pulse 2s infinite;
        }

        .logout-modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .logout-modal-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .logout-modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        /* Loading state */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1;
        }

        .loading span {
            opacity: 0;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Main Content */
        .main-content {
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
        }

        .logged-in .main-content {
            margin-left: var(--sidebar-width);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .header-search {
                display: none;
            }
            
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

            .logout-modal-content {
                padding: 1.5rem;
            }

            .logout-modal-actions {
                flex-direction: column;
            }
        }

        /* Scrollbar personalizado */
        .notification-list::-webkit-scrollbar,
        .user-menu::-webkit-scrollbar {
            width: 6px;
        }

        .notification-list::-webkit-scrollbar-track,
        .user-menu::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        .notification-list::-webkit-scrollbar-thumb,
        .user-menu::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .notification-list::-webkit-scrollbar-thumb:hover,
        .user-menu::-webkit-scrollbar-thumb:hover {
            background: var(--text-light);
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.app-sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                    sidebar.classList.toggle('mobile-open');
                    mobileOverlay.classList.toggle('active');
                    document.body.classList.toggle('mobile-menu-open');
                });
            }
            
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    mobileMenuToggle.classList.remove('active');
                    sidebar.classList.remove('mobile-open');
                    this.classList.remove('active');
                    document.body.classList.remove('mobile-menu-open');
                });
            }
            
            // Notification dropdown
            const notificationTrigger = document.getElementById('notificationTrigger');
            const notificationDropdown = notificationTrigger?.parentElement;
            
            if (notificationTrigger) {
                notificationTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');
                    // Close user menu if open
                    document.querySelector('.user-dropdown')?.classList.remove('active');
                });
            }
            
            // User dropdown
            const userTrigger = document.getElementById('userTrigger');
            const userDropdown = userTrigger?.parentElement;
            
            if (userTrigger) {
                userTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    // Close notification menu if open
                    document.querySelector('.notification-dropdown')?.classList.remove('active');
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function() {
                document.querySelector('.notification-dropdown')?.classList.remove('active');
                document.querySelector('.user-dropdown')?.classList.remove('active');
            });
            
            // Mark all notifications as read
            const markAllReadBtn = document.querySelector('.mark-all-read');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    const unreadItems = document.querySelectorAll('.notification-item.unread');
                    unreadItems.forEach(item => item.classList.remove('unread'));
                    
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                });
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
            
            // Search functionality (placeholder)
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const query = e.target.value;
                    // Implementar búsqueda global aquí
                    console.log('Búsqueda:', query);
                });
            }
        });

        // FUNCIONES DE LOGOUT MEJORADAS

        /**
         * Función principal para iniciar el logout
         */
        function performSecureLogout() {
            console.log('Iniciando logout seguro...');
            
            // Cerrar menú de usuario
            document.querySelector('.user-dropdown')?.classList.remove('active');
            
            // Mostrar modal de confirmación
            showLogoutModal();
        }

        /**
         * Mostrar modal de confirmación
         */
        function showLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutModalOverlay');
            
            if (modal && overlay) {
                modal.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Focus en el botón de cancelar para accesibilidad
                setTimeout(() => {
                    const cancelBtn = modal.querySelector('.btn-secondary');
                    cancelBtn?.focus();
                }, 100);
            }
        }

        /**
         * Cerrar modal de confirmación
         */
        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutModalOverlay');
            
            if (modal && overlay) {
                modal.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        /**
         * Confirmar y ejecutar logout
         */
        async function confirmLogout() {
            const confirmBtn = document.getElementById('confirmLogoutBtn');
            
            try {
                // Mostrar estado de carga
                if (confirmBtn) {
                    confirmBtn.classList.add('loading');
                    confirmBtn.disabled = true;
                }

                console.log('Ejecutando logout...');

                // Preparar datos para la petición
                const formData = new FormData();
                formData.append('action', 'confirm_logout');

                // Realizar petición AJAX
                const response = await fetch('../auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin',
                    cache: 'no-cache'
                });

                console.log('Response status:', response.status);
                
                // Verificar el Content-Type
                const contentType = response.headers.get('content-type');
                console.log('Content-Type:', contentType);

                if (!contentType || !contentType.includes('application/json')) {
                    // Si no es JSON, obtener el texto para debugging
                    const textResponse = await response.text();
                    console.error('Respuesta no es JSON:', textResponse.substring(0, 500));
                    
                    // Intentar logout forzado
                    throw new Error('El servidor no respondió con JSON válido. Intentando logout forzado...');
                }

                if (response.ok) {
                    const data = await response.json();
                    console.log('Logout response:', data);

                    if (data.success) {
                        // Logout exitoso
                        await handleLogoutSuccess(data);
                    } else {
                        throw new Error(data.message || 'Error en el logout');
                    }
                } else {
                    throw new Error(`Error del servidor: ${response.status}`);
                }

            } catch (error) {
                console.error('Error en logout:', error);
                await handleLogoutError(error);
            }
        }

        /**
         * Manejar logout exitoso
         */
        async function handleLogoutSuccess(data) {
            console.log('Logout exitoso:', data.message);
            
            // Cerrar modal
            closeLogoutModal();
            
            // Limpiar datos locales
            await clearLocalData();
            
            // Mostrar mensaje temporal
            showLogoutSuccessMessage(data.message);
            
            // Redirigir después de un momento
            setTimeout(() => {
                window.location.href = data.redirect || '../auth/login.php';
            }, 1500);
        }

        /**
         * Manejar errores de logout
         */
        async function handleLogoutError(error) {
            console.error('Error manejando logout:', error);
            
            // Resetear botón
            const confirmBtn = document.getElementById('confirmLogoutBtn');
            if (confirmBtn) {
                confirmBtn.classList.remove('loading');
                confirmBtn.disabled = false;
            }
            
            // Mostrar error en el modal
            showLogoutError(error.message);
            
            // Ofrecer logout forzado después de 3 segundos
            setTimeout(() => {
                addForceLogoutOption();
            }, 3000);
        }

        /**
         * Mostrar mensaje de éxito
         */
        function showLogoutSuccessMessage(message) {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.innerHTML = `
                    <div class="logout-modal-content">
                        <div class="logout-modal-header">
                            <div class="logout-icon" style="background: linear-gradient(135deg, var(--success), #34d399);">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3>¡Sesión Cerrada!</h3>
                            <p>${message || 'Tu sesión se ha cerrado correctamente'}</p>
                        </div>
                        <div class="logout-modal-actions">
                            <div style="display: flex; align-items: center; gap: 1rem; color: var(--text-secondary);">
                                <div class="loading" style="width: 20px; height: 20px;"></div>
                                <span>Redirigiendo...</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        /**
         * Mostrar error en el modal
         */
        function showLogoutError(message) {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                const content = modal.querySelector('.logout-modal-header p');
                if (content) {
                    content.innerHTML = `
                        <div style="color: var(--error); font-size: 0.9rem; margin-top: 1rem;">
                            <strong>Error:</strong> ${message}
                        </div>
                    `;
                }
            }
        }

        /**
         * Agregar opción de logout forzado
         */
        function addForceLogoutOption() {
            const actions = document.querySelector('.logout-modal-actions');
            if (actions && !actions.querySelector('.btn-warning')) {
                const forceBtn = document.createElement('button');
                forceBtn.className = 'btn btn-warning';
                forceBtn.innerHTML = '<i class="fas fa-power-off"></i> Forzar Cierre';
                forceBtn.onclick = forceLogout;
                
                actions.appendChild(forceBtn);
            }
        }

        /**
         * Logout forzado
         */
        async function forceLogout() {
            console.log('Ejecutando logout forzado...');
            
            // Cerrar modal
            closeLogoutModal();
            
            // Limpiar datos locales
            await clearLocalData();
            
            // Mostrar mensaje
            showLogoutSuccessMessage('Cerrando sesión de forma forzada...');
            
            // Intentar múltiples endpoints
            const cleanupPromises = [
                fetch('../auth/logout.php?action=force', { method: 'GET' }).catch(() => {}),
                fetch('../auth/logout.php?action=immediate', { method: 'GET' }).catch(() => {})
            ];
            
            // Esperar máximo 2 segundos
            Promise.race([
                Promise.allSettled(cleanupPromises),
                new Promise(resolve => setTimeout(resolve, 2000))
            ]).finally(() => {
                // Redirigir independientemente del resultado
                window.location.href = '../auth/login.php?forced=1';
            });
        }

        /**
         * Limpiar datos locales del navegador
         */
        async function clearLocalData() {
            try {
                console.log('Limpiando datos locales...');
                
                // Limpiar localStorage
                const keysToRemove = [
                    'user_preferences',
                    'dashboard_cache',
                    'form_drafts',
                    'auth_token',
                    'user_session',
                    'ita_social_session'
                ];
                
                keysToRemove.forEach(key => {
                    try {
                        localStorage.removeItem(key);
                    } catch (e) {
                        console.warn(`Error removiendo ${key}:`, e);
                    }
                });
                
                // Limpiar sessionStorage
                try {
                    sessionStorage.clear();
                } catch (e) {
                    console.warn('Error limpiando sessionStorage:', e);
                }
                
                console.log('Datos locales limpiados correctamente');
                
            } catch (error) {
                console.error('Error general limpiando datos locales:', error);
            }
        }

        // Event Listeners para el modal
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar modal con Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeLogoutModal();
                }
            });
            
            // Cerrar modal clickeando el overlay
            const overlay = document.getElementById('logoutModalOverlay');
            if (overlay) {
                overlay.addEventListener('click', closeLogoutModal);
            }
        });

        // Exponer funciones globalmente para debugging
        window.logoutSystem = {
            performSecureLogout,
            showLogoutModal,
            closeLogoutModal,
            confirmLogout,
            forceLogout,
            clearLocalData
        };
        </script>