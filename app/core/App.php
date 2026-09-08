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

    public function __construct()
    {
        // Parse the request URL
        $url = $this->parseUrl();

        // Determine controller
        if (isset($url[0]) && !empty($url[0])) {
            $controllerSegment = ucfirst(strtolower($url[0]));
            $controllerFile = dirname(__DIR__) . '/app/controllers/' . $controllerSegment . 'Controller.php';
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
        $isPublic = in_array(str_replace('Controller', '', $this->controllerName), $publicControllers);

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
