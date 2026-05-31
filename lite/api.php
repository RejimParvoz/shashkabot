<?php
/**
 * Shashka Lite - API (mod_rewrite KERAK EMAS)
 * Chaqirish: https://topkons.uz/shashka/api.php?action=XXX
 *
 * action lar:
 *   auth, profile, leaderboard, save_result
 *   shop_list, shop_buy, shop_equip
 *   invite_info
 *   match_find, match_state, match_move, match_resign, match_cancel
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/engine.php';

// CORS (Telegram Mini App uchun)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = isset($_GET['action']) ? $_GET['action'] : '';

function input() {
    static $data = null;
    if ($data === null) {
        $ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($ct, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) { $data = []; }
        } else { $data = $_POST; }
    }
    return $data;
}

function currentUser() {
    $in = input();
    $initData = isset($in['init_data']) ? $in['init_data'] : '';
    $tgUser = tgValidateInitData($initData);

    // DEV rejim (faqat test uchun)
    if (!$tgUser && isset($in['dev_telegram_id'])) {
        $tgUser = ['id' => (int)$in['dev_telegram_id'], 'first_name' => isset($in['dev_name']) ? $in['dev_name'] : 'Test'];
    }
    if (!$tgUser) {
        jsonResponse(['success' => false, 'error' => 'Avtorizatsiya xatosi'], 401);
    }

    // Referral (Mini App start_param: "ref_123" yoki "123")
    $refCode = null;
    if (isset($in['start_param'])) {
        $sp = str_replace('ref_', '', $in['start_param']);
        if (is_numeric($sp)) { $refCode = $sp; }
    }
    return tgUpsertUser($tgUser, $refCode);
}

function publicUser($u) {
    return [
        'id' => (int)$u['id'], 'telegram_id' => (int)$u['telegram_id'],
        'username' => $u['username'], 'first_name' => $u['first_name'], 'photo_url' => $u['photo_url'],
        'rating' => (int)$u['rating'], 'coins' => (int)$u['coins'], 'diamonds' => (int)$u['diamonds'],
        'total_games' => (int)$u['total_games'], 'wins' => (int)$u['wins'],
        'losses' => (int)$u['losses'], 'draws' => (int)$u['draws'],
        'equipped_board' => isset($u['equipped_board']) ? $u['equipped_board'] : 'classic',
        'equipped_piece' => isset($u['equipped_piece']) ? $u['equipped_piece'] : 'classic',
        'referral_count' => isset($u['referral_count']) ? (int)$u['referral_count'] : 0,
    ];
}

function calcRatingChange($result, $botLevel) {
    $expected = ['easy' => 0.8, 'medium' => 0.6, 'hard' => 0.4, 'expert' => 0.25];
    $exp = isset($expected[$botLevel]) ? $expected[$botLevel] : 0.5;
    $score = ($result === 'win') ? 1.0 : (($result === 'draw') ? 0.5 : 0.0);
    return (int) round(32 * ($score - $exp));
}

// PvP Elo
function eloChange($myRating, $oppRating, $score) {
    $exp = 1 / (1 + pow(10, ($oppRating - $myRating) / 400));
    return (int) round(32 * ($score - $exp));
}

switch ($action) {

    /* ---------------- ASOSIY ---------------- */
    case 'auth':
        $user = currentUser();
        jsonResponse(['success' => true, 'user' => publicUser($user)]);
        break;

    case 'profile':
        $user = currentUser();
        $rank = dbFirst("SELECT COUNT(*)+1 AS r FROM users WHERE rating > ?", [$user['rating']]);
        $data = publicUser($user);
        $data['rank'] = (int) $rank['r'];
        $data['win_rate'] = $user['total_games'] > 0 ? round($user['wins'] / $user['total_games'] * 100, 1) : 0;
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

    case 'save_result':  // faqat BOT o'yinlari uchun
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

        dbExec(
            "UPDATE users SET rating = ?, coins = coins + ?, total_games = total_games + 1,
             wins = wins + ?, losses = losses + ?, draws = draws + ?, last_active = NOW() WHERE id = ?",
            [$newRating, $coins, $result==='win'?1:0, $result==='loss'?1:0, $result==='draw'?1:0, $user['id']]
        );
        dbInsert(
            "INSERT INTO games (user_id, mode, bot_level, opponent_type, result, moves, rating_change, created_at)
             VALUES (?, ?, ?, 'bot', ?, ?, ?, NOW())",
            [$user['id'], $mode, $botLevel, $result, $moves, $ratingChange]
        );
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'rating_change' => $ratingChange, 'coins_earned' => $coins, 'user' => publicUser($updated)]);
        break;

    /* ---------------- DO'KON (faqat tanga) ---------------- */
    case 'shop_list':
        $user = currentUser();
        $items = dbAll("SELECT code, name, type, price_coins, data, sort FROM shop_items ORDER BY type, sort");
        $owned = dbAll("SELECT item_code FROM user_items WHERE user_id = ?", [$user['id']]);
        $ownedCodes = array_column($owned, 'item_code');
        foreach ($items as &$it) {
            $it['price_coins'] = (int)$it['price_coins'];
            // bepul buyumlar avtomatik egalik qilinadi
            $it['owned'] = ($it['price_coins'] === 0) || in_array($it['code'], $ownedCodes);
            $it['equipped'] = ($it['type'] === 'board' && $it['code'] === $user['equipped_board'])
                || ($it['type'] === 'piece' && $it['code'] === $user['equipped_piece']);
        }
        unset($it);
        jsonResponse(['success' => true, 'items' => $items, 'coins' => (int)$user['coins']]);
        break;

    case 'shop_buy':
        $user = currentUser();
        $in = input();
        $code = isset($in['code']) ? $in['code'] : '';
        $item = dbFirst("SELECT * FROM shop_items WHERE code = ?", [$code]);
        if (!$item) jsonResponse(['success' => false, 'error' => 'Buyum topilmadi'], 404);

        $already = dbFirst("SELECT id FROM user_items WHERE user_id = ? AND item_code = ?", [$user['id'], $code]);
        if ($already || (int)$item['price_coins'] === 0) {
            jsonResponse(['success' => false, 'error' => 'Allaqachon sizniki'], 400);
        }
        if ((int)$user['coins'] < (int)$item['price_coins']) {
            jsonResponse(['success' => false, 'error' => 'Tanga yetarli emas'], 400);
        }

        db()->beginTransaction();
        try {
            dbExec("UPDATE users SET coins = coins - ? WHERE id = ?", [(int)$item['price_coins'], $user['id']]);
            dbInsert("INSERT INTO user_items (user_id, item_code, bought_at) VALUES (?, ?, NOW())", [$user['id'], $code]);
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            jsonResponse(['success' => false, 'error' => 'Xarid amalga oshmadi'], 500);
        }
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'coins' => (int)$updated['coins']]);
        break;

    case 'shop_equip':
        $user = currentUser();
        $in = input();
        $code = isset($in['code']) ? $in['code'] : '';
        $item = dbFirst("SELECT * FROM shop_items WHERE code = ?", [$code]);
        if (!$item) jsonResponse(['success' => false, 'error' => 'Buyum topilmadi'], 404);

        $owned = ((int)$item['price_coins'] === 0)
            || dbFirst("SELECT id FROM user_items WHERE user_id = ? AND item_code = ?", [$user['id'], $code]);
        if (!$owned) jsonResponse(['success' => false, 'error' => 'Avval sotib oling'], 400);

        if ($item['type'] === 'board') {
            dbExec("UPDATE users SET equipped_board = ? WHERE id = ?", [$code, $user['id']]);
        } else {
            dbExec("UPDATE users SET equipped_piece = ? WHERE id = ?", [$code, $user['id']]);
        }
        jsonResponse(['success' => true]);
        break;

    /* ---------------- TAKLIF ---------------- */
    case 'invite_info':
        $user = currentUser();
        // bot username config'da bo'lishi mumkin; bo'lmasa APP_URL ishlatamiz
        jsonResponse([
            'success' => true,
            'telegram_id' => (int)$user['telegram_id'],
            'referral_count' => (int)$user['referral_count'],
            'app_url' => APP_URL,
        ]);
        break;

    /* ---------------- ONLINE O'YIN ---------------- */
    case 'match_find':
        $user = currentUser();
        // Mavjud o'yinda emasligini tekshirish
        $mine = dbFirst(
            "SELECT * FROM matches WHERE (p1_id = ? OR p2_id = ?) AND status IN ('waiting','active') ORDER BY id DESC LIMIT 1",
            [$user['id'], $user['id']]
        );
        if ($mine) { jsonResponse(['success' => true, 'match_id' => (int)$mine['id'], 'status' => $mine['status']]); }

        // Kutayotgan o'yin bormi? (boshqa o'yinchidan)
        $waiting = dbFirst(
            "SELECT * FROM matches WHERE status = 'waiting' AND p1_id != ? ORDER BY id ASC LIMIT 1",
            [$user['id']]
        );
        if ($waiting) {
            dbExec(
                "UPDATE matches SET p2_id = ?, status = 'active' WHERE id = ? AND status = 'waiting'",
                [$user['id'], $waiting['id']]
            );
            jsonResponse(['success' => true, 'match_id' => (int)$waiting['id'], 'status' => 'active']);
        }

        // Yangi kutuv o'yini yaratish (p1 = oq)
        $board = json_encode(eNewBoard());
        $mid = dbInsert(
            "INSERT INTO matches (p1_id, board, turn, status, created_at) VALUES (?, ?, 2, 'waiting', NOW())",
            [$user['id'], $board]
        );
        jsonResponse(['success' => true, 'match_id' => (int)$mid, 'status' => 'waiting']);
        break;

    case 'match_state':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $m = dbFirst("SELECT * FROM matches WHERE id = ?", [$mid]);
        if (!$m) jsonResponse(['success' => false, 'error' => 'O\'yin topilmadi'], 404);
        if ((int)$m['p1_id'] !== (int)$user['id'] && (int)$m['p2_id'] !== (int)$user['id']) {
            jsonResponse(['success' => false, 'error' => 'Bu sizning o\'yiningiz emas'], 403);
        }

        $myColor = ((int)$m['p1_id'] === (int)$user['id']) ? 2 : 1; // p1=oq(2), p2=qora(1)
        $oppId = $myColor === 2 ? $m['p2_id'] : $m['p1_id'];
        $opp = $oppId ? dbFirst("SELECT first_name, username, photo_url, rating FROM users WHERE id = ?", [$oppId]) : null;

        jsonResponse([
            'success' => true,
            'board' => json_decode($m['board'], true),
            'turn' => (int)$m['turn'],
            'status' => $m['status'],
            'my_color' => $myColor,
            'winner_id' => $m['winner_id'] ? (int)$m['winner_id'] : null,
            'move_count' => (int)$m['move_count'],
            'last_from' => $m['last_from'],
            'last_to' => $m['last_to'],
            'opponent' => $opp,
        ]);
        break;

    case 'match_move':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $from = isset($in['from']) ? $in['from'] : null;  // [r,c]
        $to = isset($in['to']) ? $in['to'] : null;        // [r,c]
        $m = dbFirst("SELECT * FROM matches WHERE id = ?", [$mid]);
        if (!$m) jsonResponse(['success' => false, 'error' => 'O\'yin topilmadi'], 404);
        if ($m['status'] !== 'active') jsonResponse(['success' => false, 'error' => 'O\'yin faol emas'], 400);

        $myColor = ((int)$m['p1_id'] === (int)$user['id']) ? 2 : 1;
        if ((int)$m['turn'] !== $myColor) jsonResponse(['success' => false, 'error' => 'Sizning navbatingiz emas'], 400);
        if (!is_array($from) || !is_array($to)) jsonResponse(['success' => false, 'error' => 'Yurish noto\'g\'ri'], 400);

        $board = json_decode($m['board'], true);
        $nb = eApplyIfLegal($board, $myColor, [(int)$from[0], (int)$from[1]], [(int)$to[0], (int)$to[1]]);
        if ($nb === null) jsonResponse(['success' => false, 'error' => 'Qonuniy bo\'lmagan yurish'], 400);

        $nextTurn = $myColor === 2 ? 1 : 2;
        $winner = eCheckWinner($nb);  // 0=davom, 1=qora yutdi, 2=oq yutdi
        $fromSq = $from[0] . ',' . $from[1];
        $toSq = $to[0] . ',' . $to[1];

        if ($winner !== 0) {
            $winnerColor = $winner;  // 2=oq=p1, 1=qora=p2
            $winnerId = $winnerColor === 2 ? $m['p1_id'] : $m['p2_id'];
            dbExec(
                "UPDATE matches SET board = ?, turn = ?, status = 'finished', winner_id = ?,
                 move_count = move_count + 1, last_from = ?, last_to = ? WHERE id = ?",
                [json_encode($nb), $nextTurn, $winnerId, $fromSq, $toSq, $mid]
            );
            finishMatch($m, $winnerId);
            jsonResponse(['success' => true, 'board' => $nb, 'finished' => true, 'winner_id' => (int)$winnerId]);
        } else {
            dbExec(
                "UPDATE matches SET board = ?, turn = ?, move_count = move_count + 1, last_from = ?, last_to = ? WHERE id = ?",
                [json_encode($nb), $nextTurn, $fromSq, $toSq, $mid]
            );
            jsonResponse(['success' => true, 'board' => $nb, 'finished' => false, 'turn' => $nextTurn]);
        }
        break;

    case 'match_resign':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $m = dbFirst("SELECT * FROM matches WHERE id = ?", [$mid]);
        if (!$m || $m['status'] !== 'active') jsonResponse(['success' => false, 'error' => 'O\'yin faol emas'], 400);
        $winnerId = ((int)$m['p1_id'] === (int)$user['id']) ? $m['p2_id'] : $m['p1_id'];
        dbExec("UPDATE matches SET status = 'finished', winner_id = ? WHERE id = ?", [$winnerId, $mid]);
        finishMatch($m, $winnerId);
        jsonResponse(['success' => true, 'winner_id' => (int)$winnerId]);
        break;

    case 'match_cancel':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        dbExec("DELETE FROM matches WHERE id = ? AND p1_id = ? AND status = 'waiting'", [$mid, $user['id']]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Noma\'lum action'], 404);
}

