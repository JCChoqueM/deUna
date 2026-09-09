<?php
/**
 * DeUna - Base Controller
 */

class Controller
{
    /**
     * Base controller constructor
     */
    public function __construct()
    {
        // Ensure database is available
        Database::getInstance();
    }
    /**
     * Load a model
     */
    protected function model(string $name): object
    {
        $modelFile = dirname(__DIR__) . '/models/' . $name . '.php';
        if (file_exists($modelFile)) {
            require_once $modelFile;
        }
        return new $name();
    }

    /**
     * Render a view
     */
    protected function view(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new Exception("View not found: {$view}");
        }

        // Extract variables for the view
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();
        require_once $viewFile;
        $content = ob_get_clean();

        // Include header and footer
        require_once dirname(__DIR__) . '/views/templates/header.php';
        echo $content;
        require_once dirname(__DIR__) . '/views/templates/footer.php';
    }

    /**
     * Render a partial view (without header/footer)
     */
    protected function partial(string $view, array $data = []): string
    {
        $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new Exception("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require_once $viewFile;
        return ob_get_clean();
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url): void
    {
        $baseUrl = rtrim(BASE_URL, '/') . '/';
        if (str_starts_with($url, 'http')) {
            header('Location: ' . $url);
        } else {
            header('Location: ' . $baseUrl . ltrim($url, '/'));
        }
        exit;
    }

    /**
     * Redirect back with flash message
     */
    protected function redirectBack(string $type, string $message): void
    {
        Session::flash($type, $message);
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? BASE_URL);
        exit;
    }

    /**
     * JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }
}
