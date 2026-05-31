-- Shashka Game Database Schema - Part 4
-- Holiday System, Admin Panel, Analytics and System Tables

SET FOREIGN_KEY_CHECKS = 0;
USE `shashka_game`;

-- ================================================================
-- HOLIDAY AND SPECIAL EVENTS SYSTEM
-- ================================================================

-- Holidays configuration
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('fixed','lunar_calculated','custom') NOT NULL DEFAULT 'fixed',
  `start_date` varchar(10) DEFAULT NULL COMMENT 'MM-DD format for fixed dates',
  `end_date` varchar(10) DEFAULT NULL COMMENT 'MM-DD format for fixed dates',
  `duration_days` int(11) NOT NULL DEFAULT 1,
  `reward_multiplier` decimal(3,2) NOT NULL DEFAULT 1.00,
  `special_rewards` json DEFAULT NULL COMMENT 'Extra rewards during holiday',
  `background_url` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert holidays
INSERT INTO `holidays` (`name`, `type`, `start_date`, `end_date`, `duration_days`, `reward_multiplier`, `description`) VALUES
('Yangi Yil', 'fixed', '12-31', '01-02', 3, 2.00, 'Yangi yil bayramlari - 2x mukofot'),
('Navro\'z', 'fixed', '03-20', '03-22', 3, 2.00, 'Navro\'z bayrami - 2x mukofot'),
('Mustaqillik kuni', 'fixed', '08-31', '09-02', 3, 3.00, 'O\'zbekiston Mustaqilligi - 3x mukofot'),
('Ramazon Hayiti', 'lunar_calculated', NULL, NULL, 3, 2.50, 'Ramazon Hayiti - 2.5x mukofot'),
('Qurbon Hayiti', 'lunar_calculated', NULL, NULL, 3, 2.50, 'Qurbon Hayiti - 2.5x mukofot');

-- Active holiday periods (calculated/custom dates)
CREATE TABLE `active_holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `holiday_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `actual_start_date` date NOT NULL,
  `actual_end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_holiday_year` (`holiday_id`, `year`),
  KEY `idx_dates_active` (`actual_start_date`, `actual_end_date`, `is_active`),
  FOREIGN KEY (`holiday_id`) REFERENCES `holidays`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Holiday rewards claimed by users
CREATE TABLE `user_holiday_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `holiday_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `rewards_claimed` json DEFAULT NULL COMMENT 'Array of claimed reward IDs',
  `total_bonus_diamonds` int(11) NOT NULL DEFAULT 0,
  `total_bonus_coins` int(11) NOT NULL DEFAULT 0,
  `last_claim_date` date DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_holiday_year` (`user_id`, `holiday_id`, `year`),
  KEY `idx_holiday_year` (`holiday_id`, `year`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`holiday_id`) REFERENCES `holidays`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- STORY MODE AND PUZZLES
-- ================================================================

-- Story mode chapters
CREATE TABLE `story_chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard','expert','master') NOT NULL DEFAULT 'easy',
  `boss_name` varchar(100) DEFAULT NULL,
  `boss_rating` int(11) NOT NULL DEFAULT 1000,
  `unlock_requirement` json DEFAULT NULL COMMENT 'Previous chapter, rating, etc.',
  
  -- Rewards
  `reward_diamonds` int(11) DEFAULT NULL,
  `reward_coins` int(11) DEFAULT NULL,
  `reward_item_id` bigint(20) unsigned DEFAULT NULL,
  
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_chapter_number` (`chapter_number`),
  KEY `idx_active_sort` (`is_active`, `sort_order`),
  FOREIGN KEY (`reward_item_id`) REFERENCES `shop_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User story progress
