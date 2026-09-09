<?php
/** @var array $usuario */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">✏️</span> Editar Usuario</h2>
            </div>

            <form action="/usuarios/actualizar/<?= $usuario['id'] ?>" method="POST" data-validate="true">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">

                <div class="form-group">
                    <label for="nombre" class="form-label required">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" 
                           value="<?= Helper::escape($usuario['nombre']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control">
                        <div class="form-text">Deje vacío para mantener la contraseña actual</div>
                    </div>
                    <div class="form-group">
                        <label for="rol" class="form-label required">Rol</label>
                        <select id="rol" name="rol" class="form-select" required>
                            <option value="operador" <?= $usuario['rol'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="estado" class="form-label required">Estado</label>
                    <select id="estado" name="estado" class="form-select" required>
                        <option value="activo" <?= $usuario['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $usuario['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Registrado</label>
                    <div class="form-text"><?= Helper::formatDate($usuario['fecha_creacion'], 'd/m/Y H:i') ?></div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="/usuarios" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
