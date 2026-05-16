<?php
/**
 * api/check_streak.php
 * Called via AJAX on lobby page load to update & return streak state.
 * Safe to call multiple times per day (idempotent).
 */
require_once __DIR__ . '/../config/app.php';
requireAuth();

$userId = (int)$_SESSION['user_id'];
$result = checkAndUpdateStreak($userId);

// Build milestones array for UI (all 4, with status)
$milestones   = getStreakMilestones();
$streak       = $result['streak'];
$uiMilestones = [];
foreach ($milestones as $day => $reward) {
    $uiMilestones[] = [
        'day'          => $day,
        'icon'         => $reward['reward_icon'],
        'name'         => $reward['reward_name'],
        'reached'      => $streak >= $day,
        'days_left'    => max(0, $day - $streak),
    ];
}

$nextMilestone = getNextStreakMilestone($streak);

jsonSuccess([
    'streak'           => $streak,
    'max_streak'       => $result['max_streak'],
    'today_played'     => $result['today_played'],
    'streak_broken'    => $result['streak_broken'],
    'new_rewards'      => $result['new_rewards'],
    'xp_boost_active'  => $result['xp_boost_active'],
    'xp_boost_until'   => $result['xp_boost_until'],
    'milestones'       => $uiMilestones,
    'next_milestone'   => $nextMilestone,
]);
