<?php
/**
 * DeUna - Front Controller
 * Punto de entrada principal de la aplicación
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';

// Load core classes
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Session.php';
require_once dirname(__DIR__) . '/app/core/Helper.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/core/Controller.php';
require_once dirname(__DIR__) . '/app/core/QRGen.php';
require_once dirname(__DIR__) . '/app/core/App.php';
require_once dirname(__DIR__) . '/app/core/Autoloader.php';

// Start session (with proper session name)
Session::start();

// Initialize database
Database::getInstance();

// Dispatch the request
$app = new App();
