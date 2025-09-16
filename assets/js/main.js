// Funciones JavaScript globales para el sistema

document.addEventListener('DOMContentLoaded', function() {
    // Manejar mensajes flash
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 5000);
    });
    
    // Validación de formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
            }
        });
    });
    
    // Toggle de contraseña
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
    
    // Confirmación de acciones destructivas
    const deleteButtons = document.querySelectorAll('.btn-delete, .btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de que desea realizar esta acción? Esta acción no se puede deshacer.')) {
                e.preventDefault();
            }
        });
    });
    
    // Cerrar sesión automáticamente después de inactividad
    let inactivityTime = function() {
        let time;
        window.onload = resetTimer;
        document.onmousemove = resetTimer;
        document.onkeypress = resetTimer;
        
        function logout() {
            window.location.href = '/auth/logout.php?timeout=1';
        }
        
        function resetTimer() {
            clearTimeout(time);
            // Reemplaza SESSION_TIMEOUT con el valor adecuado en milisegundos
            const SESSION_TIMEOUT_MS = 1800 * 1000; // Ejemplo: 1800 segundos (30 minutos)
            time = setTimeout(logout, SESSION_TIMEOUT_MS); // Convertir a milisegundos
        }
    };
    
    inactivityTime();
});

// Funciones de utilidad
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

function showLoading() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="loading-spinner"></div>';
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) overlay.remove();
}

// API calls
async function apiCall(url, options = {}) {
    showLoading();
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        });
        
        const data = await response.json();
        hideLoading();
        return data;
    } catch (error) {
        hideLoading();
        console.error('Error en la llamada API:', error);
        alert('Error de conexión. Por favor, intente nuevamente.');
        throw error;
    }
}