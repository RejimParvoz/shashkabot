<?php
/**
 * Shashka Game - Daily Controller
 * Handles daily login bonus and daily quests
 */

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Quest.php';

class DailyController extends Controller {

    /**
     * Get daily bonus status (7-day streak)
     */
    public function bonus($params = []) {
        try {
            $this->requireAuth();

            $tracking = $this->db->first(
                "SELECT * FROM user_daily_bonuses WHERE user_id = ?",
                [$this->user['id']]
            );

            $bonuses = $this->db->get("SELECT * FROM daily_bonuses WHERE is_active = 1 ORDER BY day ASC");

            $currentStreak = $tracking ? (int) $tracking['current_streak'] : 0;
            $lastClaim = $tracking ? $tracking['last_claim_date'] : null;
            $canClaim = ($lastClaim !== date('Y-m-d'));

            $this->success([
                'current_streak' => $currentStreak,
                'last_claim_date' => $lastClaim,
                'can_claim' => $canClaim,
                'next_day' => ($currentStreak % 7) + 1,
                'bonuses' => $bonuses
            ]);
        } catch (Exception $e) {
            $this->logger->error('Daily bonus error: ' . $e->getMessage());
            $this->error('Bonusni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Claim daily login bonus
     */
    public function claimBonus($params = []) {
        try {
            $this->requireAuth();

            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $tracking = $this->db->first(
                "SELECT * FROM user_daily_bonuses WHERE user_id = ?",
                [$this->user['id']]
            );

            // Already claimed today?
            if ($tracking && $tracking['last_claim_date'] === $today) {
                $this->error('Bugun allaqachon olgansiz');
            }

            // Calculate new streak
            $currentStreak = 0;
            if ($tracking) {
                $currentStreak = ($tracking['last_claim_date'] === $yesterday)
                    ? (int) $tracking['current_streak']
                    : 0;
            }
            $newStreak = $currentStreak + 1;
            $dayInCycle = (($newStreak - 1) % 7) + 1;

            // Get reward for this day
            $bonus = $this->db->first(
                "SELECT * FROM daily_bonuses WHERE day = ? AND is_active = 1",
                [$dayInCycle]
            );

            if (!$bonus) {
                $this->error('Bonus topilmadi', 404);
            }

            $user = User::find($this->user['id']);

            User::transaction(function () use ($user, $bonus, $tracking, $newStreak, $today) {
                // Award currencies
                if ($bonus['diamonds'] > 0) {
                    $user->addCurrency('diamonds', (int) $bonus['diamonds'], 'daily_bonus');
                }
                if ($bonus['coins'] > 0) {
                    $user->addCurrency('coins', (int) $bonus['coins'], 'daily_bonus');
                }

                // Update tracking
                $maxStreak = $tracking ? max((int) $tracking['max_streak'], $newStreak) : $newStreak;
                if ($tracking) {
                    $this->db->update(
                        "UPDATE user_daily_bonuses SET current_streak = ?, max_streak = ?,
                         last_claim_date = ?, total_claimed = total_claimed + 1, updated_at = NOW()
                         WHERE user_id = ?",
                        [$newStreak, $maxStreak, $today, $this->user['id']]
                    );
                } else {
                    $this->db->insert(
                        "INSERT INTO user_daily_bonuses (user_id, current_streak, max_streak, last_claim_date, total_claimed, created_at)
                         VALUES (?, ?, ?, ?, 1, NOW())",
                        [$this->user['id'], $newStreak, $newStreak, $today]
                    );
                }
            });

            $this->success([
                'day' => $dayInCycle,
                'streak' => $newStreak,
                'diamonds' => (int) $bonus['diamonds'],
                'coins' => (int) $bonus['coins'],
                'balance' => ['diamonds' => $user->diamonds, 'coins' => $user->coins]
            ], 'Kunlik bonus olindi!');
        } catch (Exception $e) {
            $this->logger->error('Daily claim error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get daily quests
     */
    public function quests($params = []) {
        try {
            $this->requireAuth();
            $quests = Quest::getUserQuests($this->user['id']);
            $this->success($quests);
        } catch (Exception $e) {
            $this->logger->error('Daily quests error: ' . $e->getMessage());
            $this->error('Vazifalarni yuklab bo\'lmadi', 500);
        }
    }

    /**
     * Claim a completed quest reward
     */
    public function completeQuest($params = []) {
        try {
            $this->requireAuth();
            $this->validateRequired(['user_quest_id']);

            $result = Quest::claimReward($this->user['id'], (int) $this->input('user_quest_id'));

            $user = User::find($this->user['id']);
            $this->success([
                'result' => $result,
                'balance' => ['diamonds' => $user->diamonds, 'coins' => $user->coins]
            ], 'Mukofot olindi!');
        } catch (Exception $e) {
            $this->logger->error('Quest claim error: ' . $e->getMessage());
            $this->error($e->getMessage(), 400);
        }
    }
}
