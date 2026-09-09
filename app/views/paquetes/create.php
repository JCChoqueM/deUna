<?php
/** @var array $vendedores */
/** @var array $compradores */
/** @var int $next_numero */
?>
<div class="main-content">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span class="icon">📦</span> Nuevo Paquete</h2>
            </div>

            <form action="/paquetes/guardar" method="POST" id="form-nuevo-paquete" data-validate="true">
                <input type="hidden" name="csrf_token" value="<?= Helper::csrfToken() ?>">

                <!-- Información del paquete -->
                <h3 style="margin: 1rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Información del Paquete
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="numero_lista" class="form-label">Número de Lista</label>
                        <input type="number" id="numero_lista" name="numero_lista" class="form-control" 
                               value="<?= $next_numero ?>" min="1">
                        <div class="form-text">Se asigna automáticamente si se deja vacío</div>
                    </div>
                    <div class="form-group">
                        <label for="monto_base" class="form-label required">Monto Base</label>
                        <input type="number" id="monto_base" name="monto_base" class="form-control" 
                               step="0.01" min="0" value="<?= Helper::formatMoney(Helper::config('tarifa_base', 10)) ?>">
                        <div class="form-text">Tarifa base configurada: <?= Helper::formatMoney(Helper::config('tarifa_base', 10)) ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion" class="form-label">Descripción / Detalle del paquete</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="2" 
                              placeholder="Describe brevemente el contenido del paquete"></textarea>
                </div>

                <!-- Información del vendedor -->
                <h3 style="margin: 1rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Información del Vendedor
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="vendedor_id" class="form-label">Vendedor existente</label>
                        <select id="vendedor_id" name="vendedor_id" class="form-select" onchange="toggleVendorInput(this.value)">
                            <option value="0">- Nuevo vendedor -</option>
                            <?php foreach ($vendedores as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= Helper::escape($v['nombre']) ?> (<?= Helper::escape($v['celular']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="vendor-new-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vendedor_nombre" class="form-label">Nombre del Vendedor</label>
                            <input type="text" id="vendedor_nombre" name="vendedor_nombre" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="vendedor_celular" class="form-label">Celular</label>
                            <input type="text" id="vendedor_celular" name="vendedor_celular" class="form-control" 
                                   inputmode="numeric" pattern="[0-9]*">
                        </div>
                    </div>
                </div>

                <!-- Información del comprador -->
                <h3 style="margin: 1rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Información del Comprador
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="comprador_nombre" class="form-label required">Nombre Completo</label>
                        <input type="text" id="comprador_nombre" name="comprador_nombre" class="form-control" required 
                               placeholder="Ej: María Cáceres">
                    </div>
                    <div class="form-group">
                        <label for="comprador_celular" class="form-label">Celular</label>
                        <input type="text" id="comprador_celular" name="comprador_celular" class="form-control" 
                               inputmode="numeric" pattern="[0-9]*">
                    </div>
                </div>

                <!-- Información del autorizado -->
                <h3 style="margin: 1rem 0 0.75rem; color: var(--gray-600); font-size: 0.9rem;">
                    Información del Autorizado (quién retira)
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="autorizado_nombre" class="form-label">Nombre</label>
                        <input type="text" id="autorizado_nombre" name="autorizado_nombre" class="form-control" 
                               placeholder="Persona que recogerá el paquete">
                    </div>
                    <div class="form-group">
                        <label for="autorizado_celular" class="form-label">Celular</label>
                        <input type="text" id="autorizado_celular" name="autorizado_celular" class="form-control" 
                               inputmode="numeric" pattern="[0-9]*">
                    </div>
                    <div class="form-group">
                        <label for="relacion" class="form-label">Relación</label>
                        <select id="relacion" name="relacion" class="form-select">
                            <option value="Comprador">Comprador</option>
                            <option value="Familiar">Familiar</option>
                            <option value="Amigo">Amigo</option>
                            <option value="Empleado">Empleado</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="2" 
                              placeholder="Notas adicionales sobre el paquete o la recepción"></textarea>
                </div>

                <div class="actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                    <button type="submit" class="btn btn-primary">
                        <span class="icon">💾</span> Guardar y Generar Código
                    </button>
                    <a href="/paquetes" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleVendorInput(value) {
    const newFields = document.getElementById('vendor-new-fields');
    const vendedorId = document.getElementById('vendedor_id');
    
    if (value == '0' || !value) {
        newFields.style.display = 'block';
        vendedorId.disabled = true;
    } else {
        newFields.style.display = 'none';
        vendedorId.disabled = false;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleVendorInput(document.getElementById('vendedor_id').value);
});
</script>
