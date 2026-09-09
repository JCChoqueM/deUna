<?php
/** @var array $paquete */
/** @var array $historial */
/** @var array $payment */
/** @var int $dias_transcurridos */
/** @var bool $public_view */
?>
<div class="main-content">
    <div class="container">
        <!-- Flash messages -->
        <div class="flash-container">
            <?php $flashes = Session::getFlashes(); ?>
            <?php foreach ($flashes as $flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <span class="flash-icon">
                    <?php if ($flash['type'] === 'success'): ?>✓
                    <?php elseif ($flash['type'] === 'error'): ?>✗
                    <?php elseif ($flash['type'] === 'warning'): ?>⚠
                    <?php else: ?>ℹ<?php endif; ?>
                </span>
                <span><?= Helper::escape($flash['message']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="icon">📦</span> Detalle del Paquete
                </h2>
                <div class="actions">
                    <span class="badge badge-<?= $paquete['estado'] === 'Pendiente' ? 'pendiente' : ($paquete['estado'] === 'Entregado' ? 'entregado' : 'anulado') ?>">
                        <?= Helper::escape($paquete['estado']) ?>
                    </span>
                    <?php if (!$public_view): ?>
                    <a href="/paquetes/imprimir/<?= $paquete['id'] ?>" class="btn btn-outline btn-sm" target="_blank">🖨️</a>
                    <a href="/paquetes" class="btn btn-outline btn-sm">← Lista</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-grid">
                <div>
                    <div class="detail-section">
                        <h3>ID Interno</h3>
                        <div class="detail-value"><?= Helper::generateInternalId($paquete['id']) ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Código Visible</h3>
                        <div class="detail-value codigo-visible"><?= Helper::escape($paquete['codigo_visible']) ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Número de Lista</h3>
                        <div class="detail-value"><?= $paquete['numero_lista'] ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Token QR</h3>
                        <div class="detail-value"><?= Helper::escape($paquete['qr_token']) ?></div>
                    </div>
                </div>

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
                    <div class="detail-section">
                        <h3>Autorizado</h3>
                        <div class="detail-value"><?= Helper::escape($paquete['autorizado_nombre'] ?? 'No especificado') ?></div>
                        <?php if ($paquete['autorizado_celular']): ?>
                            <div class="detail-value"><?= Helper::escape($paquete['autorizado_celular']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="detail-section">
                        <h3>Descripción</h3>
                        <div class="detail-value"><?= Helper::escape($paquete['descripcion'] ?? '') ?: 'Sin descripción' ?></div>
                    </div>
                </div>
            </div>

            <!-- Timeline / Payments -->
            <div class="detail-grid" style="margin-top: 1.5rem;">
                <div>
                    <div class="detail-section">
                        <h3>Fecha Recepción</h3>
                        <div class="detail-value"><?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y H:i') ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Fecha Vencimiento</h3>
                        <div class="detail-value <?= $payment['vencido'] ? 'overdue' : '' ?>">
                            <?= Helper::formatDate($paquete['fecha_vencimiento'], 'd/m/Y H:i') ?>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h3>Días Transcurridos</h3>
                        <div class="detail-value" style="font-size: 1.5rem;">
                            <?= $dias_transcurridos ?> día(s)
                            <?php if ($payment['vencido']): ?>
                                <span class="badge badge-atrasado" style="margin-left: 8px;">¡Vencido!</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h3>Ubicación</h3>
                        <div class="detail-value"><?= Helper::escape($paquete['ubicacion'] ?? 'DeUna') ?></div>
                    </div>
                </div>

                <div>
                    <div class="detail-section">
                        <h3>Monto Base</h3>
                        <div class="detail-value"><?= Helper::formatMoney($payment['base']) ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Comisión por Atraso</h3>
                        <div class="detail-value <?= $payment['vencido'] ? 'overdue' : '' ?>">
                            <?= Helper::formatMoney($payment['comision']) ?>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h3>Total a Cobrar</h3>
                        <div class="detail-value" style="font-size: 1.5rem; color: var(--primary);">
                            <strong><?= Helper::formatMoney($payment['total']) ?></strong>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h3>Observaciones</h3>
                        <div class="detail-value"><?= Helper::escape($paquete['observaciones'] ?? 'Sin observaciones') ?></div>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <?php if ($payment['vencido']): ?>
            <div class="flash flash-warning" style="margin-top: 1.5rem;">
                <span class="flash-icon">⚠</span>
                <span>Este paquete está <strong>vencido</strong>. Se aplicará comisión por atraso de <?= Helper::formatMoney($payment['comision']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <?php if (!$public_view && $paquete['estado'] === 'Pendiente'): ?>
            <div class="actions" style="margin-top: 1.5rem;">
                <a href="/paquetes/pago/<?= $paquete['id'] ?>" class="btn btn-success btn-lg">
                    🚚 Procesar Entrega
                </a>
                <?php if ($paquete['estado'] !== 'Entregado'): ?>
                <a href="/paquetes/anular/<?= $paquete['id'] ?>" class="btn btn-outline"
                   onclick="return confirm('¿Está seguro de anular este paquete?')">
                    ✖ Anular Paquete
                </a>
                <?php endif; ?>
            </div>
            <?php elseif ($public_view): ?>
            <p class="text-muted" style="margin-top: 1rem;">
                Código de seguimiento: <strong><?= Helper::escape($paquete['codigo_visible']) ?></strong>
            </p>
            <?php endif; ?>

            <!-- Historial -->
            <?php if (!empty($historial)): ?>
            <div style="margin-top: 2rem;">
                <h3 style="color: var(--gray-700); margin-bottom: 1rem;">Historial de Auditoría</h3>
                <?php foreach ($historial as $h): ?>
                <div class="history-item">
                    <div><strong><?= Helper::escape($h['accion']) ?></strong> — 
                       <span class="badge badge-<?= $h['estado_nuevo'] === 'Entregado' ? 'entregado' : ($h['estado_nuevo'] === 'Pendiente' ? 'pendiente' : 'anulado') ?>">
                           <?= Helper::escape($h['estado_nuevo'] ?? $h['accion']) ?>
                       </span>
                    </div>
                    <small class="history-date">
                        <?= Helper::formatDate($h['fecha_hora'], 'd/m/Y H:i') ?> 
                        por <strong><?= Helper::escape($h['usuario_nombre'] ?? 'Sistema') ?></strong>
                    </small>
                    <?php if ($h['estado_anterior'] && $h['estado_nuevo']): ?>
                        <small class="history-date">De: <?= Helper::escape($h['estado_anterior']) ?> → A: <?= Helper::escape($h['estado_nuevo']) ?></small>
                    <?php endif; ?>
                    <?php if ($h['detalle']): ?>
                        <div class="text-muted" style="margin-top: 4px;"><?= Helper::escape($h['detalle']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$public_view): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qrToken = '<?= Helper::escape($paquete['qr_token']) ?>';
    if (typeof QRCode !== 'undefined') {
        const container = document.createElement('div');
        container.style.marginTop = '1.5rem';
        container.innerHTML = '<h3 style="color: var(--gray-700); margin-bottom: 1rem;">Código QR</h3>';
        document.querySelector('.card').appendChild(container);
        
        QRCode.toCanvas(qrToken, { width: 256, margin: 2, color: { dark: '#000', light: '#fff' } },
        function(err, canvas) {
            if (!err) container.appendChild(canvas);
        });
    }
});
</script>
<?php endif; ?>
