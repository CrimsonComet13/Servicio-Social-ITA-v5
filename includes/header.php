<?php
// Evitar acceso directo
defined('APP_NAME') or die('Acceso restringido');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/logo-ita.png">
</head>
<body>
    <?php if (isset($session) && $session->isLoggedIn()): ?>
    <header class="main-header">
        <div class="header-container">
            <div class="header-logo">
                <img src="../assets/images/logo-ita.png" alt="ITA Logo">
                <span><?= APP_NAME ?></span>
            </div>
            
            <nav class="header-nav">
                <ul>
                    <li><a href="../dashboard/<?= $session->getUserRole() ?>.php">Dashboard</a></li>
                    <li><a href="../auth/logout.php">Cerrar Sesión</a></li>
                </ul>
            </nav>
            
            <div class="header-user">
                <span><?= $session->getUser()['nombre'] ?? $session->getUser()['email'] ?></span>
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <main>