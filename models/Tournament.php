<?php
/**
 * Shashka Game - Tournament Model
 * Handles tournament creation, registration, and bracket management
 */

require_once __DIR__ . '/../core/Model.php';

class Tournament extends Model {

    protected $table = 'tournaments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'type', 'game_mode_id', 'entry_fee_type',
        'entry_fee_amount', 'min_rating', 'max_rating', 'vip_only', 'max_players',
        'current_players', 'format', 'rounds', 'prize_pool_diamonds',
        'prize_pool_coins', 'prize_distribution', 'registration_start',
        'registration_end', 'start_time', 'end_time', 'status'
    ];

    protected $casts = [
        'game_mode_id' => 'integer',
        'entry_fee_amount' => 'integer',
        'min_rating' => 'integer',
        'max_rating' => 'integer',
        'max_players' => 'integer',
        'current_players' => 'integer',
        'rounds' => 'integer',
        'prize_pool_diamonds' => 'integer',
        'prize_pool_coins' => 'integer',
        'vip_only' => 'boolean',
        'prize_distribution' => 'array'
    ];

    // Tournament types
    const TYPE_DAILY = 'daily';
    const TYPE_WEEKLY = 'weekly';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_SEASONAL = 'seasonal';
    const TYPE_CUSTOM = 'custom';

    // Tournament statuses
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_REGISTRATION = 'registration';
    const STATUS_READY = 'ready';
    const STATUS_ACTIVE = 'active';
    const STATUS_FINISHED = 'finished';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get active and upcoming tournaments
     */
    public static function getActiveTournaments() {
        $cacheKey = 'active_tournaments';
        $tournaments = self::$cache->get($cacheKey);

        if ($tournaments === null) {
            $sql = "SELECT t.*, gm.display_name as mode_name
                    FROM tournaments t
                    INNER JOIN game_modes gm ON t.game_mode_id = gm.id
                    WHERE t.status IN ('scheduled', 'registration', 'ready', 'active')
                    ORDER BY t.start_time ASC
                    LIMIT 50";

            $tournaments = self::$db->get($sql);
            self::$cache->set($cacheKey, $tournaments, 60);
        }

        return $tournaments;
    }

    /**
     * Register a player for the tournament
     */
    public function registerPlayer($userId) {
        // Validate tournament status
        if (!in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_REGISTRATION])) {
            throw new Exception("Turnir uchun ro'yxatdan o'tish yopiq");
        }

        // Check capacity
        if ($this->current_players >= $this->max_players) {
            throw new Exception("Turnir to'lgan");
        }

        // Check if already registered
        $sql = "SELECT id FROM tournament_players WHERE tournament_id = ? AND user_id = ?";
        $existing = self::$db->first($sql, [$this->id, $userId]);
        if ($existing) {
            throw new Exception("Siz allaqachon ro'yxatdan o'tgansiz");
        }

        // Load user and validate requirements
        $user = User::find($userId);
        if (!$user) {
            throw new Exception("Foydalanuvchi topilmadi");
        }

        if ($this->min_rating !== null && $user->rating < $this->min_rating) {
            throw new Exception("Reytingingiz juda past");
        }
        if ($this->max_rating !== null && $user->rating > $this->max_rating) {
            throw new Exception("Reytingingiz juda yuqori");
        }
        if ($this->vip_only && !$user->hasActiveVip()) {
            throw new Exception("Faqat VIP foydalanuvchilar uchun");
        }

        // Process entry fee and registration atomically
        return self::transaction(function () use ($user, $userId) {
            if ($this->entry_fee_type !== 'free' && $this->entry_fee_amount > 0) {
                $paid = $user->subtractCurrency(
                    $this->entry_fee_type,
                    $this->entry_fee_amount,
                    'tournament_entry',
                    $this->id
                );
                if (!$paid) {
                    throw new Exception("Mablag' yetarli emas");
                }
            }

            $sql = "INSERT INTO tournament_players (tournament_id, user_id, registered_at) VALUES (?, ?, NOW())";
            self::$db->insert($sql, [$this->id, $userId]);

            $this->current_players++;
            $this->save();

            self::$cache->delete('active_tournaments');
            return true;
        });
    }

    /**
     * Get tournament players ordered by seed/position
     */
    public function getPlayers() {
        $sql = "SELECT tp.*, u.username, u.first_name, u.rating, u.photo_url
                FROM tournament_players tp
                INNER JOIN users u ON tp.user_id = u.id
                WHERE tp.tournament_id = ?
                ORDER BY tp.final_position ASC, tp.score DESC, u.rating DESC";

        return self::$db->get($sql, [$this->id]);
    }

    /**
     * Generate single-elimination bracket and seed players by rating
     */
    public function generateBracket() {
        $players = self::$db->get(
            "SELECT tp.user_id, u.rating FROM tournament_players tp
             INNER JOIN users u ON tp.user_id = u.id
             WHERE tp.tournament_id = ? ORDER BY u.rating DESC",
            [$this->id]
        );

        $count = count($players);
        if ($count < 2) {
            throw new Exception("Turnir uchun yetarli o'yinchi yo'q");
        }

        // Assign seeds based on rating ranking
        $seed = 1;
        foreach ($players as $player) {
            self::$db->update(
                "UPDATE tournament_players SET seed = ?, current_round = 1 WHERE tournament_id = ? AND user_id = ?",
                [$seed, $this->id, $player['user_id']]
            );
            $seed++;
        }

        // Calculate rounds (next power of 2)
        $this->rounds = (int) ceil(log($count, 2));
        $this->status = self::STATUS_READY;
        $this->save();

        return $this->buildPairings($players);
    }

    /**
     * Build first-round pairings (highest seed vs lowest seed)
     */
    private function buildPairings($players) {
        $pairings = [];
        $left = 0;
        $right = count($players) - 1;

        while ($left < $right) {
            $pairings[] = [
                'player1' => $players[$left]['user_id'],
                'player2' => $players[$right]['user_id']
            ];
            $left++;
            $right--;
        }

        // Odd player gets a bye
        if ($left === $right) {
            $pairings[] = [
                'player1' => $players[$left]['user_id'],
                'player2' => null
            ];
        }

        return $pairings;
    }

    /**
     * Distribute prizes to top players based on prize_distribution
     */
    public function distributePrizes() {
        if ($this->status !== self::STATUS_FINISHED) {
            return false;
        }

        $distribution = $this->prize_distribution ?: ['1' => 50, '2' => 30, '3' => 20];
        $players = self::$db->get(
            "SELECT user_id, final_position FROM tournament_players
             WHERE tournament_id = ? AND final_position IS NOT NULL
             ORDER BY final_position ASC",
            [$this->id]
        );

        foreach ($players as $player) {
            $position = (string) $player['final_position'];
            if (!isset($distribution[$position])) {
                continue;
            }

            $percent = $distribution[$position];
            $diamonds = (int) round($this->prize_pool_diamonds * $percent / 100);

            if ($diamonds > 0) {
                $user = User::find($player['user_id']);
                if ($user) {
                    $user->addCurrency('diamonds', $diamonds, 'tournament_prize', $this->id);
                    self::$db->update(
                        "UPDATE tournament_players SET prize_diamonds = ? WHERE tournament_id = ? AND user_id = ?",
                        [$diamonds, $this->id, $player['user_id']]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Create a tournament from a schedule template
     */
    public static function createFromSchedule($schedule) {
        $now = new DateTime();
        $regHours = (int) ($schedule['registration_hours'] ?? 2);
        $startTime = clone $now;
        $startTime->modify("+{$regHours} hours");

        $data = [
            'name' => str_replace('%date%', $now->format('d.m.Y'), $schedule['name_template']),
            'type' => $schedule['type'],
            'game_mode_id' => $schedule['game_mode_id'],
            'entry_fee_type' => $schedule['entry_fee_type'],
            'entry_fee_amount' => $schedule['entry_fee_amount'],
            'max_players' => $schedule['max_players'],
            'current_players' => 0,
            'format' => 'single_elimination',
            'prize_pool_diamonds' => $schedule['prize_pool_diamonds'],
            'registration_start' => $now->format('Y-m-d H:i:s'),
            'registration_end' => $startTime->format('Y-m-d H:i:s'),
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'status' => self::STATUS_REGISTRATION,
            'prize_distribution' => json_encode(['1' => 50, '2' => 30, '3' => 20])
        ];

        $tournament = new static($data);
        $tournament->save();
        self::$cache->delete('active_tournaments');

        return $tournament;
    }
}
