<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();

// Obtener el jefe de departamento
$jefeDepto = $db->fetch("SELECT id, nombre, departamento FROM jefes_departamento WHERE usuario_id = ?", [$usuario['id']]);
if (!$jefeDepto) {
    flashMessage('No se encontró el perfil de jefe de departamento', 'error');
    redirectTo('/dashboard/jefe_departamento.php');
}

// Obtener ID del estudiante
$estudianteId = $_GET['id'] ?? null;
if (!$estudianteId) {
    flashMessage('ID de estudiante no especificado', 'error');
    redirectTo('estudiantes.php');
}

// Por ahora, redirigir al historial con un mensaje
flashMessage('La funcionalidad de exportación a PDF estará disponible próximamente', 'info');
redirectTo('estudiante-historial.php?id=' . $estudianteId);
?>

