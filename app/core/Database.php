<?php
/**
 * DeUna - Database Connection
 * Singleton PDO connection to SQLite
 */

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbPath = DB_PATH;
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $dsn = 'sqlite:' . $dbPath;
            self::$instance = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::SQLITE_ATTR_FOREIGN_KEYS => true,
            ]);
        }
        return self::$instance;
    }

    /**
     * Run database migrations if tables don't exist
     */
    public static function migrate(): void
    {
        $pdo = self::getInstance();
        $sql = file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql');
        $pdo->exec($sql);
    }

    /**
     * Seed default data (admin user, configuration)
     */
    public static function seed(): void
    {
        $pdo = self::getInstance();

        // Check if admin user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
        $stmt->execute([DEFAULT_ADMIN_USER]);
        if ($stmt->fetchColumn() == 0) {
            $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre, usuario, password_hash, rol, estado, fecha_creacion)
                VALUES (?, ?, ?, 'admin', 'activo', datetime('now'))
            ");
            $stmt->execute(['Administrador DeUna', DEFAULT_ADMIN_USER, $hash]);
        }

        // Seed default configuration
        $defaults = [
            'tarifa_base' => '10.00',
            'tipo_comision' => 'fijo',
            'monto_comision' => '5.00',
            'porcentaje_comision' => '0.00',
            'plazo_dias' => '7',
            'numeracion_tipo' => 'continua',
            'nombre_negocio' => 'DeUna',
            'direccion_negocio' => '',
            'telefono_negocio' => '',
            'email_negocio' => '',
        ];

        foreach ($defaults as $key => $value) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuracion WHERE clave = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion (clave, valor, fecha_actualizacion, usuario_id)
                    VALUES (?, ?, datetime('now'), 1)
                ");
                $stmt->execute([$key, $value]);
            }
        }
    }
}
