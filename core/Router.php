<?php
/**
 * Shashka Game - Router Class
 * High Performance HTTP Router with Middleware Support
 */

class Router {
    
    private $routes = [];
    private $middleware = [];
    private $currentGroup = null;
    private $namedRoutes = [];
    
    /**
     * Add GET route
     */
    public function get($path, $handler) {
        return $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Add POST route
     */
    public function post($path, $handler) {
        return $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Add PUT route
     */
    public function put($path, $handler) {
        return $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Add DELETE route
     */
    public function delete($path, $handler) {
        return $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Add PATCH route
     */
    public function patch($path, $handler) {
        return $this->addRoute('PATCH', $path, $handler);
    }
    
    /**
     * Add route for any method
     */
    public function any($path, $handler) {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }
    
    /**
     * Add route group with shared attributes
     */
    public function group($prefix, $callback) {
        $previousGroup = $this->currentGroup;
        
        $this->currentGroup = [
            'prefix' => ltrim($prefix, '/'),
            'middleware' => []
        ];
        
        $callback($this);
        
        $this->currentGroup = $previousGroup;
        
        return $this;
    }
    
    /**
     * Add middleware to route group
     */
    public function middleware($middleware) {
        if ($this->currentGroup) {
            $this->currentGroup['middleware'] = array_merge(
                $this->currentGroup['middleware'] ?? [],
                is_array($middleware) ? $middleware : [$middleware]
            );
        }
        return $this;
    }
    
    /**
     * Add route with method
     */
    private function addRoute($method, $path, $handler) {
        // Apply group prefix
        if ($this->currentGroup && !empty($this->currentGroup['prefix'])) {
            $path = '/' . trim($this->currentGroup['prefix'], '/') . '/' . ltrim($path, '/');
        }
        
        // Normalize path
        $path = '/' . trim($path, '/');
        if ($path === '/') $path = '/';
        
        // Convert route parameters to regex
        $pattern = $this->convertToRegex($path);
        
        $route = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $this->currentGroup['middleware'] ?? [],
            'parameters' => $this->extractParameters($path)
        ];
        
        $this->routes[] = $route;
        
        return $this;
    }
    
    /**
     * Convert route path to regex pattern.
     * Uses '#' as the delimiter so that '/' inside [^/]+ does not clash.
     */
    private function convertToRegex($path) {
        // Protect {param} placeholders before escaping
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '___PARAM_$1___', $path);

        // Escape regex special characters (delimiter '#', so '/' is left intact)
        $pattern = preg_quote($pattern, '#');

        // Restore placeholders as named capture groups
        $pattern = preg_replace('#___PARAM_([a-zA-Z0-9_]+)___#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }
    
    /**
     * Extract parameter names from route path
     */
    private function extractParameters($path) {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1];
    }
    
    /**
     * Dispatch route
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getCurrentUri();
        
        // Find matching route
        $matchedRoute = $this->findRoute($method, $uri);
        
        if (!$matchedRoute) {
            $this->handleNotFound();
            return;
        }
        
        // Execute middleware
        $this->executeMiddleware($matchedRoute['middleware']);
        
        // Execute route handler
        $this->executeHandler($matchedRoute);
    }
    
    /**
     * Get current URI (subdirectory-aware).
     *
     * Works in three setups:
     *   1. Apache .htaccess passing ?route=...  (preferred)
     *   2. Nginx try_files passing ?route=...
     *   3. Plain REQUEST_URI under a subdirectory (e.g. /shashka/api/...)
     */
    private function getCurrentUri() {
        // 1 & 2. Front controller passed the relative route explicitly
        if (isset($_GET['route'])) {
            $route = '/' . ltrim($_GET['route'], '/');
            return $route === '' ? '/' : $route;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // 3. Strip the base path (subdirectory) derived from the script location.
        // e.g. SCRIPT_NAME = /shashka/public/index.php  ->  base = /shashka
        $basePath = $this->getBasePath();
        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . ltrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    /**
     * Determine the application base path (subdirectory) from SCRIPT_NAME.
     * Removes a trailing "/public" so routes are matched without it.
     */
    private function getBasePath() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = str_replace('\\', '/', dirname($scriptName));

        // Strip trailing /public (entry point lives in public/)
        if (substr($base, -7) === '/public') {
            $base = substr($base, 0, -7);
        }

        $base = rtrim($base, '/');
        // Root install -> empty base
        return ($base === '' || $base === '.') ? '' : $base;
    }
    
    /**
     * Find matching route
     */
    private function findRoute($method, $uri) {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $parameters = [];
                foreach ($route['parameters'] as $param) {
                    if (isset($matches[$param])) {
                        $parameters[$param] = $matches[$param];
                    }
                }
                
                $route['matched_parameters'] = $parameters;
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Execute middleware stack
     */
    private function executeMiddleware($middleware) {
        foreach ($middleware as $middlewareName) {
            if (isset($this->middleware[$middlewareName])) {
                $middlewareHandler = $this->middleware[$middlewareName];
                
                if (is_callable($middlewareHandler)) {
                    $result = $middlewareHandler();
                    
                    // If middleware returns false, stop execution
                    if ($result === false) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Execute route handler
     */
    private function executeHandler($route) {
        $handler = $route['handler'];
        
        // Set route parameters as globals for easy access
        if (!empty($route['matched_parameters'])) {
            $_GET = array_merge($_GET, $route['matched_parameters']);
        }
        
        if (is_string($handler)) {
            // Handle Controller@method format
            if (strpos($handler, '@') !== false) {
                list($controller, $method) = explode('@', $handler);
                $this->executeControllerMethod($controller, $method, $route['matched_parameters']);
            } else {
                // Handle function name
                if (function_exists($handler)) {
                    $handler($route['matched_parameters']);
                }
            }
        } elseif (is_callable($handler)) {
            // Handle closure
            $handler($route['matched_parameters']);
        }
    }
    
    /**
     * Execute controller method
     */
    private function executeControllerMethod($controllerName, $method, $parameters) {
        // Convert namespace format (Api\\AuthController -> Api/AuthController)
        $controllerPath = str_replace('\\\\', '/', $controllerName);
        $controllerFile = __DIR__ . "/../controllers/{$controllerPath}.php";
        
        if (!file_exists($controllerFile)) {
            $this->handleError("Controller file not found: $controllerFile");
            return;
        }
        
        require_once $controllerFile;
        
        // Get class name
        $className = basename($controllerName);
        
        if (!class_exists($className)) {
            $this->handleError("Controller class not found: $className");
            return;
        }
        
        $controller = new $className();
        
        if (!method_exists($controller, $method)) {
            $this->handleError("Method '$method' not found in controller '$className'");
            return;
        }
        
        // Execute controller method
        $controller->$method($parameters);
    }
    
    /**
     * Register middleware
     */
    public function registerMiddleware($name, $handler) {
        $this->middleware[$name] = $handler;
    }
    
    /**
     * Handle 404 Not Found
     */
    private function handleNotFound() {
        http_response_code(404);
        
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Route not found',
                'code' => 404
            ]);
        } else {
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
    
    /**
     * Handle router errors
     */
    private function handleError($message) {
        error_log("Router Error: $message");
        
        http_response_code(500);
        
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ]);
        } else {
            echo '<h1>500 - Internal Server Error</h1>';
        }
    }
    
    /**
     * Check if request is API request
     */
    private function isApiRequest() {
        $uri = $this->getCurrentUri();
        return strpos($uri, '/api/') === 0;
    }
    
    /**
     * Generate URL for named route
     */
    public function url($name, $parameters = []) {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Named route '$name' not found");
        }
        
        $route = $this->namedRoutes[$name];
        $path = $route['path'];
        
        // Replace parameters
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        
        return $path;
    }
    
    /**
     * Name a route
     */
    public function name($name) {
        if (!empty($this->routes)) {
            $lastRoute = end($this->routes);
            $this->namedRoutes[$name] = $lastRoute;
        }
        
        return $this;
    }
    
    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes() {
        return $this->routes;
    }
    
    /**
     * Clear all routes
     */
    public function clear() {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->middleware = [];
    }
}