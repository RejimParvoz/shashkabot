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

// Global xato ushlagich: har qanday xatoni JSON qilib qaytaradi (jimgina o'lib qolmasin)
set_exception_handler(function ($e) {
    error_log('API fatal: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $msg = $e->getMessage();
    if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'Unknown column') !== false || stripos($msg, 'Base table') !== false) {
        $msg = "Baza yangilanmagan. install.php ni qayta oching.";
    }
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
});

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
        'bot_rating' => isset($u['bot_rating']) ? (int)$u['bot_rating'] : 1000,
        'total_games' => (int)$u['total_games'], 'wins' => (int)$u['wins'],
        'losses' => (int)$u['losses'], 'draws' => (int)$u['draws'],
        'equipped_board' => isset($u['equipped_board']) ? $u['equipped_board'] : 'classic',
        'equipped_piece' => isset($u['equipped_piece']) ? $u['equipped_piece'] : 'classic',
        'referral_count' => isset($u['referral_count']) ? (int)$u['referral_count'] : 0,
        'bp_xp' => isset($u['bp_xp']) ? (int)$u['bp_xp'] : 0,
        'bp_premium' => isset($u['bp_premium']) ? (int)$u['bp_premium'] : 0,
        'bp_level' => bpLevelFromXp(isset($u['bp_xp']) ? (int)$u['bp_xp'] : 0),
        'vip_level' => isset($u['vip_level']) ? $u['vip_level'] : '',
        'vip_until' => isset($u['vip_until']) ? $u['vip_until'] : null,
        'is_vip' => isVipActive($u),
    ];
}

/** VIP faolmi? */
function isVipActive($u) {
    if (empty($u['vip_until'])) return false;
    return strtotime($u['vip_until']) > time();
}

/** XP dan Battle Pass darajasini hisoblash */
function bpLevelFromXp($xp) {
    try {
        $levels = dbAll("SELECT level, xp_required FROM bp_levels ORDER BY level ASC");
    } catch (Exception $e) {
        return 0;
    }
    $lvl = 0;
    foreach ($levels as $l) {
        if ($xp >= (int)$l['xp_required']) $lvl = (int)$l['level'];
        else break;
    }
    return $lvl;
}

