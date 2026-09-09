<?php
/** @var array $stats */
/** @var array $pendientes */
/** @var array $recientes */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">📊</span> Dashboard</h2>
                <div>
                    <span class="text-muted">Bienvenido, <?= Helper::escape(Auth::getUserName()) ?></span>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="stats-grid">
                <div class="stat-card warning">
                    <div class="stat-value"><?= $stats['pendientes'] ?? 0 ?></div>
                    <div class="stat-label">Paquetes Pendientes</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-value"><?= $stats['atrasados'] ?? 0 ?></div>
                    <div class="stat-label">Paquetes Atrasados</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?= $stats['entregados_hoy'] ?? 0 ?></div>
                    <div class="stat-label">Entregados Hoy</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-value"><?= $stats['recibidos_hoy'] ?? 0 ?></div>
                    <div class="stat-label">Recibidos Hoy</div>
                </div>
                <div class="stat-card primary">
                    <div class="stat-value"><?= Helper::formatMoney($stats['monto_cobrado'] ?? 0) ?></div>
                    <div class="stat-label">Monto Cobrado Hoy</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions" style="margin-bottom: 1rem;">
                <a href="/paquetes/crear" class="btn btn-primary">
                    <span class="icon">➕</span> Nuevo Paquete
                </a>
                <a href="/paquetes/entrega" class="btn btn-success">
                    <span class="icon">🚚</span> Entrega Rápida
                </a>
            </div>

            <!-- Pending packages -->
            <?php if (!empty($pendientes)): ?>
            <h3 style="margin-bottom: 0.75rem; color: var(--warning);">
                Paquetes Pendientes (<?= count($pendientes) ?>)
            </h3>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Comprador</th>
                            <th>Vendedor</th>
                            <th>Recepción</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendientes as $p): 
                            $dias = Helper::calculateDaysElapsed($p['fecha_recepcion']);
                            $vencido = $dias > 7;
                        ?>
                        <tr class="<?= $vencido ? 'overdue' : '' ?>">
                            <td><strong><?= Helper::escape($p['codigo_visible']) ?></strong></td>
                            <td><?= Helper::escape($p['comprador_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::escape($p['vendedor_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::formatDate($p['fecha_recepcion'], 'd/m/Y H:i') ?></td>
                            <td<?= $vencido ? ' class="overdue"' : '' ?>><?= $dias ?> día(s)</td>
                            <td>
                                <span class="badge badge-pendiente">Pendiente</span>
                                <?php if ($vencido): ?>
                                    <span class="badge badge-atrasado">Vencido</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/paquetes/pago/<?= $p['id'] ?>" class="btn btn-success btn-sm">Entregar</a>
                                <a href="/paquetes/detalle/<?= $p['id'] ?>" class="btn btn-outline btn-sm">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted" style="padding: 2rem; text-align: center;">
                No hay paquetes pendientes. ¡Todo al día!
            </p>
            <?php endif; ?>

            <!-- Recent packages -->
            <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-700);">Actividad Reciente</h3>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Comprador</th>
                            <th>Vendedor</th>
                            <th>Recepción</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recientes as $r): ?>
                        <tr>
                            <td><strong><?= Helper::escape($r['codigo_visible']) ?></strong></td>
                            <td><?= Helper::escape($r['comprador_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::escape($r['vendedor_nombre'] ?? 'N/A') ?></td>
                            <td><?= Helper::formatDate($r['fecha_recepcion'], 'd/m/Y H:i') ?></td>
                            <td><span class="badge badge-<?= strtolower($r['estado'] === 'Entregado' ? 'entregado' : 'pendiente') ?>"><?= Helper::escape($r['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
