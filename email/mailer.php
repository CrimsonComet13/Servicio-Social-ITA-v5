<?php
require_once '../config/config.php';
require_once '../config/functions.php';

class EmailManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function sendWelcomeEmail($userId) {
        $user = $this->db->fetch("
            SELECT u.*, e.nombre 
            FROM usuarios u 
            LEFT JOIN estudiantes e ON u.id = e.usuario_id 
            LEFT JOIN jefes_departamento jd ON u.id = jd.usuario_id 
            LEFT JOIN jefes_laboratorio jl ON u.id = jl.usuario_id 
            WHERE u.id = ?
        ", [$userId]);
        
        if (!$user) {
            return false;
        }
        
        $template = $this->getTemplate('welcome', [
            'nombre' => $user['nombre'] ?? $user['email'],
            'email' => $user['email'],
            'tipo_usuario' => $user['tipo_usuario'],
            'app_name' => APP_NAME,
            'base_url' => BASE_URL
        ]);
        
        return $this->sendEmail(
            $user['email'],
            'Bienvenido a ' . APP_NAME,
            $template
        );
    }
    
    public function sendPasswordResetEmail($userId) {
        $user = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$userId]);
        
        if (!$user) {
            return false;
        }
        
        // Generar token de recuperación
        $resetToken = generateToken();
        $resetTokenExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
        
        $this->db->update('usuarios', [
            'reset_token' => $resetToken,
            'reset_token_expires' => $resetTokenExpires
        ], 'id = :id', ['id' => $userId]);
        
        $resetLink = BASE_URL . "auth/reset-password.php?token=$resetToken";
        
        $template = $this->getTemplate('reset-password', [
            'nombre' => $user['email'],
            'reset_link' => $resetLink,
            'app_name' => APP_NAME
        ]);
        
        return $this->sendEmail(
            $user['email'],
            'Recuperación de Contraseña - ' . APP_NAME,
            $template
        );
    }
    
    public function sendVerificationEmail($userId) {
        $user = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$userId]);
        
        if (!$user) {
            return false;
        }
        
        // Generar token de verificación si no existe
        if (empty($user['token_verificacion'])) {
            $token = generateToken();
            $this->db->update('usuarios', [
                'token_verificacion' => $token
            ], 'id = :id', ['id' => $userId]);
        } else {
            $token = $user['token_verificacion'];
        }
        
        $verifyLink = BASE_URL . "auth/verify-email.php?token=$token";
        
        $template = $this->getTemplate('verify-email', [
            'nombre' => $user['email'],
            'verify_link' => $verifyLink,
            'app_name' => APP_NAME
        ]);
        
        return $this->sendEmail(
            $user['email'],
            'Verificación de Email - ' . APP_NAME,
            $template
        );
    }
    
    public function sendNotificationEmail($userId, $subject, $message, $actionUrl = null) {
        $user = $this->db->fetch("SELECT * FROM usuarios WHERE id = ?", [$userId]);
        
        if (!$user) {
            return false;
        }
        
        $template = $this->getTemplate('notification', [
            'nombre' => $user['email'],
            'message' => $message,
            'action_url' => $actionUrl,
            'app_name' => APP_NAME
        ]);
        
        return $this->sendEmail(
            $user['email'],
            $subject . ' - ' . APP_NAME,
            $template
        );
    }
    
    private function getTemplate($templateName, $data) {
        $templateFile = __DIR__ . "/templates/$templateName.html";
        
        if (!file_exists($templateFile)) {
            throw new Exception("Plantilla $templateName no encontrada");
        }
        
        $template = file_get_contents($templateFile);
        
        // Reemplazar variables en la plantilla
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }
        
        return $template;
    }
    
    private function sendEmail($to, $subject, $body) {
        // Configurar headers
        $headers = [
            'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
            'Reply-To: ' . FROM_EMAIL,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8'
        ];
        
        // En entorno de desarrollo, guardar en log en lugar de enviar
        if (APP_DEBUG) {
            $logMessage = "Email simulado para: $to\n";
            $logMessage .= "Asunto: $subject\n";
            $logMessage .= "Cuerpo: $body\n";
            $logMessage .= "Headers: " . implode("\n", $headers) . "\n";
            
            error_log($logMessage);
            return true;
        }
        
        // En producción, enviar email real
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

// Función helper para enviar emails
function sendEmailNotification($userId, $title, $message, $type = 'info') {
    $emailManager = new EmailManager();
    
    try {
        switch ($type) {
            case 'welcome':
                return $emailManager->sendWelcomeEmail($userId);
            case 'password_reset':
                return $emailManager->sendPasswordResetEmail($userId);
            case 'verification':
                return $emailManager->sendVerificationEmail($userId);
            default:
                return $emailManager->sendNotificationEmail($userId, $title, $message);
        }
    } catch (Exception $e) {
        error_log('Error enviando email: ' . $e->getMessage());
        return false;
    }
}
?>