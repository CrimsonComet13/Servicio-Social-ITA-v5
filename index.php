<?php
require_once 'config/config.php';
require_once 'config/session.php';

$session = SecureSession::getInstance();

// Redirigir usuarios logueados a su dashboard
if ($session->isLoggedIn()) {
    $userRole = $session->getUserRole();
    header("Location: /dashboard/$userRole.php");
    exit();
}

// Página de inicio para usuarios no autenticados
$pageTitle = "Sistema de Servicio Social - " . APP_NAME;
include 'includes/header.php';
?>

<div class="modern-landing">
    <!-- Navigation Enhancement (if not in header.php) -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <div class="brand-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span class="brand-text">ITA Social</span>
            </div>
            
            <div class="nav-actions">
                <a href="/auth/login.php" class="btn btn-ghost">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </a>
                <a href="/auth/register.php" class="btn btn-primary">
                    <i class="fas fa-rocket"></i>
                    Registrarse
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-text">
                    <div class="hero-badge">
                        <i class="fas fa-star"></i>
                        <span>Sistema Oficial ITA</span>
                    </div>
                    
                    <h1 class="hero-title">
                        Sistema de Gestión de 
                        <span class="gradient-text">Servicio Social</span>
                    </h1>
                    
                    <p class="hero-subtitle">Instituto Tecnológico de Aguascalientes</p>
                    
                    <p class="hero-description">
                        Gestiona tu servicio social de manera eficiente y mantén el control de todo el proceso 
                        con nuestra plataforma moderna, intuitiva y completamente digital.
                    </p>
                    
                    <div class="hero-actions">
                        <a href="/auth/register.php" class="btn btn-primary btn-large">
                            <i class="fas fa-rocket"></i>
                            Comenzar Ahora
                        </a>
                        <a href="/auth/login.php" class="btn btn-secondary btn-large">
                            <i class="fas fa-sign-in-alt"></i>
                            Iniciar Sesión
                        </a>
                    </div>
                    
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-number">2,500+</div>
                            <div class="stat-label">Estudiantes Activos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">98%</div>
                            <div class="stat-label">Satisfacción</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Disponibilidad</div>
                        </div>
                    </div>
                </div>
                
                <div class="hero-visual">
                    <div class="dashboard-mockup">
                        <div class="mockup-header">
                            <div class="mockup-dots">
                                <span class="dot red"></span>
                                <span class="dot yellow"></span>
                                <span class="dot green"></span>
                            </div>
                            <div class="mockup-title">Dashboard - Sistema ITA</div>
                        </div>
                        
                        <div class="mockup-content">
                            <div class="mockup-cards">
                                <div class="mockup-card primary">
                                    <div class="card-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="card-data">
                                        <div class="card-number">24</div>
                                        <div class="card-label">Solicitudes</div>
                                        <div class="card-trend positive">
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+12%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mockup-card success">
                                    <div class="card-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="card-data">
                                        <div class="card-number">480</div>
                                        <div class="card-label">Horas Completadas</div>
                                        <div class="card-trend positive">
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+8%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mockup-chart">
                                <div class="chart-header">
                                    <span>Progreso Mensual</span>
                                    <div class="chart-options">
                                        <span class="option active">6M</span>
                                        <span class="option">1A</span>
                                    </div>
                                </div>
                                <div class="chart-bars">
                                    <div class="chart-bar" style="height: 45%"></div>
                                    <div class="chart-bar" style="height: 65%"></div>
                                    <div class="chart-bar" style="height: 78%"></div>
                                    <div class="chart-bar" style="height: 52%"></div>
                                    <div class="chart-bar active" style="height: 92%"></div>
                                    <div class="chart-bar" style="height: 68%"></div>
                                    <div class="chart-bar" style="height: 85%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">¿Qué puedes hacer con nuestro sistema?</h2>
                <p class="section-subtitle">
                    Descubre todas las funcionalidades diseñadas para optimizar tu experiencia
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Gestión de Solicitudes</h3>
                        <p>Solicita tu servicio social y realiza el seguimiento de tu proceso de aprobación en tiempo real con notificaciones automáticas</p>
                        <div class="feature-benefits">
                            <span class="benefit">• Proceso 100% digital</span>
                            <span class="benefit">• Seguimiento en tiempo real</span>
                            <span class="benefit">• Notificaciones automáticas</span>
                        </div>
                    </div>
                    <div class="feature-action">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Reportes Bimestrales</h3>
                        <p>Registra tus actividades y horas cumplidas en cada periodo bimestral con formularios inteligentes y validación automática</p>
                        <div class="feature-benefits">
                            <span class="benefit">• Formularios inteligentes</span>
                            <span class="benefit">• Validación automática</span>
                            <span class="benefit">• Recordatorios programados</span>
                        </div>
                    </div>
                    <div class="feature-action">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon warning">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Documentos Automáticos</h3>
                        <p>Genera oficios, cartas y constancias con formatos oficiales del ITA de manera instantánea y con validez legal</p>
                        <div class="feature-benefits">
                            <span class="benefit">• Formatos oficiales ITA</span>
                            <span class="benefit">• Generación instantánea</span>
                            <span class="benefit">• Validez legal garantizada</span>
                        </div>
                    </div>
                    <div class="feature-action">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon info">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Comunicación Directa</h3>
                        <p>Mantente en contacto con jefes de departamento y laboratorio a través de nuestro sistema de mensajería integrado</p>
                        <div class="feature-benefits">
                            <span class="benefit">• Chat en tiempo real</span>
                            <span class="benefit">• Historial de conversaciones</span>
                            <span class="benefit">• Notificaciones push</span>
                        </div>
                    </div>
                    <div class="feature-action">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Roles Section -->
    <section class="roles-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Para todos los roles</h2>
                <p class="section-subtitle">
                    Cada usuario tiene acceso a funcionalidades específicas diseñadas para su rol
                </p>
            </div>
            
            <div class="roles-container">
                <div class="roles-tabs">
                    <button class="role-tab active" data-role="estudiantes">
                        <div class="tab-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="tab-content">
                            <h4>Estudiantes</h4>
                            <span>Gestiona tu servicio social</span>
                        </div>
                    </button>
                    
                    <button class="role-tab" data-role="laboratorio">
                        <div class="tab-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="tab-content">
                            <h4>Jefes de Laboratorio</h4>
                            <span>Supervisa estudiantes</span>
                        </div>
                    </button>
                    
                    <button class="role-tab" data-role="departamento">
                        <div class="tab-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="tab-content">
                            <h4>Jefes de Departamento</h4>
                            <span>Administra el sistema</span>
                        </div>
                    </button>
                </div>
                
                <div class="role-content-area">
                    <!-- Estudiantes -->
                    <div class="role-panel active" id="estudiantes-panel">
                        <div class="role-grid">
                            <div class="role-features">
                                <div class="role-feature">
                                    <div class="feature-icon-small primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Solicitud de servicio social</h5>
                                        <p>Envía tu solicitud de manera digital con seguimiento en tiempo real</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small success">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Registro de reportes bimestrales</h5>
                                        <p>Lleva un control detallado y organizado de todas tus actividades</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Seguimiento de horas cumplidas</h5>
                                        <p>Monitorea tu progreso y visualiza estadísticas de cumplimiento</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small info">
                                        <i class="fas fa-download"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Descarga de documentos oficiales</h5>
                                        <p>Obtén todos tus documentos oficiales cuando los necesites</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="role-preview">
                                <div class="preview-card student">
                                    <div class="preview-header">
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <i class="fas fa-user-graduate"></i>
                                            </div>
                                            <div class="user-details">
                                                <h6>Panel de Estudiante</h6>
                                                <span>Mi Servicio Social</span>
                                            </div>
                                        </div>
                                        <div class="status-badge active">
                                            <span>Activo</span>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-section">
                                        <div class="progress-label">
                                            <span>Progreso Total</span>
                                            <span class="progress-value">75%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: 75%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="quick-stats">
                                        <div class="quick-stat">
                                            <span class="stat-number">360</span>
                                            <span class="stat-label">Horas</span>
                                        </div>
                                        <div class="quick-stat">
                                            <span class="stat-number">4</span>
                                            <span class="stat-label">Reportes</span>
                                        </div>
                                        <div class="quick-stat">
                                            <span class="stat-number">98%</span>
                                            <span class="stat-label">Calificación</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Laboratorio -->
                    <div class="role-panel" id="laboratorio-panel">
                        <div class="role-grid">
                            <div class="role-features">
                                <div class="role-feature">
                                    <div class="feature-icon-small primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Gestión de estudiantes asignados</h5>
                                        <p>Supervisa y coordina a todos los estudiantes bajo tu responsabilidad</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small success">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Evaluación de reportes bimestrales</h5>
                                        <p>Revisa, califica y proporciona retroalimentación detallada</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small warning">
                                        <i class="fas fa-list-check"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Registro de horas y actividades</h5>
                                        <p>Valida y certifica las actividades realizadas por estudiantes</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small info">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Comunicación con estudiantes</h5>
                                        <p>Mantén contacto directo y fluido con tu equipo de trabajo</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="role-preview">
                                <div class="preview-card lab">
                                    <div class="preview-header">
                                        <div class="user-info">
                                            <div class="user-avatar lab">
                                                <i class="fas fa-flask"></i>
                                            </div>
                                            <div class="user-details">
                                                <h6>Panel de Laboratorio</h6>
                                                <span>Gestión de Estudiantes</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="lab-stats">
                                        <div class="lab-stat primary">
                                            <div class="stat-icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-number">24</span>
                                                <span class="stat-label">Estudiantes Asignados</span>
                                            </div>
                                        </div>
                                        
                                        <div class="lab-stat warning">
                                            <div class="stat-icon">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-number">8</span>
                                                <span class="stat-label">Reportes Pendientes</span>
                                            </div>
                                        </div>
                                        
                                        <div class="lab-stat success">
                                            <div class="stat-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-info">
                                                <span class="stat-number">92%</span>
                                                <span class="stat-label">Tasa de Aprobación</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Departamento -->
                    <div class="role-panel" id="departamento-panel">
                        <div class="role-grid">
                            <div class="role-features">
                                <div class="role-feature">
                                    <div class="feature-icon-small primary">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Aprobación de solicitudes</h5>
                                        <p>Autoriza y gestiona las solicitudes de servicio social institucional</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small success">
                                        <i class="fas fa-network-wired"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Gestión de jefes de laboratorio</h5>
                                        <p>Coordina y supervisa el trabajo de todos los laboratorios</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small warning">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Generación de documentos oficiales</h5>
                                        <p>Crea y certifica documentos con validez institucional completa</p>
                                    </div>
                                </div>
                                
                                <div class="role-feature">
                                    <div class="feature-icon-small info">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                    <div class="feature-text">
                                        <h5>Reportes y estadísticas</h5>
                                        <p>Accede a métricas detalladas y análisis de rendimiento</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="role-preview">
                                <div class="preview-card dept">
                                    <div class="preview-header">
                                        <div class="user-info">
                                            <div class="user-avatar dept">
                                                <i class="fas fa-users-cog"></i>
                                            </div>
                                            <div class="user-details">
                                                <h6>Panel de Departamento</h6>
                                                <span>Administración General</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="dept-overview">
                                        <div class="overview-item">
                                            <span class="overview-label">Total Estudiantes</span>
                                            <span class="overview-value primary">2,486</span>
                                        </div>
                                        <div class="overview-item">
                                            <span class="overview-label">Solicitudes Pendientes</span>
                                            <span class="overview-value warning">47</span>
                                        </div>
                                        <div class="overview-item">
                                            <span class="overview-label">Tasa de Cumplimiento</span>
                                            <span class="overview-value success">94.2%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mini-chart">
                                        <div class="chart-title">Progreso Departamental</div>
                                        <div class="chart-container">
                                            <div class="chart-bar" style="height: 65%"></div>
                                            <div class="chart-bar" style="height: 78%"></div>
                                            <div class="chart-bar" style="height: 45%"></div>
                                            <div class="chart-bar" style="height: 89%"></div>
                                            <div class="chart-bar" style="height: 92%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <div class="cta-text">
                    <h2>¿Listo para optimizar tu servicio social?</h2>
                    <p>Únete a miles de estudiantes que ya están gestionando su servicio social de manera eficiente con nuestra plataforma digital.</p>
                </div>
                
                <div class="cta-actions">
                    <a href="/auth/register.php" class="btn btn-primary btn-large">
                        <i class="fas fa-rocket"></i>
                        Registrarse Gratis
                    </a>
                    <a href="/auth/login.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-sign-in-alt"></i>
                        Ya tengo cuenta
                    </a>
                </div>
                
                <div class="cta-features">
                    <div class="cta-feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>100% Seguro</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Acceso Móvil</span>
                    </div>
                    <div class="cta-feature">
                        <i class="fas fa-headset"></i>
                        <span>Soporte 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
