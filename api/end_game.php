<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();

$data        = json_decode(file_get_contents('php://input'), true);
$winner      = $data['winner']          ?? null;   // 1=win, 0=loss, null=draw
$mode        = $data['mode']            ?? 'ai';   // pvp|ai|online
$difficulty  = $data['difficulty']      ?? 'medium';
$gameId      = isset($data['game_id'])          ? (int)$data['game_id']          : null;
$durationSec = isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : null;
$noPiecesLost   = !empty($data['no_pieces_lost']);
$hadDoubleCapt  = !empty($data['had_double_capture']);
$hadKingCapture = !empty($data['had_king_capture']);
$userId      = (int)$_SESSION['user_id'];

$user = Database::queryOne("SELECT * FROM users WHERE id=?", [$userId]);
if (!$user) jsonError('User not found', 404);

$isWin  = $winner === 1 || $winner === '1' || $winner === true;
$isDraw = $winner === null;

// ─── Stats & Rating ────────────────────────────────────────────────────────
$wins      = $user['wins']        + ($isWin ? 1 : 0);
$losses    = $user['losses']      + (!$isWin && !$isDraw ? 1 : 0);
$draws     = $user['draws']       + ($isDraw ? 1 : 0);
$total     = $user['total_games'] + 1;
$newRating = (int)$user['rating'];
if ($mode === 'online') {
    $score     = $isWin ? 1.0 : ($isDraw ? 0.5 : 0.0);
    $elo       = calculateElo((int)$user['rating'], 1200, $score);
    $newRating = $elo['new_a'];
}

// ─── Win streak ────────────────────────────────────────────────────────────
$currentStreak = (int)($user['win_streak'] ?? 0);
$newStreak     = $isWin ? $currentStreak + 1 : 0;

// ─── XP Calculation ────────────────────────────────────────────────────────
$xpGained = 0;
$xpEvents = [];

if ($isWin) {
    if ($mode === 'pvp' || $mode === 'online') {
        $xpGained += 50; $xpEvents[] = 'win_pvp';
    } elseif ($mode === 'ai') {
        if ($difficulty === 'medium')                              { $xpGained += 60; $xpEvents[] = 'win_ai_medium'; }
        elseif (in_array($difficulty, ['hard','expert'], true))    { $xpGained += 80; $xpEvents[] = 'win_ai_hard'; }
        else                                                       { $xpGained += 30; $xpEvents[] = 'win_ai_easy'; }
    }
    if ($durationSec !== null && $durationSec > 0 && $durationSec < 300) {
        $xpGained += 30; $xpEvents[] = 'win_fast';
    }
    if ($newStreak > 0 && $newStreak % 3 === 0) {
        $xpGained += 20; $xpEvents[] = 'win_streak_3';
    }
}

// ─── Save stats ────────────────────────────────────────────────────────────
Database::execute(
    "UPDATE users SET wins=?,losses=?,draws=?,total_games=?,rating=?,win_streak=? WHERE id=?",
    [$wins, $losses, $draws, $total, $newRating, $newStreak, $userId]
);

// ─── Award XP ──────────────────────────────────────────────────────────────
$xpResult = null;
if ($xpGained > 0) {
    $xpResult = awardXp($userId, $xpGained, implode('+', $xpEvents), $gameId);
}

// ─── Achievements ──────────────────────────────────────────────────────────
$newAchievements = checkAchievements($userId, [
    'user'           => $user,
    'isWin'          => $isWin,
    'isDraw'         => $isDraw,
    'mode'           => $mode,
    'difficulty'     => $difficulty,
    'durationSec'    => $durationSec,
    'newStreak'      => $newStreak,
    'wins'           => $wins,
    'total'          => $total,
    'newRating'      => $newRating,
    'noPiecesLost'   => $noPiecesLost,
    'hadDoubleCapt'  => $hadDoubleCapt,
    'hadKingCapture' => $hadKingCapture,
    'gameId'         => $gameId,
]);

// Enrich unlocked achievements with metadata for the frontend popup
$achievementDetails = [];
if (!empty($newAchievements)) {
    $placeholders = implode(',', array_fill(0, count($newAchievements), '?'));
    $achievementDetails = Database::query(
        "SELECT * FROM achievements WHERE id IN ($placeholders)",
        $newAchievements
    );
}

// ─── Chest reward ──────────────────────────────────────────
$chestEarned = null;
if ($isWin) {
    $chestType = determineChestType([
        'mode'       => $mode,
        'difficulty' => $difficulty,
        'rating'     => $newRating,
        'streak'     => $newStreak,
    ]);
    $chestId   = awardChest($userId, $chestType);
    $chestMeta = getChestMeta()[$chestType];
    $chestEarned = [
        'id'        => $chestId,
        'type'      => $chestType,
        'name'      => $chestMeta['name'],
        'emoji'     => $chestMeta['emoji'],
        'color'     => $chestMeta['color'],
        'glow'      => $chestMeta['glow'],
    ];
}

// ─── Response ──────────────────────────────────────────────────────────────
$freshUser = Database::queryOne("SELECT xp, level FROM users WHERE id=?", [$userId]);

$response = [
    'new_rating'       => $newRating,
    'xp_gained'        => $xpGained,
    'xp_events'        => $xpEvents,
    'win_streak'       => $newStreak,
    'total_xp'         => (int)($freshUser['xp'] ?? 0),
    'new_level'        => (int)($freshUser['level'] ?? 1),
    'level_up'         => $xpResult ? $xpResult['level_up'] : false,
    'level_name'       => $xpResult ? $xpResult['level_name'] : getLevelName((int)($freshUser['level'] ?? 1)),
    'level_icon'       => $xpResult ? $xpResult['level_icon'] : getLevelIcon((int)($freshUser['level'] ?? 1)),
    'boost_active'     => $xpResult ? ($xpResult['boost_active'] ?? false) : false,
    'new_achievements' => $achievementDetails,
    'chest_earned'     => $chestEarned,
];

jsonSuccess($response);
