<?php
/** @var array $paquete */
/** @var string $qr_data_uri */
/** @var string $qr_url */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeUna - Imprimir Etiqueta <?= Helper::escape($paquete['codigo_visible']) ?></title>
    <style>
        body { font-family: 'Courier New', monospace; margin: 0; padding: 0; background: white; }
        .print-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .print-header { text-align: center; border-bottom: 3px solid #1a56db; padding-bottom: 15px; margin-bottom: 20px; }
        .print-logo { font-size: 2rem; font-weight: bold; color: #1a56db; }
        .print-title { font-size: 1.5rem; color: #0f172a; margin-top: 8px; }
        .print-section { margin-bottom: 20px; }
        .print-section h3 { font-size: 0.9rem; text-transform: uppercase; color: #64748b; margin-bottom: 5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .print-value { font-size: 1.1rem; color: #0f172a; margin-bottom: 5px; }
        .qr-print { float: right; width: 150px; height: 150px; }
        .codigo-print { font-family: 'Courier New', monospace; font-size: 2.5rem; font-weight: bold; color: #1a56db; letter-spacing: 10px; background: white; padding: 10px 20px; border: 2px solid #cbd5e1; border-radius: 8px; display: inline-block; margin: 10px 0; }
        .clear-both { clear: both; }
        .print-actions { text-align: center; margin-top: 20px; }
        .btn-print { background: #1a56db; color: white; border: none; padding: 10px 25px; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        .no-print-btn { text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="no-print-btn">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Etiqueta</button>
        <a href="javascript:history.back()" style="margin-left: 10px; text-decoration: none;">← Volver</a>
    </div>

    <div class="print-container">
        <div class="print-header">
            <div class="print-logo">📦 DeUna</div>
            <div class="print-title">Etiqueta de Paquete - <?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y') ?></div>
        </div>

        <div class="qr-print">
            <img src="<?= $qr_data_uri ?>" alt="QR Code" width="150" height="150">
            <div style="font-size: 0.7rem; text-align: center; margin-top: 5px; color: #64748b;">
                Código QR
            </div>
        </div>

        <div class="print-section">
            <h3>ID Interno</h3>
            <div class="print-value"><?= Helper::generateInternalId($paquete['id']) ?></div>
        </div>

        <div class="print-section">
            <h3>Código Visible</h3>
            <div class="codigo-print"><?= Helper::escape($paquete['codigo_visible']) ?></div>
        </div>

        <div class="clear-both"></div>

        <div class="print-section">
            <h3>Comprador</h3>
            <div class="print-value"><?= Helper::escape($paquete['comprador_nombre'] ?? 'N/A') ?></div>
            <div class="print-value"><?= Helper::escape($paquete['comprador_celular'] ?? '') ?></div>
        </div>

        <div class="print-section">
            <h3>Autorizado para recoger</h3>
            <div class="print-value"><?= Helper::escape($paquete['autorizado_nombre'] ?? 'No especificado') ?></div>
            <?php if ($paquete['autorizado_celular']): ?>
                <div class="print-value"><?= Helper::escape($paquete['autorizado_celular']) ?></div>
            <?php endif; ?>
        </div>

        <div class="print-section">
            <h3>Vendedor / Punto DeUna</h3>
            <div class="print-value"><?= Helper::escape($paquete['vendedor_nombre'] ?? 'N/A') ?></div>
            <div class="print-value"><?= Helper::escape($paquete['vendedor_celular'] ?? '') ?></div>
        </div>

        <div class="print-section">
            <h3>Producto / Descripción</h3>
            <div class="print-value"><?= Helper::escape($paquete['descripcion'] ?? 'Sin descripción') ?></div>
        </div>

        <div class="print-section">
            <h3>Fecha de Recepción</h3>
            <div class="print-value"><?= Helper::formatDate($paquete['fecha_recepcion'], 'd/m/Y H:i') ?></div>
        </div>

        <div class="print-section">
            <h3>Token QR (para acceso seguro)</h3>
            <div class="print-value" style="font-size: 0.85rem; color: #64748b;"><?= Helper::escape($paquete['qr_token']) ?></div>
        </div>

        <div class="clear-both"></div>
    </div>
</body>
</html>
