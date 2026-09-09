<?php
/**
 * DeUna - Usuario Model
 * Usuarios del sistema (Administrador y Operador)
 */

class Usuario extends Model
{
    protected string $table = 'usuarios';

    /**
     * Get user by username
     */
    public function getByUsuario(string $usuario): ?array
    {
        return $this->where('usuario', $usuario);
    }

    /**
     * Get all active users
     */
    public function getActive(): array
    {
        return $this->query("SELECT * FROM {$this->table} WHERE estado = 'activo' ORDER BY id DESC");
    }

    /**
     * Create a new user with hashed password
     */
    public function create(array $data): int
    {
        $data['password_hash'] = password_hash($data['password_hash'], PASSWORD_DEFAULT);
        $data['fecha_creacion'] = date('Y-m-d H:i:s');
        $data['estado'] = 'activo';

        // Remove the plain password field if it exists under a different key
        unset($data['password']);

        return parent::create($data);
    }

    /**
     * Update user (handle password hashing)
     */
    public function update(int $id, array $data): bool
    {
        if (isset($data['password_hash']) && $data['password_hash']) {
            $data['password_hash'] = password_hash($data['password_hash'], PASSWORD_DEFAULT);
        } else {
            unset($data['password_hash']);
        }
        return parent::update($id, $data);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(int $id): bool
    {
        $user = $this->find($id);
        return $user && $user['rol'] === 'admin';
    }
}
