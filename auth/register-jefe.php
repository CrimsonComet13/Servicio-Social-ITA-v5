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
    'nombre' => '',
    'laboratorio' => '',
    'especialidad' => '',
    'telefono' => '',
    'extension' => ''
];

// Obtener jefes de departamento para el select
$db = Database::getInstance();
$jefesDepartamento = $db->fetchAll("SELECT id, nombre, departamento FROM jefes_departamento ORDER BY nombre");

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
    
    if (empty($formData['nombre'])) {
        $errors['nombre'] = 'El nombre es obligatorio';
    }
    
    if (empty($formData['laboratorio'])) {
        $errors['laboratorio'] = 'El laboratorio es obligatorio';
    }
    
    if (empty($formData['jefe_departamento_id'])) {
        $errors['jefe_departamento_id'] = 'Debe seleccionar un jefe de departamento';
    }
    
    // Verificar si el email ya existe
    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$formData['email']]);
    if ($existingUser) {
        $errors['email'] = 'Ya existe un usuario con este email';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Crear usuario
            $userId = $db->insert('usuarios', [
                'email' => $formData['email'],
                'password' => hashPassword($formData['password']),
                'tipo_usuario' => 'jefe_laboratorio',
                'activo' => false, // Inactivo hasta ser aprobado por el jefe de departamento
                'email_verificado' => false,
                'token_verificacion' => generateToken()
            ]);
            
            // Crear jefe de laboratorio
            $jefeLabId = $db->insert('jefes_laboratorio', [
                'usuario_id' => $userId,
                'jefe_departamento_id' => $formData['jefe_departamento_id'],
                'nombre' => $formData['nombre'],
                'laboratorio' => $formData['laboratorio'],
                'especialidad' => $formData['especialidad'] ?? null,
                'telefono' => $formData['telefono'] ?? null,
                'extension' => $formData['extension'] ?? null,
                'activo' => false
            ]);
            
            $db->commit();
            
            // Notificar al jefe de departamento
            $jefeDepartamento = $db->fetch("SELECT usuario_id FROM jefes_departamento WHERE id = ?", 
                                          [$formData['jefe_departamento_id']]);
            
            if ($jefeDepartamento) {
                createNotification(
                    $jefeDepartamento['usuario_id'],
                    'Nueva solicitud de registro',
                    "El jefe de laboratorio {$formData['nombre']} ha solicitado registro en el sistema.",
                    'info',
                    "/modules/departamento/laboratorios.php"
                );
            }
            
            $success = 'Solicitud de registro enviada. Debe ser aprobada por el jefe de departamento.';
            $formData = []; // Limpiar formulario
            
        } catch (Exception $e) {
            $db->rollback();
            $errors['general'] = 'Error en el registro: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Registro de Jefe de Laboratorio - " . APP_NAME;
include '../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h1>Registro de Jefe de Laboratorio</h1>
        
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
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
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
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($formData['nombre'] ?? '') ?>" required>
                    <?php if (isset($errors['nombre'])): ?>
                        <span class="error"><?= $errors['nombre'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="jefe_departamento_id">Jefe de Departamento *</label>
                    <select id="jefe_departamento_id" name="jefe_departamento_id" required>
                        <option value="">Seleccione un jefe de departamento</option>
                        <?php foreach ($jefesDepartamento as $jefe): ?>
                            <option value="<?= $jefe['id'] ?>" <?= isset($formData['jefe_departamento_id']) && $formData['jefe_departamento_id'] == $jefe['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($jefe['nombre'] . ' - ' . $jefe['departamento']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['jefe_departamento_id'])): ?>
                        <span class="error"><?= $errors['jefe_departamento_id'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="laboratorio">Laboratorio *</label>
                    <input type="text" id="laboratorio" name="laboratorio" value="<?= htmlspecialchars($formData['laboratorio'] ?? '') ?>" required>
                    <?php if (isset($errors['laboratorio'])): ?>
                        <span class="error"><?= $errors['laboratorio'] ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="especialidad">Especialidad</label>
                    <input type="text" id="especialidad" name="especialidad" value="<?= htmlspecialchars($formData['especialidad'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($formData['telefono'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="extension">Extensión</label>
                    <input type="text" id="extension" name="extension" value="<?= htmlspecialchars($formData['extension'] ?? '') ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Solicitar Registro</button>
        </form>
        
        <div class="form-footer">
            <p>¿Eres estudiante? <a href="register.php">Regístrate como estudiante</a></p>
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>