/** O'yin uchun XP qo'shish (game +10, win +25) */
function awardXp($userId, $result) {
    $xp = 10 + ($result === 'win' ? 25 : 0);
    dbExec("UPDATE users SET bp_xp = bp_xp + ? WHERE id = ?", [$xp, $userId]);
    return $xp;
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
        jsonResponse(['success' => true, 'user' => publicUser($user), 'bot_username' => defined('BOT_USERNAME') ? BOT_USERNAME : '']);
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
        $botRating = isset($user['bot_rating']) ? (int)$user['bot_rating'] : 1000;
        $newRating = max(100, $botRating + $ratingChange);
        $coins = ($result === 'win') ? WIN_COINS : (($result === 'draw') ? DRAW_COINS : LOSS_COINS);

        dbExec(
            "UPDATE users SET bot_rating = ?, coins = coins + ?, total_games = total_games + 1,
             wins = wins + ?, losses = losses + ?, draws = draws + ?, last_active = NOW() WHERE id = ?",
            [$newRating, $coins, $result==='win'?1:0, $result==='loss'?1:0, $result==='draw'?1:0, $user['id']]
        );
        dbInsert(
            "INSERT INTO games (user_id, mode, bot_level, opponent_type, result, moves, rating_change, created_at)
             VALUES (?, ?, ?, 'bot', ?, ?, ?, NOW())",
            [$user['id'], $mode, $botLevel, $result, $moves, $ratingChange]
        );
        awardXp($user['id'], $result);
        tournamentPoints($user['id'], $result);
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'rating_change' => $ratingChange, 'coins_earned' => $coins, 'user' => publicUser($updated)]);
        break;

    /* ---------------- DO'KON (faqat tanga) ---------------- */
    case 'shop_list':
        $user = currentUser();
        $items = dbAll("SELECT code, name, type, price_coins, price_diamonds, premium, data, sort FROM shop_items ORDER BY premium, type, sort");
        $owned = dbAll("SELECT item_code FROM user_items WHERE user_id = ?", [$user['id']]);
        $ownedCodes = array_column($owned, 'item_code');
        foreach ($items as &$it) {
            $it['price_coins'] = (int)$it['price_coins'];
            $it['price_diamonds'] = (int)$it['price_diamonds'];
            $it['premium'] = (int)$it['premium'];
            $isFree = ($it['price_coins'] === 0 && $it['price_diamonds'] === 0);
            $it['owned'] = $isFree || in_array($it['code'], $ownedCodes);
            $it['equipped'] = ($it['type'] === 'board' && $it['code'] === $user['equipped_board'])
                || ($it['type'] === 'piece' && $it['code'] === $user['equipped_piece']);
        }
        unset($it);
        jsonResponse(['success' => true, 'items' => $items, 'coins' => (int)$user['coins'], 'diamonds' => (int)$user['diamonds']]);
        break;

    case 'shop_buy':
        $user = currentUser();
        $in = input();
        $code = isset($in['code']) ? $in['code'] : '';
        $item = dbFirst("SELECT * FROM shop_items WHERE code = ?", [$code]);
        if (!$item) jsonResponse(['success' => false, 'error' => 'Buyum topilmadi'], 404);

        $already = dbFirst("SELECT id FROM user_items WHERE user_id = ? AND item_code = ?", [$user['id'], $code]);
        $isFree = ((int)$item['price_coins'] === 0 && (int)$item['price_diamonds'] === 0);
        if ($already || $isFree) {
            jsonResponse(['success' => false, 'error' => 'Allaqachon sizniki'], 400);
        }

        $useDiamonds = ((int)$item['price_diamonds'] > 0);
        if ($useDiamonds) {
            if ((int)$user['diamonds'] < (int)$item['price_diamonds']) {
                jsonResponse(['success' => false, 'error' => 'Olmos yetarli emas'], 400);
            }
        } else {
            if ((int)$user['coins'] < (int)$item['price_coins']) {
                jsonResponse(['success' => false, 'error' => 'Tanga yetarli emas'], 400);
            }
        }

        db()->beginTransaction();
        try {
            if ($useDiamonds) {
                dbExec("UPDATE users SET diamonds = diamonds - ? WHERE id = ?", [(int)$item['price_diamonds'], $user['id']]);
            } else {
                dbExec("UPDATE users SET coins = coins - ? WHERE id = ?", [(int)$item['price_coins'], $user['id']]);
            }
            dbInsert("INSERT INTO user_items (user_id, item_code, bought_at) VALUES (?, ?, NOW())", [$user['id'], $code]);
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            jsonResponse(['success' => false, 'error' => 'Xarid amalga oshmadi'], 500);
        }
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'coins' => (int)$updated['coins'], 'diamonds' => (int)$updated['diamonds']]);
        break;

    case 'shop_equip':
        $user = currentUser();
        $in = input();
        $code = isset($in['code']) ? $in['code'] : '';
        $item = dbFirst("SELECT * FROM shop_items WHERE code = ?", [$code]);
        if (!$item) jsonResponse(['success' => false, 'error' => 'Buyum topilmadi'], 404);

        $isFree = ((int)$item['price_coins'] === 0 && (int)$item['price_diamonds'] === 0);
        $owned = $isFree
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
        jsonResponse([
            'success' => true,
            'telegram_id' => (int)$user['telegram_id'],
            'referral_count' => (int)$user['referral_count'],
            'bot_username' => defined('BOT_USERNAME') ? BOT_USERNAME : '',
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
            'draw_offer' => (int)$m['draw_offer'],
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

    /* ---------------- O'YINCHI STATISTIKASI ---------------- */
    case 'user_stats':
        currentUser(); // so'rovchi avtorizatsiyadan o'tgan bo'lsin
        $in = input();
        $tgid = (int)(isset($in['telegram_id']) ? $in['telegram_id'] : 0);
        $u = dbFirst("SELECT * FROM users WHERE telegram_id = ?", [$tgid]);
        if (!$u) jsonResponse(['success' => false, 'error' => 'Topilmadi'], 404);
        $rank = dbFirst("SELECT COUNT(*)+1 AS r FROM users WHERE rating > ?", [$u['rating']]);
        $d = publicUser($u);
        $d['rank'] = (int)$rank['r'];
        $d['win_rate'] = $u['total_games'] > 0 ? round($u['wins'] / $u['total_games'] * 100, 1) : 0;
        jsonResponse(['success' => true, 'profile' => $d]);
        break;

    /* ---------------- BATTLE PASS ---------------- */
    case 'bp_state':
        $user = currentUser();
        $levels = dbAll("SELECT * FROM bp_levels ORDER BY level ASC");
        $claimed = $user['bp_claimed'] ? json_decode($user['bp_claimed'], true) : [];
        if (!is_array($claimed)) $claimed = [];
        $xp = (int)$user['bp_xp'];
        $curLevel = bpLevelFromXp($xp);
        foreach ($levels as &$l) {
            $l['level'] = (int)$l['level'];
            $l['xp_required'] = (int)$l['xp_required'];
            $l['free_coins'] = (int)$l['free_coins'];
            $l['free_diamonds'] = (int)$l['free_diamonds'];
            $l['prem_coins'] = (int)$l['prem_coins'];
            $l['prem_diamonds'] = (int)$l['prem_diamonds'];
            $l['unlocked'] = $curLevel >= $l['level'];
            $l['claimed_free'] = in_array('f' . $l['level'], $claimed);
            $l['claimed_prem'] = in_array('p' . $l['level'], $claimed);
        }
        unset($l);
        jsonResponse([
            'success' => true, 'xp' => $xp, 'level' => $curLevel,
            'premium' => (int)$user['bp_premium'], 'levels' => $levels,
            'coins' => (int)$user['coins'], 'diamonds' => (int)$user['diamonds'],
        ]);
        break;

    case 'bp_claim':
        $user = currentUser();
        $in = input();
        $level = (int)(isset($in['level']) ? $in['level'] : 0);
        $track = isset($in['track']) ? $in['track'] : 'free'; // free | prem
        $lvl = dbFirst("SELECT * FROM bp_levels WHERE level = ?", [$level]);
        if (!$lvl) jsonResponse(['success' => false, 'error' => 'Daraja topilmadi'], 404);
        if (bpLevelFromXp((int)$user['bp_xp']) < $level) jsonResponse(['success' => false, 'error' => 'Bu darajaga yetmadingiz'], 400);
        if ($track === 'prem' && (int)$user['bp_premium'] !== 1) jsonResponse(['success' => false, 'error' => 'Premium kerak'], 400);

        $claimed = $user['bp_claimed'] ? json_decode($user['bp_claimed'], true) : [];
        if (!is_array($claimed)) $claimed = [];
        $key = ($track === 'prem' ? 'p' : 'f') . $level;
        if (in_array($key, $claimed)) jsonResponse(['success' => false, 'error' => 'Allaqachon olingan'], 400);

        $coins = $track === 'prem' ? (int)$lvl['prem_coins'] : (int)$lvl['free_coins'];
        $diamonds = $track === 'prem' ? (int)$lvl['prem_diamonds'] : (int)$lvl['free_diamonds'];
        $item = ($track === 'prem' && !empty($lvl['prem_item'])) ? $lvl['prem_item'] : null;

        db()->beginTransaction();
        try {
            dbExec("UPDATE users SET coins = coins + ?, diamonds = diamonds + ? WHERE id = ?", [$coins, $diamonds, $user['id']]);
            if ($item) {
                dbInsert("INSERT IGNORE INTO user_items (user_id, item_code, bought_at) VALUES (?, ?, NOW())", [$user['id'], $item]);
            }
            $claimed[] = $key;
            dbExec("UPDATE users SET bp_claimed = ? WHERE id = ?", [json_encode($claimed), $user['id']]);
            db()->commit();
        } catch (Exception $e) {
            db()->rollBack();
            jsonResponse(['success' => false, 'error' => 'Xato'], 500);
        }
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'coins' => (int)$updated['coins'], 'diamonds' => (int)$updated['diamonds'],
            'reward' => ['coins' => $coins, 'diamonds' => $diamonds, 'item' => $item]]);
        break;

    /* ---------------- OLMOS XARID (Telegram Stars) ---------------- */
    case 'diamond_packs':
        currentUser();
        jsonResponse(['success' => true, 'packs' => diamondPacks()]);
        break;

    case 'buy_diamonds':
        $user = currentUser();
        $in = input();
        $packId = isset($in['pack']) ? $in['pack'] : '';
        $packs = diamondPacks();
        $pack = null;
        foreach ($packs as $p) { if ($p['id'] === $packId) { $pack = $p; break; } }
        if (!$pack) jsonResponse(['success' => false, 'error' => 'Paket topilmadi'], 404);

        $payload = 'dia_' . $user['telegram_id'] . '_' . $pack['diamonds'];
        $link = tgCreateInvoiceLink('💎 ' . $pack['diamonds'] . ' Olmos', 'Shashka uchun olmoslar', $payload, $pack['stars']);
        if (!$link) jsonResponse(['success' => false, 'error' => 'Invoice yaratilmadi (bot token?)'], 500);
        jsonResponse(['success' => true, 'invoice' => $link]);
        break;

    case 'bp_buy_premium':
        $user = currentUser();
        if ((int)$user['bp_premium'] === 1) jsonResponse(['success' => false, 'error' => 'Premium allaqachon bor'], 400);
        $payload = 'bp_' . $user['telegram_id'];
        $link = tgCreateInvoiceLink('🎟 Battle Pass Premium', 'Premium track ochish', $payload, 150);
        if (!$link) jsonResponse(['success' => false, 'error' => 'Invoice yaratilmadi'], 500);
        jsonResponse(['success' => true, 'invoice' => $link]);
        break;

    /* ---------------- DO'ST BILAN 1v1 ---------------- */
    case 'challenge_create':
        $user = currentUser();
        // eski faol/kutuv o'yinlarini tozalash
        dbExec("DELETE FROM matches WHERE p1_id = ? AND status = 'waiting'", [$user['id']]);
        $code = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $board = json_encode(eNewBoard());
        $mid = dbInsert(
            "INSERT INTO matches (p1_id, board, turn, status, is_private, code, created_at) VALUES (?, ?, 2, 'waiting', 1, ?, NOW())",
            [$user['id'], $board, $code]
        );
        jsonResponse(['success' => true, 'match_id' => (int)$mid, 'code' => $code, 'telegram_id' => (int)$user['telegram_id']]);
        break;

    case 'challenge_join':
        $user = currentUser();
        $in = input();
        $code = strtoupper(trim(isset($in['code']) ? $in['code'] : ''));
        if ($code === '') jsonResponse(['success' => false, 'error' => 'Kod yo\'q'], 400);
        $m = dbFirst("SELECT * FROM matches WHERE code = ? AND is_private = 1 ORDER BY id DESC LIMIT 1", [$code]);
        if (!$m) jsonResponse(['success' => false, 'error' => 'Bunday o\'yin topilmadi'], 404);
        if ((int)$m['p1_id'] === (int)$user['id']) jsonResponse(['success' => false, 'error' => 'Bu sizning o\'yiningiz'], 400);
        if ($m['status'] !== 'waiting') jsonResponse(['success' => false, 'error' => 'O\'yin allaqachon boshlangan'], 400);
        dbExec("UPDATE matches SET p2_id = ?, status = 'active' WHERE id = ? AND status = 'waiting'", [$user['id'], $m['id']]);
        jsonResponse(['success' => true, 'match_id' => (int)$m['id']]);
        break;

    /* ---------------- TURNIRLAR ---------------- */
    case 'tournament_list':
        $user = currentUser();
        ensureTournaments();
        $ts = dbAll("SELECT * FROM tournaments WHERE status = 'active' ORDER BY FIELD(type,'daily','weekly','monthly')");
        $out = [];
        foreach ($ts as $t) {
            $joined = dbFirst("SELECT score FROM tournament_players WHERE tournament_id = ? AND user_id = ?", [$t['id'], $user['id']]);
            $players = dbFirst("SELECT COUNT(*) AS c FROM tournament_players WHERE tournament_id = ?", [$t['id']]);
            $out[] = [
                'id' => (int)$t['id'], 'type' => $t['type'], 'title' => $t['title'],
                'ends_at' => $t['ends_at'], 'ends_ts' => strtotime($t['ends_at']),
                'prize1' => (int)$t['prize1'], 'prize2' => (int)$t['prize2'], 'prize3' => (int)$t['prize3'],
                'joined' => $joined ? true : false,
                'my_score' => $joined ? (int)$joined['score'] : 0,
                'players' => (int)$players['c'],
            ];
        }
        jsonResponse(['success' => true, 'tournaments' => $out, 'server_ts' => time()]);
        break;

    case 'tournament_join':
        $user = currentUser();
        $in = input();
        $tid = (int)(isset($in['tournament_id']) ? $in['tournament_id'] : 0);
        $t = dbFirst("SELECT * FROM tournaments WHERE id = ? AND status = 'active'", [$tid]);
        if (!$t) jsonResponse(['success' => false, 'error' => 'Turnir topilmadi'], 404);
        dbExec("INSERT IGNORE INTO tournament_players (tournament_id, user_id, score, joined_at) VALUES (?, ?, 0, NOW())", [$tid, $user['id']]);
        jsonResponse(['success' => true]);
        break;

    case 'tournament_top':
        $user = currentUser();
        $in = input();
        $tid = (int)(isset($in['tournament_id']) ? $in['tournament_id'] : 0);
        $rows = dbAll(
            "SELECT tp.score, u.telegram_id, u.first_name, u.username, u.photo_url, u.rating
             FROM tournament_players tp INNER JOIN users u ON tp.user_id = u.id
             WHERE tp.tournament_id = ? ORDER BY tp.score DESC, u.rating DESC LIMIT 20",
            [$tid]
        );
        $rank = 1;
        foreach ($rows as &$r) { $r['rank'] = $rank++; $r['score'] = (int)$r['score']; }
        unset($r);
        jsonResponse(['success' => true, 'top' => $rows]);
        break;

    /* ---------------- VIP ---------------- */
    case 'vip_buy':
        $user = currentUser();
        $in = input();
        $level = isset($in['level']) ? $in['level'] : '';
        $plans = vipPlans();
        if (!isset($plans[$level])) jsonResponse(['success' => false, 'error' => 'Reja topilmadi'], 404);
        $payload = 'vip_' . $user['telegram_id'] . '_' . $level;
        $link = tgCreateInvoiceLink('👑 VIP ' . ucfirst($level), 'VIP obuna (30 kun)', $payload, $plans[$level]['stars']);
        if (!$link) jsonResponse(['success' => false, 'error' => 'Invoice yaratilmadi'], 500);
        jsonResponse(['success' => true, 'invoice' => $link]);
        break;

    case 'vip_info':
        $user = currentUser();
        jsonResponse([
            'success' => true,
            'is_vip' => isVipActive($user),
            'level' => $user['vip_level'],
            'until' => $user['vip_until'],
            'can_claim' => isVipActive($user) && $user['vip_claim_date'] !== date('Y-m-d'),
            'plans' => vipPlans(),
        ]);
        break;

    case 'vip_claim':
        $user = currentUser();
        if (!isVipActive($user)) jsonResponse(['success' => false, 'error' => 'VIP faol emas'], 400);
        if ($user['vip_claim_date'] === date('Y-m-d')) jsonResponse(['success' => false, 'error' => 'Bugun olib bo\'lingan'], 400);
        $amt = vipDailyAmount($user['vip_level']);
        dbExec("UPDATE users SET diamonds = diamonds + ?, vip_claim_date = ? WHERE id = ?", [$amt, date('Y-m-d'), $user['id']]);
        $updated = dbFirst("SELECT * FROM users WHERE id = ?", [$user['id']]);
        jsonResponse(['success' => true, 'diamonds' => (int)$updated['diamonds'], 'claimed' => $amt]);
        break;

    /* ---------------- O'YIN CHATI ---------------- */
    case 'match_chat_send':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $text = trim(isset($in['text']) ? $in['text'] : '');
        if ($text === '') jsonResponse(['success' => false, 'error' => 'Bo\'sh'], 400);
        if (mb_strlen($text) > 200) $text = mb_substr($text, 0, 200);
        $m = dbFirst("SELECT p1_id, p2_id FROM matches WHERE id = ?", [$mid]);
        if (!$m || ((int)$m['p1_id'] !== (int)$user['id'] && (int)$m['p2_id'] !== (int)$user['id'])) {
            jsonResponse(['success' => false, 'error' => 'Ruxsat yo\'q'], 403);
        }
        dbInsert("INSERT INTO match_chat (match_id, user_id, text, created_at) VALUES (?, ?, ?, NOW())", [$mid, $user['id'], $text]);
        jsonResponse(['success' => true]);
        break;

    case 'match_chat_get':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $after = (int)(isset($in['after']) ? $in['after'] : 0);
        $rows = dbAll(
            "SELECT id, user_id, text FROM match_chat WHERE match_id = ? AND id > ? ORDER BY id ASC LIMIT 50",
            [$mid, $after]
        );
        foreach ($rows as &$r) { $r['mine'] = ((int)$r['user_id'] === (int)$user['id']); $r['id'] = (int)$r['id']; }
        unset($r);
        jsonResponse(['success' => true, 'messages' => $rows]);
        break;

    /* ---------------- DURANG SO'ROVI ---------------- */
    case 'match_draw_offer':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $m = dbFirst("SELECT * FROM matches WHERE id = ?", [$mid]);
        if (!$m || $m['status'] !== 'active') jsonResponse(['success' => false, 'error' => 'Faol emas'], 400);
        $who = ((int)$m['p1_id'] === (int)$user['id']) ? 1 : 2;
        dbExec("UPDATE matches SET draw_offer = ? WHERE id = ?", [$who, $mid]);
        jsonResponse(['success' => true]);
        break;

    case 'match_draw_respond':
        $user = currentUser();
        $in = input();
        $mid = (int)(isset($in['match_id']) ? $in['match_id'] : 0);
        $accept = !empty($in['accept']);
        $m = dbFirst("SELECT * FROM matches WHERE id = ?", [$mid]);
        if (!$m || $m['status'] !== 'active') jsonResponse(['success' => false, 'error' => 'Faol emas'], 400);
        if ($accept) {
            dbExec("UPDATE matches SET status = 'finished', winner_id = NULL, draw_offer = 0 WHERE id = ?", [$mid]);
            finishMatch($m, null);
            jsonResponse(['success' => true, 'draw' => true]);
        } else {
            dbExec("UPDATE matches SET draw_offer = 0 WHERE id = ?", [$mid]);
            jsonResponse(['success' => true, 'draw' => false]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Noma\'lum action'], 404);
}

