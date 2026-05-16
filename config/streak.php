<?php
/**
 * config/streak.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Daily Streak engine for CheckMasters.
 *
 * Streak milestones & rewards:
 *   Day  3 → 'neon'     piece skin
 *   Day  7 → XP Boost ×2 for 24 hours
 *   Day 14 → 'midnight' board theme
 *   Day 30 → 'galaxy'   legendary piece skin
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Milestone definitions ────────────────────────────────────────────────────
function getStreakMilestones(): array {
    return [
        3  => [
            'label'       => '3 дня подряд',
            'reward_type' => 'skin',
            'reward_slug' => 'neon',
            'reward_name' => 'Neon Cyber',
            'reward_icon' => '✨',
            'reward_desc' => 'Скин шашек «Neon Cyber» разблокирован!',
        ],
        7  => [
            'label'       => '7 дней подряд',
            'reward_type' => 'xp_boost',
            'reward_slug' => 'xp_boost_2x_24h',
            'reward_name' => 'XP Boost ×2',
            'reward_icon' => '⚡',
            'reward_desc' => 'Двойной XP на 24 часа активирован!',
        ],
        14 => [
            'label'       => '14 дней подряд',
            'reward_type' => 'board_theme',
            'reward_slug' => 'midnight',
            'reward_name' => 'Midnight Board',
            'reward_icon' => '🌌',
            'reward_desc' => 'Редкая тема доски «Midnight» разблокирована!',
        ],
        30 => [
            'label'       => '30 дней подряд',
            'reward_type' => 'skin',
            'reward_slug' => 'galaxy',
            'reward_name' => 'Galaxy',
            'reward_icon' => '🪐',
            'reward_desc' => 'Легендарный скин «Galaxy» разблокирован!',
        ],
    ];
}

/**
 * Check & update the daily streak for a user.
 * Call once per page load (idempotent within same day).
 *
 * Returns [
 *   'streak'         => int,
 *   'max_streak'     => int,
 *   'today_played'   => bool,   // true if already counted today
 *   'streak_broken'  => bool,   // true if streak was reset
 *   'new_rewards'    => array,  // newly earned milestone rewards
 *   'xp_boost_active'=> bool,
 *   'xp_boost_until' => string|null,
 * ]
 */
function checkAndUpdateStreak(int $userId): array {
    $user = Database::queryOne(
        "SELECT daily_streak, max_streak, last_played_at, xp_boost_until, last_streak_reward_day FROM users WHERE id = ?",
        [$userId]
    );
    if (!$user) return ['streak' => 0, 'max_streak' => 0, 'today_played' => false, 'streak_broken' => false, 'new_rewards' => [], 'xp_boost_active' => false, 'xp_boost_until' => null];

    $today       = date('Y-m-d');
    $lastPlayed  = $user['last_played_at'];
    $streak      = (int)($user['daily_streak'] ?? 0);
    $maxStreak   = (int)($user['max_streak']   ?? 0);
    $lastReward  = (int)($user['last_streak_reward_day'] ?? 0);
    $todayPlayed = ($lastPlayed === $today);
    $streakBroken= false;

    if (!$todayPlayed) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($lastPlayed === $yesterday) {
            // Consecutive day — extend streak
            $streak++;
        } elseif ($lastPlayed === null || $lastPlayed < $yesterday) {
            // Missed a day — reset
            $streak      = 1;
            $lastReward  = 0;
            $streakBroken = ($user['daily_streak'] > 1);
        }
        $maxStreak = max($maxStreak, $streak);

        Database::execute(
            "UPDATE users SET daily_streak=?, max_streak=?, last_played_at=?, last_streak_reward_day=? WHERE id=?",
            [$streak, $maxStreak, $today, $lastReward, $userId]
        );
    }

    // ── Check milestone rewards ──────────────────────────────────────────────
    $newRewards = [];
    $milestones = getStreakMilestones();
    // Sort milestones ascending so we handle 3 → 7 → 14 → 30 in order
    ksort($milestones);

    foreach ($milestones as $day => $reward) {
        if ($streak >= $day && $lastReward < $day) {
            $granted = grantStreakReward($userId, $day, $reward);
            if ($granted) {
                $newRewards[]= array_merge($reward, ['milestone' => $day]);
                $lastReward  = $day;
                // Persist updated lastReward milestone
                Database::execute(
                    "UPDATE users SET last_streak_reward_day=? WHERE id=?",
                    [$lastReward, $userId]
                );
            }
        }
    }

    // ── XP boost status ─────────────────────────────────────────────────────
    $boostUntil   = $user['xp_boost_until'];
    $boostActive  = $boostUntil && strtotime($boostUntil) > time();

    return [
        'streak'          => $streak,
        'max_streak'      => $maxStreak,
        'today_played'    => $todayPlayed,
        'streak_broken'   => $streakBroken,
        'new_rewards'     => $newRewards,
        'xp_boost_active' => $boostActive,
        'xp_boost_until'  => $boostUntil,
    ];
}

/**
 * Grant a single streak reward.
 * Returns true if newly granted, false if already given.
 */
function grantStreakReward(int $userId, int $milestone, array $reward): bool {
    switch ($reward['reward_type']) {
        case 'skin':
        case 'board_theme':
            // Idempotent insert into user_skins
            $existing = Database::queryOne(
                "SELECT id FROM user_skins WHERE user_id=? AND skin_slug=?",
                [$userId, $reward['reward_slug']]
            );
            if ($existing) return false;
            Database::execute(
                "INSERT IGNORE INTO user_skins (user_id, skin_slug) VALUES (?, ?)",
                [$userId, $reward['reward_slug']]
            );
            break;

        case 'xp_boost':
            // Extend or set the boost window
            Database::execute(
                "UPDATE users SET xp_boost_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id=?",
                [$userId]
            );
            break;
    }

    // Log the event
    Database::execute(
        "INSERT INTO streak_reward_events (user_id, milestone, reward_type, reward_slug) VALUES (?, ?, ?, ?)",
        [$userId, $milestone, $reward['reward_type'], $reward['reward_slug']]
    );

    return true;
}

/**
 * Returns next milestone info relative to current streak.
 * e.g. streak=5 → next is day 7, need 2 more days.
 */
function getNextStreakMilestone(int $streak): ?array {
    foreach (getStreakMilestones() as $day => $reward) {
        if ($streak < $day) {
            return array_merge($reward, [
                'milestone'   => $day,
                'days_left'   => $day - $streak,
                'progress_pct'=> round(($streak / $day) * 100),
            ]);
        }
    }
    return null; // past all milestones
}

/**
 * Is XP boost currently active for a user?
 */
function isXpBoostActive(int $userId): bool {
    $row = Database::queryOne("SELECT xp_boost_until FROM users WHERE id=?", [$userId]);
    return $row && $row['xp_boost_until'] && strtotime($row['xp_boost_until']) > time();
}
