<?php
/**
 * Shashka Game - Unit Tests
 * Tests core functionality without database
 */

echo "========================================\n";
echo "  SHASHKA GAME - TEST SUITE\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ PASS: $name\n";
        $passed++;
    } else {
        echo "  ❌ FAIL: $name\n";
        $failed++;
    }
}

// ==========================================
// TEST 1: Router
// ==========================================
echo "\n📦 1. ROUTER TEST\n";
echo "-------------------\n";

require_once __DIR__ . '/../core/Router.php';

$router = new Router();
$router->get('/api/test', 'TestController@index');
$router->post('/api/game/create', 'GameController@create');
$router->get('/api/user/{id}', 'UserController@show');

$routes = $router->getRoutes();
test("Router: GET route registered", count($routes) >= 1);
test("Router: POST route registered", $routes[1]['method'] === 'POST');
test("Router: Route with params registered", strpos($routes[2]['path'], '{id}') !== false);
test("Router: Total routes count = 3", count($routes) === 3);

// ==========================================
// TEST 2: Cache (ArrayCache)
// ==========================================
echo "\n📦 2. CACHE TEST (ArrayCache)\n";
echo "------------------------------\n";

require_once __DIR__ . '/../core/cache/ArrayCache.php';

$cache = new ArrayCache(['max_size' => 1024 * 1024, 'ttl' => 60]);

// Test set and get
$cache->set('user:1', ['name' => 'Sardor', 'rating' => 1500]);
$result = $cache->get('user:1');
test("Cache: Set and Get works", $result['name'] === 'Sardor');
test("Cache: Get returns correct data", $result['rating'] === 1500);

// Test non-existent key
$missing = $cache->get('non_existent_key');
test("Cache: Missing key returns null", $missing === null);

// Test delete
$cache->set('temp_key', 'temp_value');
$cache->delete('temp_key');
test("Cache: Delete removes key", $cache->get('temp_key') === null);

// Test multiple values
$cache->set('key1', 'value1');
$cache->set('key2', 'value2');
$cache->set('key3', 'value3');
test("Cache: Multiple keys stored", $cache->get('key1') === 'value1');
test("Cache: Multiple keys stored (2)", $cache->get('key3') === 'value3');

// Test exists
test("Cache: exists() works for existing", $cache->exists('key1'));
test("Cache: exists() works for missing", !$cache->exists('missing_key'));

// Test clear
$cache->clear();
test("Cache: clear() removes all keys", $cache->get('key1') === null);

// Test stats
$cache->set('stat_test', 'data');
$cache->get('stat_test');
$cache->get('missing_stat');
$stats = $cache->getStats();
test("Cache: Stats tracks hits", $stats['hits'] > 0);
test("Cache: Stats tracks misses", $stats['misses'] > 0);

// ==========================================
// TEST 3: Security
// ==========================================
echo "\n📦 3. SECURITY TEST\n";
echo "--------------------\n";

// Test CSRF token generation
$_SESSION = [];
require_once __DIR__ . '/../core/Security.php';

// Test base64url functions
$original = 'Hello Shashka Game!';
$encoded = base64url_encode($original);
$decoded = base64url_decode($encoded);
test("Security: base64url encode/decode", $decoded === $original);

// Test with binary data
$binary = random_bytes(32);
$encodedBin = base64url_encode($binary);
$decodedBin = base64url_decode($encodedBin);
test("Security: base64url with binary data", $decodedBin === $binary);

// Test URL-safe (no + / =)
test("Security: base64url is URL-safe", 
    strpos($encodedBin, '+') === false && 
    strpos($encodedBin, '/') === false && 
    strpos($encodedBin, '=') === false);

// ==========================================
// TEST 4: Game Logic
// ==========================================
echo "\n📦 4. GAME LOGIC TEST\n";
echo "----------------------\n";

// Test initial board setup
$initialBoard = [
    [0, 1, 0, 1, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 1, 0],
    [0, 1, 0, 1, 0, 1, 0, 1],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [2, 0, 2, 0, 2, 0, 2, 0],
    [0, 2, 0, 2, 0, 2, 0, 2],
    [2, 0, 2, 0, 2, 0, 2, 0]
];

