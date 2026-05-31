<?php
/**
 * Shashka Game - Telegram Webhook Controller
 * Handles all incoming updates from the Telegram Bot API:
 *   - messages and commands (/start, /help, /play ...)
 *   - callback queries (inline keyboard buttons)
 *   - pre_checkout_query (Telegram Stars payment confirmation)
 *   - successful_payment (credit diamonds / activate VIP)
 *
 * Webhook URL example: https://topkons.uz/shashka/api/telegram/webhook
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class TelegramController extends Controller {

    private $botToken;
    private $telegramConfig;

    public function __construct() {
        parent::__construct();
        $this->telegramConfig = $this->app->config('telegram');
        $this->botToken = isset($this->telegramConfig['bot']['token'])
            ? $this->telegramConfig['bot']['token']
            : ($_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
    }

    /**
     * Main webhook entry point.
     * Telegram always expects a fast 200 OK response.
     */
    public function webhook($params = []) {
        // 1. Verify secret token (if configured) to block spoofed requests
        if (!$this->verifySecret()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }

        // 2. Read the raw update
        $raw = file_get_contents('php://input');
        $update = json_decode($raw, true);

        if (!is_array($update)) {
            // Always answer 200 so Telegram does not retry forever
            http_response_code(200);
            echo json_encode(['ok' => true]);
            return;
        }

        try {
            $this->routeUpdate($update);
        } catch (Exception $e) {
            $this->logger->error('Telegram webhook error: ' . $e->getMessage(), ['update' => $update]);
        }

        // Always acknowledge
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    /**
     * Dispatch the update to the right handler based on its type
     */
    private function routeUpdate($update) {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        } elseif (isset($update['pre_checkout_query'])) {
            $this->handlePreCheckout($update['pre_checkout_query']);
        }

        // successful_payment arrives inside a message
        if (isset($update['message']['successful_payment'])) {
            $this->handleSuccessfulPayment($update['message']);
        }
    }

    /**
     * Handle text messages and bot commands
     */
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $text = isset($message['text']) ? trim($message['text']) : '';
        $from = isset($message['from']) ? $message['from'] : [];

        // Ensure the user exists in our database
        $this->ensureUser($from);

        // Command handling
        if (strpos($text, '/start') === 0) {
            $this->sendWelcome($chatId);
        } elseif (strpos($text, '/help') === 0) {
            $this->sendMessage($chatId, $this->msg('help', "Yordam:\n/play - o'yin\n/profile - profil\n/shop - do'kon"));
        } elseif (strpos($text, '/play') === 0) {
            $this->sendPlayButton($chatId);
        } elseif (strpos($text, '/profile') === 0 || strpos($text, '/shop') === 0) {
            $this->sendOpenAppButton($chatId, "Ilovani oching:");
        } else {
            // Default: offer to open the Mini App
            $this->sendOpenAppButton($chatId, "Shashka o'ynash uchun ilovani oching 👇");
        }
    }

    /**
     * Handle inline keyboard button presses
     */
    private function handleCallbackQuery($callback) {
        $callbackId = $callback['id'];
        // Acknowledge the button press (removes the loading spinner)
        $this->apiCall('answerCallbackQuery', ['callback_query_id' => $callbackId]);

        $data = isset($callback['data']) ? $callback['data'] : '';
        $chatId = isset($callback['message']['chat']['id']) ? $callback['message']['chat']['id'] : null;

        if ($chatId && $data === 'play') {
            $this->sendPlayButton($chatId);
        }
    }

    /**
     * Approve a pending Telegram Stars checkout.
     * MUST answer within 10 seconds or the payment fails.
     */
    private function handlePreCheckout($query) {
        $this->apiCall('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $query['id'],
            'ok' => true
        ]);
    }

    /**
     * Credit the user after a successful Telegram Stars payment.
     * payload format: "<charge_id>" (diamond pack) or "<charge_id>|<vip_level>" (VIP).
     */
    private function handleSuccessfulPayment($message) {
        $payment = $message['successful_payment'];
        $payload = isset($payment['invoice_payload']) ? $payment['invoice_payload'] : '';
        $chargeId = isset($payment['telegram_payment_charge_id']) ? $payment['telegram_payment_charge_id'] : '';
        $chatId = $message['chat']['id'];

        // Split payload (VIP carries a level after a pipe)
        $parts = explode('|', $payload);
        $internalCharge = $parts[0];
        $vipLevel = isset($parts[1]) ? $parts[1] : null;

        $pending = $this->db->first(
            "SELECT * FROM payments WHERE telegram_payment_charge_id = ? AND status = 'pending'",
            [$internalCharge]
        );

        if (!$pending) {
            $this->sendMessage($chatId, "To'lov topilmadi yoki allaqachon yakunlangan.");
            return;
        }

        User::transaction(function () use ($pending, $chargeId, $vipLevel) {
            // Mark payment completed
            $this->db->update(
                "UPDATE payments SET status = 'completed', telegram_provider_payment_charge_id = ?, completed_at = NOW() WHERE id = ?",
                [$chargeId, $pending['id']]
            );

            $user = User::find($pending['user_id']);
            if (!$user) {
                return;
            }

            if ($pending['payment_type'] === 'diamond_pack') {
                $user->addCurrency('diamonds', (int) $pending['diamonds_amount'], 'purchase', $pending['id']);
            } elseif ($pending['payment_type'] === 'vip_subscription' && $vipLevel) {
                $this->activateVip($user, $vipLevel, (int) $pending['stars_amount']);
            }
        });

        $this->sendMessage($chatId, "✅ To'lov muvaffaqiyatli! Mukofotingiz hisobingizga qo'shildi.");
    }

    /**
     * Activate a VIP subscription for 30 days
     */
    private function activateVip($user, $level, $stars) {
        $end = new DateTime();
        $end->modify('+30 days');

        // Deactivate any existing active subscription
        $this->db->update(
            "UPDATE vip_subscriptions SET is_active = 0 WHERE user_id = ? AND is_active = 1",
            [$user->id]
        );

        $this->db->insert(
            "INSERT INTO vip_subscriptions (user_id, level, stars_paid, start_date, end_date, is_active, created_at)
             VALUES (?, ?, ?, NOW(), ?, 1, NOW())",
            [$user->id, $level, $stars, $end->format('Y-m-d H:i:s')]
        );

        $this->cache->delete("user_vip:{$user->id}");
    }

    /**
     * Create or update the Telegram user in our DB
     */
    private function ensureUser($from) {
        if (empty($from['id'])) {
            return;
        }

        $existing = User::findByTelegramId($from['id']);
        if (!$existing) {
            User::createFromTelegram($from);
        }
    }

    /* ============================================================
     * Outgoing message helpers
     * ============================================================ */

    private function sendWelcome($chatId) {
        $text = $this->msg('welcome', "🎯 Shashka Game'ga xush kelibsiz!\n\nO'ynashni boshlash uchun pastdagi tugmani bosing.");
        $this->sendOpenAppButton($chatId, $text);
    }

    private function sendPlayButton($chatId) {
        $this->sendOpenAppButton($chatId, "🎮 O'yinni boshlash:");
    }

    /**
     * Send a message with a "Open App" web_app button (Telegram Mini App)
     */
    private function sendOpenAppButton($chatId, $text) {
        $appUrl = $this->app->config('telegram.mini_app.url');
        if (!$appUrl) {
            $appUrl = $_ENV['APP_URL'] ?? 'https://topkons.uz/shashka';
        }

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '🎮 Shashka o\'ynash', 'web_app' => ['url' => $appUrl]]
            ]]
        ];

        $this->apiCall('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    private function sendMessage($chatId, $text) {
        $this->apiCall('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    /**
     * Resolve a localized message template from telegram config
     */
    private function msg($key, $default) {
        $messages = isset($this->telegramConfig['messages']) ? $this->telegramConfig['messages'] : [];
        return isset($messages[$key]) ? $messages[$key] : $default;
    }

    /**
     * Verify the secret token header (X-Telegram-Bot-Api-Secret-Token)
     */
    private function verifySecret() {
        $configured = isset($this->telegramConfig['webhook']['secret_token'])
            ? $this->telegramConfig['webhook']['secret_token']
            : ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '');

        // If no secret is configured, skip verification (not recommended for production)
        if (empty($configured)) {
            return true;
        }

        $received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        return hash_equals($configured, $received);
    }

    /**
     * Low-level Telegram Bot API call via cURL
     */
    private function apiCall($method, $params) {
        if (empty($this->botToken)) {
            $this->logger->error('Telegram bot token is not configured');
            return false;
        }

        $url = 'https://api.telegram.org/bot' . $this->botToken . '/' . $method;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('Telegram API call failed: ' . $error);
            return false;
        }

        return json_decode($response, true);
    }
}
