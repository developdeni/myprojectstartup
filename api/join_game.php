<?php
/**
 * api/join_game.php
 * Player 2 joins an existing room. Returns which player number the current user is.
 */
require_once __DIR__ . '/../config/app.php';
requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roomCode = trim($data['room_code'] ?? $_GET['room_code'] ?? '');

if (empty($roomCode)) jsonError('Missing room_code');

$game = Database::queryOne("SELECT * FROM games WHERE room_code = ?", [$roomCode]);
if (!$game) jsonError('Room not found', 404);

$uid = (int)$_SESSION['user_id'];

// Already player 1
if ((int)$game['player1_id'] === $uid) {
    jsonSuccess(['player_num' => 1, 'game_id' => $game['id'], 'status' => $game['status']]);
}

// Already player 2
if ($game['player2_id'] && (int)$game['player2_id'] === $uid) {
    jsonSuccess(['player_num' => 2, 'game_id' => $game['id'], 'status' => $game['status']]);
}

// Join as player 2 if slot is free
if (!$game['player2_id']) {
    Database::execute(
        "UPDATE games SET player2_id = ?, status = 'active', updated_at = NOW() WHERE id = ?",
        [$uid, $game['id']]
    );
    jsonSuccess(['player_num' => 2, 'game_id' => $game['id'], 'status' => 'active']);
}

jsonError('Room is full', 403);
