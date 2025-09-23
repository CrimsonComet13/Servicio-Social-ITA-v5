<?php
/**
 * Página de confirmación de logout exitoso
 */

require_once '../config/config.php';

// Verificar si viene de un logout
$logoutType = $_GET['logout'] ?? 'success';
$messages = [
    'success' => [
        'title' => 'Sesión Cerrada Exitosamente',
        'message' => 'Tu sesión se ha cerrado correctamente. Gracias por usar el sistema de servicio social del ITA.',
        'icon' => '✓',
        'color' => 'success'
    ],
    'forced' => [
        'title' => 'Sesión Cerrada Forzadamente',
        'message' => 'Tu sesión fue cerrada por razones de seguridad. Todos los datos de sesión han sido limpiados.',
        'icon' => '⚠',
        'color' => 'warning'
    ],
    'error' => [
        'title' => 'Error al Cerrar Sesión',
        'message' => 'Hubo un problema al cerrar la sesión, pero se ha limpiado correctamente. Puedes continuar navegando.',
        'icon' => '!',
        'color' => 'error'
    ],
    'fallback' => [
        'title' => 'Sesión Cerrada',
        'message' => 'El proceso de logout se completó. Tu sesión ya no está activa.',
        'icon' => 'i',
        'color' => 'info'
    ]
];

$currentMessage = $messages[$logoutType] ?? $messages['success'];

$pageTitle = "Logout - " . APP_NAME;
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta http-equiv="refresh" content="5;url=../index.php">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #8b8cf7;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --bg-light: #f8fafc;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .logout-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 3rem;
            box-shadow: var(--shadow-lg);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
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
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        .logout-icon.success {
            background: linear-gradient(135deg, var(--success-color), #34d399);
        }

        .logout-icon.error {
            background: linear-gradient(135deg, var(--error-color), #f87171);
        }

        .logout-icon.warning {
            background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        }

        .logout-icon.info {
            background: linear-gradient(135deg, var(--info-color), #60a5fa);
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
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .logout-message {
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--text-secondary), #4b5563);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .redirect-info {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--text-light);
            padding: 1rem;
            background: var(--bg-light);
            border-radius: var(--radius);
            border-left: 4px solid var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .loading-dots {
            display: inline-flex;
            gap: 4px;
        }

        .loading-dots span {
            width: 4px;
            height: 4px;
            background: var(--text-light);
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
        <div class="logout-icon <?= $currentMessage['color'] ?>">
            <?= $currentMessage['icon'] ?>
        </div>
        
        <h1 class="logout-title"><?= htmlspecialchars($currentMessage['title']) ?></h1>
        <p class="logout-message"><?= htmlspecialchars($currentMessage['message']) ?></p>
        
        <div class="action-buttons">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Ir a Página Principal
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </a>
        </div>
        
        <div class="redirect-info">
            <i class="fas fa-info-circle"></i>
            <span id="redirect-text">Redirigiendo automáticamente en 5 segundos</span>
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>

    <script>
        // Contador de redirección
        let countdown = 5;
        const redirectText = document.getElementById('redirect-text');
        
        function updateCountdown() {
            if (countdown > 0) {
                redirectText.textContent = `Redirigiendo automáticamente en ${countdown} segundo${countdown !== 1 ? 's' : ''}`;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                redirectText.textContent = 'Redirigiendo ahora...';
                window.location.href = '../index.php';
            }
        }
        
        // Iniciar countdown después de 1 segundo
        setTimeout(updateCountdown, 1000);
        
        // Redirección de seguridad
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 6000);
        
        // Limpiar storage del navegador
        try {
            const keysToRemove = [
                'user_preferences',
                'dashboard_cache', 
                'form_drafts',
                'auth_token',
                'user_session',
                'ita_social_session',
                'remember_token'
            ];
            
            keysToRemove.forEach(key => {
                localStorage.removeItem(key);
                sessionStorage.removeItem(key);
            });
            
            console.log('Storage limpiado exitosamente');
        } catch(e) {
            console.warn('Error limpiando storage:', e);
        }
        
        // Prevenir botón de retroceso
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function () {
            window.history.go(1);
        };
    </script>
</body>
</html>