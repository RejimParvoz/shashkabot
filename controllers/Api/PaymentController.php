<?php
/**
 * Shashka Game - Payment Controller
 * Handles diamond pack purchases via Telegram Stars
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class PaymentController extends Controller {

    /**
     * Get available diamond packs
     */
    public function diamondPacks($params = []) {
        try {
            $packs = $this->cacheResponse('diamond_packs', function () {
                return $this->db->get(
                    "SELECT * FROM diamond_packs WHERE is_active = 1 ORDER BY sort_order ASC"
                );
            }, 3600);

            $this->success($packs);
        } catch (Exception $e) {
            $this->logger->error('Diamond packs error: ' . $e->getMessage());
            $this->error('Paketlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Create an invoice for purchasing a diamond pack
     */
    public function purchase($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 3, 10);
            $this->validateRequired(['pack_id']);

            $pack = $this->db->first(
                "SELECT * FROM diamond_packs WHERE id = ? AND is_active = 1",
                [(int) $this->input('pack_id')]
            );

            if (!$pack) {
                $this->error('Paket topilmadi', 404);
            }

            // Create a pending payment record
            $chargeId = 'pending_' . uniqid();
            $this->db->insert(
                "INSERT INTO payments (user_id, telegram_payment_charge_id, payment_type, item_id, stars_amount, diamonds_amount, status, created_at)
                 VALUES (?, ?, 'diamond_pack', ?, ?, ?, 'pending', NOW())",
                [
                    $this->user['id'],
                    $chargeId,
                    $pack['id'],
                    $pack['stars_price'],
                    $pack['diamonds_amount'] + $pack['bonus_diamonds']
                ]
            );

            // Return invoice payload for Telegram WebApp to open
            $this->success([
                'invoice' => [
                    'title' => $pack['name'],
                    'description' => $pack['description'],
                    'payload' => $chargeId,
                    'currency' => 'XTR',
                    'prices' => [
                        ['label' => $pack['name'], 'amount' => (int) $pack['stars_price']]
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error('Payment purchase error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Webhook to confirm a successful payment (called after Telegram successful_payment)
     */
    public function webhook($params = []) {
        try {
            $payload = $this->input('payload');
            $chargeId = $this->input('telegram_payment_charge_id');

            if (!$payload || !$chargeId) {
                $this->error('Noto\'g\'ri to\'lov ma\'lumotlari', 400);
            }

            $payment = $this->db->first(
                "SELECT * FROM payments WHERE telegram_payment_charge_id = ? AND status = 'pending'",
                [$payload]
            );

            if (!$payment) {
                $this->error('To\'lov topilmadi yoki allaqachon yakunlangan', 404);
            }

            User::transaction(function () use ($payment, $chargeId) {
                // Mark completed
                $this->db->update(
                    "UPDATE payments SET status = 'completed', telegram_provider_payment_charge_id = ?, completed_at = NOW()
                     WHERE id = ?",
                    [$chargeId, $payment['id']]
                );

                // Credit diamonds
                $user = User::find($payment['user_id']);
                if ($user) {
                    $user->addCurrency('diamonds', (int) $payment['diamonds_amount'], 'purchase', $payment['id']);
                }
            });

            $this->success(null, 'To\'lov muvaffaqiyatli yakunlandi');
        } catch (Exception $e) {
            $this->logger->error('Payment webhook error: ' . $e->getMessage());
            $this->error('To\'lovni qayta ishlab bo\'lmadi', 500);
        }
    }
}
