<?php
/**
 * Shashka Game - Game Controller
 * Handles game creation, joining, moves, and game actions
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/Game.php';
require_once __DIR__ . '/../../models/User.php';

class GameController extends Controller {

    /**
     * Get available game modes
     */
    public function modes($params = []) {
        try {
            $modes = $this->cacheResponse('game_modes', function () {
                return $this->db->get("SELECT * FROM game_modes WHERE is_active = 1 ORDER BY time_control ASC");
            }, 3600);

            $this->success($modes);
        } catch (Exception $e) {
            $this->logger->error('Game modes error: ' . $e->getMessage());
            $this->error('Rejimlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Create a new game
     */
    public function create($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 10, 1); // 10 per minute

            $this->validateRequired(['mode']);
            $mode = (int) $this->input('mode');
            $opponent = $this->input('opponent', 'bot');

            $isBotGame = ($opponent === 'bot');
            $botLevel = $isBotGame ? $this->input('bot_level', 'medium') : null;

            if ($isBotGame && !in_array($botLevel, ['easy', 'medium', 'hard', 'expert'])) {
                $this->error('Noto\'g\'ri bot darajasi');
            }

            $player2Id = null;
            if (!$isBotGame && $opponent !== 'matchmaking') {
                $player2Id = (int) $opponent;
            }

            $game = Game::createGame($mode, $this->user['id'], $player2Id, $isBotGame, $botLevel);

            $this->logUserAction('game_create', ['game_id' => $game->id, 'mode' => $mode]);
            $this->success($game->getGameState(), 'O\'yin yaratildi');
        } catch (Exception $e) {
            $this->logger->error('Game create error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Join an existing waiting game
     */
    public function join($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['game_id']);

            $game = Game::find((int) $this->input('game_id'));
            if (!$game) {
                $this->error('O\'yin topilmadi', 404);
            }

            if (!$game->joinGame($this->user['id'])) {
                $this->error('O\'yinga qo\'shilib bo\'lmadi');
            }

            $this->success($game->getGameState(), 'O\'yinga qo\'shildingiz');
        } catch (Exception $e) {
            $this->logger->error('Game join error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Make a move
     */
    public function move($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 60, 1); // 60 moves per minute

            $this->validateRequired(['game_id', 'from', 'to']);

            $game = Game::find((int) $this->input('game_id'));
            if (!$game) {
                $this->error('O\'yin topilmadi', 404);
            }

            $result = $game->makeMove(
                $this->user['id'],
                $this->input('from'),
                $this->input('to')
            );

            $this->success($result);
        } catch (Exception $e) {
            $this->logger->error('Game move error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Show game state
     */
    public function show($params = []) {
        try {
            $gameId = isset($params['id']) ? (int) $params['id'] : (int) $this->input('id');
            $game = Game::find($gameId);

            if (!$game) {
                $this->error('O\'yin topilmadi', 404);
            }

            $this->success($game->getGameState());
        } catch (Exception $e) {
            $this->logger->error('Game show error: ' . $e->getMessage());
            $this->error('O\'yinni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Resign from a game
     */
    public function resign($params = []) {
        try {
            $this->requireAuth();
            $gameId = isset($params['id']) ? (int) $params['id'] : (int) $this->input('game_id');

            $game = Game::find($gameId);
            if (!$game) {
                $this->error('O\'yin topilmadi', 404);
            }

            if (!$game->resign($this->user['id'])) {
                $this->error('Taslim bo\'lib bo\'lmadi');
            }

            $this->success($game->getGameState(), 'Siz taslim bo\'ldingiz');
        } catch (Exception $e) {
            $this->logger->error('Game resign error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Offer or accept a draw
     */
    public function offerDraw($params = []) {
        try {
            $this->requireAuth();
            $gameId = isset($params['id']) ? (int) $params['id'] : (int) $this->input('game_id');

            $game = Game::find($gameId);
            if (!$game) {
                $this->error('O\'yin topilmadi', 404);
            }

            $action = $this->input('action', 'offer');
            if ($action === 'accept') {
                $game->acceptDraw($this->user['id']);
                $this->success($game->getGameState(), 'Durang qabul qilindi');
            } else {
                $game->offerDraw($this->user['id']);
                $this->success(null, 'Durang taklif qilindi');
            }
        } catch (Exception $e) {
            $this->logger->error('Game draw error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
