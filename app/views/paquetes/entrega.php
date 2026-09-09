<?php
/** @var array $paquetes_recientes */
/** @var array|null $paquete */
/** @var array|null $payment */
/** @var int $dias_transcurridos */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">🚚</span> Entrega Rápida</h2>
            </div>

            <!-- Quick delivery search -->
            <form action="/paquetes/entrega" method="POST" id="form-busqueda-rapida">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="codigo-busqueda" class="form-label required">Código del Paquete</label>
                        <input type="text" id="codigo-busqueda" name="codigo_busqueda" class="form-control" 
                               placeholder="Ej: M-8 (presione Enter para buscar)" required
                               autocomplete="off">
                    </div>
                    <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </div>
            </form>

            <!-- QR Scanner info -->
            <div class="qr-scanner-container">
                <p class="text-muted" style="font-size: 0.85rem;">
                    Escaneé el código QR con un lector de códigos para acceder rápidamente al paquete.
                </p>
                <p class="text-muted" style="font-size: 0.85rem;">
                    URL de acceso directo: <code>/entrega/{token}</code> o <code>/qr-code/{token}</code>
                </p>
            </div>

            <!-- Search results -->
            <div id="quick-result" class="qr-result" style="display: none;"></div>

            <!-- Delivery form for selected package -->
            <?php if ($paquete && $paquete['estado'] === 'Pendiente'): ?>
            <div style="margin-top: 2rem; border-top: 1px solid var(--gray-200); padding-top: 1.5rem;">
                <h3 style="color: var(--gray-600); margin-bottom: 1rem;">Confirmar Entrega - <?= Helper::escape($paquete['codigo_visible']) ?></h3>

                <form action="/paquetes/entregar/<?= $paquete['id'] ?>" method="POST" id="form-entrega" data-validate="true">
                    <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">

                    <div class="detail-grid">
                        <div>
                            <div class="detail-section">
                                <h3>Comprador</h3>
                                <div class="detail-value"><?= Helper::escape($paquete['comprador_nombre'] ?? 'N/A') ?></div>
                                <div class="detail-value"><?= Helper::escape($paquete['comprador_celular'] ?? '') ?></div>
                            </div>
                            <div class="detail-section">
                                <h3>Autorizado</h3>
                                <div class="detail-value"><?= Helper::escape($paquete['autorizado_nombre'] ?? 'No especificado') ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="detail-section">
                                <h3>Código</h3>
                                <div class="detail-value codigo-visible"><?= Helper::escape($paquete['codigo_visible']) ?></div>
                            </div>
                            <div class="detail-section">
                                <h3>Días transcurridos</h3>
                                <div class="detail-value <?= $payment['vencido'] ? 'overdue' : '' ?>">
                                    <?= $dias_transcurridos ?> día(s)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <h3 style="margin: 1rem 0 0.5rem; color: var(--gray-600); font-size: 0.9rem;">Pago</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="monto_base" class="form-label required">Monto Base</label>
                            <input type="number" id="monto_base" name="monto_base" class="form-control" 
                                   value="<?= number_format($payment['base'], 2, '.', '') ?>" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="comision_atraso" class="form-label">Comisión por Atraso</label>
                            <input type="number" id="comision_atraso" name="comision_atraso" class="form-control" 
                                   value="<?= number_format($payment['comision'], 2, '.', '') ?>" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="total_pagar" class="form-label"><strong>Total</strong></label>
                            <input type="number" id="total_pagar" name="total_pagar" class="form-control" readonly 
                                   value="<?= number_format($payment['total'], 2, '.', '') ?>"
                                   style="font-weight: bold; font-size: 1.1rem; background: var(--gray-50);">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="medio_pago" class="form-label required">Medio de Pago</label>
                            <select id="medio_pago" name="medio_pago" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="referencia" class="form-label">Referencia</label>
                            <input type="text" id="referencia" name="referencia" class="form-control" 
                                   placeholder="N° de operación, ticket, etc.">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2"
                                  placeholder="Notas adicionales..."></textarea>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-success btn-lg">
                            <span class="icon">✅</span> Confirmar Entrega
                        </button>
                        <a href="/paquetes/entrega" class="btn btn-outline">Cancelar</a>
                    </div>
                </form>
            </div>
            <?php elseif ($paquete && $paquete['estado'] === 'Entregado'): ?>
            <div class="flash flash-info" style="margin-top: 1rem;">
                <span class="flash-icon">ℹ</span>
                <span>Este paquete ya fue entregado el <?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y H:i') ?></span>
            </div>
            <?php endif; ?>

            <!-- Recent pending packages -->
            <?php if (empty($paquete) && !empty($paquetes_recientes)): ?>
            <div style="margin-top: 1.5rem;">
                <h3 style="color: var(--gray-600);">Paquetes Pendientes Recientes</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Comprador</th>
                            <th>Recepción</th>
                            <th>Días</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paquetes_recientes as $pr): ?>
                        <tr <?= Helper::calculateDaysElapsed($pr['fecha_recepcion']) > 7 ? 'class="overdue"' : '' ?>>
                            <td><strong><?= Helper::escape($pr['codigo_visible']) ?></strong></td>
                            <td><?= Helper::escape($pr['comprador_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::formatDate($pr['fecha_recepcion'], 'd/m H:i') ?></td>
                            <td><?= Helper::calculateDaysElapsed($pr['fecha_recepcion']) ?></td>
                            <td><a href="/paquetes/entrega/<?= $pr['id'] ?>" class="btn btn-success btn-sm">Entregar</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on code input
    const codeInput = document.getElementById('codigo-busqueda');
    if (codeInput) codeInput.focus();

    // Auto-format code as user types (e.g., "M8" → "M-8")
    codeInput?.addEventListener('input', function(e) {
        let val = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
        if (val.length >= 2 && val[1] !== '-') {
            val = val[0] + '-' + val.substring(1);
        }
        e.target.value = val;
    });

    // Update total on payment input change
    const baseInput = document.getElementById('monto_base');
    const comisionInput = document.getElementById('comision_atraso');
    if (baseInput && comisionInput) {
        baseInput.addEventListener('input', updatePaymentTotal);
        comisionInput.addEventListener('input', updatePaymentTotal);
        updatePaymentTotal();
    }
});

function updatePaymentTotal() {
    const base = parseFloat(document.getElementById('monto_base').value) || 0;
    const comision = parseFloat(document.getElementById('comision_atraso').value) || 0;
    document.getElementById('total_pagar').value = (base + comision).toFixed(2);
}
</script>
