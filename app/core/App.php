<?php
/**
 * DeUna - Router / Front Controller Core
 * Handles URL routing and dispatches to controllers
 */

class App
{
    protected array $routes = [];
    protected string $controllerName = 'DashboardController';
    protected string $methodName = 'index';
    protected array $params = [];

    /**
     * Special route aliases (regex => [Controller, Method, ParamOffset])
     * ParamOffset: index in split URL array where param begins
     */
    protected array $routeMap = [
        // /login → AuthController@login
        '#^login$#' => ['Auth', 'login', 0],
        // /logout → AuthController@logout
        '#^logout$#' => ['Auth', 'logout', 0],
        // /register → AuthController@register
        '#^register$#' => ['Auth', 'register', 0],
        // /qr-code/{token}
        '#^qr-code/(.+)$#' => ['Paquete', 'qrCode', 1],
        // /entrega/{token}
        '#^entrega/(.+)$#' => ['Paquete', 'entregaPublica', 1],
        // /api/paquete
        '#^api/paquete$#' => ['Paquete', 'apiBuscar', 0],
        // /api/buscar
        '#^api/buscar$#' => ['Paquete', 'apiBuscarLista', 0],
    ];

    /**
     * Controller name aliases (URL segment → controller class prefix)
     */
    protected array $controllerAliases = [
        'paquetes' => 'Paquete',
        'usuarios' => 'Usuario',
        'reportes' => 'Reporte',
        'login' => 'Auth',
        'logout' => 'Auth',
        'register' => 'Auth',
    ];

    public function __construct()
    {
        // Parse the request URL
        $url = $this->parseUrl();

        // Check for special routes
        $path = implode('/', array_filter($url));
        foreach ($this->routeMap as $pattern => $route) {
            if (preg_match($pattern, $path, $matches)) {
                $this->controllerName = $route[0] . 'Controller';
                $this->methodName = $route[1];
                $this->params = $route[2] > 0 ? [$matches[$route[2]]] : [];
                $this->dispatch();
                return;
            }
        }

        // Determine controller
        if (isset($url[0]) && !empty($url[0])) {
            $controllerSegment = ucfirst(strtolower($url[0]));
            // Check for alias (e.g., paquetes → Paquete)
            $lowerSegment = strtolower($url[0]);
            if (isset($this->controllerAliases[$lowerSegment])) {
                $controllerSegment = $this->controllerAliases[$lowerSegment];
            }
            $controllerFile = dirname(__DIR__) . '/controllers/' . $controllerSegment . 'Controller.php';
            if (file_exists($controllerFile)) {
                $this->controllerName = $controllerSegment . 'Controller';
            }
        }

        // Remove controller segment from URL
        if (isset($url[0])) {
            array_shift($url);
        }

        // Determine method
        if (isset($url[0]) && !empty($url[0])) {
            $this->methodName = $url[0];
            array_shift($url);
        }

        // Ensure method exists on controller, otherwise default to index
        if (!method_exists($this->controllerName, $this->methodName)) {
            $this->methodName = 'index';
        }

        $this->params = $url;

        // Dispatch
        $this->dispatch();
    }

    /**
     * Parse the request URL
     */
    protected function parseUrl(): array
    {
        $path = $_GET['url'] ?? '';
        $path = rtrim($path, '/');
        $path = filter_var($path, FILTER_SANITIZE_URL);
        return explode('/', $path);
    }

    /**
     * Dispatch to controller method
     */
    protected function dispatch(): void
    {
        // Initialize migration
        $this->ensureDatabase();

        // Check authentication for non-auth controllers
        $publicControllers = ['Auth'];
        $publicActions = ['PaqueteController@qrCode', 'PaqueteController@entregaPublica', 'PaqueteController@apiBuscar', 'PaqueteController@apiBuscarLista'];
        $isPublic = in_array(str_replace('Controller', '', $this->controllerName), $publicControllers)
            || in_array($this->controllerName . '@' . $this->methodName, $publicActions);

        if (!$isPublic && !Auth::canAccess()) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }

        // Instantiate controller and call method
        $controller = new $this->controllerName();

        if (empty($this->params)) {
            $controller->{$this->methodName}();
        } else {
            call_user_func_array([$controller, $this->methodName], $this->params);
        }
    }

    /**
     * Ensure database exists and is migrated
     */
    protected function ensureDatabase(): void
    {
        Database::getInstance();
        Database::migrate();
        Database::seed();
    }
}
