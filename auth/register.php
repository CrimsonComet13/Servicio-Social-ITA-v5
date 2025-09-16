<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if ($session->isLoggedIn()) {
    redirectTo("/dashboard/{$session->getUserRole()}.php");
}

$errors = [];
$success = '';
$formData = [
    'email' => '',
    'numero_control' => '',
    'nombre' => '',
    'apellido_paterno' => '',
    'apellido_materno' => '',
    'carrera' => '',
    'telefono' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_map('sanitizeInput', $_POST);
    
    // Validar campos
    if (empty($formData['email'])) {
        $errors['email'] = 'El email es obligatorio';
    } elseif (!validateEmail($formData['email'])) {
        $errors['email'] = 'El formato del email no es válido';
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = 'La contraseña es obligatoria';
    } elseif (strlen($formData['password']) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    if (empty($formData['numero_control'])) {
        $errors['numero_control'] = 'El número de control es obligatorio';
    } elseif (!validateNumeroControl($formData['numero_control'])) {
        $errors['numero_control'] = 'El número de control debe tener 8 dígitos';
    }
    
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
    
    // Verificar si el email ya existe
    $db = Database::getInstance();
    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$formData['email']]);
    if ($existingUser) {
        $errors['email'] = 'Ya existe un usuario con este email';
    }
    
    // Verificar si el número de control ya existe
    $existingStudent = $db->fetch("SELECT id FROM estudiantes WHERE numero_control = ?", [$formData['numero_control']]);
    if ($existingStudent) {
        $errors['numero_control'] = 'Ya existe un estudiante con este número de control';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Crear usuario
            $userId = $db->insert('usuarios', [
                'email' => $formData['email'],
                'password' => hashPassword($formData['password']),
                'tipo_usuario' => 'estudiante',
                'activo' => true,
                'email_verificado' => false,
                'token_verificacion' => generateToken()
            ]);
            
            // Crear estudiante
            $estudianteId = $db->insert('estudiantes', [
                'usuario_id' => $userId,
                'numero_control' => $formData['numero_control'],
                'nombre' => $formData['nombre'],
                'apellido_paterno' => $formData['apellido_paterno'],
                'apellido_materno' => $formData['apellido_materno'] ?? null,
                'carrera' => $formData['carrera'],
                'semestre' => $formData['semestre'],
                'creditos_cursados' => $formData['creditos_cursados'],
                'telefono' => $formData['telefono'] ?? null,
                'estado_servicio' => 'sin_solicitud'
            ]);
            
            $db->commit();
            
            // Iniciar sesión automáticamente
            $userData = $db->fetch("
                SELECT e.*, u.email 
                FROM estudiantes e 
                JOIN usuarios u ON e.usuario_id = u.id 
                WHERE e.usuario_id = ?
            ", [$userId]);
            
            $session->set('usuario', array_merge([
                'id' => $userId,
                'email' => $formData['email'],
                'tipo_usuario' => 'estudiante',
                'activo' => true,
                'email_verificado' => false
            ], $userData));
            
            // Registrar actividad
            logActivity($userId, 'register', 'auth');
            
            $success = 'Registro exitoso. Bienvenido al sistema.';
            flashMessage($success, 'success');
            redirectTo('/dashboard/estudiante.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $errors['general'] = 'Error en el registro: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Registro de Estudiante - " . APP_NAME;
include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1>Registro de Estudiante</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?= $errors['general'] ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error"><?= $errors['email'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error"><?= $errors['password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error"><?= $errors['confirm_password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="numero_control">Número de Control *</label>
                    <input type="text" id="numero_control" name="numero_control" 
                           value="<?= htmlspecialchars($formData['numero_control']) ?>" 
                           pattern="\d{8}" title="8 dígitos" required>
                    <?php if (isset($errors['numero_control'])): ?>
                        <span class="error"><?= $errors['numero_control'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($formData['nombre']) ?>" required>
                    <?php if (isset($errors['nombre'])): ?>
                        <span class="error"><?= $errors['nombre'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellido_paterno">Apellido Paterno *</label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" 
                           value="<?= htmlspecialchars($formData['apellido_paterno']) ?>" required>
                    <?php if (isset($errors['apellido_paterno'])): ?>
                        <span class="error"><?= $errors['apellido_paterno'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="apellido_materno">Apellido Materno</label>
                    <input type="text" id="apellido_materno" name="apellido_materno" 
                           value="<?= htmlspecialchars($formData['apellido_materno']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="carrera">Carrera *</label>
                    <input type="text" id="carrera" name="carrera" value="<?= htmlspecialchars($formData['carrera']) ?>" required>
                    <?php if (isset($errors['carrera'])): ?>
                        <span class="error"><?= $errors['carrera'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="semestre">Semestre *</label>
                    <input type="number" id="semestre" name="semestre" 
                           value="<?= htmlspecialchars($formData['semestre']) ?>" min="1" max="12" required>
                    <?php if (isset($errors['semestre'])): ?>
                        <span class="error"><?= $errors['semestre'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="creditos_cursados">Créditos Cursados *</label>
                    <input type="number" id="creditos_cursados" name="creditos_cursados" 
                           value="<?= htmlspecialchars($formData['creditos_cursados']) ?>" min="0" required>
                    <?php if (isset($errors['creditos_cursados'])): ?>
                        <span class="error"><?= $errors['creditos_cursados'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($formData['telefono']) ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </form>
        
        <div class="form-footer">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            <p>¿Eres jefe de laboratorio? <a href="register-jefe.php">Regístrate aquí</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>