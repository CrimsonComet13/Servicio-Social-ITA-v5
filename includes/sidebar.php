<?php
// Sidebar común para todos los dashboards
$currentRole = $session->getUserRole();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($usuario['nombre'] ?? $usuario['email']) ?></h3>
                <span class="user-role"><?= ucfirst(str_replace('_', ' ', $currentRole)) ?></span>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="/dashboard/<?= $currentRole ?>.php" class="nav-link <?= $currentPage === "$currentRole.php" ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Menú para Estudiantes -->
            <?php if ($currentRole === 'estudiante'): ?>
            <li class="nav-item">
                <a href="/modules/estudiantes/solicitud.php" class="nav-link <?= strpos($currentPage, 'solicitud') !== false ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Solicitudes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/estudiantes/reportes.php" class="nav-link <?= strpos($currentPage, 'reportes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/estudiantes/documentos.php" class="nav-link <?= strpos($currentPage, 'documentos') !== false ? 'active' : '' ?>">
                    <i class="fas fa-file-download"></i>
                    <span>Documentos</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Menú para Jefes de Laboratorio -->
            <?php if ($currentRole === 'jefe_laboratorio'): ?>
            <li class="nav-item">
                <a href="/modules/laboratorio/estudiantes.php" class="nav-link <?= strpos($currentPage, 'estudiantes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Estudiantes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/laboratorio/evaluaciones.php" class="nav-link <?= strpos($currentPage, 'evaluaciones') !== false ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Evaluaciones</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/laboratorio/reportes.php" class="nav-link <?= strpos($currentPage, 'reportes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Menú para Jefes de Departamento -->
            <?php if ($currentRole === 'jefe_departamento'): ?>
            <li class="nav-item">
                <a href="/modules/departamento/solicitudes.php" class="nav-link <?= strpos($currentPage, 'solicitudes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Solicitudes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/departamento/estudiantes.php" class="nav-link <?= strpos($currentPage, 'estudiantes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Estudiantes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/departamento/laboratorios.php" class="nav-link <?= strpos($currentPage, 'laboratorios') !== false ? 'active' : '' ?>">
                    <i class="fas fa-flask"></i>
                    <span>Laboratorios</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/departamento/reportes.php" class="nav-link <?= strpos($currentPage, 'reportes') !== false ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/modules/departamento/configuracion.php" class="nav-link <?= strpos($currentPage, 'configuracion') !== false ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Enlaces comunes -->
            <li class="nav-item">
                <a href="/modules/<?= $currentRole ?>/perfil.php" class="nav-link <?= strpos($currentPage, 'perfil') !== false ? 'active' : '' ?>">
                    <i class="fas fa-user"></i>
                    <span>Mi Perfil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<style>
.sidebar {
    width: 250px;
    background: var(--secondary-color);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.user-details h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.user-role {
    font-size: 0.8rem;
    opacity: 0.8;
}

.sidebar-nav {
    padding: 1rem 0;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s;
    gap: 0.75rem;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.nav-link.active {
    background: var(--primary-color);
    color: white;
}

.nav-link i {
    width: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
        z-index: 1000;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}
</style>