:root {
    --primary: #6366f1;
    --primary-light: #8b8cf7;
    --primary-dark: #4f46e5;
    --secondary: #1f2937;
    --success: #10b981;
    --warning: #f59e0b;
    --info: #3b82f6;
    --danger: #ef4444;
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
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: var(--text-primary);
    background: var(--bg-light);
    overflow-x: hidden;
}

.modern-landing {
    width: 100%;
}

/* Navigation */
.top-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    z-index: 1000;
    padding: 1rem 0;
    transition: var(--transition);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.brand-logo {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: var(--shadow);
}

.brand-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.nav-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 2px solid var(--border);
    box-shadow: var(--shadow-sm);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
}

.btn-ghost:hover {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
}

/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
    color: white;
    padding: 8rem 0 6rem;
    margin-top: 80px;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(ellipse at center, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.hero-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    position: relative;
    z-index: 1;
}

.hero-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: var(--primary-light);
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 1rem;
}

.gradient-text {
    background: linear-gradient(135deg, var(--primary-light), #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.5rem;
    color: var(--primary-light);
    margin-bottom: 1rem;
    font-weight: 500;
}

.hero-description {
    font-size: 1.125rem;
    color: #d1d5db;
    margin-bottom: 2.5rem;
    line-height: 1.7;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 3rem;
}

.hero-stats {
    display: flex;
    gap: 3rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-light);
    display: block;
}

