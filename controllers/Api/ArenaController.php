<?php
/**
 * Shashka Game - Arena Controller
 * Handles arena season info, rankings, and joining
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/Arena.php';

class ArenaController extends Controller {

    /**
     * Get current arena season info + user's standing
     */
    public function index($params = []) {
        try {
            $season = Arena::getActiveSeason();
            if (!$season) {
                $this->success(['active' => false], 'Hozir faol arena yo\'q');
            }

            $arena = new Arena($season);
            $data = [
                'active' => true,
                'season' => $season,
                'standing' => null
            ];

            if ($this->user) {
                $data['standing'] = $arena->getPlayerStanding($this->user['id']);
            }

            $this->success($data);
        } catch (Exception $e) {
            $this->logger->error('Arena index error: ' . $e->getMessage());
            $this->error('Arenani yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Get arena rankings
     */
    public function rankings($params = []) {
        try {
            $season = Arena::getActiveSeason();
            if (!$season) {
                $this->success([]);
            }

            $arena = new Arena($season);
            $limit = min(100, max(1, (int) $this->input('limit', 100)));
            $rankings = $arena->getRankings($limit);

            $this->success($rankings);
        } catch (Exception $e) {
            $this->logger->error('Arena rankings error: ' . $e->getMessage());
            $this->error('Reytingni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Join the current arena season
     */
    public function join($params = []) {
        try {
            $this->requireAuth();

            $season = Arena::getActiveSeason();
            if (!$season) {
                $this->error('Hozir faol arena yo\'q', 400);
            }

            $arena = new Arena($season);
            $arena->joinSeason($this->user['id']);

            $this->success(null, 'Arenaga qo\'shildingiz!');
        } catch (Exception $e) {
            $this->logger->error('Arena join error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
