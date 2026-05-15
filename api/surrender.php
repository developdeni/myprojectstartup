<?php
/**
 * api/surrender.php
 * Endpoint to surrender an active online game. Also triggered on tab close.
 */
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json');

if (!isLoggedIn()) jsonError('Not authenticated', 401);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$roomCode = $data['room_code'] ?? '';

if (empty($roomCode)) jsonError('Missing room code');

try {
    $game = Database::queryOne("SELECT * FROM games WHERE room_code = ? AND status = 'active'", [$roomCode]);
} catch (Exception $e) {
    jsonError('DB error', 500);
}

if (!$game) jsonSuccess(['message' => 'Game already finished or not active']);

$uid = (int)$_SESSION['user_id'];
$isP1 = (int)$game['player1_id'] === $uid;
$isP2 = (int)$game['player2_id'] === $uid;

if (!$isP1 && !$isP2) jsonError('Not in this game', 403);

$winnerId = $isP1 ? $game['player2_id'] : $game['player1_id'];
if (!$winnerId) $winnerId = null;

try {
    Database::execute(
        "UPDATE games SET status = 'abandoned', winner_id = ?, finished_at = NOW() WHERE id = ?",
        [$winnerId, $game['id']]
    );
} catch (Exception $e) {
    jsonError('Failed to surrender', 500);
}

jsonSuccess(['message' => 'Surrendered successfully']);
