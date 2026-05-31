<?php
/**
 * Class Loading Integration Test
 * Requires all classes and verifies they load without fatal errors
 */

error_reporting(E_ALL & ~E_DEPRECATED);
echo "========================================\n";
echo "  CLASS LOADING INTEGRATION TEST\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;
function test($name, $cond) {
    global $passed, $failed;
    if ($cond) { echo "  PASS: $name\n"; $passed++; }
    else { echo "  FAIL: $name\n"; $failed++; }
}

$root = __DIR__ . '/..';

// Load core (order matters)
require_once $root . '/core/Cache.php';
require_once $root . '/core/cache/ArrayCache.php';
require_once $root . '/core/cache/FileCache.php';
require_once $root . '/core/cache/NullCache.php';
require_once $root . '/core/cache/RedisCache.php';
require_once $root . '/core/cache/DatabaseCache.php';
require_once $root . '/core/Logger.php';
require_once $root . '/core/Database.php';
require_once $root . '/core/Router.php';
require_once $root . '/core/RateLimiter.php';
require_once $root . '/core/Security.php';
require_once $root . '/core/Controller.php';
require_once $root . '/core/Model.php';

echo "1. CORE CLASSES\n----------------\n";
test("Cache class exists", class_exists('Cache'));
test("ArrayCache exists", class_exists('ArrayCache'));
test("FileCache exists", class_exists('FileCache'));
test("DatabaseCache exists", class_exists('DatabaseCache'));
test("Logger exists", class_exists('Logger'));
test("Database exists", class_exists('Database'));
test("Router exists", class_exists('Router'));
test("RateLimiter exists", class_exists('RateLimiter'));
test("Security exists", class_exists('Security'));
test("Controller (abstract) exists", class_exists('Controller'));
test("Model (abstract) exists", class_exists('Model'));

// Load models
echo "\n2. MODELS\n----------\n";
require_once $root . '/models/User.php';
require_once $root . '/models/Game.php';
require_once $root . '/models/Shop.php';
require_once $root . '/models/Tournament.php';
require_once $root . '/models/Arena.php';
require_once $root . '/models/Clan.php';
require_once $root . '/models/BattlePass.php';
require_once $root . '/models/Auction.php';
require_once $root . '/models/Achievement.php';
require_once $root . '/models/Quest.php';

$models = ['User', 'Game', 'Shop', 'Tournament', 'Arena', 'Clan', 'BattlePass', 'Auction', 'Achievement', 'Quest'];
foreach ($models as $m) {
    test("$m extends Model", class_exists($m) && is_subclass_of($m, 'Model'));
}

// Verify key constants / methods
echo "\n3. MODEL CONTRACTS\n-------------------\n";
test("Game::STATUS_ACTIVE = active", Game::STATUS_ACTIVE === 'active');
test("Game::INITIAL_BOARD is 8x8", count(Game::INITIAL_BOARD) === 8 && count(Game::INITIAL_BOARD[0]) === 8);
test("Clan::CREATE_COST_COINS = 1000", Clan::CREATE_COST_COINS === 1000);
test("Clan member limits L5 = 50", Clan::MAX_MEMBERS_BY_LEVEL[5] === 50);
test("Auction::EMERGENCY_THRESHOLD = 3", Auction::EMERGENCY_THRESHOLD === 3);
test("Auction::EXTENSION_MINUTES = 5", Auction::EXTENSION_MINUTES === 5);
test("BattlePass::XP_PER_WIN = 25", BattlePass::XP_PER_WIN === 25);
test("Quest::MAX_DAILY_QUESTS = 3", Quest::MAX_DAILY_QUESTS === 3);
test("Shop::RARITY_MYTHIC = mythic", Shop::RARITY_MYTHIC === 'mythic');
test("Tournament::TYPE_DAILY = daily", Tournament::TYPE_DAILY === 'daily');
test("Achievement::TIER_PLATINUM = platinum", Achievement::TIER_PLATINUM === 'platinum');

// Load controllers
echo "\n4. CONTROLLERS\n---------------\n";
require_once $root . '/controllers/Api/AuthController.php';
require_once $root . '/controllers/Api/GameController.php';
require_once $root . '/controllers/Api/ShopController.php';
require_once $root . '/controllers/Api/TournamentController.php';
require_once $root . '/controllers/Api/ArenaController.php';
require_once $root . '/controllers/Api/ClanController.php';
require_once $root . '/controllers/Api/SocialController.php';
require_once $root . '/controllers/Api/PaymentController.php';
require_once $root . '/controllers/Api/VipController.php';
require_once $root . '/controllers/Api/DailyController.php';
require_once $root . '/controllers/Api/LeaderboardController.php';
require_once $root . '/controllers/Api/UserController.php';
require_once $root . '/controllers/Admin/DashboardController.php';
require_once $root . '/controllers/PageController.php';

$controllers = [
    'AuthController', 'GameController', 'ShopController', 'TournamentController',
    'ArenaController', 'ClanController', 'SocialController', 'PaymentController',
    'VipController', 'DailyController', 'LeaderboardController', 'UserController',
    'DashboardController', 'PageController'
];
foreach ($controllers as $c) {
    test("$c extends Controller", class_exists($c) && is_subclass_of($c, 'Controller'));
}

// Verify Router actually works end-to-end
echo "\n5. ROUTER INTEGRATION\n----------------------\n";
$router = new Router();
$router->group('/api', function ($r) {
    $r->get('/game/{id}', 'GameController@show');
    $r->post('/shop/purchase', 'ShopController@purchase');
});
$routes = $router->getRoutes();
test("Router registered group routes", count($routes) === 2);
test("Group prefix applied", strpos($routes[0]['path'], '/api/game') === 0);

// Cache works end-to-end with ArrayCache layer
echo "\n6. CACHE INTEGRATION\n---------------------\n";
$cacheConfig = [
    'default' => 'memory',
    'prefix' => 'test:',
    'layers' => [],
    'stores' => [ 'memory' => ['driver' => 'array', 'max_size' => 1048576, 'ttl' => 60] ],
    'ttl' => []
];
$cache = new Cache($cacheConfig);
$cache->set('foo', ['bar' => 123]);
$val = $cache->get('foo');
test("Cache multi-layer set/get", is_array($val) && $val['bar'] === 123);
test("Cache remember()", $cache->remember('lazy', 60, function () { return 'computed'; }) === 'computed');

echo "\n========================================\n";
echo "  RESULTS: $passed passed, $failed failed\n";
echo "  Total: " . ($passed + $failed) . "\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
