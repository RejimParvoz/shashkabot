<?php
/**
 * Shashka Game - Core Application Class
 * High Performance Application Bootstrap for 1M+ Users
 */

class App {
    
    private static $instance = null;
    private $config = [];
    private $container = [];
    private $booted = false;
    
    // Core services
    private $database;
    private $cache;
    private $router;
    private $logger;
    private $rateLimiter;
    private $security;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Private for singleton
     */
    private function __construct() {
        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->boot();
    }
    
    /**
     * Load environment variables
     */
    private function loadEnvironment() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"\'');
                }
            }
        }
    }
    
    /**
     * Load configuration files
     */
    private function loadConfiguration() {
        $configFiles = ['app', 'database', 'cache', 'telegram', 'websocket'];
        foreach ($configFiles as $file) {
            $configPath = __DIR__ . "/../config/{$file}.php";
            if (file_exists($configPath)) {
                $this->config[$file] = require $configPath;
            }
        }
    }
    
    /**
     * Bootstrap application
     */
    private function boot() {
        if ($this->booted) return;
        
        // Initialize core services
        $this->initializeLogger();
        $this->initializeDatabase();
        $this->initializeCache();
        $this->initializeRouter();
        $this->initializeSecurity();
        $this->initializeRateLimiter();
        
        // Register error handlers
        $this->registerErrorHandlers();
        
        // Set application timezone
        date_default_timezone_set($this->config['app']['timezone']);
        
        $this->booted = true;
    }
    
    /**
     * Initialize logger
     */
    private function initializeLogger() {
        require_once __DIR__ . '/Logger.php';
        $this->logger = new Logger($this->config['app']['logging']);
        $this->container['logger'] = $this->logger;
    }
    
    /**
     * Initialize database
     */
    private function initializeDatabase() {
        require_once __DIR__ . '/Database.php';
        $this->database = new Database($this->config['database']);
        $this->container['database'] = $this->database;
        $this->container['db'] = $this->database; // Alias
    }
    
    /**
     * Initialize cache
     */
    private function initializeCache() {
        require_once __DIR__ . '/Cache.php';
        $this->cache = new Cache($this->config['cache']);
        $this->container['cache'] = $this->cache;
    }
    
    /**
     * Initialize router
     */
    private function initializeRouter() {
        require_once __DIR__ . '/Router.php';
        $this->router = new Router();
        $this->container['router'] = $this->router;
        $this->registerRoutes();
    }
    
    /**
     * Initialize security
     */
    private function initializeSecurity() {
        require_once __DIR__ . '/Security.php';
        $this->security = new Security($this->config['app']);
        $this->container['security'] = $this->security;
    }
    
    /**
     * Initialize rate limiter
     */
    private function initializeRateLimiter() {
        require_once __DIR__ . '/RateLimiter.php';
        $this->rateLimiter = new RateLimiter($this->cache, $this->config['app']['rate_limit']);
        $this->container['rate_limiter'] = $this->rateLimiter;
    }
    
    /**
     * Register application routes
     */
    private function registerRoutes() {
        // API Routes
        $this->router->group('/api', function($router) {
            
            // Authentication routes
            $router->post('/auth/telegram', 'Api\\AuthController@telegram');
            $router->post('/auth/refresh', 'Api\\AuthController@refresh');
            $router->post('/auth/logout', 'Api\\AuthController@logout');
            
            // Game routes
            $router->get('/game/modes', 'Api\\GameController@modes');
            $router->post('/game/create', 'Api\\GameController@create');
            $router->post('/game/join', 'Api\\GameController@join');
            $router->post('/game/move', 'Api\\GameController@move');
            $router->get('/game/{id}', 'Api\\GameController@show');
            $router->post('/game/{id}/resign', 'Api\\GameController@resign');
            $router->post('/game/{id}/draw', 'Api\\GameController@offerDraw');
            
            // User routes  
            $router->get('/user/profile', 'Api\\UserController@profile');
            $router->put('/user/profile', 'Api\\UserController@updateProfile');
            $router->get('/user/stats', 'Api\\UserController@stats');
            $router->get('/user/games', 'Api\\UserController@games');
            $router->get('/user/inventory', 'Api\\UserController@inventory');
            
            // Leaderboard routes
            $router->get('/leaderboard', 'Api\\LeaderboardController@index');
            $router->get('/leaderboard/{league}', 'Api\\LeaderboardController@league');
            
            // Shop routes
            $router->get('/shop/items', 'Api\\ShopController@items');
            $router->get('/shop/categories', 'Api\\ShopController@categories');
            $router->post('/shop/purchase', 'Api\\ShopController@purchase');
            $router->post('/shop/equip', 'Api\\ShopController@equip');
            
            // Tournament routes
            $router->get('/tournaments', 'Api\\TournamentController@index');
            $router->get('/tournaments/{id}', 'Api\\TournamentController@show');
            $router->post('/tournaments/{id}/join', 'Api\\TournamentController@join');
            
            // Arena routes
            $router->get('/arena', 'Api\\ArenaController@index');
            $router->get('/arena/rankings', 'Api\\ArenaController@rankings');
            
            // Clan routes
            $router->get('/clans', 'Api\\ClanController@index');
            $router->post('/clans', 'Api\\ClanController@create');
            $router->get('/clans/{id}', 'Api\\ClanController@show');
            $router->post('/clans/{id}/join', 'Api\\ClanController@join');
            $router->post('/clans/{id}/leave', 'Api\\ClanController@leave');
            
            // Social routes
            $router->get('/friends', 'Api\\SocialController@friends');
            $router->post('/friends/add', 'Api\\SocialController@addFriend');
            $router->delete('/friends/{id}', 'Api\\SocialController@removeFriend');
            
            // Payment routes
            $router->get('/payments/packs', 'Api\\PaymentController@diamondPacks');
            $router->post('/payments/purchase', 'Api\\PaymentController@purchase');
            $router->post('/payments/webhook', 'Api\\PaymentController@webhook');
            
            // VIP routes
            $router->get('/vip/plans', 'Api\\VipController@plans');
            $router->post('/vip/subscribe', 'Api\\VipController@subscribe');
            
            // Daily system routes
            $router->get('/daily/bonus', 'Api\\DailyController@bonus');
            $router->post('/daily/claim', 'Api\\DailyController@claimBonus');
            $router->get('/daily/quests', 'Api\\DailyController@quests');
            $router->post('/daily/complete', 'Api\\DailyController@completeQuest');
            
            // Telegram webhook
            $router->post('/telegram/webhook', 'Api\\TelegramController@webhook');

            // Health check (routing + bootstrap diagnostic)
            $router->get('/health', 'PageController@health');
        });
        
        // Admin Routes
        $this->router->group('/admin', function($router) {
            $router->get('/', 'Admin\\DashboardController@index');
            $router->get('/users', 'Admin\\UserController@index');
            $router->get('/games', 'Admin\\GameController@index');
            $router->get('/tournaments', 'Admin\\TournamentController@index');
            $router->get('/shop', 'Admin\\ShopController@index');
            $router->get('/reports', 'Admin\\ReportController@index');
        });
        
        // Public Routes
        $this->router->get('/', 'PageController@index');
        $this->router->get('/game/{id}', 'PageController@game');
        $this->router->get('/tournament/{id}', 'PageController@tournament');
        $this->router->get('/profile/{id}', 'PageController@profile');
    }
    
    /**
     * Register error handlers
     */
    private function registerErrorHandlers() {
        // PHP error handler
        set_error_handler(function($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) return false;
            
            $this->logger->error("PHP Error: $message in $file:$line");
            
            if ($severity === E_ERROR || $severity === E_PARSE) {
                $this->handleFatalError();
            }
            
            return true;
        });
        
        // Exception handler
        set_exception_handler(function($exception) {
            $this->logger->error("Uncaught Exception: " . $exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            $this->handleFatalError();
        });
        
        // Shutdown handler for fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                $this->logger->error("Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}");
                $this->handleFatalError();
            }
        });
    }
    
    /**
     * Handle fatal errors
     */
    private function handleFatalError() {
        if (!headers_sent()) {
            if ($this->isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Ichki server xatosi',
                    'code' => 500
                ]);
            } else {
                header('Location: /error.html');
            }
        }
        exit(1);
    }
    
    /**
     * Check if current request is API request
     */
    private function isApiRequest() {
        return strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    }
    
    /**
     * Run the application
     */
    public function run() {
        try {
            // Apply rate limiting
            if (!$this->rateLimiter->attempt()) {
                $this->sendResponse(['error' => 'Too many requests'], 429);
                return;
            }
            
            // Apply security checks
            if (!$this->security->validateRequest()) {
                $this->sendResponse(['error' => 'Invalid request'], 400);
                return;
            }
            
            // Route the request
            $this->router->dispatch();
            
        } catch (Exception $e) {
            $this->logger->error('Application Error: ' . $e->getMessage());
            
            if ($this->isApiRequest()) {
                $this->sendResponse(['error' => 'Server error'], 500);
            } else {
                header('Location: /error.html');
            }
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    /**
     * Get service from container
     */
    public function get($service) {
        return isset($this->container[$service]) ? $this->container[$service] : null;
    }
    
    /**
     * Set service in container
     */
    public function set($service, $instance) {
        $this->container[$service] = $instance;
    }
    
    /**
     * Get configuration
     */
    public function config($key = null, $default = null) {
        if ($key === null) return $this->config;
        
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get database instance
     */
    public function db() {
        return $this->database;
    }
    
    /**
     * Get cache instance
     */
    public function cache() {
        return $this->cache;
    }
    
    /**
     * Get logger instance
     */
    public function logger() {
        return $this->logger;
    }
    
    /**
     * Get current user (from session/JWT)
     */
    public function user() {
        // Implementation will be in Security class
        return $this->security->getCurrentUser();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return $this->security->isAuthenticated();
    }
    
    /**
     * Get request data
     */
    public function request() {
        $method = $_SERVER['REQUEST_METHOD'];
        $data = [];
        
        if ($method === 'GET') {
            $data = $_GET;
        } else if ($method === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $json = file_get_contents('php://input');
                $data = json_decode($json, true) ?? [];
            } else {
                $data = $_POST;
            }
        }
        
        // Sanitize input
        return $this->security->sanitizeInput($data);
    }
    
    /**
     * Generate CSRF token
     */
    public function csrfToken() {
        return $this->security->generateCsrfToken();
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrf($token) {
        return $this->security->validateCsrfToken($token);
    }
}