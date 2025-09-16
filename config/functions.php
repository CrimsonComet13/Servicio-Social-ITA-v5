<?php
require_once 'config.php';
require_once 'database.php';

// Funciones de utilidad global

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function logActivity($userId, $action, $module, $recordId = null, $details = null) {
    $db = Database::getInstance();
    
    $data = [
        'usuario_id' => $userId,
        'accion' => $action,
        'modulo' => $module,
        'registro_afectado_id' => $recordId,
        'detalles' => $details ? json_encode($details) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    return $db->insert('log_actividades', $data);
}

function createNotification($userId, $title, $message, $type = 'info', $actionUrl = null) {
    $db = Database::getInstance();
    
    $data = [
        'usuario_id' => $userId,
        'titulo' => $title,
        'mensaje' => $message,
        'tipo' => $type,
        'url_accion' => $actionUrl
    ];
    
    return $db->insert('notificaciones', $data);
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    $dateTime = new DateTime($datetime);
    return $dateTime->format($format);
}

function generateNumeroOficio() {
    $db = Database::getInstance();
    $year = date('Y');
    
    // Obtener el último número del año actual
    $sql = "SELECT numero_oficio FROM oficios_presentacion 
            WHERE numero_oficio LIKE :pattern 
            ORDER BY id DESC LIMIT 1";
    
    $pattern = "ITA-SS-{$year}-%";
    $result = $db->fetch($sql, ['pattern' => $pattern]);
    
    if ($result) {
        // Extraer el número secuencial y aumentarlo
        preg_match('/ITA-SS-' . $year . '-(\d+)/', $result['numero_oficio'], $matches);
        $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    
    return sprintf('ITA-SS-%s-%04d', $year, $nextNumber);
}

function uploadFile($file, $directory, $allowedTypes = ['pdf'], $maxSize = MAX_UPLOAD_SIZE) {
    // Validar errores de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo: ' . $file['error']);
    }
    
    // Validar tamaño
    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande. Máximo: ' . ($maxSize / 1024 / 1024) . 'MB');
    }
    
    // Validar tipo
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowedTypes));
    }
    
    // Generar nombre único
    $fileName = uniqid() . '_' . time() . '.' . $fileExt;
    $uploadPath = UPLOAD_PATH . $directory . '/';
    
    // Crear directorio si no existe
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    $fullPath = $uploadPath . $fileName;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception('Error al mover el archivo al directorio de destino');
    }
    
    return $directory . '/' . $fileName;
}

function deleteFile($filePath) {
    $fullPath = UPLOAD_PATH . $filePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

function getConfig($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $db = Database::getInstance();
        $results = $db->fetchAll("SELECT clave, valor, tipo FROM configuracion_sistema");
        $config = [];
        
        foreach ($results as $row) {
            $value = $row['valor'];
            
            // Convertir según el tipo
            switch ($row['tipo']) {
                case 'integer':
                    $value = intval($value);
                    break;
                case 'boolean':
                    $value = $value === 'true' || $value === '1';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $config[$row['clave']] = $value;
        }
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

function setConfig($key, $value, $type = 'string', $description = '') {
    $db = Database::getInstance();
    
    // Convertir valor según el tipo
    switch ($type) {
        case 'boolean':
            $value = $value ? 'true' : 'false';
            break;
        case 'json':
            $value = json_encode($value);
            break;
        default:
            $value = strval($value);
    }
    
    $sql = "INSERT INTO configuracion_sistema (clave, valor, tipo, descripcion) 
            VALUES (:clave, :valor, :tipo, :descripcion)
            ON DUPLICATE KEY UPDATE 
            valor = VALUES(valor), tipo = VALUES(tipo), descripcion = VALUES(descripcion)";
    
    return $db->query($sql, [
        'clave' => $key,
        'valor' => $value,
        'tipo' => $type,
        'descripcion' => $description
    ]);
}

function redirectTo($url) {
    header("Location: " .$url);
    exit();
}

function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = "El campo $field es requerido";
        }
    }
    return $errors;
}

