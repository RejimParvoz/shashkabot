<?php
/**
 * Shashka Game - WebSocket Configuration
 * Real-time Game Communication
 */

return [
    // Server Configuration
    'server' => [
        'host' => $_ENV['WEBSOCKET_HOST'] ?? '0.0.0.0',
        'port' => (int)($_ENV['WEBSOCKET_PORT'] ?? 8080),
        'ssl' => filter_var($_ENV['WEBSOCKET_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'ssl_cert' => $_ENV['WEBSOCKET_SSL_CERT'] ?? '',
        'ssl_key' => $_ENV['WEBSOCKET_SSL_KEY'] ?? '',
    ],
    
    // Connection Limits
    'limits' => [
        'max_connections' => 10000,
        'max_connections_per_ip' => 50,
        'max_frame_size' => 65536, // 64KB
        'max_message_size' => 1048576, // 1MB
        'connection_timeout' => 300, // 5 minutes
        'ping_interval' => 30, // seconds
        'pong_timeout' => 10, // seconds
    ],
    
    // Message Types
    'message_types' => [
        // Authentication
        'auth' => 'auth',
        'auth_response' => 'auth_response',
        
        // Game Messages
        'game_join' => 'game_join',
        'game_leave' => 'game_leave',
        'game_move' => 'game_move',
        'game_move_broadcast' => 'game_move_broadcast',
        'game_chat' => 'game_chat',
        'game_offer_draw' => 'game_offer_draw',
        'game_resign' => 'game_resign',
        'game_timeout' => 'game_timeout',
        'game_finished' => 'game_finished',
        
        // Spectator Messages
        'spectate_join' => 'spectate_join',
        'spectate_leave' => 'spectate_leave',
        'spectator_count' => 'spectator_count',
        
        // Matchmaking
        'find_opponent' => 'find_opponent',
        'cancel_search' => 'cancel_search',
        'match_found' => 'match_found',
        'match_declined' => 'match_declined',
        
        // Tournament Messages
        'tournament_join' => 'tournament_join',
        'tournament_update' => 'tournament_update',
        'tournament_round_start' => 'tournament_round_start',
        
        // Social Messages
        'friend_online' => 'friend_online',
        'friend_offline' => 'friend_offline',
        'private_message' => 'private_message',
        'clan_message' => 'clan_message',
        
        // System Messages
        'ping' => 'ping',
        'pong' => 'pong',
        'error' => 'error',
        'notification' => 'notification',
        'server_announcement' => 'server_announcement',
    ],
    
    // Rooms Configuration
    'rooms' => [
        'game_rooms' => [
            'prefix' => 'game_',
            'max_players' => 2,
            'max_spectators' => 100,
            'auto_cleanup' => true,
            'cleanup_delay' => 300, // 5 minutes after game ends
        ],
        'tournament_rooms' => [
            'prefix' => 'tournament_',
            'max_participants' => 1000,
            'broadcast_updates' => true,
        ],
        'clan_rooms' => [
            'prefix' => 'clan_',
            'max_members' => 50,
            'message_history' => 100,
        ],
        'global_chat' => [
            'name' => 'global',
            'max_users' => 1000,
            'message_history' => 50,
            'rate_limit' => 5, // messages per minute
        ],
    ],
    
    // Authentication
    'auth' => [
        'required' => true,
        'timeout' => 30, // seconds to authenticate after connection
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your_jwt_secret_here',
        'jwt_ttl' => 3600, // 1 hour
    ],
    
    // Rate Limiting
    'rate_limits' => [
        'messages_per_minute' => 60,
        'moves_per_minute' => 20,
        'chat_messages_per_minute' => 10,
        'violations_before_kick' => 3,
        'ban_duration' => 300, // 5 minutes
    ],
    
    // Game Integration
    'game' => [
        'move_validation' => true,
        'time_sync_interval' => 1, // seconds
        'lag_compensation' => true,
        'auto_save_moves' => true,
        'spectator_delay' => 5, // seconds delay for spectators
    ],
    
    // Performance Settings
    'performance' => [
        'worker_processes' => 4,
        'reactor_type' => 'epoll', // epoll, kqueue, select
        'buffer_size' => 8192,
        'enable_compression' => true,
        'compression_threshold' => 1024, // bytes
        'keepalive' => true,
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => '../storage/logs/websocket.log',
        'max_files' => 30,
        'log_connections' => true,
        'log_messages' => false, // Only in debug mode
        'log_errors' => true,
    ],
    
    // Monitoring
    'monitoring' => [
        'enabled' => true,
        'metrics_endpoint' => '/metrics',
        'health_endpoint' => '/health',
        'stats_interval' => 60, // seconds
        'track_metrics' => [
            'active_connections',
            'messages_per_second',
            'memory_usage',
            'cpu_usage',
            'active_games',
            'room_counts',
        ],
    ],
    
    // Security
    'security' => [
        'origin_check' => true,
        'allowed_origins' => [
            'https://shashka.uz',
            'https://web.telegram.org',
        ],
        'csrf_protection' => true,
        'xss_protection' => true,
        'validate_message_format' => true,
        'max_message_length' => 1000,
    ],
    
    // Clustering (for horizontal scaling)
    'clustering' => [
        'enabled' => false, // Enable in production with multiple servers
        'redis_host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
        'redis_port' => $_ENV['REDIS_PORT'] ?? 6379,
        'node_id' => $_ENV['WEBSOCKET_NODE_ID'] ?? 'node1',
        'sync_interval' => 5, // seconds
    ],
    
    // Load Balancing
    'load_balancing' => [
        'sticky_sessions' => true,
        'session_key' => 'user_id',
        'health_check_interval' => 30, // seconds
        'failover_enabled' => true,
    ],
    
    // Debug Mode
    'debug' => [
        'enabled' => $_ENV['APP_DEBUG'] ?? false,
        'log_all_messages' => false,
        'simulate_lag' => false,
        'lag_ms' => 100,
        'connection_stats' => true,
    ],
    
    // Graceful Shutdown
    'shutdown' => [
        'timeout' => 30, // seconds to wait for graceful shutdown
        'notify_clients' => true,
        'save_state' => true,
        'close_connections' => true,
    ],
];