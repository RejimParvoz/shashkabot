<?php
/**
 * Shashka Game - Logger Class
 * High Performance Logging System for 1M+ Users
 */

class Logger {
    
    private $config;
    private $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    private $logHandlers = [];
    
    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->config = array_merge([
            'level' => 'error',
            'file' => '../storage/logs/app.log',
            'max_files' => 30,
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'format' => '[%datetime%] %level%: %message% %context% %extra%'
        ], $config);
        
        $this->initializeHandlers();
    }
    
    /**
     * Initialize log handlers
     */
    private function initializeHandlers() {
        // File handler
        $this->logHandlers[] = new FileLogHandler($this->config);
        
        // Database handler for critical errors
        $this->logHandlers[] = new DatabaseLogHandler();
        
        // Syslog handler for production
        if ($_ENV['APP_ENV'] === 'production') {
            $this->logHandlers[] = new SyslogHandler();
        }
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log message
     */
    public function log($level, $message, $context = []) {
        // Check if level should be logged
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $logEntry = $this->createLogEntry($level, $message, $context);
        
        // Send to all handlers
        foreach ($this->logHandlers as $handler) {
            $handler->handle($logEntry);
        }
        
        // Send critical errors to monitoring service
        if ($level === 'critical') {
            $this->sendToMonitoring($logEntry);
        }
    }
    
    /**
     * Check if level should be logged
     */
    private function shouldLog($level) {
        $configLevel = $this->config['level'] ?? 'error';
        $configLevelInt = $this->logLevels[$configLevel] ?? 3;
        $levelInt = $this->logLevels[$level] ?? 0;
        
        return $levelInt >= $configLevelInt;
    }
    
    /**
     * Create log entry
     */
    private function createLogEntry($level, $message, $context) {
        return [
            'datetime' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'extra' => $this->getExtraData()
        ];
    }
    
    /**
     * Get extra data for log entry
     */
    private function getExtraData() {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
    }
    
    /**
     * Send critical errors to monitoring service
     */
    private function sendToMonitoring($logEntry) {
        // Implement integration with monitoring services like New Relic, Datadog, etc.
        // For now, just send to error log
        error_log("CRITICAL ERROR: " . json_encode($logEntry));
    }
    
    /**
     * Log game event
     */
    public function logGameEvent($eventType, $gameId, $playerId, $data = []) {
        $this->info("Game Event: $eventType", [
            'game_id' => $gameId,
            'player_id' => $playerId,
            'event_data' => $data
        ]);
    }
    
    /**
     * Log user action
     */
    public function logUserAction($action, $userId, $data = []) {
        $this->info("User Action: $action", [
            'user_id' => $userId,
            'action_data' => $data
        ]);
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($eventType, $severity = 'warning', $data = []) {
        $this->log($severity, "Security Event: $eventType", $data);
    }
    
    /**
     * Log performance metrics
     */
    public function logPerformance($metric, $value, $context = []) {
        $this->info("Performance Metric: $metric = $value", $context);
    }
    
    /**
     * Get log statistics
     */
    public function getStats() {
        $stats = [];
        
        foreach ($this->logHandlers as $handler) {
            if (method_exists($handler, 'getStats')) {
                $stats[get_class($handler)] = $handler->getStats();
            }
        }
        
        return $stats;
    }
}

/**
 * File Log Handler
 */
class FileLogHandler {
    private $config;
    private $logFile;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logFile = $config['file'];
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function handle($logEntry) {
        // Check file size and rotate if needed
        $this->rotateIfNeeded();
        
        // Format log entry
        $formattedEntry = $this->formatEntry($logEntry);
        
        // Write to file with locking
        file_put_contents($this->logFile, $formattedEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    private function formatEntry($entry) {
        $format = $this->config['format'];
        
        $replacements = [
            '%datetime%' => $entry['datetime'],
            '%level%' => $entry['level'],
            '%message%' => $entry['message'],
            '%context%' => !empty($entry['context']) ? json_encode($entry['context']) : '',
            '%extra%' => !empty($entry['extra']) ? json_encode($entry['extra']) : ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }
    
    private function rotateIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $maxSize = $this->config['max_file_size'] ?? (10 * 1024 * 1024);
        
        if (filesize($this->logFile) >= $maxSize) {
            $this->rotateLogFile();
        }
    }
    
    private function rotateLogFile() {
        $maxFiles = $this->config['max_files'] ?? 30;
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log to .1
        if (file_exists($this->logFile)) {
            rename($this->logFile, $this->logFile . '.1');
        }
    }
}

/**
 * Database Log Handler (for critical errors)
 */
class DatabaseLogHandler {
    
    public function handle($logEntry) {
        // Only log error and critical levels to database
        if (!in_array($logEntry['level'], ['ERROR', 'CRITICAL'])) {
            return;
        }
        
        try {
            $app = App::getInstance();
            $db = $app->db();
            
            $db->insert("INSERT INTO error_logs (level, message, context, extra, created_at) VALUES (?, ?, ?, ?, ?)", [
                $logEntry['level'],
                $logEntry['message'],
                json_encode($logEntry['context']),
                json_encode($logEntry['extra']),
                $logEntry['datetime']
            ]);
        } catch (Exception $e) {
            // Fallback to error_log if database fails
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
}

/**
 * Syslog Handler
 */
class SyslogHandler {
    
    public function handle($logEntry) {
        $priority = $this->getLevelPriority($logEntry['level']);
        
        $message = sprintf(
            "%s: %s %s",
            $logEntry['level'],
            $logEntry['message'],
            !empty($logEntry['context']) ? json_encode($logEntry['context']) : ''
        );
        
        syslog($priority, $message);
    }
    
    private function getLevelPriority($level) {
        $priorities = [
            'DEBUG' => LOG_DEBUG,
            'INFO' => LOG_INFO,
            'WARNING' => LOG_WARNING,
            'ERROR' => LOG_ERR,
            'CRITICAL' => LOG_CRIT
        ];
        
        return $priorities[$level] ?? LOG_INFO;
    }
}