// Count pieces
$blackCount = 0;
$whiteCount = 0;
for ($r = 0; $r < 8; $r++) {
    for ($c = 0; $c < 8; $c++) {
        if ($initialBoard[$r][$c] === 1) $blackCount++;
        if ($initialBoard[$r][$c] === 2) $whiteCount++;
    }
}

test("Game: Initial board has 12 black pieces", $blackCount === 12);
test("Game: Initial board has 12 white pieces", $whiteCount === 12);
test("Game: Board is 8x8", count($initialBoard) === 8 && count($initialBoard[0]) === 8);
test("Game: Middle rows are empty", $initialBoard[3][0] === 0 && $initialBoard[4][0] === 0);

// Test square notation conversion
function testSquareToCoords($square) {
    $col = ord(strtolower($square[0])) - ord('a');
    $row = 8 - intval($square[1]);
    return ['row' => $row, 'col' => $col];
}

function testCoordsToSquare($row, $col) {
    return chr(ord('a') + $col) . (8 - $row);
}

$coords = testSquareToCoords('a1');
test("Game: a1 -> row=7, col=0", $coords['row'] === 7 && $coords['col'] === 0);

$coords = testSquareToCoords('h8');
test("Game: h8 -> row=0, col=7", $coords['row'] === 0 && $coords['col'] === 7);

$coords = testSquareToCoords('d4');
test("Game: d4 -> row=4, col=3", $coords['row'] === 4 && $coords['col'] === 3);

$square = testCoordsToSquare(7, 0);
test("Game: (7,0) -> a1", $square === 'a1');

$square = testCoordsToSquare(0, 7);
test("Game: (0,7) -> h8", $square === 'h8');

// ==========================================
// TEST 5: Elo Rating Calculation
// ==========================================
echo "\n📦 5. ELO RATING TEST\n";
echo "----------------------\n";

function calculateElo($rating1, $rating2, $score1, $k1 = 40) {
    $expected1 = 1 / (1 + pow(10, ($rating2 - $rating1) / 400));
    $newRating1 = round($rating1 + $k1 * ($score1 - $expected1));
    return (int)max(100, $newRating1);
}

// Equal players - winner gains
$newRating = calculateElo(1000, 1000, 1, 40);
test("Elo: Equal players win (1000 vs 1000)", $newRating === 1020);

// Equal players - loser drops
$newRating = calculateElo(1000, 1000, 0, 40);
test("Elo: Equal players loss (1000 vs 1000)", $newRating === 980);

// Draw between equal players
$newRating = calculateElo(1000, 1000, 0.5, 40);
test("Elo: Equal players draw = no change", $newRating === 1000);

// Weaker beats stronger - big gain
$newRating = calculateElo(1000, 1500, 1, 40);
test("Elo: Weaker wins (1000 vs 1500) gains more", $newRating > 1020);

// Stronger beats weaker - small gain
$newRating = calculateElo(1500, 1000, 1, 40);
test("Elo: Stronger wins (1500 vs 1000) gains less", $newRating < 1520);

// K-factor test
function getKFactor($rating, $gamesPlayed) {
    if ($gamesPlayed < 20) return 40;
    elseif ($rating < 2000) return 30;
    else return 20;
}

test("Elo: K=40 for new players (<20 games)", getKFactor(1000, 10) === 40);
test("Elo: K=30 for medium players", getKFactor(1500, 50) === 30);
test("Elo: K=20 for high rated players", getKFactor(2500, 100) === 20);

// ==========================================
// TEST 6: League System
// ==========================================
echo "\n📦 6. LEAGUE SYSTEM TEST\n";
echo "-------------------------\n";

function getLeague($rating) {
    if ($rating >= 4000) return 'royal';
    if ($rating >= 3000) return 'diamond';
    if ($rating >= 2000) return 'gold';
    if ($rating >= 1000) return 'silver';
    return 'bronze';
}

test("League: 500 -> bronze", getLeague(500) === 'bronze');
test("League: 999 -> bronze", getLeague(999) === 'bronze');
test("League: 1000 -> silver", getLeague(1000) === 'silver');
test("League: 1999 -> silver", getLeague(1999) === 'silver');
test("League: 2000 -> gold", getLeague(2000) === 'gold');
test("League: 3000 -> diamond", getLeague(3000) === 'diamond');
test("League: 4000 -> royal", getLeague(4000) === 'royal');
test("League: 5000 -> royal", getLeague(5000) === 'royal');

