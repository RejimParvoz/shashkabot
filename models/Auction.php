<?php
/**
 * Shashka Game - Auction Model
 * Handles auto-generated auctions, bidding, and 5-minute extension rule
 */

require_once __DIR__ . '/../core/Model.php';

class Auction extends Model {

    protected $table = 'auctions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'item_name', 'item_description', 'category', 'rarity', 'image_url',
        'animation_url', 'starting_bid', 'current_bid', 'bid_increment',
        'current_bidder_id', 'total_bids', 'start_time', 'end_time',
        'extended_count', 'status', 'winner_id'
    ];

    protected $casts = [
        'starting_bid' => 'integer',
        'current_bid' => 'integer',
        'bid_increment' => 'integer',
        'current_bidder_id' => 'integer',
        'total_bids' => 'integer',
        'extended_count' => 'integer',
        'winner_id' => 'integer'
    ];

    // Base starting prices by rarity
    const BASE_PRICES = [
        'common' => 100,
        'uncommon' => 250,
        'rare' => 500,
        'epic' => 1000,
        'legendary' => 2500,
        'mythic' => 5000
    ];

    const EMERGENCY_THRESHOLD = 3;
    const EXTENSION_MINUTES = 5;

    /**
     * Get active auctions
     */
    public static function getActiveAuctions($category = null) {
        $sql = "SELECT * FROM auctions WHERE status = 'active' AND end_time > NOW()";
        $params = [];

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY end_time ASC";
        return self::$db->get($sql, $params);
    }

    /**
     * Count active auctions
     */
    public static function countActive() {
        $result = self::$db->first(
            "SELECT COUNT(*) as count FROM auctions WHERE status = 'active' AND end_time > NOW()"
        );
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Place a bid on the auction
     */
    public function placeBid($userId, $bidAmount) {
        if ($this->status !== 'active' || strtotime($this->end_time) < time()) {
            throw new Exception("Auksion tugagan");
        }

        // Minimum acceptable bid
        $minBid = $this->current_bid
            ? $this->current_bid + $this->bid_increment
            : $this->starting_bid;

        if ($bidAmount < $minBid) {
            throw new Exception("Minimal taklif: {$minBid} 💎");
        }

        if ($this->current_bidder_id == $userId) {
            throw new Exception("Siz allaqachon eng yuqori taklif egasisiz");
        }

        $user = User::find($userId);
        if (!$user || $user->diamonds < $bidAmount) {
            throw new Exception("Olmoslar yetarli emas");
        }

        return self::transaction(function () use ($userId, $bidAmount) {
            // Refund previous highest bidder
            if ($this->current_bidder_id && $this->current_bid) {
                $prevBidder = User::find($this->current_bidder_id);
                if ($prevBidder) {
                    $prevBidder->addCurrency('diamonds', $this->current_bid, 'auction_refund', $this->id);
                }
            }

            // Charge new bidder
            $bidder = User::find($userId);
            $bidder->subtractCurrency('diamonds', $bidAmount, 'auction_bid', $this->id);

            // Record bid
            self::$db->insert(
                "INSERT INTO auction_bids (auction_id, user_id, bid_amount, created_at) VALUES (?, ?, ?, NOW())",
                [$this->id, $userId, $bidAmount]
            );

            // Update auction
            $this->current_bid = $bidAmount;
            $this->current_bidder_id = $userId;
            $this->total_bids++;

            // 5-minute extension rule
            $secondsLeft = strtotime($this->end_time) - time();
            if ($secondsLeft < (self::EXTENSION_MINUTES * 60)) {
                $newEnd = new DateTime();
                $newEnd->modify('+' . self::EXTENSION_MINUTES . ' minutes');
                $this->end_time = $newEnd->format('Y-m-d H:i:s');
                $this->extended_count++;
            }

            $this->save();
            return true;
        });
    }

    /**
     * Finalize an ended auction and deliver item to winner
     */
    public function finalize() {
        if ($this->status !== 'active') {
            return false;
        }

        $this->status = 'ended';

        if ($this->current_bidder_id) {
            $this->winner_id = $this->current_bidder_id;

            // Find a matching shop item to grant, or create inventory entry by name
            $shopItem = self::$db->first(
                "SELECT id FROM shop_items WHERE name = ? LIMIT 1",
                [$this->item_name]
            );

            if ($shopItem) {
                self::$db->insert(
                    "INSERT IGNORE INTO user_inventory (user_id, item_id, quantity, purchased_at) VALUES (?, ?, 1, NOW())",
                    [$this->winner_id, $shopItem['id']]
                );
            }

            // Notify winner
            self::$db->insert(
                "INSERT INTO notifications (user_id, type, title, message, created_at)
                 VALUES (?, 'system_announcement', ?, ?, NOW())",
                [
                    $this->winner_id,
                    'Auksionda g\'alaba!',
                    "Siz '{$this->item_name}' buyumini yutib oldingiz!"
                ]
            );
        }

        $this->save();
        return true;
    }

    /**
     * Auto-generate auctions from the items pool
     */
    public static function generateAuctions($count = null) {
        // Get settings
        $settings = self::$db->first("SELECT * FROM auction_auto_settings WHERE is_active = 1 LIMIT 1");
        $min = $settings ? (int) $settings['min_items'] : 5;
        $max = $settings ? (int) $settings['max_items'] : 15;
        $durationHours = $settings ? (int) $settings['duration_hours'] : 24;

        if ($count === null) {
            $count = rand($min, $max);
        }

        // Pull random items weighted by their weight field
        $pool = self::$db->get(
            "SELECT * FROM auction_items_pool WHERE is_active = 1 ORDER BY RAND() LIMIT ?",
            [$count]
        );

        $created = 0;
        foreach ($pool as $item) {
            $startBid = self::BASE_PRICES[$item['rarity']] ?? 100;
            $endTime = new DateTime();
            $endTime->modify("+{$durationHours} hours");

            $auction = new static([
                'item_name' => $item['name'],
                'item_description' => $item['description'],
                'category' => $item['category'],
                'rarity' => $item['rarity'],
                'image_url' => $item['image_url'],
                'animation_url' => self::pickAnimation($item['animation_keywords']),
                'starting_bid' => $startBid,
                'bid_increment' => max(10, (int) round($startBid * 0.05)),
                'total_bids' => 0,
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'extended_count' => 0,
                'status' => 'active'
            ]);
            $auction->save();
            $created++;
        }

        // Update last generated time
        if ($settings) {
            self::$db->update(
                "UPDATE auction_auto_settings SET last_generated = NOW() WHERE id = ?",
                [$settings['id']]
            );
        }

        return $created;
    }

    /**
     * Pick an animation based on keywords in the item name
     */
    private static function pickAnimation($keywords) {
        if (empty($keywords)) {
            return null;
        }

        $keywordList = array_map('trim', explode(',', $keywords));
        $animationMap = [
            'fire' => 'fire.json',
            'olov' => 'fire.json',
            'ice' => 'ice.json',
            'muz' => 'ice.json',
            'gold' => 'golden.json',
            'oltin' => 'golden.json',
            'star' => 'stars.json',
            'yulduz' => 'stars.json',
            'lightning' => 'lightning.json',
            'chaqmoq' => 'lightning.json'
        ];

        foreach ($keywordList as $keyword) {
            $lower = strtolower($keyword);
            if (isset($animationMap[$lower])) {
                return $animationMap[$lower];
            }
        }

        return null;
    }
}
