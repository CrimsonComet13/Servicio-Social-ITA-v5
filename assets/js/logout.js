/**
 * Sistema de Logout con Rutas Absolutas - SERVICIO_SOCIAL_ITA
 * Versión que usa rutas absolutas para evitar problemas de resolución
 */

class SimpleLogoutManager {
    constructor() {
        this.projectName = 'servicio_social_ita'; // Nombre de tu proyecto
        this.init();
    }

    init() {
        console.log('=== LOGOUT MANAGER CON RUTAS ABSOLUTAS ===');
        console.log('Current URL:', window.location.href);
        console.log('Current pathname:', window.location.pathname);
        this.attachLogoutListeners();
        this.setupConfirmationModal();
    }

    /**
     * Calcular la URL ABSOLUTA del logout.php
     */
    getLogoutUrl() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        const currentPath = window.location.pathname;
        
        console.log('Calculating absolute logout URL...');
        console.log('Protocol:', protocol);
        console.log('Host:', host);
        console.log('Current path:', currentPath);
        
        // Construir la URL absoluta al logout.php
        let logoutUrl = `${protocol}//${host}/${this.projectName}/auth/logout.php`;
        
        console.log('✅ Calculated ABSOLUTE logout URL:', logoutUrl);
        return logoutUrl;
    }

    /**
     * Calcular la URL ABSOLUTA del index.php para redirección
     */
    getIndexUrl() {
        const protocol = window.location.protocol;
        const host = window.location.host;
        
        let indexUrl = `${protocol}//${host}/${this.projectName}/index.php`;
        
        console.log('✅ Calculated ABSOLUTE index URL:', indexUrl);
        return indexUrl;
    }

    /**
     * Adjuntar listeners a todos los botones de logout
     */
    attachLogoutListeners() {
        const selectors = [
            '.logout-btn', 
            '.btn-logout', 
            '[data-action="logout"]', 
            '.logout-link',
            'a[href*="logout"]',
            'button[onclick*="logout"]'
        ];
        
        const logoutButtons = document.querySelectorAll(selectors.join(', '));
        
        console.log('🔍 Found logout elements:', logoutButtons.length);
        logoutButtons.forEach((btn, index) => {
            console.log(`  ${index + 1}. ${btn.tagName} with classes:`, btn.className, 'text:', btn.textContent.trim());
        });
        
        if (logoutButtons.length === 0) {
            console.warn('⚠️ No logout buttons found! Make sure your buttons have the correct classes:');
            console.warn('   .logout-btn, .btn-logout, [data-action="logout"], or .logout-link');
        }
        
        logoutButtons.forEach((button, index) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                console.log(`🖱️ Logout button ${index + 1} clicked`);
                
                // Verificar si necesita confirmación
                const needsConfirmation = button.dataset.confirm !== 'false';
                const useModal = button.dataset.modal !== 'false';
                
                console.log('Confirmation needed:', needsConfirmation, 'Use modal:', useModal);
                
                if (needsConfirmation && useModal) {
                    this.showConfirmationModal();
                } else if (needsConfirmation) {
                    this.showBrowserConfirm();
                } else {
                    this.performLogout();
                }
            });
        });
    }

    /**
     * Mostrar confirmación del navegador
     */
    showBrowserConfirm() {
        console.log('🗨️ Showing browser confirm dialog');
        if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
            this.performLogout();
        } else {
            console.log('❌ User cancelled logout');
        }
    }

    /**
     * Crear y mostrar modal de confirmación personalizado
     */
    showConfirmationModal() {
        console.log('🗨️ Showing custom modal');
        
        // Crear modal si no existe
        if (!document.getElementById('logoutModal')) {
            this.createModal();
        }
        
        const modal = document.getElementById('logoutModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Focus en el botón de cancelar para accesibilidad
        setTimeout(() => {
            const cancelBtn = document.getElementById('cancelLogout');
            if (cancelBtn) cancelBtn.focus();
        }, 100);
    }

    /**
     * Crear modal de confirmación
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'logoutModal';
        modal.innerHTML = `
            <div class="logout-overlay" onclick="window.simpleLogout.hideModal()"></div>
            <div class="logout-dialog">
                <div class="logout-header">
                    <div class="logout-icon">
                        🚪
                    </div>
                    <h3>¿Cerrar Sesión?</h3>
                    <p>¿Estás seguro de que deseas cerrar tu sesión actual?</p>
                </div>
                <div class="logout-actions">
                    <button type="button" id="cancelLogout" class="btn-cancel">
                        Cancelar
                    </button>
                    <button type="button" id="confirmLogout" class="btn-confirm">
                        Cerrar Sesión
                    </button>
                </div>
            </div>
        `;
        
        // Agregar estilos
        const style = document.createElement('style');
        style.textContent = `
            #logoutModal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 9999;
                display: none;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.2s ease-out;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .logout-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
            }
            
            .logout-dialog {
                position: relative;
                background: white;
                border-radius: 12px;
                padding: 2rem;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
                animation: slideUp 0.2s ease-out;
            }
            
            .logout-header {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .logout-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #ef4444, #f87171);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                color: white;
                font-size: 24px;
            }
            
            .logout-header h3 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 0.5rem 0;
            }
            
            .logout-header p {
                color: #6b7280;
                margin: 0;
                line-height: 1.5;
            }
            
            .logout-actions {
                display: flex;
                gap: 0.75rem;
                justify-content: flex-end;
            }
            
            .logout-actions button {
                padding: 0.75rem 1.25rem;
                border-radius: 6px;
                border: none;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.15s ease;
                font-size: 0.875rem;
            }
            
            .btn-cancel {
                background: #f3f4f6;
                color: #374151;
            }
            
            .btn-cancel:hover {
                background: #e5e7eb;
            }
            
            .btn-confirm {
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
            }
            
            .btn-confirm:hover {
                background: linear-gradient(135deg, #dc2626, #b91c1c);
                transform: translateY(-1px);
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 480px) {
                .logout-dialog {
                    padding: 1.5rem;
                    margin: 1rem;
                }
                
                .logout-actions {
                    flex-direction: column-reverse;
                }
                
                .logout-actions button {
                    width: 100%;
                }
            }
        `;
        
        document.head.appendChild(style);
        document.body.appendChild(modal);
        
        // Adjuntar event listeners
        document.getElementById('cancelLogout').onclick = () => this.hideModal();
        document.getElementById('confirmLogout').onclick = () => {
            this.hideModal();
            this.performLogout();
        };
        
        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                this.hideModal();
            }
        });
    }

    /**
     * Ocultar modal
     */
    hideModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    /**
     * Configurar modal de confirmación
     */
    setupConfirmationModal() {
        // Ya se maneja en createModal()
    }

    /**
     * Realizar logout con URLs absolutas
     */
    performLogout() {
        console.log('🚀 PERFORMING LOGOUT WITH ABSOLUTE URLS...');
        
        // Mostrar indicador de carga en los botones
        this.showLoadingState();
        
        // Limpiar datos locales inmediatamente
        this.clearLocalData();
        
        // Obtener URLs absolutas
        const logoutUrl = this.getLogoutUrl();
        const indexUrl = this.getIndexUrl();
        
        console.log('🔄 Redirecting to absolute logout URL:', logoutUrl);
        
        // Opción 1: Usar fetch para llamar al logout y luego redireccionar
        this.performAsyncLogout(logoutUrl, indexUrl);
    }

    /**
     * Realizar logout asíncrono y luego redireccionar
     */
    async performAsyncLogout(logoutUrl, indexUrl) {
        try {
            console.log('📡 Calling logout endpoint:', logoutUrl);
            
            // Llamar al logout.php
            const response = await fetch(logoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=logout',
                credentials: 'same-origin'
            });
            
            console.log('📨 Response status:', response.status);
            
            if (response.ok) {
                console.log('✅ Logout successful, redirecting to index');
                // Redireccionar al index con parámetro de éxito
                window.location.replace(indexUrl + '?logout=success');
            } else {
                console.warn('⚠️ Logout response not OK, but redirecting anyway');
                window.location.replace(indexUrl + '?logout=error');
            }
            
        } catch (error) {
            console.error('❌ Logout fetch error:', error);
            // En caso de error, redireccionar directamente al logout.php
            console.log('🆘 Fallback: Direct redirect to logout URL');
            window.location.replace(logoutUrl);
        }
    }

    /**
     * Mostrar estado de carga en botones
     */
    showLoadingState() {
        console.log('⏳ Showing loading state on buttons');
        
        const logoutButtons = document.querySelectorAll(
            '.logout-btn, .btn-logout, [data-action="logout"], .logout-link'
        );
        
        logoutButtons.forEach(button => {
            const originalContent = button.innerHTML;
            button.dataset.originalContent = originalContent;
            button.innerHTML = '⏳ Cerrando sesión...';
            button.disabled = true;
            button.style.opacity = '0.7';
            button.style.cursor = 'wait';
            button.style.pointerEvents = 'none';
        });
    }

    /**
     * Limpiar datos locales del navegador
     */
    clearLocalData() {
        console.log('🧹 Clearing local data...');
        
        const keysToRemove = [
            'user_preferences',
            'dashboard_cache',
            'form_drafts',
            'auth_token',
            'user_session',
            'ita_social_session',
            'remember_token',
            'cart_data',
            'temp_data',
            'servicio_social_data',
            'student_data'
        ];
        
        let cleared = 0;
        keysToRemove.forEach(key => {
            try {
                if (localStorage.getItem(key)) {
                    localStorage.removeItem(key);
                    cleared++;
                }
                if (sessionStorage.getItem(key)) {
                    sessionStorage.removeItem(key);
                    cleared++;
                }
            } catch (error) {
                console.warn(`Error removing ${key}:`, error);
            }
        });
        
        console.log(`✅ Cleared ${cleared} storage items`);
    }

    /**
     * Logout inmediato sin confirmación
     */
    forceLogout() {
        console.log('🚨 FORCE LOGOUT TRIGGERED');
        this.performLogout();
    }

    /**
     * Debug: mostrar información del sistema
     */
    debug() {
        console.log('=== LOGOUT SYSTEM DEBUG (ABSOLUTE PATHS) ===');
        console.log('Current URL:', window.location.href);
        console.log('Pathname:', window.location.pathname);
        console.log('Calculated logout URL:', this.getLogoutUrl());
        console.log('Calculated index URL:', this.getIndexUrl());
        console.log('Found buttons:', document.querySelectorAll('.logout-btn, .btn-logout, [data-action="logout"]').length);
        console.log('================================================');
    }
}

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🌐 DOM loaded, initializing SimpleLogoutManager with absolute paths');
    window.simpleLogout = new SimpleLogoutManager();
    
    // Debug automático
    setTimeout(() => {
        if (window.simpleLogout) {
            window.simpleLogout.debug();
        }
    }, 1000);
});

