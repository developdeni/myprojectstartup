<?php
/**
 * api/game_sync.php
 * Returns moves since a given move_number for an online game.
 * Public endpoint — no auth required (anyone with room code can observe).
 */
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json');

$roomCode = trim($_GET['room_code'] ?? '');
$since    = (int)($_GET['since'] ?? 0);

if (empty($roomCode)) jsonError('Missing room_code');

try {
    $game = Database::queryOne("SELECT * FROM games WHERE room_code = ?", [$roomCode]);
} catch (Exception $e) {
    jsonError('DB error: ' . $e->getMessage(), 500);
}

if (!$game) jsonError('Game not found', 404);

// Fetch moves since last known move_number
try {
    $rows = Database::query(
        "SELECT move_number, player_id, from_row, from_col, to_row, to_col, captures
         FROM moves
         WHERE game_id = ? AND move_number > ?
         ORDER BY move_number ASC",
        [$game['id'], $since]
    );
} catch (Exception $e) {
    jsonError('DB error reading moves: ' . $e->getMessage(), 500);
}

// Convert DB rows to JS move objects
$moves = array_map(function($r) {
    return [
        'move_number' => (int)$r['move_number'],
        'player_id'   => (int)$r['player_id'],
        'move' => [
            'from'     => [(int)$r['from_row'], (int)$r['from_col']],
            'to'       => [(int)$r['to_row'],   (int)$r['to_col']],
            'captures' => json_decode($r['captures'] ?? '[]', true) ?: [],
        ],
    ];
}, $rows);

try {
    $totalMoves = (int)Database::queryOne(
        "SELECT COUNT(*) as c FROM moves WHERE game_id = ?", [$game['id']]
    )['c'];
} catch (Exception $e) {
    $totalMoves = 0;
}

$currentTurn = ($totalMoves % 2 === 0) ? 1 : 2;

jsonSuccess([
    'status'       => $game['status'],
    'player1_id'   => (int)$game['player1_id'],
    'player2_id'   => $game['player2_id'] ? (int)$game['player2_id'] : null,
    'moves'        => $moves,
    'total_moves'  => $totalMoves,
    'current_turn' => $currentTurn,
]);