.stat-label {
    font-size: 0.875rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}

/* Dashboard Mockup */
.hero-visual {
    display: flex;
    justify-content: center;
    align-items: center;
}

.dashboard-mockup {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    width: 100%;
    max-width: 450px;
    box-shadow: var(--shadow-lg);
}

.mockup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.mockup-dots {
    display: flex;
    gap: 0.5rem;
}

.dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.dot.red { background: #ef4444; }
.dot.yellow { background: #f59e0b; }
.dot.green { background: #10b981; }

.mockup-title {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.mockup-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.mockup-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius);
    padding: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
}

.mockup-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.mockup-card.primary::before {
    background: var(--primary);
}

.mockup-card.success::before {
    background: var(--success);
}

.card-icon {
    font-size: 1.25rem;
    color: white;
    opacity: 0.9;
}

.card-data {
    flex: 1;
}

.card-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.card-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.8);
    margin-top: 0.25rem;
}

.card-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    margin-top: 0.5rem;
}

.card-trend.positive {
    color: var(--success);
}

.mockup-chart {
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius);
    padding: 1rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.chart-header span {
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
}

.chart-options {
    display: flex;
    gap: 0.5rem;
}

.option {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: var(--transition);
}

.option.active {
    background: rgba(99, 102, 241, 0.3);
    color: var(--primary-light);
}

