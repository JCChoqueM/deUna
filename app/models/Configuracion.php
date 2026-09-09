<?php
/**
 * DeUna - Configuracion Model
 * Configuración del sistema: tarifas, comisiones, plazos, numeración
 */

class Configuracion extends Model
{
    protected string $table = 'configuracion';

    /**
     * Get a configuration value by key
     */
    public function getValue(string $clave, $default = null)
    {
        $stmt = $this->db->prepare("SELECT valor FROM {$this->table} WHERE clave = ? LIMIT 1");
        $stmt->execute([$clave]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : $default;
    }

    /**
     * Set a configuration value
     */
    public function setValue(string $clave, string $valor, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::userId() ?? 1;
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (clave, valor, fecha_actualizacion, usuario_id)
            VALUES (?, ?, datetime('now'), ?)
            ON CONFLICT(clave) DO UPDATE SET 
                valor = excluded.valor,
                fecha_actualizacion = datetime('now'),
                usuario_id = excluded.usuario_id
        ");
        return $stmt->execute([$clave, $valor, $userId]);
    }

    /**
     * Get all configuration values
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY clave ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get configuration as associative array
     */
    public function getConfig(): array
    {
        $all = $this->getAll();
        $config = [];
        foreach ($all as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        return $config;
    }
}
