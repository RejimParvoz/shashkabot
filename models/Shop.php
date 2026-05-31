<?php
/**
 * Shashka Game - Shop Model
 * Handles shop items, purchases, and inventory management
 */

require_once __DIR__ . '/../core/Model.php';

class Shop extends Model {
    
    protected $table = 'shop_items';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'category_id', 'name', 'description', 'image_url', 'animation_url',
        'price_type', 'price', 'original_price', 'rarity', 'is_limited',
        'available_until', 'max_purchases', 'is_active', 'is_featured', 'sort_order'
    ];
    
    protected $casts = [
        'category_id' => 'integer',
        'price' => 'integer',
        'original_price' => 'integer',
        'max_purchases' => 'integer',
        'sort_order' => 'integer',
        'is_limited' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];
    
    // Price types
    const PRICE_DIAMONDS = 'diamonds';
    const PRICE_COINS = 'coins';
    const PRICE_FREE = 'free';
    const PRICE_VIP_ONLY = 'vip_only';
    
    // Rarity levels
    const RARITY_COMMON = 'common';
    const RARITY_UNCOMMON = 'uncommon';
    const RARITY_RARE = 'rare';
    const RARITY_EPIC = 'epic';
    const RARITY_LEGENDARY = 'legendary';
    const RARITY_MYTHIC = 'mythic';
    
    /**
     * Get all shop categories
     */
    public static function getCategories() {
        $cacheKey = 'shop_categories';
        $categories = self::$cache->get($cacheKey);
        
        if ($categories === null) {
            $sql = "SELECT * FROM shop_categories WHERE is_active = 1 ORDER BY sort_order ASC";
            $categories = self::$db->get($sql);
            
            // Cache for 1 hour
            self::$cache->set($cacheKey, $categories, 3600);
        }
        
        return $categories;
    }
    
    /**
     * Get items by category
     */
    public static function getItemsByCategory($categoryId, $limit = 50, $offset = 0) {
        $cacheKey = "shop_items_category_{$categoryId}_{$limit}_{$offset}";
        $items = self::$cache->get($cacheKey);
        
        if ($items === null) {
            $sql = "SELECT si.*, sc.name as category_name, sc.display_name as category_display_name
                    FROM shop_items si
                    INNER JOIN shop_categories sc ON si.category_id = sc.id
                    WHERE si.category_id = ? AND si.is_active = 1
                    AND (si.available_until IS NULL OR si.available_until > NOW())
                    ORDER BY si.is_featured DESC, si.sort_order ASC, si.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $items = self::$db->get($sql, [$categoryId, $limit, $offset]);
            
            // Cache for 30 minutes
            self::$cache->set($cacheKey, $items, 1800);
        }
        
        return $items;
    }
    
    /**
     * Get featured items
     */
    public static function getFeaturedItems($limit = 10) {
        $cacheKey = "shop_featured_items_{$limit}";
        $items = self::$cache->get($cacheKey);
        
        if ($items === null) {
            $sql = "SELECT si.*, sc.name as category_name, sc.display_name as category_display_name
                    FROM shop_items si
                    INNER JOIN shop_categories sc ON si.category_id = sc.id
                    WHERE si.is_featured = 1 AND si.is_active = 1
                    AND (si.available_until IS NULL OR si.available_until > NOW())
                    ORDER BY si.sort_order ASC, si.created_at DESC
                    LIMIT ?";
            
            $items = self::$db->get($sql, [$limit]);
            
            // Cache for 1 hour
            self::$cache->set($cacheKey, $items, 3600);
        }
        
        return $items;
    }
    
    /**
     * Search items by name
     */
    public static function searchItems($query, $limit = 20) {
        $sql = "SELECT si.*, sc.name as category_name, sc.display_name as category_display_name
                FROM shop_items si
                INNER JOIN shop_categories sc ON si.category_id = sc.id
                WHERE si.name LIKE ? AND si.is_active = 1
                AND (si.available_until IS NULL OR si.available_until > NOW())
                ORDER BY si.is_featured DESC, si.name ASC
                LIMIT ?";
        
        return self::$db->get($sql, ["%{$query}%", $limit]);
    }
    
    /**
     * Get items by rarity
     */
    public static function getItemsByRarity($rarity, $limit = 20) {
        $cacheKey = "shop_items_rarity_{$rarity}_{$limit}";
        $items = self::$cache->get($cacheKey);
        
        if ($items === null) {
            $sql = "SELECT si.*, sc.name as category_name, sc.display_name as category_display_name
                    FROM shop_items si
                    INNER JOIN shop_categories sc ON si.category_id = sc.id
                    WHERE si.rarity = ? AND si.is_active = 1
                    AND (si.available_until IS NULL OR si.available_until > NOW())
                    ORDER BY si.is_featured DESC, si.created_at DESC
                    LIMIT ?";
            
            $items = self::$db->get($sql, [$rarity, $limit]);
            
            // Cache for 1 hour
            self::$cache->set($cacheKey, $items, 3600);
        }
        
        return $items;
    }
    
    /**
     * Purchase item
     */
    public function purchaseItem($userId) {
        $user = User::find($userId);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // Check if item is available
        if (!$this->is_active) {
            throw new Exception("Item is not available");
        }
        
        if ($this->is_limited && $this->available_until && strtotime($this->available_until) < time()) {
            throw new Exception("Item is no longer available");
        }
        
        // Check if user already owns this item
        $sql = "SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?";
        $existing = self::$db->first($sql, [$userId, $this->id]);
        
        if ($existing) {
            throw new Exception("You already own this item");
        }
        
        // Check purchase limits
        if ($this->max_purchases) {
            $sql = "SELECT COUNT(*) as count FROM user_inventory WHERE item_id = ?";
            $result = self::$db->first($sql, [$this->id]);
            
            if ($result && $result['count'] >= $this->max_purchases) {
                throw new Exception("Purchase limit reached for this item");
            }
        }
        
        // Check VIP requirement
        if ($this->price_type === self::PRICE_VIP_ONLY) {
            $vipInfo = $user->hasActiveVip();
            if (!$vipInfo) {
                throw new Exception("VIP subscription required");
            }
        }
        
        // Process payment
        if ($this->price_type !== self::PRICE_FREE) {
            $success = $this->processPayment($user);
            if (!$success) {
                throw new Exception("Payment failed - insufficient balance");
            }
        }
        
        // Add item to user inventory
        $sql = "INSERT INTO user_inventory (user_id, item_id, quantity, purchased_at) VALUES (?, ?, 1, NOW())";
        $inventoryId = self::$db->insert($sql, [$userId, $this->id]);
        
        // Clear user inventory cache
        self::$cache->delete("user_inventory:{$userId}");
        self::$cache->delete("user_inventory:{$userId}:{$this->category_id}");
        self::$cache->delete("user_equipped:{$userId}");
        
        return $inventoryId;
    }
    
    /**
     * Process payment for item
     */
    private function processPayment($user) {
        switch ($this->price_type) {
            case self::PRICE_DIAMONDS:
                return $user->subtractCurrency('diamonds', $this->price, 'shop_purchase', $this->id);
            case self::PRICE_COINS:
                return $user->subtractCurrency('coins', $this->price, 'shop_purchase', $this->id);
            case self::PRICE_FREE:
                return true;
            case self::PRICE_VIP_ONLY:
                return true; // VIP check already done
            default:
                return false;
        }
    }
    
    /**
     * Get item details with category info
     */
    public function getItemDetails() {
        $cacheKey = "shop_item_details:{$this->id}";
        $details = self::$cache->get($cacheKey);
        
        if ($details === null) {
            $sql = "SELECT si.*, sc.name as category_name, sc.display_name as category_display_name, sc.icon as category_icon
                    FROM shop_items si
                    INNER JOIN shop_categories sc ON si.category_id = sc.id
                    WHERE si.id = ?";
            
            $details = self::$db->first($sql, [$this->id]);
            
            if ($details) {
                // Cache for 2 hours
                self::$cache->set($cacheKey, $details, 7200);
            }
        }
        
        return $details;
    }
    
    /**
     * Check if user can purchase this item
     */
    public function canUserPurchase($userId) {
        $user = User::find($userId);
        if (!$user) {
            return ['can_purchase' => false, 'reason' => 'User not found'];
        }
        
        // Check if item is available
        if (!$this->is_active) {
            return ['can_purchase' => false, 'reason' => 'Item not available'];
        }
        
        // Check time availability
        if ($this->is_limited && $this->available_until && strtotime($this->available_until) < time()) {
            return ['can_purchase' => false, 'reason' => 'Item no longer available'];
        }
        
        // Check if already owned
        $sql = "SELECT id FROM user_inventory WHERE user_id = ? AND item_id = ?";
        $existing = self::$db->first($sql, [$userId, $this->id]);
        
        if ($existing) {
            return ['can_purchase' => false, 'reason' => 'Already owned'];
        }
        
        // Check VIP requirement
        if ($this->price_type === self::PRICE_VIP_ONLY) {
            $vipInfo = $user->hasActiveVip();
            if (!$vipInfo) {
                return ['can_purchase' => false, 'reason' => 'VIP required'];
            }
        }
        
        // Check balance
        if ($this->price_type === self::PRICE_DIAMONDS && $user->diamonds < $this->price) {
            return ['can_purchase' => false, 'reason' => 'Insufficient diamonds'];
        }
        
        if ($this->price_type === self::PRICE_COINS && $user->coins < $this->price) {
            return ['can_purchase' => false, 'reason' => 'Insufficient coins'];
        }
        
        // Check purchase limits
        if ($this->max_purchases) {
            $sql = "SELECT COUNT(*) as count FROM user_inventory WHERE item_id = ?";
            $result = self::$db->first($sql, [$this->id]);
            
            if ($result && $result['count'] >= $this->max_purchases) {
                return ['can_purchase' => false, 'reason' => 'Purchase limit reached'];
            }
        }
        
        return ['can_purchase' => true];
    }
    
    /**
     * Get rarity color
     */
    public function getRarityColor() {
        $colors = [
            self::RARITY_COMMON => '#9CA3AF',     // Gray
            self::RARITY_UNCOMMON => '#10B981',   // Green
            self::RARITY_RARE => '#3B82F6',       // Blue
            self::RARITY_EPIC => '#8B5CF6',       // Purple
            self::RARITY_LEGENDARY => '#F59E0B',  // Orange
            self::RARITY_MYTHIC => '#EF4444'      // Red
        ];
        
        return $colors[$this->rarity] ?? $colors[self::RARITY_COMMON];
    }
    
    /**
     * Get rarity name in Uzbek
     */
    public function getRarityName() {
        $names = [
            self::RARITY_COMMON => 'Oddiy',
            self::RARITY_UNCOMMON => 'Kam uchraydigan',
            self::RARITY_RARE => 'Noyob',
            self::RARITY_EPIC => 'Epik',
            self::RARITY_LEGENDARY => 'Afsonaviy',
            self::RARITY_MYTHIC => 'Mifik'
        ];
        
        return $names[$this->rarity] ?? $names[self::RARITY_COMMON];
    }
    
    /**
     * Get price display text
     */
    public function getPriceDisplay() {
        switch ($this->price_type) {
            case self::PRICE_DIAMONDS:
                return $this->price . ' 💎';
            case self::PRICE_COINS:
                return $this->price . ' 🪙';
            case self::PRICE_FREE:
                return 'Bepul';
            case self::PRICE_VIP_ONLY:
                return 'Faqat VIP';
            default:
                return 'N/A';
        }
    }
    
    /**
     * Create new shop item
     */
    public static function createItem($data) {
        // Validate required fields
        $required = ['category_id', 'name', 'price_type', 'price'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Set defaults
        $data = array_merge([
            'rarity' => self::RARITY_COMMON,
            'is_active' => true,
            'is_featured' => false,
            'is_limited' => false,
            'sort_order' => 0
        ], $data);
        
        $item = new static($data);
        $item->save();
        
        // Clear category cache
        self::$cache->delete("shop_items_category_{$data['category_id']}_50_0");
        self::$cache->delete('shop_featured_items_10');
        
        return $item;
    }
    
    /**
     * Get purchase statistics
     */
    public function getPurchaseStats() {
        $cacheKey = "shop_item_stats:{$this->id}";
        $stats = self::$cache->get($cacheKey);
        
        if ($stats === null) {
            $sql = "SELECT 
                        COUNT(*) as total_purchases,
                        COUNT(DISTINCT user_id) as unique_buyers,
                        MAX(purchased_at) as last_purchase
                    FROM user_inventory 
                    WHERE item_id = ?";
            
            $stats = self::$db->first($sql, [$this->id]);
            
            // Cache for 1 hour
            self::$cache->set($cacheKey, $stats, 3600);
        }
        
        return $stats;
    }
}

