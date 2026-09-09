<?php
/**
 * DeUna - Autoloader
 * Carga automáticamente las clases del framework MVC
 */

spl_autoload_register(function ($class) {
    $root = dirname(__DIR__, 2); // Project root

    // Core classes
    $corePaths = [
        $root . '/app/core/' . $class . '.php',
        $root . '/config/' . $class . '.php',
    ];

    foreach ($corePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }

    // Models
    $modelPath = $root . '/app/models/' . $class . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
        return;
    }

    // Controllers
    $controllerPath = $root . '/app/controllers/' . $class . '.php';
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        return;
    }
});
