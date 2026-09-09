<?php
/**
 * DeUna - Comprador Model
 * Compradores que recogen paquetes en puntos DeUna
 */

class Comprador extends Model
{
    protected string $table = 'compradores';

    /**
     * Get initial for code generation
     * Example: "Maria Caceres" -> "M"
     */
    public function getInicial(string $nombre): string
    {
        $nombre = trim($nombre);
        // Get first letter of the first word
        $words = explode(' ', $nombre);
        $firstChar = mb_substr($words[0], 0, 1, 'UTF-8');
        return mb_strtoupper($firstChar, 'UTF-8');
    }

    /**
     * Search buyers by name
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
     * Get all buyers ordered by name
     */
    public function allOrdered(): array
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY nombre_completo ASC");
    }

    /**
     * Find or create buyer by name and phone
     */
    public function findOrCreate(string $nombre, ?string $celular = null, string $obs = ''): int
    {
        if ($celular) {
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE nombre_completo = ? AND celular = ?"
            );
            $stmt->execute([$nombre, $celular]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id FROM {$this->table} WHERE nombre_completo = ?"
            );
            $stmt->execute([$nombre]);
        }
        $existing = $stmt->fetch();
        if ($existing) {
            return (int)$existing['id'];
        }

        return $this->create([
            'nombre_completo' => $nombre,
            'celular' => $celular ?? '',
            'observaciones' => $obs,
        ]);
    }
}
