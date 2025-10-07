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
        <a href="../">
            <img src="../assets/images/logo-ita.png" alt="Logo" height="36">
        </a>
    </div>

    <ul class="nav-list">
        <li><a href="../">Inicio</a></li>

        <?php if ($role === 'estudiante'): ?>
            <li><a href="../dashboard/estudiante.php">Mi Panel</a></li>
            <li><a href="../modules/estudiantes/solicitud.php">Solicitudes</a></li>
            <li><a href="../modules/estudiantes/reportes.php">Reportes</a></li>
        <?php endif; ?>

        <?php if ($role === 'jefe_laboratorio'): ?>
            <li><a href="../dashboard/jefe_laboratorio.php">Dashboard Lab</a></li>
            <li><a href="../modules/laboratorio/estudiantes.php">Estudiantes</a></li>
        <?php endif; ?>

        <?php if ($role === 'jefe_departamento'): ?>
            <li><a href="../dashboard/jefe_departamento.php">Dashboard Dept.</a></li>
            <li><a href="../modules/departamento/reportes.php">Reportes</a></li>
        <?php endif; ?>

        <li><a href="../auth/logout.php">Cerrar sesión</a></li>
    </ul>
</nav>