<?php
/**
 * Shashka Game - Base Controller Class
 * High Performance Controller Layer for 1M+ Users
 */

abstract class Controller {
    
    protected $app;
    protected $db;
    protected $cache;
    protected $security;
    protected $logger;
    
    // Request data
    protected $request;
    protected $user;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->app = App::getInstance();
        $this->db = $this->app->db();
        $this->cache = $this->app->cache();
        $this->security = $this->app->get('security');
        $this->logger = $this->app->logger();
        
        // Get request data
        $this->request = $this->getRequestData();
        
        // Get current user if authenticated
        $this->user = $this->security->getCurrentUser();
    }
    
    /**
     * Get request data based on method
     */
    protected function getRequestData() {
        $method = $_SERVER['REQUEST_METHOD'];
        $data = [];
        
        if ($method === 'GET') {
            $data = $_GET;
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'])) {
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
     * Send JSON response
     */
    protected function jsonResponse($data, $statusCode = 200, $headers = []) {
        http_response_code($statusCode);
        
        // Set default headers
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff'
        ];
        
        foreach (array_merge($defaultHeaders, $headers) as $header => $value) {
            header("$header: $value");
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Send success response
     */
    protected function success($data = null, $message = null, $statusCode = 200) {
        $response = ['success' => true];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->jsonResponse($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    protected function error($message, $statusCode = 400, $code = null) {
        $response = [
            'success' => false,
            'error' => $message
        ];
        
        if ($code !== null) {
            $response['code'] = $code;
        }
        
        $this->jsonResponse($response, $statusCode);
    }
    
    /**
     * Send validation error response
     */
    protected function validationError($errors, $message = 'Ma\'lumotlar noto\'g\'ri') {
        $this->jsonResponse([
            'success' => false,
            'error' => $message,
            'validation_errors' => $errors
        ], 422);
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!$this->user) {
            $this->error('Authentication required', 401);
        }
    }
    
    /**
     * Require specific user status
     */
    protected function requireStatus($status = 'active') {
        $this->requireAuth();
        
        if ($this->user['status'] !== $status) {
            $this->error('Account status not allowed', 403);
        }
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($fields, $data = null) {
        $data = $data ?? $this->request;
        $missing = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->validationError($missing, 'Quyidagi maydonlar to\'ldirilishi shart: ' . implode(', ', $missing));
        }
        
        return true;
    }
    
    /**
     * Validate field types
     */
    protected function validateTypes($rules, $data = null) {
        $data = $data ?? $this->request;
        $errors = [];
        
        foreach ($rules as $field => $type) {
            if (!isset($data[$field])) {
                continue;
            }
            
            $value = $data[$field];
            $valid = true;
            
            switch ($type) {
                case 'integer':
                case 'int':
                    $valid = is_numeric($value) && is_int($value + 0);
                    break;
                case 'string':
                    $valid = is_string($value);
                    break;
                case 'email':
                    $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                    break;
                case 'boolean':
                case 'bool':
                    $valid = is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false']);
                    break;
                case 'array':
                    $valid = is_array($value);
                    break;
            }
            
            if (!$valid) {
                $errors[$field] = "Field '$field' must be of type $type";
            }
        }
        
        if (!empty($errors)) {
            $this->validationError($errors, 'Ma\'lumot turlari noto\'g\'ri');
        }
        
        return true;
    }
    
    /**
     * Get request parameter with default value
     */
    protected function input($key, $default = null) {
        return $this->request[$key] ?? $default;
    }
    
    /**
     * Check if request has parameter
     */
    protected function has($key) {
        return isset($this->request[$key]);
    }
    
    /**
     * Get only specific parameters
     */
    protected function only($keys) {
        $result = [];
        foreach ($keys as $key) {
            if (isset($this->request[$key])) {
                $result[$key] = $this->request[$key];
            }
        }
        return $result;
    }
    
    /**
     * Get all parameters except specified ones
     */
    protected function except($keys) {
        $result = $this->request;
        foreach ($keys as $key) {
            unset($result[$key]);
        }
        return $result;
    }
    
    /**
     * Paginate results
     */
    protected function paginate($query, $bindings = [], $perPage = 20, $page = null) {
        $page = $page ?? max(1, (int)$this->input('page', 1));
        $perPage = min(100, max(1, $perPage)); // Limit between 1-100
        
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_table";
        $countResult = $this->db->first($countQuery, $bindings);
        $total = $countResult ? (int)$countResult['total'] : 0;
        
        // Get paginated data
        $paginatedQuery = "$query LIMIT $perPage OFFSET $offset";
        $data = $this->db->get($paginatedQuery, $bindings);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ];
    }
    
    /**
     * Cursor-based pagination (for better performance on large datasets)
     */
    protected function cursorPaginate($query, $bindings = [], $limit = 20, $cursor = null, $cursorColumn = 'id') {
        $limit = min(100, max(1, $limit));
        
        // Add cursor condition
        if ($cursor !== null) {
            if (strpos(strtoupper($query), 'WHERE') !== false) {
                $query .= " AND `$cursorColumn` > ?";
            } else {
                $query .= " WHERE `$cursorColumn` > ?";
            }
            $bindings[] = $cursor;
        }
        
        // Add ordering and limit
        $query .= " ORDER BY `$cursorColumn` ASC LIMIT ?";
        $bindings[] = $limit + 1; // +1 to check if there are more records
        
        $data = $this->db->get($query, $bindings);
        
        $hasMore = count($data) > $limit;
        if ($hasMore) {
            array_pop($data); // Remove the extra record
        }
        
        $nextCursor = null;
        if ($hasMore && !empty($data)) {
            $lastRecord = end($data);
            $nextCursor = $lastRecord[$cursorColumn];
        }
        
        return [
            'data' => $data,
            'pagination' => [
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'limit' => $limit
            ]
        ];
    }
    
    /**
     * Log user action
     */
    protected function logUserAction($action, $data = null) {
        if ($this->user) {
            $this->logger->logUserAction($action, $this->user['id'], $data);
        }
    }
    
    /**
     * Apply rate limiting
     */
    protected function applyRateLimit($identifier = null, $maxAttempts = null, $window = null) {
        $rateLimiter = $this->app->get('rate_limiter');
        
        if (!$rateLimiter->attempt($identifier, $maxAttempts, $window)) {
            $this->error('Too many requests. Please try again later.', 429);
        }
    }
    
    /**
     * Cache response with TTL
     */
    protected function cacheResponse($key, $callback, $ttl = 300) {
        $cached = $this->cache->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $callback();
        $this->cache->set($key, $result, $ttl);
        
        return $result;
    }
    
    /**
     * Handle file upload
     */
    protected function handleFileUpload($fieldName, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $file = $_FILES[$fieldName];
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $this->error('File size too large. Maximum allowed: ' . ($maxSize / 1024 / 1024) . 'MB');
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            $this->error('File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
        }
        
        // Generate unique filename
        $filename = uniqid() . '.' . $extension;
        $uploadPath = __DIR__ . '/../storage/uploads/' . $filename;
        
        // Create upload directory if it doesn't exist
        $uploadDir = dirname($uploadPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'url' => '/uploads/' . $filename
            ];
        }
        
        $this->error('File upload failed');
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrf() {
        $token = $this->input('_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !$this->security->validateCsrfToken($token)) {
            $this->error('CSRF token mismatch', 419);
        }
    }
    
    /**
     * Get client IP address
     */
    protected function getClientIp() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if request is from mobile device
     */
    protected function isMobile() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/Mobile|Android|iPhone|iPad/', $userAgent);
    }
    
    /**
     * Get user's preferred language
     */
    protected function getUserLanguage() {
        if ($this->user && isset($this->user['language_code'])) {
            return $this->user['language_code'];
        }
        
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (strpos($acceptLanguage, 'ru') !== false) {
            return 'ru';
        }
        
        return 'uz'; // Default to Uzbek
    }
    
    /**
     * Send notification to user
     */
    protected function sendNotification($userId, $type, $title, $message, $data = null) {
        $sql = "INSERT INTO notifications (user_id, type, title, message, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        
        return $this->db->insert($sql, [
            $userId,
            $type,
            $title,
            $message,
            $data ? json_encode($data) : null
        ]);
    }
    
    /**
     * Format number for display
     */
    protected function formatNumber($number) {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }
        
        return number_format($number);
    }
    
    /**
     * Calculate time ago
     */
    protected function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'Hozirgina';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' daqiqa oldin';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' soat oldin';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' kun oldin';
        } elseif ($time < 31104000) {
            return floor($time / 2592000) . ' oy oldin';
        } else {
            return floor($time / 31104000) . ' yil oldin';
        }
    }
}