<?php
/**
 * Shashka Game - Page Controller
 * Renders the HTML pages (Telegram Mini App SPA shell)
 */

require_once __DIR__ . '/../core/Controller.php';

class PageController extends Controller {

    /**
     * Home page - main SPA entry
     */
    public function index($params = []) {
        $this->renderLayout('home');
    }

    /**
     * Game page
     */
    public function game($params = []) {
        $this->renderLayout('game', [
            'game_id' => isset($params['id']) ? (int) $params['id'] : null
        ]);
    }

    /**
     * Tournament page
     */
    public function tournament($params = []) {
        $this->renderLayout('tournament', [
            'tournament_id' => isset($params['id']) ? (int) $params['id'] : null
        ]);
    }

    /**
     * Profile page
     */
    public function profile($params = []) {
        $this->renderLayout('profile', [
            'profile_id' => isset($params['id']) ? (int) $params['id'] : null
        ]);
    }

    /**
     * Shop page
     */
    public function shop($params = []) {
        $this->renderLayout('shop');
    }

    /**
     * Render the main layout with a given page
     */
    private function renderLayout($page, $data = []) {
        $layoutPath = __DIR__ . '/../views/layouts/main.php';

        if (!file_exists($layoutPath)) {
            http_response_code(500);
            echo 'Layout not found';
            return;
        }

        // Make data available to the view
        extract($data);
        $currentPage = $page;

        header('Content-Type: text/html; charset=utf-8');
        require $layoutPath;
    }
}
