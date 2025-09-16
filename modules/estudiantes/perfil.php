<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener datos actuales del estudiante
$estudiante = $db->fetch("
    SELECT e.*, u.email 
    FROM estudiantes e 
    JOIN usuarios u ON e.usuario_id = u.id 
    WHERE e.usuario_id = ?
", [$estudianteId]);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_map('sanitizeInput', $_POST);
    
    // Validar campos
    if (empty($formData['nombre'])) {
        $errors['nombre'] = 'El nombre es obligatorio';
    }
    
    if (empty($formData['apellido_paterno'])) {
        $errors['apellido_paterno'] = 'El apellido paterno es obligatorio';
    }
    
    if (empty($formData['carrera'])) {
        $errors['carrera'] = 'La carrera es obligatoria';
    }
    
    if (empty($formData['semestre'])) {
        $errors['semestre'] = 'El semestre es obligatorio';
    } elseif ($formData['semestre'] < 1 || $formData['semestre'] > 12) {
        $errors['semestre'] = 'El semestre debe estar entre 1 y 12';
    }
    
    if (empty($formData['creditos_cursados'])) {
        $errors['creditos_cursados'] = 'Los créditos cursados son obligatorios';
    } elseif ($formData['creditos_cursados'] < 0) {
        $errors['creditos_cursados'] = 'Los créditos cursados no pueden ser negativos';
    }
    
    if (empty($errors)) {
        try {
            // Actualizar datos del estudiante
            $db->update('estudiantes', [
                'nombre' => $formData['nombre'],
                'apellido_paterno' => $formData['apellido_paterno'],
                'apellido_materno' => $formData['apellido_materno'] ?? null,
                'carrera' => $formData['carrera'],
                'semestre' => $formData['semestre'],
                'creditos_cursados' => $formData['creditos_cursados'],
                'telefono' => $formData['telefono'] ?? null
            ], 'usuario_id = :usuario_id', ['usuario_id' => $estudianteId]);
            
            // Actualizar email si cambió
            if ($formData['email'] !== $estudiante['email']) {
                if (!validateEmail($formData['email'])) {
                    $errors['email'] = 'El formato del email no es válido';
                } else {
                    // Verificar si el nuevo email ya existe
                    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ? AND id != ?", 
                                              [$formData['email'], $estudianteId]);
                    if ($existingUser) {
                        $errors['email'] = 'Ya existe un usuario con este email';
                    } else {
                        $db->update('usuarios', [
                            'email' => $formData['email'],
                            'email_verificado' => false,
                            'token_verificacion' => generateToken()
                        ], 'id = :id', ['id' => $estudianteId]);
                        
                        // Enviar email de verificación (pendiente)
                        // sendVerificationEmail($formData['email'], $token);
                    }
                }
            }
            
            if (empty($errors)) {
                $success = 'Perfil actualizado correctamente';
                
                // Actualizar datos en sesión
                $estudianteActualizado = $db->fetch("
                    SELECT e.*, u.email 
                    FROM estudiantes e 
                    JOIN usuarios u ON e.usuario_id = u.id 
                    WHERE e.usuario_id = ?
                ", [$estudianteId]);
                
                $session->set('usuario', array_merge($session->getUser(), $estudianteActualizado));
                
                // Recargar la página para mostrar los cambios
                redirectTo('/modules/estudiantes/perfil.php');
            }
            
        } catch (Exception $e) {
            $errors['general'] = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Mi Perfil - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1>Mi Perfil</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?= $errors['general'] ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($estudiante['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error"><?= $errors['email'] ?></span>
                    <?php endif; ?>
                    <?php if (!$estudiante['email_verificado']): ?>
                        <small class="text-warning">Email no verificado. <a href="/auth/verify-email.php">Verificar ahora</a></small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="numero_control">Número de Control</label>
                    <input type="text" id="numero_control" name="numero_control" value="<?= htmlspecialchars($estudiante['numero_control'] ?? '') ?>" disabled>
                    <small>El número de control no puede ser modificado</small>
                </div>
                
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($estudiante['nombre'] ?? '') ?>" required>
                    <?php if (isset($errors['nombre'])): ?>
                        <span class="error"><?= $errors['nombre'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellido_paterno">Apellido Paterno *</label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($estudiante['apellido_paterno'] ?? '') ?>" required>
                    <?php if (isset($errors['apellido_paterno'])): ?>
                        <span class="error"><?= $errors['apellido_paterno'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellido_materno">Apellido Materno</label>
                    <input type="text" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($estudiante['apellido_materno'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="carrera">Carrera *</label>
                    <input type="text" id="carrera" name="carrera" value="<?= htmlspecialchars($estudiante['carrera'] ?? '') ?>" required>
                    <?php if (isset($errors['carrera'])): ?>
                        <span class="error"><?= $errors['carrera'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="semestre">Semestre *</label>
                    <input type="number" id="semestre" name="semestre" value="<?= htmlspecialchars($estudiante['semestre'] ?? '') ?>" min="1" max="12" required>
                    <?php if (isset($errors['semestre'])): ?>
                        <span class="error"><?= $errors['semestre'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="creditos_cursados">Créditos Cursados *</label>
                    <input type="number" id="creditos_cursados" name="creditos_cursados" value="<?= htmlspecialchars($estudiante['creditos_cursados'] ?? '') ?>" min="0" required>
                    <?php if (isset($errors['creditos_cursados'])): ?>
                        <span class="error"><?= $errors['creditos_cursados'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
        </form>
        
        <div class="form-footer">
            <a href="/dashboard/estudiante.php" class="btn btn-secondary">Volver al Dashboard</a>
            <a href="/auth/change-password.php" class="btn btn-info">Cambiar Contraseña</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>