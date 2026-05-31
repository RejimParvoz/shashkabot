<?php
/**
 * Shashka Game - Database Configuration
 * Optimized for 1M+ Users with MySQL 5.7
 */

return [
    // Default connection
    'default' => 'mysql',
    
    // Database connections
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_NAME'] ?? 'shashka_game',
            'username' => $_ENV['DB_USER'] ?? 'shashka_user',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            
            // Connection options for high performance
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
            
            // MySQL specific optimizations
            'mysql_options' => [
                'sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO',
                'innodb_buffer_pool_size' => '2G', // Adjust based on RAM
                'innodb_log_file_size' => '512M',
                'innodb_flush_log_at_trx_commit' => 2, // Better performance, slight risk
                'query_cache_type' => 1,
                'query_cache_size' => '256M',
                'max_connections' => 1000,
                'innodb_thread_concurrency' => 0, // Let MySQL decide
                'innodb_read_io_threads' => 8,
                'innodb_write_io_threads' => 8,
            ],
        ],
        
        // Read replica for read-heavy operations
        'mysql_read' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_READ_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_READ_PORT'] ?? $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_NAME'] ?? 'shashka_game',
            'username' => $_ENV['DB_READ_USER'] ?? $_ENV['DB_USER'] ?? 'shashka_user',
            'password' => $_ENV['DB_READ_PASS'] ?? $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'read_only' => true,
            
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ],
        ],
    ],
    
    // Table configurations for partitioning
    'partitions' => [
        'game_moves' => [
            'type' => 'RANGE',
            'column' => 'created_at',
            'interval' => 'MONTH',
            'retention' => 6, // months
        ],
        'transactions' => [
            'type' => 'RANGE', 
            'column' => 'created_at',
            'interval' => 'MONTH',
            'retention' => 12, // months
        ],
        'rating_history' => [
            'type' => 'RANGE',
            'column' => 'created_at', 
            'interval' => 'MONTH',
            'retention' => 6, // months
        ],
        'chat_messages' => [
            'type' => 'RANGE',
            'column' => 'created_at',
            'interval' => 'WEEK',
            'retention' => 4, // weeks
        ],
        'admin_logs' => [
            'type' => 'RANGE',
            'column' => 'created_at',
            'interval' => 'MONTH', 
            'retention' => 3, // months
        ],
    ],
    
    // Archive configuration (old data)
    'archive' => [
        'enabled' => true,
        'retention_months' => 6,
        'tables' => [
            'game_moves' => 'game_moves_archive',
            'transactions' => 'transactions_archive', 
            'rating_history' => 'rating_history_archive',
            'chat_messages' => 'chat_messages_archive',
            'admin_logs' => 'admin_logs_archive',
        ],
        'schedule' => '0 2 1 * *', // Monthly at 2 AM on 1st day
    ],
    
    // Index optimization
    'indexes' => [
        // Critical indexes for performance
        'users' => [
            'telegram_id' => ['telegram_id'],
            'rating_desc' => ['rating DESC'],
            'status_active' => ['status', 'last_active'],
            'created_at' => ['created_at DESC'],
        ],
        'games' => [
            'status' => ['status'],
            'players' => ['player1_id', 'player2_id'],
            'mode_created' => ['mode', 'created_at DESC'],
            'finished_rating' => ['finished_at', 'rated'],
        ],
        'game_moves' => [
            'game_id' => ['game_id'],
            'created_at' => ['created_at DESC'],
            'game_move_num' => ['game_id', 'move_number'],
        ],
        'shop_items' => [
            'category' => ['category'],
            'price_type' => ['price_type', 'price ASC'],
            'active' => ['is_active'],
        ],
        'tournaments' => [
            'status_start' => ['status', 'start_time'],
            'type_date' => ['type', 'created_at DESC'],
        ],
        'leaderboard' => [
            'rating_desc' => ['rating DESC'],
            'league_rating' => ['league', 'rating DESC'],
            'updated_at' => ['updated_at DESC'],
        ],
    ],
    
    // Connection pool settings
    'pool' => [
        'min_connections' => 10,
        'max_connections' => 100,
        'idle_timeout' => 300, // seconds
        'max_lifetime' => 3600, // seconds
        'validation_query' => 'SELECT 1',
    ],
    
    // Query optimization
    'query' => [
        'slow_query_log' => true,
        'slow_query_time' => 2.0, // seconds
        'log_queries_not_using_indexes' => true,
        'explain_threshold' => 1000, // rows
    ],
    
    // Backup configuration
    'backup' => [
        'enabled' => true,
        'schedule' => '0 3 * * *', // Daily at 3 AM
        'retention_days' => 30,
        'compress' => true,
        'exclude_tables' => [
            'sessions',
            'cache',
            'rate_limits',
        ],
        'path' => '/backup/shashka/',
    ],
    
    // Monitoring
    'monitoring' => [
        'slow_queries' => true,
        'connection_count' => true,
        'deadlock_detection' => true,
        'performance_schema' => true,
        'alerts' => [
            'slow_query_threshold' => 5.0,
            'connection_threshold' => 800,
            'deadlock_threshold' => 10, // per hour
        ],
    ],
    
    // Replication settings
    'replication' => [
        'enabled' => false, // Enable in production
        'read_write_split' => true,
        'read_queries' => [
            'SELECT',
            'SHOW',
            'DESCRIBE',
            'EXPLAIN',
        ],
        'lag_threshold' => 5, // seconds
    ],
    
    // Sharding configuration (for future scaling)
    'sharding' => [
        'enabled' => false,
        'shard_key' => 'user_id',
        'shards' => [
            'shard1' => ['host' => 'shard1.db.local'],
            'shard2' => ['host' => 'shard2.db.local'],
        ],
        'hash_function' => 'crc32',
    ],
];