function validateNumeroControl($numeroControl) {
    return preg_match('/^\d{8}$/', $numeroControl);
}

function calculateAge($birthDate) {
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    return $today->diff($birth)->y;
}

function sendEmail($to, $subject, $body, $isHtml = true) {
    // Configurar headers básicos
    $headers = [];
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: Sistema Servicio Social ITA';
    
    if ($isHtml) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    $headerString = implode("\r\n", $headers);
    
    // Enviar email
    return mail($to, $subject, $body, $headerString);
}

function getEstadoBadgeClass($estado) {
    $classes = [
        'pendiente' => 'badge-warning',
        'aprobada' => 'badge-success',
        'aprobado' => 'badge-success',
        'en_proceso' => 'badge-info',
        'evaluado' => 'badge-info',
        'concluido' => 'badge-success',
        'concluida' => 'badge-success',
        'rechazado' => 'badge-error',
        'rechazada' => 'badge-error',
        'cancelado' => 'badge-secondary',
        'cancelada' => 'badge-secondary'
    ];
    
    return $classes[$estado] ?? 'badge-secondary';
}

function getEstadoText($estado) {
    $texts = [
        'sin_solicitud' => 'Sin Solicitud',
        'solicitud_pendiente' => 'Solicitud Pendiente',
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'aprobado' => 'Aprobado',
        'en_proceso' => 'En Proceso',
        'evaluado' => 'Evaluado',
        'concluido' => 'Concluido',
        'concluida' => 'Concluida',
        'rechazado' => 'Rechazado',
        'rechazada' => 'Rechazada',
        'cancelado' => 'Cancelado',
        'cancelada' => 'Cancelada'
    ];
    
    return $texts[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

function generateNumeroConstancia() {
    $db = Database::getInstance();
    $year = date('Y');
    
    // Obtener el último número del año actual
    $sql = "SELECT numero_constancia FROM constancias 
            WHERE numero_constancia LIKE :pattern 
            ORDER BY id DESC LIMIT 1";
    
    $pattern = "CONST-ITA-{$year}-%";
    $result = $db->fetch($sql, ['pattern' => $pattern]);
    
    if ($result) {
        // Extraer el número secuencial y aumentarlo
        preg_match('/CONST-ITA-' . $year . '-(\d+)/', $result['numero_constancia'], $matches);
        $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    
    return sprintf('CONST-ITA-%s-%04d', $year, $nextNumber);
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

function isValidFileType($filename, $allowedTypes = ['pdf']) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedTypes);
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image'
    ];
    
    return $icons[$extension] ?? 'fas fa-file';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'hace unos segundos';
    if ($time < 3600) return 'hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'hace ' . floor($time/86400) . ' días';
    if ($time < 31104000) return 'hace ' . floor($time/2592000) . ' meses';
    
    return 'hace ' . floor($time/31104000) . ' años';
}

function generateSlug($text) {
    // Convertir a minúsculas y reemplazar espacios y caracteres especiales
    $text = strtolower($text);
    $text = preg_replace('/[áàâäã]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[íìîï]/u', 'i', $text);
    $text = preg_replace('/[óòôöõ]/u', 'o', $text);
    $text = preg_replace('/[úùûü]/u', 'u', $text);
    $text = preg_replace('/[ñ]/u', 'n', $text);
    $text = preg_replace('/[^a-z0-9\-_]/', '-', $text);
    $text = preg_replace('/[-_]+/', '-', $text);
    $text = trim($text, '-_');
    
    return $text;
}

function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) != 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    if (strlen($username) <= 2) {
        $maskedUsername = str_repeat('*', strlen($username));
    } else {
        $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . $username[-1];
    }
    
    return $maskedUsername . '@' . $domain;
}

