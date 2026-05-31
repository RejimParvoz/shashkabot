-- Shashka Game - Advanced Indexes for High Performance
-- Optimized for 1M+ Users

USE `shashka_game`;

-- ================================================================
-- CRITICAL PERFORMANCE INDEXES
-- ================================================================

-- Users table optimizations
CREATE INDEX `idx_users_rating_league_active` ON `users` (`status`, `league`, `rating` DESC, `last_active` DESC);
CREATE INDEX `idx_users_online_rating` ON `users` (`is_online`, `rating` DESC) WHERE `is_online` = 1;
CREATE INDEX `idx_users_created_month` ON `users` (YEAR(`created_at`), MONTH(`created_at`));

-- Games table performance indexes
CREATE INDEX `idx_games_active_players` ON `games` (`status`, `player1_id`, `player2_id`, `updated_at`);
CREATE INDEX `idx_games_finished_today` ON `games` (`status`, DATE(`finished_at`)) WHERE `status` = 'finished';
CREATE INDEX `idx_games_user_recent` ON `games` (`player1_id`, `finished_at` DESC) WHERE `finished_at` IS NOT NULL;
CREATE INDEX `idx_games_user_recent_p2` ON `games` (`player2_id`, `finished_at` DESC) WHERE `finished_at` IS NOT NULL;

-- Game moves optimizations (already partitioned)
-- Partitions will have their own indexes automatically

-- Shop and inventory optimizations  
CREATE INDEX `idx_shop_items_category_price_active` ON `shop_items` (`category_id`, `price_type`, `price` ASC, `is_active`);
CREATE INDEX `idx_shop_items_featured_rarity` ON `shop_items` (`is_featured`, `rarity`, `sort_order`);
CREATE INDEX `idx_user_inventory_equipped` ON `user_inventory` (`user_id`, `is_equipped`) WHERE `is_equipped` = 1;

-- Transactions optimizations (already partitioned)
-- Add covering index for balance queries
CREATE INDEX `idx_transactions_user_type_balance` ON `transactions` (`user_id`, `type`, `created_at` DESC, `balance_after`);

-- Tournament optimizations
CREATE INDEX `idx_tournaments_registration_active` ON `tournaments` (`status`, `registration_start`, `registration_end`);
CREATE INDEX `idx_tournaments_type_status_start` ON `tournaments` (`type`, `status`, `start_time`);
CREATE INDEX `idx_tournament_players_tournament_position` ON `tournament_players` (`tournament_id`, `final_position` ASC) WHERE `final_position` IS NOT NULL;

-- Auction optimizations
CREATE INDEX `idx_auctions_status_end_bids` ON `auctions` (`status`, `end_time`, `total_bids` DESC);
CREATE INDEX `idx_auctions_category_end` ON `auctions` (`category`, `status`, `end_time`) WHERE `status` = 'active';
CREATE INDEX `idx_auction_bids_auction_amount` ON `auction_bids` (`auction_id`, `bid_amount` DESC, `created_at` DESC);

-- Social features optimizations
CREATE INDEX `idx_friends_user_status_updated` ON `friends` (`user_id`, `status`, `updated_at` DESC);
CREATE INDEX `idx_friends_bidirectional` ON `friends` (`user_id`, `friend_id`, `status`);

-- Chat optimizations (already partitioned)
CREATE INDEX `idx_chat_messages_type_ref_time` ON `chat_messages` (`chat_type`, `reference_id`, `created_at` DESC);
CREATE INDEX `idx_chat_messages_private` ON `chat_messages` (`sender_id`, `recipient_id`, `created_at` DESC) WHERE `chat_type` = 'private';

-- Clan optimizations
CREATE INDEX `idx_clans_public_rating_members` ON `clans` (`is_public`, `average_rating` DESC, `member_count` DESC);
CREATE INDEX `idx_clan_members_clan_contribution` ON `clan_members` (`clan_id`, `contribution_points` DESC, `role`);

