<?php
require_once __DIR__ . '/../config/app.php';
requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$gameId = (int)($data['game_id'] ?? 0);
$move = $data['move'] ?? null;
if (!$gameId || !$move) jsonError('Invalid data');

$game = Database::queryOne("SELECT * FROM games WHERE id=? AND (player1_id=? OR player2_id=?)", [$gameId, $_SESSION['user_id'], $_SESSION['user_id']]);
if (!$game) jsonError('Game not found', 404);

Database::execute(
    "INSERT INTO moves (game_id, player_id, move_number, from_row, from_col, to_row, to_col, captures) VALUES (?,?,?,?,?,?,?,?)",
    [
        $gameId, $_SESSION['user_id'],
        count(Database::query("SELECT id FROM moves WHERE game_id=?", [$gameId])) + 1,
        $move['from'][0], $move['from'][1],
        $move['to'][0], $move['to'][1],
        json_encode($move['captures'] ?? [])
    ]
);
jsonSuccess();