function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function formatCurrency($amount, $currency = 'MXN') {
    switch ($currency) {
        case 'MXN':
            return '$' . number_format($amount, 2, '.', ',');
        case 'USD':
            return 'US$' . number_format($amount, 2, '.', ',');
        default:
            return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
}

function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra mayúscula';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra minúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Debe contener al menos un número';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Debe contener al menos un carácter especial';
    }
    
    return $errors;
}

function cleanFileName($filename) {
    // Remover caracteres especiales y espacios
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    $filename = trim($filename, '_');
    
    return $filename;
}

function getRandomColor() {
    $colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c',
        '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
        '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3',
        '#fdcbf1', '#e6dee9', '#ffd89b', '#19547b'
    ];
    
    return $colors[array_rand($colors)];
}

function shortenText($text, $length = 150, $ending = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $ending;
}

function arrayToCSV($array, $filename = null) {
    if ($filename) {
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
    
    $output = fopen('php://output', 'w');
    
    if (!empty($array)) {
        // Escribir encabezados
        fputcsv($output, array_keys($array[0]));
        
        // Escribir datos
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function debugLog($message, $data = null) {
    if (APP_DEBUG) {
        $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        
        if ($data !== null) {
            $logMessage .= ' | Data: ' . json_encode($data);
        }
        
        error_log($logMessage);
    }
}

function generateQRCode($data, $size = 200) {
    // Usar una librería externa como phpqrcode o un servicio web
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}

// Función para limpiar y validar datos de entrada
function validateAndClean($data, $rules) {
    $cleanData = [];
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        // Aplicar limpieza básica
        if (is_string($value)) {
            $value = sanitizeInput($value);
        }
        
        // Validar requerido
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "El campo {$field} es requerido";
            continue;
        }
        
        // Validar tipo
        if (!empty($value) && isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!validateEmail($value)) {
                        $errors[$field] = "El campo {$field} debe ser un email válido";
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$field] = "El campo {$field} debe ser un número";
                    }
                    break;
                case 'date':
                    if (!strtotime($value)) {
                        $errors[$field] = "El campo {$field} debe ser una fecha válida";
                    }
                    break;
            }
        }
        
        // Validar longitud
        if (!empty($value) && isset($rule['max_length'])) {
            if (strlen($value) > $rule['max_length']) {
                $errors[$field] = "El campo {$field} no debe exceder {$rule['max_length']} caracteres";
            }
        }
        
        if (!empty($value) && isset($rule['min_length'])) {
            if (strlen($value) < $rule['min_length']) {
                $errors[$field] = "El campo {$field} debe tener al menos {$rule['min_length']} caracteres";
            }
        }
        
        $cleanData[$field] = $value;
    }
    
    return ['data' => $cleanData, 'errors' => $errors];
}

// Funciones para manejo de sesiones de estudiantes específicas
function getEstudianteData($usuarioId) {
    $db = Database::getInstance();
    return $db->fetch("
        SELECT e.*, u.email 
        FROM estudiantes e 
        JOIN usuarios u ON e.usuario_id = u.id 
        WHERE e.usuario_id = ?
    ", [$usuarioId]);
}

function getJefeDepartamentoData($usuarioId) {
    $db = Database::getInstance();
    return $db->fetch("
        SELECT jd.*, u.email 
        FROM jefes_departamento jd 
        JOIN usuarios u ON jd.usuario_id = u.id 
        WHERE jd.usuario_id = ?
    ", [$usuarioId]);
}

function getJefeLaboratorioData($usuarioId) {
    $db = Database::getInstance();
    return $db->fetch("
        SELECT jl.*, u.email, jd.departamento 
        FROM jefes_laboratorio jl 
        JOIN usuarios u ON jl.usuario_id = u.id 
        JOIN jefes_departamento jd ON jl.jefe_departamento_id = jd.id
        WHERE jl.usuario_id = ?
    ", [$usuarioId]);
}

?>