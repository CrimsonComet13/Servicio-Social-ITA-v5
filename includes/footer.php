</main>
    
    <?php if (isset($session) && $session->isLoggedIn()): ?>
    <!-- Footer para usuarios autenticados -->
    <footer class="app-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-brand">
                        <div class="brand-logo">
                            <img src="../assets/images/logo-ita.png" alt="Logo ITA">
                        </div>
                        <div class="brand-info">
                            <h4>Instituto Tecnológico de Aguascalientes</h4>
                            <p>Sistema de Gestión de Servicio Social</p>
                        </div>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Enlaces Rápidos</h5>
                    <nav class="footer-nav">
                        <a href="../dashboard/<?= $session->getUserRole() ?>.php">Dashboard</a>
                        <a href="../modules/<?= $session->getUserRole() ?>/perfil.php">Mi Perfil</a>
                        <a href="../help.php">Ayuda</a>
                        <a href="../contacto.php">Contacto</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Soporte</h5>
                    <nav class="footer-nav">
                        <a href="../docs/manual-usuario.pdf" target="_blank">Manual de Usuario</a>
                        <a href="../help/faq.php">Preguntas Frecuentes</a>
                        <a href="mailto:soporte@ita.mx">Soporte Técnico</a>
                        <a href="../help/tutoriales.php">Tutoriales</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Información</h5>
                    <div class="footer-info">
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($usuario['nombre'] ?? $usuario['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>Última sesión: <?= formatDate($usuario['ultimo_acceso'] ?? date('Y-m-d H:i:s')) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Conexión segura SSL</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-code-branch"></i>
                            <span>Versión <?= APP_VERSION ?? '1.0.0' ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <div class="copyright">
                        <p>&copy; <?= date('Y') ?> Instituto Tecnológico de Aguascalientes. Todos los derechos reservados.</p>
                        <div class="legal-links">
                            <a href="../legal/privacidad.php">Política de Privacidad</a>
                            <a href="../legal/terminos.php">Términos de Uso</a>
                            <a href="../legal/cookies.php">Política de Cookies</a>
                        </div>
                    </div>
                    
                    <div class="footer-actions">
                        <button class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
                            <i class="fas fa-moon"></i>
                        </button>
                        <div class="language-selector">
                            <button class="language-btn" aria-label="Cambiar idioma">
                                <i class="fas fa-globe"></i>
                                <span>ES</span>
                            </button>
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
                <div class="footer-section">
                    <div class="footer-brand">
                        <div class="brand-logo">
                            <img src="assets/images/logo-ita.png" alt="Logo ITA">
                        </div>
                        <div class="brand-info">
                            <h4>ITA Social</h4>
                            <p>Sistema de Gestión de Servicio Social</p>
                            <p>Instituto Tecnológico de Aguascalientes</p>
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
                    <h5>Para Estudiantes</h5>
                    <nav class="footer-nav">
                        <a href="auth/register.php">Registrarse</a>
                        <a href="auth/login.php">Iniciar Sesión</a>
                        <a href="help/como-empezar.php">Cómo Empezar</a>
                        <a href="help/requisitos.php">Requisitos</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Para Instituciones</h5>
                    <nav class="footer-nav">
                        <a href="auth/register-jefe.php">Registro Jefe Lab.</a>
                        <a href="help/instituciones.php">Información</a>
                        <a href="contacto.php">Colaborar</a>
                        <a href="help/beneficios.php">Beneficios</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Soporte</h5>
                    <nav class="footer-nav">
                        <a href="help/faq.php">Preguntas Frecuentes</a>
                        <a href="docs/manual.pdf" target="_blank">Manual de Usuario</a>
                        <a href="contacto.php">Contacto</a>
                        <a href="help/status.php">Estado del Sistema</a>
                    </nav>
                </div>
                
                <div class="footer-section">
                    <h5>Contacto</h5>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Av. Adolfo López Mateos #1801 Ote.<br>Fracc. Bona Gens, Aguascalientes, Ags.</span>
                        </div>
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
                            <a href="legal/privacidad.php">Privacidad</a>
                            <a href="legal/terminos.php">Términos</a>
                            <a href="legal/cookies.php">Cookies</a>
                        </div>
                    </div>
                    
                    <div class="footer-stats">
                        <div class="stat-item">
                            <span class="stat-number">2,500+</span>
                            <span class="stat-label">Estudiantes Activos</span>
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
    /* Footer Styles */
    .app-footer {
        background: var(--bg-white);
        border-top: 1px solid var(--border);
        margin-top: 3rem;
        margin-left: var(--sidebar-width);
        transition: var(--transition);
    }
    
    .landing-footer {
        background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
        color: white;
        margin-top: 4rem;
    }
    
    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 3rem 2rem 0;
    }
    
    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
    }
    
    .footer-section h5 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: var(--text-primary);
    }
    
    .landing-footer .footer-section h5 {
        color: white;
    }
    
    /* Footer Brand */
    .footer-brand {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .footer-brand .brand-logo {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .footer-brand .brand-logo img {
        width: 32px;
        height: 32px;
        object-fit: contain;
    }
    
    .footer-brand .brand-info h4 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }
    
    .landing-footer .footer-brand .brand-info h4 {
        color: white;
    }
    
    .footer-brand .brand-info p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0.25rem 0;
        line-height: 1.4;
    }
    
    .landing-footer .footer-brand .brand-info p {
        color: #d1d5db;
    }
    
    /* Footer Navigation */
    .footer-nav {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .footer-nav a {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .footer-nav a:hover {
        color: var(--primary);
        transform: translateX(5px);
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
        gap: 0.75rem;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    
    .info-item i {
        width: 16px;
        color: var(--primary);
        font-size: 0.8rem;
    }
    
    /* Contact Info */
    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .contact-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: #d1d5db;
    }
    
    .contact-item i {
        width: 16px;
        color: var(--primary-light);
        font-size: 0.8rem;
        margin-top: 0.1rem;
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
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .social-links a {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        text-decoration: none;
        transition: var(--transition);
    }
    
    .social-links a:hover {
        background: var(--primary);
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Footer Bottom */
    .footer-bottom {
        border-top: 1px solid var(--border);
        padding: 2rem 0;
    }
    
    .landing-footer .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .footer-bottom-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 2rem;
    }
    
    .copyright {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .copyright p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    .landing-footer .copyright p {
        color: #9ca3af;
    }
    
    .legal-links {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    
    .legal-links a {
        font-size: 0.8rem;
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
        gap: 1rem;
    }
    
    .theme-toggle,
    .language-btn {
        width: 40px;
        height: 40px;
        background: var(--bg-light);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.9rem;
    }
    
    .theme-toggle:hover,
    .language-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .language-btn {
        gap: 0.25rem;
        padding: 0 0.5rem;
        width: auto;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    /* Footer Stats */
    .footer-stats {
        display: flex;
        gap: 2rem;
        align-items: center;
    }
    
    .footer-stats .stat-item {
        text-align: center;
    }
    
    .footer-stats .stat-number {
        display: block;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-light);
        line-height: 1;
    }
    
    .footer-stats .stat-label {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 0.25rem;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .app-footer {
            margin-left: 0;
        }
    }
    
    @media (max-width: 768px) {
        .footer-container {
            padding: 2rem 1rem 0;
        }
        
        .footer-content {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-bottom-content {
            flex-direction: column;
            text-align: center;
            gap: 1.5rem;
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
    }
    
    @media (max-width: 480px) {
        .footer-container {
            padding: 1.5rem 0.75rem 0;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .footer-brand {
            flex-direction: column;
            text-align: center;
            gap: 0.75rem;
        }
        
        .footer-stats {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .legal-links {
            flex-direction: column;
            gap: 0.5rem;
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
    
    [data-theme="dark"] .theme-toggle,
    [data-theme="dark"] .language-btn {
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
        
        // Language selector (placeholder functionality)
        const languageBtn = document.querySelector('.language-btn');
        if (languageBtn) {
            languageBtn.addEventListener('click', function() {
                // Implementar cambio de idioma aquí
                console.log('Language selector clicked');
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
            rootMargin: '0px 0px -100px 0px'
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
            const duration = 1000;
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
                }, 300);
            });
        });
    });
    </script>
    
  
    
</body>
</html>