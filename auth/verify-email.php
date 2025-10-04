<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$session = SecureSession::getInstance();

// Si ya está logueado, redirigir al dashboard
if (!$session->isLoggedIn()) {
    redirectTo("/auth/login.php");
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verificar si ya está verificado
$user = $session->getUser();
if ($user['email_verificado']) {
    $success = 'Tu email ya está verificado';
}

// Procesar verificación por token
if (!empty($token) && empty($success)) {
    $db = Database::getInstance();
    $userData = $db->fetch("SELECT id, token_verificacion FROM usuarios WHERE id = ?", [$user['id']]);
    
    if ($userData && $userData['token_verificacion'] === $token) {
        try {
            $db->update('usuarios', [
                'email_verificado' => true,
                'token_verificacion' => null
            ], 'id = :id', ['id' => $user['id']]);
            
            // Actualizar sesión
            $session->set('usuario', array_merge($session->getUser(), ['email_verificado' => true]));
            
            $success = 'Email verificado correctamente';
            
        } catch (Exception $e) {
            $error = 'Error al verificar el email: ' . $e->getMessage();
        }
    } else {
        $error = 'Token de verificación no válido';
    }
}

// Reenviar email de verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($success)) {
    try {
        $db = Database::getInstance();
        
        // Generar nuevo token
        $newToken = generateToken();
        
        $db->update('usuarios', [
            'token_verificacion' => $newToken
        ], 'id = :id', ['id' => $user['id']]);
        
        // Enviar email de verificación (simulado)
        $verifyLink = BASE_URL . "auth/verify-email.php?token=$newToken";
        
        // En un entorno real, se enviaría un email
        $success = "Se ha enviado un nuevo enlace de verificación a {$user['email']}";
        
        // Para desarrollo, mostramos el enlace
        if (APP_DEBUG) {
            $success .= "<br><br>Enlace de desarrollo: <a href='$verifyLink'>$verifyLink</a>";
        }
        
    } catch (Exception $e) {
        $error = 'Error al enviar el email de verificación: ' . $e->getMessage();
    }
}

$pageTitle = "Verificar Email - " . APP_NAME;
include '../includes/header.php';

// Si el usuario está logueado, incluir sidebar
if ($session->isLoggedIn()) {
    include '../includes/sidebar.php';
}
?>

