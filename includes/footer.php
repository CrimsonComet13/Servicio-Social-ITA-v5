<?php
// Evitar acceso directo
if (!defined('APP_NAME')) {
    die('Acceso restringido');
}

// Asegurar que las variables necesarias están definidas
if (!isset($session)) {
    $session = SecureSession::getInstance();
}

if (!isset($usuario) && $session->isLoggedIn()) {
    $usuario = [
        'nombre' => 'Usuario',
        'email' => $session->get('email') ?? 'usuario@ita.mx',
        'avatar' => null
    ];
}

// Función auxiliar para obtener nombre seguro del usuario
function getSafeUserName($usuario, $maxLength = 20) {
    if (!is_array($usuario)) {
        return 'Usuario';
    }
    
    $name = $usuario['nombre'] ?? $usuario['email'] ?? 'Usuario';
    
    if ($name && is_string($name)) {
        return htmlspecialchars(substr($name, 0, $maxLength));
    }
    
    return 'Usuario';
}

// Función auxiliar para obtener email seguro del usuario
function getSafeUserEmail($usuario) {
    if (!is_array($usuario)) {
        return 'usuario@ita.mx';
    }
    
    return htmlspecialchars($usuario['email'] ?? 'usuario@ita.mx');
}
?>

</main>
    
    <?php if (isset($session) && $session->isLoggedIn()): ?>
    <!-- Footer para usuarios autenticados -->
    <footer class="app-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section brand-section">
                    <div class="footer-brand">
                        <div class="brand-logo">
                            <img src="../assets/images/logo-ita.png" alt="Logo ITA">
                        </div>
                        <div class="brand-info">
                            <h4>ITA Social</h4>
                            <p>Sistema de Servicio Social</p>
                        </div>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Enlaces</h5>
                    <nav class="footer-nav">
                        <?php if ($session->getUserRole() === 'estudiante'): ?>
                            <a href="../dashboard/estudiante.php">Mi Dashboard</a>
                            <a href="../modules/estudiante/solicitudes.php">Mis Solicitudes</a>
                            <a href="../modules/estudiante/reportes.php">Mis Reportes</a>
                            <a href="../modules/estudiante/perfil.php">Mi Perfil</a>
                        <?php elseif ($session->getUserRole() === 'jefe_departamento'): ?>
                            <a href="../dashboard/jefe_departamento.php">Dashboard Jefe Depto.</a>
                            <a href="../modules/departamento/proyectos.php">Gestionar Proyectos</a>
                            <a href="../modules/departamento/solicitudes.php">Revisar Solicitudes</a>
                            <a href="../modules/departamento/perfil.php">Mi Perfil</a>
                        <?php elseif ($session->getUserRole() === 'jefe_laboratorio'): ?>
                            <a href="../dashboard/jefe_laboratorio.php">Dashboard Jefe Lab.</a>
                            <a href="../modules/laboratorio/mis_proyectos.php">Mis Proyectos</a>
                            <a href="../modules/laboratorio/evaluaciones.php">Evaluar Reportes</a>
                            <a href="../modules/laboratorio/perfil.php">Mi Perfil</a>
                        <?php else: ?>
                            <a href="../dashboard/index.php">Dashboard</a>
                            <a href="../help.php">Ayuda</a>
                            <a href="../contacto.php">Contacto</a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Soporte</h5>
                    <nav class="footer-nav">
                        <a href="../docs/manual-usuario.pdf" target="_blank">Manual</a>
                        <a href="mailto:soporte@ita.mx">Soporte</a>
                        <a href="../help/tutoriales.php">Tutoriales</a>
                    </nav>
                </div>
                
                <div class="footer-section user-section">
                    <h5>Sesión</h5>
                    <div class="footer-info">
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <span><?= getSafeUserName($usuario) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Conexión segura</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-code-branch"></i>
                            <span>v<?= APP_VERSION ?? '1.0' ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <div class="copyright">
                        <p>&copy; <?= date('Y') ?> ITA. Todos los derechos reservados.</p>
                        <div class="legal-links">
                            <a href="../legal/privacidad.php">Privacidad</a>
                            <a href="../legal/terminos.php">Términos</a>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </footer>
    <?php else: ?>
    <!-- Footer para usuarios no autenticados -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section brand-section">
                    <div class="footer-brand">
                        <div class="brand-logo">
                            <img src="../assets/images/logo-ita.png" alt="Logo ITA">
                        </div>
                        <div class="brand-info">
                            <h4>ITA Social</h4>
                            <p>Sistema de Gestión de Servicio Social</p>
                        </div>
                    </div>
                    <div class="social-links">
                        <a href="https://facebook.com/ITAAguascalientes" target="_blank" aria-label="Facebook">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="https://twitter.com/ITAAguascalientes" target="_blank" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://instagram.com/ITAAguascalientes" target="_blank" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://linkedin.com/company/ITAAguascalientes" target="_blank" aria-label="LinkedIn">
                            <i class="fab fa-linkedin"></i>
                        </a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Estudiantes</h5>
                    <nav class="footer-nav">
                        <a href="../auth/register.php">Registrarse</a>
                        <a href="../auth/login.php">Iniciar Sesión</a>
                        <a href="../help/como-empezar.php">Cómo Empezar</a>
                        <a href="../help/requisitos.php">Requisitos</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Instituciones</h5>
                    <nav class="footer-nav">
                        <a href="../auth/register-jefe.php">Registro</a>
                        <a href="../help/instituciones.php">Información</a>
                        <a href="../contacto.php">Colaborar</a>
                        <a href="../help/beneficios.php">Beneficios</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Contacto</h5>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <a href="tel:+524499105002">(449) 910-5002</a>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:soporte@ita.mx">soporte@ita.mx</a>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-globe"></i>
                            <a href="https://www.ita.mx" target="_blank">www.ita.mx</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <div class="copyright">
                        <p>&copy; <?= date('Y') ?> Instituto Tecnológico de Aguascalientes. Todos los derechos reservados.</p>
                        <div class="legal-links">
                            <a href="../legal/privacidad.php">Privacidad</a>
                            <a href="../legal/terminos.php">Términos</a>
                            <a href="../legal/cookies.php">Cookies</a>
                        </div>
                    </div>
                    
                    <div class="footer-stats">
                        <div class="stat-item">
                            <span class="stat-number">2,500+</span>
                            <span class="stat-label">Estudiantes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">15</span>
                            <span class="stat-label">Laboratorios</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">98%</span>
                            <span class="stat-label">Satisfacción</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Scripts Globales -->
    <script src="../assets/js/main.js"></script>
    
    <!-- Scripts condicionales -->
    <?php if (isset($dashboardJS) && $dashboardJS): ?>
    <script src="../assets/js/dashboard.js"></script>
    <?php endif; ?>
    
    <?php if (isset($formsJS) && $formsJS): ?>
    <script src="../assets/js/forms.js"></script>
    <?php endif; ?>
    
    <?php if (isset($chartsJS) && $chartsJS): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="../assets/js/charts.js"></script>
    <?php endif; ?>
    
    <style>
    /* ================================
       FOOTER STYLES - CORREGIDO PARA SIDEBAR
    ================================ */
    
    /* ⭐ FOOTER PARA USUARIOS LOGUEADOS - SOLUCIÓN PRINCIPAL */
    .app-footer {
        background: var(--bg-white);
        border-top: 1px solid var(--border);
        margin-top: 2rem;
        /* ⭐ ESTOS MÁRGENES SOLUCIONAN LA SUPERPOSICIÓN */
        margin-left: var(--sidebar-width);  /* En desktop */
        width: calc(100% - var(--sidebar-width));  /* Ancho correcto */
        transition: var(--transition);
    }
    
    /* Footer para usuarios no autenticados */
    .landing-footer {
        background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
        color: white;
        margin-top: 2rem;
        /* Sin margen lateral para usuarios no logueados */
        margin-left: 0;
        width: 100%;
    }
    
    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .footer-content {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
        gap: 3rem;
        margin-bottom: 2rem;
    }
    
    .footer-section h5 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text-primary);
    }
    
    .landing-footer .footer-section h5 {
        color: white;
    }
    
    /* Footer Brand */
    .footer-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    
    .footer-brand .brand-logo {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .footer-brand .brand-logo img {
        width: 22px;
        height: 22px;
        object-fit: contain;
    }
    
    .footer-brand .brand-info h4 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.1rem;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .landing-footer .footer-brand .brand-info h4 {
        color: white;
    }
    
    .footer-brand .brand-info p {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.3;
    }
    
    .landing-footer .footer-brand .brand-info p {
        color: #d1d5db;
    }
    
    /* Footer Navigation */
    .footer-nav {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    
    .footer-nav a {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
        display: flex;
        align-items: center;
        padding: 0.2rem 0;
    }
    
    .footer-nav a:hover {
        color: var(--primary);
        transform: translateX(3px);
    }
    
    .landing-footer .footer-nav a {
        color: #d1d5db;
    }
    
    .landing-footer .footer-nav a:hover {
        color: var(--primary-light);
    }
    
    /* Footer Info */
    .footer-info {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .info-item i {
        width: 12px;
        color: var(--primary);
        font-size: 0.7rem;
    }
    
    /* Contact Info */
    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: #d1d5db;
    }
    
    .contact-item i {
        width: 12px;
        color: var(--primary-light);
        font-size: 0.7rem;
        flex-shrink: 0;
    }
    
    .contact-item a {
        color: #d1d5db;
        text-decoration: none;
        transition: var(--transition);
    }
    
    .contact-item a:hover {
        color: var(--primary-light);
    }
    
    /* Social Links */
    .social-links {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .social-links a {
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.85rem;
        text-decoration: none;
        transition: var(--transition);
    }
    
    .social-links a:hover {
        background: var(--primary);
        border-color: var(--primary);
        transform: translateY(-1px);
    }
    
    /* Footer Bottom */
    .footer-bottom {
        border-top: 1px solid var(--border);
        padding: 1.5rem 0;
        margin-top: 1rem;
    }
    
    .landing-footer .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .footer-bottom-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .copyright {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    
    .copyright p {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.3;
    }
    
    .landing-footer .copyright p {
        color: #9ca3af;
    }
    
    .legal-links {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .legal-links a {
        font-size: 0.75rem;
        color: var(--text-light);
        text-decoration: none;
        transition: var(--transition);
    }
    
    .legal-links a:hover {
        color: var(--primary);
        text-decoration: underline;
    }
    
    .landing-footer .legal-links a {
        color: #9ca3af;
    }
    
    .landing-footer .legal-links a:hover {
        color: var(--primary-light);
    }
    
    /* Footer Actions */
    .footer-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .theme-toggle {
        width: 32px;
        height: 32px;
        background: var(--bg-light);
        border: 1px solid var(--border);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.8rem;
    }
    
    .theme-toggle:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    /* Footer Stats */
    .footer-stats {
        display: flex;
        gap: 1.5rem;
        align-items: center;
    }
    
    .footer-stats .stat-item {
        text-align: center;
    }
    
    .footer-stats .stat-number {
        display: block;
        font-size: 1rem;
        font-weight: 700;
        color: var(--primary-light);
        line-height: 1;
    }
    
    .footer-stats .stat-label {
        font-size: 0.7rem;
        color: #9ca3af;
        margin-top: 0.1rem;
    }
    
    /* Brand Section especial */
    .brand-section {
        display: flex;
        flex-direction: column;
    }
    
    .user-section .footer-info {
        max-width: 180px;
    }
    
    /* ⭐ RESPONSIVE - CRÍTICO PARA LA SOLUCIÓN */
    @media (max-width: 1024px) {
        /* REMOVER MÁRGENES EN MÓVILES - SOLUCIÓN CLAVE */
        .app-footer {
            margin-left: 0;  /* Sin margen en móviles */
            width: 100%;     /* Ancho completo en móviles */
        }
        
        .footer-content {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .footer-container {
            padding: 1rem;
        }
        
        .footer-content {
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
            margin-bottom: 1.5rem;
        }
        
        .footer-bottom-content {
            flex-direction: column;
            text-align: center;
            gap: 0.75rem;
        }
        
        .footer-stats {
            gap: 1rem;
        }
        
        .social-links {
            justify-content: center;
        }
        
        .legal-links {
            justify-content: center;
        }
        
        .brand-section {
            grid-column: 1 / -1;
        }
    }
    
    @media (max-width: 480px) {
        .footer-container {
            padding: 0.75rem;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .footer-brand {
            justify-content: center;
            text-align: center;
        }
        
        .footer-stats {
            flex-direction: row;
            gap: 0.75rem;
        }
        
        .legal-links {
            flex-direction: row;
            gap: 0.75rem;
        }
        
        .social-links {
            justify-content: center;
        }
    }
    
    /* Dark Theme Support */
    [data-theme="dark"] .app-footer {
        background: var(--bg-darker);
        border-color: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    [data-theme="dark"] .footer-section h5,
    [data-theme="dark"] .footer-brand .brand-info h4,
    [data-theme="dark"] .copyright p {
        color: white;
    }
    
    [data-theme="dark"] .footer-nav a,
    [data-theme="dark"] .info-item {
        color: #d1d5db;
    }
    
    [data-theme="dark"] .theme-toggle {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
        color: white;
    }
    
    /* Print Styles */
    @media print {
        .app-footer,
        .landing-footer {
            display: none;
        }
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        
        // Set initial theme
        document.documentElement.setAttribute('data-theme', currentTheme);
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (currentTheme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
        }
        
        // Toggle theme
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                const icon = this.querySelector('i');
                
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                
                // Update icon
                if (newTheme === 'dark') {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
                
                // Add transition effect
                this.style.transform = 'rotate(360deg)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 300);
            });
        }
        
        // Smooth scroll for footer links
        document.querySelectorAll('.footer-nav a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
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
        
        // Footer stats animation on scroll
        const footerStats = document.querySelectorAll('.footer-stats .stat-number');
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumber = entry.target;
                    const finalNumber = statNumber.textContent;
                    const numericValue = parseInt(finalNumber.replace(/[^\d]/g, ''));
                    
                    if (numericValue) {
                        animateNumber(statNumber, 0, numericValue, finalNumber);
                    }
                    
                    statsObserver.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        footerStats.forEach(stat => {
            statsObserver.observe(stat);
        });
        
        function animateNumber(element, start, end, originalText) {
            const duration = 800;
            const startTime = performance.now();
            const suffix = originalText.replace(/[\d,]/g, '');
            
            function updateNumber(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.floor(start + (end - start) * progress);
                
                element.textContent = current.toLocaleString() + suffix;
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }
        
        // Add loading effect for external links
        document.querySelectorAll('a[target="_blank"]').forEach(link => {
            link.addEventListener('click', function() {
                this.style.opacity = '0.7';
                setTimeout(() => {
                    this.style.opacity = '';
                }, 200);
            });
        });
        
        console.log('Footer con layout corregido inicializado');
    });
    </script>
    
</body>
</html>