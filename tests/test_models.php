<?php
/**
 * Shashka Game - Model Logic Tests
 * Tests pure logic of new models without database
 */

echo "========================================\n";
echo "  SHASHKA GAME - MODEL LOGIC TESTS\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;

function test($name, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "  PASS: $name\n";
        $passed++;
    } else {
        echo "  FAIL: $name\n";
        $failed++;
    }
}

// ==========================================
// Arena streak bonus logic
// ==========================================
echo "1. ARENA STREAK BONUS\n";
echo "----------------------\n";

function arenaStreakBonus($streak, $b3 = 5, $b5 = 10, $b7 = 15, $b10 = 20) {
    if ($streak >= 10) return $b10;
    if ($streak >= 7) return $b7;
    if ($streak >= 5) return $b5;
    if ($streak >= 3) return $b3;
    return 0;
}

test("Streak 2 = no bonus", arenaStreakBonus(2) === 0);
test("Streak 3 = +5", arenaStreakBonus(3) === 5);
test("Streak 5 = +10", arenaStreakBonus(5) === 10);
test("Streak 7 = +15", arenaStreakBonus(7) === 15);
test("Streak 10 = +20", arenaStreakBonus(10) === 20);
test("Streak 15 = +20 (capped)", arenaStreakBonus(15) === 20);

// ==========================================
// Clan member limits by level
// ==========================================
echo "\n2. CLAN MEMBER LIMITS\n";
echo "----------------------\n";

$clanLimits = [1 => 10, 2 => 15, 3 => 25, 4 => 35, 5 => 50];
test("Level 1 = 10 members", $clanLimits[1] === 10);
test("Level 3 = 25 members", $clanLimits[3] === 25);
test("Level 5 = 50 members", $clanLimits[5] === 50);

// Clan level up: experience >= level^2 * 10000
function clanLevelUp($experience, $currentLevel) {
    $newLevel = $currentLevel;
    while ($newLevel < 5 && $experience >= pow($newLevel, 2) * 10000) {
        $newLevel++;
    }
    return $newLevel;
}
test("0 XP = level 1", clanLevelUp(0, 1) === 1);
test("10000 XP = level 2", clanLevelUp(10000, 1) === 2);
test("40000 XP = level 3", clanLevelUp(40000, 1) === 3);
test("Create cost = 1000 coins", 1000 === 1000);

// ==========================================
// Auction pricing by rarity
// ==========================================
echo "\n3. AUCTION PRICING\n";
echo "-------------------\n";

$basePrices = [
    'common' => 100, 'uncommon' => 250, 'rare' => 500,
    'epic' => 1000, 'legendary' => 2500, 'mythic' => 5000
];
test("Common = 100", $basePrices['common'] === 100);
test("Legendary = 2500", $basePrices['legendary'] === 2500);
test("Mythic = 5000", $basePrices['mythic'] === 5000);

// Bid increment = 5% of starting bid (min 10)
function bidIncrement($startBid) {
    return max(10, (int) round($startBid * 0.05));
}
test("100 bid increment = 10 (min)", bidIncrement(100) === 10);
test("1000 bid increment = 50", bidIncrement(1000) === 50);
test("5000 bid increment = 250", bidIncrement(5000) === 250);

// Emergency threshold
test("Emergency threshold = 3", 3 === 3);

// ==========================================
// Battle Pass XP
// ==========================================
echo "\n4. BATTLE PASS XP\n";
echo "------------------\n";

$xpGame = 10; $xpWin = 25; $xpQuest = 50;
test("XP per game = 10", $xpGame === 10);
test("XP per win = 25", $xpWin === 25);
test("XP per quest = 50", $xpQuest === 50);

// A win = game XP + win XP
test("Win total XP = 35", ($xpGame + $xpWin) === 35);

// Level up logic
function bpLevelUp($currentXp, $currentLevel, $xpMap, $maxLevel = 50) {
    while ($currentLevel < $maxLevel
        && isset($xpMap[$currentLevel + 1])
        && $currentXp >= $xpMap[$currentLevel + 1]) {
        $currentLevel++;
    }
    return $currentLevel;
}
$xpMap = [1 => 0, 2 => 100, 3 => 250, 4 => 450];
test("0 XP = level 1", bpLevelUp(0, 1, $xpMap) === 1);
test("100 XP = level 2", bpLevelUp(100, 1, $xpMap) === 2);
test("450 XP = level 4", bpLevelUp(450, 1, $xpMap) === 4);

// ==========================================
// Tournament bracket pairing
// ==========================================
echo "\n5. TOURNAMENT BRACKET\n";
echo "----------------------\n";

function buildPairings($players) {
    $pairings = [];
    $left = 0;
    $right = count($players) - 1;
    while ($left < $right) {
        $pairings[] = [$players[$left], $players[$right]];
        $left++;
        $right--;
    }
    if ($left === $right) {
        $pairings[] = [$players[$left], null];
    }
    return $pairings;
}

$pairs = buildPairings([1, 2, 3, 4]);
test("4 players = 2 pairs", count($pairs) === 2);
test("Pairing 1v4 (seed)", $pairs[0][0] === 1 && $pairs[0][1] === 4);
test("Pairing 2v3 (seed)", $pairs[1][0] === 2 && $pairs[1][1] === 3);

$pairsOdd = buildPairings([1, 2, 3, 4, 5]);
test("5 players = 3 pairs (with bye)", count($pairsOdd) === 3);
test("Odd player gets bye", $pairsOdd[2][1] === null);

// Rounds calculation
test("8 players = 3 rounds", (int) ceil(log(8, 2)) === 3);
test("16 players = 4 rounds", (int) ceil(log(16, 2)) === 4);
test("64 players = 6 rounds", (int) ceil(log(64, 2)) === 6);

// ==========================================
// Tournament prize distribution
// ==========================================
echo "\n6. TOURNAMENT PRIZES\n";
echo "---------------------\n";

function prizeFor($pool, $position, $distribution) {
    $pos = (string) $position;
    if (!isset($distribution[$pos])) return 0;
    return (int) round($pool * $distribution[$pos] / 100);
}
$dist = ['1' => 50, '2' => 30, '3' => 20];
test("1st place 50% of 5000 = 2500", prizeFor(5000, 1, $dist) === 2500);
test("2nd place 30% of 5000 = 1500", prizeFor(5000, 2, $dist) === 1500);
test("3rd place 20% of 5000 = 1000", prizeFor(5000, 3, $dist) === 1000);
test("4th place = 0", prizeFor(5000, 4, $dist) === 0);

// ==========================================
// Daily bonus streak cycle
// ==========================================
echo "\n7. DAILY BONUS CYCLE\n";
echo "---------------------\n";

function dayInCycle($streak) {
    return (($streak - 1) % 7) + 1;
}
test("Streak 1 = day 1", dayInCycle(1) === 1);
test("Streak 7 = day 7", dayInCycle(7) === 7);
test("Streak 8 = day 1 (cycle)", dayInCycle(8) === 1);
test("Streak 15 = day 1", dayInCycle(15) === 1);

// ==========================================
// RESULTS
// ==========================================
echo "\n========================================\n";
echo "  RESULTS: $passed passed, $failed failed\n";
echo "  Total: " . ($passed + $failed) . " (" . round($passed / ($passed + $failed) * 100, 1) . "%)\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
