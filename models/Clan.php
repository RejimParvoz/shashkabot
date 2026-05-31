<?php
/**
 * Shashka Game - Clan Model
 * Handles clan creation, membership, roles, and leveling
 */

require_once __DIR__ . '/../core/Model.php';

class Clan extends Model {

    protected $table = 'clans';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'tag', 'description', 'logo_url', 'leader_id', 'level',
        'experience', 'member_count', 'max_members', 'total_rating',
        'average_rating', 'is_public', 'join_requirement_rating'
    ];

    protected $casts = [
        'leader_id' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'member_count' => 'integer',
        'max_members' => 'integer',
        'total_rating' => 'integer',
        'average_rating' => 'integer',
        'is_public' => 'boolean',
        'join_requirement_rating' => 'integer'
    ];

    // Roles
    const ROLE_MEMBER = 'member';
    const ROLE_ELDER = 'elder';
    const ROLE_LEADER = 'leader';

    // Member limits per level
    const MAX_MEMBERS_BY_LEVEL = [1 => 10, 2 => 15, 3 => 25, 4 => 35, 5 => 50];

    // Cost to create a clan
    const CREATE_COST_COINS = 1000;

    /**
     * Create a new clan (charges the leader 1000 coins)
     */
    public static function createClan($leaderId, $name, $tag, $description = null) {
        $user = User::find($leaderId);
        if (!$user) {
            throw new Exception("Foydalanuvchi topilmadi");
        }

        // Check if user is already in a clan
        $existing = self::$db->first(
            "SELECT id FROM clan_members WHERE user_id = ?",
            [$leaderId]
        );
        if ($existing) {
            throw new Exception("Siz allaqachon klandasiz");
        }

        // Validate name and tag uniqueness
        $dup = self::$db->first(
            "SELECT id FROM clans WHERE name = ? OR tag = ?",
            [$name, $tag]
        );
        if ($dup) {
            throw new Exception("Bu nom yoki teg band");
        }

        if ($user->coins < self::CREATE_COST_COINS) {
            throw new Exception("Klan yaratish uchun 1000 tanga kerak");
        }

        return self::transaction(function () use ($user, $leaderId, $name, $tag, $description) {
            // Charge creation cost
            $paid = $user->subtractCurrency('coins', self::CREATE_COST_COINS, 'clan_create');
            if (!$paid) {
                throw new Exception("To'lov amalga oshmadi");
            }

            // Create clan
            $clan = new static([
                'name' => $name,
                'tag' => $tag,
                'description' => $description,
                'leader_id' => $leaderId,
                'level' => 1,
                'experience' => 0,
                'member_count' => 1,
                'max_members' => self::MAX_MEMBERS_BY_LEVEL[1],
                'total_rating' => $user->rating,
                'average_rating' => $user->rating,
                'is_public' => true
            ]);
            $clan->save();

            // Add leader as member
            self::$db->insert(
                "INSERT INTO clan_members (clan_id, user_id, role, joined_at) VALUES (?, ?, 'leader', NOW())",
                [$clan->id, $leaderId]
            );

            return $clan;
        });
    }

    /**
     * Add a member to the clan
     */
    public function addMember($userId) {
        if ($this->member_count >= $this->max_members) {
            throw new Exception("Klan to'lgan");
        }

        $existing = self::$db->first("SELECT id FROM clan_members WHERE user_id = ?", [$userId]);
        if ($existing) {
            throw new Exception("Foydalanuvchi allaqachon klanda");
        }

        $user = User::find($userId);
        if (!$user) {
            throw new Exception("Foydalanuvchi topilmadi");
        }

        if ($this->join_requirement_rating !== null && $user->rating < $this->join_requirement_rating) {
            throw new Exception("Reytingingiz yetarli emas");
        }

        return self::transaction(function () use ($user, $userId) {
            self::$db->insert(
                "INSERT INTO clan_members (clan_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())",
                [$this->id, $userId]
            );

            $this->member_count++;
            $this->total_rating += $user->rating;
            $this->average_rating = (int) round($this->total_rating / $this->member_count);
            $this->save();

            self::$cache->delete("clan_members:{$this->id}");
            return true;
        });
    }

    /**
     * Remove a member from the clan
     */
    public function removeMember($userId) {
        $member = self::$db->first(
            "SELECT cm.*, u.rating FROM clan_members cm
             INNER JOIN users u ON cm.user_id = u.id
             WHERE cm.clan_id = ? AND cm.user_id = ?",
            [$this->id, $userId]
        );

        if (!$member) {
            return false;
        }

        if ($member['role'] === self::ROLE_LEADER) {
            throw new Exception("Lider klandan chiqa olmaydi. Avval liderlikni o'tkazing.");
        }

        return self::transaction(function () use ($member, $userId) {
            self::$db->delete(
                "DELETE FROM clan_members WHERE clan_id = ? AND user_id = ?",
                [$this->id, $userId]
            );

            $this->member_count = max(0, $this->member_count - 1);
            $this->total_rating = max(0, $this->total_rating - (int) $member['rating']);
            $this->average_rating = $this->member_count > 0
                ? (int) round($this->total_rating / $this->member_count)
                : 0;
            $this->save();

            self::$cache->delete("clan_members:{$this->id}");
            return true;
        });
    }

    /**
     * Promote or change a member's role
     */
    public function setMemberRole($userId, $role) {
        if (!in_array($role, [self::ROLE_MEMBER, self::ROLE_ELDER, self::ROLE_LEADER])) {
            throw new Exception("Noto'g'ri rol");
        }

        self::$db->update(
            "UPDATE clan_members SET role = ?, promoted_at = NOW() WHERE clan_id = ? AND user_id = ?",
            [$role, $this->id, $userId]
        );

        self::$cache->delete("clan_members:{$this->id}");
        return true;
    }

    /**
     * Get clan members list
     */
    public function getMembers() {
        $cacheKey = "clan_members:{$this->id}";
        $members = self::$cache->get($cacheKey);

        if ($members === null) {
            $sql = "SELECT cm.*, u.username, u.first_name, u.photo_url, u.rating, u.is_online
                    FROM clan_members cm
                    INNER JOIN users u ON cm.user_id = u.id
                    WHERE cm.clan_id = ?
                    ORDER BY FIELD(cm.role, 'leader', 'elder', 'member'), cm.contribution_points DESC";
            $members = self::$db->get($sql, [$this->id]);
            self::$cache->set($cacheKey, $members, 600);
        }

        return $members;
    }

    /**
     * Add experience and handle level up
     */
    public function addExperience($amount) {
        $this->experience += $amount;

        // Level thresholds: level^2 * 10000
        $newLevel = $this->level;
        while ($newLevel < 5 && $this->experience >= pow($newLevel, 2) * 10000) {
            $newLevel++;
        }

        if ($newLevel > $this->level) {
            $this->level = $newLevel;
            $this->max_members = self::MAX_MEMBERS_BY_LEVEL[$newLevel];
        }

        $this->save();
        return $this->level;
    }

    /**
     * Get top clans leaderboard
     */
    public static function getTopClans($limit = 50) {
        $cacheKey = "top_clans:{$limit}";
        $clans = self::$cache->get($cacheKey);

        if ($clans === null) {
            $sql = "SELECT c.*, u.first_name as leader_name
                    FROM clans c
                    INNER JOIN users u ON c.leader_id = u.id
                    WHERE c.is_public = 1
                    ORDER BY c.average_rating DESC, c.member_count DESC
                    LIMIT ?";
            $clans = self::$db->get($sql, [$limit]);
            self::$cache->set($cacheKey, $clans, 600);
        }

        return $clans;
    }
}
