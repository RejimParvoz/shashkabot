<?php
/**
 * Subdirectory Routing + Telegram Webhook Route Test
 */
require_once __DIR__ . '/../core/Router.php';

echo "========================================\n";
echo "  ROUTING & WEBHOOK TEST\n";
echo "========================================\n\n";

$passed = 0; $failed = 0;
function test($n, $c) {
    global $passed, $failed;
    if ($c) { echo "  PASS: $n\n"; $passed++; }
    else { echo "  FAIL: $n\n"; $failed++; }
}

// Helper: invoke the private getCurrentUri via a subclass exposing it
class TestRouter extends Router {
    public function uri() {
        $m = new ReflectionMethod('Router', 'getCurrentUri');
        $m->setAccessible(true);
        return $m->invoke($this);
    }
    public function base() {
        $m = new ReflectionMethod('Router', 'getBasePath');
        $m->setAccessible(true);
        return $m->invoke($this);
    }
    public function match($method, $uri) {
        $fm = new ReflectionMethod('Router', 'findRoute');
        $fm->setAccessible(true);
        return $fm->invoke($this, $method, $uri);
    }
}

echo "1. BASE PATH DETECTION\n----------------------\n";

// Root install: SCRIPT_NAME = /public/index.php -> base ""
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_GET = [];
$r = new TestRouter();
test("Root install base = ''", $r->base() === '');

// Subdirectory: SCRIPT_NAME = /shashka/public/index.php -> base "/shashka"
$_SERVER['SCRIPT_NAME'] = '/shashka/public/index.php';
$r2 = new TestRouter();
test("Subdir base = /shashka", $r2->base() === '/shashka');

echo "\n2. URI RESOLUTION (route param)\n--------------------------------\n";

// .htaccess passes ?route=api/telegram/webhook (relative, no /shashka)
$_GET = ['route' => 'api/telegram/webhook'];
$_SERVER['REQUEST_URI'] = '/shashka/api/telegram/webhook';
$_SERVER['SCRIPT_NAME'] = '/shashka/public/index.php';
$r3 = new TestRouter();
test("route param -> /api/telegram/webhook", $r3->uri() === '/api/telegram/webhook');

echo "\n3. URI RESOLUTION (REQUEST_URI fallback)\n-----------------------------------------\n";

// No route param, must strip /shashka subdirectory
$_GET = [];
$_SERVER['REQUEST_URI'] = '/shashka/api/telegram/webhook';
$_SERVER['SCRIPT_NAME'] = '/shashka/public/index.php';
$r4 = new TestRouter();
test("REQUEST_URI strips /shashka", $r4->uri() === '/api/telegram/webhook');

// Root install fallback
$_GET = [];
$_SERVER['REQUEST_URI'] = '/api/game/modes';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$r5 = new TestRouter();
test("Root install URI unchanged", $r5->uri() === '/api/game/modes');

echo "\n4. WEBHOOK ROUTE MATCHING\n--------------------------\n";

// Register the same routes the app uses for telegram + a param route
$router = new TestRouter();
$router->group('/api', function ($r) {
    $r->post('/telegram/webhook', 'Api\\TelegramController@webhook');
    $r->get('/game/{id}', 'Api\\GameController@show');
});

$m1 = $router->match('POST', '/api/telegram/webhook');
test("POST /api/telegram/webhook matches", $m1 !== null);
test("Resolves to TelegramController@webhook", $m1 && $m1['handler'] === 'Api\\TelegramController@webhook');

$m2 = $router->match('GET', '/api/game/42');
test("GET /api/game/42 matches", $m2 !== null);
test("Captures id param = 42", $m2 && $m2['matched_parameters']['id'] === '42');

$m3 = $router->match('GET', '/api/telegram/webhook');
test("GET (wrong method) does NOT match POST route", $m3 === null);

echo "\n5. TELEGRAM CONTROLLER FILE EXISTS\n-----------------------------------\n";
$ctrlFile = __DIR__ . '/../controllers/Api/TelegramController.php';
test("TelegramController.php exists", file_exists($ctrlFile));
require_once $ctrlFile;
test("TelegramController class loads", class_exists('TelegramController'));
test("webhook() method exists", method_exists('TelegramController', 'webhook'));

echo "\n========================================\n";
echo "  RESULTS: $passed passed, $failed failed\n";
echo "  Total: " . ($passed + $failed) . "\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
