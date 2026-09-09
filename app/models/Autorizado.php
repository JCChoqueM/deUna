<?php
/**
 * DeUna - Autorizado Model
 * Personas autorizadas para recoger paquetes
 */

class Autorizado extends Model
{
    protected string $table = 'autorizados';

    /**
     * Get all authorized persons for a buyer
     */
    public function getByComprador(int $compradorId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE comprador_id = ? ORDER BY id DESC");
        $stmt->execute([$compradorId]);
        return $stmt->fetchAll();
    }

    /**
     * Search authorized persons by name
     */
    public function search(string $term): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE nombre_completo LIKE ? ORDER BY nombre_completo ASC"
        );
        $stmt->execute(['%' . $term . '%']);
        return $stmt->fetchAll();
    }

    /**
     * Find or create authorized person
     */
    public function findOrCreate(string $nombre, ?string $celular = null, int $compradorId = 0, string $relacion = 'Comprador'): int
    {
        if ($celular) {
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE nombre_completo = ? AND celular = ? AND comprador_id = ?"
            );
            $stmt->execute([$nombre, $celular, $compradorId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE nombre_completo = ? AND comprador_id = ?"
            );
            $stmt->execute([$nombre, $compradorId]);
        }
        $existing = $stmt->fetch();
        if ($existing) {
            return (int)$existing['id'];
        }

        return $this->create([
            'nombre_completo' => $nombre,
            'celular' => $celular ?? '',
            'relacion_con_comprador' => $relacion,
            'comprador_id' => $compradorId,
        ]);
    }
}
