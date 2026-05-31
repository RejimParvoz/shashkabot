<?php
/**
 * Shashka Game - Quest Model
 * Handles daily quests generation, progress and reward claiming
 */

require_once __DIR__ . '/../core/Model.php';

class Quest extends Model {

    protected $table = 'daily_quests';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'type', 'requirement_value', 'requirement_data',
        'reward_diamonds', 'reward_coins', 'difficulty', 'is_active', 'weight'
    ];

    protected $casts = [
        'requirement_value' => 'integer',
        'reward_diamonds' => 'integer',
        'reward_coins' => 'integer',
        'weight' => 'integer',
        'is_active' => 'boolean',
        'requirement_data' => 'array'
    ];

    const MAX_DAILY_QUESTS = 3;
    const ALL_COMPLETE_BONUS_DIAMONDS = 5;

    /**
     * Generate daily quests for a user (3 per day)
     */
    public static function generateForUser($userId, $date = null) {
        $date = $date ?: date('Y-m-d');

        // Check if already generated
        $existing = self::$db->first(
            "SELECT COUNT(*) as count FROM user_daily_quests WHERE user_id = ? AND date = ?",
            [$userId, $date]
        );

        if ($existing && (int) $existing['count'] > 0) {
            return false; // Already generated
        }

        // Pick random active quests
        $quests = self::$db->get(
            "SELECT id FROM daily_quests WHERE is_active = 1 ORDER BY RAND() LIMIT ?",
            [self::MAX_DAILY_QUESTS]
        );

        foreach ($quests as $quest) {
            self::$db->insert(
                "INSERT INTO user_daily_quests (user_id, quest_id, date, progress, completed)
                 VALUES (?, ?, ?, 0, 0)",
                [$userId, $quest['id'], $date]
            );
        }

        self::$cache->delete("user_quests:{$userId}:{$date}");
        return count($quests);
    }

    /**
     * Get a user's quests for a date
     */
    public static function getUserQuests($userId, $date = null) {
        $date = $date ?: date('Y-m-d');
        $cacheKey = "user_quests:{$userId}:{$date}";
        $quests = self::$cache->get($cacheKey);

        if ($quests === null) {
            // Generate if missing
            self::generateForUser($userId, $date);

            $quests = self::$db->get(
                "SELECT udq.*, dq.name, dq.description, dq.type, dq.requirement_value,
                        dq.reward_diamonds, dq.reward_coins, dq.difficulty
                 FROM user_daily_quests udq
                 INNER JOIN daily_quests dq ON udq.quest_id = dq.id
                 WHERE udq.user_id = ? AND udq.date = ?",
                [$userId, $date]
            );
            self::$cache->set($cacheKey, $quests, 300);
        }

        return $quests;
    }

    /**
     * Update progress for quests of a given type
     */
    public static function updateProgress($userId, $type, $increment = 1) {
        $date = date('Y-m-d');

        $quests = self::$db->get(
            "SELECT udq.*, dq.requirement_value, dq.type
             FROM user_daily_quests udq
             INNER JOIN daily_quests dq ON udq.quest_id = dq.id
             WHERE udq.user_id = ? AND udq.date = ? AND dq.type = ? AND udq.completed = 0",
            [$userId, $date, $type]
        );

        foreach ($quests as $quest) {
            $newProgress = (int) $quest['progress'] + $increment;
            $completed = $newProgress >= (int) $quest['requirement_value'] ? 1 : 0;

            self::$db->update(
                "UPDATE user_daily_quests SET progress = ?, completed = ?,
                 completed_at = " . ($completed ? "NOW()" : "NULL") . "
                 WHERE id = ?",
                [$newProgress, $completed, $quest['id']]
            );
        }

        self::$cache->delete("user_quests:{$userId}:{$date}");
        return true;
    }

    /**
     * Claim a completed quest reward
     */
    public static function claimReward($userId, $userQuestId) {
        $quest = self::$db->first(
            "SELECT udq.*, dq.reward_diamonds, dq.reward_coins
             FROM user_daily_quests udq
             INNER JOIN daily_quests dq ON udq.quest_id = dq.id
             WHERE udq.id = ? AND udq.user_id = ?",
            [$userQuestId, $userId]
        );

        if (!$quest) {
            throw new Exception("Vazifa topilmadi");
        }
        if (!$quest['completed']) {
            throw new Exception("Vazifa hali bajarilmagan");
        }
        if ($quest['claimed']) {
            throw new Exception("Mukofot allaqachon olingan");
        }

        $user = User::find($userId);
        if ($quest['reward_diamonds']) {
            $user->addCurrency('diamonds', (int) $quest['reward_diamonds'], 'daily_quest', $userQuestId);
        }
        if ($quest['reward_coins']) {
            $user->addCurrency('coins', (int) $quest['reward_coins'], 'daily_quest', $userQuestId);
        }

        self::$db->update(
            "UPDATE user_daily_quests SET claimed = 1, claimed_at = NOW() WHERE id = ?",
            [$userQuestId]
        );

        // Check if all daily quests completed for bonus
        $allComplete = self::checkAllComplete($userId, $quest['date']);
        if ($allComplete) {
            $user->addCurrency('diamonds', self::ALL_COMPLETE_BONUS_DIAMONDS, 'daily_quest_bonus');
        }

        self::$cache->delete("user_quests:{$userId}:{$quest['date']}");
        return ['claimed' => true, 'all_complete_bonus' => $allComplete];
    }

    /**
     * Check if all daily quests for a date are claimed
     */
    private static function checkAllComplete($userId, $date) {
        $result = self::$db->first(
            "SELECT COUNT(*) as total, SUM(claimed) as claimed
             FROM user_daily_quests WHERE user_id = ? AND date = ?",
            [$userId, $date]
        );

        return $result && (int) $result['total'] > 0
            && (int) $result['total'] === (int) $result['claimed'];
    }
}