// Funciones globales para uso manual
window.performLogout = function(confirm = true) {
    console.log('🎯 Global performLogout called, confirm:', confirm);
    if (window.simpleLogout) {
        if (confirm) {
            window.simpleLogout.showConfirmationModal();
        } else {
            window.simpleLogout.performLogout();
        }
    } else {
        console.error('❌ SimpleLogoutManager not initialized');
        // Fallback de emergencia con URL absoluta
        const protocol = window.location.protocol;
        const host = window.location.host;
        const logoutUrl = `${protocol}//${host}/servicio_social_ita/auth/logout.php`;
        window.location.href = logoutUrl;
    }
};

window.forceLogout = function() {
    console.log('🚨 Global forceLogout called');
    if (window.simpleLogout) {
        window.simpleLogout.forceLogout();
    } else {
        // Fallback directo con URL absoluta
        const protocol = window.location.protocol;
        const host = window.location.host;
        const logoutUrl = `${protocol}//${host}/servicio_social_ita/auth/logout.php`;
        
        console.log('🆘 Emergency fallback redirect to:', logoutUrl);
        window.location.replace(logoutUrl);
    }
};

// Función de debug global
window.debugLogout = function() {
    if (window.simpleLogout) {
        window.simpleLogout.debug();
    } else {
        console.log('SimpleLogoutManager not available');
    }
};