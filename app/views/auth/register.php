<?php
/** @var array $data */
/** @var array $errors */
?>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="logo-badge">📦</span>
        </div>
        <h1>Crear Usuario</h1>
        <p class="subtitle">Registro de nuevos usuarios del sistema</p>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error">
            <ul style="margin-left: 20px;">
                <?php foreach ($errors as $error): ?>
                <li><?= Helper::escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form action="/register" method="POST" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            
            <div class="form-group">
                <label for="nombre" class="form-label required">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" class="form-control" 
                       value="<?= Helper::escape($data['nombre'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="usuario" class="form-label required">Usuario</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" 
                           value="<?= Helper::escape($data['usuario'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label required">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <div class="form-text">Mínimo 4 caracteres</div>
                </div>
            </div>

            <div class="form-group">
                <label for="rol" class="form-label required">Rol</label>
                <select id="rol" name="rol" class="form-select" required>
                    <option value="operador" <?= ($data['rol'] ?? 'operador') === 'operador' ? 'selected' : '' ?>>Operador</option>
                    <option value="admin" <?= ($data['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Crear usuario</button>
            <a href="/dashboard" class="btn btn-outline btn-block">Volver al Dashboard</a>
        </form>
    </div>
</div>
