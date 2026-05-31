<?php
/**
 * Shashka Game - Game Model
 * Handles game logic, moves, and state management
 */

require_once __DIR__ . '/../core/Model.php';

class Game extends Model {
    
    protected $table = 'games';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'game_mode_id', 'player1_id', 'player2_id', 'is_bot_game', 'bot_level',
        'status', 'result', 'winner_id', 'resignation', 'timeout',
        'player1_time', 'player2_time', 'board_state', 'moves_count', 'current_turn',
        'rated', 'player1_rating_before', 'player2_rating_before', 
        'player1_rating_after', 'player2_rating_after', 'rating_change'
    ];
    
    protected $casts = [
        'game_mode_id' => 'integer',
        'player1_id' => 'integer',
        'player2_id' => 'integer',
        'winner_id' => 'integer',
        'player1_time' => 'integer',
        'player2_time' => 'integer',
        'moves_count' => 'integer',
        'player1_rating_before' => 'integer',
        'player2_rating_before' => 'integer',
        'player1_rating_after' => 'integer',
        'player2_rating_after' => 'integer',
        'rating_change' => 'integer',
        'is_bot_game' => 'boolean',
        'resignation' => 'boolean',
        'timeout' => 'boolean',
        'rated' => 'boolean',
        'board_state' => 'array'
    ];
    
    // Game statuses
    const STATUS_WAITING = 'waiting';
    const STATUS_ACTIVE = 'active';
    const STATUS_FINISHED = 'finished';
    const STATUS_ABORTED = 'aborted';
    
    // Game results
    const RESULT_WHITE_WINS = 'white_wins';
    const RESULT_BLACK_WINS = 'black_wins';
    const RESULT_DRAW = 'draw';
    const RESULT_ABORTED = 'aborted';
    
    // Initial board state (standard checkers)
    const INITIAL_BOARD = [
        [0, 1, 0, 1, 0, 1, 0, 1], // Row 0
        [1, 0, 1, 0, 1, 0, 1, 0], // Row 1
        [0, 1, 0, 1, 0, 1, 0, 1], // Row 2
        [0, 0, 0, 0, 0, 0, 0, 0], // Row 3 (empty)
        [0, 0, 0, 0, 0, 0, 0, 0], // Row 4 (empty)
        [2, 0, 2, 0, 2, 0, 2, 0], // Row 5
        [0, 2, 0, 2, 0, 2, 0, 2], // Row 6
        [2, 0, 2, 0, 2, 0, 2, 0]  // Row 7
    ];
    // 1 = Black pieces (top), 2 = White pieces (bottom)
    // 3 = Black king, 4 = White king
    // 0 = Empty square
    
    /**
     * Create new game
     */
    public static function createGame($gameMode, $player1Id, $player2Id = null, $isBotGame = false, $botLevel = null) {
        // Get game mode details
        $sql = "SELECT * FROM game_modes WHERE id = ? AND is_active = 1";
        $mode = self::$db->first($sql, [$gameMode]);
        
        if (!$mode) {
            throw new Exception("Invalid game mode");
        }
        
        $game = new static([
            'game_mode_id' => $gameMode,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'is_bot_game' => $isBotGame,
            'bot_level' => $botLevel,
            'status' => $player2Id ? self::STATUS_ACTIVE : self::STATUS_WAITING,
            'player1_time' => $mode['time_control'],
            'player2_time' => $mode['time_control'],
            'board_state' => self::INITIAL_BOARD,
            'moves_count' => 0,
            'current_turn' => 'white',
            'rated' => $mode['is_rated']
        ]);
        
        $game->save();
        
        // Set started time if both players are present
        if ($player2Id) {
            $game->started_at = date('Y-m-d H:i:s');
            $game->save();
        }
        
        return $game;
    }
    
    /**
     * Join waiting game
     */
    public function joinGame($playerId) {
        if ($this->status !== self::STATUS_WAITING) {
            return false;
        }
        
        if ($this->player1_id == $playerId) {
            return false; // Can't join own game
        }
        
        $this->player2_id = $playerId;
        $this->status = self::STATUS_ACTIVE;
        $this->started_at = date('Y-m-d H:i:s');
        
        // Store initial ratings for rating calculation
        if ($this->rated) {
            $player1 = User::find($this->player1_id);
            $player2 = User::find($playerId);
            
            if ($player1 && $player2) {
                $this->player1_rating_before = $player1->rating;
                $this->player2_rating_before = $player2->rating;
            }
        }
        
        return $this->save();
    }
    
    /**
     * Make a move in the game
     */
    public function makeMove($playerId, $fromSquare, $toSquare) {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new Exception("Game is not active");
        }
        
        // Validate it's player's turn
        $isPlayer1 = ($this->player1_id == $playerId);
        $isPlayer2 = ($this->player2_id == $playerId);
        
        if (!$isPlayer1 && !$isPlayer2) {
            throw new Exception("Player not in this game");
        }
        
        $expectedTurn = $isPlayer1 ? 'white' : 'black';
        if ($this->current_turn !== $expectedTurn) {
            throw new Exception("Not your turn");
        }
        
        // Validate move
        $moveResult = $this->validateAndExecuteMove($fromSquare, $toSquare, $playerId);
        
        if (!$moveResult['valid']) {
            throw new Exception($moveResult['error']);
        }
        
        // Update game state
        $this->board_state = $moveResult['newBoard'];
        $this->moves_count++;
        $this->current_turn = ($this->current_turn === 'white') ? 'black' : 'white';
        
        // Record the move
        $this->recordMove($playerId, $fromSquare, $toSquare, $moveResult);
        
        // Check for game end conditions
        $gameEndResult = $this->checkGameEnd();
        
        if ($gameEndResult['ended']) {
            $this->finishGame($gameEndResult['result'], $gameEndResult['winner']);
        } else {
            $this->save();
        }
        
        return [
            'success' => true,
            'newBoard' => $this->board_state,
            'currentTurn' => $this->current_turn,
            'movesCount' => $this->moves_count,
            'gameEnded' => $gameEndResult['ended'],
            'result' => $gameEndResult['ended'] ? $gameEndResult['result'] : null,
            'capturedPieces' => $moveResult['captured']
        ];
    }
    
    /**
     * Validate and execute move
     */
    private function validateAndExecuteMove($fromSquare, $toSquare, $playerId) {
        $board = $this->board_state;
        
        // Convert square notation (e.g., "a3") to coordinates
        $from = $this->squareToCoords($fromSquare);
        $to = $this->squareToCoords($toSquare);
        
        if (!$from || !$to) {
            return ['valid' => false, 'error' => 'Invalid square notation'];
        }
        
        $piece = $board[$from['row']][$from['col']];
        
        // Check if there's a piece at source square
        if ($piece === 0) {
            return ['valid' => false, 'error' => 'No piece at source square'];
        }
        
        // Check if piece belongs to current player
        $isPlayer1 = ($this->player1_id == $playerId);
        $playerPieces = $isPlayer1 ? [2, 4] : [1, 3]; // White: 2,4 | Black: 1,3
        
        if (!in_array($piece, $playerPieces)) {
            return ['valid' => false, 'error' => 'Not your piece'];
        }
        
        // Check if destination is empty
        if ($board[$to['row']][$to['col']] !== 0) {
            return ['valid' => false, 'error' => 'Destination square is occupied'];
        }
        
        // Validate move pattern
        $moveValidation = $this->validateMovePattern($board, $from, $to, $piece);
        
        if (!$moveValidation['valid']) {
            return $moveValidation;
        }
        
        // Execute move
        $newBoard = $board;
        $newBoard[$from['row']][$from['col']] = 0;
        $newBoard[$to['row']][$to['col']] = $piece;
        
        // Handle captures
        $captured = [];
        if ($moveValidation['capture']) {
            foreach ($moveValidation['capturedSquares'] as $captureSquare) {
                $captured[] = $newBoard[$captureSquare['row']][$captureSquare['col']];
                $newBoard[$captureSquare['row']][$captureSquare['col']] = 0;
            }
        }
        
        // Check for king promotion
        $newBoard = $this->checkKingPromotion($newBoard, $to, $piece);
        
        return [
            'valid' => true,
            'newBoard' => $newBoard,
            'capture' => $moveValidation['capture'],
            'captured' => $captured,
            'capturedSquares' => $moveValidation['capturedSquares'] ?? []
        ];
    }
    
    /**
     * Validate move pattern (diagonal movement, captures)
     */
    private function validateMovePattern($board, $from, $to, $piece) {
        $rowDiff = $to['row'] - $from['row'];
        $colDiff = $to['col'] - $from['col'];
        
        // Must be diagonal move
        if (abs($rowDiff) !== abs($colDiff)) {
            return ['valid' => false, 'error' => 'Move must be diagonal'];
        }
        
        $isKing = in_array($piece, [3, 4]);
        
        // Regular pieces can only move forward
        if (!$isKing) {
            $isWhitePiece = in_array($piece, [2, 4]);
            $expectedDirection = $isWhitePiece ? -1 : 1; // White moves up (-), Black moves down (+)
            
            if (($rowDiff > 0 && $expectedDirection < 0) || ($rowDiff < 0 && $expectedDirection > 0)) {
                return ['valid' => false, 'error' => 'Regular pieces can only move forward'];
            }
        }
        
        $distance = abs($rowDiff);
        
        // Single step move (no capture)
        if ($distance === 1) {
            return ['valid' => true, 'capture' => false];
        }
        
        // Multi-step move (capture required)
        if ($distance === 2) {
            // Check for captured piece
            $middleRow = $from['row'] + ($rowDiff / 2);
            $middleCol = $from['col'] + ($colDiff / 2);
            $middlePiece = $board[$middleRow][$middleCol];
            
            if ($middlePiece === 0) {
                return ['valid' => false, 'error' => 'No piece to capture'];
            }
            
            // Check if captured piece belongs to opponent
            $isWhitePiece = in_array($piece, [2, 4]);
            $opponentPieces = $isWhitePiece ? [1, 3] : [2, 4];
            
            if (!in_array($middlePiece, $opponentPieces)) {
                return ['valid' => false, 'error' => 'Cannot capture own piece'];
            }
            
            return [
                'valid' => true, 
                'capture' => true,
                'capturedSquares' => [['row' => $middleRow, 'col' => $middleCol]]
            ];
        }
        
        return ['valid' => false, 'error' => 'Invalid move distance'];
    }
    
    /**
     * Check for king promotion
     */
    private function checkKingPromotion($board, $to, $piece) {
        // White pieces (2) reaching row 0 become white kings (4)
        if ($piece === 2 && $to['row'] === 0) {
            $board[$to['row']][$to['col']] = 4;
        }
        
        // Black pieces (1) reaching row 7 become black kings (3)
        if ($piece === 1 && $to['row'] === 7) {
            $board[$to['row']][$to['col']] = 3;
        }
        
        return $board;
    }
    
    /**
     * Convert square notation to coordinates
     */
    private function squareToCoords($square) {
        if (strlen($square) !== 2) {
            return false;
        }
        
        $col = ord(strtolower($square[0])) - ord('a');
        $row = 8 - intval($square[1]);
        
        if ($col < 0 || $col > 7 || $row < 0 || $row > 7) {
            return false;
        }
        
        return ['row' => $row, 'col' => $col];
    }
    
    /**
     * Convert coordinates to square notation
     */
    private function coordsToSquare($row, $col) {
        if ($row < 0 || $row > 7 || $col < 0 || $col > 7) {
            return false;
        }
        
        $colLetter = chr(ord('a') + $col);
        $rowNumber = 8 - $row;
        
        return $colLetter . $rowNumber;
    }
    
    /**
     * Record move in database
     */
    private function recordMove($playerId, $fromSquare, $toSquare, $moveResult) {
        $sql = "INSERT INTO game_moves (game_id, move_number, player_id, from_square, to_square, captured_piece, move_notation, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $moveNotation = $fromSquare . '-' . $toSquare;
        if ($moveResult['capture']) {
            $moveNotation .= 'x'; // Indicate capture
        }
        
        self::$db->insert($sql, [
            $this->id,
            $this->moves_count + 1,
            $playerId,
            $fromSquare,
            $toSquare,
            $moveResult['capture'] ? 1 : 0,
            $moveNotation
        ]);
    }
    
    /**
     * Check if game has ended
     */
    private function checkGameEnd() {
        $board = $this->board_state;
        
        // Count pieces for each side
        $whitePieces = 0;
        $blackPieces = 0;
        $whiteMoves = false;
        $blackMoves = false;
        
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $piece = $board[$row][$col];
                
                if (in_array($piece, [2, 4])) { // White pieces
                    $whitePieces++;
                    if (!$whiteMoves) {
                        $whiteMoves = $this->hasValidMoves($board, $row, $col, $piece);
                    }
                } elseif (in_array($piece, [1, 3])) { // Black pieces
                    $blackPieces++;
                    if (!$blackMoves) {
                        $blackMoves = $this->hasValidMoves($board, $row, $col, $piece);
                    }
                }
            }
        }
        
        // Check win conditions
        if ($whitePieces === 0 || !$whiteMoves) {
            return ['ended' => true, 'result' => self::RESULT_BLACK_WINS, 'winner' => $this->player1_id];
        }
        
        if ($blackPieces === 0 || !$blackMoves) {
            return ['ended' => true, 'result' => self::RESULT_WHITE_WINS, 'winner' => $this->player2_id];
        }
        
        // Check for 50-move rule (100 moves without capture)
        if ($this->moves_count >= 100) {
            // Check last 50 moves for captures
            $sql = "SELECT COUNT(*) as captures FROM game_moves WHERE game_id = ? AND move_number > ? AND captured_piece = 1";
            $result = self::$db->first($sql, [$this->id, $this->moves_count - 50]);
            
            if ($result && $result['captures'] == 0) {
                return ['ended' => true, 'result' => self::RESULT_DRAW, 'winner' => null];
            }
        }
        
        return ['ended' => false];
    }
    
    /**
     * Check if piece has valid moves
     */
    private function hasValidMoves($board, $row, $col, $piece) {
        $isKing = in_array($piece, [3, 4]);
        $isWhite = in_array($piece, [2, 4]);
        
        // Possible move directions
        $directions = [];
        if ($isKing) {
            $directions = [[-1, -1], [-1, 1], [1, -1], [1, 1]]; // All diagonals
        } else {
            $forward = $isWhite ? -1 : 1;
            $directions = [[$forward, -1], [$forward, 1]]; // Forward diagonals only
        }
        
        foreach ($directions as $dir) {
            $newRow = $row + $dir[0];
            $newCol = $col + $dir[1];
            
            // Check bounds
            if ($newRow < 0 || $newRow > 7 || $newCol < 0 || $newCol > 7) {
                continue;
            }
            
            // Check if square is empty (simple move)
            if ($board[$newRow][$newCol] === 0) {
                return true;
            }
            
            // Check for capture opportunity
            $captureRow = $newRow + $dir[0];
            $captureCol = $newCol + $dir[1];
            
            if ($captureRow >= 0 && $captureRow <= 7 && $captureCol >= 0 && $captureCol <= 7) {
                if ($board[$captureRow][$captureCol] === 0) {
                    // Check if middle piece is opponent's
                    $middlePiece = $board[$newRow][$newCol];
                    $opponentPieces = $isWhite ? [1, 3] : [2, 4];
                    
                    if (in_array($middlePiece, $opponentPieces)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Finish the game
     */
    public function finishGame($result, $winnerId = null) {
        $this->status = self::STATUS_FINISHED;
        $this->result = $result;
        $this->winner_id = $winnerId;
        $this->finished_at = date('Y-m-d H:i:s');
        
        // Update player statistics and ratings
        if (!$this->is_bot_game) {
            $this->updatePlayerStats($result, $winnerId);
        }
        
        $this->save();
        
        // Clear game from active games cache
        self::$cache->delete("active_games");
        self::$cache->delete("user_active_game:{$this->player1_id}");
        if ($this->player2_id) {
            self::$cache->delete("user_active_game:{$this->player2_id}");
        }
        
        return true;
    }
    
    /**
     * Update player statistics and ratings
     */
    private function updatePlayerStats($result, $winnerId) {
        $player1 = User::find($this->player1_id);
        $player2 = User::find($this->player2_id);
        
        if (!$player1 || !$player2) {
            return;
        }
        
        // Update game statistics
        switch ($result) {
            case self::RESULT_WHITE_WINS:
                $player2->updateGameStats('win');
                $player1->updateGameStats('loss');
                break;
            case self::RESULT_BLACK_WINS:
                $player1->updateGameStats('win');
                $player2->updateGameStats('loss');
                break;
            case self::RESULT_DRAW:
                $player1->updateGameStats('draw');
                $player2->updateGameStats('draw');
                break;
        }
        
        // Update ratings if game is rated
        if ($this->rated) {
            $this->calculateAndUpdateRatings($player1, $player2, $result);
        }
        
        // Award coins
        $this->awardGameCoins($player1, $player2, $result);
    }
    
    /**
     * Calculate and update Elo ratings
     */
    private function calculateAndUpdateRatings($player1, $player2, $result) {
        $rating1 = $this->player1_rating_before;
        $rating2 = $this->player2_rating_before;
        
        // Calculate expected scores
        $expected1 = 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
        $expected2 = 1 - $expected1;
        
        // Determine actual scores
        switch ($result) {
            case self::RESULT_BLACK_WINS: // Player 1 wins
                $score1 = 1;
                $score2 = 0;
                break;
            case self::RESULT_WHITE_WINS: // Player 2 wins
                $score1 = 0;
                $score2 = 1;
                break;
            case self::RESULT_DRAW:
                $score1 = 0.5;
                $score2 = 0.5;
                break;
            default:
                return; // No rating change for aborted games
        }
        
        // Determine K-factors based on rating and games played
        $k1 = $this->getKFactor($rating1, $player1->total_games);
        $k2 = $this->getKFactor($rating2, $player2->total_games);
        
        // Calculate new ratings
        $newRating1 = round($rating1 + $k1 * ($score1 - $expected1));
        $newRating2 = round($rating2 + $k2 * ($score2 - $expected2));
        
        // Ensure ratings don't go below 100
        $newRating1 = max(100, $newRating1);
        $newRating2 = max(100, $newRating2);
        
        // Update game record
        $this->player1_rating_after = $newRating1;
        $this->player2_rating_after = $newRating2;
        $this->rating_change = $newRating1 - $rating1;
        
        // Update player ratings
        $player1->updateRating($newRating1, $this->id);
        $player2->updateRating($newRating2, $this->id);
    }
    
    /**
     * Get K-factor for Elo rating calculation
     */
    private function getKFactor($rating, $gamesPlayed) {
        if ($gamesPlayed < 20) {
            return 40; // New players
        } elseif ($rating < 2000) {
            return 30; // Medium level
        } else {
            return 20; // High level
        }
    }
    
    /**
     * Award coins based on game result
     */
    private function awardGameCoins($player1, $player2, $result) {
        $app = App::getInstance();
        $config = $app->config('app.economy.coins');
        
        switch ($result) {
            case self::RESULT_BLACK_WINS: // Player 1 wins
                $player1->addCurrency('coins', $config['win'], 'game_reward', $this->id);
                $player2->addCurrency('coins', $config['loss'], 'game_reward', $this->id);
                break;
            case self::RESULT_WHITE_WINS: // Player 2 wins
                $player1->addCurrency('coins', $config['loss'], 'game_reward', $this->id);
                $player2->addCurrency('coins', $config['win'], 'game_reward', $this->id);
                break;
            case self::RESULT_DRAW:
                $player1->addCurrency('coins', $config['draw'], 'game_reward', $this->id);
                $player2->addCurrency('coins', $config['draw'], 'game_reward', $this->id);
                break;
        }
    }
    
    /**
     * Resign game
     */
    public function resign($playerId) {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        
        if ($this->player1_id != $playerId && $this->player2_id != $playerId) {
            return false;
        }
        
        $this->resignation = true;
        
        // Determine winner (opponent of resigning player)
        if ($this->player1_id == $playerId) {
            $result = self::RESULT_WHITE_WINS;
            $winnerId = $this->player2_id;
        } else {
            $result = self::RESULT_BLACK_WINS;
            $winnerId = $this->player1_id;
        }
        
        return $this->finishGame($result, $winnerId);
    }
    
    /**
     * Offer draw
     */
    public function offerDraw($playerId) {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        
        // Implementation would involve storing draw offers and notifications
        // For now, return success
        return true;
    }
    
    /**
     * Accept draw
     */
    public function acceptDraw($playerId) {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        
        return $this->finishGame(self::RESULT_DRAW);
    }
    
    /**
     * Get game moves
     */
    public function getMoves() {
        $cacheKey = "game_moves:{$this->id}";
        $moves = self::$cache->get($cacheKey);
        
        if ($moves === null) {
            $sql = "SELECT * FROM game_moves WHERE game_id = ? ORDER BY move_number ASC";
            $moves = self::$db->get($sql, [$this->id]);
            
            // Cache for 1 hour if game is finished
            $ttl = ($this->status === self::STATUS_FINISHED) ? 3600 : 60;
            self::$cache->set($cacheKey, $moves, $ttl);
        }
        
        return $moves;
    }
    
    /**
     * Get game state for client
     */
    public function getGameState() {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'result' => $this->result,
            'board' => $this->board_state,
            'current_turn' => $this->current_turn,
            'moves_count' => $this->moves_count,
            'player1_time' => $this->player1_time,
            'player2_time' => $this->player2_time,
            'is_bot_game' => $this->is_bot_game,
            'bot_level' => $this->bot_level,
            'moves' => $this->getMoves()
        ];
    }
}