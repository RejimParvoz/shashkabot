<?php
/**
 * Shashka Game - User Model
 * Handles user data, authentication, and game statistics
 */

require_once __DIR__ . '/../core/Model.php';

class User extends Model {
    
    protected $table = 'users';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'telegram_id', 'username', 'first_name', 'last_name', 'photo_url',
        'language_code', 'rating', 'league', 'total_games', 'wins', 'losses', 
        'draws', 'win_streak', 'max_win_streak', 'diamonds', 'coins', 'status'
    ];
    
    protected $hidden = ['created_at', 'updated_at'];
    
    protected $casts = [
        'telegram_id' => 'integer',
        'rating' => 'integer',
        'total_games' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'draws' => 'integer',
        'win_streak' => 'integer',
        'max_win_streak' => 'integer',
        'diamonds' => 'integer',
        'coins' => 'integer',
        'is_online' => 'boolean'
    ];
    
    // League definitions
    const LEAGUES = [
        'bronze' => ['min' => 0, 'max' => 999, 'name' => 'Bronza Liga'],
        'silver' => ['min' => 1000, 'max' => 1999, 'name' => 'Kumush Liga'],
        'gold' => ['min' => 2000, 'max' => 2999, 'name' => 'Oltin Liga'],
        'diamond' => ['min' => 3000, 'max' => 3999, 'name' => 'Olmos Liga'],
        'royal' => ['min' => 4000, 'max' => 9999, 'name' => 'Shohona Liga']
    ];
    
    /**
     * Find user by Telegram ID
     */
    public static function findByTelegramId($telegramId) {
        return static::findBy('telegram_id', $telegramId);
    }
    
    /**
     * Create user from Telegram data
     */
    public static function createFromTelegram($telegramData) {
        $userData = [
            'telegram_id' => $telegramData['id'],
            'username' => $telegramData['username'] ?? null,
            'first_name' => $telegramData['first_name'] ?? '',
            'last_name' => $telegramData['last_name'] ?? '',
            'photo_url' => $telegramData['photo_url'] ?? null,
            'language_code' => $telegramData['language_code'] ?? 'uz',
            'rating' => 1000,
            'league' => 'bronze',
            'diamonds' => 0,
            'coins' => 0,
            'status' => 'active'
        ];
        
        return static::create($userData);
    }
    
    /**
     * Get user's full name
     */
    public function getFullNameAttribute($value = null) {
        $firstName = $this->first_name ?? '';
        $lastName = $this->last_name ?? '';
        return trim($firstName . ' ' . $lastName);
    }
    
    /**
     * Get user's display name (full name or username)
     */
    public function getDisplayNameAttribute($value = null) {
        $fullName = $this->getFullNameAttribute();
        return !empty($fullName) ? $fullName : ($this->username ?? 'User#' . $this->id);
    }
    
    /**
     * Get win rate percentage
     */
    public function getWinRateAttribute($value = null) {
        if ($this->total_games == 0) {
            return 0;
        }
        return round(($this->wins / $this->total_games) * 100, 1);
    }
    
    /**
     * Get league information
     */
    public function getLeagueInfoAttribute($value = null) {
        return self::LEAGUES[$this->league] ?? self::LEAGUES['bronze'];
    }
    
    /**
     * Check if user has VIP subscription
     */
    public function hasActiveVip() {
        $cacheKey = "user_vip:{$this->id}";
        $vipInfo = self::$cache->get($cacheKey);
        
        if ($vipInfo === null) {
            $sql = "SELECT level, end_date FROM vip_subscriptions WHERE user_id = ? AND is_active = 1 AND end_date > NOW() ORDER BY end_date DESC LIMIT 1";
            $vipInfo = self::$db->first($sql, [$this->id]);
            
            // Cache for 5 minutes
            self::$cache->set($cacheKey, $vipInfo ?: false, 300);
        }
        
        return $vipInfo !== false ? $vipInfo : null;
    }
    
    /**
     * Get user's VIP level
     */
    public function getVipLevel() {
        $vipInfo = $this->hasActiveVip();
        return $vipInfo ? $vipInfo['level'] : null;
    }
    
    /**
     * Update user rating after game
     */
    public function updateRating($newRating, $gameId = null) {
        $oldRating = $this->rating;
        $ratingChange = $newRating - $oldRating;
        
        // Update rating and league
        $this->rating = $newRating;
        $this->league = $this->calculateLeague($newRating);
        
        // Save user
        $this->save();
        
        // Log rating change
        $sql = "INSERT INTO rating_history (user_id, old_rating, new_rating, rating_change, game_id, reason, created_at) VALUES (?, ?, ?, ?, ?, 'game_result', NOW())";
        self::$db->insert($sql, [$this->id, $oldRating, $newRating, $ratingChange, $gameId]);
        
        // Clear related cache
        $this->clearRatingCache();
        
        return $ratingChange;
    }
    
    /**
     * Calculate league based on rating
     */
    private function calculateLeague($rating) {
        foreach (self::LEAGUES as $league => $info) {
            if ($rating >= $info['min'] && $rating <= $info['max']) {
                return $league;
            }
        }
        return 'royal'; // For ratings above 4000
    }
    
    /**
     * Update game statistics
     */
    public function updateGameStats($result) {
        $this->total_games++;
        
        switch ($result) {
            case 'win':
                $this->wins++;
                $this->win_streak++;
                if ($this->win_streak > $this->max_win_streak) {
                    $this->max_win_streak = $this->win_streak;
                }
                break;
            case 'loss':
                $this->losses++;
                $this->win_streak = 0;
                break;
            case 'draw':
                $this->draws++;
                $this->win_streak = 0;
                break;
        }
        
        $this->save();
    }
    
    /**
     * Add currency (diamonds or coins)
     */
    public function addCurrency($type, $amount, $reason, $referenceId = null) {
        if (!in_array($type, ['diamonds', 'coins']) || $amount <= 0) {
            return false;
        }
        
        return self::transaction(function() use ($type, $amount, $reason, $referenceId) {
            $balanceBefore = $this->$type;
            $this->$type += $amount;
            $balanceAfter = $this->$type;
            
            // Save user
            $this->save();
            
            // Log transaction
            $sql = "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reason, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            self::$db->insert($sql, [$this->id, $type, $amount, $balanceBefore, $balanceAfter, $reason, $referenceId]);
            
            return true;
        });
    }
    
    /**
     * Subtract currency (diamonds or coins)
     */
    public function subtractCurrency($type, $amount, $reason, $referenceId = null) {
        if (!in_array($type, ['diamonds', 'coins']) || $amount <= 0) {
            return false;
        }
        
        if ($this->$type < $amount) {
            return false; // Insufficient balance
        }
        
        return self::transaction(function() use ($type, $amount, $reason, $referenceId) {
            $balanceBefore = $this->$type;
            $this->$type -= $amount;
            $balanceAfter = $this->$type;
            
            // Save user
            $this->save();
            
            // Log transaction
            $sql = "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reason, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            self::$db->insert($sql, [$this->id, $type, -$amount, $balanceBefore, $balanceAfter, $reason, $referenceId]);
            
            return true;
        });
    }
    
    /**
     * Set user online status
     */
    public function setOnline() {
        $sql = "UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?";
        self::$db->update($sql, [$this->id]);
        
        $this->is_online = true;
        $this->last_active = date('Y-m-d H:i:s');
    }
    
    /**
     * Set user offline status
     */
    public function setOffline() {
        $sql = "UPDATE users SET is_online = 0 WHERE id = ?";
        self::$db->update($sql, [$this->id]);
        
        $this->is_online = false;
    }
    
    /**
     * Get user's friends
     */
    public function getFriends($status = 'accepted') {
        $cacheKey = "user_friends:{$this->id}:{$status}";
        $friends = self::$cache->get($cacheKey);
        
        if ($friends === null) {
            $sql = "SELECT u.* FROM users u 
                    INNER JOIN friends f ON (
                        (f.user_id = ? AND f.friend_id = u.id) OR 
                        (f.friend_id = ? AND f.user_id = u.id)
                    ) 
                    WHERE f.status = ?";
            
            $friendData = self::$db->get($sql, [$this->id, $this->id, $status]);
            
            $friends = array_map(function($data) {
                return new User($data);
            }, $friendData);
            
            // Cache for 10 minutes
            self::$cache->set($cacheKey, $friends, 600);
        }
        
        return $friends;
    }
    
    /**
     * Send friend request
     */
    public function sendFriendRequest($friendId) {
        // Check if already friends or request exists
        $sql = "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
        $existing = self::$db->first($sql, [$this->id, $friendId, $friendId, $this->id]);
        
        if ($existing) {
            return false; // Already exists
        }
        
        // Create friend request
        $sql = "INSERT INTO friends (user_id, friend_id, status, requested_by, created_at) VALUES (?, ?, 'pending', ?, NOW())";
        return self::$db->insert($sql, [$this->id, $friendId, $this->id]);
    }
    
    /**
     * Accept friend request
     */
    public function acceptFriendRequest($friendId) {
        $sql = "UPDATE friends SET status = 'accepted', updated_at = NOW() WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
        $affected = self::$db->update($sql, [$this->id, $friendId, $friendId, $this->id]);
        
        if ($affected > 0) {
            // Clear friends cache for both users
            self::$cache->delete("user_friends:{$this->id}:accepted");
            self::$cache->delete("user_friends:{$friendId}:accepted");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user's inventory
     */
    public function getInventory($categoryId = null) {
        $cacheKey = "user_inventory:{$this->id}" . ($categoryId ? ":{$categoryId}" : "");
        $inventory = self::$cache->get($cacheKey);
        
        if ($inventory === null) {
            $sql = "SELECT ui.*, si.name, si.category_id, si.rarity, si.image_url 
                    FROM user_inventory ui 
                    INNER JOIN shop_items si ON ui.item_id = si.id 
                    WHERE ui.user_id = ?";
            $params = [$this->id];
            
            if ($categoryId) {
                $sql .= " AND si.category_id = ?";
                $params[] = $categoryId;
            }
            
            $sql .= " ORDER BY ui.is_equipped DESC, si.rarity DESC, ui.purchased_at DESC";
            
            $inventory = self::$db->get($sql, $params);
            
            // Cache for 30 minutes
            self::$cache->set($cacheKey, $inventory, 1800);
        }
        
        return $inventory;
    }
    
    /**
     * Get equipped items
     */
    public function getEquippedItems() {
        $cacheKey = "user_equipped:{$this->id}";
        $equipped = self::$cache->get($cacheKey);
        
        if ($equipped === null) {
            $sql = "SELECT ui.*, si.name, si.category_id, si.image_url, si.animation_url 
                    FROM user_inventory ui 
                    INNER JOIN shop_items si ON ui.item_id = si.id 
                    WHERE ui.user_id = ? AND ui.is_equipped = 1";
            
            $equipped = self::$db->get($sql, [$this->id]);
            
            // Cache for 1 hour
            self::$cache->set($cacheKey, $equipped, 3600);
        }
        
        return $equipped;
    }
    
    /**
     * Get recent games
     */
    public function getRecentGames($limit = 10) {
        $cacheKey = "user_recent_games:{$this->id}:{$limit}";
        $games = self::$cache->get($cacheKey);
        
        if ($games === null) {
            $sql = "SELECT g.*, 
                           p1.username as player1_username, p1.first_name as player1_first_name,
                           p2.username as player2_username, p2.first_name as player2_first_name,
                           gm.display_name as mode_name
                    FROM games g
                    LEFT JOIN users p1 ON g.player1_id = p1.id
                    LEFT JOIN users p2 ON g.player2_id = p2.id
                    LEFT JOIN game_modes gm ON g.game_mode_id = gm.id
                    WHERE (g.player1_id = ? OR g.player2_id = ?) 
                    AND g.status = 'finished'
                    ORDER BY g.finished_at DESC 
                    LIMIT ?";
            
            $games = self::$db->get($sql, [$this->id, $this->id, $limit]);
            
            // Cache for 5 minutes
            self::$cache->set($cacheKey, $games, 300);
        }
        
        return $games;
    }
    
    /**
     * Get leaderboard position
     */
    public function getLeaderboardPosition() {
        $cacheKey = "user_leaderboard_position:{$this->id}";
        $position = self::$cache->get($cacheKey);
        
        if ($position === null) {
            $sql = "SELECT COUNT(*) + 1 as position FROM users WHERE rating > ? AND status = 'active'";
            $result = self::$db->first($sql, [$this->rating]);
            $position = $result ? $result['position'] : 1;
            
            // Cache for 10 minutes
            self::$cache->set($cacheKey, $position, 600);
        }
        
        return $position;
    }
    
    /**
     * Clear rating-related cache
     */
    private function clearRatingCache() {
        $keys = [
            "user_leaderboard_position:{$this->id}",
            "leaderboard_global",
            "leaderboard_{$this->league}"
        ];
        
        foreach ($keys as $key) {
            self::$cache->delete($key);
        }
    }
    
    /**
     * Get user statistics for profile
     */
    public function getProfileStats() {
        return [
            'rating' => $this->rating,
            'league' => $this->getLeagueInfoAttribute(),
            'total_games' => $this->total_games,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'draws' => $this->draws,
            'win_rate' => $this->getWinRateAttribute(),
            'win_streak' => $this->win_streak,
            'max_win_streak' => $this->max_win_streak,
            'diamonds' => $this->diamonds,
            'coins' => $this->coins,
            'leaderboard_position' => $this->getLeaderboardPosition(),
            'vip_level' => $this->getVipLevel(),
            'is_online' => $this->is_online
        ];
    }
    
    /**
     * Ban user
     */
    public function ban($reason = null, $adminId = null) {
        $this->status = 'banned';
        $result = $this->save();
        
        if ($result && $adminId) {
            // Log admin action
            $sql = "INSERT INTO admin_logs (admin_id, action, resource_type, resource_id, details, created_at) VALUES (?, 'ban_user', 'user', ?, ?, NOW())";
            self::$db->insert($sql, [$adminId, $this->id, json_encode(['reason' => $reason])]);
        }
        
        return $result;
    }
    
    /**
     * Unban user
     */
    public function unban($adminId = null) {
        $this->status = 'active';
        $result = $this->save();
        
        if ($result && $adminId) {
            // Log admin action
            $sql = "INSERT INTO admin_logs (admin_id, action, resource_type, resource_id, created_at) VALUES (?, 'unban_user', 'user', ?, NOW())";
            self::$db->insert($sql, [$adminId, $this->id]);
        }
        
        return $result;
    }
}