/* DeUna - App JavaScript
   Manejo específico de la aplicación: QR, entrega, pagos
*/

// Generate QR code for a package token
function renderQR(token, containerId, size) {
    size = size || 256;
    const container = document.getElementById(containerId);
    if (!container) return;

    // Use the bundled qrcode library
    if (typeof QRCode !== 'undefined') {
        container.innerHTML = '';
        QRCode.toCanvas(token, {
            width: size,
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' }
        }, function(err, canvas) {
            if (err) {
                console.error('QR error:', err);
                container.innerHTML = '<p class="text-danger">Error al generar código QR</p>';
            } else {
                container.innerHTML = '';
                container.appendChild(canvas);
                // Add download link
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = 'qr-deuna.png';
                link.className = 'btn btn-outline btn-sm';
                link.style.marginTop = '10px';
                link.innerHTML = '⬇ Descargar QR';
                container.parentNode.appendChild(link);
            }
        });
    } else {
        container.innerHTML = '<p class="text-muted">Cargando código QR...</p>';
    }
}

// Format currency
function fmtMoney(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Update payment total in real-time
function updatePaymentTotal() {
    const baseInput = document.getElementById('monto_base');
    const comisionInput = document.getElementById('comision_atraso');
    const totalInput = document.getElementById('total_pagar');
    
    if (baseInput && comisionInput && totalInput) {
        const base = parseFloat(baseInput.value) || 0;
        const comision = parseFloat(comisionInput.value) || 0;
        const total = base + comision;
        totalInput.value = total.toFixed(2);
        totalInput.disabled = false;
    }
}

// Setup payment calculation
document.addEventListener('DOMContentLoaded', function() {
    const baseInput = document.getElementById('monto_base');
    const comisionInput = document.getElementById('comision_atraso');
    
    if (baseInput && comisionInput) {
        baseInput.addEventListener('input', updatePaymentTotal);
        comisionInput.addEventListener('input', updatePaymentTotal);
    }
    
    // Auto-focus on code input for quick delivery
    const codeInput = document.getElementById('codigo-busqueda');
    if (codeInput) {
        codeInput.focus();
    }
});

// Generate QR for package display
function initPackageQR() {
    const qrToken = document.querySelector('[data-qr-token]');
    const qrContainer = document.getElementById('qr-container');
    
    if (qrToken && qrContainer) {
        const token = qrToken.getAttribute('data-qr-token');
        renderQR(token, 'qr-container', 256);
    }
}

// Quick delivery: search and display package
async function quickSearch(code) {
    if (!code || code.length < 2) return;
    
    const result = await fetchAPI('/paquetes/apiBuscar', { codigo: code });
    if (result && result.success) {
        displayQuickResult(result.data);
    } else {
        showFlash(result?.message || 'Paquete no encontrado', 'error');
    }
}

// Display quick delivery result
function displayQuickResult(data) {
    const container = document.getElementById('quick-result');
    if (!container) return;
    
    const overdue = data.dias_transcurridos > 7;
    const badgeClass = overdue ? 'badge-atrasado' : (data.estado === 'Entregado' ? 'badge-entregado' : 'badge-pendiente');
    
    let html = `
        <div class="card">
            <div class="card-header">
                <span class="codigo-visible">${data.codigo_visible}</span>
                <span class="badge ${badgeClass}">${data.estado}</span>
            </div>
            <div class="detail-grid">
                <div>
                    <div class="detail-section">
                        <h3>Comprador</h3>
                        <div class="detail-value">${data.comprador_nombre || 'N/A'}</div>
                    </div>
                    <div class="detail-section">
                        <h3>Vendedor</h3>
                        <div class="detail-value">${data.vendedor_nombre || 'N/A'} (${data.vendedor_celular || 'N/A'})</div>
                    </div>
                    <div class="detail-section">
                        <h3>Autorizado</h3>
                        <div class="detail-value">${data.autorizado_nombre || 'No especificado'}</div>
                    </div>
                </div>
                <div>
                    <div class="detail-section">
                        <h3>Fecha de recepción</h3>
                        <div class="detail-value">${data.fecha_recepcion}</div>
                    </div>
                    <div class="detail-section">
                        <h3>Días transcurridos</h3>
                        <div class="detail-value ${overdue ? 'overdue' : ''}">${data.dias_transcurridos} día(s)</div>
                    </div>
                    <div class="detail-section">
                        <h3>Monto a cobrar</h3>
                        <div class="detail-value">
                            <strong>Base: ${fmtMoney(data.monto_base)}</strong><br>
                            ${overdue ? 'Comisión atraso: ' + fmtMoney(data.comision_atraso) + '<br>' : ''}
                            <strong>Total: ${fmtMoney(data.total_pagar)}</strong>
                        </div>
                    </div>
                </div>
            </div>
            ${data.estado === 'Pendiente' ? `
            <div class="actions" style="margin-top: 1rem;">
                <a href="/paquetes/entrega/${data.id}" class="btn btn-primary btn-sm">Procesar entrega</a>
            </div>
            ` : '<p class="text-info">Este paquete ya fue entregado.</p>'}
        </div>
    `;
    
    container.innerHTML = html;
    container.style.display = 'block';
}

// Live search for packages
let searchTimer = null;
function setupLiveSearch() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        const query = this.value.trim();
        if (query.length < 1) {
            document.getElementById('search-results').innerHTML = '';
            return;
        }
        searchTimer = setTimeout(() => {
            fetch(`/paquetes/apiBuscarLista?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    const results = document.getElementById('search-results');
                    if (results) {
                        if (data.success && data.data.length > 0) {
                            let html = '';
                            data.data.forEach(pkg => {
                                html += `<a href="/paquetes/detalle/${pkg.id}" class="dropdown-item">${pkg.codigo_visible} - ${pkg.comprador_nombre}</a>`;
                            });
                            results.innerHTML = html;
                        } else {
                            results.innerHTML = '<div class="dropdown-item text-muted">No se encontraron resultados</div>';
                        }
                    }
                })
                .catch(err => console.error('Search error:', err));
        }, 300);
    });
}

// Setup filters
function setupFilters() {
    const filters = document.querySelector('.filter-form');
    if (filters) {
        filters.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (!input.value) input.disabled = false;
            });
        });
    }
    
    // Auto-submit on filter change
    const autoFilters = document.querySelectorAll('.auto-filter');
    autoFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            this.form.submit();
        });
    });
}

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    initPackageQR();
    setupLiveSearch();
    setupFilters();
});
