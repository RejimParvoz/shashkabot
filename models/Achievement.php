<?php
/**
 * Shashka Game - Achievement Model
 * Handles achievements, progress tracking and reward claiming
 */

require_once __DIR__ . '/../core/Model.php';

class Achievement extends Model {

    protected $table = 'achievements';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'category', 'tier', 'icon', 'requirement_type',
        'requirement_value', 'requirement_data', 'reward_diamonds', 'reward_coins',
        'reward_item_id', 'is_hidden', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'requirement_value' => 'integer',
        'reward_diamonds' => 'integer',
        'reward_coins' => 'integer',
        'reward_item_id' => 'integer',
        'is_hidden' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'requirement_data' => 'array'
    ];

    // Categories
    const CATEGORY_GAMES = 'games';
    const CATEGORY_WINS = 'wins';
    const CATEGORY_SOCIAL = 'social';
    const CATEGORY_TOURNAMENTS = 'tournaments';
    const CATEGORY_CLAN = 'clan';
    const CATEGORY_COLLECTION = 'collection';

    // Tiers
    const TIER_BRONZE = 'bronze';
    const TIER_SILVER = 'silver';
    const TIER_GOLD = 'gold';
    const TIER_PLATINUM = 'platinum';

    /**
     * Get all active achievements
     */
    public static function getAllActive() {
        $cacheKey = 'achievements_all';
        $achievements = self::$cache->get($cacheKey);

        if ($achievements === null) {
            $achievements = self::$db->get(
                "SELECT * FROM achievements WHERE is_active = 1 ORDER BY category, tier, sort_order"
            );
            self::$cache->set($cacheKey, $achievements, 3600);
        }

        return $achievements;
    }

    /**
     * Get a user's achievements with progress
     */
    public static function getUserAchievements($userId) {
        $sql = "SELECT a.*, COALESCE(ua.progress, 0) as progress,
                       COALESCE(ua.completed, 0) as completed,
                       COALESCE(ua.claimed, 0) as claimed,
                       ua.completed_at
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                WHERE a.is_active = 1
                ORDER BY a.category, a.tier, a.sort_order";

        return self::$db->get($sql, [$userId]);
    }

    /**
     * Update progress for a user across achievements of a category
     */
    public static function updateProgress($userId, $category, $currentValue) {
        $achievements = self::$db->get(
            "SELECT * FROM achievements WHERE category = ? AND is_active = 1",
            [$category]
        );

        $newlyCompleted = [];

        foreach ($achievements as $achievement) {
            $record = self::$db->first(
                "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
                [$userId, $achievement['id']]
            );

            // Skip already completed
            if ($record && $record['completed']) {
                continue;
            }

            $completed = $currentValue >= (int) $achievement['requirement_value'] ? 1 : 0;

            if ($record) {
                self::$db->update(
                    "UPDATE user_achievements SET progress = ?, completed = ?,
                     completed_at = " . ($completed ? "NOW()" : "NULL") . "
                     WHERE user_id = ? AND achievement_id = ?",
                    [$currentValue, $completed, $userId, $achievement['id']]
                );
            } else {
                self::$db->insert(
                    "INSERT INTO user_achievements (user_id, achievement_id, progress, completed, completed_at)
                     VALUES (?, ?, ?, ?, " . ($completed ? "NOW()" : "NULL") . ")",
                    [$userId, $achievement['id'], $currentValue, $completed]
                );
            }

            if ($completed) {
                $newlyCompleted[] = $achievement;
            }
        }

        return $newlyCompleted;
    }

    /**
     * Claim achievement reward
     */
    public function claim($userId) {
        $record = self::$db->first(
            "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
            [$userId, $this->id]
        );

        if (!$record || !$record['completed']) {
            throw new Exception("Yutuq hali bajarilmagan");
        }

        if ($record['claimed']) {
            throw new Exception("Mukofot allaqachon olingan");
        }

        $user = User::find($userId);
        if ($this->reward_diamonds) {
            $user->addCurrency('diamonds', $this->reward_diamonds, 'achievement', $this->id);
        }
        if ($this->reward_coins) {
            $user->addCurrency('coins', $this->reward_coins, 'achievement', $this->id);
        }
        if ($this->reward_item_id) {
            self::$db->insert(
                "INSERT IGNORE INTO user_inventory (user_id, item_id, quantity, purchased_at) VALUES (?, ?, 1, NOW())",
                [$userId, $this->reward_item_id]
            );
        }

        self::$db->update(
            "UPDATE user_achievements SET claimed = 1, claimed_at = NOW() WHERE user_id = ? AND achievement_id = ?",
            [$userId, $this->id]
        );

        return true;
    }

    /**
     * Get tier color for UI
     */
    public function getTierColor() {
        $colors = [
            self::TIER_BRONZE => '#CD7F32',
            self::TIER_SILVER => '#C0C0C0',
            self::TIER_GOLD => '#FFD700',
            self::TIER_PLATINUM => '#E5E4E2'
        ];
        return $colors[$this->tier] ?? '#CD7F32';
    }
}