/**
 * User Inventory Model
 */
class UserInventory extends Model {
    
    protected $table = 'user_inventory';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'user_id', 'item_id', 'quantity', 'is_equipped'
    ];
    
    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
        'is_equipped' => 'boolean'
    ];
    
    /**
     * Equip item
     */
    public function equipItem() {
        // Get item details to check category
        $item = Shop::find($this->item_id);
        if (!$item) {
            return false;
        }
        
        return self::transaction(function() use ($item) {
            // Unequip other items in same category
            $sql = "UPDATE user_inventory ui 
                    INNER JOIN shop_items si ON ui.item_id = si.id 
                    SET ui.is_equipped = 0 
                    WHERE ui.user_id = ? AND si.category_id = ?";
            
            self::$db->update($sql, [$this->user_id, $item->category_id]);
            
            // Equip this item
            $this->is_equipped = true;
            $this->save();
            
            // Clear cache
            self::$cache->delete("user_equipped:{$this->user_id}");
            self::$cache->delete("user_inventory:{$this->user_id}");
            
            return true;
        });
    }
    
    /**
     * Unequip item
     */
    public function unequipItem() {
        $this->is_equipped = false;
        $result = $this->save();
        
        if ($result) {
            // Clear cache
            self::$cache->delete("user_equipped:{$this->user_id}");
        }
        
        return $result;
    }
}