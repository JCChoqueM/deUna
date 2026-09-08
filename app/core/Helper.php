<?php
/**
 * DeUna - Helper Functions
 * Utility functions for the application
 */

class Helper
{
    /**
     * Get configuration value
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['valor'] : $default;
    }

    /**
     * Set configuration value
     */
    public static function setConfig(string $key, string $value, ?int $userId = null): bool
    {
        $db = Database::getInstance();
        $userId = $userId ?? Auth::userId() ?? 1;
        $stmt = $db->prepare("
            INSERT INTO configuracion (clave, valor, fecha_actualizacion, usuario_id)
            VALUES (?, ?, datetime('now'), ?)
            ON CONFLICT(clave) DO UPDATE SET valor = ?, fecha_actualizacion = datetime('now'), usuario_id = ?
        ");
        return $stmt->execute([$key, $value, $userId, $value, $userId]);
    }

    /**
     * Generate a secure random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate visible code: Initial of buyer's name + sequence number
     * Example: "Maria Caceres" + 8 = "M-8"
     */
    public static function generateVisibleCode(string $buyerName, int $sequence): string
    {
        // Normalize: remove spaces, take first letter, uppercase
        $name = trim($buyerName);
        $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        return $initial . '-' . $sequence;
    }

    /**
     * Generate internal package ID
     * Format: PK-YYYY-NNNNNN
     */
    public static function generateInternalId(int $id): string
    {
        $year = date('Y');
        return 'PK-' . $year . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate expiration date (reception + plazo_dias)
     */
    public static function calculateExpiration(string $fechaRecepcion, ?int $plazoDias = null): string
    {
        $plazoDias = $plazoDias ?? (int)(self::config('plazo_dias', 7));
        $dt = new DateTime($fechaRecepcion);
        $dt->modify("+{$plazoDias} days");
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Calculate days elapsed since a date
     */
    public static function calculateDaysElapsed(string $fechaRecepcion): int
    {
        $dt = new DateTime($fechaRecepcion);
        $now = new DateTime();
        $diff = $now->diff($dt);
        return (int)$diff->format('%a');
    }

    /**
     * Check if package is overdue (beyond 7 days)
     */
    public static function isOverdue(string $fechaRecepcion): bool
    {
        $plazoHoras = (int)(self::config('plazo_dias', 7)) * 24;
        $dt = new DateTime($fechaRecepcion);
        $now = new DateTime();
        $diff = $now->diff($dt);
        $totalHours = ($diff->days * 24) + $diff->h;
        return $totalHours > $plazoHoras;
    }

    /**
     * Calculate late commission
     */
    public static function calculateLateCommission(float $montoBase, bool $overdue): float
    {
        if (!$overdue) {
            return 0.0;
        }
        $tipo = self::config('tipo_comision', 'fijo');
        if ($tipo === 'fijo') {
            return (float)self::config('monto_comision', 0);
        } else {
            $base = self::config('tarifa_base', 0);
            return $montoBase * ((float)self::config('porcentaje_comision', 0) / 100);
        }
    }

    /**
     * Format currency
     */
    public static function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }

    /**
     * Format date
     */
    public static function formatDate(string $date, string $format = 'd/m/Y H:i'): string
    {
        $dt = new DateTime($date);
        return $dt->format($format);
    }

    /**
     * Get elapsed time as string
     */
    public static function daysElapsedString(string $fechaRecepcion): string
    {
        $days = self::calculateDaysElapsed($fechaRecepcion);
        if ($days === 0) {
            $dt = new DateTime($fechaRecepcion);
            $now = new DateTime();
            $hours = $now->diff($dt)->h;
            return "Hoy ({$hours}h)";
        }
        return "{$days} día(s)";
    }

    /**
     * CSRF Token generation
     */
    public static function csrfToken(): string
    {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', self::generateToken(32));
        }
        return Session::get('csrf_token');
    }

    /**
     * CSRF Token validation
     */
    public static function validateCsrf(string $token): bool
    {
        return hash_equals(Session::get('csrf_token', ''), $token);
    }

    /**
     * Escape output
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate QR URL for a package token
     */
    public static function getPackageUrl(string $qrToken): string
    {
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/entrega/' . $qrToken;
    }
}
