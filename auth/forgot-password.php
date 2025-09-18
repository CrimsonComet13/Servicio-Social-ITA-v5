<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if ($session->isLoggedIn()) {
    redirectTo(BASE_URL . "dashboard/{$session->getUserRole()}.php");
}

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'El email es obligatorio';
    } elseif (!validateEmail($email)) {
        $error = 'El formato del email no es válido';
    } else {
        $db = Database::getInstance();
        
        // Verificar si el email existe
        $user = $db->fetch("SELECT id, email FROM usuarios WHERE email = ? AND activo = TRUE", [$email]);
        
        if ($user) {
            // Generar token de recuperación
            $resetToken = generateToken();
            $resetTokenExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            
            try {
                $db->update('usuarios', [
                    'reset_token' => $resetToken,
                    'reset_token_expires' => $resetTokenExpires
                ], 'id = :id', ['id' => $user['id']]);
                
                // Enviar email de recuperación (simulado)
                $resetLink = BASE_URL . "auth/reset-password.php?token=$resetToken";
                
                // En un entorno real, se enviaría un email
                $success = "Se ha enviado un enlace de recuperación a $email";
                
                // Para desarrollo, mostramos el enlace
                if (APP_DEBUG) {
                    $success .= "<br><br>Enlace de desarrollo: <a href='$resetLink' class='dev-link'>$resetLink</a>";
                }
                
            } catch (Exception $e) {
                $error = 'Error al procesar la solicitud: ' . $e->getMessage();
            }
        } else {
            // Por seguridad, no revelamos si el email existe o no
            $success = "Si el email existe en nuestro sistema, recibirás un enlace de recuperación";
        }
    }
}

