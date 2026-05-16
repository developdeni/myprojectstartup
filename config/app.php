<?php
// ============================================
// App Bootstrap
// ============================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/achievements.php';
require_once __DIR__ . '/streak.php';
require_once __DIR__ . '/chests.php';

// Session init
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        // If this is an API request, return JSON error
        if (
            isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json') ||
            isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        ) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}


function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return Database::queryOne(
        "SELECT id, username, email, avatar, skin, board_theme, is_pro, city, country, rating, wins, losses, draws, total_games, xp, level, win_streak FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

function redirect(string $path): void {
    header('Location: ' . APP_URL . $path);
    exit;
}

// CSRF
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Response helpers
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

function jsonSuccess(array $data = []): void {
    jsonResponse(array_merge(['success' => true], $data));
}

// Rating calculation (ELO)
function calculateElo(int $ratingA, int $ratingB, float $scoreA): array {
    $k = 32;
    $expectedA = 1 / (1 + pow(10, ($ratingB - $ratingA) / 400));
    $expectedB = 1 - $expectedA;
    $newA = round($ratingA + $k * ($scoreA - $expectedA));
    $newB = round($ratingB + $k * ((1 - $scoreA) - $expectedB));
    return [
        'new_a' => max(100, $newA),
        'new_b' => max(100, $newB),
        'change_a' => $newA - $ratingA,
        'change_b' => $newB - $ratingB,
    ];
}

// ============================================
// XP & Levels System
// ============================================

/**
 * Level thresholds: [level => min_xp_required]
 * 1=Bronze, 2=Silver, 3=Gold, 4=Platinum, 5=Grandmaster
 */
function getLevelThresholds(): array {
    return [
        1 => 0,
        2 => 200,
        3 => 500,
        4 => 1000,
        5 => 2000,
    ];
}

function getLevelName(int $level): string {
    return match($level) {
        1 => 'Bronze',
        2 => 'Silver',
        3 => 'Gold',
        4 => 'Platinum',
        5 => 'Grandmaster',
        default => 'Bronze',
    };
}

function getLevelIcon(int $level): string {
    return match($level) {
        1 => '🥉',
        2 => '🥈',
        3 => '🥇',
        4 => '💎',
        5 => '👑',
        default => '🥉',
    };
}

function getLevelColor(int $level): string {
    return match($level) {
        1 => '#cd7f32',
        2 => '#a8a9ad',
        3 => '#ffd700',
        4 => '#40e0d0',
        5 => '#9b59b6',
        default => '#cd7f32',
    };
}

/**
 * Calculate current level from total XP.
 * Returns ['level'=>int, 'name'=>str, 'xp_for_current'=>int, 'xp_for_next'=>int|null, 'progress'=>float 0-1]
 */
function calcLevelFromXp(int $xp): array {
    $thresholds = getLevelThresholds();
    $maxLevel = max(array_keys($thresholds));
    $level = 1;
    foreach ($thresholds as $lvl => $minXp) {
        if ($xp >= $minXp) $level = $lvl;
    }
    $xpCurrent = $thresholds[$level];
    $xpNext    = isset($thresholds[$level + 1]) ? $thresholds[$level + 1] : null;
    $progress  = ($xpNext !== null && $xpNext > $xpCurrent)
        ? min(1.0, ($xp - $xpCurrent) / ($xpNext - $xpCurrent))
        : 1.0;
    return [
        'level'       => $level,
        'name'        => getLevelName($level),
        'icon'        => getLevelIcon($level),
        'color'       => getLevelColor($level),
        'xp_current'  => $xpCurrent,
        'xp_next'     => $xpNext,
        'progress'    => $progress,
        'is_max'      => ($level === $maxLevel),
    ];
}

/**
 * Award XP to a user and update their level.
 * Returns ['xp_gained'=>int, 'total_xp'=>int, 'level_up'=>bool, 'new_level'=>int]
 */
function awardXp(int $userId, int $xpAmount, string $eventType, ?int $gameId = null): array {
    if ($xpAmount <= 0) return ['xp_gained' => 0, 'total_xp' => 0, 'level_up' => false, 'new_level' => 1];

    $user = Database::queryOne("SELECT xp, level, win_streak, xp_boost_until FROM users WHERE id = ?", [$userId]);
    if (!$user) return ['xp_gained' => 0, 'total_xp' => 0, 'level_up' => false, 'new_level' => 1];

    // Apply XP boost ×2 if active
    $boostActive = !empty($user['xp_boost_until']) && strtotime($user['xp_boost_until']) > time();
    $finalAmount = $boostActive ? $xpAmount * 2 : $xpAmount;

    $oldXp    = (int)($user['xp'] ?? 0);
    $oldLevel = (int)($user['level'] ?? 1);
    $newXp    = $oldXp + $finalAmount;
    $lvlInfo  = calcLevelFromXp($newXp);
    $newLevel = $lvlInfo['level'];

    Database::execute(
        "UPDATE users SET xp = ?, level = ? WHERE id = ?",
        [$newXp, $newLevel, $userId]
    );

    // Log the XP event (store actual awarded amount with boost note)
    $logType = $boostActive ? $eventType . '[boost×2]' : $eventType;
    Database::execute(
        "INSERT INTO xp_events (user_id, event_type, xp_awarded, game_id) VALUES (?, ?, ?, ?)",
        [$userId, $logType, $finalAmount, $gameId]
    );

    return [
        'xp_gained'   => $finalAmount,
        'xp_base'     => $xpAmount,
        'boost_active'=> $boostActive,
        'total_xp'    => $newXp,
        'level_up'    => ($newLevel > $oldLevel),
        'new_level'   => $newLevel,
        'level_name'  => getLevelName($newLevel),
        'level_icon'  => getLevelIcon($newLevel),
    ];
}

// Room code generator
function generateRoomCode(int $length = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Unique room code
function uniqueRoomCode(): string {
    do {
        $code = generateRoomCode();
        $existing = Database::queryOne("SELECT id FROM games WHERE room_code = ?", [$code]);
    } while ($existing);
    return $code;
}