/** Olmos paketlari (Telegram Stars) */
function diamondPacks() {
    return [
        ['id' => 'p1', 'diamonds' => 100, 'stars' => 10],
        ['id' => 'p2', 'diamonds' => 550, 'stars' => 45],
        ['id' => 'p3', 'diamonds' => 1300, 'stars' => 100],
        ['id' => 'p4', 'diamonds' => 2800, 'stars' => 200],
        ['id' => 'p5', 'diamonds' => 6000, 'stars' => 380],
    ];
}

/* ============== TURNIR MANTIG'I ============== */

/** Davr chegaralari (start, end) DATETIME */
function periodBounds($type) {
    $now = time();
    if ($type === 'daily') {
        $start = strtotime(date('Y-m-d 00:00:00'));
        $end = $start + 86400;
        $title = 'Kunlik turnir';
    } elseif ($type === 'weekly') {
        $dow = (int)date('N'); // 1=Mon
        $start = strtotime(date('Y-m-d 00:00:00', $now - ($dow - 1) * 86400));
        $end = $start + 7 * 86400;
        $title = 'Haftalik turnir';
    } else { // monthly
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month', $start)));
        $title = 'Oylik turnir';
    }
    return [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end), $title];
}

/** Faol turnirlarni yaratish va eskirganlarini yakunlash */
function ensureTournaments() {
    // eskirganlarni yakunlash
    $expired = dbAll("SELECT * FROM tournaments WHERE status = 'active' AND ends_at <= NOW()");
    foreach ($expired as $t) { finalizeTournament($t); }

    $prizes = [
        'daily' => [50, 30, 20],
        'weekly' => [150, 90, 60],
        'monthly' => [500, 300, 150],
    ];
    foreach (['daily', 'weekly', 'monthly'] as $type) {
        list($start, $end, $title) = periodBounds($type);
        $exists = dbFirst("SELECT id FROM tournaments WHERE type = ? AND starts_at = ?", [$type, $start]);
        if (!$exists) {
            dbExec(
                "INSERT IGNORE INTO tournaments (type, title, starts_at, ends_at, status, prize1, prize2, prize3, created_at)
                 VALUES (?, ?, ?, ?, 'active', ?, ?, ?, NOW())",
                [$type, $title, $start, $end, $prizes[$type][0], $prizes[$type][1], $prizes[$type][2]]
            );
        }
    }
}

