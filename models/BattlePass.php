<?php
/**
 * Shashka Game - Battle Pass Model
 * Handles seasons, XP, levels and free/premium reward tracks
 */

require_once __DIR__ . '/../core/Model.php';

class BattlePass extends Model {

    protected $table = 'battle_pass_seasons';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'start_date', 'end_date', 'max_level',
        'premium_price_stars', 'xp_per_game', 'xp_per_win', 'xp_per_quest', 'is_active'
    ];

    protected $casts = [
        'max_level' => 'integer',
        'premium_price_stars' => 'integer',
        'xp_per_game' => 'integer',
        'xp_per_win' => 'integer',
        'xp_per_quest' => 'integer',
        'is_active' => 'boolean'
    ];

    const XP_PER_GAME = 10;
    const XP_PER_WIN = 25;
    const XP_PER_QUEST = 50;

    /**
     * Get the currently active season
     */
    public static function getActiveSeason() {
        $cacheKey = 'battle_pass_active';
        $season = self::$cache->get($cacheKey);

        if ($season === null) {
            $sql = "SELECT * FROM battle_pass_seasons
                    WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
                    ORDER BY id DESC LIMIT 1";
            $season = self::$db->first($sql);
            self::$cache->set($cacheKey, $season ?: false, 600);
        }

        return $season !== false ? $season : null;
    }

    /**
     * Get or create a user's battle pass progress for this season
     */
    public function getUserProgress($userId) {
        $progress = self::$db->first(
            "SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?",
            [$userId, $this->id]
        );

        if (!$progress) {
            self::$db->insert(
                "INSERT INTO user_battle_pass (user_id, season_id, current_level, current_xp, claimed_levels, created_at)
                 VALUES (?, ?, 1, 0, '[]', NOW())",
                [$userId, $this->id]
            );
            $progress = self::$db->first(
                "SELECT * FROM user_battle_pass WHERE user_id = ? AND season_id = ?",
                [$userId, $this->id]
            );
        }

        return $progress;
    }

    /**
     * Add XP to a user and handle level ups
     */
    public function addXp($userId, $xpAmount) {
        $progress = $this->getUserProgress($userId);

        $currentXp = (int) $progress['current_xp'] + $xpAmount;
        $currentLevel = (int) $progress['current_level'];

        // Get XP requirements per level
        $levels = $this->getLevels();
        $xpMap = [];
        foreach ($levels as $lvl) {
            $xpMap[(int) $lvl['level']] = (int) $lvl['xp_required'];
        }

        // Level up while enough XP and below max
        while ($currentLevel < $this->max_level
            && isset($xpMap[$currentLevel + 1])
            && $currentXp >= $xpMap[$currentLevel + 1]) {
            $currentLevel++;
        }

        self::$db->update(
            "UPDATE user_battle_pass SET current_xp = ?, current_level = ?, updated_at = NOW()
             WHERE user_id = ? AND season_id = ?",
            [$currentXp, $currentLevel, $userId, $this->id]
        );

        return ['level' => $currentLevel, 'xp' => $currentXp];
    }

    /**
     * Get all levels with their rewards
     */
    public function getLevels() {
        $cacheKey = "battle_pass_levels:{$this->id}";
        $levels = self::$cache->get($cacheKey);

        if ($levels === null) {
            $levels = self::$db->get(
                "SELECT * FROM battle_pass_levels WHERE season_id = ? ORDER BY level ASC",
                [$this->id]
            );
            self::$cache->set($cacheKey, $levels, 3600);
        }

        return $levels;
    }

    /**
     * Purchase premium track
     */
    public function purchasePremium($userId) {
        $progress = $this->getUserProgress($userId);

        if ($progress['has_premium']) {
            throw new Exception("Premium allaqachon sotib olingan");
        }

        // Premium is purchased via Telegram Stars (handled by PaymentController),
        // here we just activate it.
        self::$db->update(
            "UPDATE user_battle_pass SET has_premium = 1, premium_purchased_at = NOW()
             WHERE user_id = ? AND season_id = ?",
            [$userId, $this->id]
        );

        return true;
    }

    /**
     * Claim rewards for a specific level
     */
    public function claimLevel($userId, $level) {
        $progress = $this->getUserProgress($userId);

        if ((int) $progress['current_level'] < $level) {
            throw new Exception("Bu darajaga hali yetmadingiz");
        }

        $claimed = json_decode($progress['claimed_levels'] ?: '[]', true);
        if (in_array($level, $claimed)) {
            throw new Exception("Bu daraja allaqachon olingan");
        }

        $levelData = self::$db->first(
            "SELECT * FROM battle_pass_levels WHERE season_id = ? AND level = ?",
            [$this->id, $level]
        );

        if (!$levelData) {
            throw new Exception("Daraja topilmadi");
        }

        $user = User::find($userId);
        $hasPremium = (bool) $progress['has_premium'];

        // Free track rewards
        if ($levelData['free_diamonds']) {
            $user->addCurrency('diamonds', (int) $levelData['free_diamonds'], 'battle_pass', $this->id);
        }
        if ($levelData['free_coins']) {
            $user->addCurrency('coins', (int) $levelData['free_coins'], 'battle_pass', $this->id);
        }

        // Premium track rewards
        if ($hasPremium) {
            if ($levelData['premium_diamonds']) {
                $user->addCurrency('diamonds', (int) $levelData['premium_diamonds'], 'battle_pass', $this->id);
            }
            if ($levelData['premium_coins']) {
                $user->addCurrency('coins', (int) $levelData['premium_coins'], 'battle_pass', $this->id);
            }
        }

        $claimed[] = $level;
        self::$db->update(
            "UPDATE user_battle_pass SET claimed_levels = ? WHERE user_id = ? AND season_id = ?",
            [json_encode($claimed), $userId, $this->id]
        );

        return true;
    }
}
