<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Script para crear usuario administrador (jefe de departamento)
function createAdminUser() {
    $db = Database::getInstance();
    
    echo "=== Creación de Usuario Administrador (Jefe de Departamento) ===\n\n";
    
    // Solicitar datos del administrador
    echo "Por favor, ingrese los datos del jefe de departamento:\n";
    
    $email = readline("Email: ");
    $password = readline("Contraseña: ");
    $confirmPassword = readline("Confirmar contraseña: ");
    $nombre = readline("Nombre completo: ");
    $departamento = readline("Departamento: ");
    $telefono = readline("Teléfono (opcional): ");
    $extension = readline("Extensión (opcional): ");
    
    // Validaciones básicas
    if (empty($email) || !validateEmail($email)) {
        echo "Error: Email inválido.\n";
        return false;
    }
    
    if (empty($password) || $password !== $confirmPassword) {
        echo "Error: Las contraseñas no coinciden o están vacías.\n";
        return false;
    }
    
    if (empty($nombre) || empty($departamento)) {
        echo "Error: Nombre y departamento son obligatorios.\n";
        return false;
    }
    
    // Verificar si el email ya existe
    $existingUser = $db->fetch("SELECT id FROM usuarios WHERE email = ?", [$email]);
    if ($existingUser) {
        echo "Error: Ya existe un usuario con este email.\n";
        return false;
    }
    
    try {
        $db->beginTransaction();
        
        // Crear usuario
        $userId = $db->insert('usuarios', [
            'email' => $email,
            'password' => hashPassword($password),
            'tipo_usuario' => 'jefe_departamento',
            'activo' => true,
            'email_verificado' => true
        ]);
        
        // Crear jefe de departamento
        $jefeId = $db->insert('jefes_departamento', [
            'usuario_id' => $userId,
            'nombre' => $nombre,
            'departamento' => $departamento,
            'telefono' => $telefono ?: null,
            'extension' => $extension ?: null,
            'puede_evaluar_laboratorio' => true
        ]);
        
        $db->commit();
        
        echo "\n✅ Usuario administrador creado exitosamente!\n";
        echo "ID de usuario: $userId\n";
        echo "ID de jefe de departamento: $jefeId\n";
        echo "Email: $email\n";
        echo "Rol: jefe_departamento\n\n";
        
        return true;
        
    } catch (Exception $e) {
        $db->rollback();
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Ejecutar solo si se llama directamente por línea de comandos
if (php_sapi_name() === 'cli') {
    createAdminUser();
} else {
    echo "Este script solo puede ejecutarse desde la línea de comandos.\n";
    exit(1);
}
?>