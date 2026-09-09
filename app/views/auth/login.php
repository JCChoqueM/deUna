<?php
/** @var array $errors */
/** @var string $usuario */
?>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="logo-badge">📦</span>
        </div>
        <h1>DeUna</h1>
        <p class="subtitle">Sistema de Recepción, Custodia y Entrega</p>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error">
            <ul style="margin-left: 20px;">
                <?php foreach ($errors as $error): ?>
                <li><?= Helper::escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form action="/login" method="POST" data-validate="true">
            <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">
            
            <div class="form-group">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" id="usuario" name="usuario" class="form-control" 
                       value="<?= Helper::escape($usuario ?? '') ?>" required autofocus
                       placeholder="Ingrese su usuario">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" 
                       required placeholder="Ingrese su contraseña">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <span class="icon">🔑</span> Ingresar
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: #64748b;">
            <p>Usuario de prueba: <strong>admin</strong> / <strong>admin123</strong></p>
        </div>
    </div>
</div>
