<?php
/**
 * Shashka Game - Rate Limiter Class
 * Distributed Rate Limiting for 1M+ Users
 */

class RateLimiter {
    
    private $cache;
    private $config;
    private $defaultLimits = [
        'requests' => 100,
        'window' => 60 // seconds
    ];
    
    /**
     * Constructor
     */
    public function __construct($cache, $config = []) {
        $this->cache = $cache;
        $this->config = array_merge($this->defaultLimits, $config);
    }
    
    /**
     * Check if request should be allowed
     */
    public function attempt($identifier = null, $maxAttempts = null, $decayMinutes = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $maxAttempts = $maxAttempts ?: $this->config['requests'];
        $decayMinutes = $decayMinutes ?: ($this->config['window'] / 60);
        
        // Get current attempt count
        $attempts = $this->getAttempts($identifier);
        
        if ($attempts >= $maxAttempts) {
            // Check if window has expired
            if ($this->hasExpired($identifier)) {
                $this->resetAttempts($identifier);
                return true;
            }
            
            return false;
        }
        
        // Increment attempt count
        $this->incrementAttempts($identifier, $decayMinutes);
        
        return true;
    }
    
    /**
     * Get default identifier (IP + User)
     */
    private function getDefaultIdentifier() {
        $ip = $this->getClientIp();
        $user = $this->getCurrentUserId();
        
        return "rate_limit:{$ip}:{$user}";
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        // Check for various headers in order of trustworthiness
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if ($this->isValidIp($ip)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Validate IP address
     */
    private function isValidIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    
    /**
     * Get current user ID
     */
    private function getCurrentUserId() {
        // Try to get from session
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        
        // Try to get from JWT token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            
            // Simple JWT decode (just for user ID extraction)
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if (isset($payload['user_id'])) {
                    return $payload['user_id'];
                }
            }
        }
        
        return 'anonymous';
    }
    
    /**
     * Get current attempt count
     */
    private function getAttempts($identifier) {
        $key = $this->getAttemptKey($identifier);
        return (int)$this->cache->get($key, 0);
    }
    
    /**
     * Increment attempt count
     */
    private function incrementAttempts($identifier, $decayMinutes) {
        $key = $this->getAttemptKey($identifier);
        $ttl = $decayMinutes * 60;
        
        $attempts = $this->getAttempts($identifier);
        
        if ($attempts === 0) {
            // First attempt - set with TTL
            $this->cache->set($key, 1, $ttl);
            $this->cache->set($this->getTimerKey($identifier), time(), $ttl);
        } else {
            // Increment existing
            $this->cache->increment($key);
        }
    }
    
    /**
     * Reset attempt count
     */
    private function resetAttempts($identifier) {
        $this->cache->delete($this->getAttemptKey($identifier));
        $this->cache->delete($this->getTimerKey($identifier));
    }
    
    /**
     * Check if rate limit window has expired
     */
    private function hasExpired($identifier) {
        $timerKey = $this->getTimerKey($identifier);
        $startTime = $this->cache->get($timerKey);
        
        if (!$startTime) {
            return true;
        }
        
        return (time() - $startTime) >= $this->config['window'];
    }
    
    /**
     * Get cache key for attempt count
     */
    private function getAttemptKey($identifier) {
        return $identifier . ':attempts';
    }
    
