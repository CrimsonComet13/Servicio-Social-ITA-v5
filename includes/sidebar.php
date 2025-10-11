<?php
// Sidebar común para todos los dashboards
$currentRole = $session->getUserRole();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['REQUEST_URI'];

// Definir menús por rol
$menusByRole = [
    'estudiante' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fas fa-home',
            'url' => BASE_URL . 'dashboard/estudiante.php',
            'badge' => null
        ],
        [
            'label' => 'Mi Solicitud',
            'icon' => 'fas fa-file-alt',
            'url' => BASE_URL . 'modules/estudiantes/solicitud.php',
            'badge' => null,
            'submenu' => [
                ['label' => 'Nueva Solicitud', 'url' => BASE_URL . 'modules/estudiantes/solicitud.php'],
                ['label' => 'Estado Actual', 'url' => BASE_URL . 'modules/estudiantes/solicitud-estado.php']
            ]
        ],
        [
            'label' => 'Reportes',
            'icon' => 'fas fa-chart-bar',
            'url' => BASE_URL . 'modules/estudiantes/reportes.php',
            'badge' => '2'
        ],
        [
            'label' => 'Documentos',
            'icon' => 'fas fa-file-download',
            'url' => BASE_URL . 'modules/estudiantes/documentos.php',
            'badge' => null
        ],
        [
            'label' => 'Mi Perfil',
            'icon' => 'fas fa-user-cog',
            'url' => BASE_URL . 'modules/estudiantes/perfil.php',
            'badge' => null
        ]
    ],
    'jefe_laboratorio' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fas fa-home',
            'url' => BASE_URL . 'dashboard/jefe_laboratorio.php',
            'badge' => null
        ],
        [
            'label' => 'Estudiantes',
            'icon' => 'fas fa-users',
            'url' => BASE_URL . 'modules/laboratorio/estudiantes.php',
            'badge' => '24',
            'submenu' => [
                ['label' => 'Asignados', 'url' => BASE_URL . 'modules/laboratorio/estudiantes-asignados.php'],
                ['label' => 'Solicitudes', 'url' => BASE_URL . 'modules/laboratorio/estudiantes-solicitudes.php'],
                ['label' => 'Historial', 'url' => BASE_URL . 'modules/laboratorio/estudiantes-historial.php']
            ]
        ],
        [
            'label' => 'Evaluaciones',
            'icon' => 'fas fa-star',
            'url' => BASE_URL . 'modules/laboratorio/evaluaciones.php',
            'badge' => '8'
        ],
        [
            'label' => 'Proyectos',
            'icon' => 'fas fa-project-diagram',
            'url' => BASE_URL . 'modules/laboratorio/proyectos.php',
            'badge' => null
        ],
        [
            'label' => 'Reportes',
            'icon' => 'fas fa-chart-line',
            'url' => BASE_URL . 'modules/laboratorio/reportes.php',
            'badge' => null
        ],
     
    ],
    'jefe_departamento' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fas fa-home',
            'url' => BASE_URL . 'dashboard/jefe_departamento.php',
            'badge' => null
        ],
        [
            'label' => 'Proyectos',
            'icon' => 'fa fa-file-alt',
            'url' => BASE_URL . 'modules/departamento/proyectos.php',
            'badge' => null
        ],
        [
            'label' => 'Solicitudes',
            'icon' => 'fas fa-clipboard-list',
            'url' => BASE_URL . 'modules/departamento/solicitudes.php',
            'badge' => null
            
        ],
        [
            'label' => 'Estudiantes',
            'icon' => 'fas fa-user-graduate',
            'url' => BASE_URL . 'modules/departamento/estudiantes.php',
            'badge' => null
        ],
        [
            'label' => 'Laboratorios',
            'icon' => 'fas fa-flask',
            'url' => BASE_URL . 'modules/departamento/laboratorios.php',
            'badge' => null,
           
        ],
        [
            'label' => 'Reportes',
            'icon' => 'fas fa-chart-pie',
            'url' => BASE_URL . 'modules/departamento/reportes.php',
            'badge' => null
        ],
        [
            'label' => 'Evaluaciones',
            'icon' => 'fas fa-star',
            'url' => BASE_URL . 'modules/departamento/evaluaciones.php',
            'badge' => null
        ],
        [
            'label' => 'Configuración',
            'icon' => 'fas fa-cog',
            'url' => BASE_URL . 'modules/departamento/configuracion.php',
            'badge' => null
        ]
    ]
];

$currentMenu = $menusByRole[$currentRole] ?? [];

// Función para verificar si un enlace está activo
function isLinkActive($url, $currentPath) {
    $parsedUrl = parse_url($url);
    $linkPath = $parsedUrl['path'] ?? '';
    return strpos($currentPath, $linkPath) !== false;
}

