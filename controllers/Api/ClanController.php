<?php
/**
 * Shashka Game - Clan Controller
 * Handles clan listing, creation, membership and management
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/Clan.php';

class ClanController extends Controller {

    /**
     * List top clans
     */
    public function index($params = []) {
        try {
            $limit = min(50, max(1, (int) $this->input('limit', 50)));
            $clans = Clan::getTopClans($limit);
            $this->success($clans);
        } catch (Exception $e) {
            $this->logger->error('Clan list error: ' . $e->getMessage());
            $this->error('Klanlarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Create a new clan
     */
    public function create($params = []) {
        try {
            $this->requireAuth();
            $this->applyRateLimit(null, 3, 60);
            $this->validateRequired(['name', 'tag']);

            $name = trim($this->input('name'));
            $tag = trim($this->input('tag'));
            $description = $this->input('description');

            if (strlen($name) < 3 || strlen($name) > 50) {
                $this->error('Klan nomi 3-50 belgi bo\'lishi kerak');
            }
            if (strlen($tag) < 2 || strlen($tag) > 10) {
                $this->error('Teg 2-10 belgi bo\'lishi kerak');
            }

            $clan = Clan::createClan($this->user['id'], $name, $tag, $description);

            $this->logUserAction('clan_create', ['clan_id' => $clan->id]);
            $this->success($clan->toArray(), 'Klan yaratildi!');
        } catch (Exception $e) {
            $this->logger->error('Clan create error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Show clan details with members
     */
    public function show($params = []) {
        try {
            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('id');
            $clan = Clan::find($id);

            if (!$clan) {
                $this->error('Klan topilmadi', 404);
            }

            $data = $clan->toArray();
            $data['members'] = $clan->getMembers();

            $this->success($data);
        } catch (Exception $e) {
            $this->logger->error('Clan show error: ' . $e->getMessage());
            $this->error('Klanni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Join a clan
     */
    public function join($params = []) {
        try {
            $this->requireAuth();
            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('clan_id');

            $clan = Clan::find($id);
            if (!$clan) {
                $this->error('Klan topilmadi', 404);
            }

            $clan->addMember($this->user['id']);
            $this->success(null, 'Klanga qo\'shildingiz!');
        } catch (Exception $e) {
            $this->logger->error('Clan join error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Leave a clan
     */
    public function leave($params = []) {
        try {
            $this->requireAuth();
            $id = isset($params['id']) ? (int) $params['id'] : (int) $this->input('clan_id');

            $clan = Clan::find($id);
            if (!$clan) {
                $this->error('Klan topilmadi', 404);
            }

            $clan->removeMember($this->user['id']);
            $this->success(null, 'Klandan chiqdingiz');
        } catch (Exception $e) {
            $this->logger->error('Clan leave error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
