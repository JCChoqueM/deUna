<?php
/** @var array $config */
/** @var string $desde */
/** @var string $hasta */
/** @var float $total_ingresos */
/** @var float $total_comisiones */
/** @var array $by_medio_pago */
/** @var array $paquetes_entregados */
/** @var array $top_buyers */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">📊</span> Reportes</h2>
                <div>
                    <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="date" name="desde" class="form-control" style="width: 140px;" 
                               value="<?= Helper::escape($desde) ?>">
                        <input type="date" name="hasta" class="form-control" style="width: 140px;" 
                               value="<?= Helper::escape($hasta) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    </form>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-value"><?= Helper::formatMoney($total_ingresos) ?></div>
                    <div class="stat-label">Total Ingresos</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-value"><?= Helper::formatMoney($total_comisiones) ?></div>
                    <div class="stat-label">Total Comisiones</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?= $paquetes_entregados['total'] ?? 0 ?></div>
                    <div class="stat-label">Paquetes Entregados</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-value"><?= Helper::formatMoney($paquetes_entregados['base_total'] ?? 0) ?></div>
                    <div class="stat-label">Monto Base Cobrado</div>
                </div>
            </div>

            <!-- Payments by method -->
            <h3 style="color: var(--gray-700); margin: 1.5rem 0 0.75rem;">Pagos por Medio de Pago</h3>
            <?php if (!empty($by_medio_pago)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Medio de Pago</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($by_medio_pago as $mp): ?>
                    <tr>
                        <td>
                            <?php
                            $icons = ['Efectivo' => '💵', 'Tarjeta' => '💳', 'Transferencia' => '🏦', 'Otro' => '📋'];
                            echo $icons[$mp['medio_pago']] ?? '💰';
                            ?>
                            <?= Helper::escape($mp['medio_pago']) ?>
                        </td>
                        <td><?= $mp['cantidad'] ?></td>
                        <td><?= Helper::formatMoney($mp['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No hay datos de pagos en este período.</p>
            <?php endif; ?>

            <!-- Top buyers -->
            <h3 style="color: var(--gray-700); margin: 1.5rem 0 0.75rem;">Top Compradores</h3>
            <?php if (!empty($top_buyers)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Comprador</th>
                        <th>Paquetes</th>
                        <th>Total Pagado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_buyers as $i => $b): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= Helper::escape($b['nombre_completo']) ?></td>
                        <td><?= $b['total_paquetes'] ?></td>
                        <td><?= Helper::formatMoney($b['total_cobrado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No hay datos de compradores en este período.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
