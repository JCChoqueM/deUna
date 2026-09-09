/* DeUna - JavaScript principal
   Funciones de validación, utilidades y manejo de interacciones
*/

// Utility: debounce
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

// Utility: format currency
function formatMoney(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.?)/, '$&,');
}

// Utility: show flash message
function showFlash(message, type) {
    const container = document.querySelector('.flash-container') || document.querySelector('.container');
    if (container) {
        const div = document.createElement('div');
        div.className = `flash flash-${type}`;
        const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : type === 'warning' ? '⚠' : 'ℹ';
        div.innerHTML = `<span class="flash-icon">${icon}</span><span>${message}</span>`;
        if (container.parentNode) {
            container.parentNode.insertBefore(div, container);
        }
        setTimeout(() => div.remove(), 5000);
    }
}

// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
    const flashes = document.querySelectorAll('.flash');
    flashes.forEach(flash => {
        setTimeout(() => {
            if (flash && flash.parentNode) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 4000);
    });
});

// Copy to clipboard
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    showFlash('Copiado al portapapeles', 'success');
}

// Print section
function printSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        const newWin = window.open('', '_blank');
        newWin.document.write(`
            <html>
            <head>
                <title>DeUna - Comprobante</title>
                <link rel="stylesheet" href="/css/style.css">
                <style>
                    body { padding: 20px; }
                    @media print { .no-print { display: none !important; } }
                </style>
            </head>
            <body>${section.innerHTML}</body>
            </html>
        `);
        newWin.document.close();
        newWin.focus();
        newWin.print();
    }
}

// Generate QR code in browser using the bundled qrcode library
function generateQRCode(data, elementId, size) {
    size = size || 256;
    const element = document.getElementById(elementId);
    if (!element) return;

    element.innerHTML = '';

    // Try using the bundled qrcode.min.js library
    if (typeof QRCode !== 'undefined') {
        element.innerHTML = '';
        try {
            QRCode.toCanvas(data, { width: size, margin: 2, color: { dark: '#000', light: '#fff' } }, function(err, canvas) {
                if (err) {
                    console.error('QR generation error:', err);
                    element.innerHTML = '<p class="text-danger">Error al generar el código QR</p>';
                } else {
                    element.appendChild(canvas);
                }
            });
        } catch (e) {
            console.error('QR error:', e);
        }
    } else {
        // Fallback: show loading message
        element.innerHTML = '<div class="text-center"><p>Generando código QR...</p></div>';
    }
}

// Form validation
function validateForm(formId, errorClass) {
    errorClass = errorClass || 'is-invalid';
    const form = document.getElementById(formId);
    if (!form) return true;

    let valid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');

    inputs.forEach(input => {
        const value = input.value.trim();
        if (!value) {
            input.classList.add(errorClass);
            input.setAttribute('aria-invalid', 'true');

            // Add error message
            let msg = input.parentNode.querySelector('.error-message');
            if (!msg) {
                msg = document.createElement('div');
                msg.className = 'error-message';
                msg.style.color = '#dc2626';
                msg.style.fontSize = '0.75rem';
                msg.style.marginTop = '4px';
                input.parentNode.appendChild(msg);
            }
            if (!msg.textContent.trim()) {
                msg.textContent = 'Este campo es obligatorio';
            }

            valid = false;
        } else {
            input.classList.remove(errorClass);
            const msg = input.parentNode.querySelector('.error-message');
            if (msg) msg.remove();
        }
    });

    return valid;
}

// Setup form validation on submit
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this.id)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
});

// Auto-format phone numbers
function formatPhone(input) {
    if (input && input.value) {
        let v = input.value.replace(/\D/g, '');
        if (v.length > 4) v = v.substring(0, 4) + ' ' + v.substring(4, 11);
        input.value = v;
    }
}

// Auto-format currency inputs
function formatCurrencyInput(input) {
    if (!input) return;
    let v = input.value.replace(/[^0-9.]/g, '');
    if (v && !isNaN(parseFloat(v))) {
        input.value = parseFloat(v).toFixed(2);
    }
}

// Quick delivery code input handler
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('codigo-busqueda');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            let val = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
            if (val.length > 1 && val[1] !== '-') {
                if (val.length >= 2) {
                    val = val[0] + '-' + val.substring(1);
                }
            }
            e.target.value = val;
        });

        codeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('form-busqueda-rapida') || 
                             document.querySelector('form[action="/paquetes/entrega"]');
                if (form) form.submit();
            }
        });
    }
});

// Toggle section visibility
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
}

// Confirm action
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX helper
async function fetchAPI(url, data = null) {
    const options = {
        headers: { 'Content-Type': 'application/json' },
    };
    if (data) {
        options.method = 'POST';
        options.body = JSON.stringify(data);
    }
    const response = await fetch(url, options);
    return response.json();
}

// Export functions
window.DeUna = {
    debounce,
    formatMoney,
    showFlash,
    copyToClipboard,
    printSection,
    generateQRCode,
    validateForm,
    formatPhone,
    formatCurrencyInput,
    toggleSection,
    confirmAction,
    fetchAPI,
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', function() {
            const tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tip);
            const rect = this.getBoundingClientRect();
            tip.style.left = rect.left + 'px';
            tip.style.top = (rect.top - 30) + 'px';
        });
        el.addEventListener('mouseleave', function() {
            const tip = document.querySelector('.tooltip');
            if (tip) tip.remove();
        });
    });
});
