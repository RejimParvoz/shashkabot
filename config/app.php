<?php
/**
 * Shashka Game - Application Configuration
 * High Performance Configuration for 1M+ Users
 */

return [
    // Application Settings
    'name' => $_ENV['APP_NAME'] ?? 'Shashka Game',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'https://shashka.uz',
    'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
    'timezone' => 'Asia/Tashkent',
    'locale' => 'uz',
    
    // Security Configuration
    'csrf_token_name' => $_ENV['CSRF_TOKEN_NAME'] ?? '_token',
    'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 86400),
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your_jwt_secret_here_min_32_chars',
    'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? 'your_encryption_key_here_32_chars',
    
    // Rate Limiting
    'rate_limit' => [
        'requests' => (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100),
        'window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60), // seconds
        'storage' => 'redis', // redis, file, database
    ],
    
    // File Upload Configuration
    'upload' => [
        'max_size' => (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 5242880), // 5MB
        'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,gif,webp'),
        'path' => 'storage/uploads/',
        'url' => '/uploads/',
    ],
    
    // CDN Configuration
    'cdn' => [
        'enabled' => !empty($_ENV['CDN_URL']),
        'url' => $_ENV['CDN_URL'] ?? '',
        'static_version' => $_ENV['STATIC_VERSION'] ?? '1.0.0',
    ],
    
    // Game Configuration
    'game' => [
        'default_elo' => (int)($_ENV['DEFAULT_ELO_RATING'] ?? 1000),
        'elo_k_factors' => [
            'new' => (int)($_ENV['ELO_K_FACTOR_NEW'] ?? 40),
            'medium' => (int)($_ENV['ELO_K_FACTOR_MEDIUM'] ?? 30),
            'high' => (int)($_ENV['ELO_K_FACTOR_HIGH'] ?? 20),
        ],
        'modes' => [
            'classic' => ['time' => 300, 'increment' => 5], // 5min + 5sec
            'blitz' => ['time' => 180, 'increment' => 2],   // 3min + 2sec
            'bullet' => ['time' => 60, 'increment' => 1],   // 1min + 1sec
            'rapid' => ['time' => 600, 'increment' => 10],  // 10min + 10sec
            'marathon' => ['time' => 1800, 'increment' => 30], // 30min + 30sec
        ],
        'ai_levels' => [
            'easy' => ['depth' => 2, 'name' => 'Oson'],
            'medium' => ['depth' => 4, 'name' => "O'rta"],
            'hard' => ['depth' => 6, 'name' => 'Qiyin'],
            'expert' => ['depth' => 8, 'name' => 'Ekspert'],
        ],
    ],
    
    // Economy Configuration
    'economy' => [
        'coins' => [
            'win' => (int)($_ENV['WIN_COINS'] ?? 25),
            'draw' => (int)($_ENV['DRAW_COINS'] ?? 10),
            'loss' => (int)($_ENV['LOSS_COINS'] ?? 5),
        ],
        'diamond_packs' => [
            ['stars' => 10, 'diamonds' => 100],
            ['stars' => 45, 'diamonds' => 500],
            ['stars' => 100, 'diamonds' => 1200],
            ['stars' => 200, 'diamonds' => 2500],
            ['stars' => 380, 'diamonds' => 5000],
        ],
        'vip_daily_diamonds' => [
            'bronze' => (int)($_ENV['DAILY_DIAMONDS_VIP_BRONZE'] ?? 5),
            'silver' => (int)($_ENV['DAILY_DIAMONDS_VIP_SILVER'] ?? 10),
            'gold' => (int)($_ENV['DAILY_DIAMONDS_VIP_GOLD'] ?? 20),
            'platinum' => (int)($_ENV['DAILY_DIAMONDS_VIP_PLATINUM'] ?? 35),
        ],
    ],
    
    // League Configuration
    'leagues' => [
        'bronze' => ['min_rating' => 0, 'max_rating' => 999, 'name' => 'Bronza'],
        'silver' => ['min_rating' => 1000, 'max_rating' => 1999, 'name' => 'Kumush'],
        'gold' => ['min_rating' => 2000, 'max_rating' => 2999, 'name' => 'Oltin'],
        'diamond' => ['min_rating' => 3000, 'max_rating' => 3999, 'name' => 'Olmos'],
        'royal' => ['min_rating' => 4000, 'max_rating' => 9999, 'name' => 'Shohona'],
    ],
    
    // VIP Configuration
    'vip_levels' => [
        'bronze' => [
            'stars_monthly' => 50,
            'daily_diamonds' => 5,
            'features' => ['vip_badge', 'bronze_chat_color'],
            'name' => 'Bronze VIP',
        ],
        'silver' => [
            'stars_monthly' => 120,
            'daily_diamonds' => 10,
            'features' => ['animated_badge', 'silver_chat_color'],
            'name' => 'Silver VIP',
        ],
        'gold' => [
            'stars_monthly' => 250,
            'daily_diamonds' => 20,
            'features' => ['gold_badge', 'exclusive_tournaments', 'rating_bonus_1_5x'],
            'name' => 'Gold VIP',
        ],
        'platinum' => [
            'stars_monthly' => 500,
            'daily_diamonds' => 35,
            'features' => ['diamond_badge', 'exclusive_items', 'rating_bonus_2x'],
            'name' => 'Platinum VIP',
        ],
    ],
    
    // Tournament Configuration
    'tournaments' => [
        'daily' => [
            'time' => $_ENV['DAILY_TOURNAMENT_TIME'] ?? '19:00',
            'entry_fee_coins' => 50,
            'prize_diamonds' => 500,
            'max_players' => 64,
        ],
        'weekly' => [
            'day' => 5, // Friday
            'time' => $_ENV['WEEKLY_TOURNAMENT_TIME'] ?? '20:00',
            'entry_fee_coins' => 100,
            'prize_diamonds' => 2000,
            'max_players' => 128,
        ],
        'monthly' => [
            'day' => 1, // 1st day of month
            'time' => $_ENV['MONTHLY_TOURNAMENT_TIME'] ?? '21:00',
            'entry_fee_diamonds' => 50,
            'prize_diamonds' => 5000,
            'max_players' => 256,
        ],
    ],
    
    // Battle Pass Configuration
    'battle_pass' => [
        'season_duration' => 30, // days
        'max_level' => 50,
        'premium_stars' => 150,
        'xp_per_game' => 10,
        'xp_per_win' => 25,
        'xp_per_quest' => 50,
        'free_rewards' => ['diamonds' => 200, 'coins' => 5000],
        'premium_rewards' => ['diamonds' => 500, 'coins' => 10000, 'exclusive_items' => true],
    ],
    
    // Arena Configuration
    'arena' => [
        'season_duration' => 7, // days (weekly)
        'points' => [
            'win' => 50,
            'draw' => 15,
            'loss' => 5,
        ],
        'streak_bonus' => [
            3 => 5,
            5 => 10,
            7 => 15,
            10 => 20,
        ],
        'rewards' => [
            'first_place' => ['diamonds' => 500, 'exclusive_item' => 'Arena Stone'],
            'participation' => ['diamonds' => 10],
        ],
    ],
    
    // Daily System Configuration
    'daily' => [
        'login_bonus' => [
            1 => ['diamonds' => 5, 'coins' => 50],
            2 => ['diamonds' => 10, 'coins' => 75],
            3 => ['diamonds' => 15, 'coins' => 100],
            4 => ['diamonds' => 20, 'coins' => 150],
            5 => ['diamonds' => 30, 'coins' => 200],
            6 => ['diamonds' => 40, 'coins' => 250],
            7 => ['diamonds' => 50, 'coins' => 300],
        ],
        'quest_reward' => ['diamonds' => 5], // for completing all daily quests
        'max_quests' => 3,
    ],
    
    // Holiday Configuration
    'holidays' => [
        'new_year' => [
            'start' => '12-31',
            'end' => '01-02',
            'multiplier' => 2.0,
            'name' => 'Yangi Yil',
        ],
        'navruz' => [
            'start' => '03-20',
            'end' => '03-22',
            'multiplier' => 2.0,
            'name' => 'Navro\'z',
        ],
        'independence' => [
            'start' => '08-31',
            'end' => '09-02',
            'multiplier' => 3.0,
            'name' => 'Mustaqillik kuni',
        ],
        'ramadan' => [
            'multiplier' => 2.5,
            'name' => 'Ramazon Hayiti',
            // Dates calculated dynamically
        ],
        'qurban' => [
            'multiplier' => 2.5,
            'name' => 'Qurbon Hayiti',
            // Dates calculated dynamically
        ],
    ],
    
    // Clan Configuration
    'clans' => [
        'create_cost_coins' => 1000,
        'max_members' => [
            1 => 10, 2 => 15, 3 => 25, 4 => 35, 5 => 50
        ],
        'roles' => ['member', 'elder', 'leader'],
        'max_level' => 5,
    ],
    
    // Social Configuration
    'social' => [
        'max_friends' => 50,
        'referral_reward' => ['diamonds' => 50],
    ],
    
    // Logging Configuration
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'error',
        'file' => $_ENV['LOG_FILE'] ?? '../storage/logs/app.log',
        'max_files' => 30,
        'slow_query_log' => filter_var($_ENV['SLOW_QUERY_LOG'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'slow_query_time' => (float)($_ENV['SLOW_QUERY_TIME'] ?? 2.0),
    ],
    
    // Pagination Configuration
    'pagination' => [
        'default_limit' => 20,
        'max_limit' => 100,
        'use_cursor' => true, // For large datasets
    ],
    
    // Cache Configuration
    'cache_ttl' => [
        'user_profile' => 300,     // 5 minutes
        'leaderboard' => 600,     // 10 minutes
        'shop_items' => 1800,     // 30 minutes
        'tournaments' => 60,      // 1 minute
        'game_state' => 30,       // 30 seconds
        'static_data' => 3600,    // 1 hour
    ],
];