/** Turnirni yakunlash va top 3 ga mukofot */
function finalizeTournament($t) {
    $top = dbAll(
        "SELECT user_id, score FROM tournament_players WHERE tournament_id = ? AND score > 0 ORDER BY score DESC, joined_at ASC LIMIT 3",
        [$t['id']]
    );
    $prizes = [(int)$t['prize1'], (int)$t['prize2'], (int)$t['prize3']];
    foreach ($top as $i => $p) {
        if ($prizes[$i] > 0) {
            dbExec("UPDATE users SET diamonds = diamonds + ? WHERE id = ?", [$prizes[$i], $p['user_id']]);
        }
    }
    dbExec("UPDATE tournaments SET status = 'finished' WHERE id = ?", [$t['id']]);
}

/** G'alaba/durang uchun turnir ochkolari (qo'shilgan turnirlarga) */
function tournamentPoints($userId, $result) {
    $pts = ($result === 'win') ? 3 : (($result === 'draw') ? 1 : 0);
    if ($pts === 0) return;
    $active = dbAll("SELECT id FROM tournaments WHERE status = 'active' AND starts_at <= NOW() AND ends_at > NOW()");
    foreach ($active as $t) {
        dbExec(
            "UPDATE tournament_players SET score = score + ? WHERE tournament_id = ? AND user_id = ?",
            [$pts, $t['id'], $userId]
        );
    }
}

/* ============== VIP ============== */
function vipPlans() {
    return [
        'bronze' => ['stars' => 75, 'daily' => 10, 'name' => 'Bronze'],
        'gold' => ['stars' => 200, 'daily' => 30, 'name' => 'Gold'],
        'platinum' => ['stars' => 450, 'daily' => 75, 'name' => 'Platinum'],
    ];
}
function vipDailyAmount($level) {
    $p = vipPlans();
    return isset($p[$level]) ? $p[$level]['daily'] : 0;
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
    awardXp($u['id'], $result);
    tournamentPoints($u['id'], $result);
}
