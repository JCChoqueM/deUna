<?php
/**
 * DeUna - Vendedor Model
 * Vendedores que dejan paquetes en puntos DeUna
 */

class Vendedor extends Model
{
    protected string $table = 'vendedores';

    /**
     * Get all vendors ordered by name
     */
    public function allOrdered(): array
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY nombre ASC");
    }

    /**
     * Find or create vendor
     */
    public function findOrCreate(array $data): int
    {
        // Try to find by celular
        if (!empty($data['celular'])) {
            $existing = $this->getByCelular($data['celular']);
            if ($existing) return (int)$existing['id'];
        }

        $data['fecha_registro'] = date('Y-m-d H:i:s');
        return $this->create($data);
    }

    /**
     * Get vendor by cell phone
     */
    public function getByCelular(string $celular): ?array
    {
        return $this->where('celular', $celular);
    }

    /**
     * Search vendors by name or phone
     */
    public function search(string $term): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE nombre LIKE ? OR celular LIKE ? ORDER BY nombre ASC"
        );
        $term = '%' . $term . '%';
        $stmt->execute([$term, $term]);
        return $stmt->fetchAll();
    }

    /**
     * Get vendor with package count
     */
    public function getWithStats(): array
    {
        return $this->query("
            SELECT v.*, 
                   COUNT(p.id) as total_paquetes,
                   SUM(CASE WHEN p.estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                   SUM(CASE WHEN p.estado = 'Entregado' THEN 1 ELSE 0 END) as entregados
            FROM {$this->table} v
            LEFT JOIN paquetes p ON p.vendedor_id = v.id
            GROUP BY v.id
            ORDER BY v.nombre ASC
        ");
    }
}
