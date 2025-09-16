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
    'telefono' => '',
    'semestre' => '',
    'creditos_cursados' => ''
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #8b8cf7;
            --success-color: #10b981;
            --error-color: #ef4444;
            --bg-primary: #0f1419;
            --bg-secondary: #1a202c;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: var(--text-primary);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .register-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 3rem;
            margin: 2rem 0;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            margin-bottom: 2rem;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: var(--success-color);
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fecaca;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .error {
            color: var(--error-color);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            width: 100%;
            margin-bottom: 2rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .form-footer {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .form-footer p {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .required {
            color: var(--error-color);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .register-card {
                padding: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Volver al inicio
        </a>

        <div class="register-card">
            <div class="header">
                <h1><i class="fas fa-user-plus"></i> Registro de Estudiante</h1>
                <p>Instituto Tecnológico de Aguascalientes</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="register-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>" 
                               placeholder="21000000@aguascalientes.tecnm.mx" required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="numero_control">Número de Control <span class="required">*</span></label>
                        <input type="text" id="numero_control" name="numero_control" 
                               value="<?= htmlspecialchars($formData['numero_control'] ?? '') ?>" 
                               pattern="\d{8}" title="8 dígitos" placeholder="20180001" required>
                        <?php if (isset($errors['numero_control'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['numero_control']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" 
                               value="<?= htmlspecialchars($formData['nombre'] ?? '') ?>" 
                               placeholder="Juan Carlos" required>
                        <?php if (isset($errors['nombre'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['nombre']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="apellido_paterno">Apellido Paterno <span class="required">*</span></label>
                        <input type="text" id="apellido_paterno" name="apellido_paterno" 
                               value="<?= htmlspecialchars($formData['apellido_paterno'] ?? '') ?>" 
                               placeholder="Pérez" required>
                        <?php if (isset($errors['apellido_paterno'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['apellido_paterno']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="apellido_materno">Apellido Materno</label>
                        <input type="text" id="apellido_materno" name="apellido_materno" 
                               value="<?= htmlspecialchars($formData['apellido_materno'] ?? '') ?>" 
                               placeholder="García">
                    </div>

                    <div class="form-group">
                        <label for="carrera">Carrera <span class="required">*</span></label>
                        <select id="carrera" name="carrera" required>
                            <option value="">Seleccionar carrera</option>
                            <option value="Ingeniería en Tecnologías de la Información y Comunicaciones" <?= ($formData['carrera'] ?? '') === 'Ingeniería en Tecnologías de la Información y Comunicaciones' ? 'selected' : '' ?>>Ingeniería en Tecnologías de la Información y Comunicaciones</option>
                            <option value="Ingeniería Industrial" <?= ($formData['carrera'] ?? '') === 'Ingeniería Industrial' ? 'selected' : '' ?>>Ingeniería Industrial</option>
                            <option value="Ingeniería Mecánica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Mecánica' ? 'selected' : '' ?>>Ingeniería Mecánica</option>
                            <option value="Ingeniería Electrónica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Electrónica' ? 'selected' : '' ?>>Ingeniería Electrónica</option>
                            <option value="Ingeniería Química" <?= ($formData['carrera'] ?? '') === 'Ingeniería Química' ? 'selected' : '' ?>>Ingeniería Química</option>
                            <option value="Ingeniería Mecatrónica" <?= ($formData['carrera'] ?? '') === 'Ingeniería Mecatrónica' ? 'selected' : '' ?>>Ingeniería Mecatrónica</option>
                            <option value="Ingeniería en Gestión Empresarial" <?= ($formData['carrera'] ?? '') === 'Ingeniería en Gestión Empresarial' ? 'selected' : '' ?>>Ingeniería en Gestión Empresarial</option>
                            <option value="Licenciatura en Administración" <?= ($formData['carrera'] ?? '') === 'Licenciatura en Administración' ? 'selected' : '' ?>>Licenciatura en Administración</option>
                        </select>
                        <?php if (isset($errors['carrera'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['carrera']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="semestre">Semestre <span class="required">*</span></label>
                        <input type="number" id="semestre" name="semestre" 
                               value="<?= htmlspecialchars($formData['semestre'] ?? '') ?>" 
                               min="1" max="12" placeholder="6" required>
                        <?php if (isset($errors['semestre'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['semestre']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="creditos_cursados">Créditos Cursados <span class="required">*</span></label>
                        <input type="number" id="creditos_cursados" name="creditos_cursados" 
                               value="<?= htmlspecialchars($formData['creditos_cursados'] ?? '') ?>" 
                               min="0" placeholder="180" required>
                        <?php if (isset($errors['creditos_cursados'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['creditos_cursados']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" 
                               value="<?= htmlspecialchars($formData['telefono'] ?? '') ?>" 
                               placeholder="449 123 4567">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña <span class="required">*</span></label>
                        <input type="password" id="password" name="password" 
                               placeholder="Mínimo 8 caracteres" required>
                        <?php if (isset($errors['password'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar Contraseña <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Repetir contraseña" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Registrarse
                </button>
            </form>

            <div class="form-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                <p>¿Eres jefe de laboratorio? <a href="register-jefe.php">Regístrate aquí</a></p>
            </div>
        </div>
    </div>

    <script>
        // Validación del número de control
        document.getElementById('numero_control').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 8);
        });

        // Validación de contraseñas
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.style.borderColor = 'var(--error-color)';
            } else {
                this.style.borderColor = 'var(--border-color)';
            }
        });
    </script>
</body>
</html>

<?php include '../includes/footer.php'; ?>