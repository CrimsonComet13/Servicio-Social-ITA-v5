// Funciones específicas para formularios

document.addEventListener('DOMContentLoaded', function() {
    // Validación de email en tiempo real
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('error');
                showFieldError(this, 'Por favor, ingrese un email válido.');
            } else {
                this.classList.remove('error');
                clearFieldError(this);
            }
        });
    });
    
    // Validación de contraseña en tiempo real
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.id === 'password' || input.name === 'password') {
            input.addEventListener('input', function() {
                validatePasswordStrength(this.value, this);
            });
        }
    });
    
    // Confirmación de contraseña
    const confirmPasswordInputs = document.querySelectorAll('input[name="confirm_password"]');
    confirmPasswordInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const password = document.querySelector('input[name="password"]');
            if (password && this.value !== password.value) {
                this.classList.add('error');
                showFieldError(this, 'Las contraseñas no coinciden.');
            } else {
                this.classList.remove('error');
                clearFieldError(this);
            }
        });
    });
    
    // Validación de números de control
    const numeroControlInputs = document.querySelectorAll('input[name="numero_control"]');
    numeroControlInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const numeroControl = this.value.trim();
            if (numeroControl && !isValidNumeroControl(numeroControl)) {
                this.classList.add('error');
                showFieldError(this, 'El número de control debe tener 8 dígitos.');
            } else {
                this.classList.remove('error');
                clearFieldError(this);
            }
        });
    });
    
    // Select2 para selects complejos
    const complexSelects = document.querySelectorAll('select[data-select2]');
    complexSelects.forEach(select => {
        // Implementación básica de select2 (simplificada)
        select.addEventListener('focus', function() {
            this.style.background = '#fff';
            this.style.zIndex = '1000';
        });
        
        select.addEventListener('blur', function() {
            this.style.background = '';
            this.style.zIndex = '';
        });
    });
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidNumeroControl(numeroControl) {
    return /^\d{8}$/.test(numeroControl);
}

function validatePasswordStrength(password, inputElement) {
    const errors = [];
    const PASSWORD_MIN_LENGTH = 8; // Cambia este valor según tu requerimiento
    
    if (password.length < PASSWORD_MIN_LENGTH) {
        errors.push('Debe tener al menos ' + PASSWORD_MIN_LENGTH + ' caracteres');
    }
    
    if (!/[A-Z]/.test(password)) {
        errors.push('Debe contener al menos una letra mayúscula');
    }
    
    if (!/[a-z]/.test(password)) {
        errors.push('Debe contener al menos una letra minúscula');
    }
    
    if (!/[0-9]/.test(password)) {
        errors.push('Debe contener al menos un número');
    }
    
    if (!/[^A-Za-z0-9]/.test(password)) {
        errors.push('Debe contener al menos un carácter especial');
    }
    
    if (errors.length > 0) {
        inputElement.classList.add('error');
        showFieldError(inputElement, errors.join('<br>'));
    } else {
        inputElement.classList.remove('error');
        clearFieldError(inputElement);
    }
}

function showFieldError(input, message) {
    clearFieldError(input);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.innerHTML = message;
    
    input.parentNode.appendChild(errorDiv);
}

function clearFieldError(input) {
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Upload de archivos con preview
function setupFileUpload(previewElementId, inputElementId) {
    const input = document.getElementById(inputElementId);
    const preview = document.getElementById(previewElementId);
    
    if (!input || !preview) return;
    
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        // Validar tipo de archivo
        // Define allowedTypes como un array de extensiones permitidas, por ejemplo:
        const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png']; // Reemplaza con tus tipos permitidos
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(extension)) {
            alert('Tipo de archivo no permitido. Permitidos: ' + allowedTypes.join(', '));
            this.value = '';
            return;
        }
        
        // Validar tamaño
        const maxSize = window.MAX_UPLOAD_SIZE || 5 * 1024 * 1024; // 5MB por defecto, ajusta según tu necesidad
        if (file.size > maxSize) {
            alert('El archivo es demasiado grande. Máximo: ' + formatBytes(maxSize));
            this.value = '';
            return;
        }
        
        // Mostrar preview
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = `
                <div class="file-preview">
                    <i class="fas fa-file-pdf"></i>
                    <span>${file.name}</span>
                    <small>${formatBytes(file.size)}</small>
                </div>
            `;
        }
    });
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}