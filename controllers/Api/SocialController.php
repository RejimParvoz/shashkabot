<?php
/**
 * Shashka Game - Social Controller
 * Handles friends, requests, search and gifting
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class SocialController extends Controller {

    const MAX_FRIENDS = 50;
    const REFERRAL_REWARD = 50;

    /**
     * Get friends list
     */
    public function friends($params = []) {
        try {
            $this->requireAuth();
            $user = User::find($this->user['id']);
            $status = $this->input('status', 'accepted');

            $friends = $user->getFriends($status);
            $result = array_map(function ($f) {
                return [
                    'id' => $f->id,
                    'username' => $f->username,
                    'display_name' => $f->getDisplayNameAttribute(),
                    'photo_url' => $f->photo_url,
                    'rating' => $f->rating,
                    'is_online' => $f->is_online
                ];
            }, $friends);

            $this->success($result);
        } catch (Exception $e) {
            $this->logger->error('Friends list error: ' . $e->getMessage());
            $this->error('Do\'stlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Send a friend request
     */
    public function addFriend($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['friend_id']);

            $friendId = (int) $this->input('friend_id');
            if ($friendId === (int) $this->user['id']) {
                $this->error('O\'zingizni do\'st qo\'sha olmaysiz');
            }

            // Check friend limit
            $count = $this->db->first(
                "SELECT COUNT(*) as count FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'",
                [$this->user['id'], $this->user['id']]
            );
            if ($count && (int) $count['count'] >= self::MAX_FRIENDS) {
                $this->error('Do\'stlar limiti (50) to\'ldi');
            }

            $user = User::find($this->user['id']);
            if (!$user->sendFriendRequest($friendId)) {
                $this->error('So\'rov yuborib bo\'lmadi (allaqachon mavjud)');
            }

            // Notify the friend
            $this->sendNotification(
                $friendId,
                'friend_request',
                'Yangi do\'stlik so\'rovi',
                $user->getDisplayNameAttribute() . ' sizni do\'st qo\'shmoqchi'
            );

            $this->success(null, 'Do\'stlik so\'rovi yuborildi');
        } catch (Exception $e) {
            $this->logger->error('Add friend error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Accept a friend request
     */
    public function acceptFriend($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['friend_id']);

            $user = User::find($this->user['id']);
            if (!$user->acceptFriendRequest((int) $this->input('friend_id'))) {
                $this->error('So\'rov topilmadi');
            }

            $this->success(null, 'Do\'stlik qabul qilindi');
        } catch (Exception $e) {
            $this->logger->error('Accept friend error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Remove a friend
     */
    public function removeFriend($params = []) {
        try {
            $this->requireAuth();
            $friendId = isset($params['id']) ? (int) $params['id'] : (int) $this->input('friend_id');

            $this->db->delete(
                "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)",
                [$this->user['id'], $friendId, $friendId, $this->user['id']]
            );

            $this->cache->delete("user_friends:{$this->user['id']}:accepted");
            $this->cache->delete("user_friends:{$friendId}:accepted");

            $this->success(null, 'Do\'st o\'chirildi');
        } catch (Exception $e) {
            $this->logger->error('Remove friend error: ' . $e->getMessage());
            $this->error('O\'chirib bo\'lmadi', 500);
        }
    }

    /**
     * Search users by username or name
     */
    public function search($params = []) {
        try {
            $this->requireAuth();
            $query = trim($this->input('query', ''));

            if (strlen($query) < 2) {
                $this->error('Kamida 2 ta belgi kiriting');
            }

            $users = $this->db->get(
                "SELECT id, username, first_name, photo_url, rating, is_online
                 FROM users
                 WHERE (username LIKE ? OR first_name LIKE ?) AND status = 'active' AND id != ?
                 LIMIT 20",
                ["%{$query}%", "%{$query}%", $this->user['id']]
            );

            $this->success($users);
        } catch (Exception $e) {
            $this->logger->error('User search error: ' . $e->getMessage());
            $this->error('Qidiruv amalga oshmadi', 500);
        }
    }

    /**
     * Send a gift to a friend (diamonds)
     */
    public function gift($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['friend_id', 'amount']);

            $friendId = (int) $this->input('friend_id');
            $amount = (int) $this->input('amount');

            if ($amount < 1 || $amount > 1000) {
                $this->error('Sovg\'a miqdori 1-1000 oralig\'ida bo\'lishi kerak');
            }

            $sender = User::find($this->user['id']);
            if ($sender->diamonds < $amount) {
                $this->error('Olmoslar yetarli emas');
            }

            $recipient = User::find($friendId);
            if (!$recipient) {
                $this->error('Foydalanuvchi topilmadi', 404);
            }

            User::transaction(function () use ($sender, $recipient, $amount, $friendId) {
                $sender->subtractCurrency('diamonds', $amount, 'gift_sent', $friendId);
                $recipient->addCurrency('diamonds', $amount, 'gift_received', $this->user['id']);
            });

            $this->sendNotification(
                $friendId,
                'system_announcement',
                'Sovg\'a oldingiz!',
                $sender->getDisplayNameAttribute() . " sizga {$amount} 💎 sovg'a qildi"
            );

            $this->success(null, 'Sovg\'a yuborildi!');
        } catch (Exception $e) {
            $this->logger->error('Gift error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
