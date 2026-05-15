<?php
/**
 * api/online_move.php
 * Save a move for an online game, validate it's the correct player's turn.
 */
require_once __DIR__ . '/../config/app.php';
requireAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roomCode = trim($data['room_code'] ?? '');
$move     = $data['move'] ?? null;

if (empty($roomCode) || !$move) jsonError('Missing data');

$game = Database::queryOne("SELECT * FROM games WHERE room_code = ?", [$roomCode]);
if (!$game) jsonError('Game not found', 404);
if ($game['status'] !== 'active') jsonError('Game not active');

$uid  = (int)$_SESSION['user_id'];
$isP1 = (int)$game['player1_id'] === $uid;
$isP2 = $game['player2_id'] && (int)$game['player2_id'] === $uid;
if (!$isP1 && !$isP2) jsonError('Not a participant', 403);

// Count existing moves to determine turn
$moveCount = (int)Database::queryOne(
    "SELECT COUNT(*) as c FROM moves WHERE game_id = ?", [$game['id']]
)['c'];

$isP1Turn = ($moveCount % 2 === 0); // P1 on even (0,2,4...), P2 on odd

if ($isP1Turn && !$isP1) jsonError('Not your turn');
if (!$isP1Turn && !$isP2) jsonError('Not your turn');

// Extract move fields
$from     = $move['from'] ?? [0, 0];
$to       = $move['to']   ?? [0, 0];
$captures = $move['captures'] ?? [];

Database::execute(
    "INSERT INTO moves (game_id, player_id, move_number, from_row, from_col, to_row, to_col, captures)
     VALUES (?,?,?,?,?,?,?,?)",
    [
        $game['id'], $uid, $moveCount + 1,
        $from[0], $from[1], $to[0], $to[1],
        json_encode($captures)
    ]
);

Database::execute("UPDATE games SET updated_at = NOW() WHERE id = ?", [$game['id']]);

jsonSuccess(['move_number' => $moveCount + 1, 'turn' => $isP1Turn ? 2 : 1]);
