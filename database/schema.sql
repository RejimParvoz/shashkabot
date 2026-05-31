-- Shashka Game Database Schema
-- Optimized for 1M+ Users with MySQL 5.7
-- High Performance with Proper Indexing and Partitioning

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `shashka_game` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shashka_game`;

-- ================================================================
-- USER MANAGEMENT TABLES
-- ================================================================

-- Users table (Main user data)
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) unsigned NOT NULL UNIQUE,
  `username` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `photo_url` text DEFAULT NULL,
  `language_code` varchar(10) DEFAULT 'uz',
  
  -- Game stats
  `rating` int(11) NOT NULL DEFAULT 1000,
  `league` enum('bronze','silver','gold','diamond','royal') NOT NULL DEFAULT 'bronze',
  `total_games` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `win_streak` int(11) NOT NULL DEFAULT 0,
  `max_win_streak` int(11) NOT NULL DEFAULT 0,
  
  -- Economy
  `diamonds` bigint(20) NOT NULL DEFAULT 0,
  `coins` bigint(20) NOT NULL DEFAULT 0,
  
  -- Status and timestamps
  `status` enum('active','banned','suspended') NOT NULL DEFAULT 'active',
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `last_active` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_telegram_id` (`telegram_id`),
  KEY `idx_rating_desc` (`rating` DESC),
  KEY `idx_league_rating` (`league`, `rating` DESC),
  KEY `idx_status_active` (`status`, `last_active`),
  KEY `idx_created_at` (`created_at` DESC),
  KEY `idx_online_users` (`is_online`, `last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VIP Subscriptions
CREATE TABLE `vip_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `level` enum('bronze','silver','gold','platinum') NOT NULL,
  `stars_paid` int(11) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_date` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active_subs` (`is_active`, `end_date`),
  KEY `idx_end_date` (`end_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User profiles (Extended user data)
CREATE TABLE `user_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `bio` text DEFAULT NULL,
  `country` varchar(10) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Asia/Tashkent',
  `theme` enum('light','dark','auto') DEFAULT 'auto',
  `sound_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `show_online_status` tinyint(1) NOT NULL DEFAULT 1,
  `allow_friend_requests` tinyint(1) NOT NULL DEFAULT 1,
  `preferred_game_mode` enum('classic','blitz','bullet','rapid','marathon') DEFAULT 'classic',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- GAME MANAGEMENT TABLES
-- ================================================================

-- Game modes configuration
CREATE TABLE `game_modes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `time_control` int(11) NOT NULL COMMENT 'Base time in seconds',
  `increment` int(11) NOT NULL DEFAULT 0 COMMENT 'Increment per move in seconds',
  `is_rated` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default game modes
INSERT INTO `game_modes` (`name`, `display_name`, `time_control`, `increment`, `description`) VALUES
('classic', 'Klassik', 300, 5, '5 daqiqa + 5 soniya har yurishda'),
('blitz', 'Blitz', 180, 2, '3 daqiqa + 2 soniya har yurishda'),
('bullet', 'Bullet', 60, 1, '1 daqiqa + 1 soniya har yurishda'),
('rapid', 'Rapid', 600, 10, '10 daqiqa + 10 soniya har yurishda'),
('marathon', 'Marafon', 1800, 30, '30 daqiqa + 30 soniya har yurishda');

-- Games table
CREATE TABLE `games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_mode_id` int(11) NOT NULL,
  `player1_id` bigint(20) unsigned NOT NULL,
  `player2_id` bigint(20) unsigned DEFAULT NULL,
  `is_bot_game` tinyint(1) NOT NULL DEFAULT 0,
  `bot_level` enum('easy','medium','hard','expert') DEFAULT NULL,
  
  -- Game state
  `status` enum('waiting','active','finished','aborted') NOT NULL DEFAULT 'waiting',
  `result` enum('white_wins','black_wins','draw','aborted') DEFAULT NULL,
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `resignation` tinyint(1) NOT NULL DEFAULT 0,
  `timeout` tinyint(1) NOT NULL DEFAULT 0,
  
  -- Time control
  `player1_time` int(11) NOT NULL DEFAULT 0 COMMENT 'Remaining time in seconds',
  `player2_time` int(11) NOT NULL DEFAULT 0 COMMENT 'Remaining time in seconds',
  
  -- Game data
  `board_state` json DEFAULT NULL COMMENT 'Current board position',
  `moves_count` int(11) NOT NULL DEFAULT 0,
  `current_turn` enum('white','black') NOT NULL DEFAULT 'white',
  
  -- Rating changes
  `rated` tinyint(1) NOT NULL DEFAULT 1,
  `player1_rating_before` int(11) DEFAULT NULL,
  `player2_rating_before` int(11) DEFAULT NULL,
  `player1_rating_after` int(11) DEFAULT NULL,
  `player2_rating_after` int(11) DEFAULT NULL,
  `rating_change` int(11) DEFAULT NULL,
  
  -- Timestamps
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_player1` (`player1_id`),
  KEY `idx_player2` (`player2_id`),
  KEY `idx_winner` (`winner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_mode_created` (`game_mode_id`, `created_at` DESC),
  KEY `idx_finished_rating` (`finished_at`, `rated`),
  KEY `idx_active_games` (`status`, `updated_at`),
  FOREIGN KEY (`game_mode_id`) REFERENCES `game_modes`(`id`),
  FOREIGN KEY (`player1_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`player2_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`winner_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Game moves (Partitioned by month)
CREATE TABLE `game_moves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `move_number` int(11) NOT NULL,
  `player_id` bigint(20) unsigned NOT NULL,
  `from_square` varchar(2) NOT NULL COMMENT 'e.g. a3',
  `to_square` varchar(2) NOT NULL COMMENT 'e.g. b4',
  `captured_piece` tinyint(1) NOT NULL DEFAULT 0,
  `is_king` tinyint(1) NOT NULL DEFAULT 0,
  `move_notation` varchar(20) DEFAULT NULL,
  `time_spent` int(11) NOT NULL DEFAULT 0 COMMENT 'Time spent on this move in seconds',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_game_id` (`game_id`),
  KEY `idx_game_move_num` (`game_id`, `move_number`),
  KEY `idx_player_id` (`player_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`player_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) (
  PARTITION p202401 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
  PARTITION p202402 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
  PARTITION p202403 VALUES LESS THAN (UNIX_TIMESTAMP('2024-04-01')),
  PARTITION p202404 VALUES LESS THAN (UNIX_TIMESTAMP('2024-05-01')),
  PARTITION p202405 VALUES LESS THAN (UNIX_TIMESTAMP('2024-06-01')),
  PARTITION p202406 VALUES LESS THAN (UNIX_TIMESTAMP('2024-07-01')),
  PARTITION p202407 VALUES LESS THAN (UNIX_TIMESTAMP('2024-08-01')),
  PARTITION p202408 VALUES LESS THAN (UNIX_TIMESTAMP('2024-09-01')),
  PARTITION p202409 VALUES LESS THAN (UNIX_TIMESTAMP('2024-10-01')),
  PARTITION p202410 VALUES LESS THAN (UNIX_TIMESTAMP('2024-11-01')),
  PARTITION p202411 VALUES LESS THAN (UNIX_TIMESTAMP('2024-12-01')),
  PARTITION p202412 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Game spectators
CREATE TABLE `game_spectators` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_game_user` (`game_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SHOP AND ECONOMY TABLES
-- ================================================================

-- Shop categories
CREATE TABLE `shop_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert shop categories
INSERT INTO `shop_categories` (`name`, `display_name`, `icon`, `sort_order`) VALUES
('stones', 'Toshlar', '🪨', 1),
('boards', 'Doskalar', '🏁', 2),
('frames', 'Ramkalar', '🖼️', 3),
('effects', 'Effektlar', '✨', 4),
('sounds', 'Ovozlar', '🔊', 5),
('titles', 'Unvonlar', '🏆', 6),
('avatars', 'Avatarlar', '👤', 7),
('themes', 'Mavzular', '🎨', 8);

-- Shop items
CREATE TABLE `shop_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `animation_url` varchar(255) DEFAULT NULL,
  
  -- Pricing
  `price_type` enum('diamonds','coins','free','vip_only') NOT NULL DEFAULT 'diamonds',
  `price` bigint(20) NOT NULL DEFAULT 0,
  `original_price` bigint(20) DEFAULT NULL COMMENT 'For discounts',
  
  -- Rarity and availability
  `rarity` enum('common','uncommon','rare','epic','legendary','mythic') NOT NULL DEFAULT 'common',
  `is_limited` tinyint(1) NOT NULL DEFAULT 0,
  `available_until` timestamp NULL DEFAULT NULL,
  `max_purchases` int(11) DEFAULT NULL COMMENT 'Max purchases per user',
  
  -- Status
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_price_type` (`price_type`, `price` ASC),
  KEY `idx_rarity` (`rarity`),
  KEY `idx_active` (`is_active`),
  KEY `idx_featured` (`is_featured`, `sort_order`),
  KEY `idx_limited` (`is_limited`, `available_until`),
  FOREIGN KEY (`category_id`) REFERENCES `shop_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User inventory
CREATE TABLE `user_inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `item_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `is_equipped` tinyint(1) NOT NULL DEFAULT 0,
  `purchased_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_item` (`user_id`, `item_id`),
  KEY `idx_equipped` (`user_id`, `is_equipped`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Diamond packs (Telegram Stars pricing)
CREATE TABLE `diamond_packs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `stars_price` int(11) NOT NULL COMMENT 'Price in Telegram Stars',
  `diamonds_amount` int(11) NOT NULL,
  `bonus_diamonds` int(11) NOT NULL DEFAULT 0,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`),
  KEY `idx_popular` (`is_popular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert diamond packs
INSERT INTO `diamond_packs` (`name`, `description`, `stars_price`, `diamonds_amount`, `bonus_diamonds`, `sort_order`) VALUES
('💎 Boshlang\'ich paket', '100 olmos - boshlash uchun mukammal', 10, 100, 0, 1),
('💎 Mashhur paket', '500 olmos + 50 bonus', 45, 500, 50, 2),
('💎 Katta paket', '1200 olmos + 200 bonus', 100, 1200, 200, 3),
('💎 Premium paket', '2500 olmos + 500 bonus', 200, 2500, 500, 4),
('💎 Mega paket', '5000 olmos + 1000 bonus', 380, 5000, 1000, 5);

-- ================================================================
-- PAYMENT AND TRANSACTION TABLES
-- ================================================================

-- Payments (Telegram Stars)
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `telegram_payment_charge_id` varchar(255) NOT NULL,
  `telegram_provider_payment_charge_id` varchar(255) DEFAULT NULL,
  `payment_type` enum('diamond_pack','vip_subscription') NOT NULL,
  `item_id` int(11) DEFAULT NULL COMMENT 'diamond_pack_id or vip_level',
  `stars_amount` int(11) NOT NULL,
  `diamonds_amount` int(11) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_telegram_charge_id` (`telegram_payment_charge_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions (All diamond/coin movements) - Partitioned by month
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('diamonds','coins') NOT NULL,
  `amount` bigint(20) NOT NULL COMMENT 'Positive for credit, negative for debit',
  `balance_before` bigint(20) NOT NULL,
  `balance_after` bigint(20) NOT NULL,
  `reason` enum('purchase','game_reward','daily_bonus','vip_daily','shop_purchase','tournament_prize','referral','admin_adjustment','refund') NOT NULL,
  `reference_id` bigint(20) DEFAULT NULL COMMENT 'Related game_id, payment_id, etc.',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type_user` (`type`, `user_id`),
  KEY `idx_reason` (`reason`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) (
  PARTITION p202401 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
  PARTITION p202402 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
  PARTITION p202403 VALUES LESS THAN (UNIX_TIMESTAMP('2024-04-01')),
  PARTITION p202404 VALUES LESS THAN (UNIX_TIMESTAMP('2024-05-01')),
  PARTITION p202405 VALUES LESS THAN (UNIX_TIMESTAMP('2024-06-01')),
  PARTITION p202406 VALUES LESS THAN (UNIX_TIMESTAMP('2024-07-01')),
  PARTITION p202407 VALUES LESS THAN (UNIX_TIMESTAMP('2024-08-01')),
  PARTITION p202408 VALUES LESS THAN (UNIX_TIMESTAMP('2024-09-01')),
  PARTITION p202409 VALUES LESS THAN (UNIX_TIMESTAMP('2024-10-01')),
  PARTITION p202410 VALUES LESS THAN (UNIX_TIMESTAMP('2024-11-01')),
  PARTITION p202411 VALUES LESS THAN (UNIX_TIMESTAMP('2024-12-01')),
  PARTITION p202412 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;