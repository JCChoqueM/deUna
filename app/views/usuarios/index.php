<?php
/** @var array $usuarios */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">👥</span> Gestión de Usuarios</h2>
                <a href="/register" class="btn btn-primary btn-sm">
                    <span class="icon">➕</span> Nuevo Usuario
                </a>
            </div>

            <?php if (!empty($usuarios)): ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registrado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= Helper::escape($u['nombre']) ?></td>
                            <td><?= Helper::escape($u['usuario']) ?></td>
                            <td>
                                <span class="badge badge-<?= $u['rol'] === 'admin' ? 'primary' : 'pendiente' ?>">
                                    <?= ucfirst($u['rol']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u['estado'] === 'activo' ? 'entregado' : 'anulado' ?>">
                                    <?= ucfirst($u['estado']) ?>
                                </span>
                            </td>
                            <td><?= Helper::formatDate($u['fecha_creacion'], 'd/m/Y') ?></td>
                            <td class="actions">
                                <a href="/usuarios/editar/<?= $u['id'] ?>" class="btn btn-outline btn-sm" title="Editar">✏️</a>
                                <a href="/usuarios/eliminar/<?= $u['id'] ?>" class="btn btn-danger btn-sm" title="Desactivar"
                                   onclick="return confirm('¿Desea desactivar este usuario?')">✖</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted" style="text-align: center; padding: 2rem;">
                No hay usuarios registrados.
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