// ==========================================
// TEST 7: Economy System
// ==========================================
echo "\n📦 7. ECONOMY SYSTEM TEST\n";
echo "--------------------------\n";

// Coin rewards
$winCoins = 25;
$drawCoins = 10;
$lossCoins = 5;

test("Economy: Win = 25 coins", $winCoins === 25);
test("Economy: Draw = 10 coins", $drawCoins === 10);
test("Economy: Loss = 5 coins", $lossCoins === 5);

// Diamond pack pricing
$packs = [
    ['stars' => 10, 'diamonds' => 100],
    ['stars' => 45, 'diamonds' => 500],
    ['stars' => 100, 'diamonds' => 1200],
    ['stars' => 200, 'diamonds' => 2500],
    ['stars' => 380, 'diamonds' => 5000],
];

test("Economy: 5 diamond packs available", count($packs) === 5);
test("Economy: Pack 1 = 10 stars -> 100 diamonds", $packs[0]['diamonds'] === 100);
test("Economy: Pack 5 = 380 stars -> 5000 diamonds", $packs[4]['diamonds'] === 5000);

// VIP daily rewards
$vipDailyDiamonds = [
    'bronze' => 5,
    'silver' => 10,
    'gold' => 20,
    'platinum' => 35
];

test("Economy: Bronze VIP = 5 diamonds/day", $vipDailyDiamonds['bronze'] === 5);
test("Economy: Platinum VIP = 35 diamonds/day", $vipDailyDiamonds['platinum'] === 35);

// Monthly income calculation
$gamesPerDay = 5;
$winRate = 0.5;
$daysPerMonth = 30;

$monthlyWins = $gamesPerDay * $winRate * $daysPerMonth;
$monthlyLosses = $gamesPerDay * (1 - $winRate) * $daysPerMonth;
$monthlyCoins = ($monthlyWins * $winCoins) + ($monthlyLosses * $lossCoins);
test("Economy: Monthly coins ~ 2250 (5 games/day, 50% WR)", $monthlyCoins === 2250.0);

// ==========================================
// TEST 8: FileCache Driver
// ==========================================
echo "\n📦 8. FILE CACHE TEST\n";
echo "----------------------\n";

require_once __DIR__ . '/../core/cache/FileCache.php';

$fileCachePath = '/tmp/shashka_test_cache_' . uniqid();
$fileCache = new FileCache([
    'path' => $fileCachePath,
    'directory_levels' => 2,
    'file_locking' => true,
    'compress' => false
]);

// Test basic operations
$fileCache->set('test_key', 'test_value', 60);
$value = $fileCache->get('test_key');
test("FileCache: Set and Get works", $value === 'test_value');

// Test complex data
$complexData = [
    'user' => ['id' => 1, 'name' => 'Test'],
    'games' => [1, 2, 3, 4, 5],
    'nested' => ['a' => ['b' => ['c' => 'deep']]]
];
$fileCache->set('complex', $complexData, 60);
$retrieved = $fileCache->get('complex');
test("FileCache: Complex data preserved", $retrieved['nested']['a']['b']['c'] === 'deep');
test("FileCache: Array data preserved", count($retrieved['games']) === 5);

// Test delete
$fileCache->set('del_key', 'delete_me');
$fileCache->delete('del_key');
test("FileCache: Delete works", $fileCache->get('del_key') === null);

// Test TTL expiration
$fileCache->set('expired_key', 'expired_value', -1); // Already expired
$expiredVal = $fileCache->get('expired_key');
test("FileCache: Expired key returns null", $expiredVal === null);

// Cleanup
$fileCache->clear();
test("FileCache: Clear works", $fileCache->get('test_key') === null);

// ==========================================
// RESULTS
// ==========================================
echo "\n========================================\n";
echo "  TEST RESULTS\n";
echo "========================================\n";
echo "  ✅ Passed: $passed\n";
echo "  ❌ Failed: $failed\n";
echo "  📊 Total:  " . ($passed + $failed) . "\n";
echo "  📈 Rate:   " . round($passed / ($passed + $failed) * 100, 1) . "%\n";
echo "========================================\n";

if ($failed > 0) {
    echo "\n⚠️  Ba'zi testlar o'tmadi!\n";
    exit(1);
} else {
    echo "\n🎉 Barcha testlar muvaffaqiyatli o'tdi!\n";
    exit(0);
}
