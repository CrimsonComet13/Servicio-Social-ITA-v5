<?php
// Navigation partial - muestra enlaces según el rol del usuario
if (!isset($session)) {
    require_once __DIR__ . '/../config/session.php';
    $session = SecureSession::getInstance();
}

$user = $session->getUser();
$role = $session->getUserRole();
?>
<nav class="main-nav">
    <div class="nav-brand">
        <a href="/servicio_social_ita/">
            <img src="/servicio_social_ita/assets/images/logo-ita.png" alt="Logo" height="36">
        </a>
    </div>

    <ul class="nav-list">
        <li><a href="/servicio_social_ita/">Inicio</a></li>

        <?php if ($role === 'estudiante'): ?>
            <li><a href="/servicio_social_ita/dashboard/estudiante.php">Mi Panel</a></li>
            <li><a href="/servicio_social_ita/modules/estudiante/solicitudes.php">Solicitudes</a></li>
            <li><a href="/servicio_social_ita/modules/estudiante/reportes.php">Reportes</a></li>
        <?php endif; ?>

        <?php if ($role === 'jefe_laboratorio'): ?>
            <li><a href="/servicio_social_ita/dashboard/jefe-laboratorio.php">Dashboard Lab</a></li>
            <li><a href="/servicio_social_ita/modules/laboratorio/estudiantes.php">Estudiantes</a></li>
        <?php endif; ?>

        <?php if ($role === 'jefe_departamento'): ?>
            <li><a href="/servicio_social_ita/dashboard/jefe-departamento.php">Dashboard Dept.</a></li>
            <li><a href="/servicio_social_ita/modules/departamento/reportes.php">Reportes</a></li>
        <?php endif; ?>

        <li><a href="/servicio_social_ita/auth/logout.php">Cerrar sesión</a></li>
    </ul>
</nav>