// Función para verificar si un menú tiene submenú activo
function hasActiveSubmenu($submenu, $currentPath) {
    if (!$submenu) return false;
    foreach ($submenu as $item) {
        if (isLinkActive($item['url'], $currentPath)) {
            return true;
        }
    }
    return false;
}
?>
<aside class="sidebar" id="appSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php if (isset($usuario['avatar']) && $usuario['avatar']): ?>
                    <img src="<?= UPLOAD_URL . $usuario['avatar'] ?>" alt="Avatar">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
                <div class="status-indicator online"></div>
            </div>
            <div class="user-info">
                <h3 class="user-name"><?= htmlspecialchars(($usuario['nombre'] ?? $usuario['email']) ?: 'Usuario') ?></h3>
                <span class="user-role"><?= ucfirst(str_replace('_', ' ', $currentRole)) ?></span>
            </div>
        </div>
        
        <?php if ($currentRole === 'estudiante' && isset($estudiante)): ?>
        <div class="progress-widget">
            <div class="progress-header">
                <span class="progress-label">Progreso del Servicio</span>
                <span class="progress-percentage"><?= min(100, round(($estudiante['horas_completadas'] ?? 0) / 500 * 100)) ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= min(100, ($estudiante['horas_completadas'] ?? 0) / 500 * 100) ?>%"></div>
            </div>
            <div class="progress-info">
                <span><?= $estudiante['horas_completadas'] ?? 0 ?> / 500 horas</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <ul class="nav-menu" role="menubar">
                <?php foreach ($currentMenu as $index => $item): 
                    $isActive = isLinkActive($item['url'], $currentPath);
                    $hasActiveSubmenuItem = hasActiveSubmenu($item['submenu'] ?? null, $currentPath);
                    $isExpanded = $isActive || $hasActiveSubmenuItem;
                ?>
                <li class="nav-item <?= $isActive ? 'active' : '' ?> <?= $hasActiveSubmenuItem ? 'has-active-child' : '' ?>" role="menuitem">
                    <a href="<?= $item['url'] ?>" 
                       class="nav-link <?= $isActive ? 'active' : '' ?> <?= isset($item['submenu']) ? 'has-submenu' : '' ?>"
                       <?= isset($item['submenu']) ? 'data-submenu-toggle' : '' ?>
                       role="button"
                       aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>">
                        <div class="nav-link-content">
                            <div class="nav-icon">
                                <i class="<?= $item['icon'] ?>"></i>
                            </div>
                            <span class="nav-text"><?= $item['label'] ?></span>
                        </div>
                        
                        <?php if ($item['badge']): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        
                        <?php if (isset($item['submenu'])): ?>
                        <i class="fas fa-chevron-down nav-arrow <?= $isExpanded ? 'expanded' : '' ?>"></i>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (isset($item['submenu'])): ?>
                    <ul class="nav-submenu <?= $isExpanded ? 'expanded' : '' ?>" role="menu">
                        <?php foreach ($item['submenu'] as $subitem): 
                            $isSubActive = isLinkActive($subitem['url'], $currentPath);
                        ?>
                        <li class="nav-subitem <?= $isSubActive ? 'active' : '' ?>" role="menuitem">
                            <a href="<?= $subitem['url'] ?>" class="nav-sublink <?= $isSubActive ? 'active' : '' ?>">
                                <span class="nav-subtext"><?= $subitem['label'] ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="footer-info">
            <div class="app-version">
                <span>ITA Social v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?></span>
            </div>
        </div>
    </div>
</aside>

<style>
/* ================================
   SIDEBAR STYLES - ACTUALIZADO COMPLETO
================================ */

/* Sidebar principal - POSICIÓN FIJA CORRECTA */
.sidebar {
    position: fixed;
    left: 0;
    top: var(--header-height);
    width: var(--sidebar-width);
    height: calc(100vh - var(--header-height));
    background: var(--bg-white);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 900;  /* Menos que header (1000) */
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: var(--bg-light);
}

.sidebar::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--text-light);
}

/* Sidebar Header */
.sidebar-header {
    padding: 2rem;
    border-bottom: 1px solid var(--border-light);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 140, 247, 0.05) 100%);
    flex-shrink: 0;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.sidebar-user .user-avatar {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    overflow: hidden;
    box-shadow: var(--shadow);
    flex-shrink: 0;
}

.sidebar-user .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid var(--bg-white);
}

.status-indicator.online {
    background: var(--success);
}

.status-indicator.away {
    background: var(--warning);
}

.status-indicator.offline {
    background: var(--text-light);
}

.sidebar-user .user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user .user-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user .user-role {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    display: inline-block;
}

/* Progress Widget */
.progress-widget {
    background: var(--bg-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius);
    padding: 1rem;
    margin-top: 1rem;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.progress-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-percentage {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--primary);
}

.progress-widget .progress-bar {
    height: 6px;
    background: var(--bg-light);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-widget .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 3px;
    transition: var(--transition);
}

.progress-info {
    font-size: 0.75rem;
    color: var(--text-light);
    text-align: center;
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 2rem;
}

.nav-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0 2rem;
    margin-bottom: 1rem;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 0;
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 2rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition);
    position: relative;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
}

.nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary);
    transform: scaleY(0);
    transition: var(--transition);
}

.nav-link:hover {
    background: var(--bg-light);
    color: var(--primary);
}

