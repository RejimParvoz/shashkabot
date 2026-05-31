<?php
/**
 * Shashka Game - Leaderboard Controller
 * Handles global and league-based rankings
 */

require_once __DIR__ . '/../../core/Controller.php';

class LeaderboardController extends Controller {

    /**
     * Get global leaderboard (top players)
     */
    public function index($params = []) {
        try {
            $limit = min(100, max(1, (int) $this->input('limit', 100)));

            $players = $this->cacheResponse("leaderboard_global_{$limit}", function () use ($limit) {
                return $this->db->get(
                    "SELECT id, username, first_name, photo_url, rating, league,
                            total_games, wins, losses, draws
                     FROM users
                     WHERE status = 'active'
                     ORDER BY rating DESC
                     LIMIT ?",
                    [$limit]
                );
            }, 300);

            // Add rank
            $rank = 1;
            foreach ($players as &$p) {
                $p['rank'] = $rank++;
            }
            unset($p);

            $this->success($players);
        } catch (Exception $e) {
            $this->logger->error('Leaderboard error: ' . $e->getMessage());
            $this->error('Reytingni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Get league-specific leaderboard
     */
    public function league($params = []) {
        try {
            $league = isset($params['league']) ? $params['league'] : $this->input('league', 'bronze');
            $validLeagues = ['bronze', 'silver', 'gold', 'diamond', 'royal'];

            if (!in_array($league, $validLeagues)) {
                $this->error('Noto\'g\'ri liga');
            }

            $limit = min(100, max(1, (int) $this->input('limit', 100)));

            $players = $this->cacheResponse("leaderboard_{$league}_{$limit}", function () use ($league, $limit) {
                return $this->db->get(
                    "SELECT id, username, first_name, photo_url, rating, league,
                            total_games, wins, losses, draws
                     FROM users
                     WHERE status = 'active' AND league = ?
                     ORDER BY rating DESC
                     LIMIT ?",
                    [$league, $limit]
                );
            }, 300);

            $rank = 1;
            foreach ($players as &$p) {
                $p['rank'] = $rank++;
            }
            unset($p);

            $this->success($players);
        } catch (Exception $e) {
            $this->logger->error('League leaderboard error: ' . $e->getMessage());
            $this->error('Reytingni yuklab bo\'lmadi', 500);
        }
    }
}
