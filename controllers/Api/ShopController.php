<?php
/**
 * Shashka Game - Shop Controller
 * Handles shop browsing, purchases, and item equipping
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/Shop.php';
require_once __DIR__ . '/../../models/User.php';

class ShopController extends Controller {

    /**
     * Get shop categories
     */
    public function categories($params = []) {
        try {
            $categories = Shop::getCategories();
            $this->success($categories);
        } catch (Exception $e) {
            $this->logger->error('Shop categories error: ' . $e->getMessage());
            $this->error('Kategoriyalarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Get shop items (optionally by category)
     */
    public function items($params = []) {
        try {
            $categoryId = $this->input('category_id');
            $limit = min(50, max(1, (int) $this->input('limit', 50)));
            $offset = max(0, (int) $this->input('offset', 0));

            if ($categoryId) {
                $items = Shop::getItemsByCategory((int) $categoryId, $limit, $offset);
            } else {
                $items = Shop::getFeaturedItems($limit);
            }

            // Attach ownership info if authenticated
            if ($this->user) {
                $items = $this->attachOwnership($items, $this->user['id']);
            }

            $this->success($items);
        } catch (Exception $e) {
            $this->logger->error('Shop items error: ' . $e->getMessage());
            $this->error('Buyumlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Purchase a shop item
     */
    public function purchase($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 10, 1);
            $this->validateRequired(['item_id']);

            $item = Shop::find((int) $this->input('item_id'));
            if (!$item) {
                $this->error('Buyum topilmadi', 404);
            }

            $item->purchaseItem($this->user['id']);

            $this->logUserAction('shop_purchase', ['item_id' => $item->id]);

            // Return updated balance
            $user = User::find($this->user['id']);
            $this->success([
                'diamonds' => $user->diamonds,
                'coins' => $user->coins
            ], 'Buyum sotib olindi!');
        } catch (Exception $e) {
            $this->logger->error('Shop purchase error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Equip an owned item
     */
    public function equip($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['inventory_id']);

            require_once __DIR__ . '/../../models/Shop.php';
            $inventory = UserInventory::find((int) $this->input('inventory_id'));

            if (!$inventory || $inventory->user_id != $this->user['id']) {
                $this->error('Buyum sizning inventaringizda yo\'q', 404);
            }

            $equip = $this->input('equip', true);
            if ($equip) {
                $inventory->equipItem();
                $this->success(null, 'Buyum jihozlandi');
            } else {
                $inventory->unequipItem();
                $this->success(null, 'Buyum yechildi');
            }
        } catch (Exception $e) {
            $this->logger->error('Shop equip error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Attach ownership flags to items
     */
    private function attachOwnership($items, $userId) {
        if (empty($items)) {
            return $items;
        }

        $itemIds = array_column($items, 'id');
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        $owned = $this->db->get(
            "SELECT item_id FROM user_inventory WHERE user_id = ? AND item_id IN ($placeholders)",
            array_merge([$userId], $itemIds)
        );
        $ownedIds = array_column($owned, 'item_id');

        foreach ($items as &$item) {
            $item['owned'] = in_array($item['id'], $ownedIds);
        }
        unset($item);

        return $items;
    }
}