.nav-link.active {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.nav-link.active::before {
    transform: scaleY(1);
}

.nav-link-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.nav-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.nav-text {
    font-size: 0.9rem;
    font-weight: 500;
}

.nav-badge {
    background: var(--error);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    flex-shrink: 0;
}

.nav-arrow {
    font-size: 0.75rem;
    color: var(--text-light);
    transition: var(--transition);
    margin-left: 0.5rem;
    flex-shrink: 0;
}

.nav-arrow.expanded {
    transform: rotate(180deg);
}

/* Submenu */
.nav-submenu {
    list-style: none;
    padding: 0;
    margin: 0;
    background: var(--bg-light);
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-submenu.expanded {
    max-height: 300px;
}

.nav-subitem {
    margin: 0;
}

.nav-sublink {
    display: flex;
    align-items: center;
    padding: 0.625rem 2rem 0.625rem 4rem;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: var(--transition);
    position: relative;
}

.nav-sublink::before {
    content: '';
    position: absolute;
    left: 3rem;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 4px;
    background: var(--text-light);
    border-radius: 50%;
    transition: var(--transition);
}

.nav-sublink:hover {
    background: var(--bg-white);
    color: var(--primary);
}

.nav-sublink.active {
    background: var(--bg-white);
    color: var(--primary);
}

.nav-sublink.active::before {
    background: var(--primary);
    transform: translateY(-50%) scale(1.5);
}

/* Quick Stats */
.quick-stats {
    padding: 0 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    transition: var(--transition);
    cursor: pointer;
}

.stat-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.stat-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    color: white;
    flex-shrink: 0;
}

.stat-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-icon.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border-light);
    background: var(--bg-light);
    flex-shrink: 0;
}

.footer-info {
    text-align: center;
}

.app-version {
    font-size: 0.75rem;
    color: var(--text-light);
}

/* ⭐ RESPONSIVE MÓVIL - CRÍTICO PARA LA SOLUCIÓN */
@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);  /* Ocultar por defecto */
        z-index: 1001;  /* Mayor que overlay (999) */
        width: var(--sidebar-width);
        box-shadow: none;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);  /* Mostrar cuando está abierto */
        box-shadow: var(--shadow-lg);
    }
}

@media (max-width: 768px) {
    .sidebar-header {
        padding: 1.5rem;
    }
    
    .sidebar-user {
        margin-bottom: 1rem;
    }
    
    .sidebar-user .user-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .quick-stats {
        padding: 0 1.5rem;
    }
    
    .sidebar-footer {
        padding: 1rem 1.5rem;
    }
    
    :root {
        --sidebar-width: 280px;  /* Mantener ancho en móviles */
    }
}

/* Animación para submenú */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.nav-submenu.expanded .nav-subitem {
    animation: slideDown 0.3s ease-out forwards;
}

.nav-submenu.expanded .nav-subitem:nth-child(2) {
    animation-delay: 0.05s;
}

.nav-submenu.expanded .nav-subitem:nth-child(3) {
    animation-delay: 0.1s;
}

.nav-submenu.expanded .nav-subitem:nth-child(4) {
    animation-delay: 0.15s;
}

/* Estados especiales */
.nav-item.has-active-child > .nav-link {
    background: rgba(99, 102, 241, 0.05);
    color: var(--primary);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle submenu toggles
    const submenuToggles = document.querySelectorAll('[data-submenu-toggle]');
    
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const submenu = this.nextElementSibling;
            const arrow = this.querySelector('.nav-arrow');
            const isExpanded = submenu && submenu.classList.contains('expanded');
            
            // Close other submenus
            document.querySelectorAll('.nav-submenu.expanded').forEach(menu => {
                if (menu !== submenu) {
                    menu.classList.remove('expanded');
                    const prevArrow = menu.previousElementSibling?.querySelector('.nav-arrow');
                    if (prevArrow) prevArrow.classList.remove('expanded');
                    menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current submenu
            if (submenu) {
                if (!isExpanded) {
                    submenu.classList.add('expanded');
                    arrow?.classList.add('expanded');
                    this.setAttribute('aria-expanded', 'true');
                } else {
                    submenu.classList.remove('expanded');
                    arrow?.classList.remove('expanded');
                    this.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });
    
    // Handle active states - expand parent submenu if sublink is active
    const activeSublinks = document.querySelectorAll('.nav-sublink.active');
    activeSublinks.forEach(sublink => {
        const parentSubmenu = sublink.closest('.nav-submenu');
        const parentToggle = parentSubmenu?.previousElementSibling;
        
        if (parentSubmenu && parentToggle) {
            parentSubmenu.classList.add('expanded');
            parentToggle.querySelector('.nav-arrow')?.classList.add('expanded');
            parentToggle.setAttribute('aria-expanded', 'true');
            parentToggle.parentElement?.classList.add('has-active-child');
        }
    });
    
    // Handle stat item interactions
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach(item => {
        item.addEventListener('click', function() {
            // Add click effect
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
        
        // Add hover effect
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    console.log('Sidebar inicializado correctamente');
});
</script>