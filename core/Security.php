<?php
/**
 * Shashka Game - Security Class
 * Comprehensive Security Layer for 1M+ Users
 */

class Security {
    
    private $config;
    private $currentUser = null;
    private $csrfTokens = [];
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
        $this->initializeSecurity();
    }
    
    /**
     * Initialize security settings
     */
    private function initializeSecurity() {
        // Start secure session if not started
        if (session_status() === PHP_SESSION_NONE) {
            $this->startSecureSession();
        }
        
        // Initialize CSRF tokens
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        $this->csrfTokens = &$_SESSION['csrf_tokens'];
    }
    
    /**
     * Start secure session
     */
    private function startSecureSession() {
        // Configure secure session settings
        ini_set('session.cookie_lifetime', $this->config['session_lifetime']);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        session_start();
    }
    
    /**
     * Validate incoming request
     */
    public function validateRequest() {
        // Check request method
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            return false;
        }
        
        // Validate content type for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $allowedTypes = [
                'application/json',
                'application/x-www-form-urlencoded',
                'multipart/form-data'
            ];
            
            $isValidContentType = false;
            foreach ($allowedTypes as $type) {
                if (strpos($contentType, $type) === 0) {
                    $isValidContentType = true;
                    break;
                }
            }
            
            if (!$isValidContentType) {
                return false;
            }
        }
        
        // Validate request size
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($contentLength > $maxSize) {
            return false;
        }
        
        // Check for suspicious patterns
        if ($this->containsMaliciousPatterns()) {
            $this->logSecurityViolation('Malicious pattern detected');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for malicious patterns in request
     */
    private function containsMaliciousPatterns() {
        $suspicious = [
            'union.*select',
            'drop.*table',
            '<script',
            'javascript:',
            'eval\(',
            'exec\(',
            'system\(',
            'passthru\(',
            'shell_exec\(',
            '`.*`',
            '\.\./\.\.',
        ];
        
        $inputs = array_merge($_GET, $_POST);
        $inputString = json_encode($inputs) . ($_SERVER['REQUEST_URI'] ?? '');
        
        foreach ($suspicious as $pattern) {
            if (preg_match('/' . $pattern . '/i', $inputString)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // Remove control characters except tab, newline, carriage return
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            
            return $data;
        }
        
        return $data;
    }
    
    /**
     * Escape output for HTML
     */
    public function escapeHtml($data) {
        if (is_array($data)) {
            return array_map([$this, 'escapeHtml'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        $token = bin2hex(random_bytes(32));
        $this->csrfTokens[$token] = time();
        
        // Clean old tokens (older than 1 hour)
        $this->cleanOldCsrfTokens();
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token) {
        if (!$token || !isset($this->csrfTokens[$token])) {
            return false;
        }
        
        // Check token age (valid for 1 hour)
        if (time() - $this->csrfTokens[$token] > 3600) {
            unset($this->csrfTokens[$token]);
            return false;
        }
        
        // Remove token after use (one-time use)
        unset($this->csrfTokens[$token]);
        
        return true;
    }
    
    /**
     * Clean old CSRF tokens
     */
    private function cleanOldCsrfTokens() {
        $currentTime = time();
        foreach ($this->csrfTokens as $token => $timestamp) {
            if ($currentTime - $timestamp > 3600) { // 1 hour
                unset($this->csrfTokens[$token]);
            }
        }
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,        // 4 iterations
            'threads' => 3           // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate JWT token
     */
    public function generateJwtToken($payload) {
        $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->config['jwt_ttl'];
        $payloadEncoded = base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $header . '.' . $payloadEncoded, $this->config['jwt_secret'], true);
        $signatureEncoded = base64url_encode($signature);
        
        return $header . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Verify JWT token
     */
    public function verifyJwtToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $header . '.' . $payload, $this->config['jwt_secret'], true);
        $expectedSignatureEncoded = base64url_encode($expectedSignature);
        
        if (!hash_equals($expectedSignatureEncoded, $signature)) {
            return false;
        }
        
        // Decode payload
        $payloadData = json_decode(base64url_decode($payload), true);
        
        // Check expiration
        if (isset($payloadData['exp']) && time() > $payloadData['exp']) {
            return false;
        }
        
        return $payloadData;
    }
    
    /**
     * Authenticate user via Telegram
     */
    public function authenticateTelegram($initData) {
        // Parse Telegram init data
        parse_str($initData, $data);
        
        if (!isset($data['hash'])) {
            return false;
        }
        
        $hash = $data['hash'];
        unset($data['hash']);
        
        // Create data check string
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);
        
        // Verify hash
        $secretKey = hash('sha256', $this->config['telegram']['bot']['token'], true);
        $expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        
        if (!hash_equals($expectedHash, $hash)) {
            return false;
        }
        
        // Check timestamp (data should be recent)
        if (isset($data['auth_date'])) {
            $authTime = (int)$data['auth_date'];
            if (time() - $authTime > 86400) { // 24 hours
                return false;
            }
        }
        
        // Parse user data
        if (isset($data['user'])) {
            $userData = json_decode($data['user'], true);
            return $this->processUserLogin($userData);
        }
        
        return false;
    }
    
    /**
     * Process user login
     */
    private function processUserLogin($telegramUser) {
        // Load user from database or create new
        $app = App::getInstance();
        $db = $app->db();
        
        $user = $db->first("SELECT * FROM users WHERE telegram_id = ?", [$telegramUser['id']]);
        
        if (!$user) {
            // Create new user
            $userId = $db->insert("INSERT INTO users (telegram_id, username, first_name, last_name, photo_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())", [
                $telegramUser['id'],
                $telegramUser['username'] ?? null,
                $telegramUser['first_name'] ?? '',
                $telegramUser['last_name'] ?? '',
                $telegramUser['photo_url'] ?? null
            ]);
            
            $user = $db->first("SELECT * FROM users WHERE id = ?", [$userId]);
        } else {
            // Update user data
            $db->update("UPDATE users SET username = ?, first_name = ?, last_name = ?, photo_url = ?, last_active = NOW(), updated_at = NOW() WHERE id = ?", [
                $telegramUser['username'] ?? null,
                $telegramUser['first_name'] ?? '',
                $telegramUser['last_name'] ?? '',
                $telegramUser['photo_url'] ?? null,
                $user['id']
            ]);
        }
        
        // Set current user
        $this->currentUser = $user;
        $_SESSION['user_id'] = $user['id'];
        
        return $user;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if ($this->currentUser) {
            return $this->currentUser;
        }
        
        // Check session
        if (isset($_SESSION['user_id'])) {
            $app = App::getInstance();
            $db = $app->db();
            
            $this->currentUser = $db->first("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        }
        
        // Check JWT token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $payload = $this->verifyJwtToken($token);
            
            if ($payload && isset($payload['user_id'])) {
                $app = App::getInstance();
                $db = $app->db();
                
                $this->currentUser = $db->first("SELECT * FROM users WHERE id = ?", [$payload['user_id']]);
            }
        }
        
        return $this->currentUser;
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return $this->getCurrentUser() !== null;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $this->currentUser = null;
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        // Clear cookies
        if (isset($_COOKIE['session'])) {
            setcookie('session', '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data) {
        $key = $this->config['encryption_key'];
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encryptedData) {
        $key = $this->config['encryption_key'];
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Log security violation
     */
    private function logSecurityViolation($message) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'message' => $message
        ];
        
        error_log("Security Violation: " . json_encode($logData));
        
        // Store in database for analysis
        try {
            $app = App::getInstance();
            $db = $app->db();
            
            $db->insert("INSERT INTO security_logs (ip_address, user_agent, request_uri, violation_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())", [
                $logData['ip'],
                $logData['user_agent'],
                $logData['uri'],
                'malicious_pattern',
                $message
            ]);
        } catch (Exception $e) {
            // Ignore database errors in security logging
        }
    }
}

/**
 * Base64 URL-safe encode
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL-safe decode
 */
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}