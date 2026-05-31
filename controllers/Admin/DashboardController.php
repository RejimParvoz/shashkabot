<?php
/**
 * Shashka Game - Admin Dashboard Controller
 * Provides real-time statistics and overview for administrators
 */

require_once __DIR__ . '/../../core/Controller.php';

class DashboardController extends Controller {

    /**
     * Check admin authentication
     */
    private function requireAdmin() {
        $this->requireAuth();

        $admin = $this->db->first(
            "SELECT * FROM admin_users WHERE id = ? AND is_active = 1",
            [$this->user['id']]
        );

        // Fallback: check by telegram_id mapping if available
        if (!$admin) {
            $this->error('Admin huquqi talab qilinadi', 403);
        }

        return $admin;
    }

    /**
     * Dashboard index with real-time stats
     */
    public function index($params = []) {
        try {
            $this->requireAdmin();

            $stats = $this->cacheResponse('admin_dashboard_stats', function () {
                return [
                    'users' => $this->getUserStats(),
                    'games' => $this->getGameStats(),
                    'economy' => $this->getEconomyStats(),
                    'tournaments' => $this->getTournamentStats()
                ];
            }, 60);

            $stats['recent_activity'] = $this->getRecentActivity();

            $this->success($stats);
        } catch (Exception $e) {
            $this->logger->error('Admin dashboard error: ' . $e->getMessage());
            $this->error('Dashboardni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * User statistics
     */
    private function getUserStats() {
        $total = $this->db->first("SELECT COUNT(*) as count FROM users");
        $active = $this->db->first(
            "SELECT COUNT(*) as count FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        $online = $this->db->first("SELECT COUNT(*) as count FROM users WHERE is_online = 1");
        $newToday = $this->db->first(
            "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()"
        );

        return [
            'total' => (int) $total['count'],
            'active_24h' => (int) $active['count'],
            'online' => (int) $online['count'],
            'new_today' => (int) $newToday['count']
        ];
    }

    /**
     * Game statistics
     */
    private function getGameStats() {
        $active = $this->db->first(
            "SELECT COUNT(*) as count FROM games WHERE status = 'active'"
        );
        $today = $this->db->first(
            "SELECT COUNT(*) as count FROM games WHERE DATE(created_at) = CURDATE()"
        );
        $total = $this->db->first("SELECT COUNT(*) as count FROM games");

        return [
            'active' => (int) $active['count'],
            'today' => (int) $today['count'],
            'total' => (int) $total['count']
        ];
    }

    /**
     * Economy statistics (revenue from payments)
     */
    private function getEconomyStats() {
        $revenue = $this->db->first(
            "SELECT COALESCE(SUM(stars_amount), 0) as stars FROM payments WHERE status = 'completed'"
        );
        $revenueToday = $this->db->first(
            "SELECT COALESCE(SUM(stars_amount), 0) as stars FROM payments
             WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
        );
        $activeVip = $this->db->first(
            "SELECT COUNT(*) as count FROM vip_subscriptions WHERE is_active = 1 AND end_date > NOW()"
        );

        return [
            'total_stars' => (int) $revenue['stars'],
            'stars_today' => (int) $revenueToday['stars'],
            'active_vip' => (int) $activeVip['count']
        ];
    }

    /**
     * Tournament statistics
     */
    private function getTournamentStats() {
        $active = $this->db->first(
            "SELECT COUNT(*) as count FROM tournaments WHERE status IN ('registration', 'active')"
        );

        return ['active' => (int) $active['count']];
    }

    /**
     * Recent activity feed
     */
    private function getRecentActivity() {
        return $this->db->get(
            "SELECT id, username, first_name, rating, created_at
             FROM users ORDER BY created_at DESC LIMIT 10"
        );
    }
}
