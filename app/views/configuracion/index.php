<?php
/** @var array $config */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">⚙️</span> Configuración del Sistema</h2>
            </div>

            <form action="/configuracion/guardar" method="POST" id="form-configuracion" data-validate="true">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">

                <!-- Información del negocio -->
                <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.5rem;">
                    Información del Negocio
                </h3>
                <div class="form-group">
                    <label for="nombre_negocio" class="form-label">Nombre del Negocio</label>
                    <input type="text" id="nombre_negocio" name="nombre_negocio" class="form-control" 
                           value="<?= Helper::escape($config['nombre_negocio'] ?? 'DeUna') ?>">
                </div>

                <!-- Tarifas -->
                <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.5rem;">
                    Tarifas y Comisiones
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tarifa_base" class="form-label required">Tarifa Base</label>
                        <input type="number" id="tarifa_base" name="tarifa_base" class="form-control" 
                               value="<?= $config['tarifa_base'] ?? '10' ?>" step="0.01" min="0" required>
                        <div class="form-text">Monto base cobrado por cada paquete</div>
                    </div>
                    <div class="form-group">
                        <label for="plazo_dias" class="form-label required">Plazo (días)</label>
                        <input type="number" id="plazo_dias" name="plazo_dias" class="form-control" 
                               value="<?= $config['plazo_dias'] ?? '7' ?>" min="1" required>
                        <div class="form-text">Días de plazo para recoger el paquete (7 días = 168 horas)</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_comision" class="form-label required">Tipo de Comisión por Atraso</label>
                        <select id="tipo_comision" name="tipo_comision" class="form-select" required onchange="toggleCommissionFields()">
                            <option value="fijo" <?= ($config['tipo_comision'] ?? 'fijo') === 'fijo' ? 'selected' : '' ?>>Fijo</option>
                            <option value="porcentual" <?= ($config['tipo_comision'] ?? '') === 'porcentual' ? 'selected' : '' ?>>Porcentual</option>
                        </select>
                    </div>
                    <div class="form-group" id="monto_comision_field">
                        <label for="monto_comision" class="form-label">Monto Comisión (Fijo)</label>
                        <input type="number" id="monto_comision" name="monto_comision" class="form-control" 
                               value="<?= $config['monto_comision'] ?? '5' ?>" step="0.01" min="0">
                    </div>
                    <div class="form-group" id="porcentaje_comision_field" style="display: none;">
                        <label for="porcentaje_comision" class="form-label">Porcentaje Comisión (%)</label>
                        <input type="number" id="porcentaje_comision" name="porcentaje_comision" class="form-control" 
                               value="<?= $config['porcentaje_comision'] ?? '0' ?>" step="0.01" min="0">
                    </div>
                </div>

                <!-- Numeración -->
                <h3 style="margin: 1.5rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.5rem;">
                    Numeración de Paquetes
                </h3>
                <div class="form-group">
                    <label for="numeracion_tipo" class="form-label required">Tipo de Numeración</label>
                    <select id="numeracion_tipo" name="numeracion_tipo" class="form-select" required>
                        <option value="continua" <?= ($config['numeracion_tipo'] ?? 'continua') === 'continua' ? 'selected' : '' ?>>Continua</option>
                        <option value="diaria" <?= ($config['numeracion_tipo'] ?? '') === 'diaria' ? 'selected' : '' ?>>Diaria (reinicia cada día)</option>
                    </select>
                    <div class="form-text">Continua: secuencia única. Diaria: reinicia en 1 cada día.</div>
                </div>

                <!-- Security notice -->
                <div class="flash flash-info" style="margin-top: 1.5rem;">
                    <span class="flash-icon">ℹ</span>
                    <span>Los códigos QR contienen tokens seguros de 32 caracteres (no datos personales). 
                    Los códigos visibles (M-N) son generados automáticamente al registrar un paquete.</span>
                </div>

                <div class="actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                    <button type="submit" class="btn btn-primary">
                        <span class="icon">💾</span> Guardar Configuración
                    </button>
                    <a href="/dashboard" class="btn btn-outline">Volver al Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCommissionFields() {
    const tipo = document.getElementById('tipo_comision').value;
    document.getElementById('monto_comision_field').style.display = tipo === 'fijo' ? 'block' : 'none';
    document.getElementById('porcentaje_comision_field').style.display = tipo === 'porcentual' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    toggleCommissionFields();
});
</script>