$pageTitle = "Recuperar Contraseña - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo-ita.png">
</head>
<body>
    <!-- Background Pattern -->
    <div class="background-pattern"></div>

    <!-- Navigation Back -->
    <nav class="nav-back">
        <a href="<?= BASE_URL ?>auth/login.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span>Volver al login</span>
        </a>
    </nav>

    <div class="forgot-password-container">
        <div class="forgot-password-wrapper">
            <!-- Left Side - Branding -->
            <div class="forgot-password-branding">
                <div class="branding-content">
                    <div class="logo-container">
                        <div class="logo">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h2>Recuperación Segura</h2>
                    </div>
                    
                    <h1>¿Olvidaste tu contraseña?</h1>
                    <p>No te preocupes, te ayudamos a recuperar el acceso a tu cuenta de manera segura</p>
                    
                    <div class="security-features">
                        <div class="security-feature">
                            <div class="feature-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Proceso Seguro</h4>
                                <p>Tu información está protegida durante todo el proceso</p>
                            </div>
                        </div>
                        
                        <div class="security-feature">
                            <div class="feature-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Recuperación Rápida</h4>
                                <p>Recibe tu enlace de recuperación en minutos</p>
                            </div>
                        </div>
                        
                        <div class="security-feature">
                            <div class="feature-icon">
                                <i class="fas fa-envelope-open-text"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Enlace Temporal</h4>
                                <p>El enlace expira en 1 hora por tu seguridad</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Form -->
            <div class="forgot-password-form-container">
                <div class="form-card">
                    <div class="form-header">
                        <div class="form-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h2>Recuperar Contraseña</h2>
                        <p>Ingresa tu email para recibir instrucciones de recuperación</p>
                    </div>
                    
                    <!-- Messages -->
                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="alert-content">
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert-content">
                            <span><?= $success ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$success): ?>
                    <form class="forgot-password-form" method="POST" id="forgotPasswordForm">
                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <div class="input-container">
                                <i class="fas fa-envelope input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?= htmlspecialchars($email) ?>"
                                    placeholder="tu-email@ita.mx"
                                    required
                                    autocomplete="email"
                                >
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <span class="btn-text">
                                <i class="fas fa-paper-plane"></i>
                                Enviar Instrucciones
                            </span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <div class="form-footer">
                        <div class="footer-links">
                            <a href="<?= BASE_URL ?>auth/login.php" class="footer-link">
                                <i class="fas fa-arrow-left"></i>
                                Volver al inicio de sesión
                            </a>
                            <a href="<?= BASE_URL ?>auth/register.php" class="footer-link">
                                <i class="fas fa-user-plus"></i>
                                ¿No tienes cuenta? Regístrate
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Help Section -->
                <div class="help-section">
                    <div class="help-card">
                        <div class="help-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="help-content">
                            <h4>¿Necesitas ayuda?</h4>
                            <p>Si tienes problemas para recuperar tu cuenta, contacta a soporte técnico</p>
                            <a href="mailto:soporte@ita.mx" class="help-link">
                                <i class="fas fa-envelope"></i>
                                soporte@ita.mx
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #8b8cf7;
            --primary-dark: #4f46e5;
            --secondary: #1f2937;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --bg-dark: #0f1419;
            --bg-darker: #1a202c;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Pattern */
        .background-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(139, 140, 247, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(79, 70, 229, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Navigation */
        .nav-back {
            position: absolute;
            top: 2rem;
            left: 2rem;
            z-index: 10;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-2px);
        }

        /* Main Container */
        .forgot-password-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .forgot-password-wrapper {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        /* Left Side - Branding */
        .forgot-password-branding {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            padding: 3rem;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .forgot-password-branding::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .branding-content {
            position: relative;
            z-index: 2;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .logo-container h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .branding-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .branding-content p {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 3rem;
        }

        .security-features {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .security-feature {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .feature-text h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-text p {
            font-size: 0.875rem;
            opacity: 0.8;
            line-height: 1.4;
        }

        /* Right Side - Form */
        .forgot-password-form-container {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--bg-white);
        }

        .form-card {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow);
        }

        .form-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-icon {
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }

        .alert-content {
            flex: 1;
            line-height: 1.5;
        }

        .dev-link {
            color: var(--success);
            text-decoration: underline;
            word-break: break-all;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .input-container {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .input-container input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: white;
        }

        .input-container input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Primary Button */
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Form Footer */
        .form-footer {
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .footer-link:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        /* Help Section */
        .help-section {
            margin-top: 2rem;
        }

        .help-card {
            background: var(--bg-light);
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            border: 1px solid var(--border);
        }

        .help-icon {
            width: 40px;
            height: 40px;
            background: var(--info);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .help-content {
            flex: 1;
        }

        .help-content h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .help-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .help-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--info);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .help-link:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-back {
                top: 1rem;
                left: 1rem;
            }

            .forgot-password-container {
                padding: 1rem;
            }

            .forgot-password-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .forgot-password-branding {
                padding: 2rem;
                text-align: center;
            }

            .branding-content h1 {
                font-size: 2rem;
            }

            .security-features {
                gap: 1.5rem;
            }

            .forgot-password-form-container {
                padding: 2rem;
            }
        }

        /* Loading Animation */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .fa-spin {
            animation: spin 1s linear infinite;
        }

        /* Focus styles for accessibility */
        .back-link:focus,
        .footer-link:focus,
        .help-link:focus,
        .dev-link:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
            border-radius: var(--radius);
        }

        .btn-primary:focus {
            outline: 2px solid var(--primary-dark);
            outline-offset: 2px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form Submission Loading State
            const form = document.getElementById('forgotPasswordForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form && submitBtn) {
                const btnText = submitBtn.querySelector('.btn-text');
                const btnLoader = submitBtn.querySelector('.btn-loader');

                form.addEventListener('submit', function(e) {
                    // Show loading state
                    submitBtn.disabled = true;
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoader) btnLoader.style.display = 'inline-flex';
                });
            }

            // Input Focus Effects
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Auto-focus email input if empty
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
    </script>
</body>
</html>