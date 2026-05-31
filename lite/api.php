<?php
/**
 * Shashka Lite - API (mod_rewrite KERAK EMAS)
 * Chaqirish: https://topkons.uz/shashka/api.php?action=XXX
 *
 * action lar:
 *   auth          - POST init_data -> foydalanuvchini tasdiqlash
 *   profile       - POST init_data -> profil + statistika
 *   leaderboard   - top o'yinchilar (GET)
 *   save_result   - POST init_data, result, mode, bot_level, moves -> natijani saqlash
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';

// CORS (Telegram Mini App uchun)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// POST tanasini o'qish (JSON yoki form)
function input() {
    static $data = null;
    if ($data === null) {
        $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($ct, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) { $data = []; }
        } else {
            $data = $_POST;
        }
    }
    return $data;
}

// Joriy foydalanuvchini olish (initData orqali)
function currentUser() {
    $in = input();
    $initData = isset($in['init_data']) ? $in['init_data'] : '';
    $tgUser = tgValidateInitData($initData);

    // DEV rejim: agar initData bo'sh bo'lsa va localda test qilinsa
    if (!$tgUser && isset($in['dev_telegram_id'])) {
        $tgUser = [
            'id' => (int)$in['dev_telegram_id'],
            'first_name' => isset($in['dev_name']) ? $in['dev_name'] : 'Test',
        ];
    }

    if (!$tgUser) {
        jsonResponse(['success' => false, 'error' => 'Avtorizatsiya xatosi'], 401);
    }
    return tgUpsertUser($tgUser);
}

// Reyting o'zgarishini hisoblash (sodda Elo, bot uchun)
function calcRatingChange($result, $botLevel) {
    // Bot darajasiga qarab kutilgan natija
    $expected = [
        'easy' => 0.8, 'medium' => 0.6, 'hard' => 0.4, 'expert' => 0.25,
    ];
    $exp = isset($expected[$botLevel]) ? $expected[$botLevel] : 0.5;
    $score = ($result === 'win') ? 1.0 : (($result === 'draw') ? 0.5 : 0.0);
    $k = 32;
    return (int) round($k * ($score - $exp));
}

switch ($action) {

    case 'auth':
        $user = currentUser();
        jsonResponse(['success' => true, 'user' => publicUser($user)]);
        break;

    case 'profile':
        $user = currentUser();
        $rank = dbFirst("SELECT COUNT(*)+1 AS r FROM users WHERE rating > ?", [$user['rating']]);
        $data = publicUser($user);
        $data['rank'] = (int) $rank['r'];
        $data['win_rate'] = $user['total_games'] > 0
            ? round($user['wins'] / $user['total_games'] * 100, 1) : 0;
        jsonResponse(['success' => true, 'profile' => $data]);
        break;

    case 'leaderboard':
        $rows = dbAll(
            "SELECT telegram_id, username, first_name, photo_url, rating, wins, losses, draws
             FROM users ORDER BY rating DESC LIMIT 100"
        );
        $rank = 1;
        foreach ($rows as &$r) { $r['rank'] = $rank++; }
        unset($r);
        jsonResponse(['success' => true, 'leaderboard' => $rows]);
        break;

    case 'save_result':
        $user = currentUser();
        $in = input();
        $result = isset($in['result']) ? $in['result'] : '';
        if (!in_array($result, ['win', 'loss', 'draw'], true)) {
            jsonResponse(['success' => false, 'error' => 'Noto\'g\'ri natija'], 400);
        }
        $mode = isset($in['mode']) ? $in['mode'] : 'classic';
        $botLevel = isset($in['bot_level']) ? $in['bot_level'] : 'medium';
        $moves = isset($in['moves']) ? (int)$in['moves'] : 0;

        $ratingChange = calcRatingChange($result, $botLevel);
        $newRating = max(100, (int)$user['rating'] + $ratingChange);

        $coins = ($result === 'win') ? WIN_COINS : (($result === 'draw') ? DRAW_COINS : LOSS_COINS);

        // Statistikani yangilash
        $winInc = $result === 'win' ? 1 : 0;
        $lossInc = $result === 'loss' ? 1 : 0;
        $drawInc = $result === 'draw' ? 1 : 0;

        dbExec(
            "UPDATE users SET rating = ?, coins = coins + ?, total_games = total_games + 1,
             wins = wins + ?, losses = losses + ?, draws = draws + ?, last_active = NOW()
             WHERE id = ?",
            [$newRating, $coins, $winInc, $lossInc, $drawInc, $user['id']]
        );

        dbInsert(
            "INSERT INTO games (user_id, mode, bot_level, result, moves, rating_change, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$user['id'], $mode, $botLevel, $result, $moves, $ratingChange]
        );

        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse([
            'success' => true,
            'rating_change' => $ratingChange,
            'coins_earned' => $coins,
            'user' => publicUser($updated),
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Noma\'lum action'], 404);
}

/** Foydalanuvchining ochiq ma'lumotlari */
function publicUser($u) {
    return [
        'id' => (int)$u['id'],
        'telegram_id' => (int)$u['telegram_id'],
        'username' => $u['username'],
        'first_name' => $u['first_name'],
        'photo_url' => $u['photo_url'],
        'rating' => (int)$u['rating'],
        'coins' => (int)$u['coins'],
        'diamonds' => (int)$u['diamonds'],
        'total_games' => (int)$u['total_games'],
        'wins' => (int)$u['wins'],
        'losses' => (int)$u['losses'],
        'draws' => (int)$u['draws'],
    ];
}
