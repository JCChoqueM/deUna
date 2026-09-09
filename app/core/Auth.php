<?php
/**
 * DeUna - Authentication & Authorization
 */

class Auth
{
    /**
     * Attempt to log in a user
     */
    public static function login(string $usuario, string $password): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            Session::set('user_id', $user['id']);
            Session::set('user_name', $user['nombre']);
            Session::set('user_rol', $user['rol']);
            Session::set('logged_in', true);
            return true;
        }
        return false;
    }

    /**
     * Log out the current user
     */
    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function isAuthenticated(): bool
    {
        return Session::get('logged_in', false) === true;
    }

    /**
     * Get current user ID
     */
    public static function userId(): ?int
    {
        return Session::get('user_id');
    }

    /**
     * Get current user role
     */
    public static function userRole(): ?string
    {
        return Session::get('user_rol');
    }

    /**
     * Check if current user has admin role
     */
    public static function isAdmin(): bool
    {
        return self::userRole() === 'admin';
    }

    /**
     * Check if current user is admin or operator (authenticated)
     */
    public static function canAccess(): bool
    {
        return self::isAuthenticated();
    }

    /**
     * Get current user's display name
     */
    public static function getUserName(): string
    {
        return Session::get('user_name', 'Usuario');
    }
}
