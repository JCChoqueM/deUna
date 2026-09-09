<?php
/**
 * DeUna - Pago Model
 * Pagos realizados al momento de la entrega
 */

class Pago extends Model
{
    protected string $table = 'pagos';

    /**
     * Get payment for a specific package
     */
    public function getByPaquete(int $paqueteId): ?array
    {
        return $this->where('paquete_id', $paqueteId);
    }

    /**
     * Get total income for a date range
     */
    public function getTotalIncome(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total), 0) FROM {$this->table}
            WHERE date(fecha_hora) >= ? AND date(fecha_hora) <= ?
        ");
        $stmt->execute([$desde, $hasta]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Get total commissions for a date range
     */
    public function getTotalCommissions(string $desde, string $hasta): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(comision_atraso), 0) FROM {$this->table}
            WHERE date(fecha_hora) >= ? AND date(fecha_hora) <= ?
        ");
        $stmt->execute([$desde, $hasta]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Get payments by payment method for a date range
     */
    public function getByMedioPago(string $desde, string $hasta): array
    {
        $stmt = $this->db->prepare("
            SELECT medio_pago, COUNT(*) as cantidad, SUM(total) as total
            FROM {$this->table}
            WHERE date(fecha_hora) >= ? AND date(fecha_hora) <= ?
            GROUP BY medio_pago
            ORDER BY total DESC
        ");
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll();
    }
}
