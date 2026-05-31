<?php
/**
 * Shashka Game - Cache Configuration
 * 2-Level Caching System for 1M+ Users
 */

return [
    // Default cache driver
    'default' => $_ENV['CACHE_DRIVER'] ?? 'redis',
    
    // Cache stores configuration
    'stores' => [
        
        // Redis cache (Level 1 - Fast)
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? '',
            'database' => $_ENV['REDIS_DATABASE'] ?? 0,
            'timeout' => 2.0,
            'read_timeout' => 2.0,
            'prefix' => 'shashka:',
            
            // Redis optimizations
            'options' => [
                'serializer' => 'php', // php, igbinary, json
                'compression' => 'lz4', // lz4, zstd, gzip, none
            ],
            
            // Connection pool
            'pool' => [
                'min_connections' => 5,
                'max_connections' => 20,
                'idle_timeout' => 300,
            ],
            
            // Clustering for high availability
            'cluster' => [
                'enabled' => false, // Enable in production
                'nodes' => [
                    ['host' => '127.0.0.1', 'port' => 6379],
                    ['host' => '127.0.0.1', 'port' => 6380],
                    ['host' => '127.0.0.1', 'port' => 6381],
                ],
            ],
        ],
        
        // File cache (Level 2 - Persistent)
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/cache',
            'hash_function' => 'xxh3', // Fast hash for file names
            'directory_levels' => 2, // Prevent too many files in one dir
            'file_locking' => true,
            'compress' => true,
            'compression_level' => 6,
        ],
        
        // Memory cache (Level 0 - Ultra Fast, In-Process)
        'memory' => [
            'driver' => 'array',
            'max_size' => 50 * 1024 * 1024, // 50MB max
            'ttl' => 300, // 5 minutes max
        ],
        
        // Database cache (Fallback)
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => 'mysql',
            'prefix' => 'shashka_cache',
        ],
        
        // Null cache (for debugging)
        'null' => [
            'driver' => 'null',
        ],
    ],
    
    // Cache layers configuration (Multi-level caching)
    'layers' => [
        'game_state' => ['memory', 'redis'],      // Ultra fast access
        'user_profile' => ['redis', 'file'],     // Fast with persistence
        'leaderboard' => ['redis', 'file'],      // Updated frequently
        'shop_items' => ['file'],                // Static data
        'tournaments' => ['redis'],              // Real-time data
        'static_data' => ['file'],               // Rarely changes
    ],
    
    // TTL (Time To Live) configuration by data type
    'ttl' => [
        // Game related
        'game_state' => 30,        // 30 seconds
        'game_moves' => 300,       // 5 minutes
        'active_games' => 60,      // 1 minute
        
        // User related  
        'user_profile' => 300,     // 5 minutes
        'user_stats' => 600,       // 10 minutes
        'user_inventory' => 1800,  // 30 minutes
        'user_friends' => 3600,    // 1 hour
        
        // Leaderboard and rankings
        'leaderboard' => 300,      // 5 minutes
        'top_players' => 600,      // 10 minutes
        'league_rankings' => 1800, // 30 minutes
        
        // Shop and economy
        'shop_items' => 3600,      // 1 hour
        'diamond_packs' => 7200,   // 2 hours
        'auction_items' => 60,     // 1 minute
        
        // Tournaments and events
        'tournaments' => 60,       // 1 minute
        'tournament_brackets' => 300, // 5 minutes
        'arena_rankings' => 600,   // 10 minutes
        
        // Static data
        'app_config' => 86400,     // 24 hours
        'achievements' => 7200,    // 2 hours
        'quests' => 3600,         // 1 hour
        
        // Social features
        'clan_info' => 600,        // 10 minutes
        'clan_members' => 1800,    // 30 minutes
        'chat_history' => 300,     // 5 minutes
    ],
    
    // Cache tags for group invalidation
    'tags' => [
        'user' => 'user_{user_id}',
        'game' => 'game_{game_id}',
        'tournament' => 'tournament_{tournament_id}',
        'clan' => 'clan_{clan_id}',
        'leaderboard' => 'leaderboard_{league}',
        'shop' => 'shop_{category}',
    ],
    
    // Warm-up cache configuration
    'warmup' => [
        'enabled' => true,
        'schedule' => '*/5 * * * *', // Every 5 minutes
        'items' => [
            'leaderboard' => [
                'keys' => ['top_players_global', 'top_players_bronze', 'top_players_silver'],
                'priority' => 'high',
            ],
            'shop_items' => [
                'keys' => ['shop_stones', 'shop_boards', 'shop_frames'],
                'priority' => 'medium',
            ],
            'tournaments' => [
                'keys' => ['active_tournaments', 'upcoming_tournaments'],
                'priority' => 'high',
            ],
        ],
    ],
    
    // Cache statistics and monitoring
    'statistics' => [
        'enabled' => true,
        'track_hits' => true,
        'track_misses' => true,
        'track_timing' => true,
        'log_slow_operations' => true,
        'slow_threshold' => 0.1, // seconds
    ],
    
    // Cache invalidation strategies
    'invalidation' => [
        'strategies' => [
            'ttl' => true,         // Time-based expiration
            'lru' => true,         // Least Recently Used
            'tag' => true,         // Tag-based group invalidation
            'manual' => true,      // Manual invalidation
        ],
        
        // Smart invalidation rules
        'rules' => [
            'user_profile_update' => ['user_{user_id}*'],
            'game_finished' => ['game_{game_id}*', 'leaderboard*', 'user_{player1_id}*', 'user_{player2_id}*'],
            'shop_item_purchased' => ['shop*', 'user_{user_id}*'],
            'tournament_update' => ['tournament*', 'leaderboard*'],
        ],
    ],
    
    // Compression configuration
    'compression' => [
        'enabled' => true,
        'algorithm' => 'lz4', // lz4, zstd, gzip
        'level' => 6,
        'min_size' => 1024, // Only compress data larger than 1KB
    ],
    
    // Serialization configuration
    'serialization' => [
        'default' => 'php',
        'drivers' => [
            'php' => ['fast' => false, 'compact' => true],
            'json' => ['fast' => true, 'compact' => false],
            'igbinary' => ['fast' => true, 'compact' => true], // If extension available
        ],
    ],
    
    // Cache partitioning for large datasets
    'partitioning' => [
        'enabled' => true,
        'strategies' => [
            'hash' => ['leaderboard', 'user_profiles'],
            'range' => ['game_history', 'transactions'],
            'list' => ['active_games', 'online_users'],
        ],
        'partition_size' => 1000, // items per partition
    ],
    
    // Development and debugging
    'debug' => [
        'log_operations' => false, // Only in development
        'trace_calls' => false,
        'profile_performance' => false,
        'mock_redis' => false, // Use array driver instead of Redis
    ],
    
    // Production optimizations
    'production' => [
        'preload_critical' => true,
        'background_refresh' => true,
        'circuit_breaker' => true,
        'fallback_enabled' => true,
        'health_checks' => true,
    ],
];