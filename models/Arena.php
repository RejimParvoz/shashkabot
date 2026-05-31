<?php
/**
 * Shashka Game - Arena Model
 * Handles weekly arena competitions, points, and streak bonuses
 */

require_once __DIR__ . '/../core/Model.php';

class Arena extends Model {

    protected $table = 'arena_seasons';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'season_number', 'start_date', 'end_date', 'is_active',
        'points_win', 'points_draw', 'points_loss',
        'streak_bonus_3', 'streak_bonus_5', 'streak_bonus_7', 'streak_bonus_10',
        'first_place_diamonds', 'participation_diamonds', 'special_item_id'
    ];

    protected $casts = [
        'season_number' => 'integer',
        'is_active' => 'boolean',
        'points_win' => 'integer',
        'points_draw' => 'integer',
        'points_loss' => 'integer',
        'streak_bonus_3' => 'integer',
        'streak_bonus_5' => 'integer',
        'streak_bonus_7' => 'integer',
        'streak_bonus_10' => 'integer',
        'first_place_diamonds' => 'integer',
        'participation_diamonds' => 'integer'
    ];

    /**
     * Get the currently active arena season
     */
    public static function getActiveSeason() {
        $cacheKey = 'arena_active_season';
        $season = self::$cache->get($cacheKey);

        if ($season === null) {
            $sql = "SELECT * FROM arena_seasons
                    WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
                    ORDER BY season_number DESC LIMIT 1";
            $season = self::$db->first($sql);
            self::$cache->set($cacheKey, $season ?: false, 300);
        }

        return $season !== false ? $season : null;
    }

    /**
     * Join the arena season
     */
    public function joinSeason($userId) {
        $sql = "SELECT id FROM arena_players WHERE season_id = ? AND user_id = ?";
        $existing = self::$db->first($sql, [$this->id, $userId]);

        if ($existing) {
            return false; // Already joined
        }

        $sql = "INSERT INTO arena_players (season_id, user_id, joined_at) VALUES (?, ?, NOW())";
        self::$db->insert($sql, [$this->id, $userId]);
        self::$cache->delete("arena_rankings:{$this->id}");

        return true;
    }

    /**
     * Record a game result for a player and update points + streak
     */
    public function recordResult($userId, $result) {
        $player = self::$db->first(
            "SELECT * FROM arena_players WHERE season_id = ? AND user_id = ?",
            [$this->id, $userId]
        );

        if (!$player) {
            // Auto-join if not yet a participant
            $this->joinSeason($userId);
            $player = self::$db->first(
                "SELECT * FROM arena_players WHERE season_id = ? AND user_id = ?",
                [$this->id, $userId]
            );
        }

        $points = (int) $player['points'];
        $streak = (int) $player['current_streak'];
        $wins = (int) $player['wins'];
        $losses = (int) $player['losses'];
        $draws = (int) $player['draws'];

        if ($result === 'win') {
            $points += $this->points_win;
            $streak++;
            $wins++;
            $points += $this->calculateStreakBonus($streak);
        } elseif ($result === 'draw') {
            $points += $this->points_draw;
            $streak = 0;
            $draws++;
        } else {
            $points += $this->points_loss;
            $streak = 0;
            $losses++;
        }

        $maxStreak = max((int) $player['max_streak'], $streak);

        self::$db->update(
            "UPDATE arena_players SET points = ?, current_streak = ?, max_streak = ?,
             wins = ?, losses = ?, draws = ?, games_played = games_played + 1, updated_at = NOW()
             WHERE season_id = ? AND user_id = ?",
            [$points, $streak, $maxStreak, $wins, $losses, $draws, $this->id, $userId]
        );

        self::$cache->delete("arena_rankings:{$this->id}");
        return $points;
    }

    /**
     * Calculate streak bonus points
     */
    public function calculateStreakBonus($streak) {
        if ($streak >= 10) {
            return $this->streak_bonus_10;
        }
        if ($streak >= 7) {
            return $this->streak_bonus_7;
        }
        if ($streak >= 5) {
            return $this->streak_bonus_5;
        }
        if ($streak >= 3) {
            return $this->streak_bonus_3;
        }
        return 0;
    }

    /**
     * Get arena rankings (leaderboard)
     */
    public function getRankings($limit = 100) {
        $cacheKey = "arena_rankings:{$this->id}";
        $rankings = self::$cache->get($cacheKey);

        if ($rankings === null) {
            $sql = "SELECT ap.*, u.username, u.first_name, u.photo_url, u.rating
                    FROM arena_players ap
                    INNER JOIN users u ON ap.user_id = u.id
                    WHERE ap.season_id = ?
                    ORDER BY ap.points DESC, ap.wins DESC
                    LIMIT ?";
            $rankings = self::$db->get($sql, [$this->id, $limit]);

            // Add rank position
            $rank = 1;
            foreach ($rankings as &$row) {
                $row['rank'] = $rank++;
            }
            unset($row);

            self::$cache->set($cacheKey, $rankings, 300);
        }

        return $rankings;
    }

    /**
     * Get a single player's arena standing
     */
    public function getPlayerStanding($userId) {
        $sql = "SELECT ap.*, (
                    SELECT COUNT(*) + 1 FROM arena_players ap2
                    WHERE ap2.season_id = ap.season_id AND ap2.points > ap.points
                ) as rank
                FROM arena_players ap
                WHERE ap.season_id = ? AND ap.user_id = ?";

        return self::$db->first($sql, [$this->id, $userId]);
    }

    /**
     * Finalize the season and distribute rewards
     */
    public function finalizeSeason() {
        $rankings = $this->getRankings(1000);

        foreach ($rankings as $player) {
            $user = User::find($player['user_id']);
            if (!$user) {
                continue;
            }

            // First place gets the big prize, everyone gets participation
            if ($player['rank'] == 1) {
                $user->addCurrency('diamonds', $this->first_place_diamonds, 'arena_prize', $this->id);
            } else {
                $user->addCurrency('diamonds', $this->participation_diamonds, 'arena_prize', $this->id);
            }

            self::$db->update(
                "UPDATE arena_players SET final_rank = ?, rewards_claimed = 1 WHERE season_id = ? AND user_id = ?",
                [$player['rank'], $this->id, $player['user_id']]
            );
        }

        $this->is_active = false;
        $this->save();
        self::$cache->delete('arena_active_season');

        return true;
    }
}
