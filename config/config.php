<?php
/**
 * DeUna - Configuration
 * Sistema de recepción, custodia y entrega de paquetes
 */

define('APP_NAME', 'DeUna');
define('APP_VERSION', '1.0.0');

// Database configuration (SQLite for self-contained operation)
define('DB_PATH', dirname(__DIR__) . '/database/deuna.db');

// Application settings
define('BASE_URL', '/');
define('UPLOAD_PATH', dirname(__DIR__) . '/public/uploads/');

// Session configuration
define('SESSION_NAME', 'deuna_session');
define('SESSION_TIMEOUT', 7200); // 2 hours

// Default admin credentials (for initial setup)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

// Pagination
define('ITEMS_PER_PAGE', 20);
