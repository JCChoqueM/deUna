<?php
/** @var array $paquete */
/** @var string $qr_url */
/** @var string $qr_data_uri */
/** @var array $payment */
/** @var int $dias_transcurridos */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">✅</span> Confirmación de Recepción</h2>
                <span class="badge badge-pendiente">Pendiente</span>
            </div>

            <div style="text-align: center; padding: 2rem 0;">
                <h3 style="color: var(--gray-500); margin-bottom: 1.5rem;">¡Paquete registrado con éxito!</h3>
                
                <!-- Código visible -->
                <div class="qr-code-container">
                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.8rem; color: var(--gray-500);">Código visible</label>
                        <div class="codigo-visible"><?= Helper::escape($paquete['codigo_visible']) ?></div>
                        <button class="btn btn-outline btn-sm" style="margin-top: 0.5rem;" 
                                onclick="copyToClipboard('<?= Helper::escape($paquete['codigo_visible']) ?>')">
                            📋 Copiar código
                        </button>
                    </div>

                    <!-- QR Code -->
                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.8rem; color: var(--gray-500);">Código QR</label>
                        <div class="qr-code">
                            <img src="<?= $qr_data_uri ?>" alt="QR Code" width="256" height="256">
                        </div>
                        <p style="color: var(--gray-500); font-size: 0.8rem; margin-top: 0.5rem;">
                            Token: <?= Helper::escape($paquete['qr_token']) ?>
                        </p>
                    </div>

                    <!-- Internal ID -->
                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.8rem; color: var(--gray-500);">ID interno</label>
                        <div style="font-family: 'Courier New', monospace; font-size: 1.2rem; color: var(--gray-600);">
                            <?= Helper::generateInternalId($paquete['id']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package info -->
            <div style="border-top: 1px solid var(--gray-200); padding-top: 1.5rem;">
                <div class="detail-grid">
                    <div>
                        <div class="detail-section">
                            <h3>Comprador</h3>
                            <div class="detail-value"><?= Helper::escape($paquete['comprador_nombre'] ?? 'N/A') ?></div>
                            <div class="detail-value"><?= Helper::escape($paquete['comprador_celular'] ?? '') ?></div>
                        </div>
                        <div class="detail-section">
                            <h3>Vendedor</h3>
                            <div class="detail-value"><?= Helper::escape($paquete['vendedor_nombre'] ?? 'N/A') ?></div>
                            <div class="detail-value"><?= Helper::escape($paquete['vendedor_celular'] ?? '') ?></div>
                        </div>
                    </div>
                    <div>
                        <div class="detail-section">
                            <h3>Fecha de recepción</h3>
                            <div class="detail-value"><?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y H:i') ?></div>
                        </div>
                        <div class="detail-section">
                            <h3>Vence el</h3>
                            <div class="detail-value <?= $payment['vencido'] ? 'overdue' : '' ?>">
                                <?= Helper::formatDate($paquete['fecha_vencimiento'], 'd/m/Y H:i') ?>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h3>Días transcurridos</h3>
                            <div class="detail-value"><?= $dias_transcurridos ?> día(s)</div>
                        </div>
                    </div>
                </div>

                <?php if ($payment['vencido']): ?>
                <div class="flash flash-warning" style="margin-top: 1rem;">
                    <span class="flash-icon">⚠</span>
                    <span>Paquete vencido. Comisión por atraso: <?= Helper::formatMoney($payment['comision']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                <a href="/paquetes/detalle/<?= $paquete['id'] ?>" class="btn btn-outline">
                    <span class="icon">👁️</span> Ver Detalle
                </a>
                <a href="/paquetes/pago/<?= $paquete['id'] ?>" class="btn btn-success">
                    <span class="icon">🚚</span> Procesar Entrega
                </a>
                <a href="/paquetes/imprimir/<?= $paquete['id'] ?>" class="btn btn-outline" target="_blank">
                    <span class="icon">🖨️</span> Imprimir
                </a>
                <a href="/paquetes/crear" class="btn btn-primary">
                    <span class="icon">➕</span> Nuevo Paquete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
DeUna.renderQR = function() {};
// Generate QR using JS library for preview (if available)
document.addEventListener('DOMContentLoaded', function() {
    const qrImg = document.querySelector('.qr-code img');
    if (qrImg && typeof QRCode !== 'undefined') {
        // Also generate with JS library as backup
        const token = '<?= Helper::escape($qr_url) ?>';
        const container = document.createElement('div');
        container.id = 'qr-js';
        qrImg.parentNode.insertBefore(container, qrImg);
        
        QRCode.toCanvas(token, { width: 256, margin: 2, color: { dark: '#000', light: '#fff' } }, 
        function(err, canvas) {
            if (err) {
                console.error('JS QR error:', err);
            } else {
                container.innerHTML = '';
                container.appendChild(canvas);
            }
        });
    }
});
</script>
