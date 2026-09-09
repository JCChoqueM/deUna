/* DeUna - JavaScript de Validaciones
   Validaciones del lado del cliente para formularios
*/

(function() {
    'use strict';

    // Formulario de login
    function validarLogin(form) {
        let valid = true;
        const usuario = form.querySelector('[name="usuario"]');
        const password = form.querySelector('[name="password"]');

        if (usuario && !usuario.value.trim()) {
            showError(usuario, 'Ingrese su nombre de usuario');
            valid = false;
        }
        if (password && !password.value.trim()) {
            showError(password, 'Ingrese su contraseña');
            valid = false;
        }
        return valid;
    }

    // Formulario de nuevo paquete
    function validarNuevoPaquete(form) {
        let valid = true;
        const comprador = form.querySelector('[name="comprador_nombre"]');
        const monto = form.querySelector('[name="monto_base"]');

        if (comprador && !comprador.value.trim()) {
            showError(comprador, 'Ingrese el nombre del comprador');
            valid = false;
        }
        if (monto && parseFloat(monto.value) <= 0) {
            showError(monto, 'Ingrese un monto válido');
            valid = false;
        }
        return valid;
    }

    // Formulario de entrega/pago
    function validarEntrega(form) {
        let valid = true;
        const medioPago = form.querySelector('[name="medio_pago"]');

        if (medioPago && !medioPago.value) {
            showError(medioPago, 'Seleccione un medio de pago');
            valid = false;
        }

        const montoBase = form.querySelector('[name="monto_base"]');
        if (montoBase && parseFloat(montoBase.value) <= 0) {
            showError(montoBase, 'Ingrese un monto base válido');
            valid = false;
        }

        return valid;
    }

    // Mostrar error
    function showError(field, message) {
        field.classList.add('form-control-error');
        field.style.borderColor = '#dc2626';

        let existing = field.parentNode.querySelector('.validation-error');
        if (existing) existing.remove();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error';
        errorDiv.style.color = '#dc2626';
        errorDiv.style.fontSize = '0.75rem';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = message;

        field.parentNode.appendChild(errorDiv);

        field.addEventListener('input', function() {
            this.style.borderColor = '';
            const err = this.parentNode.querySelector('.validation-error');
            if (err) err.remove();
        });
    }

    // Auto-format código visible (M8 → M-8)
    function formatearCodigo(input) {
        let val = input.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
        if (val.length >= 2 && val[1] !== '-') {
            val = val[0] + '-' + val.substring(1);
        }
        input.value = val;
    }

    // Auto-format teléfono (solo números)
    function formatearTelefono(input) {
        input.value = input.value.replace(/[^0-9]/g, '');
    }

    // Setup event listeners
    function setupValidation(formSelector, validator) {
        const form = document.querySelector(formSelector);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            if (!validator(this)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    }

    // Auto-format inputs
    function setupAutoFormats() {
        // Código visible
        const codigos = document.querySelectorAll('input[name="codigo_busqueda"], input[name="codigo"]');
        codigos.forEach(input => {
            input.addEventListener('input', function() {
                formatearCodigo(this);
            });
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const form = this.closest('form');
                    if (form) form.submit();
                }
            });
        });

        // Teléfonos
        const phones = document.querySelectorAll('input[name*="celular"], input[name*="telefono"]');
        phones.forEach(input => {
            input.addEventListener('input', function() {
                formatearTelefono(this);
            });
        });
    }

    // Live payment calculation
    function setupPaymentCalculation() {
        const baseInput = document.getElementById('monto_base');
        const comisionInput = document.getElementById('comision_atraso');
        const totalInput = document.getElementById('total_pagar');

        if (baseInput && comisionInput && totalInput) {
            baseInput.addEventListener('input', calcularTotal);
            comisionInput.addEventListener('input', calcularTotal);
        }
        
        function calcularTotal() {
            const base = parseFloat(baseInput.value) || 0;
            const comision = parseFloat(comisionInput.value) || 0;
            totalInput.value = (base + comision).toFixed(2);
        }
        
        // Initial calculation
        calcularTotal();
    }

    // Initialize on DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Form validations
        setupValidation('form[data-validate="login"]', validarLogin);
        setupValidation('form[id="form-nuevo-paquete"]', validarNuevoPaquete);
        setupValidation('form[id="form-entrega"]', validarEntrega);
        setupValidation('form[id="form-pago"]', validarEntrega);

        // Auto-formatting
        setupAutoFormats();

        // Payment calculation
        setupPaymentCalculation();

        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('.alert-auto-dismiss, .flash');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + / = focus search
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            const search = document.querySelector('input[type="text"], input[type="search"]');
            if (search) search.focus();
        }
    });
})();