/** Online o'yin tugagach ikkala o'yinchining statistikasini yangilash */
function finishMatch($m, $winnerId) {
    if (empty($m['p2_id'])) return;
    $p1 = dbFirst("SELECT * FROM users WHERE id = ?", [$m['p1_id']]);
    $p2 = dbFirst("SELECT * FROM users WHERE id = ?", [$m['p2_id']]);
    if (!$p1 || !$p2) return;

    if ($winnerId === null) {
        $s1 = 0.5; $s2 = 0.5; $r1 = 'draw'; $r2 = 'draw';
    } elseif ((int)$winnerId === (int)$p1['id']) {
        $s1 = 1; $s2 = 0; $r1 = 'win'; $r2 = 'loss';
    } else {
        $s1 = 0; $s2 = 1; $r1 = 'loss'; $r2 = 'win';
    }

    $c1 = eloChange((int)$p1['rating'], (int)$p2['rating'], $s1);
    $c2 = eloChange((int)$p2['rating'], (int)$p1['rating'], $s2);
    $coin1 = $r1 === 'win' ? WIN_COINS : ($r1 === 'draw' ? DRAW_COINS : LOSS_COINS);
    $coin2 = $r2 === 'win' ? WIN_COINS : ($r2 === 'draw' ? DRAW_COINS : LOSS_COINS);

    updatePlayer($p1, $c1, $coin1, $r1, $m);
    updatePlayer($p2, $c2, $coin2, $r2, $m);
}

function updatePlayer($u, $ratingChange, $coins, $result, $m) {
    $newRating = max(100, (int)$u['rating'] + $ratingChange);
    dbExec(
        "UPDATE users SET rating = ?, coins = coins + ?, total_games = total_games + 1,
         wins = wins + ?, losses = losses + ?, draws = draws + ?, last_active = NOW() WHERE id = ?",
        [$newRating, $coins, $result==='win'?1:0, $result==='loss'?1:0, $result==='draw'?1:0, $u['id']]
    );
    dbInsert(
        "INSERT INTO games (user_id, mode, opponent_type, result, moves, rating_change, created_at)
         VALUES (?, 'online', 'online', ?, ?, ?, NOW())",
        [$u['id'], $result, (int)$m['move_count'], $ratingChange]
    );
}
