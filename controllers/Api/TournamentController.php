<?php
/**
 * Shashka Game - Tournament Controller
 * Handles tournament listing, registration, and brackets
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/Tournament.php';
require_once __DIR__ . '/../../models/User.php';

class TournamentController extends Controller {

    /**
     * List active and upcoming tournaments
     */
    public function index($params = []) {
        try {
            $tournaments = Tournament::getActiveTournaments();
            $this->success($tournaments);
        } catch (Exception $e) {
            $this->logger->error('Tournament list error: ' . $e->getMessage());
            $this->error('Turnirlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Show a single tournament with players
     */
    public function show($params = []) {
        try {
            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('id');
            $tournament = Tournament::find($id);

            if (!$tournament) {
                $this->error('Turnir topilmadi', 404);
            }

            $data = $tournament->toArray();
            $data['players'] = $tournament->getPlayers();

            $this->success($data);
        } catch (Exception $e) {
            $this->logger->error('Tournament show error: ' . $e->getMessage());
            $this->error('Turnirni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Join a tournament
     */
    public function join($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 5, 1);

            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('tournament_id');
            $tournament = Tournament::find($id);

            if (!$tournament) {
                $this->error('Turnir topilmadi', 404);
            }

            $tournament->registerPlayer($this->user['id']);

            $this->logUserAction('tournament_join', ['tournament_id' => $id]);
            $this->success(null, 'Turnirga ro\'yxatdan o\'tdingiz!');
        } catch (Exception $e) {
            $this->logger->error('Tournament join error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get tournament bracket / players standing
     */
    public function bracket($params = []) {
        try {
            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('id');
            $tournament = Tournament::find($id);

            if (!$tournament) {
                $this->error('Turnir topilmadi', 404);
            }

            $this->success([
                'tournament' => $tournament->toArray(),
                'players' => $tournament->getPlayers()
            ]);
        } catch (Exception $e) {
            $this->logger->error('Tournament bracket error: ' . $e->getMessage());
            $this->error('Bracketni yuklab bo\'lmadi', 500);
        }
    }
}
