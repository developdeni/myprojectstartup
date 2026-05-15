<?php
// ============================================
// App Bootstrap
// ============================================

require_once __DIR__ . '/database.php';

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
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return Database::queryOne(
        "SELECT id, username, email, avatar, skin, board_theme, is_pro, city, country, rating, wins, losses, draws, total_games FROM users WHERE id = ?",
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
