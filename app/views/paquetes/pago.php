<?php
/** @var array $paquete */
/** @var array $payment */
/** @var int $dias_transcurridos */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">💳</span> Registrar Pago y Entrega</h2>
                <span class="badge badge-pendiente">Pendiente</span>
            </div>

            <!-- Package info -->
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
                        <h3>Código</h3>
                        <div class="detail-value codigo-visible"><?= Helper::escape($paquete['codigo_visible']) ?></div>
                    </div>
                    <div class="detail-section">
                        <h3>Recepción</h3>
                        <div class="detail-value"><?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y H:i') ?></div>
                    </div>
                </div>
            </div>

            <?php if ($payment['vencido']): ?>
            <div class="flash flash-warning" style="margin: 1rem 0;">
                <span class="flash-icon">⚠</span>
                <span>Paquete <strong>vencido</strong> (<?= $dias_transcurridos ?> días). 
                Comisión por atraso: <strong><?= Helper::formatMoney($payment['comision']) ?></strong></span>
            </div>
            <?php else: ?>
            <div class="flash flash-info" style="margin: 1rem 0;">
                <span class="flash-icon">ℹ</span>
                <span>Paquete dentro de plazo. Días transcurridos: <strong><?= $dias_transcurridos ?></strong></span>
            </div>
            <?php endif; ?>

            <!-- Payment form -->
            <form action="/paquetes/entregar/<?= $paquete['id'] ?>" method="POST" id="form-pago" data-validate="true">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">

                <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Detalle de Cobro
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monto_base" class="form-label required">Monto Base</label>
                        <input type="number" id="monto_base" name="monto_base" class="form-control" 
                               value="<?= number_format($payment['base'], 2, '.', '') ?>" step="0.01" required>
                        <div class="form-text">Tarifa base del paquete</div>
                    </div>
                    <div class="form-group">
                        <label for="comision_atraso" class="form-label">Comisión por Atraso</label>
                        <input type="number" id="comision_atraso" name="comision_atraso" class="form-control" 
                               value="<?= number_format($payment['comision'], 2, '.', '') ?>" step="0.01">
                        <div class="form-text">
                            <?php if ($payment['vencido']): ?>
                                Aplicada por vencimiento (Tipo: <?= Helper::config('tipo_comision', 'fijo') === 'fijo' ? 'Fijo' : 'Porcentual' ?>)
                            <?php else: ?>
                                No aplica (dentro de plazo)
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group" style="min-width: 120px;">
                        <label for="total_pagar" class="form-label"><strong>Total a Pagar</strong></label>
                        <input type="number" id="total_pagar" name="total_pagar" class="form-control" readonly 
                               value="<?= number_format($payment['total'], 2, '.', '') ?>"
                               style="font-weight: bold; font-size: 1.2rem; background: var(--gray-50);">
                    </div>
                </div>

                <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Información de Pago
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="medio_pago" class="form-label required">Medio de Pago</label>
                        <select id="medio_pago" name="medio_pago" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <option value="Efectivo">💵 Efectivo</option>
                            <option value="Tarjeta">💳 Tarjeta</option>
                            <option value="Transferencia">🏦 Transferencia</option>
                            <option value="Otro">Otros</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="referencia" class="form-label">Referencia</label>
                        <input type="text" id="referencia" name="referencia" class="form-control" 
                               placeholder="N° de ticket, transferencia, etc.">
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="2"
                              placeholder="Notas adicionales..."></textarea>
                </div>

                <div class="actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                    <button type="submit" class="btn btn-success btn-lg">
                        <span class="icon">✅</span> Confirmar Entrega y Cobro
                    </button>
                    <a href="/paquetes/detalle/<?= $paquete['id'] ?>" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseInput = document.getElementById('monto_base');
    const comisionInput = document.getElementById('comision_atraso');
    if (baseInput && comisionInput) {
        baseInput.addEventListener('input', updateTotal);
        comisionInput.addEventListener('input', updateTotal);
    }
});

function updateTotal() {
    const base = parseFloat(document.getElementById('monto_base').value) || 0;
    const comision = parseFloat(document.getElementById('comision_atraso').value) || 0;
    const totalInput = document.getElementById('total_pagar');
    if (totalInput) totalInput.value = (base + comision).toFixed(2);
}
</script>
