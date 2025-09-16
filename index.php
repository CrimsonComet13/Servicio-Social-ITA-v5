<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Servicio Social - ITA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span class="brand-text">ITA Social</span>
            </div>
            
            <div class="nav-actions">
                <a href="auth/login.php" class="btn btn-ghost">Iniciar Sesión</a>
                <a href="auth/register.php" class="btn btn-primary">Registrarse</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
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
                        
                        <p class="hero-subtitle">
                            Instituto Tecnológico de Aguascalientes
                        </p>
                        
                        <p class="hero-description">
                            Gestiona tu servicio social de manera eficiente y mantén el control 
                            de todo el proceso con nuestra plataforma moderna e intuitiva.
                        </p>
                        
                        <div class="hero-actions">
                            <a href="auth/register.php" class="btn btn-primary btn-large">
                                <i class="fas fa-rocket"></i>
                                Comenzar Ahora
                            </a>
                            <a href="#features" class="btn btn-secondary btn-large">
                                <i class="fas fa-info-circle"></i>
                                Conocer Más
                            </a>
                        </div>
                        
                        <div class="hero-stats">
                            <div class="stat">
                                <div class="stat-number">2,500+</div>
                                <div class="stat-label">Estudiantes Activos</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number">98%</div>
                                <div class="stat-label">Satisfacción</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number">24/7</div>
                                <div class="stat-label">Disponibilidad</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hero-visual">
                        <div class="dashboard-preview">
                            <div class="preview-header">
                                <div class="preview-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                                <div class="preview-title">Dashboard</div>
                            </div>
                            
                            <div class="preview-content">
                                <div class="preview-cards">
                                    <div class="preview-card primary">
                                        <div class="card-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="card-info">
                                            <div class="card-number">12</div>
                                            <div class="card-label">Solicitudes</div>
                                        </div>
                                    </div>
                                    
                                    <div class="preview-card success">
                                        <div class="card-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="card-info">
                                            <div class="card-number">480</div>
                                            <div class="card-label">Horas</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="preview-chart">
                                    <div class="chart-bars">
                                        <div class="bar" style="height: 40%"></div>
                                        <div class="bar" style="height: 60%"></div>
                                        <div class="bar" style="height: 80%"></div>
                                        <div class="bar" style="height: 45%"></div>
                                        <div class="bar" style="height: 95%"></div>
                                        <div class="bar" style="height: 70%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">¿Qué puedes hacer?</h2>
                    <p class="section-subtitle">
                        Descubre todas las funcionalidades que nuestro sistema tiene para ofrecerte
                    </p>
                </div>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Gestión de Solicitudes</h3>
                        <p>Solicita tu servicio social y realiza el seguimiento de tu proceso de aprobación en tiempo real</p>
                        <div class="feature-link">
                            <span>Ver más</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Reportes Bimestrales</h3>
                        <p>Registra tus actividades y horas cumplidas en cada periodo bimestral de forma sencilla</p>
                        <div class="feature-link">
                            <span>Ver más</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon warning">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h3>Documentos Automáticos</h3>
                        <p>Genera oficios, cartas y constancias con formatos oficiales del ITA instantáneamente</p>
                        <div class="feature-link">
                            <span>Ver más</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon info">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Comunicación Directa</h3>
                        <p>Mantente en contacto con jefes de departamento y laboratorio sin complicaciones</p>
                        <div class="feature-link">
                            <span>Ver más</span>
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Roles Section -->
        <section class="roles">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Para todos los roles</h2>
                    <p class="section-subtitle">
                        Cada usuario tiene acceso a funcionalidades específicas según su rol
                    </p>
                </div>
                
                <div class="roles-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" data-tab="estudiantes">
                            <i class="fas fa-user-graduate"></i>
                            Estudiantes
                        </button>
                        <button class="tab-btn" data-tab="laboratorio">
                            <i class="fas fa-flask"></i>
                            Jefes de Laboratorio
                        </button>
                        <button class="tab-btn" data-tab="departamento">
                            <i class="fas fa-users-cog"></i>
                            Jefes de Departamento
                        </button>
                    </div>
                    
                    <div class="tab-content">
                        <div class="tab-panel active" id="estudiantes">
                            <div class="role-content">
                                <div class="role-features">
                                    <div class="role-feature">
                                        <i class="fas fa-paper-plane"></i>
                                        <div>
                                            <h4>Solicitud de servicio social</h4>
                                            <p>Envía tu solicitud de manera digital y rápida</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div>
                                            <h4>Registro de reportes bimestrales</h4>
                                            <p>Lleva un control detallado de tus actividades</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <h4>Seguimiento de horas cumplidas</h4>
                                            <p>Monitorea tu progreso en tiempo real</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-download"></i>
                                        <div>
                                            <h4>Descarga de documentos oficiales</h4>
                                            <p>Obtén todos tus documentos cuando los necesites</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="role-visual">
                                    <div class="role-card-preview student">
                                        <div class="card-header">
                                            <i class="fas fa-user-graduate"></i>
                                            <span>Panel de Estudiante</span>
                                        </div>
                                        <div class="progress-ring">
                                            <div class="progress-circle">
                                                <span>75%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-panel" id="laboratorio">
                            <div class="role-content">
                                <div class="role-features">
                                    <div class="role-feature">
                                        <i class="fas fa-users"></i>
                                        <div>
                                            <h4>Gestión de estudiantes asignados</h4>
                                            <p>Supervisa a todos los estudiantes bajo tu cargo</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-star"></i>
                                        <div>
                                            <h4>Evaluación de reportes bimestrales</h4>
                                            <p>Revisa y califica el desempeño de los estudiantes</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-list-check"></i>
                                        <div>
                                            <h4>Registro de horas y actividades</h4>
                                            <p>Valida las actividades realizadas por los estudiantes</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-envelope"></i>
                                        <div>
                                            <h4>Comunicación con estudiantes</h4>
                                            <p>Mantén contacto directo y fluido</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="role-visual">
                                    <div class="role-card-preview lab">
                                        <div class="card-header">
                                            <i class="fas fa-flask"></i>
                                            <span>Panel de Laboratorio</span>
                                        </div>
                                        <div class="stats-mini">
                                            <div class="stat-mini">
                                                <span class="number">24</span>
                                                <span class="label">Estudiantes</span>
                                            </div>
                                            <div class="stat-mini">
                                                <span class="number">8</span>
                                                <span class="label">Pendientes</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-panel" id="departamento">
                            <div class="role-content">
                                <div class="role-features">
                                    <div class="role-feature">
                                        <i class="fas fa-check-circle"></i>
                                        <div>
                                            <h4>Aprobación de solicitudes</h4>
                                            <p>Autoriza las solicitudes de servicio social</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-network-wired"></i>
                                        <div>
                                            <h4>Gestión de jefes de laboratorio</h4>
                                            <p>Coordina el trabajo de los laboratorios</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-file-contract"></i>
                                        <div>
                                            <h4>Generación de documentos oficiales</h4>
                                            <p>Crea documentos con validez institucional</p>
                                        </div>
                                    </div>
                                    <div class="role-feature">
                                        <i class="fas fa-chart-pie"></i>
                                        <div>
                                            <h4>Reportes y estadísticas</h4>
                                            <p>Accede a métricas y análisis detallados</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="role-visual">
                                    <div class="role-card-preview dept">
                                        <div class="card-header">
                                            <i class="fas fa-users-cog"></i>
                                            <span>Panel de Departamento</span>
                                        </div>
                                        <div class="chart-mini">
                                            <div class="chart-bar" style="height: 60%"></div>
                                            <div class="chart-bar" style="height: 80%"></div>
                                            <div class="chart-bar" style="height: 45%"></div>
                                            <div class="chart-bar" style="height: 90%"></div>
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
        <section class="cta">
            <div class="container">
                <div class="cta-content">
                    <h2>¿Listo para comenzar?</h2>
                    <p>Únete a miles de estudiantes que ya están gestionando su servicio social de manera eficiente</p>
                    <div class="cta-actions">
                        <a href="auth/register.php" class="btn btn-primary btn-large">
                            <i class="fas fa-rocket"></i>
                            Registrarse Ahora
                        </a>
                        <a href="auth/login.php" class="btn btn-ghost btn-large">
                            <i class="fas fa-sign-in-alt"></i>
                            Ya tengo cuenta
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <span class="brand-text">ITA Social</span>
                    <p>Sistema oficial del Instituto Tecnológico de Aguascalientes</p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-section">
                        <h4>Enlaces</h4>
                        <ul>
                            <li><a href="#features">Características</a></li>
                            <li><a href="#roles">Roles</a></li>
                            <li><a href="docs/manual.pdf">Documentación</a></li>
                            <li><a href="contacto.php">Soporte</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-section">
                        <h4>Contacto</h4>
                        <ul>
                            <li><a href="mailto:servicio.social@ita.mx">servicio.social@ita.mx</a></li>
                            <li><a href="tel:+524491234567">+52 (449) 123-4567</a></li>
                            <li>Aguascalientes, México</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Instituto Tecnológico de Aguascalientes. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #8b8cf7;
            --primary-dark: #4f46e5;
            --secondary-color: #1f2937;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --bg-primary: #0f1419;
            --bg-secondary: #1a202c;
            --bg-light: #f8fafc;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border-color: #e5e7eb;
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-light);
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1000;
            padding: 1rem 0;
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

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
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
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }

        .btn-ghost:hover {
            color: var(--primary-color);
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: white;
            padding: 6rem 0;
            overflow: hidden;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
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
            color: var(--primary-light);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 2rem;
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

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-light);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #9ca3af;
        }

        /* Dashboard Preview */
        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .dashboard-preview {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .preview-dots {
            display: flex;
            gap: 0.5rem;
        }

        .preview-dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .preview-title {
            color: white;
            font-weight: 600;
        }

        .preview-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .preview-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .preview-card.primary {
            background: rgba(99, 102, 241, 0.2);
        }

        .preview-card.success {
            background: rgba(16, 185, 129, 0.2);
        }

        .card-icon {
            font-size: 1.25rem;
            color: white;
        }

        .card-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .card-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .preview-chart {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 1rem;
        }

        .chart-bars {
            display: flex;
            align-items: end;
            gap: 0.5rem;
            height: 60px;
        }

        .bar {
            background: linear-gradient(to top, var(--primary-color), var(--primary-light));
            border-radius: 2px;
            width: 100%;
            min-height: 20%;
            animation: grow 2s ease-out;
        }

        @keyframes grow {
            from { height: 0; }
            to { height: var(--height); }
        }

        /* Features Section */
        .features {
            padding: 6rem 0;
            background: white;
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
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
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }

        .feature-icon.success {
            background: linear-gradient(135deg, var(--success-color), #34d399);
        }

        .feature-icon.warning {
            background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        }

        .feature-icon.info {
            background: linear-gradient(135deg, var(--info-color), #60a5fa);
        }

        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .feature-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .feature-link i {
            transition: transform 0.2s ease;
        }

        .feature-card:hover .feature-link i {
            transform: translateX(4px);
        }

        /* Roles Section */
        .roles {
            padding: 6rem 0;
            background: var(--bg-light);
        }

        .roles-tabs {
            max-width: 1000px;
            margin: 0 auto;
        }

        .tab-buttons {
            display: flex;
            background: white;
            border-radius: var(--radius-lg);
            padding: 0.5rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow);
        }

        .tab-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 500;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .tab-btn:hover:not(.active) {
            background: var(--bg-light);
            color: var(--primary-color);
        }

        .tab-content {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .tab-panel {
            display: none;
            padding: 3rem;
        }

        .tab-panel.active {
            display: block;
        }

        .role-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .role-features {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .role-feature {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .role-feature i {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .role-feature h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .role-feature p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .role-visual {
            display: flex;
            justify-content: center;
        }

        .role-card-preview {
            background: var(--bg-light);
            border-radius: var(--radius-lg);
            padding: 2rem;
            width: 280px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header i {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-header span {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-ring {
            display: flex;
            justify-content: center;
        }

        .progress-circle {
            width: 120px;
            height: 120px;
            background: conic-gradient(var(--primary-color) 0deg 270deg, var(--border-color) 270deg 360deg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }

        .progress-circle span {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            z-index: 1;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .stat-mini {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }

        .stat-mini .number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-mini .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .chart-mini {
            display: flex;
            align-items: end;
            gap: 0.5rem;
            height: 80px;
            padding: 1rem;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }

        .chart-bar {
            background: linear-gradient(to top, var(--primary-color), var(--primary-light));
            border-radius: 2px;
            width: 100%;
            min-height: 20%;
        }

        /* CTA Section */
        .cta {
            padding: 6rem 0;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            text-align: center;
        }

        .cta-content h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-content p {
            font-size: 1.125rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }

        .cta-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .cta .btn-primary {
            background: white;
            color: var(--primary-color);
        }

        .cta .btn-primary:hover {
            background: var(--bg-light);
        }

        .cta .btn-ghost {
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .cta .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
        }

        /* Footer */
        .footer {
            background: var(--bg-primary);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .footer-brand .logo {
            margin-bottom: 0.5rem;
        }

        .footer-brand p {
            color: #9ca3af;
            max-width: 300px;
        }

        .footer-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .footer-section h4 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-light);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section li {
            margin-bottom: 0.5rem;
        }

        .footer-section a {
            color: #d1d5db;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-section a:hover {
            color: var(--primary-light);
        }

        .footer-bottom {
            border-top: 1px solid #374151;
            padding-top: 2rem;
            text-align: center;
            color: #9ca3af;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 3rem;
            }

            .role-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }

            .container, .hero-container {
                padding: 0 1rem;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-actions {
                flex-direction: column;
                align-items: center;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .cta-actions {
                flex-direction: column;
                align-items: center;
            }

            .footer-links {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .features-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-preview {
                max-width: 300px;
            }

            .preview-cards {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
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

        .feature-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .feature-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .feature-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .feature-card:nth-child(4) {
            animation-delay: 0.3s;
        }
    </style>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');

                    // Remove active class from all buttons and panels
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanels.forEach(panel => panel.classList.remove('active'));

                    // Add active class to clicked button and corresponding panel
                    button.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
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

            // Navbar background on scroll
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                } else {
                    navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                }
            });
        });
    </script>
</body>
</html>