<!-- Main Container -->
<div class="verify-email-container">
    <div class="verify-email-card">
        
        <!-- Header Icon -->
        <div class="verify-header">
            <?php if ($user['email_verificado'] || (!empty($success) && strpos($success, 'verificado correctamente') !== false)): ?>
                <div class="verify-icon success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            <?php elseif ($error): ?>
                <div class="verify-icon error-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            <?php else: ?>
                <div class="verify-icon pending-icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Title Section -->
        <div class="verify-title-section">
            <h1 class="verify-title">
                <?php if ($user['email_verificado'] || (!empty($success) && strpos($success, 'verificado correctamente') !== false)): ?>
                    ¡Email Verificado!
                <?php elseif ($error): ?>
                    Error de Verificación
                <?php else: ?>
                    Verificación de Email
                <?php endif; ?>
            </h1>
            <p class="verify-subtitle">
                <?php if ($user['email_verificado']): ?>
                    Tu cuenta está completamente activa
                <?php elseif (!empty($success) && strpos($success, 'verificado correctamente') !== false): ?>
                    Tu dirección de email ha sido confirmada exitosamente
                <?php elseif ($error): ?>
                    No pudimos verificar tu dirección de email
                <?php else: ?>
                    Confirma tu dirección de email para continuar
                <?php endif; ?>
            </p>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="verify-alert error-alert">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <strong>Error</strong>
                    <p><?= $error ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="verify-alert success-alert">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <strong>¡Éxito!</strong>
                    <p><?= $success ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Content Section -->
        <?php if (!$user['email_verificado'] && empty($success)): ?>
            <div class="verify-content">
                <div class="email-info-card">
                    <div class="email-info-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="email-info-content">
                        <h3>Email de Verificación Enviado</h3>
                        <p>Hemos enviado un enlace de verificación a:</p>
                        <div class="email-address">
                            <i class="fas fa-envelope"></i>
                            <strong><?= htmlspecialchars($user['email']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="verify-instructions">
                    <h4><i class="fas fa-info-circle"></i> Instrucciones</h4>
                    <ol>
                        <li>Revisa tu bandeja de entrada</li>
                        <li>Busca el email de verificación de <?= APP_NAME ?></li>
                        <li>Haz clic en el enlace de verificación</li>
                        <li>Regresa aquí para continuar</li>
                    </ol>
                </div>

                <div class="resend-section">
                    <p class="resend-text">¿No recibiste el email?</p>
                    <form method="POST" class="resend-form">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-redo"></i>
                            Reenviar Email de Verificación
                        </button>
                    </form>
                </div>

                <div class="verify-help">
                    <p>
                        <i class="fas fa-question-circle"></i>
                        ¿Problemas con la verificación? 
                        <a href="/contacto.php">Contacta con soporte</a>
                    </p>
                </div>
            </div>
        <?php elseif ($user['email_verificado'] || (!empty($success) && strpos($success, 'verificado correctamente') !== false)): ?>
            <div class="verify-content verified">
                <div class="success-message">
                    <div class="success-icon-large">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3>¡Todo listo!</h3>
                    <p>Tu email ha sido verificado exitosamente. Ya puedes acceder a todas las funcionalidades del sistema.</p>
                </div>

                <div class="verified-benefits">
                    <h4>Ahora puedes:</h4>
                    <ul>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Enviar solicitudes de servicio social</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Recibir notificaciones importantes</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Acceder a todos los documentos</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Gestionar tu perfil completo</span>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Actions -->
        <div class="verify-footer">
            <a href="/dashboard/<?= $user['tipo_usuario'] ?>.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
            
            <?php if ($user['email_verificado'] || (!empty($success) && strpos($success, 'verificado correctamente') !== false)): ?>
                <a href="/dashboard/<?= $user['tipo_usuario'] ?>.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Ir al Dashboard
                </a>
            <?php endif; ?>
        </div>

    </div>

    <!-- Additional Info -->
    <div class="verify-additional-info">
        <div class="info-item">
            <i class="fas fa-shield-alt"></i>
            <span>Conexión segura</span>
        </div>
        <div class="info-item">
            <i class="fas fa-lock"></i>
            <span>Datos protegidos</span>
        </div>
        <div class="info-item">
            <i class="fas fa-user-shield"></i>
            <span>Privacidad garantizada</span>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --primary-dark: #4f46e5;
    --success: #10b981;
    --success-light: #34d399;
    --error: #ef4444;
    --error-light: #f87171;
    --warning: #f59e0b;
    --info: #3b82f6;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    --border: #e5e7eb;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius: 0.5rem;
    --radius-lg: 1rem;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Main Container */
.verify-email-container {
    min-height: calc(100vh - var(--header-height, 80px));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
}

.verify-email-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.2) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.2) 0%, transparent 50%);
    pointer-events: none;
}

/* Main Card */
.verify-email-card {
    position: relative;
    z-index: 1;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    padding: 3rem;
    max-width: 600px;
    width: 100%;
    animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header Icon */
.verify-header {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

.verify-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    position: relative;
    animation: iconPulse 2s ease-in-out infinite;
}

.verify-icon::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: inherit;
    opacity: 0.3;
    animation: ripple 2s ease-out infinite;
}

@keyframes iconPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

