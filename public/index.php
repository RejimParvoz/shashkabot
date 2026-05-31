<?php
/**
 * Shashka Game Platform - Entry Point
 * High Performance Checkers Game for 1M+ Users
 * PHP 7.4 Compatible
 */

// Performance optimizations
ini_set('opcache.enable', '1');
ini_set('opcache.memory_consumption', '256');
ini_set('opcache.max_accelerated_files', '20000');

// Error reporting
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/error.log');

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS for Telegram Mini App
header('Access-Control-Allow-Origin: https://web.telegram.org');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Tashkent');

// Start session with security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Autoloader and bootstrap
require_once __DIR__ . '/../core/App.php';

try {
    // Initialize application
    $app = new App();
    $app->run();
} catch (Exception $e) {
    // Log error
    error_log('Application Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Return JSON error for API requests
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Ichki server xatosi',
            'code' => 500
        ]);
    } else {
        // Redirect to error page
        header('Location: /error.html');
    }
    exit();
}