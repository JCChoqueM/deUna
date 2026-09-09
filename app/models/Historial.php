<?php
/**
 * DeUna - Historial Model
 * Historial de auditoría de cambios de estado
 */

class Historial extends Model
{
    protected string $table = 'historial';

    /**
     * Get history for a specific package
     */
    public function getByPaquete(int $paqueteId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.nombre as usuario_nombre
            FROM {$this->table} h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.paquete_id = ?
            ORDER BY h.fecha_hora ASC
        ");
        $stmt->execute([$paqueteId]);
        return $stmt->fetchAll();
    }

    /**
     * Register a state change
     */
    public function registerChange(int $paqueteId, string $accion, ?string $estadoAnterior, ?string $estadoNuevo, ?int $usuarioId = null, ?string $detalle = null): int
    {
        return $this->create([
            'paquete_id' => $paqueteId,
            'accion' => $accion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'fecha_hora' => date('Y-m-d H:i:s'),
            'usuario_id' => $usuarioId ?? Auth::userId() ?? 0,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Get all audit events for a date range
     */
    public function getByDateRange(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.nombre as usuario_nombre, p.codigo_visible
            FROM {$this->table} h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            LEFT JOIN paquetes p ON p.id = h.paquete_id
            WHERE date(h.fecha_hora) >= ? AND date(h.fecha_hora) <= ?
            ORDER BY h.fecha_hora DESC
        ");
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll();
    }
}