-- Leaderboard optimizations
CREATE INDEX `idx_rating_history_user_recent` ON `rating_history` (`user_id`, `created_at` DESC);
CREATE INDEX `idx_arena_players_season_points` ON `arena_players` (`season_id`, `points` DESC, `games_played`);

-- Notification optimizations
CREATE INDEX `idx_notifications_user_unread_type` ON `notifications` (`user_id`, `is_read`, `type`, `created_at` DESC);
CREATE INDEX `idx_notifications_expires` ON `notifications` (`expires_at`) WHERE `expires_at` IS NOT NULL;

-- Battle Pass optimizations
CREATE INDEX `idx_user_battle_pass_season_level` ON `user_battle_pass` (`season_id`, `current_level` DESC, `current_xp` DESC);
CREATE INDEX `idx_battle_pass_levels_season_level` ON `battle_pass_levels` (`season_id`, `level`, `xp_required`);

-- Achievement optimizations
CREATE INDEX `idx_achievements_category_tier_active` ON `achievements` (`category`, `tier`, `is_active`, `sort_order`);
CREATE INDEX `idx_user_achievements_user_completed` ON `user_achievements` (`user_id`, `completed`, `completed_at` DESC);

-- Daily system optimizations
CREATE INDEX `idx_user_daily_quests_date_status` ON `user_daily_quests` (`user_id`, `date`, `completed`, `claimed`);
CREATE INDEX `idx_user_daily_bonuses_streak_date` ON `user_daily_bonuses` (`current_streak` DESC, `last_claim_date`);

-- VIP optimizations
CREATE INDEX `idx_vip_subscriptions_active_end` ON `vip_subscriptions` (`user_id`, `is_active`, `end_date`);
CREATE INDEX `idx_vip_subscriptions_expiring` ON `vip_subscriptions` (`is_active`, `end_date`) WHERE `is_active` = 1;

-- Payment optimizations
CREATE INDEX `idx_payments_user_status_created` ON `payments` (`user_id`, `status`, `created_at` DESC);
CREATE INDEX `idx_payments_status_completed` ON `payments` (`status`, `completed_at` DESC) WHERE `status` = 'completed';

-- ================================================================
-- COVERING INDEXES FOR COMPLEX QUERIES
-- ================================================================

-- User profile with stats (avoid additional lookups)
CREATE INDEX `idx_users_profile_stats` ON `users` (`id`, `telegram_id`, `username`, `first_name`, `rating`, `league`, `total_games`, `wins`, `losses`, `draws`, `diamonds`, `coins`, `status`, `is_online`, `last_active`);

-- Game history with player info
CREATE INDEX `idx_games_history_covering` ON `games` (`player1_id`, `status`, `finished_at` DESC, `id`, `game_mode_id`, `result`, `winner_id`, `moves_count`);

-- Shop items with full details
CREATE INDEX `idx_shop_items_display` ON `shop_items` (`category_id`, `is_active`, `sort_order`, `id`, `name`, `price_type`, `price`, `rarity`, `is_featured`);

-- Tournament leaderboard
CREATE INDEX `idx_tournament_leaderboard` ON `tournament_players` (`tournament_id`, `final_position` ASC, `user_id`, `score`, `wins`, `losses`);

-- Arena leaderboard
CREATE INDEX `idx_arena_leaderboard` ON `arena_players` (`season_id`, `points` DESC, `user_id`, `games_played`, `wins`, `current_streak`);

-- Clan member listing
CREATE INDEX `idx_clan_members_list` ON `clan_members` (`clan_id`, `role`, `contribution_points` DESC, `user_id`, `games_played`, `games_won`, `joined_at`);

-- ================================================================
-- FUNCTIONAL INDEXES FOR ADVANCED QUERIES
-- ================================================================

