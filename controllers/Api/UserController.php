<?php
/**
 * Shashka Game - User Controller
 * Handles user profile, stats, games, and inventory
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class UserController extends Controller {

    /**
     * Get user profile
     */
    public function profile($params = []) {
        try {
            $userId = $this->input('user_id');
            if (!$userId) {
                $this->requireAuth();
                $userId = $this->user['id'];
            }

            $user = User::find((int) $userId);
            if (!$user) {
                $this->error('Foydalanuvchi topilmadi', 404);
            }

            $data = [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->getDisplayNameAttribute(),
                'photo_url' => $user->photo_url,
                'stats' => $user->getProfileStats()
            ];

            $this->success($data);
        } catch (Exception $e) {
            $this->logger->error('User profile error: ' . $e->getMessage());
            $this->error('Profilni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Update profile preferences
     */
    public function updateProfile($params = []) {
        try {
            $this->requireAuth();

            $allowed = ['first_name', 'last_name', 'language_code'];
            $user = User::find($this->user['id']);

            foreach ($allowed as $field) {
                if ($this->has($field)) {
                    $user->$field = $this->input($field);
                }
            }
            $user->save();

            $this->success($user->getProfileStats(), 'Profil yangilandi');
        } catch (Exception $e) {
            $this->logger->error('Update profile error: ' . $e->getMessage());
            $this->error('Yangilab bo\'lmadi', 500);
        }
    }

    /**
     * Get user stats
     */
    public function stats($params = []) {
        try {
            $this->requireAuth();
            $user = User::find($this->user['id']);
            $this->success($user->getProfileStats());
        } catch (Exception $e) {
            $this->logger->error('User stats error: ' . $e->getMessage());
            $this->error('Statistikani yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Get user's recent games
     */
    public function games($params = []) {
        try {
            $this->requireAuth();
            $limit = min(50, max(1, (int) $this->input('limit', 20)));

            $user = User::find($this->user['id']);
            $games = $user->getRecentGames($limit);

            $this->success($games);
        } catch (Exception $e) {
            $this->logger->error('User games error: ' . $e->getMessage());
            $this->error('O\'yinlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Get user's inventory
     */
    public function inventory($params = []) {
        try {
            $this->requireAuth();
            $categoryId = $this->input('category_id');

            $user = User::find($this->user['id']);
            $inventory = $user->getInventory($categoryId ? (int) $categoryId : null);

            $this->success($inventory);
        } catch (Exception $e) {
            $this->logger->error('User inventory error: ' . $e->getMessage());
            $this->error('Inventarni yuklab bo\'lmadi', 500);
        }
    }
}
