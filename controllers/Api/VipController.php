<?php
/**
 * Shashka Game - VIP Controller
 * Handles VIP plans, subscriptions, and daily diamond claims
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class VipController extends Controller {

    // VIP plans (price in Telegram Stars, daily diamonds)
    const PLANS = [
        'bronze' => ['stars' => 50, 'daily' => 5, 'name' => 'Bronze VIP'],
        'silver' => ['stars' => 120, 'daily' => 10, 'name' => 'Silver VIP'],
        'gold' => ['stars' => 250, 'daily' => 20, 'name' => 'Gold VIP'],
        'platinum' => ['stars' => 500, 'daily' => 35, 'name' => 'Platinum VIP']
    ];

    /**
     * Get available VIP plans
     */
    public function plans($params = []) {
        try {
            $plans = [];
            foreach (self::PLANS as $level => $info) {
                $plans[] = [
                    'level' => $level,
                    'name' => $info['name'],
                    'stars_price' => $info['stars'],
                    'daily_diamonds' => $info['daily'],
                    'duration_days' => 30
                ];
            }
            $this->success($plans);
        } catch (Exception $e) {
            $this->logger->error('VIP plans error: ' . $e->getMessage());
            $this->error('Rejalarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Subscribe to a VIP plan (creates pending payment / activates on confirmation)
     */
    public function subscribe($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['level']);

            $level = $this->input('level');
            if (!isset(self::PLANS[$level])) {
                $this->error('Noto\'g\'ri VIP daraja');
            }

            $plan = self::PLANS[$level];

            // Create invoice payload for Telegram Stars
            $chargeId = 'vip_' . uniqid();
            $this->db->insert(
                "INSERT INTO payments (user_id, telegram_payment_charge_id, payment_type, item_id, stars_amount, status, created_at)
                 VALUES (?, ?, 'vip_subscription', NULL, ?, 'pending', NOW())",
                [$this->user['id'], $chargeId, $plan['stars']]
            );

            $this->success([
                'invoice' => [
                    'title' => $plan['name'] . ' (30 kun)',
                    'description' => 'Kunlik ' . $plan['daily'] . ' 💎 va VIP imtiyozlar',
                    'payload' => $chargeId . '|' . $level,
                    'currency' => 'XTR',
                    'prices' => [
                        ['label' => $plan['name'], 'amount' => $plan['stars']]
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error('VIP subscribe error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get current VIP status
     */
    public function status($params = []) {
        try {
            $this->requireAuth();
            $user = User::find($this->user['id']);
            $vipInfo = $user->hasActiveVip();

            $this->success([
                'has_vip' => $vipInfo !== null,
                'level' => $vipInfo ? $vipInfo['level'] : null,
                'expires_at' => $vipInfo ? $vipInfo['end_date'] : null
            ]);
        } catch (Exception $e) {
            $this->logger->error('VIP status error: ' . $e->getMessage());
            $this->error('VIP holatini yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Claim daily VIP diamonds
     */
    public function claimDaily($params = []) {
        try {
            $this->requireAuth();
            $user = User::find($this->user['id']);
            $vipInfo = $user->hasActiveVip();

            if (!$vipInfo) {
                $this->error('Sizda faol VIP yo\'q', 403);
            }

            // Check if already claimed today
            $today = date('Y-m-d');
            $cacheKey = "vip_daily_claim:{$this->user['id']}:{$today}";
            if ($this->cache->get($cacheKey)) {
                $this->error('Bugun allaqachon olgansiz');
            }

            $daily = self::PLANS[$vipInfo['level']]['daily'];
            $user->addCurrency('diamonds', $daily, 'vip_daily');

            // Mark claimed until end of day
            $this->cache->set($cacheKey, true, 86400);

            $this->success(['diamonds_added' => $daily, 'balance' => $user->diamonds], "Kunlik {$daily} 💎 olindi!");
        } catch (Exception $e) {
            $this->logger->error('VIP daily claim error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
