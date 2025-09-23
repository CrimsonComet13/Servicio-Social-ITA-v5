/**
 * Sistema de Logout Frontend
 * Script para manejar logout desde JavaScript de manera consistente
 */

class LogoutManager {
    constructor() {
        this.logoutUrl = './auth/logout.php';
        this.isLoggingOut = false;
        this.init();
    }

    init() {
        // Detectar elementos de logout automáticamente
        this.attachLogoutListeners();
        
        // Manejar confirmaciones de logout
        this.setupConfirmationModal();
    }

    /**
     * Adjuntar listeners a elementos de logout
     */
    attachLogoutListeners() {
        // Buscar todos los elementos con clase 'logout-btn' o 'btn-logout'
        const logoutButtons = document.querySelectorAll('.logout-btn, .btn-logout, [data-action="logout"]');
        
        logoutButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const confirmRequired = button.dataset.confirm !== 'false';
                
                if (confirmRequired) {
                    this.showConfirmationModal();
                } else {
                    this.performLogout();
                }
            });
        });
    }

    /**
     * Mostrar modal de confirmación
     */
    showConfirmationModal() {
        // Crear modal dinámicamente si no existe
        if (!document.getElementById('logoutModal')) {
            this.createConfirmationModal();
        }
        
        const modal = document.getElementById('logoutModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    /**
     * Crear modal de confirmación
     */
    createConfirmationModal() {
        const modal = document.createElement('div');
        modal.id = 'logoutModal';
        modal.className = 'logout-modal';
        modal.innerHTML = `
            <div class="logout-modal-overlay"></div>
            <div class="logout-modal-content">
                <div class="logout-modal-header">
                    <div class="logout-modal-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h3>¿Cerrar Sesión?</h3>
                    <p>¿Estás seguro de que deseas cerrar tu sesión?</p>
                </div>
                <div class="logout-modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancelLogout">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmLogout">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </button>
                </div>
            </div>
        `;
        
        // Agregar estilos
        const style = document.createElement('style');
        style.textContent = `
            .logout-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 9999;
                display: none;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease-out;
            }
            
            .logout-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
            }
            
            .logout-modal-content {
                position: relative;
                background: white;
                border-radius: 16px;
                padding: 2rem;
                max-width: 400px;
                width: 90%;
                margin: 1rem;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                animation: slideIn 0.3s ease-out;
                text-align: center;
            }
            
            .logout-modal-header {
                margin-bottom: 2rem;
            }
            
            .logout-modal-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #ef4444, #f87171);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                color: white;
                font-size: 1.5rem;
            }
            
            .logout-modal-header h3 {
                font-size: 1.5rem;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 0.5rem;
            }
            
            .logout-modal-header p {
                color: #6b7280;
                font-size: 1rem;
                line-height: 1.5;
            }
            
            .logout-modal-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
            }
            
            .logout-modal .btn {
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                border: none;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.95rem;
            }
            
            .logout-modal .btn-secondary {
                background: #6b7280;
                color: white;
            }
            
            .logout-modal .btn-danger {
                background: linear-gradient(135deg, #ef4444, #f87171);
                color: white;
            }
            
            .logout-modal .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideIn {
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
                .logout-modal-content {
                    padding: 1.5rem;
                }
                
                .logout-modal-actions {
                    flex-direction: column;
                }
                
                .logout-modal .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        `;
        
        document.head.appendChild(style);
        document.body.appendChild(modal);
    }

    /**
     * Configurar modal de confirmación
     */
    setupConfirmationModal() {
        document.addEventListener('click', (e) => {
            if (e.target.id === 'cancelLogout' || e.target.closest('.logout-modal-overlay')) {
                this.hideConfirmationModal();
            }
            
            if (e.target.id === 'confirmLogout') {
                this.hideConfirmationModal();
                this.performLogout();
            }
        });

        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('logoutModal')?.style.display === 'flex') {
                this.hideConfirmationModal();
            }
        });
    }

    /**
     * Ocultar modal de confirmación
     */
    hideConfirmationModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    /**
     * Realizar logout via AJAX
     */
    async performLogout(force = false) {
        if (this.isLoggingOut) {
            return;
        }

        this.isLoggingOut = true;
        
        try {
            // Mostrar indicador de carga si existe
            this.showLoadingState();
            
            const formData = new FormData();
            formData.append('action', 'logout');
            
            const url = force ? `${this.logoutUrl}?action=force` : this.logoutUrl;
            
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            
            if (data.success || data.redirect) {
                // Limpiar datos locales
                this.clearLocalData();
                
                // Redirigir
                window.location.href = data.redirect || './auth/logout-success.php?logout=success';
            } else {
                console.error('Error en logout:', data.message);
                // Redirigir a página de error
                window.location.href = './auth/logout-success.php?logout=error';
            }
            
        } catch (error) {
            console.error('Error crítico en logout:', error);
            // Logout de emergencia - redireccionar directamente
            window.location.href = `${this.logoutUrl}?action=emergency`;
        } finally {
            this.isLoggingOut = false;
        }
    }

    /**
     * Mostrar estado de carga
     */
    showLoadingState() {
        const logoutButtons = document.querySelectorAll('.logout-btn, .btn-logout, [data-action="logout"]');
        
        logoutButtons.forEach(button => {
            const originalText = button.innerHTML;
            button.dataset.originalText = originalText;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cerrando sesión...';
            button.disabled = true;
        });
    }

    /**
     * Limpiar datos locales del navegador
     */
    clearLocalData() {
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
            try {
                localStorage.removeItem(key);
                sessionStorage.removeItem(key);
            } catch (error) {
                console.warn(`Error removing ${key}:`, error);
            }
        });
    }

    /**
     * Logout forzado (sin confirmación)
     */
    forceLogout() {
        return this.performLogout(true);
    }

    /**
     * Verificar estado de sesión
     */
    async checkSession() {
        try {
            const response = await fetch(`${this.logoutUrl}?action=check`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            return data.logged_in;
        } catch (error) {
            console.warn('Error verificando sesión:', error);
            return false;
        }
    }
}

// Inicializar automáticamente cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.logoutManager = new LogoutManager();
});

// Función global para logout manual
window.performLogout = function(confirm = true) {
    if (window.logoutManager) {
        if (confirm) {
            window.logoutManager.showConfirmationModal();
        } else {
            window.logoutManager.performLogout();
        }
    }
};

// Función global para logout forzado
window.forceLogout = function() {
    if (window.logoutManager) {
        window.logoutManager.forceLogout();
    }
};

// Exportar para módulos ES6 si es necesario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LogoutManager;
}