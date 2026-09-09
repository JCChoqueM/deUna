<?php
/** @var array $paquetes */
/** @var array $filters */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">📦</span> Listado de Paquetes</h2>
                <a href="/paquetes/crear" class="btn btn-primary btn-sm">
                    <span class="icon">➕</span> Nuevo Paquete
                </a>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-form" style="margin-bottom: 1rem;">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="q" class="form-control" placeholder="Código (ej: M-8)" 
                               value="<?= Helper::escape($filters['codigo'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <input type="text" name="comprador" class="form-control" placeholder="Comprador" 
                               value="<?= Helper::escape($filters['comprador'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <input type="text" name="vendedor" class="form-control" placeholder="Cel. Vendedor" 
                               value="<?= Helper::escape($filters['vendedor'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <select name="estado" class="form-select auto-filter">
                            <option value="">Todos los estados</option>
                            <option value="Pendiente" <?= ($filters['estado'] ?? '') === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Entregado" <?= ($filters['estado'] ?? '') === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                            <option value="Anulado" <?= ($filters['estado'] ?? '') === 'Anulado' ? 'selected' : '' ?>>Anulado</option>
                            <option value="Incidencia" <?= ($filters['estado'] ?? '') === 'Incidencia' ? 'selected' : '' ?>>Incidencia</option>
                        </select>
                    </div>
                    <div class="form-group" style="min-width: 120px;">
                        <input type="date" name="fecha_desde" class="form-control auto-filter" 
                               value="<?= Helper::escape($filters['fecha_desde'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="min-width: 120px;">
                        <input type="date" name="fecha_hasta" class="form-control auto-filter" 
                               value="<?= Helper::escape($filters['fecha_hasta'] ?? '') ?>">
                    </div>
                </div>
            </form>

            <!-- Packages table -->
            <?php if (!empty($paquetes)): ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Código</th>
                            <th>Vendedor</th>
                            <th>Comprador</th>
                            <th>Autorizado</th>
                            <th>Recepción</th>
                            <th>Vence</th>
                            <th>Días</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paquetes as $p):
                            $dias = Helper::calculateDaysElapsed($p['fecha_recepcion']);
                            $vencido = $dias > 7;
                        ?>
                        <tr class="<?= $vencido ? 'overdue' : '' ?>">
                            <td><?= $p['numero_lista'] ?></td>
                            <td><strong><?= Helper::escape($p['codigo_visible']) ?></strong></td>
                            <td><?= Helper::escape($p['vendedor_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::escape($p['comprador_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::escape($p['autorizado_nombre'] ?? '-') ?></td>
                            <td><?= Helper::formatDate($p['fecha_recepcion'], 'd/m H:i') ?></td>
                            <td><?= Helper::formatDate($p['fecha_vencimiento'] ?? $p['fecha_recepcion'], 'd/m H:i') ?></td>
                            <td<?= $vencido ? ' class="overdue"' : '' ?>><?= $dias ?></td>
                            <td><?= Helper::formatMoney($p['monto_base'] ?? 0) ?></td>
                            <td>
                                <span class="badge badge-<?= $p['estado'] === 'Pendiente' ? 'pendiente' : ($p['estado'] === 'Entregado' ? 'entregado' : ($p['estado'] === 'Anulado' ? 'anulado' : 'incidencia')) ?>">
                                    <?= Helper::escape($p['estado']) ?>
                                </span>
                                <?php if ($vencido && $p['estado'] === 'Pendiente'): ?>
                                    <span class="badge badge-atrasado">Vencido</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="/paquetes/detalle/<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Ver detalle">👁️</a>
                                <?php if ($p['estado'] === 'Pendiente'): ?>
                                <a href="/paquetes/pago/<?= $p['id'] ?>" class="btn btn-success btn-sm" title="Entregar">🚚</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted" style="padding: 2rem; text-align: center;">
                No se encontraron paquetes. 
                <a href="/paquetes/crear">Registrar el primer paquete</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