.chart-bars {
    display: flex;
    align-items: end;
    gap: 0.5rem;
    height: 60px;
}

.chart-bar {
    background: linear-gradient(to top, var(--primary), var(--primary-light));
    border-radius: 2px 2px 0 0;
    width: 100%;
    min-height: 20%;
    transition: var(--transition);
    position: relative;
}

.chart-bar.active {
    background: linear-gradient(to top, var(--success), #34d399);
}

.chart-bar:hover {
    opacity: 0.8;
}

/* Features Section */
.features-section {
    padding: 6rem 0;
    background: var(--bg-white);
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.section-subtitle {
    font-size: 1.125rem;
    color: var(--text-secondary);
    max-width: 600px;
    margin: 0 auto;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    transform: scaleX(0);
    transition: var(--transition);
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.feature-card:hover::before {
    transform: scaleX(1);
}

.feature-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 1.5rem;
}

.feature-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.feature-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.feature-icon.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.feature-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.feature-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.feature-content p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.feature-benefits {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.benefit {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.feature-action {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    color: var(--primary);
    font-size: 1.25rem;
    transition: var(--transition);
}

.feature-card:hover .feature-action {
    transform: translateX(5px);
}

/* Roles Section */
.roles-section {
    padding: 6rem 0;
    background: var(--bg-light);
}

.roles-container {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.roles-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
}

.role-tab {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: none;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.role-tab::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary);
    transform: scaleX(0);
    transition: var(--transition);
}

.role-tab.active::after {
    transform: scaleX(1);
}

.role-tab:hover {
    background: var(--bg-light);
}

.role-tab.active {
    background: rgba(99, 102, 241, 0.05);
}

.tab-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: var(--bg-light);
    color: var(--text-secondary);
    transition: var(--transition);
}

.role-tab.active .tab-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.tab-content {
    text-align: left;
}

.tab-content h4 {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.tab-content span {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.role-content-area {
    position: relative;
}

.role-panel {
    display: none;
    padding: 3rem;
}

.role-panel.active {
    display: block;
}

.role-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: start;
}

.role-features {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.role-feature {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.feature-icon-small {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.feature-icon-small.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.feature-icon-small.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.feature-icon-small.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.feature-icon-small.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.feature-text h5 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.feature-text p {
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Role Previews */
.role-preview {
    display: flex;
    justify-content: center;
}

.preview-card {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    width: 100%;
    max-width: 350px;
    box-shadow: var(--shadow);
}

.preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.user-avatar.lab {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.user-avatar.dept {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.user-details h6 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-details span {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

/* Student Preview */
.progress-section {
    margin-bottom: 2rem;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-label span:first-child {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.progress-value {
    font-weight: 600;
    color: var(--primary);
}

.progress-bar {
    height: 8px;
    background: var(--bg-light);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px;
    transition: var(--transition);
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.quick-stat {
    text-align: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.quick-stat .stat-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary);
    display: block;
}

.quick-stat .stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

/* Lab Preview */
.lab-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.lab-stat {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
}

.lab-stat .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
}

.lab-stat.primary .stat-icon {
    background: var(--primary);
}

.lab-stat.warning .stat-icon {
    background: var(--warning);
}

.lab-stat.success .stat-icon {
    background: var(--success);
}

.lab-stat .stat-info {
    flex: 1;
}

.lab-stat .stat-number {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    display: block;
}

.lab-stat .stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Dept Preview */
.dept-overview {
    margin-bottom: 2rem;
}

.overview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.overview-item:last-child {
    border-bottom: none;
}

.overview-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.overview-value {
    font-weight: 600;
}

.overview-value.primary {
    color: var(--primary);
}

.overview-value.warning {
    color: var(--warning);
}

.overview-value.success {
    color: var(--success);
}

.mini-chart {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1rem;
}

.chart-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.chart-container {
    display: flex;
    align-items: end;
    gap: 0.5rem;
    height: 50px;
}

.chart-container .chart-bar {
    background: linear-gradient(to top, var(--primary), var(--primary-light));
    border-radius: 2px 2px 0 0;
    width: 100%;
    min-height: 20%;
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
    color: white;
    padding: 6rem 0;
    text-align: center;
}

.cta-content {
    max-width: 800px;
    margin: 0 auto;
}

.cta-text h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.cta-text p {
    font-size: 1.125rem;
    color: #d1d5db;
    margin-bottom: 2.5rem;
    line-height: 1.6;
}

.cta-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2.5rem;
}

.cta-features {
    display: flex;
    justify-content: center;
    gap: 2rem;
}

.cta-feature {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #9ca3af;
}

.cta-feature i {
    color: var(--primary-light);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .hero-content,
    .role-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .hero-text {
        text-align: center;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .roles-tabs {
        flex-direction: column;
    }
    
    .role-tab {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .nav-container {
        padding: 0 1rem;
    }
    
    .container {
        padding: 0 1rem;
    }
    
    .hero-section {
        padding: 6rem 0 4rem;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .hero-stats {
        gap: 1.5rem;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .cta-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .cta-features {
        flex-direction: column;
        gap: 1rem;
    }
    
    .cta-text h2 {
        font-size: 2rem;
    }
}

/* Animation keyframes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Animation classes */
.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}

.animate-slide-in-right {
    animation: slideInRight 0.6s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Role tabs functionality
    const roleTabs = document.querySelectorAll('.role-tab');
    const rolePanels = document.querySelectorAll('.role-panel');
    
    roleTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetRole = this.dataset.role;
            
            // Remove active class from all tabs and panels
            roleTabs.forEach(t => t.classList.remove('active'));
            rolePanels.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding panel
            this.classList.add('active');
            document.getElementById(targetRole + '-panel').classList.add('active');
        });
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add scroll effect to navbar
    const navbar = document.querySelector('.top-nav');
    let lastScrollY = window.scrollY;
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            navbar.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.boxShadow = 'none';
        }
        lastScrollY = window.scrollY;
    });
    
    // Add hover effects to feature cards
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>