<?php
/**
 * config/achievements.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Achievement definitions and unlock logic for CheckMasters.
 *
 * Each achievement has a slug (DB id) and an optional XP reward on first unlock.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// XP bonus granted when an achievement is unlocked
const ACHIEVEMENT_XP = [
    'first_win'       => 30,
    'wins_3_streak'   => 20,
    'games_10'        => 25,
    'double_killer'   => 15,
    'king_hunter'     => 20,
    'no_loss_victory' => 30,
    'games_50'        => 50,
    'beat_hard_ai'    => 40,
    'win_under_3min'  => 35,
    'wins_5_streak'   => 40,
    'online_winner'   => 25,
    'rating_1200'     => 30,
];

/**
 * Return list of all achievement slugs already unlocked for a user.
 */
function getUserAchievements(int $userId): array {
    $rows = Database::query(
        "SELECT achievement_id FROM user_achievements WHERE user_id = ?",
        [$userId]
    );
    return array_column($rows, 'achievement_id');
}

/**
 * Unlock a single achievement for a user (idempotent via IGNORE).
 * Returns true if it was newly unlocked, false if already had it.
 */
function unlockAchievement(int $userId, string $slug): bool {
    // Check if already unlocked
    $existing = Database::queryOne(
        "SELECT id FROM user_achievements WHERE user_id=? AND achievement_id=?",
        [$userId, $slug]
    );
    if ($existing) return false;

    Database::execute(
        "INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)",
        [$userId, $slug]
    );

    // Award bonus XP for unlock
    $xpBonus = ACHIEVEMENT_XP[$slug] ?? 0;
    if ($xpBonus > 0) {
        awardXp($userId, $xpBonus, "achievement_{$slug}");
    }

    return true;
}

/**
 * Main entry point: check all achievements that could be triggered by a game event.
 *
 * $context = [
 *   'user'           => full user row (BEFORE stat update),
 *   'isWin'          => bool,
 *   'isDraw'         => bool,
 *   'mode'           => pvp|ai|online,
 *   'difficulty'     => easy|medium|hard|expert,
 *   'durationSec'    => int|null,
 *   'newStreak'      => int,
 *   'wins'           => int  (new wins count),
 *   'total'          => int  (new total_games count),
 *   'newRating'      => int,
 *   'noPiecesLost'   => bool  (optional, sent from JS),
 *   'hadDoubleCapt'  => bool  (optional, sent from JS),
 *   'hadKingCapture' => bool  (optional, sent from JS),
 *   'gameId'         => int|null,
 * ]
 *
 * Returns array of newly unlocked achievement slugs.
 */
function checkAchievements(int $userId, array $ctx): array {
    $unlocked = [];

    // Helper closure — unlock & collect
    $try = function(string $slug) use ($userId, &$unlocked) {
        if (unlockAchievement($userId, $slug)) {
            $unlocked[] = $slug;
        }
    };

    // ── Basic ──────────────────────────────────────────────────────────────
    if ($ctx['wins'] >= 1)  $try('first_win');
    if ($ctx['total'] >= 10) $try('games_10');
    if ($ctx['total'] >= 50) $try('games_50');

    if ($ctx['isWin']) {
        if ($ctx['newStreak'] >= 3) $try('wins_3_streak');
        if ($ctx['newStreak'] >= 5) $try('wins_5_streak');

        // ── Advanced ────────────────────────────────────────────────────────
        if (!empty($ctx['noPiecesLost']))   $try('no_loss_victory');
        if (!empty($ctx['hadDoubleCapt']))  $try('double_killer');
        if (!empty($ctx['hadKingCapture'])) $try('king_hunter');

        if ($ctx['mode'] === 'online')      $try('online_winner');

        // ── Hardcore ────────────────────────────────────────────────────────
        if (
            $ctx['mode'] === 'ai' &&
            in_array($ctx['difficulty'], ['hard', 'expert'], true)
        ) {
            $try('beat_hard_ai');
        }

        if (
            $ctx['durationSec'] !== null &&
            $ctx['durationSec'] > 0 &&
            $ctx['durationSec'] < 180   // under 3 minutes
        ) {
            $try('win_under_3min');
        }
    }

    // Rating milestone (any game result)
    if ((int)$ctx['newRating'] >= 1200) $try('rating_1200');

    return $unlocked;
}

/**
 * Fetch all achievements with unlock status for a user (for profile display).
 * Returns an array of achievement rows with extra 'unlocked' and 'unlocked_at' fields.
 */
function getAllAchievementsForUser(int $userId): array {
    return Database::query(
        "SELECT a.*,
            ua.unlocked_at,
            IF(ua.user_id IS NOT NULL, 1, 0) AS unlocked
         FROM achievements a
         LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
         ORDER BY a.sort_order",
        [$userId]
    );
}