@keyframes ripple {
    0% {
        transform: scale(1);
        opacity: 0.3;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

.success-icon {
    background: linear-gradient(135deg, var(--success), var(--success-light));
}

.error-icon {
    background: linear-gradient(135deg, var(--error), var(--error-light));
}

.pending-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

/* Title Section */
.verify-title-section {
    text-align: center;
    margin-bottom: 2rem;
}

.verify-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.verify-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Alert Messages */
.verify-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
    animation: slideIn 0.4s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.error-alert {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(248, 113, 113, 0.05));
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.success-alert {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.error-alert .alert-icon {
    background: var(--error);
    color: white;
}

.success-alert .alert-icon {
    background: var(--success);
    color: white;
}

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.error-alert .alert-content strong {
    color: #991b1b;
}

.success-alert .alert-content strong {
    color: #065f46;
}

.alert-content p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.6;
}

.error-alert .alert-content p {
    color: #b91c1c;
}

.success-alert .alert-content p {
    color: #047857;
}

/* Content Section */
.verify-content {
    margin-bottom: 2rem;
}

/* Email Info Card */
.email-info-card {
    background: linear-gradient(135deg, var(--bg-light), var(--bg-white));
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.email-info-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.email-info-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.email-info-content p {
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
}

.email-address {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--bg-white);
    border: 1px solid var(--primary);
    border-radius: 2rem;
    color: var(--primary);
    font-size: 0.95rem;
}

/* Instructions */
.verify-instructions {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.verify-instructions h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.verify-instructions ol {
    margin: 0;
    padding-left: 1.5rem;
    color: var(--text-secondary);
}

.verify-instructions li {
    margin-bottom: 0.5rem;
    line-height: 1.6;
}

/* Resend Section */
.resend-section {
    text-align: center;
    margin-bottom: 1.5rem;
}

.resend-text {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.resend-form {
    display: flex;
    justify-content: center;
}

/* Success Message */
.verify-content.verified {
    text-align: center;
}

.success-message {
    margin-bottom: 2rem;
}

.success-icon-large {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success), var(--success-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    animation: successPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes successPop {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.success-message h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.success-message p {
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Verified Benefits */
.verified-benefits {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    text-align: left;
}

.verified-benefits h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.verified-benefits ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.verified-benefits li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
    transition: var(--transition);
}

.verified-benefits li:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow);
}

.verified-benefits li i {
    color: var(--success);
    font-size: 1rem;
}

.verified-benefits li span {
    color: var(--text-secondary);
}

/* Help Section */
.verify-help {
    text-align: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.verify-help p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.verify-help a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.verify-help a:hover {
    text-decoration: underline;
}

/* Footer */
.verify-footer {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

/* Additional Info */
.verify-additional-info {
    position: relative;
    z-index: 1;
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    font-size: 0.9rem;
    opacity: 0.9;
}

.info-item i {
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .verify-email-container {
        padding: 1.5rem;
    }

    .verify-email-card {
        padding: 2rem 1.5rem;
    }

    .verify-icon {
        width: 80px;
        height: 80px;
        font-size: 2.5rem;
    }

    .verify-title {
        font-size: 1.5rem;
    }

    .verify-subtitle {
        font-size: 1rem;
    }

    .verify-footer {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .verify-additional-info {
        flex-direction: column;
        gap: 1rem;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .verify-email-card {
        padding: 1.5rem 1rem;
    }

    .verify-icon {
        width: 70px;
        height: 70px;
        font-size: 2rem;
    }

    .verify-title {
        font-size: 1.25rem;
    }

    .alert-icon {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
}

/* Loading Animation */
@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

/* Print Styles */
@media print {
    .verify-email-container {
        background: white;
    }

    .verify-footer,
    .verify-additional-info {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to alerts if present
    const alert = document.querySelector('.verify-alert');
    if (alert) {
        setTimeout(() => {
            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
    }

    // Add form submission animation
    const form = document.querySelector('.resend-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button');
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                button.disabled = true;
            }
        });
    }

    // Add hover effects to benefits list
    const benefitItems = document.querySelectorAll('.verified-benefits li');
    benefitItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.style.animation = 'slideIn 0.5s ease-out forwards';
    });

    console.log('✅ Verificación de email - UI inicializada');
});
</script>

<?php 
if ($session->isLoggedIn()) {
    include '../includes/footer.php';
} else {
    include '../includes/footer.php';
}
?>