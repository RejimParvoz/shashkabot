-- Shashka Game Database Schema - Part 3
-- Social, Clan, Daily System, and Administrative Tables

SET FOREIGN_KEY_CHECKS = 0;
USE `shashka_game`;

-- ================================================================
-- RATING AND LEADERBOARD TABLES
-- ================================================================

-- Rating history (Partitioned by month)
CREATE TABLE `rating_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `old_rating` int(11) NOT NULL,
  `new_rating` int(11) NOT NULL,
  `rating_change` int(11) NOT NULL,
  `game_id` bigint(20) unsigned DEFAULT NULL,
  `reason` enum('game_result','season_reset','admin_adjustment') NOT NULL DEFAULT 'game_result',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_game_id` (`game_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`game_id`) REFERENCES `games`(`id`)
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

-- League definitions
CREATE TABLE `leagues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `min_rating` int(11) NOT NULL,
  `max_rating` int(11) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_rating_range` (`min_rating`, `max_rating`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert leagues
INSERT INTO `leagues` (`name`, `display_name`, `min_rating`, `max_rating`, `icon`, `color`, `sort_order`) VALUES
('bronze', 'Bronza Liga', 0, 999, '🥉', '#CD7F32', 1),
('silver', 'Kumush Liga', 1000, 1999, '🥈', '#C0C0C0', 2),
('gold', 'Oltin Liga', 2000, 2999, '🥇', '#FFD700', 3),
('diamond', 'Olmos Liga', 3000, 3999, '💎', '#B9F2FF', 4),
('royal', 'Shohona Liga', 4000, 9999, '👑', '#800080', 5);

-- Rating seasons
CREATE TABLE `seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `season_number` int(11) NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `rating_reset_percentage` decimal(3,2) NOT NULL DEFAULT 0.10 COMMENT '10% reset',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_season_number` (`season_number`),
  KEY `idx_active_dates` (`is_active`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SOCIAL SYSTEM TABLES  
-- ================================================================

-- Friends system
CREATE TABLE `friends` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `friend_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_friend` (`user_id`, `friend_id`),
  KEY `idx_friend_user` (`friend_id`, `user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages (Global and private) - Partitioned by week
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL for global chat',
  `chat_type` enum('global','private','clan','game') NOT NULL DEFAULT 'global',
  `reference_id` bigint(20) unsigned DEFAULT NULL COMMENT 'clan_id or game_id',
  `message` text NOT NULL,
  `message_type` enum('text','emoji','sticker','system') NOT NULL DEFAULT 'text',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_chat_type_ref` (`chat_type`, `reference_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) (
  PARTITION p_week1 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-08')),
  PARTITION p_week2 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-15')),
  PARTITION p_week3 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-22')),
  PARTITION p_week4 VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-29')),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ================================================================
-- CLAN SYSTEM TABLES
-- ================================================================

-- Clans
CREATE TABLE `clans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `tag` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `leader_id` bigint(20) unsigned NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `experience` bigint(20) NOT NULL DEFAULT 0,
  `member_count` int(11) NOT NULL DEFAULT 1,
  `max_members` int(11) NOT NULL DEFAULT 10,
  `total_rating` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Sum of all member ratings',
  `average_rating` int(11) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `join_requirement_rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  UNIQUE KEY `idx_tag` (`tag`),
  KEY `idx_leader_id` (`leader_id`),
  KEY `idx_level_desc` (`level` DESC),
  KEY `idx_average_rating_desc` (`average_rating` DESC),
  KEY `idx_public_rating` (`is_public`, `average_rating` DESC),
  FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clan members
CREATE TABLE `clan_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clan_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('member','elder','leader') NOT NULL DEFAULT 'member',
  `contribution_points` bigint(20) NOT NULL DEFAULT 0,
  `games_played` int(11) NOT NULL DEFAULT 0,
  `games_won` int(11) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `promoted_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_clan_user` (`clan_id`, `user_id`),
  UNIQUE KEY `idx_user_clan` (`user_id`, `clan_id`),
  KEY `idx_role` (`role`),
  KEY `idx_contribution_desc` (`clan_id`, `contribution_points` DESC),
  FOREIGN KEY (`clan_id`) REFERENCES `clans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clan wars/battles
CREATE TABLE `clan_wars` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `clan1_id` bigint(20) unsigned NOT NULL,
  `clan2_id` bigint(20) unsigned NOT NULL,
  `status` enum('scheduled','active','finished') NOT NULL DEFAULT 'scheduled',
  `clan1_score` int(11) NOT NULL DEFAULT 0,
  `clan2_score` int(11) NOT NULL DEFAULT 0,
  `winner_clan_id` bigint(20) unsigned DEFAULT NULL,
  `start_time` timestamp NOT NULL,
  `end_time` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_clan1_id` (`clan1_id`),
  KEY `idx_clan2_id` (`clan2_id`),
  KEY `idx_status_time` (`status`, `start_time`),
  KEY `idx_winner` (`winner_clan_id`),
  FOREIGN KEY (`clan1_id`) REFERENCES `clans`(`id`),
  FOREIGN KEY (`clan2_id`) REFERENCES `clans`(`id`),
  FOREIGN KEY (`winner_clan_id`) REFERENCES `clans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- NOTIFICATIONS SYSTEM
-- ================================================================

-- Notifications
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('friend_request','game_invite','tournament_start','clan_invite','system_announcement','vip_expired','daily_bonus','achievement') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL COMMENT 'Additional data for action buttons etc',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `action_taken` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_user_unread` (`user_id`, `is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ACHIEVEMENTS SYSTEM
-- ================================================================

-- Achievements
CREATE TABLE `achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `category` enum('games','wins','social','tournaments','clan','collection') NOT NULL,
  `tier` enum('bronze','silver','gold','platinum') NOT NULL DEFAULT 'bronze',
  `icon` varchar(100) DEFAULT NULL,
  `requirement_type` enum('count','streak','percentage','specific') NOT NULL,
  `requirement_value` int(11) NOT NULL,
  `requirement_data` json DEFAULT NULL COMMENT 'Additional requirements',
  `reward_diamonds` int(11) DEFAULT NULL,
  `reward_coins` int(11) DEFAULT NULL,
  `reward_item_id` bigint(20) unsigned DEFAULT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`),
  KEY `idx_category_tier` (`category`, `tier`),
  KEY `idx_active_sort` (`is_active`, `sort_order`),
  FOREIGN KEY (`reward_item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements
CREATE TABLE `user_achievements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `claimed` tinyint(1) NOT NULL DEFAULT 0,
  `claimed_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_achievement` (`user_id`, `achievement_id`),
  KEY `idx_achievement_id` (`achievement_id`),
  KEY `idx_completed` (`completed`, `completed_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- DAILY SYSTEM TABLES
-- ================================================================

-- Daily quests/tasks
CREATE TABLE `daily_quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `type` enum('play_games','win_games','play_mode','defeat_bot','social_action') NOT NULL,
  `requirement_value` int(11) NOT NULL DEFAULT 1,
  `requirement_data` json DEFAULT NULL COMMENT 'Mode, bot level, etc.',
  `reward_diamonds` int(11) DEFAULT NULL,
  `reward_coins` int(11) DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'easy',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `weight` int(11) NOT NULL DEFAULT 1 COMMENT 'Selection probability',
  
  PRIMARY KEY (`id`),
  KEY `idx_active_weight` (`is_active`, `weight`),
  KEY `idx_type_difficulty` (`type`, `difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User daily quests (generated daily)
CREATE TABLE `user_daily_quests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `quest_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `claimed` tinyint(1) NOT NULL DEFAULT 0,
  `claimed_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_quest_date` (`user_id`, `quest_id`, `date`),
  KEY `idx_date_completed` (`date`, `completed`),
  KEY `idx_quest_id` (`quest_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quest_id`) REFERENCES `daily_quests`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily login bonuses
CREATE TABLE `daily_bonuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day` int(11) NOT NULL COMMENT '1-7',
  `diamonds` int(11) NOT NULL DEFAULT 0,
  `coins` int(11) NOT NULL DEFAULT 0,
  `special_item_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_day` (`day`),
  FOREIGN KEY (`special_item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert daily bonuses
INSERT INTO `daily_bonuses` (`day`, `diamonds`, `coins`) VALUES
(1, 5, 50),
(2, 10, 75),
(3, 15, 100),
(4, 20, 150),
(5, 30, 200),
(6, 40, 250),
(7, 50, 300);

-- User daily bonus tracking
CREATE TABLE `user_daily_bonuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `current_streak` int(11) NOT NULL DEFAULT 0,
  `max_streak` int(11) NOT NULL DEFAULT 0,
  `last_claim_date` date DEFAULT NULL,
  `total_claimed` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_streak` (`current_streak`),
  KEY `idx_last_claim` (`last_claim_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;