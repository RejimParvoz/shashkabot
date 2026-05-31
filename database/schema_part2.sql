-- Shashka Game Database Schema - Part 2
-- Tournament, Arena, Social and System Tables

SET FOREIGN_KEY_CHECKS = 0;
USE `shashka_game`;

-- ================================================================
-- AUCTION SYSTEM TABLES
-- ================================================================

-- Auction items pool (for auto generation)
CREATE TABLE `auction_items_pool` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('stones','boards','frames','effects','sounds','titles','avatars','themes') NOT NULL,
  `rarity` enum('common','uncommon','rare','epic','legendary','mythic') NOT NULL DEFAULT 'common',
  `base_price` bigint(20) NOT NULL DEFAULT 100 COMMENT 'Starting bid price in diamonds',
  `image_url` varchar(255) DEFAULT NULL,
  `animation_keywords` text DEFAULT NULL COMMENT 'Keywords for auto animation selection',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT 'Generation probability weight',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_category_rarity` (`category`, `rarity`),
  KEY `idx_active_weight` (`is_active`, `weight`),
  KEY `idx_rarity` (`rarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Active auctions
CREATE TABLE `auctions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `item_description` text DEFAULT NULL,
  `category` enum('stones','boards','frames','effects','sounds','titles','avatars','themes') NOT NULL,
  `rarity` enum('common','uncommon','rare','epic','legendary','mythic') NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `animation_url` varchar(255) DEFAULT NULL,
  
  -- Auction details
  `starting_bid` bigint(20) NOT NULL,
  `current_bid` bigint(20) DEFAULT NULL,
  `bid_increment` bigint(20) NOT NULL DEFAULT 10,
  `current_bidder_id` bigint(20) unsigned DEFAULT NULL,
  `total_bids` int(11) NOT NULL DEFAULT 0,
  
  -- Timing
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NOT NULL,
  `extended_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of 5-minute extensions',
  
  -- Status
  `status` enum('active','ended','claimed') NOT NULL DEFAULT 'active',
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_status_end` (`status`, `end_time`),
  KEY `idx_current_bidder` (`current_bidder_id`),
  KEY `idx_winner` (`winner_id`),
  KEY `idx_category_rarity` (`category`, `rarity`),
  KEY `idx_end_time` (`end_time`),
  FOREIGN KEY (`current_bidder_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`winner_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auction bids
CREATE TABLE `auction_bids` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `auction_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `bid_amount` bigint(20) NOT NULL,
  `is_auto_bid` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_auction_id` (`auction_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`auction_id`) REFERENCES `auctions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auction auto-generation settings
CREATE TABLE `auction_auto_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `generation_time` time NOT NULL DEFAULT '00:00:00',
  `min_items` int(11) NOT NULL DEFAULT 5,
  `max_items` int(11) NOT NULL DEFAULT 15,
  `duration_hours` int(11) NOT NULL DEFAULT 24,
  `emergency_threshold` int(11) NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_generated` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default auto-generation settings
INSERT INTO `auction_auto_settings` (`generation_time`, `min_items`, `max_items`, `duration_hours`) VALUES
('00:00:00', 5, 15, 24);

-- ================================================================
-- BATTLE PASS SYSTEM TABLES
-- ================================================================

-- Battle Pass seasons
CREATE TABLE `battle_pass_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `max_level` int(11) NOT NULL DEFAULT 50,
  `premium_price_stars` int(11) NOT NULL DEFAULT 150,
  `xp_per_game` int(11) NOT NULL DEFAULT 10,
  `xp_per_win` int(11) NOT NULL DEFAULT 25,
  `xp_per_quest` int(11) NOT NULL DEFAULT 50,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_active_dates` (`is_active`, `start_date`, `end_date`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Battle Pass level rewards
CREATE TABLE `battle_pass_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `xp_required` int(11) NOT NULL,
  
  -- Free tier rewards
  `free_diamonds` int(11) DEFAULT NULL,
  `free_coins` int(11) DEFAULT NULL,
  `free_item_id` bigint(20) unsigned DEFAULT NULL,
  
  -- Premium tier rewards
  `premium_diamonds` int(11) DEFAULT NULL,
  `premium_coins` int(11) DEFAULT NULL,
  `premium_item_id` bigint(20) unsigned DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_season_level` (`season_id`, `level`),
  KEY `idx_free_item` (`free_item_id`),
  KEY `idx_premium_item` (`premium_item_id`),
  FOREIGN KEY (`season_id`) REFERENCES `battle_pass_seasons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`free_item_id`) REFERENCES `shop_items`(`id`),
  FOREIGN KEY (`premium_item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Battle Pass progress
CREATE TABLE `user_battle_pass` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `season_id` int(11) NOT NULL,
  `current_level` int(11) NOT NULL DEFAULT 1,
  `current_xp` int(11) NOT NULL DEFAULT 0,
  `has_premium` tinyint(1) NOT NULL DEFAULT 0,
  `premium_purchased_at` timestamp NULL DEFAULT NULL,
  `claimed_levels` json DEFAULT NULL COMMENT 'Array of claimed level numbers',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_season` (`user_id`, `season_id`),
  KEY `idx_season_id` (`season_id`),
  KEY `idx_level` (`current_level`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`season_id`) REFERENCES `battle_pass_seasons`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TOURNAMENT SYSTEM TABLES
-- ================================================================

-- Tournaments
CREATE TABLE `tournaments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('daily','weekly','monthly','seasonal','custom') NOT NULL,
  `game_mode_id` int(11) NOT NULL,
  
  -- Entry requirements
  `entry_fee_type` enum('coins','diamonds','free') NOT NULL DEFAULT 'coins',
  `entry_fee_amount` bigint(20) NOT NULL DEFAULT 0,
  `min_rating` int(11) DEFAULT NULL,
  `max_rating` int(11) DEFAULT NULL,
  `vip_only` tinyint(1) NOT NULL DEFAULT 0,
  
  -- Tournament settings
  `max_players` int(11) NOT NULL DEFAULT 64,
  `current_players` int(11) NOT NULL DEFAULT 0,
  `format` enum('single_elimination','double_elimination','round_robin','swiss') NOT NULL DEFAULT 'single_elimination',
  `rounds` int(11) DEFAULT NULL,
  
  -- Prizes
  `prize_pool_diamonds` bigint(20) NOT NULL DEFAULT 0,
  `prize_pool_coins` bigint(20) NOT NULL DEFAULT 0,
  `prize_distribution` json DEFAULT NULL COMMENT 'Prize distribution by position',
  
  -- Timing
  `registration_start` timestamp NOT NULL,
  `registration_end` timestamp NOT NULL,
  `start_time` timestamp NOT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  
  -- Status
  `status` enum('scheduled','registration','ready','active','finished','cancelled') NOT NULL DEFAULT 'scheduled',
  
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_type_start` (`type`, `start_time`),
  KEY `idx_status_start` (`status`, `start_time`),
  KEY `idx_game_mode` (`game_mode_id`),
  KEY `idx_registration` (`registration_start`, `registration_end`),
  FOREIGN KEY (`game_mode_id`) REFERENCES `game_modes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tournament participants
CREATE TABLE `tournament_players` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `seed` int(11) DEFAULT NULL COMMENT 'Tournament seeding position',
  `current_round` int(11) DEFAULT NULL,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `score` decimal(3,1) NOT NULL DEFAULT 0.0,
  `eliminated` tinyint(1) NOT NULL DEFAULT 0,
  `final_position` int(11) DEFAULT NULL,
  `prize_diamonds` bigint(20) NOT NULL DEFAULT 0,
  `prize_coins` bigint(20) NOT NULL DEFAULT 0,
  `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tournament_user` (`tournament_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_seed` (`tournament_id`, `seed`),
  KEY `idx_position` (`tournament_id`, `final_position`),
  FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tournament schedule (for automatic tournaments)
CREATE TABLE `tournament_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('daily','weekly','monthly') NOT NULL,
  `day_of_week` int(11) DEFAULT NULL COMMENT '1-7 for weekly (Monday=1)',
  `day_of_month` int(11) DEFAULT NULL COMMENT '1-31 for monthly',
  `time` time NOT NULL,
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Tashkent',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_created` timestamp NULL DEFAULT NULL,
  
  -- Tournament settings
  `name_template` varchar(100) NOT NULL,
  `game_mode_id` int(11) NOT NULL,
  `entry_fee_type` enum('coins','diamonds','free') NOT NULL DEFAULT 'coins',
  `entry_fee_amount` bigint(20) NOT NULL DEFAULT 0,
  `max_players` int(11) NOT NULL DEFAULT 64,
  `prize_pool_diamonds` bigint(20) NOT NULL DEFAULT 0,
  `registration_hours` int(11) NOT NULL DEFAULT 2 COMMENT 'Hours before start',
  
  PRIMARY KEY (`id`),
  KEY `idx_active_type` (`is_active`, `type`),
  FOREIGN KEY (`game_mode_id`) REFERENCES `game_modes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert tournament schedules
INSERT INTO `tournament_schedule` 
(`type`, `time`, `name_template`, `game_mode_id`, `entry_fee_type`, `entry_fee_amount`, `max_players`, `prize_pool_diamonds`, `registration_hours`) 
VALUES
('daily', '19:00:00', 'Kunlik Turnir - %date%', 1, 'coins', 50, 64, 500, 2),
('weekly', '20:00:00', 'Haftalik Turnir - %date%', 1, 'coins', 100, 128, 2000, 4),
('monthly', '21:00:00', 'Oylik Turnir - %date%', 1, 'diamonds', 50, 256, 5000, 24);

UPDATE `tournament_schedule` SET `day_of_week` = 5 WHERE `type` = 'weekly'; -- Friday
UPDATE `tournament_schedule` SET `day_of_month` = 1 WHERE `type` = 'monthly'; -- 1st day

-- ================================================================
-- ARENA SYSTEM TABLES
-- ================================================================

-- Arena seasons (weekly competitions)
CREATE TABLE `arena_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `season_number` int(11) NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  
  -- Point system
  `points_win` int(11) NOT NULL DEFAULT 50,
  `points_draw` int(11) NOT NULL DEFAULT 15,
  `points_loss` int(11) NOT NULL DEFAULT 5,
  
  -- Streak bonuses
  `streak_bonus_3` int(11) NOT NULL DEFAULT 5,
  `streak_bonus_5` int(11) NOT NULL DEFAULT 10,
  `streak_bonus_7` int(11) NOT NULL DEFAULT 15,
  `streak_bonus_10` int(11) NOT NULL DEFAULT 20,
  
  -- Rewards
  `first_place_diamonds` int(11) NOT NULL DEFAULT 500,
  `participation_diamonds` int(11) NOT NULL DEFAULT 10,
  `special_item_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Arena Stone or other exclusive item',
  
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_season_number` (`season_number`),
  KEY `idx_active_dates` (`is_active`, `start_date`, `end_date`),
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`special_item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arena player participation
CREATE TABLE `arena_players` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` int(11) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `games_played` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `current_streak` int(11) NOT NULL DEFAULT 0,
  `max_streak` int(11) NOT NULL DEFAULT 0,
  `final_rank` int(11) DEFAULT NULL,
  `rewards_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_season_user` (`season_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_points_desc` (`season_id`, `points` DESC),
  KEY `idx_final_rank` (`season_id`, `final_rank`),
  FOREIGN KEY (`season_id`) REFERENCES `arena_seasons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;