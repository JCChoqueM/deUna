<?php
/**
 * DeUna - Session Management
 */

class Session
{
    /**
     * Start session if not already started
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            // Ensure writable session save path
            $savePath = session_save_path();
            if (empty($savePath) || !is_writable($savePath)) {
                $savePath = sys_get_temp_dir();
            }
            session_save_path($savePath);
            ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
            session_start();
        }
    }

    /**
     * Set a session value
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a session value
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        session_write_close();
        // Clear session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
    }

    /**
     * Set flash message
     */
    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$key] = ['type' => $key, 'message' => $message];
    }

    /**
     * Get and clear flash message
     */
    public static function getFlash(string $key): ?string
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $msg = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $msg;
        }
        return null;
    }

    /**
     * Get all flash messages and clear them
     */
    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }
}