-- Date-based functional indexes
CREATE INDEX `idx_users_created_date` ON `users` (DATE(`created_at`));
CREATE INDEX `idx_games_finished_date` ON `games` (DATE(`finished_at`)) WHERE `finished_at` IS NOT NULL;
CREATE INDEX `idx_payments_completed_date` ON `payments` (DATE(`completed_at`)) WHERE `completed_at` IS NOT NULL;

-- JSON functional indexes (MySQL 5.7+)
-- Battle Pass claimed levels
CREATE INDEX `idx_user_battle_pass_claimed_count` ON `user_battle_pass` ((JSON_LENGTH(`claimed_levels`)));

-- Tournament prize distribution
CREATE INDEX `idx_tournaments_first_prize` ON `tournaments` ((JSON_EXTRACT(`prize_distribution`, '$."1"'))) WHERE `prize_distribution` IS NOT NULL;

-- ================================================================
-- PARTIAL INDEXES FOR SPECIFIC CONDITIONS
-- ================================================================

-- Active games only
CREATE INDEX `idx_active_games_only` ON `games` (`game_mode_id`, `created_at` DESC) WHERE `status` IN ('waiting', 'active');

-- Recent finished games
CREATE INDEX `idx_recent_finished_games` ON `games` (`finished_at` DESC, `rated`) WHERE `finished_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Online users only  
CREATE INDEX `idx_online_users_only` ON `users` (`last_active` DESC, `rating` DESC) WHERE `is_online` = 1 AND `status` = 'active';

-- Active auctions only
CREATE INDEX `idx_active_auctions_only` ON `auctions` (`end_time` ASC, `category`, `rarity`) WHERE `status` = 'active';

-- Pending notifications
CREATE INDEX `idx_pending_notifications` ON `notifications` (`user_id`, `created_at` DESC) WHERE `is_read` = 0;

-- ================================================================
-- COMPOSITE INDEXES FOR JOIN OPTIMIZATION  
-- ================================================================

-- User-Game joins
CREATE INDEX `idx_user_games_join` ON `games` (`player1_id`, `player2_id`, `status`, `finished_at`);

-- User-Inventory joins  
CREATE INDEX `idx_user_shop_join` ON `user_inventory` (`user_id`, `item_id`, `is_equipped`, `purchased_at`);

-- Tournament-Player joins
CREATE INDEX `idx_tournament_player_join` ON `tournament_players` (`tournament_id`, `user_id`, `final_position`, `eliminated`);

-- Clan-Member joins
CREATE INDEX `idx_clan_member_join` ON `clan_members` (`clan_id`, `user_id`, `role`, `joined_at`);

-- ================================================================
-- STATISTICS UPDATE (For Query Optimizer)
-- ================================================================

-- Analyze all tables to update statistics
ANALYZE TABLE `users`, `games`, `game_moves`, `shop_items`, `user_inventory`, 
             `tournaments`, `tournament_players`, `auctions`, `auction_bids`,
             `battle_pass_seasons`, `user_battle_pass`, `clans`, `clan_members`,
             `friends`, `chat_messages`, `notifications`, `achievements`,
             `user_achievements`, `daily_quests`, `user_daily_quests`,
             `transactions`, `payments`, `rating_history`;

-- ================================================================
-- INDEX MAINTENANCE COMMANDS
-- ================================================================

-- Commands for regular maintenance (run via cron)
-- OPTIMIZE TABLE `users`, `games`, `shop_items`, `tournaments`, `auctions`, `clans`;
-- CHECK TABLE `users`, `games`, `shop_items`;
-- REPAIR TABLE `table_name` (if needed);

-- Monitor index usage:
-- SELECT * FROM performance_schema.table_io_waits_summary_by_index_usage WHERE object_schema = 'shashka_game';

-- Check for unused indexes:
-- SELECT * FROM performance_schema.table_io_waits_summary_by_index_usage WHERE count_star = 0 AND object_schema = 'shashka_game';