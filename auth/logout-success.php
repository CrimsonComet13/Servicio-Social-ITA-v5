<?php
/**
 * Página de confirmación de logout exitoso - Versión simplificada
 */

// Configuración básica
require_once '../config/config.php';

// Obtener tipo de logout
$logoutType = $_GET['logout'] ?? 'success';

// Mensajes según el tipo de logout
$messages = [
    'success' => [
        'title' => 'Sesión Cerrada Exitosamente',
        'message' => 'Tu sesión se ha cerrado correctamente.',
        'icon' => 'fa-check-circle',
        'color' => '#10b981'
    ],
    'forced' => [
        'title' => 'Sesión Cerrada',
        'message' => 'Tu sesión fue cerrada por razones de seguridad.',
        'icon' => 'fa-exclamation-triangle',
        'color' => '#f59e0b'
    ],
    'error' => [
        'title' => 'Sesión Cerrada',
        'message' => 'Hubo un problema pero la sesión se cerró correctamente.',
        'icon' => 'fa-info-circle',
        'color' => '#3b82f6'
    ],
    'timeout' => [
        'title' => 'Sesión Cerrada por Timeout',
        'message' => 'La sesión se cerró debido a inactividad.',
        'icon' => 'fa-clock',
        'color' => '#f59e0b'
    ],
    'emergency' => [
        'title' => 'Sesión Cerrada de Emergencia',
        'message' => 'Se ejecutó un cierre de sesión de emergencia.',
        'icon' => 'fa-power-off',
        'color' => '#ef4444'
    ]
];

$currentMessage = $messages[$logoutType] ?? $messages['success'];

// Título de la página
$pageTitle = "Logout - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta http-equiv="refresh" content="10;url=../index.php">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #1f2937;
        }

        .logout-container {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            color: white;
            font-size: 2rem;
            background: <?= $currentMessage['color'] ?>;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .logout-title {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .logout-message {
            color: #6b7280;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b8cf7);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .redirect-info {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #9ca3af;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .countdown {
            font-weight: 600;
            color: #6366f1;
        }

        .loading-dots {
            display: inline-flex;
            gap: 4px;
        }

        .loading-dots span {
            width: 4px;
            height: 4px;
            background: #9ca3af;
            border-radius: 50%;
            animation: loadingDots 1.4s infinite ease-in-out both;
        }

        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes loadingDots {
            0%, 80%, 100% {
                transform: scale(0);
            }
            40% {
                transform: scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .logout-container {
                padding: 2rem;
                margin: 1rem;
            }

            .logout-title {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas <?= $currentMessage['icon'] ?>"></i>
        </div>
        
        <h1 class="logout-title"><?= htmlspecialchars($currentMessage['title']) ?></h1>
        <p class="logout-message"><?= htmlspecialchars($currentMessage['message']) ?></p>
        
        <div class="action-buttons">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Página Principal
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </a>
        </div>
        
        <div class="redirect-info">
            <i class="fas fa-info-circle"></i>
            <span>Redirigiendo automáticamente en <span class="countdown" id="countdown">10</span> segundos</span>
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>

    <script>
        // Contador de redirección simplificado
        let countdown = 10;
        const countdownEl = document.getElementById('countdown');
        
        function updateCountdown() {
            if (countdown > 0) {
                countdownEl.textContent = countdown;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                window.location.href = '../index.php';
            }
        }
        
        // Iniciar countdown
        setTimeout(updateCountdown, 1000);
        
        // Redirección de respaldo
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 12000);
        
        // Limpiar storage del navegador de forma segura
        try {
            const keysToRemove = [
                'user_preferences', 'dashboard_cache', 'form_drafts',
                'auth_token', 'user_session', 'ita_social_session', 'remember_token'
            ];
            
            keysToRemove.forEach(key => {
                try {
                    localStorage.removeItem(key);
                    sessionStorage.removeItem(key);
                } catch (e) {
                    // Ignorar errores individuales
                }
            });
            
            console.log('Storage limpiado correctamente');
        } catch (e) {
            console.warn('No se pudo limpiar el storage:', e);
        }
        
        // Prevenir botón de retroceso
        try {
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function () {
                window.history.go(1);
            };
        } catch (e) {
            console.warn('No se pudo configurar prevención de retroceso:', e);
        }
        
        console.log('Página de logout cargada - Tipo:', '<?= $logoutType ?>');
    </script>
</body>
</html>