    /**
     * Get cache key for timer
     */
    private function getTimerKey($identifier) {
        return $identifier . ':timer';
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($identifier = null, $maxAttempts = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $maxAttempts = $maxAttempts ?: $this->config['requests'];
        
        $attempts = $this->getAttempts($identifier);
        
        return max(0, $maxAttempts - $attempts);
    }
    
    /**
     * Get time until reset
     */
    public function getTimeUntilReset($identifier = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $timerKey = $this->getTimerKey($identifier);
        
        $startTime = $this->cache->get($timerKey);
        
        if (!$startTime) {
            return 0;
        }
        
        $elapsed = time() - $startTime;
        $remaining = $this->config['window'] - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Clear rate limit for identifier
     */
    public function clear($identifier = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $this->resetAttempts($identifier);
    }
    
    /**
     * Get rate limit status
     */
    public function getStatus($identifier = null, $maxAttempts = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $maxAttempts = $maxAttempts ?: $this->config['requests'];
        
        $attempts = $this->getAttempts($identifier);
        $remaining = $this->getRemainingAttempts($identifier, $maxAttempts);
        $resetTime = $this->getTimeUntilReset($identifier);
        
        return [
            'limit' => $maxAttempts,
            'remaining' => $remaining,
            'reset_in' => $resetTime,
            'attempts' => $attempts
        ];
    }
    
    /**
     * Rate limit for specific action
     */
    public function forAction($action, $identifier = null, $maxAttempts = null, $decayMinutes = null) {
        $actionIdentifier = ($identifier ?: $this->getDefaultIdentifier()) . ":action:$action";
        
        return $this->attempt($actionIdentifier, $maxAttempts, $decayMinutes);
    }
    
    /**
     * Rate limit for API endpoints
     */
    public function forApi($endpoint = null, $identifier = null) {
        $endpoint = $endpoint ?: $this->getCurrentEndpoint();
        $apiIdentifier = ($identifier ?: $this->getDefaultIdentifier()) . ":api:$endpoint";
        
        // Different limits for different endpoints
        $limits = $this->getApiLimits($endpoint);
        
        return $this->attempt($apiIdentifier, $limits['requests'], $limits['window'] / 60);
    }
    
    /**
     * Get current API endpoint
     */
    private function getCurrentEndpoint() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Normalize endpoint (remove IDs)
        $endpoint = preg_replace('/\/\d+/', '/{id}', $uri);
        
        return $endpoint;
    }
    
    /**
     * Get API-specific rate limits
     */
    private function getApiLimits($endpoint) {
        $defaultLimits = ['requests' => 60, 'window' => 60]; // 1 request per second
        
        $endpointLimits = [
            '/api/auth/telegram' => ['requests' => 5, 'window' => 300],      // 5 per 5 minutes
            '/api/game/create' => ['requests' => 10, 'window' => 60],        // 10 per minute
            '/api/game/move' => ['requests' => 30, 'window' => 60],          // 30 per minute
            '/api/shop/purchase' => ['requests' => 5, 'window' => 300],      // 5 per 5 minutes
            '/api/payments/purchase' => ['requests' => 3, 'window' => 600],  // 3 per 10 minutes
            '/api/tournaments/{id}/join' => ['requests' => 3, 'window' => 60], // 3 per minute
        ];
        
        return $endpointLimits[$endpoint] ?? $defaultLimits;
    }
    
    /**
     * Add rate limit headers to response
     */
    public function addHeaders($identifier = null, $maxAttempts = null) {
        $status = $this->getStatus($identifier, $maxAttempts);
        
        header("X-RateLimit-Limit: {$status['limit']}");
        header("X-RateLimit-Remaining: {$status['remaining']}");
        header("X-RateLimit-Reset: " . (time() + $status['reset_in']));
        
        if ($status['remaining'] <= 0) {
            header("Retry-After: {$status['reset_in']}");
        }
    }
    
    /**
     * Handle rate limit exceeded
     */
    public function handleExceeded($identifier = null) {
        $status = $this->getStatus($identifier);
        
        http_response_code(429);
        
        // Add rate limit headers
        $this->addHeaders($identifier);
        
        // Return JSON response for API requests
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Too many requests',
                'retry_after' => $status['reset_in'],
                'limit' => $status['limit'],
                'remaining' => $status['remaining']
            ]);
        } else {
            // Return HTML for web requests
            echo '<h1>429 - Too Many Requests</h1>';
            echo '<p>Please wait ' . $status['reset_in'] . ' seconds before trying again.</p>';
        }
        
        exit;
    }
    
    /**
     * Check if request is API request
     */
    private function isApiRequest() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/api/') === 0;
    }
    
    /**
     * Sliding window rate limiter (more accurate)
     */
    public function slidingWindow($identifier = null, $maxAttempts = null, $windowSize = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $maxAttempts = $maxAttempts ?: $this->config['requests'];
        $windowSize = $windowSize ?: $this->config['window'];
        
        $now = time();
        $windowStart = $now - $windowSize;
        
        // Get all timestamps in the current window
        $key = $identifier . ':sliding_window';
        $timestamps = $this->cache->get($key, []);
        
        // Filter out old timestamps
        $timestamps = array_filter($timestamps, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        if (count($timestamps) >= $maxAttempts) {
            return false;
        }
        
        // Add current timestamp
        $timestamps[] = $now;
        
        // Store updated timestamps
        $this->cache->set($key, $timestamps, $windowSize);
        
        return true;
    }
    
    /**
     * Token bucket rate limiter (allows bursts)
     */
    public function tokenBucket($identifier = null, $capacity = null, $refillRate = null) {
        $identifier = $identifier ?: $this->getDefaultIdentifier();
        $capacity = $capacity ?: $this->config['requests'];
        $refillRate = $refillRate ?: ($capacity / $this->config['window']); // tokens per second
        
        $key = $identifier . ':token_bucket';
        $bucket = $this->cache->get($key, [
            'tokens' => $capacity,
            'last_refill' => time()
        ]);
        
        // Calculate tokens to add
        $now = time();
        $elapsed = $now - $bucket['last_refill'];
        $tokensToAdd = $elapsed * $refillRate;
        
        // Update bucket
        $bucket['tokens'] = min($capacity, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        if ($bucket['tokens'] < 1) {
            return false;
        }
        
        // Consume one token
        $bucket['tokens'] -= 1;
        
        // Store updated bucket
        $this->cache->set($key, $bucket, $this->config['window'] * 2);
        
        return true;
    }
}