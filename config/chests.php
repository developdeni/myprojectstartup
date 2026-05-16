<?php
/**
 * config/chests.php
 * ──────────────────────────────────────────────────────────────────────────
 * Chest & Reward engine for CheckMasters.
 *
 * Chest types (ascending rarity): common → rare → epic → legendary
 * ──────────────────────────────────────────────────────────────────────────
 */

// ── Catalog ───────────────────────────────────────────────────────────────

function getChestMeta(): array {
    return [
        'common'    => ['name'=>'Common Chest',    'emoji'=>'📦', 'color'=>'#9ca3af', 'glow'=>'rgba(156,163,175,.4)'],
        'rare'      => ['name'=>'Rare Chest',      'emoji'=>'💠', 'color'=>'#3b82f6', 'glow'=>'rgba(59,130,246,.5)'],
        'epic'      => ['name'=>'Epic Chest',      'emoji'=>'💜', 'color'=>'#8b5cf6', 'glow'=>'rgba(139,92,246,.6)'],
        'legendary' => ['name'=>'Legendary Chest', 'emoji'=>'🏆', 'color'=>'#f59e0b', 'glow'=>'rgba(245,158,11,.7)'],
    ];
}

// Reward catalog: [type, value, name, icon, weight]
function getRewardCatalog(): array {
    return [
        'common' => [
            ['type'=>'xp',   'value'=>'75',   'name'=>'+75 XP',          'icon'=>'⚡', 'weight'=>35],
            ['type'=>'xp',   'value'=>'100',  'name'=>'+100 XP',         'icon'=>'⚡', 'weight'=>30],
            ['type'=>'xp',   'value'=>'50',   'name'=>'+50 XP',          'icon'=>'⚡', 'weight'=>20],
            ['type'=>'skin', 'value'=>'neon', 'name'=>'Neon Cyber Skin', 'icon'=>'✨', 'weight'=>15],
        ],
        'rare' => [
            ['type'=>'xp',          'value'=>'200',     'name'=>'+200 XP',       'icon'=>'⚡', 'weight'=>30],
            ['type'=>'xp',          'value'=>'150',     'name'=>'+150 XP',       'icon'=>'⚡', 'weight'=>20],
            ['type'=>'skin',        'value'=>'fire',    'name'=>'Fire Storm',    'icon'=>'🔥', 'weight'=>25],
            ['type'=>'board_theme', 'value'=>'midnight','name'=>'Midnight Board','icon'=>'🌌', 'weight'=>25],
        ],
        'epic' => [
            ['type'=>'xp',          'value'=>'400',    'name'=>'+400 XP',       'icon'=>'⚡', 'weight'=>20],
            ['type'=>'skin',        'value'=>'gold',   'name'=>'Gold Rush',     'icon'=>'🥇', 'weight'=>30],
            ['type'=>'skin',        'value'=>'emerald','name'=>'Emerald',       'icon'=>'💎', 'weight'=>25],
            ['type'=>'board_theme', 'value'=>'inferno','name'=>'Inferno Board', 'icon'=>'🔥', 'weight'=>25],
        ],
        'legendary' => [
            ['type'=>'xp',           'value'=>'750',       'name'=>'+750 XP',           'icon'=>'⚡', 'weight'=>25],
            ['type'=>'skin',         'value'=>'galaxy',    'name'=>'Galaxy',            'icon'=>'🪐', 'weight'=>40],
            ['type'=>'win_animation','value'=>'fireworks', 'name'=>'Fireworks Victory', 'icon'=>'🎆', 'weight'=>35],
        ],
    ];
}

// ── Chest type probability ────────────────────────────────────────────────

/**
 * Determine which chest type a player earns after a win.
 * $ctx: ['mode', 'difficulty', 'rating', 'streak']
 */