CREATE TABLE `user_story_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `best_moves` int(11) DEFAULT NULL,
  `rewards_claimed` tinyint(1) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_chapter` (`user_id`, `chapter_id`),
  KEY `idx_chapter_id` (`chapter_id`),
  KEY `idx_completed` (`completed`, `completed_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`chapter_id`) REFERENCES `story_chapters`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily puzzles
CREATE TABLE `puzzles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `board_state` json NOT NULL COMMENT 'Initial puzzle position',
  `solution_moves` json NOT NULL COMMENT 'Correct solution sequence',
  `max_moves` int(11) NOT NULL DEFAULT 10,
  `reward_diamonds` int(11) DEFAULT 5,
  `reward_coins` int(11) DEFAULT 50,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date` (`date`),
  KEY `idx_active_difficulty` (`is_active`, `difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User puzzle attempts
CREATE TABLE `user_puzzles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `puzzle_id` bigint(20) unsigned NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `solved` tinyint(1) NOT NULL DEFAULT 0,
  `solved_at` timestamp NULL DEFAULT NULL,
  `moves_used` int(11) DEFAULT NULL,
  `rewards_claimed` tinyint(1) NOT NULL DEFAULT 0,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_puzzle` (`user_id`, `puzzle_id`),
  KEY `idx_puzzle_id` (`puzzle_id`),
  KEY `idx_solved` (`solved`, `solved_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`puzzle_id`) REFERENCES `puzzles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PLAYER HOUSES AND TROPHIES
-- ================================================================

-- Player houses (Trophy collection)
CREATE TABLE `player_houses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `house_name` varchar(100) DEFAULT 'Mening uyim',
  `theme` enum('classic','modern','royal','medieval','futuristic') NOT NULL DEFAULT 'classic',
  `trophy_display_limit` int(11) NOT NULL DEFAULT 20,
  `last_visited` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `visit_count` bigint(20) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_public_visited` (`is_public`, `last_visited` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trophy collection
CREATE TABLE `user_trophies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `trophy_type` enum('tournament_win','achievement','milestone','special') NOT NULL,
  `trophy_name` varchar(100) NOT NULL,
  `trophy_description` text DEFAULT NULL,
  `trophy_icon` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL COMMENT 'tournament_id, achievement_id, etc.',
  `earned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_displayed` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`, `trophy_type`),
  KEY `idx_earned_at` (`earned_at` DESC),
  KEY `idx_displayed` (`user_id`, `is_displayed`, `display_order`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- COACHING AND MENTORSHIP SYSTEM
-- ================================================================

-- Coaches/Mentors
CREATE TABLE `coaches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `specialization` enum('beginner','intermediate','advanced','all_levels') NOT NULL DEFAULT 'all_levels',
  `min_student_rating` int(11) DEFAULT NULL,
  `max_student_rating` int(11) DEFAULT NULL,
  `hourly_rate_diamonds` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `total_students` int(11) NOT NULL DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_active_specialization` (`is_active`, `specialization`),
  KEY `idx_rating_desc` (`average_rating` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coach-Student relationships
CREATE TABLE `coaching_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coach_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `session_type` enum('analysis','practice','lesson') NOT NULL DEFAULT 'lesson',
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `cost_diamonds` int(11) NOT NULL DEFAULT 0,
  `status` enum('scheduled','active','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_at` timestamp NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `student_rating` int(11) DEFAULT NULL COMMENT '1-5 stars',
  `student_feedback` text DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_coach_id` (`coach_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status_scheduled` (`status`, `scheduled_at`),
  FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ADMIN AND SYSTEM TABLES
-- ================================================================

-- Admin users
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','moderator','support') NOT NULL DEFAULT 'moderator',
  `permissions` json DEFAULT NULL COMMENT 'Array of specific permissions',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_active_role` (`is_active`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin activity logs (Partitioned by month)
CREATE TABLE `admin_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL COMMENT 'user, game, tournament, etc.',
  `resource_id` bigint(20) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`, `created_at`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_resource` (`resource_type`, `resource_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) (
  PARTITION p202401 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
  PARTITION p202402 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
  PARTITION p202403 VALUES LESS THAN (UNIX_TIMESTAMP('2024-04-01')),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- User reports and moderation
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` bigint(20) unsigned NOT NULL,
  `reported_user_id` bigint(20) unsigned NOT NULL,
  `report_type` enum('cheating','inappropriate_behavior','harassment','spam','other') NOT NULL,
  `description` text NOT NULL,
  `evidence_urls` json DEFAULT NULL COMMENT 'Screenshots, game IDs, etc.',
  `status` enum('pending','investigating','resolved','dismissed') NOT NULL DEFAULT 'pending',
  `assigned_admin_id` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `action_taken` enum('none','warning','temporary_ban','permanent_ban','other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_reporter_id` (`reporter_id`),
  KEY `idx_reported_user_id` (`reported_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_admin` (`assigned_admin_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`reported_user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`assigned_admin_id`) REFERENCES `admin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','integer','boolean','json','float') NOT NULL DEFAULT 'string',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Can be read by API',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key` (`key`),
  KEY `idx_category` (`category`),
  KEY `idx_public` (`is_public`),
  FOREIGN KEY (`updated_by`) REFERENCES `admin_users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ANALYTICS AND MONITORING TABLES
-- ================================================================

-- Error logs
CREATE TABLE `error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
  `message` text NOT NULL,
  `context` json DEFAULT NULL,
  `extra` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security logs
CREATE TABLE `security_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `violation_type` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at` DESC),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting tracking
CREATE TABLE `rate_limits` (
  `id` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `reset_time` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_reset_time` (`reset_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance monitoring
CREATE TABLE `performance_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) NOT NULL,
  `value` decimal(15,6) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `tags` json DEFAULT NULL COMMENT 'Additional metadata',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slow query log
CREATE TABLE `slow_query_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `query_sql` text NOT NULL,
  `execution_time` decimal(10,6) NOT NULL,
  `bindings` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_execution_time` (`execution_time` DESC),
  KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache table (fallback for database cache driver)
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  `expiration` int(11) NOT NULL,
  
  PRIMARY KEY (`key`),
  KEY `idx_expiration` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session storage
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_activity` (`last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;