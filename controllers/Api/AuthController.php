<?php
/**
 * Shashka Game - Authentication Controller
 * Handles user authentication via Telegram
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';

class AuthController extends Controller {
    
    /**
     * Authenticate user via Telegram Mini App
     */
    public function telegram($params = []) {
        try {
            // Apply rate limiting
            $this->applyRateLimit(null, 5, 5); // 5 attempts per 5 minutes
            
            // Validate required fields
            $this->validateRequired(['init_data']);
            
            $initData = $this->input('init_data');
            
            // Authenticate with Telegram
            $user = $this->security->authenticateTelegram($initData);
            
            if (!$user) {
                $this->error('Invalid Telegram authentication data', 401);
            }
            
            // Check if user is banned
            if ($user['status'] === 'banned') {
                $this->error('Your account has been banned', 403);
            }
            
            // Update user's online status
            $userModel = new User($user);
            $userModel->setOnline();
            
            // Generate JWT token
            $tokenPayload = [
                'user_id' => $user['id'],
                'telegram_id' => $user['telegram_id'],
                'username' => $user['username']
            ];
            
            $token = $this->security->generateJwtToken($tokenPayload);
            
            // Log successful login
            $this->logUserAction('login', [
                'method' => 'telegram',
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            // Get user profile data
            $profileData = $this->getUserProfileData($user['id']);
            
            $this->success([
                'token' => $token,
                'user' => $profileData,
                'expires_at' => time() + $this->app->config('app.jwt_ttl', 3600)
            ], 'Authentication successful');
            
        } catch (Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());
            $this->error('Authentication failed', 500);
        }
    }
    
    /**
     * Refresh authentication token
     */
    public function refresh($params = []) {
        try {
            $this->requireAuth();
            
            // Generate new token
            $tokenPayload = [
                'user_id' => $this->user['id'],
                'telegram_id' => $this->user['telegram_id'],
                'username' => $this->user['username']
            ];
            
            $newToken = $this->security->generateJwtToken($tokenPayload);
            
            $this->success([
                'token' => $newToken,
                'expires_at' => time() + $this->app->config('app.jwt_ttl', 3600)
            ], 'Token refreshed successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Token refresh error: ' . $e->getMessage());
            $this->error('Token refresh failed', 500);
        }
    }
    
    /**
     * Logout user
     */
    public function logout($params = []) {
        try {
            if ($this->user) {
                // Set user offline
                $userModel = new User($this->user);
                $userModel->setOffline();
                
                // Log logout
                $this->logUserAction('logout', [
                    'ip' => $this->getClientIp()
                ]);
            }
            
            $this->success(null, 'Logged out successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Logout error: ' . $e->getMessage());
            $this->error('Logout failed', 500);
        }
    }
    
    /**
     * Get current user profile
     */
    public function profile($params = []) {
        try {
            $this->requireAuth();
            
            $profileData = $this->getUserProfileData($this->user['id']);
            
            $this->success($profileData);
            
        } catch (Exception $e) {
            $this->logger->error('Profile fetch error: ' . $e->getMessage());
            $this->error('Failed to fetch profile', 500);
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($params = []) {
        try {
            $this->requireAuth();
            $this->requireStatus('active');
            
            // Validate CSRF token
            $this->validateCsrf();
            
            // Get allowed fields for update
            $allowedFields = ['first_name', 'last_name', 'language_code'];
            $updateData = $this->only($allowedFields);
            
            if (empty($updateData)) {
                $this->error('No valid fields to update');
            }
            
            // Validate field lengths
            if (isset($updateData['first_name']) && strlen($updateData['first_name']) > 100) {
                $this->error('First name too long (max 100 characters)');
            }
            
            if (isset($updateData['last_name']) && strlen($updateData['last_name']) > 100) {
                $this->error('Last name too long (max 100 characters)');
            }
            
            if (isset($updateData['language_code']) && !in_array($updateData['language_code'], ['uz', 'ru', 'en'])) {
                $this->error('Invalid language code');
            }
            
            // Update user
            $user = User::find($this->user['id']);
            if (!$user) {
                $this->error('User not found', 404);
            }
            
            foreach ($updateData as $field => $value) {
                $user->$field = $value;
            }
            
            $user->save();
            
            // Log profile update
            $this->logUserAction('profile_update', $updateData);
            
            // Return updated profile
            $profileData = $this->getUserProfileData($this->user['id']);
            
            $this->success($profileData, 'Profile updated successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Profile update error: ' . $e->getMessage());
            $this->error('Failed to update profile', 500);
        }
    }
    
    /**
     * Check authentication status
     */
    public function status($params = []) {
        try {
            if ($this->user) {
                $this->success([
                    'authenticated' => true,
                    'user_id' => $this->user['id'],
                    'username' => $this->user['username'],
                    'status' => $this->user['status']
                ]);
            } else {
                $this->success([
                    'authenticated' => false
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Auth status error: ' . $e->getMessage());
            $this->error('Failed to check authentication status', 500);
        }
    }
    
    /**
     * Delete user account
     */
    public function deleteAccount($params = []) {
        try {
            $this->requireAuth();
            
            // Validate CSRF token
            $this->validateCsrf();
            
            // Require confirmation
            $confirmation = $this->input('confirmation');
            if ($confirmation !== 'DELETE_MY_ACCOUNT') {
                $this->error('Invalid confirmation. Please type "DELETE_MY_ACCOUNT" to confirm.');
            }
            
            $userId = $this->user['id'];
            
            // Soft delete - change status to banned and clear sensitive data
            $user = User::find($userId);
            if (!$user) {
                $this->error('User not found', 404);
            }
            
            // Update user status and clear data
            $user->status = 'banned';
            $user->username = null;
            $user->first_name = 'Deleted User';
            $user->last_name = '';
            $user->photo_url = null;
            $user->save();
            
            // Log account deletion
            $this->logUserAction('account_deletion', [
                'ip' => $this->getClientIp(),
                'timestamp' => time()
            ]);
            
            $this->success(null, 'Account deleted successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Account deletion error: ' . $e->getMessage());
            $this->error('Failed to delete account', 500);
        }
    }
    
    /**
     * Get comprehensive user profile data
     */
    private function getUserProfileData($userId) {
        $cacheKey = "user_profile_full:{$userId}";
        
        return $this->cacheResponse($cacheKey, function() use ($userId) {
            $user = User::find($userId);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get user stats
            $profileStats = $user->getProfileStats();
            
            // Get VIP info
            $vipInfo = $user->hasActiveVip();
            
            // Get recent games (last 5)
            $recentGames = $user->getRecentGames(5);
            
            // Get equipped items
            $equippedItems = $user->getEquippedItems();
            
            // Get achievements count
            $achievementsCount = $this->db->first(
                "SELECT COUNT(*) as total, COUNT(CASE WHEN completed = 1 THEN 1 END) as completed 
                 FROM user_achievements WHERE user_id = ?", 
                [$userId]
            );
            
            // Get friends count
            $friendsCount = $this->db->first(
                "SELECT COUNT(*) as count FROM friends 
                 WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'", 
                [$userId, $userId]
            );
            
            return [
                'id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'username' => $user->username,
                'display_name' => $user->getDisplayNameAttribute(),
                'full_name' => $user->getFullNameAttribute(),
                'photo_url' => $user->photo_url,
                'language_code' => $user->language_code,
                'status' => $user->status,
                'is_online' => $user->is_online,
                'last_active' => $user->last_active,
                'created_at' => $user->created_at,
                
                // Game statistics
                'rating' => $profileStats['rating'],
                'league' => $profileStats['league'],
                'leaderboard_position' => $profileStats['leaderboard_position'],
                'total_games' => $profileStats['total_games'],
                'wins' => $profileStats['wins'],
                'losses' => $profileStats['losses'],
                'draws' => $profileStats['draws'],
                'win_rate' => $profileStats['win_rate'],
                'win_streak' => $profileStats['win_streak'],
                'max_win_streak' => $profileStats['max_win_streak'],
                
                // Economy
                'diamonds' => $profileStats['diamonds'],
                'coins' => $profileStats['coins'],
                
                // VIP status
                'vip_level' => $profileStats['vip_level'],
                'vip_expires_at' => $vipInfo ? $vipInfo['end_date'] : null,
                
                // Social stats
                'friends_count' => $friendsCount ? (int)$friendsCount['count'] : 0,
                'achievements_total' => $achievementsCount ? (int)$achievementsCount['total'] : 0,
                'achievements_completed' => $achievementsCount ? (int)$achievementsCount['completed'] : 0,
                
                // Recent activity
                'recent_games' => $recentGames,
                'equipped_items' => $equippedItems
            ];
        }, 300); // Cache for 5 minutes
    }
}