function determineChestType(array $ctx): string {
    // Base weights [common, rare, epic, legendary]
    $weights = match($ctx['mode'] ?? 'ai') {
        'ai' => match($ctx['difficulty'] ?? 'medium') {
            'easy'   => [60, 28,  10,  2],
            'medium' => [40, 38,  17,  5],
            'hard'   => [20, 35,  30, 15],
            'expert' => [10, 24,  36, 30],
            default  => [40, 38,  17,  5],
        },
        'online' => [25, 35, 25, 15],
        'pvp'    => [35, 35, 20, 10],
        default  => [50, 33, 13,  4],
    };

    // Streak bonus: shift common weight toward Legendary
    $streak = (int)($ctx['streak'] ?? 0);
    if ($streak >= 7) { $weights[0] -= 15; $weights[3] += 15; }
    elseif ($streak >= 5) { $weights[0] -= 10; $weights[3] += 10; }
    elseif ($streak >= 3) { $weights[0] -= 5;  $weights[2] += 5; }

    // High rating bonus
    $rating = (int)($ctx['rating'] ?? 1000);
    if ($rating >= 1800)    { $weights[0] -= 10; $weights[3] += 10; }
    elseif ($rating >= 1400){ $weights[0] -= 5;  $weights[2] += 5; }

    // Clamp negatives
    $weights = array_map(fn($w) => max(0, $w), $weights);

    $types  = ['common','rare','epic','legendary'];
    $total  = array_sum($weights);
    $roll   = mt_rand(1, max(1, $total));
    $cum    = 0;
    foreach ($types as $i => $type) {
        $cum += $weights[$i];
        if ($roll <= $cum) return $type;
    }
    return 'common';
}

// ── Reward generation ─────────────────────────────────────────────────────

/** Pick N rewards from a chest type using weighted random. */
function generateRewards(string $chestType, int $count = 2): array {
    $catalog  = getRewardCatalog()[$chestType] ?? getRewardCatalog()['common'];
    $total    = array_sum(array_column($catalog, 'weight'));
    $selected = [];
    $used     = [];

    for ($i = 0; $i < $count && count($catalog) > count($used); $i++) {
        $roll = mt_rand(1, max(1, $total));
        $cum  = 0;
        foreach ($catalog as $idx => $r) {
            if (in_array($idx, $used)) continue;
            $cum += $r['weight'];
            if ($roll <= $cum) {
                $selected[] = $r;
                $used[]     = $idx;
                break;
            }
        }
    }
    return $selected;
}

// ── Award & Open ──────────────────────────────────────────────────────────

/** Create a chest record for a user. Returns chest ID. */
function awardChest(int $userId, string $chestType): int {
    Database::execute(
        "INSERT INTO user_chests (user_id, chest_type) VALUES (?, ?)",
        [$userId, $chestType]
    );
    return (int)Database::queryOne("SELECT LAST_INSERT_ID() as id")['id'];
}

/**
 * Open a chest: pick rewards, persist them, return reward details.
 * Returns null if chest not found / already opened / not owned.
 */
function openChest(int $userId, int $chestId): ?array {
    $chest = Database::queryOne(
        "SELECT * FROM user_chests WHERE id=? AND user_id=? AND opened_at IS NULL",
        [$chestId, $userId]
    );
    if (!$chest) return null;

    // Mark opened
    Database::execute(
        "UPDATE user_chests SET opened_at=NOW() WHERE id=?",
        [$chestId]
    );

    $chestType = $chest['chest_type'];
    $numRewards = match($chestType) {
        'rare'      => 2,
        'epic'      => 3,
        'legendary' => 3,
        default     => 2,
    };

    $rewards = generateRewards($chestType, $numRewards);
    $granted = [];

    foreach ($rewards as $r) {
        // Grant reward to user
        grantReward($userId, $r);

        // Record it
        Database::execute(
            "INSERT INTO user_chest_rewards (user_chest_id, user_id, reward_type, reward_value) VALUES (?,?,?,?)",
            [$chestId, $userId, $r['type'], $r['value']]
        );
        $granted[] = $r;
    }

    return [
        'chest_type' => $chestType,
        'chest_meta' => getChestMeta()[$chestType],
        'rewards'    => $granted,
    ];
}

/** Apply a reward to the user (XP / skin unlock). */
function grantReward(int $userId, array $reward): void {
    switch ($reward['type']) {
        case 'xp':
            awardXp($userId, (int)$reward['value'], 'chest_reward');
            break;

        case 'skin':
        case 'board_theme':
        case 'win_animation':
            Database::execute(
                "INSERT IGNORE INTO user_skins (user_id, skin_slug) VALUES (?,?)",
                [$userId, $reward['value']]
            );
            break;
    }
}

/** Count unopened chests for a user. */
function getUnopenedChests(int $userId): array {
    return Database::query(
        "SELECT * FROM user_chests WHERE user_id=? AND opened_at IS NULL ORDER BY earned_at DESC",
        [$userId]
    );
}
